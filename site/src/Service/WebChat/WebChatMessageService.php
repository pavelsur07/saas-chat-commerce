<?php

declare(strict_types=1);

namespace App\Service\WebChat;

use App\Entity\Messaging\Message;
use App\Entity\WebChat\WebChatThread;
use App\Repository\Messaging\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final class WebChatMessageService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $messages,
        private readonly WebChatRealtimePublisher $publisher,
    ) {
    }

    public function createInbound(WebChatThread $thread, string $text, ?string $dedupeKey = null, ?string $sourceId = null): Message
    {
        $text = trim($text);
        $dedupeKey = $dedupeKey !== null ? trim($dedupeKey) : null;

        if ($dedupeKey !== null && $dedupeKey !== '') {
            $existing = $this->messages->findOneByThreadAndDedupe($thread, $dedupeKey);
            if ($existing instanceof Message) {
                return $existing;
            }
        }

        $message = Message::messageInGeneric(Uuid::uuid4()->toString(), $thread->getClient(), $text);
        $message->setThread($thread);
        $message->setDedupeKey($dedupeKey ?: null);
        if ($sourceId !== null) {
            $message->setSourceId($sourceId);
        }

        $thread->registerMessage($message->getCreatedAt());

        $this->em->persist($message);
        $this->publisher->publishMessage($thread, $message);

        return $message;
    }

    public function createOutbound(WebChatThread $thread, string $text): Message
    {
        $message = Message::messageOutGeneric(Uuid::uuid4()->toString(), $thread->getClient(), $text);
        $message->setThread($thread);
        $message->markDelivered();
        $thread->registerMessage($message->getCreatedAt());

        $this->em->persist($message);
        $this->publisher->publishMessage($thread, $message);

        return $message;
    }

    public function publishStatus(WebChatThread $thread, array $messageIds, string $status): void
    {
        $this->publisher->publishStatus($thread, $messageIds, $status);
    }
}
