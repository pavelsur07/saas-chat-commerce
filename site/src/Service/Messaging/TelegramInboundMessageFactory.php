<?php

declare(strict_types=1);

namespace App\Service\Messaging;

use App\Entity\Messaging\Client;
use App\Entity\Messaging\TelegramBot;
use App\Service\Messaging\Dto\InboundMessage;

/**
 * Создаёт InboundMessage на основе обновления Telegram для унифицированного конвейера.
 */
final class TelegramInboundMessageFactory
{
    /**
     * @param array<string, mixed> $update
     */
    public function createFromUpdate(TelegramBot $bot, array $update): ?InboundMessage
    {
        $message = $update['message'] ?? ($update['edited_message'] ?? null);
        if (!is_array($message)) {
            return null;
        }

        $chat = $message['chat'] ?? null;
        if (!is_array($chat) || !isset($chat['id'])) {
            return null;
        }

        $from = $message['from'] ?? $chat;

        $chatId = (string) $chat['id'];
        if ('' === $chatId) {
            return null;
        }

        $text = '';
        if (array_key_exists('text', $message) && is_string($message['text'])) {
            $text = $message['text'];
        } elseif (isset($message['caption']) && is_string($message['caption'])) {
            $text = $message['caption'];
        }

        $ingestType = 'unknown';
        if (array_key_exists('sticker', $message)) {
            $ingestType = 'sticker';
        } elseif (array_key_exists('photo', $message)) {
            $ingestType = 'photo';
        } elseif (array_key_exists('video', $message)) {
            $ingestType = 'video';
        } elseif ('' !== trim($text)) {
            $ingestType = 'text';
        }

        $meta = [
            'username' => $from['username'] ?? ($chat['username'] ?? null),
            'firstName' => $from['first_name'] ?? ($chat['first_name'] ?? null),
            'lastName' => $from['last_name'] ?? ($chat['last_name'] ?? null),
            'company' => $bot->getCompany(),
            'bot_id' => $bot->getId(),
            'update_id' => $update['update_id'] ?? null,
            'raw' => $update,
            'ingest' => [
                'type' => $ingestType,
                'message_id' => $message['message_id'] ?? null,
                'date' => $message['date'] ?? null,
                'has_text' => '' !== trim($text),
            ],
        ];

        return new InboundMessage(
            channel: Client::TELEGRAM,
            externalId: $chatId,
            text: $text,
            clientId: null,
            meta: $meta,
        );
    }
}
