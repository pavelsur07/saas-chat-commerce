<?php
declare(strict_types=1);

namespace App\Service\Messaging\Channel;

use App\Service\Messaging\Dto\OutboundMessage;
use App\Service\Messaging\Port\ChannelAdapterInterface;
use App\Service\Messaging\TelegramService; // у вас уже есть этот сервис

final class TelegramChannelAdapter implements ChannelAdapterInterface
{
    public function __construct(private readonly TelegramService $telegram) {}

    public function supports(string $channel): bool
    {
        return $channel === 'telegram';
    }

    public function send(OutboundMessage $msg): void
    {
        // recipientRef — это chatId
        $this->telegram->sendMessage($msg->recipientRef, $msg->text);
    }
}
