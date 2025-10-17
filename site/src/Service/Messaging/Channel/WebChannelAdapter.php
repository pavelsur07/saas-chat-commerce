<?php

declare(strict_types=1);

namespace App\Service\Messaging\Channel;

use App\Service\Messaging\Dto\OutboundMessage;
use App\Service\Messaging\Port\ChannelAdapterInterface;

final class WebChannelAdapter implements ChannelAdapterInterface
{
    public function supports(string $channel): bool
    {
        return 'web' === $channel;
    }

    public function send(OutboundMessage $msg): void
    {
        $clientId = (string) $msg->recipientRef;

        $payload = [
            'clientId' => $clientId,
            'text' => $msg->text,
            'direction' => 'out',
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        try {
            $redis = new \Predis\Client([
                'scheme' => 'tcp',
                'host' => $_ENV['REDIS_REALTIME_HOST'] ?? 'redis-realtime',
                'port' => (int) ($_ENV['REDIS_REALTIME_PORT'] ?? 6379),
            ]);

            $redis->publish(
                "chat.client.{$clientId}",
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log(sprintf(
                    'Failed to publish web message for client "%s": %s',
                    $clientId,
                    $e->getMessage()
                ));
            }
        }
    }
}
