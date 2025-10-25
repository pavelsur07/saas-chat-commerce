<?php

namespace App\Service\Realtime;

use Predis\Client as PredisClient;
use Psr\Log\LoggerInterface;

final class PredisRealtimePublisher implements RealtimePublisher
{
    public function __construct(
        private readonly PredisClient $redis,
        private readonly LoggerInterface $logger
    ) {
    }

    public function publish(string $channel, array $payload): void
    {
        $envelope = [
            'event' => $payload['event'] ?? 'event',
            'data' => $payload['data'] ?? [],
            'occurred_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'correlation_id' => $payload['correlation_id'] ?? bin2hex(random_bytes(8)),
        ];

        try {
            $this->redis->publish($channel, json_encode($envelope, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            $this->logger->error('Redis publish failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function toClient(string $clientId, string $event, array $data = []): void
    {
        $this->publish("chat.client.$clientId", ['event' => $event, 'data' => $data]);
    }

    public function toCompany(string $companyId, string $event, array $data = []): void
    {
        $this->publish("chat.company.$companyId", ['event' => $event, 'data' => $data]);
    }
}
