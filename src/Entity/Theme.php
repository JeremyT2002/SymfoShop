<?php

namespace App\Entity;

use App\Repository\ThemeRepository;
use App\Theme\ThemeConfig;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThemeRepository::class)]
#[ORM\Table(name: 'theme')]
#[ORM\UniqueConstraint(name: 'uniq_theme_shop_slug', columns: ['shop_id', 'slug'])]
#[ORM\Index(columns: ['shop_id'], name: 'idx_theme_shop')]
#[ORM\Index(columns: ['status'], name: 'idx_theme_status')]
class Theme
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shop::class, inversedBy: 'themes')]
    #[ORM\JoinColumn(name: 'shop_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Shop $shop = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $slug;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'draft'])]
    private string $status = self::STATUS_DRAFT;

    /** @var array<string, mixed> Theme configuration (colors, fonts, layout, etc.) */
    #[ORM\Column(type: Types::JSON)]
    #[ThemeConfig]
    private array $config = [];

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ThemeRevision> */
    #[ORM\OneToMany(mappedBy: 'theme', targetEntity: ThemeRevision::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['version' => 'DESC'])]
    private Collection $revisions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->revisions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): self
    {
        $this->shop = $shop;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): self
    {
        $this->config = $config;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
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

    public function incrementVersion(): self
    {
        $this->version++;
        $this->updatedAt = new \DateTimeImmutable();
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

    /** @return Collection<int, ThemeRevision> */
    public function getRevisions(): Collection
    {
        return $this->revisions;
    }

    public function addRevision(ThemeRevision $revision): self
    {
        if (!$this->revisions->contains($revision)) {
            $this->revisions->add($revision);
            $revision->setTheme($this);
        }
        return $this;
    }

    public function removeRevision(ThemeRevision $revision): self
    {
        if ($this->revisions->removeElement($revision) && $revision->getTheme() === $this) {
            $revision->setTheme(null);
        }
        return $this;
    }
}
