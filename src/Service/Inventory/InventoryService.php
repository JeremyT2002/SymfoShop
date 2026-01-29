<?php

namespace App\Service\Inventory;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderReservation;
use App\Entity\ProductVariant;
use App\Entity\StockItem;
use App\Repository\OrderReservationRepository;
use App\Repository\StockItemRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

class InventoryService
{
    private const RESERVATION_TIMEOUT_MINUTES = 15;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockItemRepository $stockItemRepository,
        private readonly OrderReservationRepository $reservationRepository
    ) {
    }

    /**
     * Reserve inventory for an order
     *
     * @return array{success: bool, errors: string[]}
     */
    public function reserve(Order $order): array
    {
        $errors = [];
        $reservations = [];

        $this->entityManager->beginTransaction();

        try {
            foreach ($order->getItems() as $orderItem) {
                $variant = $this->findVariantBySku($orderItem->getSku());

                if (!$variant) {
                    $errors[] = 'Variant not found for SKU: ' . $orderItem->getSku();
                    continue;
                }

                $stockItem = $this->getOrCreateStockItem($variant);

                // Use pessimistic lock for reservation
                $this->entityManager->lock($stockItem, LockMode::PESSIMISTIC_WRITE);
                $this->entityManager->refresh($stockItem);

                $available = $stockItem->getAvailable();
                $required = $orderItem->getQuantity();

                if ($available < $required) {
                    $errors[] = sprintf(
                        'Insufficient stock for %s. Available: %d, Required: %d',
                        $orderItem->getSku(),
                        $available,
                        $required
                    );
                    continue;
                }

                // Reserve inventory
                $stockItem->setReserved($stockItem->getReserved() + $required);
                $this->entityManager->persist($stockItem);

                // Create reservation record
                $reservation = new OrderReservation();
                $reservation->setOrder($order);
                $reservation->setVariant($variant);
                $reservation->setQuantity($required);
                $reservation->setExpiresAt(
                    (new \DateTimeImmutable())->modify('+' . self::RESERVATION_TIMEOUT_MINUTES . ' minutes')
                );
                $this->entityManager->persist($reservation);
                $reservations[] = $reservation;
            }

            if (!empty($errors)) {
                $this->entityManager->rollback();
                return ['success' => false, 'errors' => $errors];
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return ['success' => true, 'errors' => []];
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return ['success' => false, 'errors' => ['Reservation failed: ' . $e->getMessage()]];
        }
    }

    /**
     * Commit reserved inventory (convert reservation to actual stock reduction)
     */
    public function commit(Order $order): void
    {
        $this->entityManager->beginTransaction();

        try {
            $reservations = $this->reservationRepository->findByOrder($order);

            foreach ($reservations as $reservation) {
                $variant = $reservation->getVariant();
                $stockItem = $this->stockItemRepository->findOneByVariantForUpdate($variant);

                if (!$stockItem) {
                    continue;
                }

                // Reduce on-hand and reserved quantities
                $stockItem->setOnHand($stockItem->getOnHand() - $reservation->getQuantity());
                $stockItem->setReserved($stockItem->getReserved() - $reservation->getQuantity());

                $this->entityManager->persist($stockItem);
                $this->entityManager->remove($reservation);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw new \RuntimeException('Failed to commit inventory: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Release reserved inventory back to available stock
     */
    public function release(Order $order): void
    {
        $this->entityManager->beginTransaction();

        try {
            $reservations = $this->reservationRepository->findByOrder($order);

            foreach ($reservations as $reservation) {
                $variant = $reservation->getVariant();
                $stockItem = $this->stockItemRepository->findOneByVariantForUpdate($variant);

                if (!$stockItem) {
                    continue;
                }

                // Release reserved quantity
                $stockItem->setReserved($stockItem->getReserved() - $reservation->getQuantity());

                $this->entityManager->persist($stockItem);
                $this->entityManager->remove($reservation);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw new \RuntimeException('Failed to release inventory: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Release expired reservations
     *
     * @return int Number of reservations released
     */
    public function releaseExpiredReservations(): int
    {
        $expired = $this->reservationRepository->findExpired();
        $count = 0;

        foreach ($expired as $reservation) {
            $this->entityManager->beginTransaction();

            try {
                $variant = $reservation->getVariant();
                $stockItem = $this->stockItemRepository->findOneByVariantForUpdate($variant);

                if ($stockItem) {
                    $stockItem->setReserved($stockItem->getReserved() - $reservation->getQuantity());
                    $this->entityManager->persist($stockItem);
                }

                $this->entityManager->remove($reservation);
                $this->entityManager->flush();
                $this->entityManager->commit();
                $count++;
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                // Continue processing other expired reservations
            }
        }

        return $count;
    }

    /**
     * Get stock item for variant (public for validation)
     */
    public function getStockItem(ProductVariant $variant): ?StockItem
    {
        return $this->stockItemRepository->findOneByVariant($variant);
    }

    /**
     * Get or create stock item for variant
     */
    private function getOrCreateStockItem(ProductVariant $variant): StockItem
    {
        $stockItem = $this->stockItemRepository->findOneByVariant($variant);

        if (!$stockItem) {
            $stockItem = new StockItem();
            $stockItem->setVariant($variant);
            $stockItem->setOnHand(0);
            $stockItem->setReserved(0);
            $this->entityManager->persist($stockItem);
        }

        return $stockItem;
    }

    /**
     * Find variant by SKU (helper method)
     */
    private function findVariantBySku(string $sku): ?ProductVariant
    {
        return $this->entityManager->getRepository(ProductVariant::class)->findOneBy(['sku' => $sku]);
    }
}

