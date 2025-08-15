<?php

namespace App\Entity\Messaging;

use App\Repository\Messaging\MessageRepository;
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

    #[ORM\ManyToOne]
    private Client $client;

    #[ORM\Column(length: 20)]
    private string $channel; // дублируется для удобства фильтрации

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
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $meta = null;

    public function __construct(string $id, Client $client, string $direction, ?string $text = null, ?array $payload = null, ?TelegramBot $telegramBot = null)
    {
        Assert::oneOf($direction, self::directionList());
        $this->id = $id;
        $this->client = $client;
        $this->channel = $client->getChannel();
        $this->direction = $direction;
        $this->text = $text;
        $this->payload = $payload;
        $this->telegramBot = $telegramBot;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function messageOut(string $id, Client $client, TelegramBot $telegramBot, ?string $text = null, ?array $payload = null): self
    {
        return new self($id, $client, 'out', $text, $payload, $telegramBot);
    }

    public static function messageIn(string $id, Client $client, TelegramBot $telegramBot, ?string $text = null, ?array $payload = null): self
    {
        return new self($id, $client, 'in', $text, $payload, $telegramBot);
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

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

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

    public function getTelegramBot(): ?TelegramBot
    {
        return $this->telegramBot;
    }

    public function setTelegramBot(?TelegramBot $telegramBot): void
    {
        $this->telegramBot = $telegramBot;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function directionList(): array
    {
        return [
            self::IN,
            self::OUT,
        ];
    }
}
