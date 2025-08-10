<?php

namespace App\Entity\AI;

use App\Entity\AI\Enum\AiFaqSource;
use App\Entity\AI\Traits\Timestampable;
use App\Entity\Company;
use App\Entity\User;
use App\Repository\AI\AiFaqRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiFaqRepository::class)]
#[ORM\Table(name: 'ai_faq')]
class AiFaq
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

    #[ORM\Column(type: 'text')]
    private string $question;

    #[ORM\Column(type: 'text')]
    private string $answer;

    #[ORM\Column(type: 'string', length: 10, options: ['comment' => 'ru,en,...'])]
    private string $language = 'ru';

    #[ORM\Column(enumType: AiFaqSource::class)]
    private AiFaqSource $source = AiFaqSource::MANUAL;

    #[ORM\Column(type: 'jsonb', nullable: true)]
    private ?array $tags = null; // ["доставка","возврат"]

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    public function __construct(string $id, Company $company, string $question, string $answer)
    {
        $this->id = $id;
        $this->company = $company;
        $this->question = $question;
        $this->answer = $answer;
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

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): void
    {
        $this->question = $question;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): void
    {
        $this->answer = $answer;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function getSource(): AiFaqSource
    {
        return $this->source;
    }

    public function setSource(AiFaqSource $source): void
    {
        $this->source = $source;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): void
    {
        $this->tags = $tags;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}
