<?php

declare(strict_types=1);

namespace App\Controller\AI;

use App\Service\AI\AiPromptLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ai/prompt-logs')]
final class AiPromptLogController extends AbstractController
{
    public function __construct(private readonly AiPromptLogService $service)
    {
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $r): JsonResponse
    {
        $data = json_decode($r->getContent() ?: '[]', true);
        $log = $this->service->create($data, $this->getUser());

        return $this->json([
            'id' => $log->getId(),
            'status' => $log->getStatus()->value,
        ], 201);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function getOne(string $id): JsonResponse
    {
        $l = $this->service->get($id);

        return $this->json([
            'id' => $l->getId(),
            'companyId' => $l->getCompany()->getId(),
            'userId' => $l->getUser()?->getId(),
            'channel' => $l->getChannel(),
            'model' => $l->getModel(),
            'prompt' => $l->getPrompt(),
            'response' => $l->getResponse(),
            'tokens' => [
                'prompt' => $l->getPromptTokens(),
                'completion' => $l->getCompletionTokens(),
                'total' => $l->getTotalTokens(),
            ],
            'latencyMs' => $l->getLatencyMs(),
            'status' => $l->getStatus()->value,
            'errorMessage' => $l->getErrorMessage(),
            'costUsd' => $l->getCostUsd(),
            'metadata' => $l->getMetadata(),
            'createdAt' => $l->getCreatedAt()->format(DATE_ATOM),
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->json(null, 204);
    }

    #[Route('', methods: ['GET'])]
    public function search(Request $r): JsonResponse
    {
        $filters = [
            'model' => $r->query->get('model'),
            'status' => $r->query->get('status'),
            'channel' => $r->query->get('channel'),
            'userId' => $r->query->get('userId'),
            'from' => $r->query->get('from'),
            'to' => $r->query->get('to'),
            'onlyErrors' => $r->query->has('onlyErrors') ? filter_var($r->query->get('onlyErrors'), FILTER_VALIDATE_BOOLEAN) : null,
            'feature' => $r->query->get('feature'),
        ];
        $page = (int) $r->query->get('page', 1);
        $limit = (int) $r->query->get('limit', 20);

        $filters = array_filter($filters, static fn ($v) => null !== $v && '' !== $v);

        $res = $this->service->search($filters, $page, $limit);

        return $this->json([
            'total' => $res['total'],
            'items' => array_map(static fn ($l) => [
                'id' => $l->getId(),
                'model' => $l->getModel(),
                'channel' => $l->getChannel(),
                'status' => $l->getStatus()->value,
                'latencyMs' => $l->getLatencyMs(),
                'totalTokens' => $l->getTotalTokens(),
                'createdAt' => $l->getCreatedAt()->format(DATE_ATOM),
                'feature' => $l->getMetadata()['feature'] ?? null,
            ], $res['items']),
        ]);
    }
}
