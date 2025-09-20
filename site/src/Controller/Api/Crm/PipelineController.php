<?php

namespace App\Controller\Api\Crm;

use App\Entity\Company\Company;
use App\Entity\Crm\CrmDeal;
use App\Entity\Crm\CrmPipeline;
use App\Entity\Crm\CrmStage;
use App\Service\Company\CompanyContextService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Контракты API:
 *
 * @psalm-type PipelineStageOutput = array{
 *     id: string,
 *     name: string,
 *     position: int,
 *     color: string,
 *     probability: int,
 *     isStart: bool,
 *     isWon: bool,
 *     isLost: bool,
 *     slaHours: int|null,
 *     createdAt: string,
 *     updatedAt: string
 * }
 * @psalm-type PipelineOutput = array{
 *     id: string,
 *     name: string,
 *     slug: string,
 *     isDefault: bool,
 *     createdAt: string,
 *     updatedAt: string
 * }
 * @psalm-type PipelineDetailedOutput = PipelineOutput&array{
 *     stages: list<PipelineStageOutput>
 * }
 * @psalm-type PipelineCreateInput = array{
 *     name: string,
 *     slug?: string|null,
 *     isDefault?: bool
 * }
 * @psalm-type PipelineUpdateInput = array{
 *     name?: string|null,
 *     slug?: string|null,
 *     isDefault?: bool
 * }
 */
#[Route('/api/crm/pipelines')]
#[IsGranted('ROLE_USER')]
final class PipelineController extends AbstractController
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Возвращает список воронок текущей компании.
     *
     * Выходной контракт: list<PipelineOutput>.
     */
    #[Route('', name: 'api_crm_pipeline_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $pipelines = $this->em->getRepository(CrmPipeline::class)->findBy(
            ['company' => $company],
            ['createdAt' => 'ASC'],
        );

        $data = array_map(fn (CrmPipeline $pipeline) => $this->formatPipeline($pipeline), $pipelines);

        return $this->json($data);
    }

    /**
     * Создаёт новую воронку.
     *
     * Входной контракт (JSON): PipelineCreateInput.
     * Выходной контракт: PipelineOutput.
     */
    #[Route('', name: 'api_crm_pipeline_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->getPayload($request);
        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        if ($name === '') {
            return $this->json(['error' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $slugSource = isset($payload['slug']) ? trim((string) $payload['slug']) : $name;
        $slug = $this->slugify($slugSource);
        if ($slug === '') {
            $slug = 'pipeline';
        }
        $slug = $this->ensureUniqueSlug($company, $slug);

        $isDefault = false;
        if (array_key_exists('isDefault', $payload)) {
            $converted = $this->toBoolean($payload['isDefault']);
            if ($converted === null) {
                return $this->json(['error' => 'isDefault must be boolean'], Response::HTTP_BAD_REQUEST);
            }
            $isDefault = $converted;
        }

        $now = new DateTimeImmutable();
        $pipeline = new CrmPipeline(Uuid::uuid4()->toString(), $company);
        $pipeline->setName($name);
        $pipeline->setSlug($slug);
        $pipeline->setIsDefault($isDefault);
        $pipeline->setCreatedAt($now);
        $pipeline->setUpdatedAt($now);

        if ($isDefault) {
            $this->resetDefaultPipelines($company, $pipeline, $now);
        }

        $this->em->persist($pipeline);
        $this->em->flush();

        return $this->json($this->formatPipeline($pipeline), Response::HTTP_CREATED);
    }

    /**
     * Возвращает детали воронки вместе со стадиями.
     *
     * Выходной контракт: PipelineDetailedOutput.
     */
    #[Route('/{id}', name: 'api_crm_pipeline_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $pipeline = $this->findPipelineForCompany($company, $id);
        if (!$pipeline) {
            return $this->json(['error' => 'Pipeline not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->formatPipeline($pipeline, true));
    }

    /**
     * Обновляет свойства воронки.
     *
     * Входной контракт (JSON): PipelineUpdateInput.
     * Выходной контракт: PipelineOutput.
     */
    #[Route('/{id}', name: 'api_crm_pipeline_update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $pipeline = $this->findPipelineForCompany($company, $id);
        if (!$pipeline) {
            return $this->json(['error' => 'Pipeline not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->getPayload($request);
        $hasChanges = false;
        $now = new DateTimeImmutable();

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                return $this->json(['error' => 'Name cannot be blank'], Response::HTTP_BAD_REQUEST);
            }
            $pipeline->setName($name);
            $hasChanges = true;
        }

        if (array_key_exists('slug', $payload)) {
            $slugInput = trim((string) $payload['slug']);
            if ($slugInput === '') {
                return $this->json(['error' => 'Slug cannot be blank'], Response::HTTP_BAD_REQUEST);
            }
            $slug = $this->slugify($slugInput);
            if ($slug === '') {
                return $this->json(['error' => 'Slug cannot be blank'], Response::HTTP_BAD_REQUEST);
            }
            $slug = $this->ensureUniqueSlug($company, $slug, $pipeline);
            $pipeline->setSlug($slug);
            $hasChanges = true;
        }

        if (array_key_exists('isDefault', $payload)) {
            $converted = $this->toBoolean($payload['isDefault']);
            if ($converted === null) {
                return $this->json(['error' => 'isDefault must be boolean'], Response::HTTP_BAD_REQUEST);
            }
            $pipeline->setIsDefault($converted);
            if ($converted) {
                $this->resetDefaultPipelines($company, $pipeline, $now);
            }
            $hasChanges = true;
        }

        if ($hasChanges) {
            $pipeline->setUpdatedAt($now);
            $this->em->flush();
        }

        return $this->json($this->formatPipeline($pipeline));
    }

    /**
     * Удаляет воронку, если к ней не привязаны сделки.
     *
     * Выходной контракт: HTTP 204 без тела при успехе.
     */
    #[Route('/{id}', name: 'api_crm_pipeline_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $pipeline = $this->findPipelineForCompany($company, $id);
        if (!$pipeline) {
            return $this->json(['error' => 'Pipeline not found'], Response::HTTP_NOT_FOUND);
        }

        $dealsCount = $this->em->getRepository(CrmDeal::class)->count(['pipeline' => $pipeline]);
        if ($dealsCount > 0) {
            return $this->json(['error' => 'Pipeline has active deals'], Response::HTTP_CONFLICT);
        }

        $this->em->remove($pipeline);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Возвращает сырые данные запроса.
     *
     * @return array<string, mixed>
     */
    private function getPayload(Request $request): array
    {
        $content = trim((string) $request->getContent());
        if ($content !== '') {
            $data = json_decode($content, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return $request->request->all();
    }

    private function findPipelineForCompany(Company $company, string $id): ?CrmPipeline
    {
        return $this->em->getRepository(CrmPipeline::class)->findOneBy([
            'id' => $id,
            'company' => $company,
        ]);
    }

    /**
     * @psalm-return ($includeStages is true ? PipelineDetailedOutput : PipelineOutput)
     */
    private function formatPipeline(CrmPipeline $pipeline, bool $includeStages = false): array
    {
        $data = [
            'id' => $pipeline->getId(),
            'name' => $pipeline->getName(),
            'slug' => $pipeline->getSlug(),
            'isDefault' => $pipeline->isDefault(),
            'createdAt' => $pipeline->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $pipeline->getUpdatedAt()->format(DATE_ATOM),
        ];

        if ($includeStages) {
            $stages = $this->em->getRepository(CrmStage::class)->findBy([
                'pipeline' => $pipeline,
            ], ['position' => 'ASC']);

            $data['stages'] = array_map(static function (CrmStage $stage) {
                return [
                    'id' => $stage->getId(),
                    'name' => $stage->getName(),
                    'position' => $stage->getPosition(),
                    'color' => $stage->getColor(),
                    'probability' => $stage->getProbability(),
                    'isStart' => $stage->isStart(),
                    'isWon' => $stage->isWon(),
                    'isLost' => $stage->isLost(),
                    'slaHours' => $stage->getSlaHours(),
                    'createdAt' => $stage->getCreatedAt()->format(DATE_ATOM),
                    'updatedAt' => $stage->getUpdatedAt()->format(DATE_ATOM),
                ];
            }, $stages);
        }

        return $data;
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $lower = mb_strtolower($value);
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        if ($transliterated !== false) {
            $value = $transliterated;
        } else {
            $value = $lower;
        }

        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim((string) $value, '-');

        return $value ?? '';
    }

    private function ensureUniqueSlug(Company $company, string $baseSlug, ?CrmPipeline $ignore = null): string
    {
        $slug = $baseSlug;
        $counter = 2;
        $repository = $this->em->getRepository(CrmPipeline::class);

        while (true) {
            $existing = $repository->findOneBy([
                'company' => $company,
                'slug' => $slug,
            ]);

            if (!$existing || ($ignore && $existing->getId() === $ignore->getId())) {
                return $slug;
            }

            $slug = sprintf('%s-%d', $baseSlug, $counter);
            $counter++;
        }
    }

    private function resetDefaultPipelines(Company $company, CrmPipeline $current, DateTimeImmutable $now): void
    {
        $defaults = $this->em->getRepository(CrmPipeline::class)->findBy([
            'company' => $company,
            'isDefault' => true,
        ]);

        foreach ($defaults as $pipeline) {
            if ($pipeline->getId() === $current->getId()) {
                continue;
            }

            $pipeline->setIsDefault(false);
            $pipeline->setUpdatedAt($now);
        }
    }

    private function toBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }
}
