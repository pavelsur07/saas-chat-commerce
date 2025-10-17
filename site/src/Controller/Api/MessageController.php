<?php

namespace App\Controller\Api;

use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use App\Entity\Company\User as CompanyUser;
use App\Entity\Messaging\ClientReadState;
use App\Repository\Messaging\ClientReadStateRepository;
use App\Repository\Messaging\ClientRepository;
use App\Repository\Messaging\MessageRepository;
use App\Service\Messaging\Dto\OutboundMessage;
use App\Service\Messaging\MessageEgressService;
use App\Service\Messaging\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Predis\Client as RedisClient;
use Ramsey\Uuid\Nonstandard\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageController extends AbstractController
{
    #[Route('/api/messages/{client_id}', name: 'api.messages', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(
        string $client_id,
        Request $request,
        ClientRepository $clients,
        MessageRepository $messages,
        ClientReadStateRepository $readStates,
    ): JsonResponse {
        $activeCompanyId = $request->getSession()->get('active_company_id');
        if (!$activeCompanyId) {
            return new JsonResponse(['error' => 'Active company not selected'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        if (!$user instanceof CompanyUser) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_FORBIDDEN);
        }

        $client = $clients->find($client_id);
        if (!$client) {
            return new JsonResponse(['error' => 'Client not found'], Response::HTTP_NOT_FOUND);
        }

        if ($client->getCompany()->getId() !== $activeCompanyId) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $limit = (int) $request->query->get('limit', 30);
        if ($limit <= 0) {
            $limit = 30;
        }
        $limit = min($limit, 100);

        $beforeId = $request->query->get('before_id');

        if ($beforeId) {
            $beforeMessage = $messages->find($beforeId);
            if ($beforeMessage && $beforeMessage->getClient()->getId() === $client->getId()) {
                $items = $messages->findBefore($client, $beforeMessage->getCreatedAt(), $limit);
            } else {
                $items = [];
            }
        } else {
            $items = $messages->findLastByClient($client->getId(), $limit);
        }

        $lastReadMessageId = null;
        $state = $readStates->findOneBy([
            'company' => $client->getCompany(),
            'client' => $client,
            'user' => $user,
        ]);

        if ($state && $state->getLastReadAt()) {
            $lastReadMessage = $messages->findLastBeforeOrEqual($client, $state->getLastReadAt());
            if ($lastReadMessage) {
                $lastReadMessageId = $lastReadMessage->getId();
            }
        }

        $dataMessages = array_map(static function (Message $message) {
            return [
                'id' => $message->getId(),
                'text' => $message->getText(),
                'direction' => Message::IN === $message->getDirection() ? 'in' : 'out',
                'timestamp' => $message->getCreatedAt()->format(DATE_ATOM),
            ];
        }, $items);

        $data = [
            'client' => [
                'id' => $client->getId(),
                'name' => $client->getUsername(),
                'channel' => $client->getChannel(),
                'external_id' => $client->getExternalId(),
            ],
            'messages' => $dataMessages,
            'last_read_message_id' => $lastReadMessageId,
        ];

        return new JsonResponse($data);
    }

    #[Route('/api/messages/{client_id}', name: 'api.messages.send', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function send(
        string $client_id,
        Request $request,
        ClientRepository $clients,
        MessageRepository $messages,
        EntityManagerInterface $em,
        TelegramService $telegramService,
        ValidatorInterface $validator,
        MessageEgressService $egress,
    ): JsonResponse {
        $activeCompanyId = $request->getSession()->get('active_company_id');

        if (!$activeCompanyId) {
            return new JsonResponse(['error' => 'Active company not selected'], Response::HTTP_FORBIDDEN);
        }

        /** @var Client $client */
        $client = $clients->find($client_id);
        if (!$client) {
            return new JsonResponse(['error' => 'Client not found'], Response::HTTP_NOT_FOUND);
        }

        if ($client->getCompany()->getId() !== $activeCompanyId) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            $data = $request->request->all();
        }

        $text = $data['text'] ?? null;

        $errors = $validator->validate($text, [new Assert\NotBlank(), new Assert\Length(max: 1000)]);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => 'Invalid text'], Response::HTTP_BAD_REQUEST);
        }

        if ($client->getChannel()->value === Channel::WEB->value) {
            $message = Message::messageOut(Uuid::uuid4()->toString(), $client, null, $text);
            $em->persist($message);
            $em->flush();

            $egress->send(new OutboundMessage('web', $client->getId(), $text));

            $redis = new RedisClient([
                'scheme' => 'tcp',
                'host' => 'redis-realtime',
                'port' => 6379,
            ]);

            $redis->publish("chat.client.{$client->getId()}", json_encode([
                'id' => $message->getId(),
                'clientId' => $client->getId(),
                'text' => $message->getText(),
                'direction' => 'out',
                'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]));

            return new JsonResponse(['ok' => true]);
        }

        if (Channel::TELEGRAM !== $client->getChannel()) {
            return new JsonResponse(['error' => 'Client is not from telegram'], Response::HTTP_BAD_REQUEST);
        }

        $lastMessage = $messages->findLastInboundByClient($client);
        $bot = $lastMessage?->getTelegramBot();
        if (!$bot) {
            return new JsonResponse(['error' => 'Cannot determine bot for client'], Response::HTTP_BAD_REQUEST);
        }

        $message = Message::messageOut(Uuid::uuid4()->toString(), $client, $bot, $text);
        $em->persist($message);
        $em->flush();

        /* $egress->send(new OutboundMessage(
             channel: $client->getChannel()->value,          // 'telegram'
             recipientRef: $client->getExternalId(),  // chatId
             text: $text,
             meta: ['token' => $bot->getToken()]
         ));*/

        $egress->send(new OutboundMessage(
            channel: Channel::TELEGRAM->value,       // 'telegram'
            recipientRef: $client->getExternalId(),  // chatId
            text: $text,
            meta: ['token' => $bot->getToken()]
        ));

        // после успешного сохранения исходящего
        $redis = new RedisClient([
            'scheme' => 'tcp',
            'host' => 'redis-realtime',
            'port' => 6379,
        ]);

        $redis->publish("chat.client.{$client->getId()}", json_encode([
            'id' => $message->getId(),
            'clientId' => $client->getId(),
            'text' => $message->getText(),
            'direction' => 'out',
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]));

        return new JsonResponse([
            'status' => 'success',
            'message_id' => $message->getId(),
        ]);
    }

    #[Route('/api/messages/{client_id}/read', name: 'api.messages.read', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function markRead(
        string $client_id,
        EntityManagerInterface $em,
        ClientRepository $clients,
        MessageRepository $messages,
        ClientReadStateRepository $readStates,
        Request $request
    ): JsonResponse {
        $activeCompanyId = $request->getSession()->get('active_company_id');
        if (!$activeCompanyId) {
            return new JsonResponse(['error' => 'Active company not selected'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        if (!$user instanceof CompanyUser) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_FORBIDDEN);
        }

        /** @var Client|null $client */
        $client = $clients->find($client_id);
        if (!$client) {
            return new JsonResponse(['error' => 'Client not found'], Response::HTTP_NOT_FOUND);
        }

        $company = $client->getCompany();
        if ($company->getId() !== $activeCompanyId) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $lastMessage = $messages->findLastOneByClient($client_id);
        if (!$lastMessage) {
            return new JsonResponse(['ok' => true, 'unread_count' => 0]);
        }

        $state = $readStates->findOneBy([
            'company' => $company,
            'client' => $client,
            'user' => $user,
        ]);

        $lastCreatedAt = $lastMessage->getCreatedAt();

        if (!$state) {
            $state = new ClientReadState(
                Uuid::uuid4()->toString(),
                $company,
                $client,
                $user,
            );
            $state->setLastReadAt($lastCreatedAt);
            $em->persist($state);
        } else {
            $prevReadAt = $state->getLastReadAt();
            if (!$prevReadAt || $lastCreatedAt > $prevReadAt) {
                $state->setLastReadAt($lastCreatedAt);
            }
        }

        $em->flush();

        return new JsonResponse(['ok' => true, 'unread_count' => 0]);
    }
}
