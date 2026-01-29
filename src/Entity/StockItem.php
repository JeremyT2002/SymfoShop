<?php

namespace App\Entity;

use App\Repository\StockItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockItemRepository::class)]
#[ORM\Table(name: 'stock_item')]
#[ORM\Index(columns: ['variant_id'], name: 'idx_stock_item_variant')]
class StockItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: ProductVariant::class, inversedBy: 'stockItem')]
    #[ORM\JoinColumn(name: 'variant_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private ProductVariant $variant;

    #[ORM\Column(type: Types::INTEGER)]
    private int $onHand = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $reserved = 0;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $version = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVariant(): ProductVariant
    {
        return $this->variant;
    }

    public function setVariant(ProductVariant $variant): self
    {
        $this->variant = $variant;
        return $this;
    }

    public function getOnHand(): int
    {
        return $this->onHand;
    }

    public function setOnHand(int $onHand): self
    {
        $this->onHand = $onHand;
        return $this;
    }

    public function getReserved(): int
    {
        return $this->reserved;
    }

    public function setReserved(int $reserved): self
    {
        $this->reserved = $reserved;
        return $this;
    }

    public function getAvailable(): int
    {
        return $this->onHand - $this->reserved;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }
}

