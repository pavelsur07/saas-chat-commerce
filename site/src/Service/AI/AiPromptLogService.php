<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Entity\AI\AiPromptLog;
use App\Entity\AI\Enum\PromptStatus;
use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Repository\AI\AiPromptLogRepository;
use App\Service\Company\CompanyContextService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Ramsey\Uuid\Uuid;

final class AiPromptLogService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AiPromptLogRepository $repo,
        private readonly CompanyContextService $companyCtx,
    ) {
    }

    /**
     * Быстрая запись лога LLM.
     *
     * @param array{
     *   channel:string,
     *   model:string,
     *   prompt:string,
     *   response?:string|null,
     *   promptTokens?:int,
     *   completionTokens?:int,
     *   totalTokens?:int,
     *   latencyMs?:int,
     *   status?: 'ok'|'error'|'timeout'|'rate_limited',
     *   errorMessage?:string|null,
     *   costUsd?:string|null,
     *   metadata?:array|null
     * } $data
     */
    public function log(array $data, ?User $actor = null): AiPromptLog
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        $id = Uuid::uuid4()->toString();

        $log = new AiPromptLog(
            id: $id,
            company: $company,
            channel: (string) $data['channel'],
            model: (string) $data['model'],
            prompt: (string) $data['prompt'],
        );

        if ($actor) {
            $log->setUser($actor);
        }

        $log->setResponse($data['response'] ?? null);
        $log->setPromptTokens((int) ($data['promptTokens'] ?? 0));
        $log->setCompletionTokens((int) ($data['completionTokens'] ?? 0));
        $log->setTotalTokens((int) ($data['totalTokens'] ?? 0));
        $log->setLatencyMs((int) ($data['latencyMs'] ?? 0));
        $log->setStatus(isset($data['status']) ? PromptStatus::from($data['status']) : PromptStatus::OK);
        $log->setErrorMessage($data['errorMessage'] ?? null);
        $log->setCostUsd($data['costUsd'] ?? null);
        $log->setMetadata($data['metadata'] ?? null);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    /**
     * Ручное создание (для тестов/интерфейсов). Синоним log().
     */
    public function create(array $data, ?User $actor = null): AiPromptLog
    {
        return $this->log($data, $actor);
    }

    public function get(string $id): AiPromptLog
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        return $this->repo->findByIdForCompany($id, $company)
            ?? throw new \RuntimeException('Log not found or belongs to another company');
    }

    public function delete(string $id): void
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        $log = $this->repo->findByIdForCompany($id, $company)
            ?? throw new \RuntimeException('Log not found or belongs to another company');

        $this->em->remove($log);
        $this->em->flush();
    }

    /**
     * Поиск с фильтрами и пагинацией.
     *
     * @param array{
     *   model?:string,
     *   status?: 'ok'|'error'|'timeout'|'rate_limited',
     *   channel?:string,
     *   userId?:string,
     *   from?:string,      // ISO8601
     *   to?:string,        // ISO8601
     *   onlyErrors?:bool,
     *   feature?:string,   // metadata->>feature = '...'
     * } $filters
     *
     * @return array{items:AiPromptLog[], total:int}
     */
    public function search(array $filters = [], int $page = 1, int $limit = 20): array
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        $qb = $this->repo->qbBaseForCompany($company);

        if (!empty($filters['model'])) {
            $qb->andWhere('l.model = :m')->setParameter('m', $filters['model']);
        }
        if (!empty($filters['status'])) {
            $qb->andWhere('l.status = :s')->setParameter('s', PromptStatus::from($filters['status']));
        }
        if (!empty($filters['channel'])) {
            $qb->andWhere('l.channel = :ch')->setParameter('ch', $filters['channel']);
        }
        if (!empty($filters['userId'])) {
            $qb->andWhere('l.user = :u')->setParameter('u', $filters['userId']);
        }
        if (!empty($filters['onlyErrors'])) {
            $qb->andWhere('l.status <> :ok')->setParameter('ok', PromptStatus::OK);
        }

        // период дат
        if (!empty($filters['from'])) {
            $from = new \DateTimeImmutable($filters['from']);
            $qb->andWhere('l.createdAt >= :from')->setParameter('from', $from);
        }
        if (!empty($filters['to'])) {
            $to = new \DateTimeImmutable($filters['to']);
            $qb->andWhere('l.createdAt <= :to')->setParameter('to', $to);
        }

        // фильтр по feature в metadata (использует GIN при jsonb_path_ops + выражение оператора @>)
        if (!empty($filters['feature'])) {
            $json = json_encode(['feature' => (string) $filters['feature']]);
            $qb->andWhere('l.metadata @> CAST(:metaFeature AS jsonb)')
                ->setParameter('metaFeature', $json);
        }

        $qb->orderBy('l.createdAt', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit);

        $paginator = new Paginator($qb);
        $items = iterator_to_array($paginator->getIterator());
        $total = count($paginator);

        return ['items' => $items, 'total' => $total];
    }
}
