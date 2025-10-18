<?php

namespace App\Service\RateLimiter;

use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class VisitorMessageRateLimiter
{
    private readonly CacheItemPoolInterface $cache;
    private readonly int $limit;
    private readonly int $intervalSeconds;
    private readonly string $cachePrefix;

    public function __construct(
        #[Autowire(service: 'cache.app')] CacheItemPoolInterface $cache,
        int $limit = 50,
        int $intervalSeconds = 60,
        string $cacheNamespace = 'webchat_messages'
    ) {
        $this->cache = $cache;
        $this->limit = max(1, $limit);
        $this->intervalSeconds = max(1, $intervalSeconds);
        $trimmedNamespace = trim($cacheNamespace) !== '' ? $cacheNamespace : 'rate_limiter';
        $this->cachePrefix = 'rl_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $trimmedNamespace) . '_';
    }

    public function consume(string $sessionId, int $tokens = 1): RateLimit
    {
        if ($tokens <= 0) {
            return RateLimit::accepted();
        }

        $now = time();
        $cacheKey = $this->cachePrefix . md5($sessionId);
        $item = $this->cache->getItem($cacheKey);
        $payload = $item->isHit() ? $item->get() : null;

        if (!is_array($payload) || !isset($payload['count'], $payload['window_start']) || !is_int($payload['count']) || !is_int($payload['window_start'])) {
            $payload = [
                'count' => 0,
                'window_start' => $now,
            ];
        }

        if ($now >= $payload['window_start'] + $this->intervalSeconds) {
            $payload = [
                'count' => 0,
                'window_start' => $now,
            ];
        }

        if ($payload['count'] + $tokens > $this->limit) {
            $retryTimestamp = $payload['window_start'] + $this->intervalSeconds;
            $item->set($payload);
            $item->expiresAfter(max(1, $retryTimestamp - $now));
            $this->cache->save($item);

            $retryAfter = DateTimeImmutable::createFromFormat('U', (string) $retryTimestamp) ?: new DateTimeImmutable('@' . $retryTimestamp);

            return RateLimit::rejected($retryAfter);
        }

        $payload['count'] += $tokens;
        $item->set($payload);
        $item->expiresAfter($this->intervalSeconds);
        $this->cache->save($item);

        return RateLimit::accepted();
    }
}
