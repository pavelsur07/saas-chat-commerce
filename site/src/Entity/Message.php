<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;
#[ORM\Entity]
#[ORM\Table(name: '`messages`')]
class Message
{
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

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id,Client $client, string $direction, ?string $text = null, ?array $payload = null)
    {
        $this->id = $id;
        $this->client = $client;
        $this->channel = $client->getChannel();
        $this->direction = $direction;
        $this->text = $text;
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

}
