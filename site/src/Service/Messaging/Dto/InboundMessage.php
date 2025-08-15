<?php

declare(strict_types=1);

namespace App\Service\Messaging\Dto;

final class InboundMessage
{
    public function __construct(
        public readonly string $channel,     // 'telegram' | 'web' | ...
        public readonly string $externalId,  // chat/user id в канале
        public readonly string $text,
        public ?string $clientId = null,     // подставим позже
        public array $meta = [],              // username, raw, company и т.д.
    ) {
    }
}
