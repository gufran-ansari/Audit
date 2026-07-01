<?php

namespace App\Services;

/**
 * Rate Limiter
 * 
 * File-based rate limiting for shared hosting (no Redis required).
 * Tracks attempts per IP + action key with configurable windows.
 * 
 * Usage:
 *   $limiter = new RateLimiter();
 *   if (!$limiter->attempt('login:' . $ip, 5, 900)) {
 *       // Too many attempts, block for 15 minutes
 *   }
 */
class RateLimiter
{
    private string $storageDir;

    public function __construct()
    {
        $this->storageDir = __DIR__ . '/../../storage/rate_limits';
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Record an attempt and check if limit is exceeded.
     * 
     * @param string $key Unique key (e.g., "login:192.168.1.1")
     * @param int $maxAttempts Max attempts allowed in the window
     * @param int $windowSeconds Time window in seconds
     * @return bool True if attempt is allowed, false if rate limited
     */
    public function attempt(string $key, int $maxAttempts = 5, int $windowSeconds = 900): bool
    {
        $file = $this->getFile($key);
        $data = $this->loadData($file);
        $now = time();

        // Remove expired entries
        $data['attempts'] = array_filter($data['attempts'] ?? [], function ($ts) use ($now, $windowSeconds) {
            return ($now - $ts) < $windowSeconds;
        });

        // Check if locked out
        if (isset($data['locked_until']) && $data['locked_until'] > $now) {
            return false;
        }

        // Check attempts count
        if (count($data['attempts']) >= $maxAttempts) {
            // Lock out for the remaining window time
            $data['locked_until'] = $now + $windowSeconds;
            $this->saveData($file, $data);
            return false;
        }

        // Record this attempt
        $data['attempts'][] = $now;
        $this->saveData($file, $data);

        return true;
    }

    /**
     * Check remaining attempts without recording one.
     */
    public function remaining(string $key, int $maxAttempts = 5, int $windowSeconds = 900): int
    {
        $file = $this->getFile($key);
        $data = $this->loadData($file);
        $now = time();

        // Check lockout
        if (isset($data['locked_until']) && $data['locked_until'] > $now) {
            return 0;
        }

        // Count valid attempts
        $valid = array_filter($data['attempts'] ?? [], function ($ts) use ($now, $windowSeconds) {
            return ($now - $ts) < $windowSeconds;
        });

        return max(0, $maxAttempts - count($valid));
    }

    /**
     * Get seconds until rate limit resets.
     */
    public function retryAfter(string $key, int $windowSeconds = 900): int
    {
        $file = $this->getFile($key);
        $data = $this->loadData($file);
        $now = time();

        if (isset($data['locked_until']) && $data['locked_until'] > $now) {
            return $data['locked_until'] - $now;
        }

        return 0;
    }

    /**
     * Clear rate limit for a key (e.g., after successful login).
     */
    public function clear(string $key): void
    {
        $file = $this->getFile($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Cleanup old rate limit files (call periodically).
     */
    public function cleanup(int $maxAge = 3600): int
    {
        $removed = 0;
        $files = glob($this->storageDir . '/*.limit');
        if (!$files) return 0;

        $now = time();
        foreach ($files as $file) {
            if (($now - filemtime($file)) > $maxAge) {
                @unlink($file);
                $removed++;
            }
        }
        return $removed;
    }

    private function getFile(string $key): string
    {
        return $this->storageDir . '/' . md5($key) . '.limit';
    }

    private function loadData(string $file): array
    {
        if (!file_exists($file)) {
            return ['attempts' => []];
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return ['attempts' => []];
        }

        $data = @json_decode($content, true);
        return is_array($data) ? $data : ['attempts' => []];
    }

    private function saveData(string $file, array $data): void
    {
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
