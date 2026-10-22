<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\StockItem;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Changes applied to many products at once from the admin product list.
 * Price actions work on gross prices of all variants of the selected products.
 */
final class ProductBulkEditor
{
    public const ACTIONS = ['status', 'price_percent', 'sale_start', 'sale_end', 'stock_set'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<Product> $products
     *
     * @throws \InvalidArgumentException for unknown actions or invalid values
     */
    public function apply(array $products, string $action, string $value): int
    {
        $apply = match ($action) {
            'status' => $this->status($value),
            'price_percent' => $this->changePrice($this->percent($value, -99, 1000)),
            'sale_start' => $this->startSale($this->percent($value, 1, 99)),
            'sale_end' => $this->endSale(...),
            'stock_set' => $this->setStock($this->stock($value)),
            default => throw new \InvalidArgumentException(sprintf('Unknown bulk action "%s".', $action)),
        };

        $now = new \DateTimeImmutable();
        foreach ($products as $product) {
            $apply($product);
            $product->setUpdatedAt($now);
        }
        $this->entityManager->flush();

        return \count($products);
    }

    private function status(string $value): \Closure
    {
        $status = ProductStatus::tryFrom($value) ?? throw new \InvalidArgumentException('Unknown status.');

        return static fn (Product $product) => $product->setStatus($status);
    }

    /** +10 = 10 % more expensive, -10 = 10 % cheaper (rounded to whole cents). */
    private function changePrice(float $percent): \Closure
    {
        return static function (Product $product) use ($percent): void {
            foreach ($product->getVariants() as $variant) {
                $variant->setPriceAmount(max(0, (int) round($variant->getPriceAmount() * (1 + $percent / 100))));
            }
        };
    }

    /** Reduces the price by $percent and shows the current price as compare-at ("was") price. */
    private function startSale(float $percent): \Closure
    {
        return static function (Product $product) use ($percent): void {
            foreach ($product->getVariants() as $variant) {
                $regular = $variant->getCompareAtPriceAmount() ?? $variant->getPriceAmount();
                $variant->setCompareAtPriceAmount($regular);
                $variant->setPriceAmount((int) round($regular * (1 - $percent / 100)));
            }
        };
    }

    /** Back to the regular price. */
    private function endSale(Product $product): void
    {
        foreach ($product->getVariants() as $variant) {
            if ($variant->getCompareAtPriceAmount() !== null) {
                $variant->setPriceAmount($variant->getCompareAtPriceAmount());
                $variant->setCompareAtPriceAmount(null);
            }
        }
    }

    private function setStock(int $onHand): \Closure
    {
        return function (Product $product) use ($onHand): void {
            foreach ($product->getVariants() as $variant) {
                $stockItem = $variant->getStockItem();
                if ($stockItem === null) {
                    $stockItem = (new StockItem())->setVariant($variant)->setReserved(0);
                    $variant->setStockItem($stockItem);
                    $this->entityManager->persist($stockItem);
                }
                $stockItem->setOnHand($onHand);
            }
        };
    }

    private function percent(string $value, float $min, float $max): float
    {
        $percent = filter_var(str_replace(',', '.', trim($value)), \FILTER_VALIDATE_FLOAT);
        if ($percent === false || $percent < $min || $percent > $max || $percent == 0) {
            throw new \InvalidArgumentException(sprintf('Enter a percentage between %s and %s.', $min, $max));
        }

        return $percent;
    }

    private function stock(string $value): int
    {
        $stock = filter_var(trim($value), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($stock === false) {
            throw new \InvalidArgumentException('Enter a stock quantity of 0 or more.');
        }

        return $stock;
    }
}
