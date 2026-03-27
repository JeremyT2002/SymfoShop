<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupportAttachmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupportAttachmentRepository::class)]
#[ORM\Table(name: 'support_attachment')]
class SupportAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SupportMessage::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(name: 'message_id', nullable: false, onDelete: 'CASCADE')]
    private ?SupportMessage $message = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $originalName = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $storedName = '';

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $mimeType = '';

    #[ORM\Column(type: Types::INTEGER)]
    private int $sizeBytes = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?SupportMessage
    {
        return $this->message;
    }

    public function setMessage(SupportMessage $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $name): self
    {
        $this->originalName = $name;
        return $this;
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function setStoredName(string $storedName): self
    {
        $this->storedName = $storedName;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): self
    {
        $this->sizeBytes = $sizeBytes;
        return $this;
    }
}

