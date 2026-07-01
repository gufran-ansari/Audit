<?php

namespace App\Middleware;

/**
 * Performance Middleware
 * 
 * Applies performance optimizations to requests:
 * - Output buffering with gzip compression
 * - Cache headers for API responses
 * - Memory limit management
 * - Request timing
 */
class PerformanceMiddleware
{
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Apply performance settings for API responses.
     */
    public function handleApi(): void
    {
        // Set memory limit for large operations
        if ((int) ini_get('memory_limit') < 128) {
            @ini_set('memory_limit', '128M');
        }

        // Enable output compression if available
        if (function_exists('ob_gzhandler') && !ini_get('zlib.output_compression')) {
            ob_start('ob_gzhandler');
        }
    }

    /**
     * Apply performance settings for report pages.
     */
    public function handleReport(): void
    {
        // Allow more memory for report generation
        @ini_set('memory_limit', '256M');

        // Set reasonable time limit
        @set_time_limit(120);
    }

    /**
     * Add cache control headers for cacheable responses.
     */
    public static function setCacheHeaders(int $maxAge = 60, bool $isPublic = true): void
    {
        $visibility = $isPublic ? 'public' : 'private';
        header("Cache-Control: {$visibility}, max-age={$maxAge}");
        header('Vary: Accept-Encoding');
    }

    /**
     * Add no-cache headers for dynamic API responses.
     */
    public static function setNoCacheHeaders(): void
    {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Get elapsed time since middleware init.
     */
    public function getElapsedMs(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    /**
     * Get current memory usage (formatted).
     */
    public static function getMemoryUsage(): string
    {
        $bytes = memory_get_peak_usage(true);
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
