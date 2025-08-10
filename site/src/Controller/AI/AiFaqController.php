<?php

declare(strict_types=1);

namespace App\Controller\AI;

use App\Service\AI\AiFaqService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ai/faq')]
final class AiFaqController extends AbstractController
{
    public function __construct(private readonly AiFaqService $service)
    {
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $r): JsonResponse
    {
        $data = json_decode($r->getContent() ?: '[]', true);
        $faq = $this->service->create($data, $this->getUser());

        return $this->json(['id' => $faq->getId()], 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $r): JsonResponse
    {
        $patch = json_decode($r->getContent() ?: '[]', true);
        $faq = $this->service->update($id, $patch, $this->getUser());

        return $this->json(['id' => $faq->getId()]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->json(null, 204);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function getOne(string $id): JsonResponse
    {
        $faq = $this->service->get($id);

        return $this->json([
            'id' => $faq->getId(),
            'question' => $faq->getQuestion(),
            'answer' => $faq->getAnswer(),
        ]);
    }

    #[Route('', methods: ['GET'])]
    public function search(Request $r): JsonResponse
    {
        $filters = [
            'q' => $r->query->get('q'),
            'language' => $r->query->get('language'),
            'isActive' => $r->query->has('isActive') ? filter_var($r->query->get('isActive'), FILTER_VALIDATE_BOOLEAN) : null,
            'tags' => $r->query->all('tags'), // ?tags=x&tags=y
        ];
        $page = (int) $r->query->get('page', 1);
        $limit = (int) $r->query->get('limit', 20);

        // убираем null, чтобы не мешали
        $filters = array_filter($filters, static fn ($v) => null !== $v && '' !== $v);

        $res = $this->service->search($filters, $page, $limit);

        return $this->json([
            'total' => $res['total'],
            'items' => array_map(static fn ($f) => [
                'id' => $f->getId(),
                'question' => $f->getQuestion(),
                'answer' => $f->getAnswer(),
                'language' => $f->getLanguage(),
                'tags' => $f->getTags(),
                'isActive' => $f->isActive(),
            ], $res['items']),
        ]);
    }
}
