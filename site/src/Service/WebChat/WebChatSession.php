<?php

declare(strict_types=1);

namespace App\Service\WebChat;

use App\Entity\Messaging\Client;
use App\Entity\WebChat\WebChatThread;

final class WebChatSession
{
    public function __construct(
        private readonly string $visitorId,
        private readonly string $sessionId,
        private readonly ?Client $client = null,
        private readonly ?WebChatThread $thread = null,
        private readonly ?WebChatToken $token = null,
    ) {
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function getThread(): ?WebChatThread
    {
        return $this->thread;
    }

    public function getToken(): ?WebChatToken
    {
        return $this->token;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getVisitorId(): string
    {
        return $this->visitorId;
    }

    public function toArray(): array
    {
        return [
            'visitor_id' => $this->visitorId,
            'thread_id' => $this->thread?->getId(),
            'token' => $this->token?->getToken(),
            'expires_in' => $this->token?->getTtlSeconds() ?? 0,
            'session_id' => $this->sessionId,
        ];
    }
}
