<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Entity\Message;
use App\Repository\ClientRepository;
use App\Repository\MessageRepository;
use App\Repository\TelegramBotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Nonstandard\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
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
    public function list(string $client_id, Request $request, ClientRepository $clients, MessageRepository $messages): JsonResponse
    {
        $activeCompanyId = $request->getSession()->get('active_company_id');
        if (!$activeCompanyId) {
            return new JsonResponse(['error' => 'Active company not selected'], Response::HTTP_FORBIDDEN);
        }

        $client = $clients->find($client_id);
        if (!$client) {
            return new JsonResponse(['error' => 'Client not found'], Response::HTTP_NOT_FOUND);
        }

        if ($client->getCompany()->getId() !== $activeCompanyId) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $items = $messages->findBy(['client' => $client], ['createdAt' => 'ASC']);
        $dataMessages = array_map(static function (Message $message) {
            return [
                'id' => $message->getId(),
                'text' => $message->getText(),
                'direction' => $message->getDirection() === Message::IN ? 'incoming' : 'outgoing',
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
        ];

        return new JsonResponse($data);
    }

    #[Route('/api/messages/{client_id}', name: 'api.messages.send', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function send(
        string $client_id,
        Request $request,
        ClientRepository $clients,
        TelegramBotRepository $bots,
        EntityManagerInterface $em,
        HttpClientInterface $httpClient,
        ValidatorInterface $validator,
    ): JsonResponse {
        $activeCompanyId = $request->getSession()->get('active_company_id');
        if (!$activeCompanyId) {
            return new JsonResponse(['error' => 'Active company not selected'], Response::HTTP_FORBIDDEN);
        }

        $client = $clients->find($client_id);
        if (!$client) {
            return new JsonResponse(['error' => 'Client not found'], Response::HTTP_NOT_FOUND);
        }

        if ($client->getCompany()->getId() !== $activeCompanyId) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        if ($client->getChannel() !== Client::TELEGRAM) {
            return new JsonResponse(['error' => 'Client is not from telegram'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $text = $data['text'] ?? null;

        $errors = $validator->validate($text, [new Assert\NotBlank(), new Assert\Length(max: 1000)]);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => 'Invalid text'], Response::HTTP_BAD_REQUEST);
        }

        $bot = $bots->findOneBy(['company' => $client->getCompany(), 'isActive' => true]);
        if (!$bot) {
            return new JsonResponse(['error' => 'Active telegram bot not found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $response = $httpClient->request('POST', sprintf('https://api.telegram.org/bot%s/sendMessage', $bot->getToken()), [
                'json' => [
                    'chat_id' => $client->getExternalId(),
                    'text' => $text,
                ],
            ]);

            $result = $response->toArray(false);
            if (!($result['ok'] ?? false)) {
                throw new \RuntimeException('Telegram API error');
            }
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Telegram API error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $message = Message::messageOut(Uuid::uuid4()->toString(), $client, $text);
        $em->persist($message);
        $em->flush();

        return new JsonResponse([
            'status' => 'success',
            'message_id' => $message->getId(),
        ]);
    }
}

