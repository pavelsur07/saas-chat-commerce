<?php

namespace App\Controller\Api\Crm;

use App\Account\Entity\Company;
use App\Entity\Crm\CrmDeal;
use App\Entity\Crm\CrmPipeline;
use App\Entity\Crm\CrmStage;
use App\Service\Company\CompanyContextService;
use App\Service\Crm\PipelineManager;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Контракты API:
 *
 * @psalm-type StageOutput = array{
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
 * @psalm-type StageCreateInput = array{
 *     name: string,
 *     position: int,
 *     color?: string|null,
 *     probability?: int|null,
 *     isStart?: bool|null,
 *     isWon?: bool|null,
 *     isLost?: bool|null,
 *     slaHours?: int|null
 * }
 * @psalm-type StageUpdateInput = array{
 *     name?: string|null,
 *     color?: string|null,
 *     probability?: int|null,
 *     isStart?: bool|null,
 *     isWon?: bool|null,
 *     isLost?: bool|null,
 *     slaHours?: int|null
 * }
 * @psalm-type StageReorderPair = array{stageId: string, position: int}
 * @psalm-type StageReorderInput = array{order: list<StageReorderPair>}
 */
#[Route('/api/crm')]
#[IsGranted('ROLE_USER')]
final class StageController extends AbstractController
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly EntityManagerInterface $em,
        private readonly PipelineManager $pipelineManager,
    ) {
    }

    /**
     * Создаёт этап в указанной воронке.
     *
     * Входной контракт (JSON): StageCreateInput.
     * Выходной контракт: StageOutput.
     */
    #[Route('/pipelines/{pipelineId}/stages', name: 'api_crm_stage_create', methods: ['POST'])]
    public function create(string $pipelineId, Request $request): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $pipeline = $this->findPipelineForCompany($company, $pipelineId);
        if (!$pipeline) {
            return $this->json(['error' => 'Pipeline not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->getPayload($request);

        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        if ($name === '') {
            return $this->json(['error' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        if (!array_key_exists('position', $payload)) {
            return $this->json(['error' => 'Position is required'], Response::HTTP_BAD_REQUEST);
        }

        $position = $this->toInt($payload['position']);
        if ($position === null) {
            return $this->json(['error' => 'Position must be an integer'], Response::HTTP_BAD_REQUEST);
        }
        if ($position < 0) {
            return $this->json(['error' => 'Position must be greater than or equal to zero'], Response::HTTP_BAD_REQUEST);
        }

        $color = isset($payload['color']) ? trim((string) $payload['color']) : '#CBD5E1';
        if ($color === '') {
            $color = '#CBD5E1';
        }

        $probability = 0;
        if (array_key_exists('probability', $payload)) {
            $probabilityValue = $this->toInt($payload['probability']);
            if ($probabilityValue === null) {
                return $this->json(['error' => 'Probability must be an integer'], Response::HTTP_BAD_REQUEST);
            }
            if ($probabilityValue < 0 || $probabilityValue > 100) {
                return $this->json(['error' => 'Probability must be between 0 and 100'], Response::HTTP_BAD_REQUEST);
            }
            $probability = $probabilityValue;
        }

        $isStart = false;
        if (array_key_exists('isStart', $payload)) {
            $converted = $this->toBoolean($payload['isStart']);
            if ($converted === null) {
                return $this->json(['error' => 'isStart must be boolean'], Response::HTTP_BAD_REQUEST);
            }
            $isStart = $converted;
        }

        $isWon = false;
        if (array_key_exists('isWon', $payload)) {
            $converted = $this->toBoolean($payload['isWon']);
            if ($converted === null) {
                return $this->json(['error' => 'isWon must be boolean'], Response::HTTP_BAD_REQUEST);
            }
            $isWon = $converted;
        }

        $isLost = false;
        if (array_key_exists('isLost', $payload)) {
            $converted = $this->toBoolean($payload['isLost']);
            if ($converted === null) {
                return $this->json(['error' => 'isLost must be boolean'], Response::HTTP_BAD_REQUEST);
            }
            $isLost = $converted;
        }

        $slaHours = null;
        if (array_key_exists('slaHours', $payload)) {
            $value = $payload['slaHours'];
            if ($value === null || $value === '') {
                $slaHours = null;
            } else {
                $converted = $this->toInt($value);
                if ($converted === null) {
                    return $this->json(['error' => 'slaHours must be an integer or null'], Response::HTTP_BAD_REQUEST);
                }
                if ($converted < 0) {
                    return $this->json(['error' => 'slaHours must be greater than or equal to zero'], Response::HTTP_BAD_REQUEST);
                }
                $slaHours = $converted;
            }
        }

        try {
            $stage = $this->pipelineManager->createStage(
                $pipeline,
                $name,
                $position,
                $color,
                $probability,
                $isStart,
                $isWon,
                $isLost,
                $slaHours,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->formatStage($stage), Response::HTTP_CREATED);
    }

    /**
     * Обновляет свойства этапа.
     *
     * Входной контракт (JSON): StageUpdateInput.
     * Выходной контракт: StageOutput.
     */
    #[Route('/stages/{id}', name: 'api_crm_stage_update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $stage = $this->findStageForCompany($company, $id);
        if (!$stage) {
            return $this->json(['error' => 'Stage not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->getPayload($request);

        $name = $stage->getName();
        if (array_key_exists('name', $payload)) {
            $nameCandidate = trim((string) $payload['name']);
            if ($nameCandidate === '') {
                return $this->json(['error' => 'Name cannot be blank'], Response::HTTP_BAD_REQUEST);
            }
            $name = $nameCandidate;
        }

        $color = $stage->getColor();
        if (array_key_exists('color', $payload)) {
            $colorCandidate = trim((string) $payload['color']);
            if ($colorCandidate === '') {
                return $this->json(['error' => 'Color cannot be blank'], Response::HTTP_BAD_REQUEST);
            }
            $color = $colorCandidate;
        }

        $probability = $stage->getProbability();
        if (array_key_exists('probability', $payload)) {
            $probabilityValue = $this->toInt($payload['probability']);
            if ($probabilityValue === null) {
                return $this->json(['error' => 'Probability must be an integer'], Response::HTTP_BAD_REQUEST);
            }
            if ($probabilityValue < 0 || $probabilityValue > 100) {
                return $this->json(['error' => 'Probability must be between 0 and 100'], Response::HTTP_BAD_REQUEST);
            }
            $probability = $probabilityValue;
        }

        $isStart = $stage->isStart();
        if (array_key_exists('isStart', $payload)) {
            $converted = $this->toBoolean($payload['isStart']);
            if ($converted === null) {
                return $this->json(['error' => 'isStart must be boolean'], Response::HTTP_BAD_REQUEST);
            }
            $isStart = $converted;
        }

        $isWon = $stage->isWon();
        if (array_key_exists('isWon', $payload)) {
            $converted = $this->toBoolean($payload['isWon']);
            if ($converted === null) {
                return $this->json(['error' => 'isWon must be boolean'], Response::HTTP_BAD_REQUEST);
            }
            $isWon = $converted;
        }

        $isLost = $stage->isLost();
        if (array_key_exists('isLost', $payload)) {
            $converted = $this->toBoolean($payload['isLost']);
            if ($converted === null) {
                return $this->json(['error' => 'isLost must be boolean'], Response::HTTP_BAD_REQUEST);
            }
            $isLost = $converted;
        }

        $slaHours = $stage->getSlaHours();
        if (array_key_exists('slaHours', $payload)) {
            $value = $payload['slaHours'];
            if ($value === null || $value === '') {
                $slaHours = null;
            } else {
                $converted = $this->toInt($value);
                if ($converted === null) {
                    return $this->json(['error' => 'slaHours must be an integer or null'], Response::HTTP_BAD_REQUEST);
                }
                if ($converted < 0) {
                    return $this->json(['error' => 'slaHours must be greater than or equal to zero'], Response::HTTP_BAD_REQUEST);
                }
                $slaHours = $converted;
            }
        }

        try {
            $this->pipelineManager->updateStage(
                $stage,
                $name,
                $color,
                $probability,
                $isStart,
                $isWon,
                $isLost,
                $slaHours,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->formatStage($stage));
    }

    /**
     * Удаляет этап при отсутствии активных сделок.
     *
     * Выходной контракт: HTTP 204 без тела при успехе.
     */
    #[Route('/stages/{id}', name: 'api_crm_stage_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $stage = $this->findStageForCompany($company, $id);
        if (!$stage) {
            return $this->json(['error' => 'Stage not found'], Response::HTTP_NOT_FOUND);
        }

        $activeDeals = $this->em->getRepository(CrmDeal::class)->count([
            'company' => $company,
            'stage' => $stage,
            'isClosed' => false,
        ]);

        if ($activeDeals > 0) {
            return $this->json(['error' => 'Stage has active deals'], Response::HTTP_CONFLICT);
        }

        $this->em->remove($stage);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Переупорядочивает этапы воронки.
     *
     * Входной контракт (JSON): StageReorderInput.
     * Выходной контракт: HTTP 204 без тела при успехе.
     */
    #[Route('/pipelines/{id}/stages/reorder', name: 'api_crm_stage_reorder', methods: ['POST'])]
    public function reorder(string $id, Request $request): JsonResponse
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
        $order = $payload['order'] ?? null;
        if (!is_array($order)) {
            return $this->json(['error' => 'order must be an array'], Response::HTTP_BAD_REQUEST);
        }

        $orderPairs = [];
        foreach ($order as $item) {
            if (!is_array($item)) {
                return $this->json(['error' => 'Each order item must be an object'], Response::HTTP_BAD_REQUEST);
            }

            $stageId = $item['stageId'] ?? null;
            if (!is_string($stageId) || trim($stageId) === '') {
                return $this->json(['error' => 'stageId is required for each order item'], Response::HTTP_BAD_REQUEST);
            }

            if (!array_key_exists('position', $item)) {
                return $this->json(['error' => 'position is required for each order item'], Response::HTTP_BAD_REQUEST);
            }

            $position = $this->toInt($item['position']);
            if ($position === null) {
                return $this->json(['error' => 'position must be an integer'], Response::HTTP_BAD_REQUEST);
            }
            if ($position < 0) {
                return $this->json(['error' => 'position must be greater than or equal to zero'], Response::HTTP_BAD_REQUEST);
            }

            $orderPairs[] = [
                'stageId' => $stageId,
                'position' => $position,
            ];
        }

        try {
            $this->pipelineManager->reorderStages($pipeline, $orderPairs);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
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

    private function findStageForCompany(Company $company, string $id): ?CrmStage
    {
        $stage = $this->em->getRepository(CrmStage::class)->find($id);
        if (!$stage) {
            return null;
        }

        $pipeline = $stage->getPipeline();
        if ($pipeline->getCompany()->getId() !== $company->getId()) {
            return null;
        }

        return $stage;
    }

    private function formatStage(CrmStage $stage): array
    {
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

    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
