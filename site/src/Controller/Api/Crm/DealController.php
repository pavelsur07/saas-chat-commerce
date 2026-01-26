<?php

namespace App\Controller\Api\Crm;

use App\Account\Entity\Company;
use App\Account\Entity\User;
use App\Entity\Company\UserCompany;
use App\Entity\Crm\CrmDeal;
use App\Entity\Crm\CrmPipeline;
use App\Entity\Crm\CrmStage;
use App\Entity\Crm\CrmStageHistory;
use App\Entity\Messaging\Client;
use App\Service\Company\CompanyContextService;
use App\Service\Crm\DealFactory;
use App\Service\Crm\DealMover;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Контракты API:
 *
 * @psalm-type DealUserRef = array{
 *     id: string,
 *     email: string|null
 * }
 * @psalm-type DealClientRef = array{
 *     id: string,
 *     displayName: string|null,
 *     channel: string|null,
 *     externalId: string,
 *     username: string|null,
 *     firstName: string|null,
 *     lastName: string|null
 * }
 * @psalm-type DealStageRef = array{
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
 * @psalm-type DealPipelineRef = array{
 *     id: string,
 *     name: string,
 *     slug: string,
 *     createdAt: string,
 *     updatedAt: string
 * }
 * @psalm-type DealStageHistoryEntry = array{
 *     id: string,
 *     fromStage: DealStageRef|null,
 *     toStage: DealStageRef,
 *     comment: string|null,
 *     changedAt: string,
 *     changedBy: DealUserRef,
 *     spentHours: int|null
 * }
 * @psalm-type DealOutput = array{
 *     id: string,
 *     title: string,
 *     amount: string|null,
 *     currency: string,
 *     source: string|null,
 *     note: string|null,
 *     lossReason: string|null,
 *     isClosed: bool,
 *     openedAt: string,
 *     stageEnteredAt: string,
 *     closedAt: string|null,
 *     createdAt: string,
 *     updatedAt: string,
 *     pipeline: DealPipelineRef,
 *     stage: DealStageRef,
 *     owner: DealUserRef|null,
 *     client: DealClientRef|null,
 *     createdBy: DealUserRef,
 *     meta: array<array-key, mixed>
 * }
 * @psalm-type DealDetailedOutput = DealOutput&array{
 *     stageHistory: list<DealStageHistoryEntry>
 * }
 * @psalm-type DealListOutput = array{
 *     items: list<DealOutput>,
 *     total: int,
 *     limit: int,
 *     offset: int
 * }
 * @psalm-type DealCreateInput = array{
 *     pipelineId: string,
 *     title: string,
 *     amount?: string|int|float|null,
 *     clientId?: string|null,
 *     ownerId?: string|null,
 *     source?: string|null,
 *     meta?: array<array-key, mixed>|null
 * }
 * @psalm-type DealUpdateInput = array{
 *     title?: string|null,
 *     amount?: string|int|float|null,
 *     clientId?: string|null,
 *     ownerId?: string|null,
 *     source?: string|null,
 *     currency?: string|null,
 *     note?: string|null,
 *     lossReason?: string|null,
 *     meta?: array<array-key, mixed>|null,
 *     isClosed?: bool|null,
 *     openedAt?: string|null,
 *     closedAt?: string|null
 * }
 * @psalm-type DealMoveInput = array{
 *     toStageId: string,
 *     comment?: string|null
 * }
 */
#[Route('/api/crm/deals')]
#[IsGranted('ROLE_USER')]
final class DealController extends AbstractController
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly EntityManagerInterface $em,
        private readonly DealFactory $dealFactory,
        private readonly DealMover $dealMover,
        #[Autowire(service: 'monolog.logger.crm')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Возвращает сделки текущей компании с фильтрами и пагинацией.
     *
     * Выходной контракт: DealListOutput.
     */
    #[Route('', name: 'api_crm_deal_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $pipelineId = trim((string) $request->query->get('pipeline', ''));
        $stageId = trim((string) $request->query->get('stage', ''));
        $ownerId = trim((string) $request->query->get('owner', ''));
        $search = trim((string) $request->query->get('search', ''));

        $onlyWebForms = null;
        $onlyWebFormsRaw = $request->query->get('onlyWebForms');
        if ($onlyWebFormsRaw !== null && $onlyWebFormsRaw !== '') {
            $onlyWebForms = $this->toBoolean($onlyWebFormsRaw);
            if ($onlyWebForms === null) {
                return $this->json(['error' => 'onlyWebForms must be boolean'], Response::HTTP_BAD_REQUEST);
            }
        }

        $utmCampaign = trim((string) $request->query->get('utmCampaign', ''));

        $limit = 20;
        $limitValue = $request->query->get('limit');
        if ($limitValue !== null && $limitValue !== '') {
            $limitCandidate = $this->toInt($limitValue);
            if ($limitCandidate === null) {
                return $this->json(['error' => 'limit must be an integer'], Response::HTTP_BAD_REQUEST);
            }
            if ($limitCandidate < 1 || $limitCandidate > 100) {
                return $this->json(['error' => 'limit must be between 1 and 100'], Response::HTTP_BAD_REQUEST);
            }
            $limit = $limitCandidate;
        }

        $offset = 0;
        $offsetValue = $request->query->get('offset');
        if ($offsetValue !== null && $offsetValue !== '') {
            $offsetCandidate = $this->toInt($offsetValue);
            if ($offsetCandidate === null) {
                return $this->json(['error' => 'offset must be an integer'], Response::HTTP_BAD_REQUEST);
            }
            if ($offsetCandidate < 0) {
                return $this->json(['error' => 'offset must be greater than or equal to zero'], Response::HTTP_BAD_REQUEST);
            }
            $offset = $offsetCandidate;
        }

        $pipeline = null;
        if ($pipelineId !== '') {
            $pipeline = $this->findPipelineForCompany($company, $pipelineId);
            if (!$pipeline) {
                return $this->json(['error' => 'Pipeline not found'], Response::HTTP_NOT_FOUND);
            }
        }

        $stage = null;
        if ($stageId !== '') {
            $stage = $this->findStageForCompany($company, $stageId);
            if (!$stage) {
                return $this->json(['error' => 'Stage not found'], Response::HTTP_NOT_FOUND);
            }
            if ($pipeline && $stage->getPipeline()->getId() !== $pipeline->getId()) {
                return $this->json(['error' => 'Stage does not belong to the selected pipeline'], Response::HTTP_BAD_REQUEST);
            }
        }

        $owner = null;
        if ($ownerId !== '') {
            $owner = $this->findCompanyUser($company, $ownerId);
            if (!$owner) {
                return $this->json(['error' => 'Owner not found'], Response::HTTP_NOT_FOUND);
            }
        }

        $qb = $this->em->createQueryBuilder()
            ->select('deal', 'pipeline', 'stage', 'owner', 'client', 'createdBy')
            ->from(CrmDeal::class, 'deal')
            ->join('deal.pipeline', 'pipeline')
            ->join('deal.stage', 'stage')
            ->join('deal.createdBy', 'createdBy')
            ->leftJoin('deal.owner', 'owner')
            ->leftJoin('deal.client', 'client')
            ->where('deal.company = :company')
            ->setParameter('company', $company)
            ->orderBy('deal.updatedAt', 'DESC')
            ->addOrderBy('deal.createdAt', 'DESC');

        if ($pipeline) {
            $qb->andWhere('deal.pipeline = :pipeline')->setParameter('pipeline', $pipeline);
        }

        if ($stage) {
            $qb->andWhere('deal.stage = :stage')->setParameter('stage', $stage);
        }

        if ($owner) {
            $qb->andWhere('deal.owner = :owner')->setParameter('owner', $owner);
        }

        if ($search !== '') {
            $searchTerm = '%'.mb_strtolower($search).'%';
            $qb->andWhere('LOWER(deal.title) LIKE :search OR LOWER(COALESCE(deal.note, \'\')) LIKE :search');
            $qb->setParameter('search', $searchTerm);
        }

        if ($onlyWebForms === true) {
            $qb->andWhere('deal.source LIKE :wfSource')
                ->setParameter('wfSource', 'web_form:%');
        }

        if ($utmCampaign !== '') {
            $term = '%'.trim($utmCampaign).'%';
            $qb->andWhere("COALESCE(deal.meta->'utm'->>'utm_campaign', '') ILIKE :utmCampaign")
                ->setParameter('utmCampaign', $term);
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->resetDQLPart('select')
            ->select('COUNT(deal.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $deals = $qb
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        $items = array_map(fn (CrmDeal $deal) => $this->formatDeal($deal), $deals);

        return $this->json([
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Создаёт новую сделку в указанной воронке.
     *
     * Входной контракт (JSON): DealCreateInput.
     * Выходной контракт: DealOutput.
     */
    #[Route('', name: 'api_crm_deal_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->getPayload($request);

        $pipelineId = isset($payload['pipelineId']) ? trim((string) $payload['pipelineId']) : '';
        if ($pipelineId === '') {
            return $this->json(['error' => 'pipelineId is required'], Response::HTTP_BAD_REQUEST);
        }

        $pipeline = $this->findPipelineForCompany($company, $pipelineId);
        if (!$pipeline) {
            return $this->json(['error' => 'Pipeline not found'], Response::HTTP_NOT_FOUND);
        }

        $stage = $this->findStartStage($pipeline);
        if (!$stage) {
            return $this->json(['error' => 'Pipeline does not have stages'], Response::HTTP_CONFLICT);
        }

        $title = isset($payload['title']) ? trim((string) $payload['title']) : '';
        if ($title === '') {
            return $this->json(['error' => 'Title is required'], Response::HTTP_BAD_REQUEST);
        }
        if (mb_strlen($title) > 160) {
            return $this->json(['error' => 'Title must be at most 160 characters'], Response::HTTP_BAD_REQUEST);
        }

        $amount = null;
        if (array_key_exists('amount', $payload)) {
            $amountValue = $payload['amount'];
            if ($amountValue === null || $amountValue === '') {
                $amount = null;
            } else {
                $normalized = $this->normalizeAmount($amountValue);
                if ($normalized === null) {
                    return $this->json(['error' => 'amount must be a decimal with up to 12 digits and 2 decimals'], Response::HTTP_BAD_REQUEST);
                }
                $amount = $normalized;
            }
        }

        $client = null;
        if (array_key_exists('clientId', $payload)) {
            $clientId = $payload['clientId'];
            if ($clientId === null || $clientId === '') {
                $client = null;
            } else {
                if (!is_string($clientId)) {
                    return $this->json(['error' => 'clientId must be a string'], Response::HTTP_BAD_REQUEST);
                }
                $client = $this->findClientForCompany($company, $clientId);
                if (!$client) {
                    return $this->json(['error' => 'Client not found'], Response::HTTP_NOT_FOUND);
                }
            }
        }

        $owner = null;
        if (array_key_exists('ownerId', $payload)) {
            $ownerId = $payload['ownerId'];
            if ($ownerId === null || $ownerId === '') {
                $owner = null;
            } else {
                if (!is_string($ownerId)) {
                    return $this->json(['error' => 'ownerId must be a string'], Response::HTTP_BAD_REQUEST);
                }
                $owner = $this->findCompanyUser($company, $ownerId);
                if (!$owner) {
                    return $this->json(['error' => 'Owner not found'], Response::HTTP_NOT_FOUND);
                }
            }
        }

        $source = null;
        if (array_key_exists('source', $payload)) {
            $sourceCandidate = trim((string) $payload['source']);
            if ($sourceCandidate === '') {
                $source = null;
            } else {
                if (mb_strlen($sourceCandidate) > 40) {
                    return $this->json(['error' => 'Source must be at most 40 characters'], Response::HTTP_BAD_REQUEST);
                }
                $source = $sourceCandidate;
            }
        }

        $meta = [];
        if (array_key_exists('meta', $payload)) {
            $metaValue = $payload['meta'];
            if ($metaValue === null) {
                $meta = [];
            } elseif (!is_array($metaValue)) {
                return $this->json(['error' => 'meta must be an object'], Response::HTTP_BAD_REQUEST);
            } else {
                $meta = $metaValue;
            }
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new LogicException('Authenticated user expected.');
        }

        $deal = $this->dealFactory->create(
            $company,
            $pipeline,
            $stage,
            $user,
            $title,
            $amount,
            $client,
            $owner,
            $source,
            $meta,
        );

        return $this->json($this->formatDeal($deal), Response::HTTP_CREATED);
    }

    /**
     * Возвращает подробную информацию о сделке.
     *
     * Выходной контракт: DealDetailedOutput.
     */
    #[Route('/{id}', name: 'api_crm_deal_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $deal = $this->findDealForCompany($company, $id);
        if (!$deal) {
            return $this->json(['error' => 'Deal not found'], Response::HTTP_NOT_FOUND);
        }

        $history = $this->em->getRepository(CrmStageHistory::class)->findBy(
            ['deal' => $deal],
            ['changedAt' => 'ASC', 'id' => 'ASC'],
        );

        return $this->json($this->formatDealDetailed($deal, $history));
    }

    /**
     * Обновляет свойства сделки.
     *
     * Входной контракт (JSON): DealUpdateInput.
     * Выходной контракт: DealOutput.
     */
    #[Route('/{id}', name: 'api_crm_deal_update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $deal = $this->findDealForCompany($company, $id);
        if (!$deal) {
            return $this->json(['error' => 'Deal not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->getPayload($request);
        $hasChanges = false;
        $now = new DateTimeImmutable();

        if (array_key_exists('title', $payload)) {
            $titleCandidate = trim((string) $payload['title']);
            if ($titleCandidate === '') {
                return $this->json(['error' => 'Title cannot be blank'], Response::HTTP_BAD_REQUEST);
            }
            if (mb_strlen($titleCandidate) > 160) {
                return $this->json(['error' => 'Title must be at most 160 characters'], Response::HTTP_BAD_REQUEST);
            }
            $deal->setTitle($titleCandidate);
            $hasChanges = true;
        }

        if (array_key_exists('amount', $payload)) {
            $amountValue = $payload['amount'];
            if ($amountValue === null || $amountValue === '') {
                $deal->setAmount(null);
                $hasChanges = true;
            } else {
                $normalized = $this->normalizeAmount($amountValue);
                if ($normalized === null) {
                    return $this->json(['error' => 'amount must be a decimal with up to 12 digits and 2 decimals'], Response::HTTP_BAD_REQUEST);
                }
                $deal->setAmount($normalized);
                $hasChanges = true;
            }
        }

        if (array_key_exists('currency', $payload)) {
            $currencyCandidate = strtoupper(trim((string) $payload['currency']));
            if ($currencyCandidate === '') {
                return $this->json(['error' => 'Currency cannot be blank'], Response::HTTP_BAD_REQUEST);
            }
            if (mb_strlen($currencyCandidate) !== 3) {
                return $this->json(['error' => 'Currency must consist of 3 letters'], Response::HTTP_BAD_REQUEST);
            }
            $deal->setCurrency($currencyCandidate);
            $hasChanges = true;
        }

        if (array_key_exists('clientId', $payload)) {
            $clientId = $payload['clientId'];
            if ($clientId === null || $clientId === '') {
                $deal->setClient(null);
                $hasChanges = true;
            } else {
                if (!is_string($clientId)) {
                    return $this->json(['error' => 'clientId must be a string'], Response::HTTP_BAD_REQUEST);
                }
                $client = $this->findClientForCompany($company, $clientId);
                if (!$client) {
                    return $this->json(['error' => 'Client not found'], Response::HTTP_NOT_FOUND);
                }
                $deal->setClient($client);
                $hasChanges = true;
            }
        }

        if (array_key_exists('ownerId', $payload)) {
            $ownerId = $payload['ownerId'];
            if ($ownerId === null || $ownerId === '') {
                $deal->setOwner(null);
                $hasChanges = true;
            } else {
                if (!is_string($ownerId)) {
                    return $this->json(['error' => 'ownerId must be a string'], Response::HTTP_BAD_REQUEST);
                }
                $owner = $this->findCompanyUser($company, $ownerId);
                if (!$owner) {
                    return $this->json(['error' => 'Owner not found'], Response::HTTP_NOT_FOUND);
                }
                $deal->setOwner($owner);
                $hasChanges = true;
            }
        }

        if (array_key_exists('source', $payload)) {
            $sourceCandidate = trim((string) $payload['source']);
            if ($sourceCandidate === '') {
                $deal->setSource(null);
                $hasChanges = true;
            } else {
                if (mb_strlen($sourceCandidate) > 40) {
                    return $this->json(['error' => 'Source must be at most 40 characters'], Response::HTTP_BAD_REQUEST);
                }
                $deal->setSource($sourceCandidate);
                $hasChanges = true;
            }
        }

        if (array_key_exists('note', $payload)) {
            $noteCandidate = $payload['note'];
            if ($noteCandidate === null) {
                $deal->setNote(null);
            } else {
                $deal->setNote((string) $noteCandidate);
            }
            $hasChanges = true;
        }

        if (array_key_exists('lossReason', $payload)) {
            $lossCandidate = $payload['lossReason'];
            if ($lossCandidate === null || trim((string) $lossCandidate) === '') {
                $deal->setLossReason(null);
            } else {
                $lossReason = trim((string) $lossCandidate);
                if (mb_strlen($lossReason) > 120) {
                    return $this->json(['error' => 'Loss reason must be at most 120 characters'], Response::HTTP_BAD_REQUEST);
                }
                $deal->setLossReason($lossReason);
            }
            $hasChanges = true;
        }

        if (array_key_exists('meta', $payload)) {
            $metaValue = $payload['meta'];
            if ($metaValue === null) {
                $deal->setMeta([]);
            } elseif (!is_array($metaValue)) {
                return $this->json(['error' => 'meta must be an object'], Response::HTTP_BAD_REQUEST);
            } else {
                $deal->setMeta($metaValue);
            }
            $hasChanges = true;
        }

        if (array_key_exists('isClosed', $payload)) {
            $isClosedValue = $this->toBoolean($payload['isClosed']);
            if ($isClosedValue === null) {
                return $this->json(['error' => 'isClosed must be boolean'], Response::HTTP_BAD_REQUEST);
            }
            $deal->setIsClosed($isClosedValue);
            if ($isClosedValue && $deal->getClosedAt() === null) {
                $deal->setClosedAt($now);
            }
            if (!$isClosedValue) {
                $deal->setClosedAt(null);
            }
            $hasChanges = true;
        }

        if (array_key_exists('openedAt', $payload)) {
            $openedAtValue = $payload['openedAt'];
            if ($openedAtValue === null || $openedAtValue === '') {
                return $this->json(['error' => 'openedAt must be an ISO 8601 string'], Response::HTTP_BAD_REQUEST);
            }
            if (!is_string($openedAtValue)) {
                return $this->json(['error' => 'openedAt must be an ISO 8601 string'], Response::HTTP_BAD_REQUEST);
            }
            $openedAt = $this->parseDateTime($openedAtValue);
            if (!$openedAt) {
                return $this->json(['error' => 'openedAt must be an ISO 8601 string'], Response::HTTP_BAD_REQUEST);
            }
            $deal->setOpenedAt($openedAt);
            $hasChanges = true;
        }

        if (array_key_exists('closedAt', $payload)) {
            $closedAtValue = $payload['closedAt'];
            if ($closedAtValue === null || $closedAtValue === '') {
                $deal->setClosedAt(null);
                $hasChanges = true;
            } else {
                if (!is_string($closedAtValue)) {
                    return $this->json(['error' => 'closedAt must be an ISO 8601 string'], Response::HTTP_BAD_REQUEST);
                }
                $closedAt = $this->parseDateTime($closedAtValue);
                if (!$closedAt) {
                    return $this->json(['error' => 'closedAt must be an ISO 8601 string'], Response::HTTP_BAD_REQUEST);
                }
                $deal->setClosedAt($closedAt);
                $deal->setIsClosed(true);
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $deal->setUpdatedAt($now);
            $this->em->flush();

            $this->logger->info('crm.deal_updated', [
                'dealId' => $deal->getId(),
            ]);
        }

        return $this->json($this->formatDeal($deal));
    }

    /**
     * Переводит сделку в другой этап воронки.
     *
     * Входной контракт (JSON): DealMoveInput.
     * Выходной контракт: DealDetailedOutput.
     */
    #[Route('/{id}/move', name: 'api_crm_deal_move', methods: ['POST'])]
    public function move(string $id, Request $request): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $deal = $this->findDealForCompany($company, $id);
        if (!$deal) {
            return $this->json(['error' => 'Deal not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->getPayload($request);
        $toStageId = isset($payload['toStageId']) ? trim((string) $payload['toStageId']) : '';
        if ($toStageId === '') {
            return $this->json(['error' => 'toStageId is required'], Response::HTTP_BAD_REQUEST);
        }

        $stage = $this->findStageForCompany($company, $toStageId);
        if (!$stage) {
            return $this->json(['error' => 'Stage not found'], Response::HTTP_NOT_FOUND);
        }

        $comment = null;
        if (array_key_exists('comment', $payload)) {
            $commentCandidate = $payload['comment'];
            if ($commentCandidate === null || trim((string) $commentCandidate) === '') {
                $comment = null;
            } else {
                $comment = trim((string) $commentCandidate);
                if (mb_strlen($comment) > 240) {
                    return $this->json(['error' => 'Comment must be at most 240 characters'], Response::HTTP_BAD_REQUEST);
                }
            }
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new LogicException('Authenticated user expected.');
        }

        try {
            $this->dealMover->move($deal, $stage, $user, $comment);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $history = $this->em->getRepository(CrmStageHistory::class)->findBy(
            ['deal' => $deal],
            ['changedAt' => 'ASC', 'id' => 'ASC'],
        );

        return $this->json($this->formatDealDetailed($deal, $history));
    }

    /**
     * Удаляет сделку.
     *
     * Выходной контракт: HTTP 204 без тела при успехе.
     */
    #[Route('/{id}', name: 'api_crm_deal_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $company = $this->companyContext->getCompany();
        if (!$company) {
            return $this->json(['error' => 'No active company'], Response::HTTP_FORBIDDEN);
        }

        $deal = $this->findDealForCompany($company, $id);
        if (!$deal) {
            return $this->json(['error' => 'Deal not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($deal);
        $this->em->flush();

        $this->logger->info('crm.deal_deleted', [
            'dealId' => $id,
        ]);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<int, CrmStageHistory> $history
     */
    private function formatDealDetailed(CrmDeal $deal, array $history): array
    {
        $data = $this->formatDeal($deal);
        $data['stageHistory'] = array_map(fn (CrmStageHistory $entry) => $this->formatStageHistory($entry), $history);

        return $data;
    }

    private function formatDeal(CrmDeal $deal): array
    {
        return [
            'id' => $deal->getId(),
            'title' => $deal->getTitle(),
            'amount' => $deal->getAmount(),
            'currency' => $deal->getCurrency(),
            'source' => $deal->getSource(),
            'note' => $deal->getNote(),
            'lossReason' => $deal->getLossReason(),
            'isClosed' => $deal->isClosed(),
            'openedAt' => $deal->getOpenedAt()->format(DATE_ATOM),
            'stageEnteredAt' => $deal->getStageEnteredAt()->format(DATE_ATOM),
            'closedAt' => $deal->getClosedAt()?->format(DATE_ATOM),
            'createdAt' => $deal->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $deal->getUpdatedAt()->format(DATE_ATOM),
            'pipeline' => $this->formatPipeline($deal->getPipeline()),
            'stage' => $this->formatStage($deal->getStage()),
            'owner' => $deal->getOwner() ? $this->formatUser($deal->getOwner()) : null,
            'client' => $deal->getClient() ? $this->formatClient($deal->getClient()) : null,
            'createdBy' => $this->formatUser($deal->getCreatedBy()),
            'meta' => $deal->getMeta(),
        ];
    }

    private function formatStageHistory(CrmStageHistory $entry): array
    {
        return [
            'id' => $entry->getId(),
            'fromStage' => $entry->getFromStage() ? $this->formatStage($entry->getFromStage()) : null,
            'toStage' => $this->formatStage($entry->getToStage()),
            'comment' => $entry->getComment(),
            'changedAt' => $entry->getChangedAt()->format(DATE_ATOM),
            'changedBy' => $this->formatUser($entry->getChangedBy()),
            'spentHours' => $entry->getSpentHours(),
        ];
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

    private function formatPipeline(CrmPipeline $pipeline): array
    {
        return [
            'id' => $pipeline->getId(),
            'name' => $pipeline->getName(),
            'slug' => $pipeline->getSlug(),
            'createdAt' => $pipeline->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $pipeline->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
        ];
    }

    private function formatClient(Client $client): array
    {
        $displayName = $client->getUsername();
        if ($displayName === null || $displayName === '') {
            $parts = array_filter([$client->getFirstName(), $client->getLastName()]);
            if ($parts !== []) {
                $displayName = trim(implode(' ', $parts));
            }
        }

        return [
            'id' => $client->getId(),
            'displayName' => $displayName,
            'channel' => $client->getChannel()?->value,
            'externalId' => $client->getExternalId(),
            'username' => $client->getUsername(),
            'firstName' => $client->getFirstName(),
            'lastName' => $client->getLastName(),
        ];
    }

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

    private function findDealForCompany(Company $company, string $id): ?CrmDeal
    {
        return $this->em->getRepository(CrmDeal::class)->findOneBy([
            'id' => $id,
            'company' => $company,
        ]);
    }

    private function findClientForCompany(Company $company, string $id): ?Client
    {
        return $this->em->getRepository(Client::class)->findOneBy([
            'id' => $id,
            'company' => $company,
        ]);
    }

    private function findCompanyUser(Company $company, string $id): ?User
    {
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return null;
        }

        $membership = $this->em->getRepository(UserCompany::class)->findOneBy([
            'user' => $user,
            'company' => $company,
        ]);

        if (!$membership) {
            return null;
        }

        return $user;
    }

    private function findStartStage(CrmPipeline $pipeline): ?CrmStage
    {
        $stage = $this->em->getRepository(CrmStage::class)->findOneBy([
            'pipeline' => $pipeline,
            'isStart' => true,
        ]);

        if ($stage) {
            return $stage;
        }

        return $this->em->getRepository(CrmStage::class)->findOneBy([
            'pipeline' => $pipeline,
        ], [
            'position' => 'ASC',
        ]);
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

    private function normalizeAmount(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = str_replace(',', '.', trim($value));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $normalized) !== 1) {
            return null;
        }

        $sign = '';
        if (str_starts_with($normalized, '-')) {
            $sign = '-';
            $normalized = substr($normalized, 1);
        }

        $parts = explode('.', $normalized, 2);
        $integerPart = $parts[0];
        $decimalPart = $parts[1] ?? '';

        $integerPart = ltrim($integerPart, '0');
        if ($integerPart === '') {
            $integerPart = '0';
        }

        if (strlen($integerPart) > 12) {
            return null;
        }

        if ($decimalPart !== '') {
            if (strlen($decimalPart) > 2) {
                return null;
            }
            $decimalPart = str_pad($decimalPart, 2, '0');
        } else {
            $decimalPart = '00';
        }

        if ($sign === '-' && $integerPart === '0' && $decimalPart === '00') {
            $sign = '';
        }

        return $sign.$integerPart.'.'.$decimalPart;
    }

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $trimmed);
        if ($date === false) {
            return null;
        }

        return $date;
    }
}
