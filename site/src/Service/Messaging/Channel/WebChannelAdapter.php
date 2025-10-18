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

        $createdAt = $msg->meta['createdAt'] ?? null;
        if ($createdAt instanceof \DateTimeInterface) {
            $createdAt = $createdAt->format(DATE_ATOM);
        } elseif (is_string($createdAt)) {
            $createdAt = trim($createdAt);
            if ($createdAt === '') {
                $createdAt = null;
            }
        } else {
            $createdAt = null;
        }

        $payload = [
            'clientId' => $clientId,
            'text' => $msg->text,
            'direction' => 'out',
            'createdAt' => $createdAt ?? (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        if (isset($msg->meta['messageId'])) {
            $payload['id'] = (string) $msg->meta['messageId'];
        }

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
