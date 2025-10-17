<?php

namespace App\Entity\WebChat;

use App\Entity\Company\Company;
use App\Repository\WebChat\WebChatSiteRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

#[ORM\Entity(repositoryClass: WebChatSiteRepository::class)]
#[ORM\Table(name: '`web_chat_sites`')]
#[ORM\Index(name: 'idx_web_chat_sites_company_active', columns: ['company_id', 'is_active'])]
#[ORM\HasLifecycleCallbacks]
class WebChatSite
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 64, unique: true)]
    private string $siteKey;

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
        string $name,
        string $siteKey,
        array $allowedOrigins = [],
        bool $isActive = true,
    ) {
        Assert::uuid($id);
        Assert::notEmpty($siteKey);

        $this->id = $id;
        $this->company = $company;
        $this->setName($name);
        $this->setSiteKey($siteKey);
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    public function setSiteKey(string $siteKey): void
    {
        Assert::notEmpty($siteKey);
        $this->siteKey = $siteKey;
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
