<?php

namespace App\Entity\Crm;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Webmozart\Assert\Assert as WebmozartAssert;

#[ORM\Entity]
#[ORM\Table(name: '`crm_stages`')]
#[ORM\UniqueConstraint(name: 'crm_stage_pipeline_position_unique', fields: ['pipeline', 'position'])]
class CrmStage
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CrmPipeline $pipeline;

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(type: 'integer')]
    private int $position;

    #[ORM\Column(length: 7, options: ['default' => '#CBD5E1'])]
    private string $color = '#CBD5E1';

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $probability = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isStart = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isWon = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isLost = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $slaHours = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $id, CrmPipeline $pipeline)
    {
        WebmozartAssert::uuid($id);

        $this->id = $id;
        $this->pipeline = $pipeline;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPipeline(): CrmPipeline
    {
        return $this->pipeline;
    }

    public function setPipeline(CrmPipeline $pipeline): void
    {
        $this->pipeline = $pipeline;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }

    public function getProbability(): int
    {
        return $this->probability;
    }

    public function setProbability(int $probability): void
    {
        $this->probability = $probability;
    }

    public function isStart(): bool
    {
        return $this->isStart;
    }

    public function setIsStart(bool $isStart): void
    {
        $this->isStart = $isStart;
    }

    public function isWon(): bool
    {
        return $this->isWon;
    }

    public function setIsWon(bool $isWon): void
    {
        $this->isWon = $isWon;
    }

    public function isLost(): bool
    {
        return $this->isLost;
    }

    public function setIsLost(bool $isLost): void
    {
        $this->isLost = $isLost;
    }

    public function getSlaHours(): ?int
    {
        return $this->slaHours;
    }

    public function setSlaHours(?int $slaHours): void
    {
        $this->slaHours = $slaHours;
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

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->isWon && $this->isLost) {
            $context
                ->buildViolation('A stage cannot be both won and lost.')
                ->atPath('isWon')
                ->addViolation();

            $context
                ->buildViolation('A stage cannot be both won and lost.')
                ->atPath('isLost')
                ->addViolation();
        }
    }
}
