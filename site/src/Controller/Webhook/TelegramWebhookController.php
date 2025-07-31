<?php

namespace App\Controller\Webhook;


use App\Entity\Client;
use App\Entity\Message;
use App\Repository\TelegramBotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Nonstandard\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bot')]
class TelegramWebhookController extends AbstractController
{
    #[Route('/{token}', name: 'telegram.webhook', methods: ['POST'])]
    public function handleWebhook(
        string $token,
        Request $request,
        TelegramBotRepository $botRepo,
        EntityManagerInterface $em
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
        $message = new Message($client, $msg['text'] ?? '', 'in');
        $em->persist($message);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
