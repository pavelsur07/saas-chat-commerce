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

    public function findActiveAllowingOriginHost(string $host): ?WebChatSite
    {
        if (!$this->isStorageReady()) {
            return null;
        }

        $normalizedHost = strtolower($host);
        if ($normalizedHost === '') {
            return null;
        }

        $query = $this->createQueryBuilder('site')
            ->andWhere('site.isActive = true')
            ->getQuery();

        foreach ($query->toIterable() as $site) {
            if (!$site instanceof WebChatSite) {
                continue;
            }

            foreach ($site->getAllowedOrigins() as $origin) {
                $allowedHost = $this->extractHost($origin);
                if ($allowedHost === null || $allowedHost === '') {
                    continue;
                }

                if ($normalizedHost === $allowedHost || str_ends_with($normalizedHost, '.' . $allowedHost)) {
                    return $site;
                }
            }
        }

        return null;
    }

    private function extractHost(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = $trimmed;
        if (!str_contains($normalized, '://')) {
            $normalized = 'https://' . ltrim($normalized, '/');
        }

        $host = parse_url($normalized, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return strtolower($host);
        }

        return null;
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
