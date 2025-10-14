<?php

namespace App\Controller\Webhook;

use App\Entity\Messaging\Client;
use App\Repository\Messaging\TelegramBotRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\MessageIngressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TelegramWebhookController extends AbstractController
{
    #[Route(
        '/webhook/telegram/bot/{token}',
        name: 'telegram.webhook',
        requirements: ['token' => '.+'],
        methods: ['POST']
    )]
    public function handleWebhook(
        string $token,
        Request $request,
        TelegramBotRepository $botRepo,
        MessageIngressService $ingress,
    ): JsonResponse {
        $bot = $botRepo->findOneBy(['token' => $token, 'isActive' => true]);
        if (!$bot) {
            return new JsonResponse(['error' => 'Invalid bot'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $msg = $data['message'] ?? $data['edited_message'] ?? null;
        if (!$msg || !is_array($msg)) {
            return new JsonResponse(['ok' => true]);
        }

        // Валидация наличия from.id и chat.id
        $from = $msg['from'] ?? null;
        $chat = $msg['chat'] ?? null;

        if (!is_array($from) || !isset($from['id'])) {
            // В этом тесте как раз нет from.id — корректно выходим
            return new JsonResponse(['ok' => true]);
        }

        if (!is_array($chat) || !isset($chat['id'])) {
            // Без chat.id не можем привязать чат
            return new JsonResponse(['ok' => true]);
        }

        $telegramId = (string) $chat['id']; // для private чат = user id

        $hasText = array_key_exists('text', $msg) && is_string($msg['text']);
        $hasPhoto = array_key_exists('photo', $msg);
        $hasVideo = array_key_exists('video', $msg);
        $caption = $msg['caption'] ?? null;

        $resolvedText = $hasText ? (string) $msg['text'] : '';
        if ('' === $resolvedText && is_string($caption)) {
            $resolvedText = $caption;
        }

        $ingestType = 'unknown';
        if (array_key_exists('sticker', $msg)) {
            $ingestType = 'sticker';
        } elseif ($hasPhoto) {
            $ingestType = 'photo';
        } elseif ($hasVideo) {
            $ingestType = 'video';
        } elseif ('' !== $resolvedText) {
            $ingestType = 'text';
        }

        $meta = [
            'username' => $from['username'] ?? ($chat['username'] ?? null),
            'firstName' => $from['first_name'] ?? ($chat['first_name'] ?? null),
            'lastName' => $from['last_name'] ?? ($chat['last_name'] ?? null),
            'company' => $bot->getCompany(),
            'bot_id' => $bot->getId(),
            'update_id' => $data['update_id'] ?? null,
            'raw' => $data,
            'ingest' => [
                'type' => $ingestType,
                'message_id' => $msg['message_id'] ?? null,
                'date' => $msg['date'] ?? null,
                'has_text' => '' !== trim((string) $resolvedText),
            ],
        ];

        $inbound = new InboundMessage(
            channel: Client::TELEGRAM,
            externalId: $telegramId,
            text: $resolvedText,
            clientId: null,
            meta: $meta
        );

        $ingress->accept($inbound);

        $client = $inbound->meta['_client'] ?? null;
        $persistedMessageId = $inbound->meta['_persisted_message_id'] ?? null;

        if ($client instanceof Client && null !== $persistedMessageId) {
            try {
                $redis = new \Predis\Client([
                    'scheme' => 'tcp',
                    'host' => $_ENV['REDIS_REALTIME_HOST'] ?? 'redis-realtime',
                    'port' => (int) ($_ENV['REDIS_REALTIME_PORT'] ?? 6379),
                ]);
                $redis->publish("chat.client.{$client->getId()}", json_encode([
                    'id' => $persistedMessageId,
                    'clientId' => $client->getId(),
                    'text' => $inbound->text,
                    'direction' => 'in',
                    'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            } catch (\Throwable) {
                // не роняем webhook
            }
        }

        return new JsonResponse(['ok' => true]);
    }
}
