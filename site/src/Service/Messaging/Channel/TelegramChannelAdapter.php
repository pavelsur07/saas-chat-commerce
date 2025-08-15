<?php

declare(strict_types=1);

namespace App\Service\Messaging\Channel;

use App\Service\Messaging\Dto\OutboundMessage;
use App\Service\Messaging\Port\ChannelAdapterInterface;
use App\Service\Messaging\TelegramService; // у вас уже есть этот сервис

final class TelegramChannelAdapter implements ChannelAdapterInterface
{
    public function __construct(private readonly TelegramService $telegram)
    {
    }

    public function supports(string $channel): bool
    {
        return 'telegram' === $channel;
    }

    public function send(OutboundMessage $msg): void
    {
        // Нужен токен бота. Берём из meta.
        $token = $msg->meta['token'] ?? null;
        if (!$token) {
            throw new \InvalidArgumentException('Telegram token is required in OutboundMessage::meta["token"].');
        }

        // recipientRef — это chatId
        $this->telegram->sendMessage($token, $msg->recipientRef, $msg->text);
    }
}
