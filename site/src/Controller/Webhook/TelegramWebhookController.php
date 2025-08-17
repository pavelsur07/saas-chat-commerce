<?php

namespace App\Controller\Webhook;

use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use App\Repository\Messaging\TelegramBotRepository;
use App\Service\AI\AiFeature;
use App\Service\AI\LlmClient;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Nonstandard\Uuid;
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
        EntityManagerInterface $em,
        LlmClient $llm, // декоратор логирует в ai_prompt_log
    ): JsonResponse {
        $bot = $botRepo->findOneBy(['token' => $token, 'isActive' => true]);
        if (!$bot) {
            return new JsonResponse(['error' => 'Invalid bot'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $msg = $data['message'] ?? $data['edited_message'] ?? null;
        if (!$msg) {
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
        $username = $from['username'] ?? null;
        $firstName = $from['first_name'] ?? null;
        $lastName = $from['last_name'] ?? null;

        // Найти или создать клиента по externalId+company
        /** @var Client|null $client */
        $client = $em->getRepository(Client::class)->findOneBy([
            'externalId' => $telegramId,
            'company' => $bot->getCompany(),
        ]);
        if (!$client) {
            $client = new Client(
                id: Uuid::uuid4()->toString(),
                channel: Client::TELEGRAM,
                externalId: $telegramId,
                company: $bot->getCompany()
            );
            $client->setUsername($username);
            $client->setFirstName($firstName);
            $client->setLastName($lastName);
            $em->persist($client);
        }

        $text = $msg['text'] ?? null;

        // Сохраняем сообщение (даже если text пуст — payload пригодится)
        $message = Message::messageIn(
            Uuid::uuid4()->toString(),
            $client,
            $bot,
            $text,
            $msg // сырой payload
        );
        $em->persist($message);
        $em->flush();

        // Нотифицируем фронт (Redis → Socket.IO)
        try {
            $redis = new \Predis\Client([
                'scheme' => 'tcp',
                'host' => $_ENV['REDIS_REALTIME_HOST'] ?? 'redis-realtime',
                'port' => (int) ($_ENV['REDIS_REALTIME_PORT'] ?? 6379),
            ]);
            $redis->publish("chat.client.{$client->getId()}", json_encode([
                'id' => $message->getId(),
                'clientId' => $client->getId(),
                'text' => $message->getText(),
                'direction' => 'in',
                'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            // не роняем webhook
        }

        // AI: можно вызывать только если есть текст
        try {
            if (is_string($text) && '' !== $text) {
                $res = $llm->chat([
                    'model' => 'gpt-4o-mini',
                    'messages' => [['role' => 'user', 'content' => $text]],
                    // LlmClientWithLogging expects feature as string
                    'feature' => AiFeature::INTENT_CLASSIFY->value,
                    'channel' => 'telegram',
                    'company' => $bot->getCompany(),
                ]);

                /*$res = $llm->chat([
                    'model' => 'gpt-4o-mini',
                    'messages' => [['role' => 'user', 'content' => $text]],
                    'feature' => AiFeature::INTENT_CLASSIFY->value ?? 'intent_classify',
                    'channel' => 'telegram',
                ]);*/

                $intent = trim((string) ($res['content'] ?? ''));
                $meta = $message->getMeta() ?? [];
                $meta['ai'] = array_merge($meta['ai'] ?? [], ['intent' => $intent]);
                $message->setMeta($meta);
                $em->flush();
            }
        } catch (\Throwable) {
            // записей в ai_prompt_log достаточно; ошибку не пробрасываем
        }

        return new JsonResponse(['ok' => true]);
    }
}
