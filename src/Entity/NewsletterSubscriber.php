<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NewsletterSubscriberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Newsletter subscription with double opt-in: only confirmed, not unsubscribed entries receive mail.
 */
#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\Table(name: 'newsletter_subscriber')]
#[ORM\UniqueConstraint(name: 'uniq_newsletter_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_newsletter_token', columns: ['token'])]
class NewsletterSubscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    /** Random secret used in the confirmation and unsubscribe links */
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $token;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $locale = 'en';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    public function __construct(string $email, string $locale = 'en')
    {
        $this->email = mb_strtolower(trim($email));
        $this->locale = $locale;
        $this->createdAt = new \DateTimeImmutable();
        $this->regenerateToken();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function regenerateToken(): void
    {
        $this->token = bin2hex(random_bytes(24));
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getUnsubscribedAt(): ?\DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function isActive(): bool
    {
        return $this->confirmedAt !== null && $this->unsubscribedAt === null;
    }

    public function confirm(): void
    {
        $this->confirmedAt ??= new \DateTimeImmutable();
        $this->unsubscribedAt = null;
    }

    /** A new sign-up after unsubscribing starts the double opt-in again */
    public function restart(): void
    {
        $this->confirmedAt = null;
        $this->unsubscribedAt = null;
        $this->regenerateToken();
    }

    public function unsubscribe(): void
    {
        $this->unsubscribedAt ??= new \DateTimeImmutable();
    }
}
