<?php

namespace App\Service\Realtime;

use Predis\ClientInterface as PredisClient;
use Psr\Log\LoggerInterface;

use function array_diff_key;
use function array_replace;
use function is_array;
use function json_encode;
use function json_last_error_msg;

final class PredisRealtimePublisher implements RealtimePublisher
{
    private const RESERVED_KEYS = [
        'event' => true,
        'data' => true,
        'correlation_id' => true,
        'occurred_at' => true,
    ];

    public function __construct(
        private readonly PredisClient $redis,
        private readonly LoggerInterface $logger
    ) {
    }

    public function publish(string $channel, array $payload): void
    {
        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            $data = array_diff_key($payload, self::RESERVED_KEYS);
        }

        $metadata = [
            'event' => $payload['event'] ?? 'event',
            'occurred_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'correlation_id' => $payload['correlation_id'] ?? bin2hex(random_bytes(8)),
            'data' => $data,
        ];

        $envelope = array_replace($data, $metadata);

        $encoded = json_encode($envelope, JSON_UNESCAPED_UNICODE);
        if (false === $encoded) {
            $this->logger->error('Failed to encode realtime payload', [
                'channel' => $channel,
                'error' => json_last_error_msg(),
                'payload' => $payload,
            ]);

            return;
        }

        try {
            $this->redis->publish($channel, $encoded);
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
