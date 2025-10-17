<?php

namespace App\Repository\WebChat;

use App\Entity\WebChat\WebChatSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;

class WebChatSiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebChatSite::class);
    }

    public function siteKeyExists(string $siteKey): bool
    {
        if (!$this->isStorageReady()) {
            return false;
        }

        return (bool) $this->createQueryBuilder('site')
            ->select('1')
            ->andWhere('site.siteKey = :key')
            ->setParameter('key', $siteKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveBySiteKey(string $siteKey): ?WebChatSite
    {
        if (!$this->isStorageReady()) {
            return null;
        }

        try {
            return $this->createQueryBuilder('site')
                ->andWhere('site.siteKey = :key')
                ->andWhere('site.isActive = true')
                ->setParameter('key', $siteKey)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException $exception) {
            // Уникальный индекс гарантирует, что такое не должно происходить, но на всякий случай
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
