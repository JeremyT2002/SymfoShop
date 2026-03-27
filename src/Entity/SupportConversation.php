<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupportConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupportConversationRepository::class)]
#[ORM\Table(name: 'support_conversation')]
#[ORM\Index(columns: ['status', 'updated_at'], name: 'idx_support_conversation_status_updated')]
class SupportConversation
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const CATEGORY_ORDER = 'order';
    public const CATEGORY_PAYMENT = 'payment';
    public const CATEGORY_SHIPPING = 'shipping';
    public const CATEGORY_PRODUCT = 'product';
    public const CATEGORY_TECHNICAL = 'technical';
    public const CATEGORY_OTHER = 'other';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $customer = null;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $subject = '';

    #[ORM\Column(type: Types::STRING, length: 30, options: ['default' => self::CATEGORY_OTHER])]
    private string $category = self::CATEGORY_OTHER;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $relatedOrderNumber = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => self::STATUS_OPEN])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $customerUnreadCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $supporterUnreadCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, SupportMessage> */
    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: SupportMessage::class, orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?User
    {
        return $this->customer;
    }

    public function setCustomer(User $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = trim($subject);
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = trim($category) !== '' ? trim($category) : self::CATEGORY_OTHER;
        return $this;
    }

    public function getRelatedOrderNumber(): ?string
    {
        return $this->relatedOrderNumber;
    }

    public function setRelatedOrderNumber(?string $relatedOrderNumber): self
    {
        $value = $relatedOrderNumber !== null ? trim($relatedOrderNumber) : null;
        $this->relatedOrderNumber = $value !== '' ? $value : null;
        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCustomerUnreadCount(): int
    {
        return $this->customerUnreadCount;
    }

    public function setCustomerUnreadCount(int $count): self
    {
        $this->customerUnreadCount = max(0, $count);
        return $this;
    }

    public function getSupporterUnreadCount(): int
    {
        return $this->supporterUnreadCount;
    }

    public function setSupporterUnreadCount(int $count): self
    {
        $this->supporterUnreadCount = max(0, $count);
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * @return Collection<int, SupportMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }
}

