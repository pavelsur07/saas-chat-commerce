<?php

declare(strict_types=1);

namespace App\Channel\DTO;

final class IncomingMessageDTO
{
    public function __construct(
        public readonly string $channelType,
        public readonly string $externalId,
        public readonly string $text,
        public readonly array $raw = [],
        public array $meta = [],
    ) {
    }
}
