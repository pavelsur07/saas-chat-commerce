<?php

namespace App\Entity\Crm;

use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Entity\Messaging\Client;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

#[ORM\Entity]
#[ORM\Table(name: '`crm_deals`')]
#[ORM\Index(name: 'idx_crm_deals_company_pipeline', columns: ['company_id', 'pipeline_id'])]
#[ORM\Index(name: 'idx_crm_deals_stage', columns: ['stage_id'])]
class CrmDeal
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: CrmPipeline::class)]
    #[ORM\JoinColumn(nullable: false)]
    private CrmPipeline $pipeline;

    #[ORM\ManyToOne(targetEntity: CrmStage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private CrmStage $stage;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $stageEnteredAt;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'RUB', 'fixed' => true])]
    private string $currency = 'RUB';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(type: 'jsonb', options: ['default' => '{}'])]
    private array $meta = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $openedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isClosed = false;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $lossReason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        Company $company,
        CrmPipeline $pipeline,
        CrmStage $stage,
        User $createdBy,
        string $title,
        \DateTimeImmutable $openedAt,
    ) {
        Assert::uuid($id);

        $this->id = $id;
        $this->company = $company;
        $this->pipeline = $pipeline;
        $this->stage = $stage;
        $this->createdBy = $createdBy;
        $this->title = $title;
        $this->openedAt = $openedAt;
        $this->stageEnteredAt = $openedAt;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
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

    public function getPipeline(): CrmPipeline
    {
        return $this->pipeline;
    }

    public function setPipeline(CrmPipeline $pipeline): void
    {
        $this->pipeline = $pipeline;
    }

    public function getStage(): CrmStage
    {
        return $this->stage;
    }

    public function setStage(CrmStage $stage): void
    {
        $this->stage = $stage;
    }

    public function getStageEnteredAt(): \DateTimeImmutable
    {
        return $this->stageEnteredAt;
    }

    public function setStageEnteredAt(\DateTimeImmutable $stageEnteredAt): void
    {
        $this->stageEnteredAt = $stageEnteredAt;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): void
    {
        $this->client = $client;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): void
    {
        $this->owner = $owner;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): void
    {
        $this->source = $source;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }

    public function getOpenedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(\DateTimeImmutable $openedAt): void
    {
        $this->openedAt = $openedAt;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): void
    {
        $this->closedAt = $closedAt;
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }

    public function setIsClosed(bool $isClosed): void
    {
        $this->isClosed = $isClosed;
    }

    public function getLossReason(): ?string
    {
        return $this->lossReason;
    }

    public function setLossReason(?string $lossReason): void
    {
        $this->lossReason = $lossReason;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
