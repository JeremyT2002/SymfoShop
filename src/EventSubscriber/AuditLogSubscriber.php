<?php

namespace App\EventSubscriber;

use App\Entity\AuditLog;
use App\Entity\Order;
use App\Entity\Product;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AuditLogSubscriber implements EventSubscriber
{
    private array $entitiesToTrack = [
        Order::class => 'Order',
        Product::class => 'Product',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?Security $security = null
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
        ];
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityClass = get_class($entity);

        // Only track specific entities
        if (!isset($this->entitiesToTrack[$entityClass])) {
            return;
        }

        $entityType = $this->entitiesToTrack[$entityClass];
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;

        // Get user identifier
        $userIdentifier = null;
        if ($this->security && $this->security->getUser()) {
            $user = $this->security->getUser();
            $userIdentifier = method_exists($user, 'getUserIdentifier') 
                ? $user->getUserIdentifier() 
                : (method_exists($user, 'getUsername') ? $user->getUsername() : 'admin');
        }

        // Log each changed field
        foreach ($args->getEntityChangeSet() as $fieldName => $change) {
            [$oldValue, $newValue] = $change;

            // Skip version field (optimistic locking)
            if ($fieldName === 'version') {
                continue;
            }

            // Convert values to string
            $oldValueStr = $this->convertValueToString($oldValue);
            $newValueStr = $this->convertValueToString($newValue);

            // Create audit log entry
            $auditLog = new AuditLog();
            $auditLog->setEntityType($entityType);
            $auditLog->setEntityId($entityId);
            $auditLog->setAction('update');
            $auditLog->setChangedField($fieldName);
            $auditLog->setOldValue($oldValueStr);
            $auditLog->setNewValue($newValueStr);
            $auditLog->setUserIdentifier($userIdentifier);

            // Use a separate entity manager to avoid recursion
            $this->entityManager->persist($auditLog);
        }
    }

    private function convertValueToString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            if (method_exists($value, 'getId')) {
                return get_class($value) . '#' . $value->getId();
            }
            return get_class($value);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}

