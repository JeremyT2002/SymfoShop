<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupportMessageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupportMessageRepository::class)]
#[ORM\Table(name: 'support_message')]
#[ORM\Index(columns: ['conversation_id', 'id'], name: 'idx_support_message_conversation_id')]
class SupportMessage
{
    public const SENDER_CUSTOMER = 'customer';
    public const SENDER_SUPPORT = 'support';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SupportConversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'conversation_id', nullable: false, onDelete: 'CASCADE')]
    private ?SupportConversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $senderUser = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $senderType = self::SENDER_CUSTOMER;

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, SupportAttachment> */
    #[ORM\OneToMany(mappedBy: 'message', targetEntity: SupportAttachment::class, orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): ?SupportConversation
    {
        return $this->conversation;
    }

    public function setConversation(SupportConversation $conversation): self
    {
        $this->conversation = $conversation;
        return $this;
    }

    public function getSenderUser(): ?User
    {
        return $this->senderUser;
    }

    public function setSenderUser(?User $senderUser): self
    {
        $this->senderUser = $senderUser;
        return $this;
    }

    public function getSenderType(): string
    {
        return $this->senderType;
    }

    public function setSenderType(string $senderType): self
    {
        $this->senderType = $senderType;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = trim($body);
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, SupportAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }
}

