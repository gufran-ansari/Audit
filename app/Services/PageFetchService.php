<?php

namespace App\Services;

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
 */
class PageFetchService
{
    private int $timeout;
    private int $maxRedirects;
    private string $userAgent;

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
        $this->timeout = SettingsService::getFetchTimeout();
        $this->maxRedirects = 5;
        $this->userAgent = 'AuditWithGufran/1.0 (SEO Audit Bot)';
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

        $startTime = microtime(true);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->maxRedirects);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        // Limit download size to 5MB to prevent memory issues on shared hosting
        curl_setopt($ch, CURLOPT_MAXFILESIZE, 5 * 1024 * 1024);

        $content = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        $fetchTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        $this->fetchCount++;
        $this->totalFetchTimeMs += $fetchTimeMs;

        if ($content === false || $httpCode < 200 || $httpCode >= 400) {
            $errorMessage = $error !== '' ? $error : "HTTP {$httpCode}";

            $response = [
                'success' => false,
                'html' => null,
                'http_code' => $httpCode,
                'final_url' => $finalUrl ?: $url,
                'content_type' => $contentType,
                'fetch_time_ms' => $fetchTimeMs,
                'error' => "Failed to fetch URL: {$errorMessage}"
            ];
        } else {
            $response = [
                'success' => true,
                'html' => $content,
                'http_code' => $httpCode,
                'final_url' => $finalUrl ?: $url,
                'content_type' => $contentType,
                'fetch_time_ms' => $fetchTimeMs,
                'error' => null
            ];
        }

        if ($useCache) {
            $this->storeInCache($cacheKey, $response);
        }

        return $response;
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
        $this->timeout = max(5, $seconds);
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
