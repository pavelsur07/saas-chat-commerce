<?php

declare(strict_types=1);

namespace App\Repository\AI;

use App\Entity\AI\AiScenario;
use App\Entity\Company\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final class AiScenarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiScenario::class);
    }

    public function findByIdForCompany(string $id, Company $company): ?AiScenario
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.id = :id')
            ->andWhere('s.company = :c')
            ->setParameter('id', $id)
            ->setParameter('c', $company)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function qbBaseForCompany(Company $company): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.company = :c')
            ->setParameter('c', $company);
    }
}
