<?php

namespace App\Entity\Messaging;

// src/Entity/TelegramBot.php

use App\Entity\Company\Company;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '`telegram_bots`')]
class TelegramBot
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    private string $token;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255, name: 'first_name', nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $webhookUrl = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $lastUpdateId = null;

    #[ORM\ManyToOne]
    private Company $company;

    public function __construct(string $id, Company $company)
    {
        $this->id = $id;
        $this->company = $company;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(?string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getLastUpdateId(): ?int
    {
        return $this->lastUpdateId;
    }

    public function setLastUpdateId(?int $lastUpdateId): void
    {
        $this->lastUpdateId = $lastUpdateId;
    }
}
