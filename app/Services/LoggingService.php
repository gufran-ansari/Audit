<?php

namespace App\Services;

/**
 * Logging Service
 * 
 * Centralized logging for audit execution, jobs, retries, and system events.
 * Writes to structured log files organized by date and category.
 * Lightweight and shared-hosting friendly (file-based, no external deps).
 */
class LoggingService
{
    private static ?LoggingService $instance = null;

    private string $logDir;

    /** Current log entries buffered for batch write */
    private array $buffer = [];
    private int $bufferLimit = 20;

    private function __construct()
    {
        $this->logDir = __DIR__ . '/../../storage/logs';

        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─── PUBLIC LOGGING METHODS ────────────────────────────────

    /**
     * Log an audit execution event.
     */
    public function audit(string $message, array $context = []): void
    {
        $this->write('audit', 'info', $message, $context);
    }

    /**
     * Log a job processing event.
     */
    public function job(string $message, array $context = []): void
    {
        $this->write('jobs', 'info', $message, $context);
    }

    /**
     * Log a retry event.
     */
    public function retry(string $message, array $context = []): void
    {
        $this->write('retry', 'info', $message, $context);
    }

    /**
     * Log a fetch event.
     */
    public function fetch(string $message, array $context = []): void
    {
        $this->write('fetch', 'info', $message, $context);
    }

    /**
     * Log a system/health event.
     */
    public function system(string $message, array $context = []): void
    {
        $this->write('system', 'info', $message, $context);
    }

    /**
     * Log a warning.
     */
    public function warning(string $category, string $message, array $context = []): void
    {
        $this->write($category, 'warning', $message, $context);
    }

    /**
     * Log an error.
     */
    public function error(string $category, string $message, array $context = []): void
    {
        $this->write($category, 'error', $message, $context);
    }

    // ─── PERFORMANCE METRICS ───────────────────────────────────

    /**
     * Log a performance metric.
     */
    public function metric(string $name, $value, array $context = []): void
    {
        $context['metric'] = $name;
        $context['value'] = $value;
        $this->write('metrics', 'info', "{$name}: {$value}", $context);
    }

    // ─── INTERNALS ─────────────────────────────────────────────

    /**
     * Write a log entry to the appropriate file.
     */
    private function write(string $category, string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $date = date('Y-m-d');

        $entry = "[{$timestamp}] [{$level}] {$message}";

        if (!empty($context)) {
            $entry .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        $entry .= PHP_EOL;

        $file = "{$this->logDir}/{$category}-{$date}.log";

        // Direct write (no buffering complexity for shared hosting reliability)
        @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get recent log entries for a category.
     */
    public function getRecentLogs(string $category, int $lines = 50): array
    {
        $date = date('Y-m-d');
        $file = "{$this->logDir}/{$category}-{$date}.log";

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        $allLines = array_filter(explode(PHP_EOL, $content));

        return array_slice($allLines, -$lines);
    }

    /**
     * Get log file size in bytes for a category/date.
     */
    public function getLogSize(string $category, ?string $date = null): int
    {
        $date = $date ?? date('Y-m-d');
        $file = "{$this->logDir}/{$category}-{$date}.log";

        return file_exists($file) ? filesize($file) : 0;
    }

    /**
     * Clean up old log files.
     */
    public function cleanupOldLogs(int $daysToKeep = 14): int
    {
        $cutoff = strtotime("-{$daysToKeep} days");
        $deleted = 0;

        $files = glob("{$this->logDir}/*.log");
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
