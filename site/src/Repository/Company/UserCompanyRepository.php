<?php

namespace App\Repository\Company;

use App\Entity\Company\Company;
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

    /**
     * @return list<UserCompany>
     */
    public function findActiveOwnerLinksByUser(User $user): array
    {
        return $this->createQueryBuilder('uc')
            ->addSelect('company')
            ->leftJoin('uc.company', 'company')
            ->andWhere('uc.user = :user')
            ->andWhere('uc.status = :status')
            ->andWhere('uc.role = :role')
            ->orderBy('company.name', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('status', UserCompany::STATUS_ACTIVE)
            ->setParameter('role', UserCompany::ROLE_OWNER)
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndCompany(User $user, Company $company): ?UserCompany
    {
        return $this->createQueryBuilder('uc')
            ->andWhere('uc.user = :user')
            ->andWhere('uc.company = :company')
            ->setParameter('user', $user)
            ->setParameter('company', $company)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByUserAndCompanyId(User $user, string $companyId): ?UserCompany
    {
        return $this->createQueryBuilder('uc')
            ->addSelect('company')
            ->leftJoin('uc.company', 'company')
            ->andWhere('uc.user = :user')
            ->andWhere('company.id = :companyId')
            ->setParameter('user', $user)
            ->setParameter('companyId', $companyId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function clearDefaultForUserExcept(User $user, UserCompany $keep): void
    {
        $this->getEntityManager()
            ->createQuery('UPDATE App\\Entity\\Company\\UserCompany uc SET uc.isDefault = :default WHERE uc.user = :user AND uc != :keep')
            ->setParameter('default', false)
            ->setParameter('user', $user)
            ->setParameter('keep', $keep)
            ->execute();
    }

    public function setDefault(UserCompany $link): void
    {
        $this->clearDefaultForUserExcept($link->getUser(), $link);
        $link->setIsDefault(true);
    }

}
