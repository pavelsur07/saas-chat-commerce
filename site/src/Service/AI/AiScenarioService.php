<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Entity\AI\AiScenario;
use App\Entity\AI\Enum\ScenarioStatus;
use App\Entity\Company\Company;
use App\Account\Entity\User;
use App\Repository\AI\AiScenarioRepository;
use App\Service\Company\CompanyContextService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Ramsey\Uuid\Uuid;

final class AiScenarioService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AiScenarioRepository $repo,
        private readonly CompanyContextService $companyCtx,
    ) {
    }

    /**
     * Создать новый сценарий.
     *
     * @param array{name:string, slug:string, graph:array, notes?:string} $data
     */
    public function create(array $data, ?User $actor = null): AiScenario
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        $id = Uuid::uuid4()->toString();

        // простая валидация графа (минимум: наличие ключей nodes/edges)
        $this->validateGraph($data['graph']);

        $scenario = new AiScenario(
            id: $id,
            company: $company,
            name: trim($data['name']),
            slug: trim($data['slug']),
            graph: $data['graph']
        );

        if (!empty($data['notes'])) {
            $scenario->setNotes($data['notes']);
        }

        if ($actor) {
            $scenario->setCreatedBy($actor);
            $scenario->setUpdatedBy($actor);
        }

        $this->em->persist($scenario);
        $this->em->flush();

        return $scenario;
    }

    /**
     * Обновить сценарий.
     * Разрешены: name, slug, graph, notes, status.
     */
    public function update(string $id, array $patch, ?User $actor = null): AiScenario
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        $scenario = $this->repo->findByIdForCompany($id, $company)
            ?? throw new \RuntimeException('Scenario not found or belongs to another company');

        if (isset($patch['name'])) {
            $scenario->setName(trim($patch['name']));
        }
        if (isset($patch['slug'])) {
            $scenario->setSlug(trim($patch['slug']));
        }
        if (isset($patch['graph'])) {
            $this->validateGraph($patch['graph']);
            $scenario->setGraph($patch['graph']);
        }
        if (isset($patch['notes'])) {
            $scenario->setNotes($patch['notes']);
        }
        if (isset($patch['status'])) {
            $scenario->setStatus(ScenarioStatus::from($patch['status']));
        }

        if ($actor) {
            $scenario->setUpdatedBy($actor);
        }
        $scenario->touchUpdated();

        $this->em->flush();

        return $scenario;
    }

    public function delete(string $id): void
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        $scenario = $this->repo->findByIdForCompany($id, $company)
            ?? throw new \RuntimeException('Scenario not found or belongs to another company');

        $this->em->remove($scenario);
        $this->em->flush();
    }

    public function get(string $id): AiScenario
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        return $this->repo->findByIdForCompany($id, $company)
            ?? throw new \RuntimeException('Scenario not found or belongs to another company');
    }

    /**
     * Публикация сценария.
     */
    public function publish(string $id, ?User $actor = null): AiScenario
    {
        $scenario = $this->get($id);

        $scenario->setStatus(ScenarioStatus::PUBLISHED);
        $scenario->setPublishedAt(new \DateTimeImmutable());

        if ($actor) {
            $scenario->setUpdatedBy($actor);
        }
        $scenario->touchUpdated();

        $this->em->flush();

        return $scenario;
    }

    /**
     * Клонировать сценарий → новая версия (+1).
     */
    public function cloneVersion(string $id, ?User $actor = null): AiScenario
    {
        $original = $this->get($id);

        $newId = Uuid::uuid4()->toString();
        $newVersion = $original->getVersion() + 1;

        $clone = new AiScenario(
            id: $newId,
            company: $original->getCompany(),
            name: $original->getName(),
            slug: $original->getSlug(),
            graph: $original->getGraph()
        );
        $clone->setVersion($newVersion);
        $clone->setStatus(ScenarioStatus::DRAFT);
        $clone->setNotes($original->getNotes());

        if ($actor) {
            $clone->setCreatedBy($actor);
            $clone->setUpdatedBy($actor);
        }

        $this->em->persist($clone);
        $this->em->flush();

        return $clone;
    }

    /**
     * Список/поиск сценариев.
     *
     * @param array{name?:string,status?:string} $filters
     *
     * @return array{items:AiScenario[], total:int}
     */
    public function search(array $filters = [], int $page = 1, int $limit = 20): array
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        $qb = $this->repo->qbBaseForCompany($company);

        if (!empty($filters['name'])) {
            $qb->andWhere('s.name ILIKE :n')->setParameter('n', '%'.$filters['name'].'%');
        }
        if (!empty($filters['status'])) {
            $qb->andWhere('s.status = :st')->setParameter('st', ScenarioStatus::from($filters['status']));
        }

        $qb->orderBy('s.createdAt', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit);

        $paginator = new Paginator($qb);
        $items = iterator_to_array($paginator->getIterator());
        $total = count($paginator);

        return ['items' => $items, 'total' => $total];
    }

    private function validateGraph(array $graph): void
    {
        if (!isset($graph['nodes']) || !isset($graph['edges'])) {
            throw new \InvalidArgumentException('Graph must have nodes and edges keys');
        }
    }
}
