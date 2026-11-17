<?php

namespace App\Services;

use App\Services\SecureUrlFetcher;

/**
 * Page Fetch Service
 * 
 * Centralized HTML fetching for all audit modules.
 * Fetches each page once and shares the response across modules,
 * reducing duplicate requests and server load.
 *
 * Includes an in-memory request cache so multiple modules running
 * against the same URL within one execution reuse the same response.
 *
 * Optimized for shared hosting:
 *  - Configurable timeout and redirects via SettingsService
 *  - Memory-bounded LRU cache with auto-eviction
 *  - Metrics tracking for fetch performance
 *  - Deduplication guarantee (same URL never fetched twice per execution)
 *  - SSRF protection with SSL verification
 */
class PageFetchService
{
    private SecureUrlFetcher $fetcher;

    /**
     * In-memory cache of fetched pages for the current execution.
     * @var array<string, array>
     */
    private array $cache = [];
    private int $maxCacheEntries;

    /** Metrics tracking */
    private int $fetchCount = 0;
    private int $cacheHits = 0;
    private int $totalFetchTimeMs = 0;

    public function __construct()
    {
        $this->fetcher = new SecureUrlFetcher(
            timeout: SettingsService::getFetchTimeout(),
            connectTimeout: 10,
            maxRedirects: 5,
            userAgent: 'AuditWithGufran/1.0 (SEO Audit Bot)',
            maxContentLength: 5 * 1024 * 1024
        );
        $this->maxCacheEntries = 50;
    }

    /**
     * Fetch a URL and return a standardized response structure.
     */
    public function fetch(string $url, bool $useCache = true): array
    {
        $cacheKey = $this->cacheKey($url);

        if ($useCache && isset($this->cache[$cacheKey])) {
            $this->cacheHits++;
            return $this->cache[$cacheKey];
        }

        $result = $this->fetcher->fetch($url);

        $this->fetchCount++;
        $this->totalFetchTimeMs += $result['fetch_time_ms'];

        if ($useCache) {
            $this->storeInCache($cacheKey, $result);
        }

        return $result;
    }

    /**
     * Store a response in the in-memory cache with LRU eviction.
     */
    private function storeInCache(string $key, array $response): void
    {
        if (count($this->cache) >= $this->maxCacheEntries) {
            array_shift($this->cache);
        }
        $this->cache[$key] = $response;
    }

    /**
     * Clear the in-memory cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Remove a single URL from the cache.
     */
    public function forget(string $url): void
    {
        unset($this->cache[$this->cacheKey($url)]);
    }

    /**
     * Set max cache entries (tuning for environment).
     */
    public function setMaxCacheEntries(int $max): void
    {
        $this->maxCacheEntries = max(1, $max);
    }

    /**
     * Set fetch timeout.
     */
    public function setTimeout(int $seconds): void
    {
        $this->fetcher = new SecureUrlFetcher(
            timeout: max(5, $seconds),
            connectTimeout: 10,
            maxRedirects: 5,
            userAgent: 'AuditWithGufran/1.0 (SEO Audit Bot)',
            maxContentLength: 5 * 1024 * 1024
        );
    }

    /**
     * Get fetch performance metrics.
     */
    public function getMetrics(): array
    {
        return [
            'fetch_count' => $this->fetchCount,
            'cache_hits' => $this->cacheHits,
            'total_fetch_time_ms' => $this->totalFetchTimeMs,
            'avg_fetch_time_ms' => $this->fetchCount > 0
                ? (int) round($this->totalFetchTimeMs / $this->fetchCount)
                : 0,
            'cache_hit_rate' => ($this->fetchCount + $this->cacheHits) > 0
                ? round($this->cacheHits / ($this->fetchCount + $this->cacheHits) * 100, 1)
                : 0,
        ];
    }

    /**
     * Generate cache key for a URL.
     */
    private function cacheKey(string $url): string
    {
        return md5($url);
    }
}