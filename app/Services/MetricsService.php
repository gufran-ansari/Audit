<?php

namespace App\Services;

use Database\Database;

/**
 * Metrics Service
 * 
 * Tracks audit performance metrics: execution time, pages per minute,
 * module execution time, fetch time, and success/failure rates.
 * Stores in-memory during execution and persists summary at the end.
 */
class MetricsService
{
    private array $timers = [];
    private array $counters = [];
    private array $moduleTimings = [];
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    // ─── TIMING ────────────────────────────────────────────────

    /**
     * Start a named timer.
     */
    public function startTimer(string $name): void
    {
        $this->timers[$name] = microtime(true);
    }

    /**
     * Stop a named timer and return elapsed milliseconds.
     */
    public function stopTimer(string $name): int
    {
        if (!isset($this->timers[$name])) {
            return 0;
        }

        $elapsed = (int) round((microtime(true) - $this->timers[$name]) * 1000);
        unset($this->timers[$name]);
        return $elapsed;
    }

    /**
     * Record a module execution time.
     */
    public function recordModuleTime(string $moduleKey, int $milliseconds): void
    {
        if (!isset($this->moduleTimings[$moduleKey])) {
            $this->moduleTimings[$moduleKey] = [];
        }
        $this->moduleTimings[$moduleKey][] = $milliseconds;
    }

    // ─── COUNTERS ──────────────────────────────────────────────

    /**
     * Increment a counter.
     */
    public function increment(string $name, int $amount = 1): void
    {
        $this->counters[$name] = ($this->counters[$name] ?? 0) + $amount;
    }

    /**
     * Get a counter value.
     */
    public function getCount(string $name): int
    {
        return $this->counters[$name] ?? 0;
    }

    // ─── SUMMARY ───────────────────────────────────────────────

    /**
     * Get a full metrics summary.
     */
    public function getSummary(): array
    {
        $totalTime = (int) round((microtime(true) - $this->startTime) * 1000);
        $pagesProcessed = $this->getCount('pages_processed');
        $pagesPerMinute = $totalTime > 0
            ? round(($pagesProcessed / ($totalTime / 60000)), 1)
            : 0;

        $moduleSummary = [];
        foreach ($this->moduleTimings as $key => $timings) {
            $count = count($timings);
            $total = array_sum($timings);
            $moduleSummary[$key] = [
                'count' => $count,
                'total_ms' => $total,
                'avg_ms' => $count > 0 ? round($total / $count) : 0,
                'min_ms' => $count > 0 ? min($timings) : 0,
                'max_ms' => $count > 0 ? max($timings) : 0,
            ];
        }

        return [
            'total_time_ms' => $totalTime,
            'pages_processed' => $pagesProcessed,
            'pages_per_minute' => $pagesPerMinute,
            'pages_success' => $this->getCount('pages_success'),
            'pages_failed' => $this->getCount('pages_failed'),
            'fetch_count' => $this->getCount('fetches'),
            'fetch_cache_hits' => $this->getCount('fetch_cache_hits'),
            'avg_fetch_time_ms' => $this->getCount('fetches') > 0
                ? round($this->getCount('total_fetch_time') / $this->getCount('fetches'))
                : 0,
            'writes_skipped' => $this->getCount('writes_skipped'),
            'writes_performed' => $this->getCount('writes_performed'),
            'module_timings' => $moduleSummary,
            'counters' => $this->counters,
        ];
    }

    /**
     * Log the summary to the logging service.
     */
    public function logSummary(int $jobId): void
    {
        $summary = $this->getSummary();
        $logger = LoggingService::getInstance();
        $logger->metric('job_completed', $summary['total_time_ms'], [
            'job_id' => $jobId,
            'pages' => $summary['pages_processed'],
            'ppm' => $summary['pages_per_minute'],
            'success' => $summary['pages_success'],
            'failed' => $summary['pages_failed'],
        ]);
    }

    /**
     * Reset all metrics for a fresh run.
     */
    public function reset(): void
    {
        $this->timers = [];
        $this->counters = [];
        $this->moduleTimings = [];
        $this->startTime = microtime(true);
    }
}
