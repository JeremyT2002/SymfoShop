<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RefundRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Money paid back to the customer. Every refund gets its own credit note number (CN-YYYY-NNNN),
 * the PDF is rendered on download like invoices.
 */
#[ORM\Entity(repositoryClass: RefundRepository::class)]
#[ORM\Table(name: 'refund')]
#[ORM\Index(columns: ['order_id'], name: 'idx_refund_order')]
class Refund
{
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_PENDING = 'pending';

    /** Refunds paid back outside the payment provider (e.g. bank transfer). */
    public const PROVIDER_MANUAL = 'manual';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: ReturnRequest::class)]
    #[ORM\JoinColumn(name: 'return_request_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ReturnRequest $returnRequest = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    private string $creditNoteNumber;

    /** Gross amount in minor units. */
    #[ORM\Column(type: Types::INTEGER)]
    private int $amount;

    /** VAT contained in the amount (same share as on the invoice). */
    #[ORM\Column(type: Types::INTEGER)]
    private int $taxAmount = 0;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::TEXT)]
    private string $reason = '';

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $provider;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $providerReference = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = self::STATUS_SUCCEEDED;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $restocked = false;

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    private ?string $createdBy = null;

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

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getReturnRequest(): ?ReturnRequest
    {
        return $this->returnRequest;
    }

    public function setReturnRequest(?ReturnRequest $returnRequest): self
    {
        $this->returnRequest = $returnRequest;

        return $this;
    }

    public function getCreditNoteNumber(): string
    {
        return $this->creditNoteNumber;
    }

    public function setCreditNoteNumber(string $creditNoteNumber): self
    {
        $this->creditNoteNumber = $creditNoteNumber;

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getTaxAmount(): int
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(int $taxAmount): self
    {
        $this->taxAmount = $taxAmount;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    public function setProviderReference(?string $providerReference): self
    {
        $this->providerReference = $providerReference;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isRestocked(): bool
    {
        return $this->restocked;
    }

    public function setRestocked(bool $restocked): self
    {
        $this->restocked = $restocked;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
