<?php

declare(strict_types=1);

namespace App\Service\WebChat;

use App\Entity\Messaging\Client;
use App\Entity\WebChat\WebChatThread;

final class WebChatSession
{
    public function __construct(
        private readonly Client $client,
        private readonly WebChatThread $thread,
        private readonly WebChatToken $token,
        private readonly string $sessionId,
    ) {
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getThread(): WebChatThread
    {
        return $this->thread;
    }

    public function getToken(): WebChatToken
    {
        return $this->token;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function toArray(): array
    {
        return [
            'visitor_id' => $this->client->getExternalId(),
            'thread_id' => $this->thread->getId(),
            'token' => $this->token->getToken(),
            'expires_in' => $this->token->getTtlSeconds(),
            'session_id' => $this->sessionId,
        ];
    }
}
