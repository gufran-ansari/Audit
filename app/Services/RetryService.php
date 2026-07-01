<?php

namespace App\Services;

use Database\Database;

/**
 * Retry Service
 * 
 * Smart retry engine that tracks failures per page/module and ensures
 * successful pages are never re-processed. Handles retry limits,
 * error classification, and retry state management.
 */
class RetryService
{
    private $db;

    /** Default maximum retry attempts */
    private const DEFAULT_MAX_RETRIES = 3;

    /** Known error type classifications */
    public const ERROR_TIMEOUT = 'timeout';
    public const ERROR_CONNECTION = 'connection';
    public const ERROR_INVALID_RESPONSE = 'invalid_response';
    public const ERROR_MALFORMED_HTML = 'malformed_html';
    public const ERROR_PROCESSING = 'processing';
    public const ERROR_UNKNOWN = 'unknown';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ─── FAILURE RECORDING ─────────────────────────────────────

    /**
     * Record a failure for a page/module. Creates or updates the retry tracking row.
     *
     * @param int $pageId
     * @param string $moduleName Database module name (e.g. 'h1_audit')
     * @param string $errorMessage Human-readable error
     * @param string|null $errorType Classified error type
     */
    public function recordFailure(int $pageId, string $moduleName, string $errorMessage, ?string $errorType = null): void
    {
        $errorType = $errorType ?? $this->classifyError($errorMessage);

        $query = "INSERT INTO page_retry_tracking
                    (project_page_id, module_name, retry_count, error_type, last_error, last_attempt_at, status)
                  VALUES
                    (:page_id, :module_name, 0, :error_type, :last_error, NOW(), 'retry_pending')
                  ON DUPLICATE KEY UPDATE
                    error_type = VALUES(error_type),
                    last_error = VALUES(last_error),
                    last_attempt_at = NOW(),
                    status = CASE 
                        WHEN retry_count >= max_retries THEN 'retry_exhausted'
                        ELSE 'retry_pending'
                    END,
                    updated_at = NOW()";

        $this->db->execute($query, [
            'page_id' => $pageId,
            'module_name' => $moduleName,
            'error_type' => $errorType,
            'last_error' => $errorMessage,
        ]);
    }

    /**
     * Mark a page/module as resolved (successful after retry or manual clear).
     */
    public function markResolved(int $pageId, string $moduleName): void
    {
        $query = "UPDATE page_retry_tracking
                  SET status = 'resolved', updated_at = NOW()
                  WHERE project_page_id = :page_id AND module_name = :module_name";

        $this->db->execute($query, [
            'page_id' => $pageId,
            'module_name' => $moduleName,
        ]);
    }

    /**
     * Increment retry count and update status. Called before a retry attempt.
     */
    public function incrementRetry(int $pageId, string $moduleName): bool
    {
        $query = "UPDATE page_retry_tracking
                  SET retry_count = retry_count + 1,
                      last_attempt_at = NOW(),
                      status = 'retrying',
                      updated_at = NOW()
                  WHERE project_page_id = :page_id 
                    AND module_name = :module_name
                    AND status IN ('retry_pending', 'retry_failed')
                    AND retry_count < max_retries";

        return $this->db->execute($query, [
            'page_id' => $pageId,
            'module_name' => $moduleName,
        ]);
    }

    /**
     * Mark a retry attempt as failed (still under limit or exhausted).
     */
    public function markRetryFailed(int $pageId, string $moduleName, string $errorMessage): void
    {
        $errorType = $this->classifyError($errorMessage);

        $query = "UPDATE page_retry_tracking
                  SET status = CASE
                        WHEN retry_count >= max_retries THEN 'retry_exhausted'
                        ELSE 'retry_failed'
                      END,
                      error_type = :error_type,
                      last_error = :last_error,
                      last_attempt_at = NOW(),
                      updated_at = NOW()
                  WHERE project_page_id = :page_id AND module_name = :module_name";

        $this->db->execute($query, [
            'page_id' => $pageId,
            'module_name' => $moduleName,
            'error_type' => $errorType,
            'last_error' => $errorMessage,
        ]);
    }

    // ─── QUERIES ───────────────────────────────────────────────

    /**
     * Get pages eligible for retry for a project.
     * Returns page IDs with retry_pending or retry_failed status that haven't exhausted retries.
     */
    public function getRetryablePages(int $projectId, ?string $moduleName = null, int $limit = 100): array
    {
        $params = ['project_id' => $projectId, 'limit' => $limit];

        $query = "SELECT prt.*, pp.original_url
                  FROM page_retry_tracking prt
                  INNER JOIN project_pages pp ON pp.id = prt.project_page_id
                  WHERE pp.project_id = :project_id
                    AND prt.status IN ('retry_pending', 'retry_failed')
                    AND prt.retry_count < prt.max_retries";

        if ($moduleName) {
            $query .= " AND prt.module_name = :module_name";
            $params['module_name'] = $moduleName;
        }

        $query .= " ORDER BY prt.retry_count ASC, prt.last_attempt_at ASC
                    LIMIT :limit";

        return $this->db->query($query, $params) ?: [];
    }

    /**
     * Get all failed/interrupted pages for a project (for display).
     */
    public function getFailedPages(int $projectId, int $limit = 200): array
    {
        $query = "SELECT prt.*, pp.original_url
                  FROM page_retry_tracking prt
                  INNER JOIN project_pages pp ON pp.id = prt.project_page_id
                  WHERE pp.project_id = :project_id
                    AND prt.status NOT IN ('resolved')
                  ORDER BY prt.updated_at DESC
                  LIMIT :limit";

        return $this->db->query($query, [
            'project_id' => $projectId,
            'limit' => $limit,
        ]) ?: [];
    }

    /**
     * Get retry history for a specific page.
     */
    public function getPageRetryHistory(int $pageId): array
    {
        $query = "SELECT * FROM page_retry_tracking
                  WHERE project_page_id = :page_id
                  ORDER BY module_name ASC";

        return $this->db->query($query, ['page_id' => $pageId]) ?: [];
    }

    /**
     * Get retry statistics for a project.
     */
    public function getRetryStats(int $projectId): array
    {
        $query = "SELECT 
                    prt.status,
                    COUNT(*) as count
                  FROM page_retry_tracking prt
                  INNER JOIN project_pages pp ON pp.id = prt.project_page_id
                  WHERE pp.project_id = :project_id
                  GROUP BY prt.status";

        $rows = $this->db->query($query, ['project_id' => $projectId]) ?: [];

        $stats = [
            'retry_pending' => 0,
            'retrying' => 0,
            'retry_failed' => 0,
            'retry_exhausted' => 0,
            'resolved' => 0,
            'total_failures' => 0,
        ];

        foreach ($rows as $row) {
            $stats[$row['status']] = (int) $row['count'];
            if ($row['status'] !== 'resolved') {
                $stats['total_failures'] += (int) $row['count'];
            }
        }

        return $stats;
    }

    /**
     * Get error type distribution for a project.
     */
    public function getErrorTypeStats(int $projectId): array
    {
        $query = "SELECT 
                    prt.error_type,
                    COUNT(*) as count
                  FROM page_retry_tracking prt
                  INNER JOIN project_pages pp ON pp.id = prt.project_page_id
                  WHERE pp.project_id = :project_id
                    AND prt.status NOT IN ('resolved')
                  GROUP BY prt.error_type
                  ORDER BY count DESC";

        return $this->db->query($query, ['project_id' => $projectId]) ?: [];
    }

    /**
     * Check if a page/module should be skipped (already passed or exhausted).
     */
    public function shouldSkip(int $pageId, string $moduleName): bool
    {
        // Check if the page already has a passing result
        $query = "SELECT result_status FROM page_audit_results
                  WHERE project_page_id = :page_id AND module_name = :module_name
                  LIMIT 1";

        $result = $this->db->query($query, [
            'page_id' => $pageId,
            'module_name' => $moduleName,
        ]);

        if ($result && isset($result[0]) && $result[0]['result_status'] === 'PASS') {
            return true; // Already passing — never rerun
        }

        // Check if retries are exhausted
        $query2 = "SELECT status FROM page_retry_tracking
                   WHERE project_page_id = :page_id AND module_name = :module_name
                   LIMIT 1";

        $retry = $this->db->query($query2, [
            'page_id' => $pageId,
            'module_name' => $moduleName,
        ]);

        if ($retry && isset($retry[0]) && $retry[0]['status'] === 'retry_exhausted') {
            return true; // Exhausted — stop retrying
        }

        return false;
    }

    /**
     * Reset retry tracking for a page/module (force re-enable).
     */
    public function resetRetry(int $pageId, string $moduleName): bool
    {
        $query = "UPDATE page_retry_tracking
                  SET retry_count = 0, status = 'retry_pending', updated_at = NOW()
                  WHERE project_page_id = :page_id AND module_name = :module_name";

        return $this->db->execute($query, [
            'page_id' => $pageId,
            'module_name' => $moduleName,
        ]);
    }

    /**
     * Reset all retry tracking for a project (force re-enable all).
     */
    public function resetProjectRetries(int $projectId): bool
    {
        $query = "UPDATE page_retry_tracking prt
                  INNER JOIN project_pages pp ON pp.id = prt.project_page_id
                  SET prt.retry_count = 0, prt.status = 'retry_pending', prt.updated_at = NOW()
                  WHERE pp.project_id = :project_id AND prt.status != 'resolved'";

        return $this->db->execute($query, ['project_id' => $projectId]);
    }

    /**
     * Delete resolved entries older than N days.
     */
    public function cleanupResolved(int $daysToKeep = 30): void
    {
        $query = "DELETE FROM page_retry_tracking
                  WHERE status = 'resolved'
                  AND updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)";

        $this->db->execute($query, ['days' => $daysToKeep]);
    }

    // ─── ERROR CLASSIFICATION ──────────────────────────────────

    /**
     * Classify an error message into a known error type.
     */
    public function classifyError(string $message): string
    {
        $lower = strtolower($message);

        if (strpos($lower, 'timeout') !== false || strpos($lower, 'timed out') !== false) {
            return self::ERROR_TIMEOUT;
        }

        if (strpos($lower, 'connection') !== false || strpos($lower, 'connect') !== false
            || strpos($lower, 'refused') !== false || strpos($lower, 'unreachable') !== false) {
            return self::ERROR_CONNECTION;
        }

        if (strpos($lower, 'http 4') !== false || strpos($lower, 'http 5') !== false
            || strpos($lower, 'invalid response') !== false || strpos($lower, 'status code') !== false) {
            return self::ERROR_INVALID_RESPONSE;
        }

        if (strpos($lower, 'malformed') !== false || strpos($lower, 'parse') !== false
            || strpos($lower, 'encoding') !== false || strpos($lower, 'dom') !== false) {
            return self::ERROR_MALFORMED_HTML;
        }

        if (strpos($lower, 'processing') !== false || strpos($lower, 'module') !== false
            || strpos($lower, 'exception') !== false) {
            return self::ERROR_PROCESSING;
        }

        return self::ERROR_UNKNOWN;
    }
}
