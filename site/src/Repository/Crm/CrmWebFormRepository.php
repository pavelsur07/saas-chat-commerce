<?php

namespace App\Repository\Crm;

use App\Entity\Company\Company;
use App\Entity\Crm\CrmWebForm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;

class CrmWebFormRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrmWebForm::class);
    }

    /**
     * @return CrmWebForm[]
     */
    public function findForCompany(Company $company): array
    {
        if (!$this->isStorageReady()) {
            return [];
        }

        return $this->createQueryBuilder('form')
            ->andWhere('form.company = :company')
            ->setParameter('company', $company)
            ->orderBy('form.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByPublicKey(string $publicKey): ?CrmWebForm
    {
        if (!$this->isStorageReady()) {
            return null;
        }

        try {
            return $this->createQueryBuilder('form')
                ->andWhere('form.publicKey = :publicKey')
                ->andWhere('form.isActive = true')
                ->setParameter('publicKey', $publicKey)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    public function isStorageReady(): bool
    {
        try {
            $connection = $this->getEntityManager()->getConnection();
            $schemaManager = $connection->createSchemaManager();

            return $schemaManager->tablesExist([$this->getClassMetadata()->getTableName()]);
        } catch (\Throwable) {
            return false;
        }
    }
}
