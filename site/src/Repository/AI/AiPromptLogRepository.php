<?php

declare(strict_types=1);

namespace App\Repository\AI;

use App\Entity\AI\AiPromptLog;
use App\Entity\Company\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final class AiPromptLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiPromptLog::class);
    }

    public function findByIdForCompany(string $id, Company $company): ?AiPromptLog
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.id = :id')
            ->andWhere('l.company = :c')
            ->setParameter('id', $id)
            ->setParameter('c', $company)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function qbBaseForCompany(Company $company): QueryBuilder
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.company = :c')
            ->setParameter('c', $company);
    }
}
