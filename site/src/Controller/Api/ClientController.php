<?php

namespace App\Controller\Api;

use App\Account\Entity\User as CompanyUser;
use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use App\Repository\Company\CompanyRepository;
use App\Repository\Messaging\ClientReadStateRepository;
use App\Repository\Messaging\ClientRepository;
use App\Repository\Messaging\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ClientController extends AbstractController
{
    #[Route('/api/clients', name: 'api.clients', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        ClientRepository $clients,
        CompanyRepository $companies,
        MessageRepository $messages,
        ClientReadStateRepository $readStates
    ): JsonResponse
    {
        $activeCompanyId = $request->getSession()->get('active_company_id');

        if (!$activeCompanyId) {
            return new JsonResponse(['error' => 'Active company not selected'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        if (!$user instanceof CompanyUser) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_FORBIDDEN);
        }

        $company = $companies->find($activeCompanyId);
        if (!$company) {
            return new JsonResponse(['error' => 'Active company not found'], Response::HTTP_FORBIDDEN);
        }

        $items = $clients->findByCompanyWithMessages($company);

        $data = array_map(function (Client $client) use ($messages, $readStates, $company, $user) {
            $lastMessage = $messages->findLastOneByClient($client->getId());
            $awaiting = $lastMessage ? Message::IN === $lastMessage->getDirection() : false;

            $telegramBotName = null;
            $bot = $client->getTelegramBot();
            if ($bot) {
                $telegramBotName = $bot->getFirstName() ?? $bot->getUsername();
            }

            $state = $readStates->findOneBy([
                'company' => $company,
                'client' => $client,
                'user' => $user,
            ]);

            if ($lastMessage) {
                if ($state && null !== $state->getLastReadAt()) {
                    $unread = $messages->countInboundAfter($client->getId(), $state->getLastReadAt());
                } else {
                    $unread = $messages->countAllInbound($client->getId());
                }
            } else {
                $unread = 0;
            }

            return [
                'id' => $client->getId(),
                'name' => $client->getUsername(),
                'channel' => $client->getChannel(),
                'external_id' => $client->getExternalId(),
                'source' => $client->getChannel(),
                'unread_count' => $unread,
                'awaiting' => $awaiting,
                'telegram_bot_name' => $telegramBotName,
            ];
        }, $items);

        return new JsonResponse($data);
    }
}
