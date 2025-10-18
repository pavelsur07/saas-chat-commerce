<?php

namespace App\Service\RateLimiter;

use DateTimeImmutable;

final class RateLimit
{
    public function __construct(
        private readonly bool $accepted,
        private readonly ?DateTimeImmutable $retryAfter,
    ) {
    }

    public static function accepted(): self
    {
        return new self(true, null);
    }

    public static function rejected(?DateTimeImmutable $retryAfter): self
    {
        return new self(false, $retryAfter);
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function getRetryAfter(): ?DateTimeImmutable
    {
        return $this->retryAfter;
    }
}
