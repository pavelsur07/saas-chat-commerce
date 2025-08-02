<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Repository\CompanyRepository;
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
    public function index(Request $request, ClientRepository $clients, CompanyRepository $companies): JsonResponse
    {
        $activeCompanyId = $request->getSession()->get('active_company_id');
        if (!$activeCompanyId) {
            return new JsonResponse(['error' => 'Active company not selected'], Response::HTTP_FORBIDDEN);
        }

        $company = $companies->find($activeCompanyId);
        if (!$company) {
            return new JsonResponse(['error' => 'Active company not found'], Response::HTTP_FORBIDDEN);
        }

        $items = $clients->findBy(['company' => $company]);

        $data = array_map(static function (Client $client) {
            return [
                'id' => $client->getId(),
                'name' => $client->getUsername(),
                'channel' => $client->getChannel(),
                'external_id' => $client->getExternalId(),
            ];
        }, $items);

        return new JsonResponse($data);
    }
}
