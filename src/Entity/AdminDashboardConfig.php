<?php

namespace App\Entity;

use App\Repository\AdminDashboardConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminDashboardConfigRepository::class)]
#[ORM\Table(name: 'admin_dashboard_config')]
#[ORM\UniqueConstraint(name: 'uniq_admin_dashboard_config_owner', columns: ['owner_id'])]
#[ORM\Index(columns: ['owner_id'], name: 'idx_admin_dashboard_config_owner')]
class AdminDashboardConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $owner = null;

    /** @var array{widgets?: array<int, array>, nav?: array<int, array>} */
    #[ORM\Column(type: Types::JSON)]
    private array $configJson = [];

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    /** @return array{widgets?: array<int, array>, nav?: array<int, array>} */
    public function getConfigJson(): array
    {
        return $this->configJson;
    }

    /** @param array{widgets?: array<int, array>, nav?: array<int, array>} $configJson */
    public function setConfigJson(array $configJson): self
    {
        $this->configJson = $configJson;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
