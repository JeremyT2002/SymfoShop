<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\Index(columns: ['order_number'], name: 'idx_order_order_number')]
#[ORM\Index(columns: ['email'], name: 'idx_order_email')]
#[ORM\Index(columns: ['status'], name: 'idx_order_status')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    private string $orderNumber;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    // Customer and shipping address snapshot taken at checkout
    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'phone', type: Types::STRING, length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'shipping_street', type: Types::STRING, length: 255, nullable: true)]
    private ?string $shippingStreet = null;

    #[ORM\Column(name: 'shipping_postal_code', type: Types::STRING, length: 20, nullable: true)]
    private ?string $shippingPostalCode = null;

    #[ORM\Column(name: 'shipping_city', type: Types::STRING, length: 120, nullable: true)]
    private ?string $shippingCity = null;

    #[ORM\Column(name: 'shipping_state', type: Types::STRING, length: 120, nullable: true)]
    private ?string $shippingState = null;

    #[ORM\Column(name: 'shipping_country', type: Types::STRING, length: 2, nullable: true)]
    private ?string $shippingCountry = null;

    /** Request locale at checkout (ISO language, e.g. en, de, fr) for transactional emails */
    #[ORM\Column(type: Types::STRING, length: 10, options: ['default' => 'en'])]
    private string $locale = 'en';

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $status = 'new';

    #[ORM\Column(type: Types::INTEGER)]
    private int $subtotal;

    #[ORM\Column(type: Types::INTEGER)]
    private int $taxTotal;

    #[ORM\Column(type: Types::INTEGER)]
    private int $grandTotal;

    /** Coupon applied at checkout (snapshot of the code, the coupon itself may change later) */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $couponCode = null;

    /** Discount in minor units, subtracted from the subtotal before shipping and tax */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $discountTotal = 0;

    /**
     * true: amounts are gross and taxTotal is the VAT they contain (orders since gross pricing);
     * false: legacy orders where taxTotal was added on top of net amounts.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $pricesIncludeTax = false;

    /** Shipping in minor units (excl. VAT), before tax */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $shippingAmount = 0;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $shippingMethodCode = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    private ?string $shippingMethodLabel = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $carrier = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: Payment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $payments;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): self
    {
        $this->orderNumber = $orderNumber;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName !== null && trim($firstName) !== '' ? trim($firstName) : null;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName !== null && trim($lastName) !== '' ? trim($lastName) : null;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone !== null && trim($phone) !== '' ? trim($phone) : null;
        return $this;
    }

    public function getShippingStreet(): ?string
    {
        return $this->shippingStreet;
    }

    public function setShippingStreet(?string $shippingStreet): self
    {
        $this->shippingStreet = $shippingStreet !== null && trim($shippingStreet) !== '' ? trim($shippingStreet) : null;
        return $this;
    }

    public function getShippingPostalCode(): ?string
    {
        return $this->shippingPostalCode;
    }

    public function setShippingPostalCode(?string $shippingPostalCode): self
    {
        $this->shippingPostalCode = $shippingPostalCode !== null && trim($shippingPostalCode) !== '' ? trim($shippingPostalCode) : null;
        return $this;
    }

    public function getShippingCity(): ?string
    {
        return $this->shippingCity;
    }

    public function setShippingCity(?string $shippingCity): self
    {
        $this->shippingCity = $shippingCity !== null && trim($shippingCity) !== '' ? trim($shippingCity) : null;
        return $this;
    }

    public function getShippingState(): ?string
    {
        return $this->shippingState;
    }

    public function setShippingState(?string $shippingState): self
    {
        $this->shippingState = $shippingState !== null && trim($shippingState) !== '' ? trim($shippingState) : null;
        return $this;
    }

    public function getShippingCountry(): ?string
    {
        return $this->shippingCountry;
    }

    public function setShippingCountry(?string $shippingCountry): self
    {
        $this->shippingCountry = $shippingCountry !== null && trim($shippingCountry) !== '' ? trim($shippingCountry) : null;
        return $this;
    }

    public function getCustomerName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
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

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
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

    public function getSubtotal(): int
    {
        return $this->subtotal;
    }

    public function setSubtotal(int $subtotal): self
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    public function getTaxTotal(): int
    {
        return $this->taxTotal;
    }

    public function setTaxTotal(int $taxTotal): self
    {
        $this->taxTotal = $taxTotal;
        return $this;
    }

    public function getGrandTotal(): int
    {
        return $this->grandTotal;
    }

    public function setGrandTotal(int $grandTotal): self
    {
        $this->grandTotal = $grandTotal;
        return $this;
    }

    public function getCouponCode(): ?string
    {
        return $this->couponCode;
    }

    public function setCouponCode(?string $couponCode): self
    {
        $this->couponCode = $couponCode;
        return $this;
    }

    public function isPricesIncludeTax(): bool
    {
        return $this->pricesIncludeTax;
    }

    public function setPricesIncludeTax(bool $pricesIncludeTax): self
    {
        $this->pricesIncludeTax = $pricesIncludeTax;
        return $this;
    }

    public function getDiscountTotal(): int
    {
        return $this->discountTotal;
    }

    public function setDiscountTotal(int $discountTotal): self
    {
        $this->discountTotal = $discountTotal;
        return $this;
    }

    public function getShippingAmount(): int
    {
        return $this->shippingAmount;
    }

    public function setShippingAmount(int $shippingAmount): self
    {
        $this->shippingAmount = max(0, $shippingAmount);
        return $this;
    }

    public function getShippingMethodCode(): ?string
    {
        return $this->shippingMethodCode;
    }

    public function setShippingMethodCode(?string $shippingMethodCode): self
    {
        $this->shippingMethodCode = $shippingMethodCode !== null && $shippingMethodCode !== ''
            ? mb_strtolower(trim($shippingMethodCode))
            : null;
        return $this;
    }

    public function getShippingMethodLabel(): ?string
    {
        return $this->shippingMethodLabel;
    }

    public function setShippingMethodLabel(?string $shippingMethodLabel): self
    {
        $this->shippingMethodLabel = $shippingMethodLabel !== null && $shippingMethodLabel !== ''
            ? trim($shippingMethodLabel)
            : null;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }
        return $this;
    }

    public function removeItem(OrderItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOrder() === $this) {
                $item->setOrder(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setOrder($this);
        }
        return $this;
    }

    public function removePayment(Payment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getOrder() === $this) {
                $payment->setOrder(null);
            }
        }
        return $this;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): self
    {
        $this->trackingNumber = $trackingNumber;
        return $this;
    }

    public function getCarrier(): ?string
    {
        return $this->carrier;
    }

    public function setCarrier(?string $carrier): self
    {
        $this->carrier = $carrier;
        return $this;
    }

    public function getShippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function setShippedAt(?\DateTimeImmutable $shippedAt): self
    {
        $this->shippedAt = $shippedAt;
        return $this;
    }
}

