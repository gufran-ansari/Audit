<?php

namespace App\Services;

use Database\Database;

/**
 * Job Service
 * 
 * Manages audit job lifecycle: creation, state transitions, progress
 * tracking, heartbeat monitoring, and interruption detection.
 */
class JobService
{
    private $db;

    /** Jobs with no heartbeat for this many seconds are considered interrupted */
    private const HEARTBEAT_TIMEOUT_SECONDS = 120;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ─── CREATION ──────────────────────────────────────────────

    /**
     * Create a new audit job.
     *
     * @param int $projectId
     * @param array $selectedModules Module keys
     * @param array $options [batch_size, priority, module_params, job_type, user_id, audit_run_id]
     * @return array The created job record
     */
    public function createJob(int $projectId, array $selectedModules, array $options = []): array
    {
        $batchSize = $options['batch_size'] ?? 25;
        $priority = $options['priority'] ?? 5;
        $moduleParams = $options['module_params'] ?? null;
        $jobType = $options['job_type'] ?? 'audit';
        $userId = $options['user_id'] ?? null;
        $auditRunId = $options['audit_run_id'] ?? null;

        // Count total active pages for the project
        $totalPages = $this->countProjectPages($projectId);

        $query = "INSERT INTO audit_jobs (
                    audit_run_id, project_id, job_type, selected_modules, module_params,
                    status, priority, total_pages, batch_size, max_retries,
                    created_by, created_at
                  ) VALUES (
                    :audit_run_id, :project_id, :job_type, :selected_modules, :module_params,
                    'queued', :priority, :total_pages, :batch_size, :max_retries,
                    :created_by, NOW()
                  )";

        $id = $this->db->insert($query, [
            'audit_run_id' => $auditRunId,
            'project_id' => $projectId,
            'job_type' => $jobType,
            'selected_modules' => json_encode($selectedModules),
            'module_params' => $moduleParams ? json_encode($moduleParams) : null,
            'priority' => $priority,
            'total_pages' => $totalPages,
            'batch_size' => $batchSize,
            'max_retries' => $options['max_retries'] ?? 3,
            'created_by' => $userId,
        ]);

        $this->log($id, 'info', 'Job created', [
            'modules' => $selectedModules,
            'total_pages' => $totalPages,
            'batch_size' => $batchSize,
        ]);

        return $this->getById($id);
    }

    // ─── STATE TRANSITIONS ─────────────────────────────────────

    /**
     * Claim and start processing a queued job.
     */
    public function startJob(int $jobId): bool
    {
        // Atomic claim: only transition from 'queued' to prevent double-processing
        $query = "UPDATE audit_jobs 
                  SET status = 'processing', started_at = NOW(), last_heartbeat = NOW()
                  WHERE id = :id AND status = 'queued'";

        $success = $this->db->execute($query, ['id' => $jobId]);

        if ($success) {
            $this->log($jobId, 'info', 'Job started processing');
        }

        return $success;
    }

    /**
     * Mark job as completed.
     */
    public function completeJob(int $jobId): bool
    {
        $query = "UPDATE audit_jobs 
                  SET status = 'completed', completed_at = NOW(), last_heartbeat = NOW()
                  WHERE id = :id AND status = 'processing'";

        $success = $this->db->execute($query, ['id' => $jobId]);

        if ($success) {
            $this->log($jobId, 'info', 'Job completed successfully');
        }

        return $success;
    }

    /**
     * Mark job as failed.
     */
    public function failJob(int $jobId, string $errorMessage): bool
    {
        $query = "UPDATE audit_jobs 
                  SET status = 'failed', completed_at = NOW(), error_message = :error, last_heartbeat = NOW()
                  WHERE id = :id";

        $success = $this->db->execute($query, [
            'id' => $jobId,
            'error' => $errorMessage,
        ]);

        if ($success) {
            $this->log($jobId, 'error', "Job failed: {$errorMessage}");
        }

        return $success;
    }

    /**
     * Pause a running job (user-initiated).
     */
    public function pauseJob(int $jobId): bool
    {
        $query = "UPDATE audit_jobs 
                  SET status = 'paused', last_heartbeat = NOW()
                  WHERE id = :id AND status = 'processing'";

        $success = $this->db->execute($query, ['id' => $jobId]);

        if ($success) {
            $this->log($jobId, 'info', 'Job paused by user');
        }

        return $success;
    }

    /**
     * Mark job as interrupted (system-detected).
     */
    public function markInterrupted(int $jobId): bool
    {
        $query = "UPDATE audit_jobs 
                  SET status = 'interrupted'
                  WHERE id = :id AND status = 'processing'";

        $success = $this->db->execute($query, ['id' => $jobId]);

        if ($success) {
            $this->log($jobId, 'warning', 'Job marked as interrupted (heartbeat timeout)');
        }

        return $success;
    }

    // ─── PROGRESS TRACKING ─────────────────────────────────────

    /**
     * Update job progress and heartbeat.
     * Called after each batch is processed.
     */
    public function updateProgress(int $jobId, int $processedPages, int $successCount, int $failedCount, ?int $lastPageId = null): bool
    {
        $query = "UPDATE audit_jobs 
                  SET processed_pages = :processed,
                      success_count = :success,
                      failed_count = :failed,
                      last_page_id = :last_page_id,
                      last_heartbeat = NOW()
                  WHERE id = :id";

        return $this->db->execute($query, [
            'id' => $jobId,
            'processed' => $processedPages,
            'success' => $successCount,
            'failed' => $failedCount,
            'last_page_id' => $lastPageId,
        ]);
    }

    /**
     * Send a heartbeat (updates last_heartbeat without changing progress).
     */
    public function heartbeat(int $jobId): bool
    {
        $query = "UPDATE audit_jobs SET last_heartbeat = NOW() WHERE id = :id";
        return $this->db->execute($query, ['id' => $jobId]);
    }

    // ─── INTERRUPTION DETECTION ────────────────────────────────

    /**
     * Find jobs that appear to have been interrupted (no heartbeat within timeout).
     */
    public function detectInterruptedJobs(): array
    {
        $query = "SELECT * FROM audit_jobs
                  WHERE status = 'processing'
                  AND last_heartbeat < DATE_SUB(NOW(), INTERVAL :timeout SECOND)";

        return $this->db->query($query, ['timeout' => self::HEARTBEAT_TIMEOUT_SECONDS]) ?: [];
    }

    /**
     * Mark all detected interrupted jobs.
     * Returns the number of jobs marked.
     */
    public function markAllInterrupted(): int
    {
        $interrupted = $this->detectInterruptedJobs();
        $count = 0;

        foreach ($interrupted as $job) {
            if ($this->markInterrupted((int) $job['id'])) {
                $count++;
            }
        }

        return $count;
    }

    // ─── QUERIES ───────────────────────────────────────────────

    /**
     * Get a job by ID.
     */
    public function getById(int $jobId): ?array
    {
        $query = "SELECT aj.*, u.name as created_by_name, p.project_name
                  FROM audit_jobs aj
                  LEFT JOIN users u ON u.id = aj.created_by
                  LEFT JOIN projects p ON p.id = aj.project_id
                  WHERE aj.id = :id";

        $result = $this->db->query($query, ['id' => $jobId]);

        if ($result && isset($result[0])) {
            $row = $result[0];
            $row['selected_modules'] = json_decode($row['selected_modules'], true);
            $row['module_params'] = $row['module_params'] ? json_decode($row['module_params'], true) : null;
            $row['progress_percent'] = $row['total_pages'] > 0
                ? round(($row['processed_pages'] / $row['total_pages']) * 100, 1)
                : 0;
            return $row;
        }

        return null;
    }

    /**
     * Get the next queued job (highest priority, oldest first).
     */
    public function getNextQueued(): ?array
    {
        $query = "SELECT * FROM audit_jobs
                  WHERE status = 'queued'
                  ORDER BY priority ASC, created_at ASC
                  LIMIT 1";

        $result = $this->db->query($query, []);

        if ($result && isset($result[0])) {
            $row = $result[0];
            $row['selected_modules'] = json_decode($row['selected_modules'], true);
            $row['module_params'] = $row['module_params'] ? json_decode($row['module_params'], true) : null;
            return $row;
        }

        return null;
    }

    /**
     * Get all jobs for a project (most recent first).
     */
    public function getByProject(int $projectId, int $limit = 50): array
    {
        $query = "SELECT aj.*, u.name as created_by_name
                  FROM audit_jobs aj
                  LEFT JOIN users u ON u.id = aj.created_by
                  WHERE aj.project_id = :project_id
                  ORDER BY aj.created_at DESC
                  LIMIT :limit";

        $jobs = $this->db->query($query, [
            'project_id' => $projectId,
            'limit' => $limit,
        ]) ?: [];

        foreach ($jobs as &$job) {
            $job['selected_modules'] = json_decode($job['selected_modules'], true);
            $job['module_params'] = $job['module_params'] ? json_decode($job['module_params'], true) : null;
            $job['progress_percent'] = $job['total_pages'] > 0
                ? round(($job['processed_pages'] / $job['total_pages']) * 100, 1)
                : 0;
        }

        return $jobs;
    }

    /**
     * Get jobs by status.
     */
    public function getByStatus(string $status, int $limit = 50): array
    {
        $query = "SELECT aj.*, p.project_name, u.name as created_by_name
                  FROM audit_jobs aj
                  LEFT JOIN projects p ON p.id = aj.project_id
                  LEFT JOIN users u ON u.id = aj.created_by
                  WHERE aj.status = :status
                  ORDER BY aj.priority ASC, aj.created_at ASC
                  LIMIT :limit";

        $jobs = $this->db->query($query, [
            'status' => $status,
            'limit' => $limit,
        ]) ?: [];

        foreach ($jobs as &$job) {
            $job['selected_modules'] = json_decode($job['selected_modules'], true);
            $job['module_params'] = $job['module_params'] ? json_decode($job['module_params'], true) : null;
            $job['progress_percent'] = $job['total_pages'] > 0
                ? round(($job['processed_pages'] / $job['total_pages']) * 100, 1)
                : 0;
        }

        return $jobs;
    }

    /**
     * Get counts of jobs grouped by status for a project.
     */
    public function getStatusCounts(int $projectId): array
    {
        $query = "SELECT status, COUNT(*) as count
                  FROM audit_jobs
                  WHERE project_id = :project_id
                  GROUP BY status";

        $rows = $this->db->query($query, ['project_id' => $projectId]) ?: [];

        $counts = [
            'queued' => 0,
            'processing' => 0,
            'paused' => 0,
            'interrupted' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Check if any job is currently running for a project.
     */
    public function isProjectBusy(int $projectId): bool
    {
        $query = "SELECT COUNT(*) as count FROM audit_jobs
                  WHERE project_id = :project_id AND status IN ('queued', 'processing')";

        $result = $this->db->query($query, ['project_id' => $projectId]);
        return $result && (int) $result[0]['count'] > 0;
    }

    // ─── LOGGING ───────────────────────────────────────────────

    /**
     * Add a log entry for a job.
     */
    public function log(int $jobId, string $level, string $message, ?array $context = null): void
    {
        $query = "INSERT INTO audit_job_logs (job_id, log_level, message, context, created_at)
                  VALUES (:job_id, :log_level, :message, :context, NOW())";

        $this->db->insert($query, [
            'job_id' => $jobId,
            'log_level' => $level,
            'message' => $message,
            'context' => $context ? json_encode($context) : null,
        ]);
    }

    /**
     * Get logs for a job.
     */
    public function getLogs(int $jobId, int $limit = 100): array
    {
        $query = "SELECT * FROM audit_job_logs
                  WHERE job_id = :job_id
                  ORDER BY created_at DESC
                  LIMIT :limit";

        return $this->db->query($query, [
            'job_id' => $jobId,
            'limit' => $limit,
        ]) ?: [];
    }

    // ─── CLEANUP ───────────────────────────────────────────────

    /**
     * Delete old completed/failed jobs.
     */
    public function cleanupOldJobs(int $daysToKeep = 30): int
    {
        $query = "DELETE FROM audit_jobs
                  WHERE status IN ('completed', 'failed')
                  AND completed_at < DATE_SUB(NOW(), INTERVAL :days DAY)";

        $this->db->execute($query, ['days' => $daysToKeep]);

        // Return affected rows via a count query proxy (PDO limitation)
        return 0;
    }

    // ─── HELPERS ───────────────────────────────────────────────

    /**
     * Count active pages for a project.
     */
    private function countProjectPages(int $projectId): int
    {
        $query = "SELECT COUNT(*) as count FROM project_pages
                  WHERE project_id = :project_id AND url_status = 'active'";

        $result = $this->db->query($query, ['project_id' => $projectId]);
        return $result ? (int) $result[0]['count'] : 0;
    }
}
