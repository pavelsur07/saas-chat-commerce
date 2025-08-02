<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

#[ORM\Entity]
#[ORM\Table(name: '`clients`')]
class Client
{
    public const TELEGRAM = 'telegram';
    public const WHATSAPP = 'whatsapp';
    public const INSTAGRAM = 'instagram';

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $id = null;

    #[ORM\Column(length: 50)]
    private string $channel; // telegram, whatsapp, instagram, site

    #[ORM\Column(length: 255)]
    private string $externalId; // telegram_id, wa_id и т.д.

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $rawData = null;

    #[ORM\ManyToOne]
    private Company $company;

    public function __construct(string $id, string $channel, string $externalId, Company $company)
    {
        Assert::uuid($id);
        Assert::oneOf($channel, self::channelList());
        $this->id = $id;
        $this->channel = $channel;
        $this->externalId = $externalId;
        $this->company = $company;
    }

    public function getUniqueKey(): string
    {
        return $this->channel.':'.$this->externalId;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): void
    {
        $this->externalId = $externalId;
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

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getRawData(): ?array
    {
        return $this->rawData;
    }

    public function setRawData(?array $rawData): void
    {
        $this->rawData = $rawData;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    public static function channelList(): array
    {
        return [
            self::TELEGRAM,
            self::WHATSAPP,
            self::INSTAGRAM,
        ];
    }
}
