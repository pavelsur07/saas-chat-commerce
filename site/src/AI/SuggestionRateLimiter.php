<?php

namespace App\AI;

use App\Account\Entity\Company;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SuggestionRateLimiter
{
    public function __construct(
        #[Autowire(service: 'cache.app')] private readonly CacheItemPoolInterface $cache,
        #[Autowire('%ai.suggestions.rate_limit_seconds%')] private readonly int $ttlSeconds,
    ) {
    }

    /**
     * Возвращает true, если можно делать запрос (и ставит "флажок" на ttlSeconds).
     * Возвращает false, если недавно уже был запрос (в окне).
     */
    public function acquire(Company $company, string $clientId): bool
    {
        $key = $this->key($company->getId(), $clientId);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return false; // слишком часто
        }

        // ставим флажок на TTL
        $item->set(1);
        $item->expiresAfter($this->ttlSeconds);
        $this->cache->save($item);

        return true;
    }

    private function key(string $companyId, string $clientId): string
    {
        // короткий и безопасный ключ (без двоеточий от Redis не страдает)
        return 'ai_suggest_'.md5($companyId.'_'.$clientId);
    }
}
