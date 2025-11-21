<?php

declare(strict_types=1);

namespace App\Service\WebChat;

use App\Entity\Messaging\Message;
use App\Entity\WebChat\WebChatThread;
use Predis\ClientInterface as RedisClient;
use Psr\Log\LoggerInterface;

final class WebChatRealtimePublisher
{
    public function __construct(
        private readonly RedisClient $redis,
        private readonly LoggerInterface $logger,
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
            $this->redis->publish(
                $channel,
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to publish webchat realtime payload', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
