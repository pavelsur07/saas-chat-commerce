<?php

namespace App\Entity\AI;

use App\Entity\AI\Enum\KnowledgeType;
use App\Entity\Company\Company;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'company_knowledge')]
#[ORM\Index(name: 'idx_ck_company_type', columns: ['company_id', 'type'])]
#[ORM\Index(name: 'idx_ck_company_title', columns: ['company_id', 'title'])]
class CompanyKnowledge
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\Column(type: 'string', enumType: KnowledgeType::class)]
    private KnowledgeType $type;

    #[ORM\Column(type: 'string', length: 160)]
    private string $title;

    // Можно text либо json; для MVP — text
    #[ORM\Column(type: 'text')]
    private string $content;

    // опционально, для простых тегов через запятую
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $tags = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, Company $company, KnowledgeType $type, string $title, string $content)
    {
        $this->id = $id;
        $this->company = $company;
        $this->type = $type;
        $this->title = $title;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCompany(): Company { return $this->company; }
    public function getType(): KnowledgeType { return $this->type; }
    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }
    public function getTags(): ?string { return $this->tags; }

    public function setTags(?string $tags): void { $this->tags = $tags; }
}
