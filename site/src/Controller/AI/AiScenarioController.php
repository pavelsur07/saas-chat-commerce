<?php
declare(strict_types=1);

namespace App\Controller\AI;

use App\Service\AI\AiScenarioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ai/scenarios')]
final class AiScenarioController extends AbstractController
{
    public function __construct(private readonly AiScenarioService $service) {}

    #[Route('', methods: ['POST'])]
    public function create(Request $r): JsonResponse
    {
        $data = json_decode($r->getContent() ?: '[]', true);
        $scenario = $this->service->create($data, $this->getUser());

        return $this->json([
            'id'      => $scenario->getId(),
            'name'    => $scenario->getName(),
            'slug'    => $scenario->getSlug(),
            'version' => $scenario->getVersion(),
            'status'  => $scenario->getStatus()->value,
        ], 201);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function getOne(string $id): JsonResponse
    {
        $s = $this->service->get($id);

        return $this->json([
            'id'         => $s->getId(),
            'name'       => $s->getName(),
            'slug'       => $s->getSlug(),
            'version'    => $s->getVersion(),
            'status'     => $s->getStatus()->value,
            'graph'      => $s->getGraph(),
            'notes'      => $s->getNotes(),
            'publishedAt'=> $s->getPublishedAt()?->format(DATE_ATOM),
            'createdAt'  => $s->getCreatedAt()->format(DATE_ATOM),
            'updatedAt'  => $s->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $r): JsonResponse
    {
        $patch = json_decode($r->getContent() ?: '[]', true);
        $s = $this->service->update($id, $patch, $this->getUser());

        return $this->json([
            'id'      => $s->getId(),
            'name'    => $s->getName(),
            'slug'    => $s->getSlug(),
            'version' => $s->getVersion(),
            'status'  => $s->getStatus()->value,
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
            'name'   => $r->query->get('name'),
            'status' => $r->query->get('status'),
        ];
        $page  = (int)($r->query->get('page', 1));
        $limit = (int)($r->query->get('limit', 20));

        $filters = array_filter($filters, static fn($v) => $v !== null && $v !== '');

        $res = $this->service->search($filters, $page, $limit);

        return $this->json([
            'total' => $res['total'],
            'items' => array_map(static fn($s) => [
                'id'      => $s->getId(),
                'name'    => $s->getName(),
                'slug'    => $s->getSlug(),
                'version' => $s->getVersion(),
                'status'  => $s->getStatus()->value,
                'createdAt' => $s->getCreatedAt()->format(DATE_ATOM),
            ], $res['items']),
        ]);
    }

    #[Route('/{id}/publish', methods: ['POST'])]
    public function publish(string $id): JsonResponse
    {
        $s = $this->service->publish($id, $this->getUser());

        return $this->json([
            'id'         => $s->getId(),
            'status'     => $s->getStatus()->value,
            'publishedAt'=> $s->getPublishedAt()?->format(DATE_ATOM),
        ]);
    }


    #[Route('/{id}/clone', methods: ['POST'])]
    public function cloneVersion(string $id): JsonResponse
    {
        $s = $this->service->cloneVersion($id, $this->getUser());

        return $this->json([
            'id'      => $s->getId(),
            'name'    => $s->getName(),
            'slug'    => $s->getSlug(),
            'version' => $s->getVersion(),
            'status'  => $s->getStatus()->value,
        ], 201);
    }
}
