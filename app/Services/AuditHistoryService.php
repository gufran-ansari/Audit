<?php

namespace App\Services;

/**
 * Audit History Service
 * 
 * Tracks and manages audit result changes over time
 */
class AuditHistoryService
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Record audit history if result changed
     * 
     * @param int $pageId Project page ID
     * @param string $moduleName Module name
     * @param array|null $oldResult Previous result
     * @param array $newResult New result
     * @param int|null $auditRunId Audit run ID
     * @return bool True if history was recorded, false if no change
     */
    public function recordIfChanged(
        int $pageId,
        string $moduleName,
        ?array $oldResult,
        array $newResult,
        ?int $auditRunId = null
    ): bool {
        // Check if result changed
        if (!AuditResultService::hasChanged($oldResult, $newResult)) {
            return false;
        }

        // Record the change
        return $this->record(
            $pageId,
            $moduleName,
            $oldResult,
            $newResult,
            $auditRunId
        );
    }

    /**
     * Record audit history entry
     */
    public function record(
        int $pageId,
        string $moduleName,
        ?array $oldResult,
        array $newResult,
        ?int $auditRunId = null
    ): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO page_audit_history (
                project_page_id,
                audit_run_id,
                module_name,
                previous_status,
                new_status,
                previous_value,
                new_value,
                previous_data,
                new_data,
                change_summary,
                changed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $previousStatus = $oldResult['status'] ?? null;
        $newStatus = $newResult['status'];
        $previousValue = $oldResult['value'] ?? null;
        $newValue = $newResult['value'] ?? null;
        $previousData = isset($oldResult['data']) ? json_encode($oldResult['data']) : null;
        $newData = json_encode($newResult['data'] ?? []);
        
        $changeSummary = AuditResultService::generateChangeSummary(
            $moduleName,
            $oldResult,
            $newResult
        );

        return $stmt->execute([
            $pageId,
            $auditRunId,
            $moduleName,
            $previousStatus,
            $newStatus,
            $previousValue,
            $newValue,
            $previousData,
            $newData,
            $changeSummary
        ]);
    }

    /**
     * Get history for a specific page and module
     */
    public function getPageModuleHistory(
        int $pageId,
        string $moduleName,
        int $limit = 50
    ): array {
        $stmt = $this->pdo->prepare("
            SELECT 
                pah.*,
                ar.uuid as audit_run_uuid,
                ar.run_type
            FROM page_audit_history pah
            LEFT JOIN audit_runs ar ON ar.id = pah.audit_run_id
            WHERE pah.project_page_id = ? AND pah.module_name = ?
            ORDER BY pah.changed_at DESC
            LIMIT ?
        ");

        $stmt->execute([$pageId, $moduleName, $limit]);
        $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($history as &$entry) {
            $entry['previous_data'] = $entry['previous_data'] ? json_decode($entry['previous_data'], true) : null;
            $entry['new_data'] = $entry['new_data'] ? json_decode($entry['new_data'], true) : null;
        }

        return $history;
    }

    /**
     * Get all history for a page (all modules)
     */
    public function getPageHistory(int $pageId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                pah.*,
                ar.uuid as audit_run_uuid,
                ar.run_type
            FROM page_audit_history pah
            LEFT JOIN audit_runs ar ON ar.id = pah.audit_run_id
            WHERE pah.project_page_id = ?
            ORDER BY pah.changed_at DESC
            LIMIT ?
        ");

        $stmt->execute([$pageId, $limit]);
        $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($history as &$entry) {
            $entry['previous_data'] = $entry['previous_data'] ? json_decode($entry['previous_data'], true) : null;
            $entry['new_data'] = $entry['new_data'] ? json_decode($entry['new_data'], true) : null;
        }

        return $history;
    }

    /**
     * Get history for an audit run
     */
    public function getRunHistory(int $auditRunId, int $limit = 1000): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                pah.*,
                pp.original_url
            FROM page_audit_history pah
            INNER JOIN project_pages pp ON pp.id = pah.project_page_id
            WHERE pah.audit_run_id = ?
            ORDER BY pah.changed_at DESC
            LIMIT ?
        ");

        $stmt->execute([$auditRunId, $limit]);
        $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($history as &$entry) {
            $entry['previous_data'] = $entry['previous_data'] ? json_decode($entry['previous_data'], true) : null;
            $entry['new_data'] = $entry['new_data'] ? json_decode($entry['new_data'], true) : null;
        }

        return $history;
    }

    /**
     * Get history statistics for a project
     */
    public function getProjectHistoryStats(int $projectId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                pah.module_name,
                COUNT(*) as total_changes,
                SUM(CASE WHEN pah.new_status = 'PASS' AND pah.previous_status != 'PASS' THEN 1 ELSE 0 END) as improvements,
                SUM(CASE WHEN pah.new_status IN ('FAIL', 'ERROR') AND pah.previous_status = 'PASS' THEN 1 ELSE 0 END) as regressions,
                MAX(pah.changed_at) as last_change
            FROM page_audit_history pah
            INNER JOIN project_pages pp ON pp.id = pah.project_page_id
            WHERE pp.project_id = ?
            GROUP BY pah.module_name
        ");

        $stmt->execute([$projectId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get recent changes for a project
     */
    public function getRecentChanges(int $projectId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                pah.*,
                pp.original_url,
                ar.uuid as audit_run_uuid,
                ar.run_type
            FROM page_audit_history pah
            INNER JOIN project_pages pp ON pp.id = pah.project_page_id
            LEFT JOIN audit_runs ar ON ar.id = pah.audit_run_id
            WHERE pp.project_id = ?
            ORDER BY pah.changed_at DESC
            LIMIT ?
        ");

        $stmt->execute([$projectId, $limit]);
        $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($history as &$entry) {
            $entry['previous_data'] = $entry['previous_data'] ? json_decode($entry['previous_data'], true) : null;
            $entry['new_data'] = $entry['new_data'] ? json_decode($entry['new_data'], true) : null;
        }

        return $history;
    }

    /**
     * Get changes by status transition
     * 
     * @param int $projectId Project ID
     * @param string $fromStatus Previous status
     * @param string $toStatus New status
     * @param int $limit Limit results
     * @return array History entries
     */
    public function getChangesByTransition(
        int $projectId,
        string $fromStatus,
        string $toStatus,
        int $limit = 50
    ): array {
        $stmt = $this->pdo->prepare("
            SELECT 
                pah.*,
                pp.original_url
            FROM page_audit_history pah
            INNER JOIN project_pages pp ON pp.id = pah.project_page_id
            WHERE pp.project_id = ?
              AND pah.previous_status = ?
              AND pah.new_status = ?
            ORDER BY pah.changed_at DESC
            LIMIT ?
        ");

        $stmt->execute([$projectId, $fromStatus, $toStatus, $limit]);
        $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($history as &$entry) {
            $entry['previous_data'] = $entry['previous_data'] ? json_decode($entry['previous_data'], true) : null;
            $entry['new_data'] = $entry['new_data'] ? json_decode($entry['new_data'], true) : null;
        }

        return $history;
    }

    /**
     * Delete old history entries
     * 
     * @param int $daysToKeep Number of days to keep
     * @return int Number of entries deleted
     */
    public function cleanupOldHistory(int $daysToKeep = 180): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM page_audit_history
            WHERE changed_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");

        $stmt->execute([$daysToKeep]);
        return $stmt->rowCount();
    }

    /**
     * Get comparison between two points in time
     */
    public function compareTimePoints(
        int $projectId,
        string $moduleName,
        string $startDate,
        string $endDate
    ): array {
        // Get status distribution at start date
        $stmt = $this->pdo->prepare("
            SELECT 
                new_status as status,
                COUNT(*) as count
            FROM page_audit_history pah
            INNER JOIN project_pages pp ON pp.id = pah.project_page_id
            WHERE pp.project_id = ?
              AND pah.module_name = ?
              AND pah.changed_at <= ?
            GROUP BY new_status
        ");

        $stmt->execute([$projectId, $moduleName, $startDate]);
        $startStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get status distribution at end date
        $stmt->execute([$projectId, $moduleName, $endDate]);
        $endStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_stats' => $startStats,
            'end_stats' => $endStats
        ];
    }
}
