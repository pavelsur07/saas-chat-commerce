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
#[ORM\Index(name: 'idx_ai_scenario_company_slug_status', columns: ['company_id','slug','status'])]
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

    // getters/setters...
}
