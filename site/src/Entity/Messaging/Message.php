<?php

namespace App\Entity\Messaging;

use App\Account\Entity\Company;
use App\Entity\Messaging\Channel\Channel;
use App\Entity\WebChat\WebChatThread;
use App\Repository\Messaging\MessageRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: '`messages`')]
class Message
{
    public const IN = 'in';
    public const OUT = 'out';

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;                // ✅ Новое поле

    #[ORM\Column(type: 'channel_enum', nullable: false)]
    private Channel $channel; // дублируется для удобства фильтрации

    #[ORM\Column(length: 20)]
    private string $direction; // in / out

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $text = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null; // любые дополнительные данные

    #[ORM\ManyToOne(targetEntity: TelegramBot::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?TelegramBot $telegramBot = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $meta = null;

    #[ORM\ManyToOne(targetEntity: WebChatThread::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WebChatThread $thread = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $sourceId = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $dedupeKey = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $readAt = null;

    public function __construct(string $id, Client $client, string $direction, ?string $text = null, ?array $payload = null, ?TelegramBot $telegramBot = null)
    {
        Assert::oneOf($direction, self::directionList());

        $this->id = $id;
        $this->client = $client;
        $this->company = $client->getCompany(); // ✅ фиксируем компанию при создании
        $this->setChannel($client->getChannel());
        $this->direction = $direction;
        $this->text = $text;
        $this->payload = $payload;
        $this->telegramBot = $telegramBot;
        $this->createdAt = new DateTimeImmutable();

        if ($telegramBot !== null) {
            Assert::eq(
                $telegramBot->getCompany()->getId(),
                $this->company->getId(),
                'TelegramBot company mismatch with message company'
            );
        }
    }

    public static function messageOut(string $id, Client $client, ?TelegramBot $telegramBot = null, ?string $text = null, ?array $payload = null): self
    {
        return new self($id, $client, 'out', $text, $payload, $telegramBot);
    }

    public static function messageIn(string $id, Client $client, ?TelegramBot $telegramBot = null, ?string $text = null, ?array $payload = null): self
    {
        return new self($id, $client, 'in', $text, $payload, $telegramBot);
    }

    /**
     * Удобные обёртки для каналов без Telegram-бота (web/instagram/whatsapp/avito и др.).
     */
    public static function messageOutGeneric(string $id, Client $client, ?string $text = null, ?array $payload = null): self
    {
        return new self($id, $client, 'out', $text, $payload, null);
    }

    public static function messageInGeneric(string $id, Client $client, ?string $text = null, ?array $payload = null): self
    {
        return new self($id, $client, 'in', $text, $payload, null);
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    public function getThread(): ?WebChatThread
    {
        return $this->thread;
    }

    public function setThread(?WebChatThread $thread): void
    {
        $this->thread = $thread;
        if ($thread !== null) {
            $thread->registerMessage($this->createdAt);
        }
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    private function setCompany(Company $company): void
    {
        $this->company = $company;
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

    /* public function getChannel(): string
     {
         return $this->channel;
     }

     public function setChannel(string $channel): void
     {
         $this->channel = $channel;
     }*/

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): void
    {
        $this->direction = $direction;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): void
    {
        $this->text = $text;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }

    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }

    public function setSourceId(?string $sourceId): void
    {
        $this->sourceId = $sourceId;
    }

    public function getDedupeKey(): ?string
    {
        return $this->dedupeKey;
    }

    public function setDedupeKey(?string $dedupeKey): void
    {
        $this->dedupeKey = $dedupeKey;
    }

    public function getDeliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function markDelivered(?DateTimeImmutable $moment = null): void
    {
        $this->deliveredAt = $moment ?? new DateTimeImmutable();
    }

    public function getReadAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function markRead(?DateTimeImmutable $moment = null): void
    {
        $this->readAt = $moment ?? new DateTimeImmutable();
    }

    public function getTelegramBot(): ?TelegramBot
    {
        return $this->telegramBot;
    }

    public function setTelegramBot(?TelegramBot $telegramBot): void
    {
        $this->telegramBot = $telegramBot;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public static function directionList(): array
    {
        return [
            self::IN,
            self::OUT,
        ];
    }

    public function getBot()
    {
        return $this->getTelegramBot();
    }
}
