<?php

namespace App\Modules;

use Database\Database;

/**
 * Base Audit Repository
 * 
 * Provides shared database operations for all audit module repositories.
 * Each module repository extends this and only needs to define MODULE_NAME
 * and override resultValue() for the display value.
 * 
 * Eliminates duplicated query logic across 7 repositories.
 */
abstract class BaseAuditRepository
{
    protected $db;

    /** Override in child class */
    protected const MODULE_NAME = '';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Extract the primary display value from result data.
     * Override in child to customize what's stored in result_value.
     */
    protected function resultValue(array $data): ?string
    {
        return null;
    }

    /**
     * Save audit result (INSERT or UPDATE on duplicate).
     */
    public function saveResult(int $pageId, array $data): ?int
    {
        $query = "INSERT INTO page_audit_results (
                    project_page_id, module_name, result_status, result_value, raw_data_json, checked_at
                  ) VALUES (
                    :project_page_id, :module_name, :result_status, :result_value, :raw_data_json, NOW()
                  )
                  ON DUPLICATE KEY UPDATE 
                    result_status = VALUES(result_status),
                    result_value = VALUES(result_value),
                    raw_data_json = VALUES(raw_data_json),
                    checked_at = NOW(),
                    updated_at = NOW()";

        return $this->db->insert($query, [
            'project_page_id' => $pageId,
            'module_name' => static::MODULE_NAME,
            'result_status' => $data['status'],
            'result_value' => $this->resultValue($data),
            'raw_data_json' => json_encode($data)
        ]);
    }

    /**
     * Get audit result for a single page.
     */
    public function getResultByPage(int $pageId): ?array
    {
        $result = $this->db->query(
            "SELECT * FROM page_audit_results 
             WHERE project_page_id = :page_id AND module_name = :module_name LIMIT 1",
            ['page_id' => $pageId, 'module_name' => static::MODULE_NAME]
        );

        if ($result && isset($result[0])) {
            $row = $result[0];
            $row['raw_data'] = json_decode($row['raw_data_json'], true);
            return $row;
        }
        return null;
    }

    /**
     * Get all results for a project.
     */
    public function getResultsByProject(int $projectId): array
    {
        $results = $this->db->query(
            "SELECT par.*, pp.original_url, pp.normalized_url 
             FROM page_audit_results par
             INNER JOIN project_pages pp ON par.project_page_id = pp.id
             WHERE pp.project_id = :project_id AND par.module_name = :module_name
             ORDER BY par.checked_at DESC",
            ['project_id' => $projectId, 'module_name' => static::MODULE_NAME]
        ) ?: [];

        foreach ($results as &$row) {
            $row['raw_data'] = json_decode($row['raw_data_json'], true);
        }
        return $results;
    }

    /**
     * Get statistics for a project.
     */
    public function getStats(int $projectId): array
    {
        $result = $this->db->query(
            "SELECT 
                COUNT(*) as total_audited,
                SUM(CASE WHEN par.result_status = 'PASS' THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN par.result_status = 'WARNING' THEN 1 ELSE 0 END) as warnings,
                SUM(CASE WHEN par.result_status = 'FAIL' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN par.result_status = 'ERROR' THEN 1 ELSE 0 END) as errors
             FROM page_audit_results par
             INNER JOIN project_pages pp ON par.project_page_id = pp.id
             WHERE pp.project_id = :project_id AND par.module_name = :module_name",
            ['project_id' => $projectId, 'module_name' => static::MODULE_NAME]
        );

        if ($result) {
            return [
                'total_audited' => (int) $result[0]['total_audited'],
                'passed' => (int) $result[0]['passed'],
                'warnings' => (int) $result[0]['warnings'],
                'failed' => (int) $result[0]['failed'],
                'errors' => (int) $result[0]['errors'],
            ];
        }

        return ['total_audited' => 0, 'passed' => 0, 'warnings' => 0, 'failed' => 0, 'errors' => 0];
    }

    /**
     * Delete result for a page.
     */
    public function delete(int $pageId): bool
    {
        return (bool) $this->db->execute(
            "DELETE FROM page_audit_results WHERE project_page_id = :page_id AND module_name = :module_name",
            ['page_id' => $pageId, 'module_name' => static::MODULE_NAME]
        );
    }

    /**
     * Delete all results for a project.
     */
    public function deleteByProject(int $projectId): bool
    {
        return (bool) $this->db->execute(
            "DELETE par FROM page_audit_results par
             INNER JOIN project_pages pp ON par.project_page_id = pp.id
             WHERE pp.project_id = :project_id AND par.module_name = :module_name",
            ['project_id' => $projectId, 'module_name' => static::MODULE_NAME]
        );
    }
}
