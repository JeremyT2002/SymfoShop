<?php

namespace App\Entity;

use App\Repository\StockNotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockNotificationRepository::class)]
#[ORM\Table(name: 'stock_notification')]
#[ORM\Index(columns: ['product_variant_id', 'notified_at'], name: 'idx_stock_notification_variant_notified')]
#[ORM\Index(columns: ['confirmation_token'], name: 'idx_stock_notification_token')]
class StockNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(name: 'product_variant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ProductVariant $productVariant = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $confirmationToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProductVariant(): ?ProductVariant { return $this->productVariant; }
    public function setProductVariant(?ProductVariant $productVariant): self { $this->productVariant = $productVariant; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = mb_strtolower(trim($email)); return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getNotifiedAt(): ?\DateTimeImmutable { return $this->notifiedAt; }
    public function setNotifiedAt(?\DateTimeImmutable $notifiedAt): self { $this->notifiedAt = $notifiedAt; return $this; }
    public function getConfirmationToken(): ?string { return $this->confirmationToken; }
    public function setConfirmationToken(?string $confirmationToken): self { $this->confirmationToken = $confirmationToken; return $this; }
    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): self { $this->confirmedAt = $confirmedAt; return $this; }
    public function getTokenExpiresAt(): ?\DateTimeImmutable { return $this->tokenExpiresAt; }
    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): self { $this->tokenExpiresAt = $tokenExpiresAt; return $this; }

    public function isConfirmed(): bool
    {
        return $this->confirmedAt !== null;
    }
}

