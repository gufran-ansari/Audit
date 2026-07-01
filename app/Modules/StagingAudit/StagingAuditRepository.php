<?php

namespace App\Modules\StagingAudit;

use Database\Database;

/**
 * Staging Audit Repository
 * 
 * Handles database operations for staging URL audit results
 */
class StagingAuditRepository
{
    private $db;
    private const MODULE_NAME = 'staging_audit';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Save audit result
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

        // Use occurrence count summary as primary result value
        $occurrences = $data['total_occurrences'] ?? 0;
        $resultValue = $occurrences > 0
            ? "{$occurrences} staging reference(s)"
            : 'No staging references';

        return $this->db->insert($query, [
            'project_page_id' => $pageId,
            'module_name' => self::MODULE_NAME,
            'result_status' => $data['status'],
            'result_value' => $resultValue,
            'raw_data_json' => json_encode($data)
        ]);
    }

    /**
     * Get audit result for a page
     */
    public function getResultByPage(int $pageId): ?array
    {
        $query = "SELECT * FROM page_audit_results 
                  WHERE project_page_id = :page_id AND module_name = :module_name 
                  LIMIT 1";

        $result = $this->db->query($query, [
            'page_id' => $pageId,
            'module_name' => self::MODULE_NAME
        ]);

        if ($result && isset($result[0])) {
            $row = $result[0];
            $row['raw_data'] = json_decode($row['raw_data_json'], true);
            return $row;
        }

        return null;
    }

    /**
     * Get all audit results for a project
     */
    public function getResultsByProject(int $projectId): array
    {
        $query = "SELECT par.*, pp.original_url, pp.normalized_url 
                  FROM page_audit_results par
                  INNER JOIN project_pages pp ON par.project_page_id = pp.id
                  WHERE pp.project_id = :project_id AND par.module_name = :module_name
                  ORDER BY par.checked_at DESC";

        $results = $this->db->query($query, [
            'project_id' => $projectId,
            'module_name' => self::MODULE_NAME
        ]) ?: [];

        foreach ($results as &$result) {
            $result['raw_data'] = json_decode($result['raw_data_json'], true);
        }

        return $results;
    }

    /**
     * Get audit statistics for a project
     */
    public function getStats(int $projectId): array
    {
        $query = "SELECT 
                    COUNT(*) as total_audited,
                    SUM(CASE WHEN par.result_status = 'PASS' THEN 1 ELSE 0 END) as passed,
                    SUM(CASE WHEN par.result_status = 'WARNING' THEN 1 ELSE 0 END) as warnings,
                    SUM(CASE WHEN par.result_status = 'FAIL' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN par.result_status = 'ERROR' THEN 1 ELSE 0 END) as errors
                  FROM page_audit_results par
                  INNER JOIN project_pages pp ON par.project_page_id = pp.id
                  WHERE pp.project_id = :project_id AND par.module_name = :module_name";

        $result = $this->db->query($query, [
            'project_id' => $projectId,
            'module_name' => self::MODULE_NAME
        ]);

        if ($result) {
            return [
                'total_audited' => (int)$result[0]['total_audited'],
                'passed' => (int)$result[0]['passed'],
                'warnings' => (int)$result[0]['warnings'],
                'failed' => (int)$result[0]['failed'],
                'errors' => (int)$result[0]['errors']
            ];
        }

        return [
            'total_audited' => 0,
            'passed' => 0,
            'warnings' => 0,
            'failed' => 0,
            'errors' => 0
        ];
    }

    /**
     * Delete audit result
     */
    public function delete(int $pageId): bool
    {
        $query = "DELETE FROM page_audit_results 
                  WHERE project_page_id = :page_id AND module_name = :module_name";

        return $this->db->execute($query, [
            'page_id' => $pageId,
            'module_name' => self::MODULE_NAME
        ]);
    }

    /**
     * Delete all audit results for a project
     */
    public function deleteByProject(int $projectId): bool
    {
        $query = "DELETE par FROM page_audit_results par
                  INNER JOIN project_pages pp ON par.project_page_id = pp.id
                  WHERE pp.project_id = :project_id AND par.module_name = :module_name";

        return $this->db->execute($query, [
            'project_id' => $projectId,
            'module_name' => self::MODULE_NAME
        ]);
    }
}
