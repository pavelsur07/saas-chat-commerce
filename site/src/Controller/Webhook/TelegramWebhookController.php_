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
        methods: ['POST'])
    ]
    public function handleWebhook(
        string $token,
        Request $request,
        TelegramBotRepository $botRepo,
        EntityManagerInterface $em,
        LlmClient $llm, // декоратор добавит лог в ai_prompt_log
    ): JsonResponse {
        $bot = $botRepo->findOneBy(['token' => $token, 'isActive' => true]);
        if (!$bot) {
            return new JsonResponse(['error' => 'Invalid bot'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['message'])) {
            return new JsonResponse(['ok' => true]);
        }

        $msg = $data['message'];
        $chat = $msg['chat'];

        $telegramId = (string) $chat['id'];
        $username = $chat['username'] ?? null;
        $firstName = $chat['first_name'] ?? null;
        $lastName = $chat['last_name'] ?? null;

        // Найти или создать клиента
        $client = $em->getRepository(Client::class)->find($telegramId);
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

        // Сохраняем сообщение
        $message = Message::messageIn(
            Uuid::uuid4()->toString(),
            $client,
            $bot,
            $msg['text'] ?? null,
            $msg
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
        } catch (\Throwable $e) {
            // не роняем webhook — просто можно залогировать через monolog, если нужно
        }

        // AI: классифицируем интент (декоратор запишет ai_prompt_log)
        try {
            $intentRes = $llm->chat([
                'model' => 'gpt-4o-mini',
                'messages' => [['role' => 'user', 'content' => $msg['text'] ?? null]],
                'feature' => AiFeature::INTENT_CLASSIFY->value ?? 'intent_classify',
                'channel' => 'telegram',
            ]);

            $intent = trim((string) ($intentRes['content'] ?? ''));
            $meta = $message->getMeta();
            if (!is_array($meta)) {
                $meta = [];
            }
            $meta['ai'] = array_merge($meta['ai'] ?? [], ['intent' => $intent]);
            $message->setMeta($meta);

            $em->flush();
        } catch (\Throwable $e) {
            // не падаем; запись в ai_prompt_log будет со status=error
        }

        return new JsonResponse(['ok' => true]);
    }
}
