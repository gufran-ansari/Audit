<?php

namespace App\Services;

/**
 * Audit Run Service
 * 
 * Manages audit run lifecycle and tracking
 */
class AuditRunService
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Create new audit run
     * 
     * @param int $projectId Project ID
     * @param array $selectedModules Array of module names
     * @param string $runType Type of run (manual, retry, preset, scheduled)
     * @param int|null $userId User who initiated the run
     * @return array Audit run data with UUID
     */
    public function createRun(
        int $projectId,
        array $selectedModules,
        string $runType = 'manual',
        ?int $userId = null
    ): array {
        $uuid = $this->generateUuid();
        
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_runs (
                uuid, project_id, run_type, selected_modules,
                total_pages, run_status, created_by, started_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");

        $totalPages = $this->getTotalPagesForProject($projectId);
        
        $stmt->execute([
            $uuid,
            $projectId,
            $runType,
            json_encode($selectedModules),
            $totalPages,
            $userId
        ]);

        return [
            'id' => $this->pdo->lastInsertId(),
            'uuid' => $uuid,
            'project_id' => $projectId,
            'run_type' => $runType,
            'selected_modules' => $selectedModules,
            'total_pages' => $totalPages,
            'status' => 'pending'
        ];
    }

    /**
     * Start audit run
     */
    public function startRun(int $runId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE audit_runs 
            SET run_status = 'running', started_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$runId]);
    }

    /**
     * Update run progress
     */
    public function updateProgress(
        int $runId,
        int $processedPages,
        int $successPages,
        int $failedPages
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE audit_runs 
            SET processed_pages = ?,
                success_pages = ?,
                failed_pages = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $processedPages,
            $successPages,
            $failedPages,
            $runId
        ]);
    }

    /**
     * Complete audit run
     */
    public function completeRun(int $runId, bool $success = true, ?string $errorMessage = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE audit_runs 
            SET run_status = ?,
                completed_at = NOW(),
                execution_time_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW()),
                error_message = ?
            WHERE id = ?
        ");

        $status = $success ? 'completed' : 'failed';

        return $stmt->execute([$status, $errorMessage, $runId]);
    }

    /**
     * Get audit run by ID
     */
    public function getRunById(int $runId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ar.*,
                u.name as created_by_name,
                p.project_name
            FROM audit_runs ar
            LEFT JOIN users u ON u.id = ar.created_by
            LEFT JOIN projects p ON p.id = ar.project_id
            WHERE ar.id = ?
        ");

        $stmt->execute([$runId]);
        $run = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($run) {
            $run['selected_modules'] = json_decode($run['selected_modules'], true);
            $run['metadata'] = $run['metadata'] ? json_decode($run['metadata'], true) : null;
        }

        return $run ?: null;
    }

    /**
     * Get audit run by UUID
     */
    public function getRunByUuid(string $uuid): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ar.*,
                u.name as created_by_name,
                p.project_name
            FROM audit_runs ar
            LEFT JOIN users u ON u.id = ar.created_by
            LEFT JOIN projects p ON p.id = ar.project_id
            WHERE ar.uuid = ?
        ");

        $stmt->execute([$uuid]);
        $run = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($run) {
            $run['selected_modules'] = json_decode($run['selected_modules'], true);
            $run['metadata'] = $run['metadata'] ? json_decode($run['metadata'], true) : null;
        }

        return $run ?: null;
    }

    /**
     * Get all runs for a project
     */
    public function getProjectRuns(int $projectId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ar.*,
                u.name as created_by_name
            FROM audit_runs ar
            LEFT JOIN users u ON u.id = ar.created_by
            WHERE ar.project_id = ?
            ORDER BY ar.created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$projectId, $limit]);
        $runs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($runs as &$run) {
            $run['selected_modules'] = json_decode($run['selected_modules'], true);
            $run['metadata'] = $run['metadata'] ? json_decode($run['metadata'], true) : null;
        }

        return $runs;
    }

    /**
     * Get latest run for a project
     */
    public function getLatestRun(int $projectId): ?array
    {
        $runs = $this->getProjectRuns($projectId, 1);
        return $runs[0] ?? null;
    }

    /**
     * Get run statistics
     */
    public function getRunStatistics(int $runId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_results,
                SUM(CASE WHEN result_status = 'PASS' THEN 1 ELSE 0 END) as pass_count,
                SUM(CASE WHEN result_status = 'WARNING' THEN 1 ELSE 0 END) as warning_count,
                SUM(CASE WHEN result_status = 'FAIL' THEN 1 ELSE 0 END) as fail_count,
                SUM(CASE WHEN result_status = 'ERROR' THEN 1 ELSE 0 END) as error_count,
                AVG(execution_time_ms) as avg_execution_time_ms
            FROM page_audit_results
            WHERE audit_run_id = ?
        ");

        $stmt->execute([$runId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Delete old audit runs
     * 
     * @param int $daysToKeep Number of days to keep
     * @return int Number of runs deleted
     */
    public function cleanupOldRuns(int $daysToKeep = 90): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM audit_runs
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND run_status IN ('completed', 'failed')
        ");

        $stmt->execute([$daysToKeep]);
        return $stmt->rowCount();
    }

    /**
     * Get total pages for a project
     */
    private function getTotalPagesForProject(int $projectId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM project_pages 
            WHERE project_id = ? AND url_status = 'active'
        ");

        $stmt->execute([$projectId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Generate UUID
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Cancel a running audit
     */
    public function cancelRun(int $runId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE audit_runs 
            SET run_status = 'cancelled',
                completed_at = NOW(),
                execution_time_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
            WHERE id = ? AND run_status = 'running'
        ");

        return $stmt->execute([$runId]);
    }
}
