<?php

namespace App\Entity\Messaging;

use App\Entity\Company\Company;
use App\Entity\Messaging\Channel\Channel;
use App\Entity\WebChat\WebChatSite;
use App\Repository\Messaging\ClientRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: '`clients`')]
class Client
{
    public const TELEGRAM = 'telegram';
    public const WHATSAPP = 'whatsapp';
    public const INSTAGRAM = 'instagram';

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $id = null;

    #[ORM\Column(type: 'channel_enum', nullable: true)]
    private Channel $channel; // telegram, whatsapp, instagram, site

    #[ORM\Column(length: 255)]
    private string $externalId; // telegram_id, wa_id и т.д.

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lastName = null;

    #[ORM\ManyToOne(targetEntity: WebChatSite::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WebChatSite $webChatSite = null;

    #[ORM\Column(name: 'raw_data', type: 'json', nullable: true)]
    private ?array $meta = null;

    #[ORM\ManyToOne]
    private Company $company;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $telegramId = null;

    #[ORM\ManyToOne(targetEntity: TelegramBot::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?TelegramBot $telegramBot = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastSeenAt = null;

    public function __construct(string $id, Channel|string $channel, string $externalId, Company $company)
    {
        Assert::uuid($id);
        $this->id = $id;
        $this->setChannel($channel);
        $this->externalId = $externalId;
        $this->company = $company;
    }

    public function getTelegramBot(): ?TelegramBot
    {
        return $this->telegramBot;
    }

    public function setTelegramBot(TelegramBot $telegramBot): static
    {
        $this->telegramBot = $telegramBot;

        return $this;
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

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function setChannel(Channel|string $channel): self
    {
        if (!$channel instanceof Channel) {
            $channel = Channel::tryFromCaseInsensitive((string) $channel)
                ?? throw new \InvalidArgumentException('Unknown channel');
        }
        $this->channel = $channel;

        return $this;
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

    public function getWebChatSite(): ?WebChatSite
    {
        return $this->webChatSite;
    }

    public function setWebChatSite(?WebChatSite $site): void
    {
        $this->webChatSite = $site;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function setMeta(?array $meta): void
    {
        $this->meta = $meta;
    }

    public function mergeMeta(array $extra): void
    {
        $this->meta = array_merge($this->meta ?? [], $extra);
    }

    public function getRawData(): ?array
    {
        return $this->meta;
    }

    public function setRawData(?array $rawData): void
    {
        $this->meta = $rawData;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    public function getTelegramId(): ?int
    {
        return $this->telegramId;
    }

    public function setTelegramId(?int $telegramId): void
    {
        $this->telegramId = $telegramId;
    }

    public function getLastSeenAt(): ?DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touchLastSeen(?DateTimeImmutable $moment = null): void
    {
        $this->lastSeenAt = $moment ?? new DateTimeImmutable();
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
