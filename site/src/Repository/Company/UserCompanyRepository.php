<?php

namespace App\Repository\Company;

use App\Entity\Company\User;
use App\Entity\Company\UserCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserCompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserCompany::class);
    }

    public function findOneActiveByUser(User $user): ?UserCompany
    {
        return $this->createQueryBuilder('uc')
            ->addSelect('company')
            ->leftJoin('uc.company', 'company')
            ->andWhere('uc.user = :user')
            ->andWhere('uc.status = :status')
            ->orderBy('uc.createdAt', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('status', UserCompany::STATUS_ACTIVE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneActiveByUserAndCompanyId(User $user, string $companyId): ?UserCompany
    {
        return $this->createQueryBuilder('uc')
            ->addSelect('company')
            ->leftJoin('uc.company', 'company')
            ->andWhere('uc.user = :user')
            ->andWhere('uc.status = :status')
            ->andWhere('company.id = :companyId')
            ->setParameter('user', $user)
            ->setParameter('status', UserCompany::STATUS_ACTIVE)
            ->setParameter('companyId', $companyId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<UserCompany>
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('uc')
            ->addSelect('company')
            ->leftJoin('uc.company', 'company')
            ->andWhere('uc.user = :user')
            ->andWhere('uc.status = :status')
            ->orderBy('company.name', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('status', UserCompany::STATUS_ACTIVE)
            ->getQuery()
            ->getResult();
    }

}
