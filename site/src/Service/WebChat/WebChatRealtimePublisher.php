<?php

declare(strict_types=1);

namespace App\Service\WebChat;

use App\Entity\Messaging\Message;
use App\Entity\WebChat\WebChatThread;
use Predis\Client as RedisClient;

final class WebChatRealtimePublisher
{
    private ?RedisClient $redis = null;

    public function __construct(
        private readonly string $host = 'redis-realtime',
        private readonly int $port = 6379,
    ) {
    }

    public function publishMessage(WebChatThread $thread, Message $message): void
    {
        $threadPayload = [
            'event' => 'message.new',
            'threadId' => $thread->getId(),
            'message' => [
                'id' => $message->getId(),
                'direction' => $message->getDirection(),
                'text' => $message->getText(),
                'payload' => $message->getPayload(),
                'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
                'deliveredAt' => $message->getDeliveredAt()?->format(DATE_ATOM),
                'readAt' => $message->getReadAt()?->format(DATE_ATOM),
            ],
        ];

        $this->publishThread($thread->getId(), $threadPayload);

        $clientId = $message->getClient()->getId();
        if ($clientId !== null) {
            $clientPayload = [
                'id' => $message->getId(),
                'clientId' => $clientId,
                'text' => $message->getText(),
                'direction' => $message->getDirection(),
                'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
            ];

            $this->publishClient($clientId, $clientPayload);
        }
    }

    public function publishStatus(WebChatThread $thread, array $messageIds, string $status, ?\DateTimeImmutable $at = null): void
    {
        if ($messageIds === []) {
            return;
        }

        $payload = [
            'event' => 'message.status',
            'threadId' => $thread->getId(),
            'messages' => $messageIds,
            'status' => $status,
            'timestamp' => ($at ?? new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->publishThread($thread->getId(), $payload);
    }

    private function publishThread(string $threadId, array $payload): void
    {
        $this->publishChannel(sprintf('chat.thread.%s', $threadId), $payload);
    }

    private function publishClient(string $clientId, array $payload): void
    {
        $this->publishChannel(sprintf('chat.client.%s', $clientId), $payload);
    }

    private function publishChannel(string $channel, array $payload): void
    {
        try {
            $redis = $this->redis ??= new RedisClient([
                'scheme' => 'tcp',
                'host' => $this->host,
                'port' => $this->port,
            ]);

            $redis->publish(
                $channel,
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable) {
            // swallow redis exceptions to avoid breaking request cycle
        }
    }
}
