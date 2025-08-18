<?php

namespace App\Controller\Api;

use App\AI\SuggestionService;
use App\Repository\Messaging\ClientRepository;
use App\Service\Company\CompanyContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/suggestions')]
#[IsGranted('ROLE_USER')]
final class SuggestionController extends AbstractController
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly ClientRepository $clients,
        private readonly SuggestionService $suggestions,
    ) {
    }

    #[Route('/{clientId}', name: 'api_suggestions_generate', methods: ['POST'])]
    public function generate(int $clientId, Request $request): JsonResponse
    {
        $companyId = $this->companyContext->getCurrentCompanyIdOrThrow();

        // Валидация принадлежности клиента компании
        if (!$this->clients->belongsToCompany($clientId, $companyId)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $data = [
            'suggestions' => $this->suggestions->suggest($companyId, $clientId),
        ];

        return $this->json($data);
    }
}
