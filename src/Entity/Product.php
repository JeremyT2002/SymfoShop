<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
#[ORM\Index(columns: ['slug'], name: 'idx_product_slug')]
#[ORM\Index(columns: ['status'], name: 'idx_product_status')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: ProductStatus::class)]
    private ProductStatus $status;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $taxClass;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $seoTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $seoDescription = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $seoNoIndex = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductVariant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $variants;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductMedia::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sort' => 'ASC'])]
    private Collection $media;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductReview::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $reviews;

    public function __construct()
    {
        $this->variants = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->taxClass = 'standard';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        try {
            return $this->name;
        } catch (\Error $e) {
            return null;
        }
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): ?ProductStatus
    {
        try {
            return $this->status;
        } catch (\Error $e) {
            return null;
        }
    }

    public function setStatus(ProductStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getTaxClass(): string
    {
        return $this->taxClass;
    }

    public function setTaxClass(string $taxClass): self
    {
        $this->taxClass = $taxClass;
        return $this;
    }

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function setSeoTitle(?string $seoTitle): self
    {
        $this->seoTitle = $seoTitle;
        return $this;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function setSeoDescription(?string $seoDescription): self
    {
        $this->seoDescription = $seoDescription;
        return $this;
    }

    public function isSeoNoIndex(): bool
    {
        return $this->seoNoIndex;
    }

    public function setSeoNoIndex(bool $seoNoIndex): self
    {
        $this->seoNoIndex = $seoNoIndex;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(ProductVariant $variant): self
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setProduct($this);
        }
        return $this;
    }

    public function removeVariant(ProductVariant $variant): self
    {
        if ($this->variants->removeElement($variant)) {
            if ($variant->getProduct() === $this) {
                $variant->setProduct(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ProductMedia>
     */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedia(ProductMedia $media): self
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->setProduct($this);
        }
        return $this;
    }

    public function removeMedia(ProductMedia $media): self
    {
        if ($this->media->removeElement($media)) {
            if ($media->getProduct() === $this) {
                $media->setProduct(null);
            }
        }
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Collection<int, ProductReview>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(ProductReview $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setProduct($this);
        }

        return $this;
    }

    public function removeReview(ProductReview $review): self
    {
        if ($this->reviews->removeElement($review) && $review->getProduct() === $this) {
            $review->setProduct(null);
        }

        return $this;
    }

    public function getAverageRating(): ?float
    {
        $sum = 0;
        $count = 0;

        foreach ($this->reviews as $review) {
            if (!$review->isApproved()) {
                continue;
            }

            $sum += $review->getRating();
            $count++;
        }

        if ($count === 0) {
            return null;
        }

        return round($sum / $count, 2);
    }

    public function getReviewCount(): int
    {
        $count = 0;
        foreach ($this->reviews as $review) {
            if ($review->isApproved()) {
                $count++;
            }
        }

        return $count;
    }

    public function getStockOnHand(): int
    {
        $onHand = 0;
        foreach ($this->variants as $variant) {
            $stockItem = $variant->getStockItem();
            if ($stockItem !== null) {
                $onHand += $stockItem->getOnHand();
            }
        }

        return $onHand;
    }

    public function getStockAvailable(): int
    {
        $available = 0;
        foreach ($this->variants as $variant) {
            $stockItem = $variant->getStockItem();
            if ($stockItem !== null) {
                $available += $stockItem->getAvailable();
            }
        }

        return $available;
    }
}

