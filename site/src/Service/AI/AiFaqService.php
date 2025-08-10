<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Entity\AI\AiFaq;
use App\Entity\AI\Enum\AiFaqSource;
use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Repository\AI\AiFaqRepository;
use App\Service\Company\CompanyContextService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Ramsey\Uuid\Nonstandard\Uuid;

final class AiFaqService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AiFaqRepository $repo,
        private readonly CompanyContextService $companyCtx,
    ) {
    }

    /**
     * Создать FAQ.
     *
     * @param array{
     *   question:string,
     *   answer:string,
     *   language?:string,
     *   tags?:string[],
     *   source?: 'ai'|'manual',
     *   isActive?:bool
     * } $data
     */
    public function create(array $data, ?User $actor = null): AiFaq
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        $id = Uuid::uuid4()->toString();

        $faq = new AiFaq(
            id: $id,
            company: $company,
            question: trim($data['question']),
            answer: trim($data['answer'])
        );

        if (!empty($data['language'])) {
            $faq->setLanguage($data['language']);
        }

        $faq->setSource(($data['source'] ?? 'manual') === 'ai' ? AiFaqSource::AI : AiFaqSource::MANUAL);

        if (isset($data['tags'])) {
            $faq->setTags(array_values(array_unique(array_map('strval', $data['tags']))));
        }

        if (array_key_exists('isActive', $data)) {
            $faq->setIsActive((bool) $data['isActive']);
        }

        if ($actor) {
            $faq->setCreatedBy($actor);
            $faq->setUpdatedBy($actor);
        }

        $this->em->persist($faq);
        $this->em->flush();

        return $faq;
    }

    /**
     * Обновить FAQ частично.
     * Разрешены: question, answer, language, tags, isActive, source.
     */
    public function update(string $id, array $patch, ?User $actor = null): AiFaq
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        $faq = $this->repo->findByIdForCompany($id, $company)
            ?? throw new \RuntimeException('FAQ not found or belongs to another company');

        if (array_key_exists('question', $patch)) {
            $faq->setQuestion(trim((string) $patch['question']));
        }
        if (array_key_exists('answer', $patch)) {
            $faq->setAnswer(trim((string) $patch['answer']));
        }
        if (array_key_exists('language', $patch)) {
            $faq->setLanguage((string) $patch['language']);
        }
        if (array_key_exists('tags', $patch)) {
            $tags = $patch['tags'] ?? [];
            $faq->setTags(array_values(array_unique(array_map('strval', $tags))));
        }
        if (array_key_exists('isActive', $patch)) {
            $faq->setIsActive((bool) $patch['isActive']);
        }
        if (array_key_exists('source', $patch)) {
            $faq->setSource(($patch['source'] ?? 'manual') === 'ai' ? AiFaqSource::AI : AiFaqSource::MANUAL);
        }

        if ($actor) {
            $faq->setUpdatedBy($actor);
        }
        $faq->touchUpdated();

        $this->em->flush();

        return $faq;
    }

    public function delete(string $id): void
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        $faq = $this->repo->findByIdForCompany($id, $company)
            ?? throw new \RuntimeException('FAQ not found or belongs to another company');

        $this->em->remove($faq);
        $this->em->flush();
    }

    public function get(string $id): AiFaq
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        return $this->repo->findByIdForCompany($id, $company)
            ?? throw new \RuntimeException('FAQ not found or belongs to another company');
    }

    /**
     * Список/поиск: поддерживает фильтры и пагинацию.
     *
     * @param array{
     *   q?:string,           // полнотекстовый (ILIKE) по question/answer
     *   tags?:string[],      // JSONB @>
     *   language?:string,
     *   isActive?:bool
     * } $filters
     *
     * @return array{items:AiFaq[], total:int}
     */
    public function search(array $filters = [], int $page = 1, int $limit = 20): array
    {
        /** @var Company $company */
        $company = $this->companyCtx->getCompany();

        $qb = $this->repo->qbBaseForCompany($company);

        // фильтры BTREE
        if (isset($filters['language']) && '' !== $filters['language']) {
            $qb->andWhere('f.language = :lang')->setParameter('lang', $filters['language']);
        }
        if (isset($filters['isActive'])) {
            $qb->andWhere('f.isActive = :active')->setParameter('active', (bool) $filters['isActive']);
        }

        // фильтр по тегам (GIN, JSONB @>)
        if (!empty($filters['tags'])) {
            // ожидаем массив строк → JSONB-массив
            $json = json_encode(array_values(array_unique(array_map('strval', $filters['tags']))));
            // Важно: приводим к jsonb, чтобы использовать индекс
            $qb->andWhere('f.tags @> CAST(:tags AS jsonb)')->setParameter('tags', $json);
        }

        // полнотекстовый/подстрочный поиск по trigram (ILIKE)
        if (!empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $qb->andWhere('(f.question ILIKE :q OR f.answer ILIKE :q)')
                ->setParameter('q', $term);
        }

        $qb->orderBy('f.createdAt', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit);

        // пагинация
        $paginator = new Paginator($qb);
        $items = iterator_to_array($paginator->getIterator());
        $total = count($paginator);

        return ['items' => $items, 'total' => $total];
    }
}
