<?php

namespace App\Services;

use Database\Database;

/**
 * Audit Summary Service
 * 
 * Produces project-level and module-level audit summaries for the
 * workspace dashboard. Optimized with aggregate queries to remain
 * performant on large URL collections.
 */
class AuditSummaryService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get the overall project summary:
     * total pages, audited pages, and status distribution.
     */
    public function getProjectSummary(int $projectId): array
    {
        // Total active pages
        $totalQuery = "SELECT COUNT(*) as total 
                       FROM project_pages 
                       WHERE project_id = :project_id AND url_status = 'active'";
        $totalResult = $this->db->query($totalQuery, ['project_id' => $projectId]);
        $totalPages = $totalResult ? (int) $totalResult[0]['total'] : 0;

        // Pages that have at least one audit result
        $auditedQuery = "SELECT COUNT(DISTINCT pp.id) as audited
                         FROM project_pages pp
                         INNER JOIN page_audit_results par ON par.project_page_id = pp.id
                         WHERE pp.project_id = :project_id AND pp.url_status = 'active'";
        $auditedResult = $this->db->query($auditedQuery, ['project_id' => $projectId]);
        $auditedPages = $auditedResult ? (int) $auditedResult[0]['audited'] : 0;

        // Status distribution across all module results
        $statusQuery = "SELECT 
                          par.result_status as status,
                          COUNT(*) as count
                        FROM page_audit_results par
                        INNER JOIN project_pages pp ON pp.id = par.project_page_id
                        WHERE pp.project_id = :project_id AND pp.url_status = 'active'
                        GROUP BY par.result_status";
        $statusRows = $this->db->query($statusQuery, ['project_id' => $projectId]) ?: [];

        $distribution = [
            'PASS' => 0,
            'WARNING' => 0,
            'FAIL' => 0,
            'ERROR' => 0,
            'NOT_RUN' => 0,
        ];

        foreach ($statusRows as $row) {
            $status = $row['status'];
            if (isset($distribution[$status])) {
                $distribution[$status] = (int) $row['count'];
            }
        }

        return [
            'total_pages' => $totalPages,
            'audited_pages' => $auditedPages,
            'not_audited_pages' => max(0, $totalPages - $auditedPages),
            'passed' => $distribution['PASS'],
            'warnings' => $distribution['WARNING'],
            'failed' => $distribution['FAIL'],
            'errors' => $distribution['ERROR'],
            'last_audit_date' => $this->getLastAuditDate($projectId),
        ];
    }

    /**
     * Get per-module summary (issue counts by module).
     */
    public function getModuleSummary(int $projectId): array
    {
        $query = "SELECT 
                    par.module_name,
                    COUNT(*) as total,
                    SUM(CASE WHEN par.result_status = 'PASS' THEN 1 ELSE 0 END) as pass_count,
                    SUM(CASE WHEN par.result_status = 'WARNING' THEN 1 ELSE 0 END) as warning_count,
                    SUM(CASE WHEN par.result_status = 'FAIL' THEN 1 ELSE 0 END) as fail_count,
                    SUM(CASE WHEN par.result_status = 'ERROR' THEN 1 ELSE 0 END) as error_count,
                    MAX(par.checked_at) as last_checked
                  FROM page_audit_results par
                  INNER JOIN project_pages pp ON pp.id = par.project_page_id
                  WHERE pp.project_id = :project_id AND pp.url_status = 'active'
                  GROUP BY par.module_name";

        $rows = $this->db->query($query, ['project_id' => $projectId]) ?: [];

        $summary = [];

        foreach ($rows as $row) {
            $moduleName = $row['module_name'];
            $module = ModuleRegistry::getByModuleName($moduleName);

            // "Issues" = WARNING + FAIL + ERROR
            $issues = (int) $row['warning_count'] + (int) $row['fail_count'] + (int) $row['error_count'];

            $summary[$moduleName] = [
                'module_name' => $moduleName,
                'label' => $module['label'] ?? $moduleName,
                'icon' => $module['icon'] ?? 'bi-square',
                'total' => (int) $row['total'],
                'pass' => (int) $row['pass_count'],
                'warning' => (int) $row['warning_count'],
                'fail' => (int) $row['fail_count'],
                'error' => (int) $row['error_count'],
                'issues' => $issues,
                'last_checked' => $row['last_checked'],
            ];
        }

        // Ensure all registered modules appear, even with zero results
        foreach (ModuleRegistry::all() as $module) {
            $name = $module['module_name'];
            if (!isset($summary[$name])) {
                $summary[$name] = [
                    'module_name' => $name,
                    'label' => $module['label'],
                    'icon' => $module['icon'],
                    'total' => 0,
                    'pass' => 0,
                    'warning' => 0,
                    'fail' => 0,
                    'error' => 0,
                    'issues' => 0,
                    'last_checked' => null,
                ];
            }
        }

        return array_values($summary);
    }

    /**
     * Get a combined dashboard payload.
     */
    public function getDashboard(int $projectId): array
    {
        return [
            'project' => $this->getProjectSummary($projectId),
            'modules' => $this->getModuleSummary($projectId),
        ];
    }

    /**
     * Get the most recent audit timestamp for a project.
     */
    private function getLastAuditDate(int $projectId): ?string
    {
        $query = "SELECT MAX(par.checked_at) as last_date
                  FROM page_audit_results par
                  INNER JOIN project_pages pp ON pp.id = par.project_page_id
                  WHERE pp.project_id = :project_id";

        $result = $this->db->query($query, ['project_id' => $projectId]);

        return $result && $result[0]['last_date'] ? $result[0]['last_date'] : null;
    }
}
