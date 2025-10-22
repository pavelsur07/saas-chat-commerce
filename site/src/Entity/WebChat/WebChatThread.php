<?php

declare(strict_types=1);

namespace App\Entity\WebChat;

use App\Entity\Messaging\Client;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

#[ORM\Entity(repositoryClass: \App\Repository\WebChat\WebChatThreadRepository::class)]
#[ORM\Table(name: '`web_chat_threads`')]
#[ORM\Index(columns: ['client_id', 'is_open'], name: 'idx_web_chat_threads_client_open')]
#[ORM\Index(columns: ['site_id', 'created_at'], name: 'idx_web_chat_threads_site_created')]
#[ORM\HasLifecycleCallbacks]
class WebChatThread
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: WebChatSite::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private WebChatSite $site;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isOpen = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $reopenedCount = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastMessageAt = null;

    public function __construct(string $id, WebChatSite $site, Client $client)
    {
        Assert::uuid($id);

        $this->id = $id;
        $this->site = $site;
        $this->client = $client;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): string
    {
        Assert::notNull($this->id);

        return $this->id;
    }

    public function getSite(): WebChatSite
    {
        return $this->site;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function close(?DateTimeImmutable $moment = null): void
    {
        if (!$this->isOpen) {
            return;
        }

        $this->isOpen = false;
        $this->closedAt = $moment ?? new DateTimeImmutable();
    }

    public function reopen(?DateTimeImmutable $moment = null): void
    {
        if ($this->isOpen) {
            return;
        }

        $this->isOpen = true;
        $this->closedAt = null;
        ++$this->reopenedCount;
        $this->touch($moment ?? new DateTimeImmutable());
    }

    public function touch(?DateTimeImmutable $moment = null): void
    {
        $now = $moment ?? new DateTimeImmutable();
        $this->updatedAt = $now;
        if ($this->lastMessageAt === null || $now > $this->lastMessageAt) {
            $this->lastMessageAt = $now;
        }
    }

    public function registerMessage(DateTimeImmutable $at): void
    {
        if (!$this->isOpen) {
            $this->reopen($at);
        }
        $this->touch($at);
    }

    public function getClosedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getReopenedCount(): int
    {
        return $this->reopenedCount;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastMessageAt(): ?DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    public function isStale(DateInterval $interval, ?DateTimeImmutable $reference = null): bool
    {
        $ref = $reference ?? new DateTimeImmutable();
        $pivot = $this->lastMessageAt ?? $this->createdAt;

        return $pivot->add($interval) < $ref;
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
