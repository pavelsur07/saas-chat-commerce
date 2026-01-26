<?php

namespace App\Entity\Crm;

use App\Account\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

#[ORM\Entity]
#[ORM\Table(name: '`crm_stage_history`')]
#[ORM\Index(name: 'idx_crm_stage_history_deal', columns: ['deal_id'])]
class CrmStageHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: CrmDeal::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CrmDeal $deal;

    #[ORM\ManyToOne(targetEntity: CrmStage::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CrmStage $fromStage = null;

    #[ORM\ManyToOne(targetEntity: CrmStage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private CrmStage $toStage;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $changedBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $changedAt;

    #[ORM\Column(length: 240, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $spentHours = null;

    public function __construct(
        string $id,
        CrmDeal $deal,
        CrmStage $toStage,
        User $changedBy,
        \DateTimeImmutable $changedAt,
        ?CrmStage $fromStage = null,
    ) {
        Assert::uuid($id);

        $this->id = $id;
        $this->deal = $deal;
        $this->toStage = $toStage;
        $this->changedBy = $changedBy;
        $this->changedAt = $changedAt;
        $this->fromStage = $fromStage;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDeal(): CrmDeal
    {
        return $this->deal;
    }

    public function setDeal(CrmDeal $deal): void
    {
        $this->deal = $deal;
    }

    public function getFromStage(): ?CrmStage
    {
        return $this->fromStage;
    }

    public function setFromStage(?CrmStage $fromStage): void
    {
        $this->fromStage = $fromStage;
    }

    public function getToStage(): CrmStage
    {
        return $this->toStage;
    }

    public function setToStage(CrmStage $toStage): void
    {
        $this->toStage = $toStage;
    }

    public function getChangedBy(): User
    {
        return $this->changedBy;
    }

    public function setChangedBy(User $changedBy): void
    {
        $this->changedBy = $changedBy;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTimeImmutable $changedAt): void
    {
        $this->changedAt = $changedAt;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getSpentHours(): ?int
    {
        return $this->spentHours;
    }

    public function setSpentHours(?int $spentHours): void
    {
        $this->spentHours = $spentHours;
    }
}
