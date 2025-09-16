<?php

namespace App\Controller\Api;

use App\AI\SuggestionRateLimiter;
use App\AI\SuggestionService;
use App\Repository\Messaging\ClientRepository;
use App\Service\Company\CompanyContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private readonly SuggestionRateLimiter $limiter,   // ← добавили
    ) {
    }

    #[Route('/{clientId}', name: 'api_suggestions_generate', methods: ['POST'])]
    public function generate(string $clientId): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], 403);
        }

        if (!$this->clients->belongsToCompany($clientId, $company->getId())) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        // Rate-limit: не чаще 1 запроса / 3 сек на диалог
        if (!$this->limiter->acquire($company, $clientId)) {
            // "мягкая" отдача — пустой список, без 429
            return $this->json([
                'suggestions' => [],
                'knowledgeHitsCount' => 0,
            ]);
        }

        /* throw new \DomainException('Company Id'.$company->getId().'--- Client Id'.$clientId); */

        $result = $this->suggestions->suggest($company, $clientId);
        $knowledgeHits = (int) ($result['knowledgeHitsCount'] ?? 0);
        $suggestions = [];
        if (isset($result['suggestions']) && is_array($result['suggestions'])) {
            $suggestions = $result['suggestions'];
        }

        return $this->json([
            'suggestions' => $suggestions,
            'knowledgeHitsCount' => $knowledgeHits,
        ]);
    }
}
