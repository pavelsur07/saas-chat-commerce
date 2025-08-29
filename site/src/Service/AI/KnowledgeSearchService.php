<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Entity\Company\Company;
use App\ReadModel\AI\KnowledgeHit;
use App\Repository\AI\CompanyKnowledgeRepository;
use Psr\Cache\CacheItemPoolInterface;

final class KnowledgeSearchService
{
    public function __construct(
        private CompanyKnowledgeRepository $repo,
        private CacheItemPoolInterface $cache,
        private int $ttlSeconds = 600,
        private int $topN = 5,
    ) {
    }

    /** @return KnowledgeHit[] */
    public function search(Company $company, string $query, ?int $limit = null): array
    {
        $limit ??= $this->topN;

        // ✅ безопасный ключ
        $hash = sha1($company->getId().'|'.mb_strtolower($query).'|'.$limit);
        $key = 'ck.search.'.$hash;

        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            /* @var KnowledgeHit[] */
            return $item->get();
        }

        $hits = $this->repo->findTopByQuery($company, $query, $limit);

        $item->set($hits)->expiresAfter($this->ttlSeconds);
        $this->cache->save($item);

        return $hits;
    }

    public function invalidateCompanyCache(Company $company): void
    {
        // MVP: полная очистка
        $this->cache->clear();
    }
}
