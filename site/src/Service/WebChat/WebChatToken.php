<?php

declare(strict_types=1);

namespace App\Service\WebChat;

use DateTimeImmutable;

final class WebChatToken
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly string $token,
        private readonly array $payload,
        private readonly DateTimeImmutable $expiresAt,
        private readonly ?array $header = null,
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getVisitorId(): ?string
    {
        return isset($this->payload['sub']) ? (string) $this->payload['sub'] : null;
    }

    public function getSiteKey(): ?string
    {
        return isset($this->payload['aud']) ? (string) $this->payload['aud'] : null;
    }

    public function getThreadId(): ?string
    {
        return isset($this->payload['thread']) ? (string) $this->payload['thread'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getHeader(): ?array
    {
        return $this->header;
    }

    public function getTtlSeconds(?DateTimeImmutable $reference = null): int
    {
        $ref = $reference ?? new DateTimeImmutable();
        $diff = $this->expiresAt->getTimestamp() - $ref->getTimestamp();

        return max(0, $diff);
    }
}
