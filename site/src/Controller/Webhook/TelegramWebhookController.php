<?php

namespace App\Controller\Webhook;

use App\Entity\Messaging\Client;
use App\Repository\Messaging\TelegramBotRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\MessageIngressService;
use App\Service\Messaging\TelegramInboundMessageFactory;
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
        TelegramInboundMessageFactory $messageFactory,
    ): JsonResponse {
        $bot = $botRepo->findOneBy(['token' => $token, 'isActive' => true]);
        if (!$bot) {
            return new JsonResponse(['error' => 'Invalid bot'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $inbound = $messageFactory->createFromUpdate($bot, $data);

        if (!$inbound instanceof InboundMessage) {
            return new JsonResponse(['ok' => true]);
        }

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
