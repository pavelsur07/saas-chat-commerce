<?php

namespace App\Entity\Crm;

use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Repository\Crm\CrmWebFormRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

#[ORM\Entity(repositoryClass: CrmWebFormRepository::class)]
#[ORM\Table(name: '`crm_web_forms`')]
#[ORM\Index(name: 'idx_crm_web_forms_company', columns: ['company_id'])]
#[ORM\UniqueConstraint(name: 'crm_web_forms_company_slug_unique', fields: ['company', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class CrmWebForm
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: CrmPipeline::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CrmPipeline $pipeline;

    #[ORM\ManyToOne(targetEntity: CrmStage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CrmStage $stage;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 140)]
    private string $slug;

    #[ORM\Column(length: 64, unique: true)]
    private string $publicKey;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'jsonb', options: ['default' => '[]'])]
    private array $fields = [];

    #[ORM\Column(length: 20, options: ['default' => 'message'])]
    private string $successType = 'message';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $successMessage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $successRedirectUrl = null;

    #[ORM\Column(type: 'jsonb', options: ['default' => '[]'])]
    private array $tags = [];

    #[ORM\Column(type: 'jsonb', options: ['default' => '[]'])]
    private array $allowedOrigins = [];

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        Company $company,
        CrmPipeline $pipeline,
        CrmStage $stage,
        string $name,
        string $slug,
        string $publicKey,
        array $fields = [],
        string $successType = 'message',
        array $tags = [],
        array $allowedOrigins = [],
        bool $isActive = true,
    ) {
        Assert::uuid($id);
        Assert::notEmpty($slug);
        Assert::notEmpty($publicKey);

        $this->id = $id;
        $this->company = $company;
        $this->pipeline = $pipeline;
        $this->stage = $stage;
        $this->setName($name);
        $this->setSlug($slug);
        $this->setPublicKey($publicKey);
        $this->setFields($fields);
        $this->setSuccessType($successType);
        $this->setTags($tags);
        $this->setAllowedOrigins($allowedOrigins);
        $this->isActive = $isActive;

        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): void
    {
        $this->owner = $owner;
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
        Assert::notEmpty($slug);
        $this->slug = $slug;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): void
    {
        Assert::notEmpty($publicKey);
        $this->publicKey = $publicKey;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return array<int, mixed>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param array<int, mixed> $fields
     */
    public function setFields(array $fields): void
    {
        $this->fields = array_values($fields);
    }

    public function getSuccessType(): string
    {
        return $this->successType;
    }

    public function setSuccessType(string $successType): void
    {
        $this->successType = $successType;
    }

    public function getSuccessMessage(): ?string
    {
        return $this->successMessage;
    }

    public function setSuccessMessage(?string $successMessage): void
    {
        $this->successMessage = $successMessage;
    }

    public function getSuccessRedirectUrl(): ?string
    {
        return $this->successRedirectUrl;
    }

    public function setSuccessRedirectUrl(?string $successRedirectUrl): void
    {
        $this->successRedirectUrl = $successRedirectUrl;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param string[] $tags
     */
    public function setTags(array $tags): void
    {
        $this->tags = array_values(array_map(static fn ($tag): string => (string) $tag, $tags));
    }

    /**
     * @return string[]
     */
    public function getAllowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    /**
     * @param string[] $allowedOrigins
     */
    public function setAllowedOrigins(array $allowedOrigins): void
    {
        $this->allowedOrigins = array_values(array_map(static fn ($origin): string => (string) $origin, $allowedOrigins));
    }

    public function addAllowedOrigin(string $origin): void
    {
        if (!in_array($origin, $this->allowedOrigins, true)) {
            $this->allowedOrigins[] = $origin;
        }
    }

    public function removeAllowedOrigin(string $origin): void
    {
        $this->allowedOrigins = array_values(array_filter(
            $this->allowedOrigins,
            static fn (string $existing): bool => $existing !== $origin,
        ));
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $this->createdAt ?? $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
