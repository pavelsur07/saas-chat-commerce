<?php

declare(strict_types=1);

namespace App\Service\Messaging\Dto;

final class OutboundMessage
{
    public function __construct(
        public readonly string $channel,      // 'telegram' | 'web'
        public readonly string $recipientRef, // chatId / room
        public readonly string $text,
        public array $meta = [],
    ) {
    }
}
