<?php

namespace App\Services;

use Database\Database;

/**
 * Resume Service
 * 
 * Handles auto-resuming interrupted and paused jobs.
 * Identifies the last processed page and continues from that point,
 * never restarting the entire audit.
 */
class ResumeService
{
    private $db;
    private JobService $jobService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->jobService = new JobService();
    }

    /**
     * Find all resumable jobs for a project (interrupted or paused).
     */
    public function getResumableJobs(int $projectId): array
    {
        $query = "SELECT * FROM audit_jobs
                  WHERE project_id = :project_id
                    AND status IN ('interrupted', 'paused')
                  ORDER BY updated_at DESC";

        $jobs = $this->db->query($query, ['project_id' => $projectId]) ?: [];

        foreach ($jobs as &$job) {
            $job['selected_modules'] = json_decode($job['selected_modules'], true);
            $job['module_params'] = $job['module_params'] ? json_decode($job['module_params'], true) : null;
            $job['remaining_pages'] = max(0, (int) $job['total_pages'] - (int) $job['processed_pages']);
            $job['progress_percent'] = $job['total_pages'] > 0
                ? round(($job['processed_pages'] / $job['total_pages']) * 100, 1)
                : 0;
        }

        return $jobs;
    }

    /**
     * Resume an interrupted or paused job.
     * Re-queues it so the JobProcessor picks it up from where it stopped.
     *
     * @param int $jobId
     * @return bool
     */
    public function resumeJob(int $jobId): bool
    {
        // Only resume interrupted or paused jobs
        $query = "UPDATE audit_jobs
                  SET status = 'queued',
                      last_heartbeat = NULL,
                      error_message = NULL,
                      updated_at = NOW()
                  WHERE id = :id AND status IN ('interrupted', 'paused')";

        $success = $this->db->execute($query, ['id' => $jobId]);

        if ($success) {
            $this->jobService->log($jobId, 'info', 'Job resumed (re-queued for continuation)');
        }

        return $success;
    }

    /**
     * Resume all interrupted jobs for a project.
     * Returns the count of jobs resumed.
     */
    public function resumeAllInterrupted(int $projectId): int
    {
        $query = "SELECT id FROM audit_jobs
                  WHERE project_id = :project_id AND status = 'interrupted'";

        $jobs = $this->db->query($query, ['project_id' => $projectId]) ?: [];
        $count = 0;

        foreach ($jobs as $job) {
            if ($this->resumeJob((int) $job['id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Create a retry job for failed pages only.
     * Gathers pages that have retry_pending/retry_failed status and creates a
     * new job targeting only those pages.
     *
     * @param int $projectId
     * @param array $moduleKeys Module keys to retry
     * @param array $options [user_id, module_params]
     * @return array|null The created job, or null if nothing to retry
     */
    public function createRetryJob(int $projectId, array $moduleKeys, array $options = []): ?array
    {
        $retryService = new RetryService();

        // Collect page IDs that need retry across selected modules
        $pageIds = [];
        foreach ($moduleKeys as $key) {
            $module = ModuleRegistry::getByKey($key);
            if (!$module) continue;

            $retryable = $retryService->getRetryablePages($projectId, $module['module_name'], 10000);
            foreach ($retryable as $row) {
                $pageIds[(int) $row['project_page_id']] = true;
            }
        }

        $pageIds = array_keys($pageIds);

        if (empty($pageIds)) {
            return null; // Nothing to retry
        }

        // Create a retry job
        $job = $this->jobService->createJob($projectId, $moduleKeys, [
            'job_type' => 'retry',
            'user_id' => $options['user_id'] ?? null,
            'module_params' => $options['module_params'] ?? null,
            'batch_size' => 25,
        ]);

        // Override total_pages to only the retryable count
        $this->db->execute(
            "UPDATE audit_jobs SET total_pages = :total WHERE id = :id",
            ['total' => count($pageIds), 'id' => $job['id']]
        );

        $this->jobService->log((int) $job['id'], 'info', 'Retry job created', [
            'retryable_pages' => count($pageIds),
            'modules' => $moduleKeys,
        ]);

        return $this->jobService->getById((int) $job['id']);
    }

    /**
     * Check if a project has any recoverable work (interrupted jobs or retryable pages).
     */
    public function hasRecoverableWork(int $projectId): array
    {
        $resumable = $this->getResumableJobs($projectId);

        $retryService = new RetryService();
        $retryStats = $retryService->getRetryStats($projectId);

        return [
            'has_resumable_jobs' => count($resumable) > 0,
            'resumable_count' => count($resumable),
            'has_retryable_pages' => $retryStats['retry_pending'] + $retryStats['retry_failed'] > 0,
            'retryable_count' => $retryStats['retry_pending'] + $retryStats['retry_failed'],
            'exhausted_count' => $retryStats['retry_exhausted'],
            'total_failures' => $retryStats['total_failures'],
        ];
    }
}
