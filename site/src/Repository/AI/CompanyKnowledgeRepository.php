<?php

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
     * Топ-N знаний по компании: простая токенизация входной фразы и ILIKE по 1–3 «осмысленным» словам.
     */
    public function findTopByQuery(Company $company, string $query, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('k')
            ->andWhere('k.company = :c')->setParameter('c', $company);

        $q = trim(mb_strtolower($query));
        if ('' !== $q) {
            // выдернем до трёх ключевых слов длиной >= 4
            $raw = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
            $words = array_values(array_filter($raw, fn (string $w) => mb_strlen($w) >= 4));
            $words = array_slice($words, 0, 3);

            if (!empty($words)) {
                $or = $qb->expr()->orX();
                foreach ($words as $i => $w) {
                    $param = 'q'.$i;
                    $or->add($qb->expr()->like('LOWER(k.title)', ':'.$param));
                    $or->add($qb->expr()->like('LOWER(k.content)', ':'.$param));
                    $qb->setParameter($param, '%'.$w.'%');
                }
                $qb->andWhere($or);
            }
        }

        return $qb->orderBy('k.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
