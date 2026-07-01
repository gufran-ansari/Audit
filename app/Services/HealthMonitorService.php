<?php

namespace App\Services;

use Database\Database;

/**
 * Health Monitor Service
 * 
 * Provides project health status, processing safeguards, and
 * system-level checks. Detects stale/orphaned jobs, enforces
 * duplicate prevention, and reports overall processing state.
 */
class HealthMonitorService
{
    private $db;
    private JobService $jobService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->jobService = new JobService();
    }

    // ─── PROJECT HEALTH ────────────────────────────────────────

    /**
     * Get comprehensive health status for a project.
     */
    public function getProjectHealth(int $projectId): array
    {
        $jobCounts = $this->jobService->getStatusCounts($projectId);

        $retryService = new RetryService();
        $retryStats = $retryService->getRetryStats($projectId);

        // Last completed audit
        $lastRun = $this->getLastCompletedJob($projectId);

        // Processing rate (from last completed job)
        $processingRate = $this->getProcessingRate($projectId);

        $activeJobs = $jobCounts['processing'] + $jobCounts['queued'];
        $problemJobs = $jobCounts['interrupted'] + $jobCounts['failed'];

        // Determine health status
        $status = 'healthy';
        if ($problemJobs > 0 && $activeJobs === 0) {
            $status = 'needs_attention';
        }
        if ($jobCounts['interrupted'] > 2) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'jobs' => $jobCounts,
            'active_jobs' => $activeJobs,
            'problem_jobs' => $problemJobs,
            'retry_queue' => $retryStats['retry_pending'] + $retryStats['retry_failed'],
            'retry_exhausted' => $retryStats['retry_exhausted'],
            'last_completed_job' => $lastRun,
            'processing_rate' => $processingRate,
        ];
    }

    // ─── SAFEGUARDS ────────────────────────────────────────────

    /**
     * Run all processing safeguards.
     * Call from cron or before starting new jobs.
     * Returns a report of actions taken.
     */
    public function runSafeguards(): array
    {
        $report = [
            'interrupted_marked' => 0,
            'stale_failed' => 0,
            'orphans_cleaned' => 0,
        ];

        // 1. Mark interrupted jobs (heartbeat timeout)
        $report['interrupted_marked'] = $this->jobService->markAllInterrupted();

        // 2. Fail very stale 'processing' jobs (safeguard timeout)
        $report['stale_failed'] = $this->failStaleJobs();

        // 3. Clean orphaned jobs (no project or invalid state)
        $report['orphans_cleaned'] = $this->cleanOrphanedJobs();

        // Log safeguard run
        $logger = LoggingService::getInstance();
        $logger->system('Safeguards executed', $report);

        return $report;
    }

    /**
     * Fail jobs that have been 'processing' for too long (stale job timeout).
     * Default: 1 hour.
     */
    private function failStaleJobs(): int
    {
        $config = require __DIR__ . '/../../config/audit.php';
        $timeout = $config['safeguards']['stale_job_timeout'] ?? 3600;

        $query = "SELECT id FROM audit_jobs
                  WHERE status = 'processing'
                  AND started_at < DATE_SUB(NOW(), INTERVAL :timeout SECOND)";

        $stale = $this->db->query($query, ['timeout' => $timeout]) ?: [];
        $count = 0;

        foreach ($stale as $job) {
            $this->jobService->failJob((int) $job['id'], 'Stale job timeout (exceeded ' . $timeout . 's)');
            $count++;
        }

        return $count;
    }

    /**
     * Clean up orphaned jobs (project deleted or invalid state combos).
     */
    private function cleanOrphanedJobs(): int
    {
        // Jobs whose project no longer exists
        $query = "DELETE aj FROM audit_jobs aj
                  LEFT JOIN projects p ON p.id = aj.project_id
                  WHERE p.id IS NULL";

        $this->db->execute($query, []);

        return 0; // PDO doesn't expose affected rows easily here
    }

    /**
     * Check if it's safe to start a new job for a project.
     * Prevents duplicate execution.
     */
    public function canStartJob(int $projectId): array
    {
        $config = require __DIR__ . '/../../config/audit.php';
        $preventDuplicates = $config['safeguards']['duplicate_prevention'] ?? true;

        if (!$preventDuplicates) {
            return ['allowed' => true, 'reason' => null];
        }

        if ($this->jobService->isProjectBusy($projectId)) {
            return [
                'allowed' => false,
                'reason' => 'Project already has active or queued jobs'
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Check for potential infinite loop (too many consecutive failures).
     * Returns true if the job should be aborted.
     */
    public function shouldAbortJob(int $jobId, int $consecutiveErrors): bool
    {
        $config = require __DIR__ . '/../../config/audit.php';
        $maxErrors = $config['safeguards']['max_consecutive_errors'] ?? 50;

        return $consecutiveErrors >= $maxErrors;
    }

    // ─── QUERIES ───────────────────────────────────────────────

    /**
     * Get the last completed job for a project.
     */
    private function getLastCompletedJob(int $projectId): ?array
    {
        $query = "SELECT id, completed_at, total_pages, success_count, failed_count,
                         TIMESTAMPDIFF(SECOND, started_at, completed_at) as duration_seconds
                  FROM audit_jobs
                  WHERE project_id = :project_id AND status = 'completed'
                  ORDER BY completed_at DESC
                  LIMIT 1";

        $result = $this->db->query($query, ['project_id' => $projectId]);
        return $result ? $result[0] : null;
    }

    /**
     * Get processing rate from recent jobs.
     */
    private function getProcessingRate(int $projectId): ?float
    {
        $query = "SELECT 
                    SUM(success_count + failed_count) as total_pages,
                    SUM(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as total_seconds
                  FROM audit_jobs
                  WHERE project_id = :project_id 
                    AND status = 'completed'
                    AND completed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";

        $result = $this->db->query($query, ['project_id' => $projectId]);

        if ($result && $result[0]['total_seconds'] > 0) {
            return round(($result[0]['total_pages'] / $result[0]['total_seconds']) * 60, 1);
        }

        return null;
    }

    // ─── SYSTEM-WIDE ───────────────────────────────────────────

    /**
     * Get system-wide health overview (across all projects).
     */
    public function getSystemHealth(): array
    {
        $query = "SELECT 
                    SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN status = 'interrupted' THEN 1 ELSE 0 END) as interrupted,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    COUNT(*) as total
                  FROM audit_jobs
                  WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";

        $result = $this->db->query($query, []);
        $jobs = $result ? $result[0] : [];

        // Retry queue system-wide
        $retryQuery = "SELECT COUNT(*) as count FROM page_retry_tracking
                       WHERE status IN ('retry_pending', 'retry_failed')";
        $retryResult = $this->db->query($retryQuery, []);
        $retryQueue = $retryResult ? (int) $retryResult[0]['count'] : 0;

        return [
            'jobs_24h' => $jobs,
            'retry_queue_total' => $retryQueue,
            'status' => ((int)($jobs['interrupted'] ?? 0) > 5) ? 'degraded' : 'healthy',
        ];
    }
}
