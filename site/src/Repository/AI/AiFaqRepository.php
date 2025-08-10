<?php

declare(strict_types=1);

namespace App\Repository\AI;

use App\Entity\AI\AiFaq;
use App\Entity\Company\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final class AiFaqRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiFaq::class);
    }

    public function findByIdForCompany(string $id, Company $company): ?AiFaq
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.id = :id')
            ->andWhere('f.company = :c')
            ->setParameter('id', $id)
            ->setParameter('c', $company)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Базовый QB с ограничением по компании.
     */
    public function qbBaseForCompany(Company $company): QueryBuilder
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.company = :c')
            ->setParameter('c', $company);
    }
}
