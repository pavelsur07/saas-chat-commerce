<?php

// src/Repository/AI/CompanyKnowledgeRepository.php

namespace App\Repository\AI;

use App\Entity\AI\CompanyKnowledge;
use App\Entity\Company\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CompanyKnowledgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, CompanyKnowledge::class);
    }

    /**
     * Простой поиск по заголовку/ответу; limit = 5.
     */
    public function findTopByQuery(Company $company, string $query, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('k')
            ->andWhere('k.company = :c')->setParameter('c', $company);

        if ('' !== trim($query)) {
            $qb->andWhere('(LOWER(k.question) LIKE :q OR LOWER(k.answer) LIKE :q)')
                ->setParameter('q', '%'.mb_strtolower($query).'%');
        }

        return $qb->orderBy('k.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
