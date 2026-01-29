<?php

namespace App\Entity;

use App\Repository\ProductMediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductMediaRepository::class)]
#[ORM\Table(name: 'product_media')]
#[ORM\Index(columns: ['product_id'], name: 'idx_product_media_product')]
#[ORM\Index(columns: ['product_id', 'sort'], name: 'idx_product_media_product_sort')]
class ProductMedia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'media')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $path;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $sort = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;
        return $this;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function setSort(int $sort): self
    {
        $this->sort = $sort;
        return $this;
    }
}

