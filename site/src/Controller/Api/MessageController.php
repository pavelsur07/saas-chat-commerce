<?php

namespace App\Controller\Api;

use App\Entity\Message;
use App\Repository\ClientRepository;
use App\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
}

