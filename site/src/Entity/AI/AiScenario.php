<?php

namespace App\Entity\AI;

use App\Entity\Ai\Enum\ScenarioStatus;
use App\Entity\AI\Traits\Timestampable;
use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Repository\AI\AiScenarioRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiScenarioRepository::class)]
#[ORM\Table(name: 'ai_scenario')]
#[ORM\UniqueConstraint(name: 'uniq_company_slug_version', columns: ['company_id', 'slug', 'version'])]
#[ORM\Index(name: 'idx_ai_scenario_company_slug_status', columns: ['company_id', 'slug', 'status'])]
class AiScenario
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    #[ORM\Column(type: 'string', length: 160)]
    private string $name;

    #[ORM\Column(type: 'string', length: 160)]
    private string $slug;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(enumType: ScenarioStatus::class)]
    private ScenarioStatus $status = ScenarioStatus::DRAFT;

    #[ORM\Column(type: 'jsonb')]
    private array $graph; // совместимо с форматом React Flow (nodes, edges, viewport,...)

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct(string $id, Company $company, string $name, string $slug, array $graph)
    {
        $this->id = $id;
        $this->company = $company;
        $this->name = $name;
        $this->slug = $slug;
        $this->graph = $graph;
        $this->touchCreated();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getStatus(): ScenarioStatus
    {
        return $this->status;
    }

    public function setStatus(ScenarioStatus $status): void
    {
        $this->status = $status;
    }

    public function getGraph(): array
    {
        return $this->graph;
    }

    public function setGraph(array $graph): void
    {
        $this->graph = $graph;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }
}
