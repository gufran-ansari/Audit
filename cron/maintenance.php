<?php
/**
 * Cron: Maintenance & Cleanup
 * 
 * Performs periodic housekeeping tasks:
 * - Clears expired cache entries
 * - Clears stale rate limit files
 * - Rotates old log files
 * - Reports system health
 * 
 * Schedule: Run daily
 *   0 3 * * * php /path/to/Audit/cron/maintenance.php >> /path/to/Audit/storage/logs/maintenance.log 2>&1
 */

set_time_limit(60);
ini_set('memory_limit', '128M');

require __DIR__ . '/../app/bootstrap.php';

use App\Services\CacheService;
use App\Services\RateLimiter;

$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Maintenance started\n";

// 1. Clear expired cache entries
$cache = CacheService::getInstance();
$cacheCleared = $cache->cleanup();
echo "  Cache: {$cacheCleared} expired entries removed\n";

// 2. Clear stale rate limit files (older than 1 hour)
$rateLimiter = new RateLimiter();
$limitsCleared = $rateLimiter->cleanup(3600);
echo "  Rate limits: {$limitsCleared} stale files removed\n";

// 3. Rotate old log files (delete logs older than 30 days)
$logDir = __DIR__ . '/../storage/logs';
$logsRemoved = 0;
if (is_dir($logDir)) {
    $files = glob($logDir . '/*.log');
    $cutoff = time() - (30 * 86400);
    foreach ($files ?: [] as $file) {
        if (filemtime($file) < $cutoff) {
            @unlink($file);
            $logsRemoved++;
        }
    }
}
echo "  Logs: {$logsRemoved} old log files removed\n";

// 4. Report storage usage
$storageDir = __DIR__ . '/../storage';
$totalSize = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($storageDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile()) $totalSize += $file->getSize();
}
$sizeMb = round($totalSize / 1048576, 2);
echo "  Storage usage: {$sizeMb} MB\n";

echo "[{$timestamp}] Maintenance complete\n";
