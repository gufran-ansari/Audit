<?php

namespace App\Modules\Reports;

use Database\Database;
use App\Services\ModuleRegistry;

/**
 * Report Generator
 * 
 * Assembles all data needed for a public report from existing audit results.
 * Never duplicates data — reads directly from page_audit_results and projects.
 */
class ReportGenerator
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Generate the complete report data payload for rendering.
     */
    public function generate(int $projectId): array
    {
        return [
            'hero' => $this->getHeroData($projectId),
            'overview' => $this->getOverviewCards($projectId),
            'modules' => $this->getModuleBreakdown($projectId),
            'pages' => $this->getDetailedResults($projectId),
            'summary' => $this->getSummary($projectId),
        ];
    }

    /**
     * Hero section: project name, domain, last audit, totals.
     */
    private function getHeroData(int $projectId): array
    {
        $project = $this->db->query(
            "SELECT project_name, primary_domain FROM projects WHERE id = :id",
            ['id' => $projectId]
        );

        $stats = $this->db->query(
            "SELECT COUNT(*) as total_urls,
                    COUNT(DISTINCT CASE WHEN par.id IS NOT NULL THEN pp.id END) as audited_pages,
                    MAX(par.checked_at) as last_audit_date
             FROM project_pages pp
             LEFT JOIN page_audit_results par ON par.project_page_id = pp.id
             WHERE pp.project_id = :pid AND pp.url_status = 'active'",
            ['pid' => $projectId]
        );

        return [
            'project_name' => $project ? $project[0]['project_name'] : '',
            'domain' => $project ? $project[0]['primary_domain'] : '',
            'total_urls' => $stats ? (int) $stats[0]['total_urls'] : 0,
            'audited_pages' => $stats ? (int) $stats[0]['audited_pages'] : 0,
            'last_audit_date' => $stats ? $stats[0]['last_audit_date'] : null,
        ];
    }

    /**
     * Overview cards: pass/fail/warning/error/not-audited counts.
     */
    private function getOverviewCards(int $projectId): array
    {
        // Count distinct pages per worst status
        $query = "SELECT 
                    COUNT(DISTINCT pp.id) as total,
                    COUNT(DISTINCT CASE WHEN worst.worst_status = 'PASS' THEN pp.id END) as passed,
                    COUNT(DISTINCT CASE WHEN worst.worst_status = 'FAIL' THEN pp.id END) as failed,
                    COUNT(DISTINCT CASE WHEN worst.worst_status = 'WARNING' THEN pp.id END) as warnings,
                    COUNT(DISTINCT CASE WHEN worst.worst_status = 'ERROR' THEN pp.id END) as errors,
                    COUNT(DISTINCT CASE WHEN worst.worst_status IS NULL THEN pp.id END) as not_audited
                  FROM project_pages pp
                  LEFT JOIN (
                      SELECT project_page_id,
                             CASE
                                 WHEN SUM(result_status = 'ERROR') > 0 THEN 'ERROR'
                                 WHEN SUM(result_status = 'FAIL') > 0 THEN 'FAIL'
                                 WHEN SUM(result_status = 'WARNING') > 0 THEN 'WARNING'
                                 WHEN SUM(result_status = 'PASS') > 0 THEN 'PASS'
                                 ELSE NULL
                             END as worst_status
                      FROM page_audit_results
                      GROUP BY project_page_id
                  ) worst ON worst.project_page_id = pp.id
                  WHERE pp.project_id = :pid AND pp.url_status = 'active'";

        $result = $this->db->query($query, ['pid' => $projectId]);
        $r = $result ? $result[0] : [];

        return [
            'total' => (int) ($r['total'] ?? 0),
            'passed' => (int) ($r['passed'] ?? 0),
            'failed' => (int) ($r['failed'] ?? 0),
            'warnings' => (int) ($r['warnings'] ?? 0),
            'errors' => (int) ($r['errors'] ?? 0),
            'not_audited' => (int) ($r['not_audited'] ?? 0),
        ];
    }

    /**
     * Per-module issue breakdown.
     */
    private function getModuleBreakdown(int $projectId): array
    {
        $query = "SELECT par.module_name,
                         COUNT(*) as total,
                         SUM(result_status = 'PASS') as pass_count,
                         SUM(result_status = 'WARNING') as warning_count,
                         SUM(result_status = 'FAIL') as fail_count,
                         SUM(result_status = 'ERROR') as error_count
                  FROM page_audit_results par
                  INNER JOIN project_pages pp ON pp.id = par.project_page_id
                  WHERE pp.project_id = :pid AND pp.url_status = 'active'
                  GROUP BY par.module_name";

        $rows = $this->db->query($query, ['pid' => $projectId]) ?: [];
        $modules = [];

        foreach ($rows as $row) {
            $reg = ModuleRegistry::getByModuleName($row['module_name']);
            $issues = (int) $row['warning_count'] + (int) $row['fail_count'] + (int) $row['error_count'];
            $modules[] = [
                'module_name' => $row['module_name'],
                'label' => $reg ? $reg['label'] : $row['module_name'],
                'icon' => $reg ? $reg['icon'] : 'bi-square',
                'total' => (int) $row['total'],
                'pass' => (int) $row['pass_count'],
                'warning' => (int) $row['warning_count'],
                'fail' => (int) $row['fail_count'],
                'error' => (int) $row['error_count'],
                'issues' => $issues,
            ];
        }

        return $modules;
    }

    /**
     * Detailed per-page results for the report table.
     * Includes result_value and checked_at for expanded row details.
     */
    private function getDetailedResults(int $projectId, int $limit = 500): array
    {
        $query = "SELECT pp.id, pp.original_url,
                    h1.result_status as h1_status, h1.result_value as h1_value, h1.checked_at as h1_checked,
                    meta.result_status as meta_status, meta.result_value as meta_value, meta.checked_at as meta_checked,
                    noindex.result_status as noindex_status, noindex.result_value as noindex_value, noindex.checked_at as noindex_checked,
                    faq.result_status as faq_status, faq.result_value as faq_value, faq.checked_at as faq_checked,
                    lorem.result_status as lorem_status, lorem.result_value as lorem_value, lorem.checked_at as lorem_checked,
                    staging.result_status as staging_status, staging.result_value as staging_value, staging.checked_at as staging_checked,
                    word.result_status as word_status, word.result_value as word_value, word.checked_at as word_checked,
                    GREATEST(
                        COALESCE(h1.checked_at, '1970-01-01'),
                        COALESCE(meta.checked_at, '1970-01-01'),
                        COALESCE(noindex.checked_at, '1970-01-01'),
                        COALESCE(faq.checked_at, '1970-01-01'),
                        COALESCE(lorem.checked_at, '1970-01-01'),
                        COALESCE(staging.checked_at, '1970-01-01'),
                        COALESCE(word.checked_at, '1970-01-01')
                    ) as last_audited
                  FROM project_pages pp
                  LEFT JOIN page_audit_results h1 ON pp.id = h1.project_page_id AND h1.module_name = 'h1_audit'
                  LEFT JOIN page_audit_results meta ON pp.id = meta.project_page_id AND meta.module_name = 'meta_audit'
                  LEFT JOIN page_audit_results noindex ON pp.id = noindex.project_page_id AND noindex.module_name = 'noindex_audit'
                  LEFT JOIN page_audit_results faq ON pp.id = faq.project_page_id AND faq.module_name = 'faq_schema_audit'
                  LEFT JOIN page_audit_results lorem ON pp.id = lorem.project_page_id AND lorem.module_name = 'lorem_audit'
                  LEFT JOIN page_audit_results staging ON pp.id = staging.project_page_id AND staging.module_name = 'staging_audit'
                  LEFT JOIN page_audit_results word ON pp.id = word.project_page_id AND word.module_name = 'word_occurrence_audit'
                  WHERE pp.project_id = :pid AND pp.url_status = 'active'
                  ORDER BY pp.id ASC
                  LIMIT :lim";

        return $this->db->query($query, ['pid' => $projectId, 'lim' => $limit]) ?: [];
    }

    /**
     * Get summary insights for the report.
     */
    public function getSummary(int $projectId): array
    {
        // Top problems: modules with most issues
        $topProblems = $this->db->query(
            "SELECT par.module_name, COUNT(*) as issue_count
             FROM page_audit_results par
             INNER JOIN project_pages pp ON pp.id = par.project_page_id
             WHERE pp.project_id = :pid AND pp.url_status = 'active'
               AND par.result_status IN ('FAIL', 'ERROR')
             GROUP BY par.module_name
             ORDER BY issue_count DESC
             LIMIT 5",
            ['pid' => $projectId]
        ) ?: [];

        // Most common issues
        $commonIssues = $this->db->query(
            "SELECT par.module_name, par.result_status, COUNT(*) as cnt
             FROM page_audit_results par
             INNER JOIN project_pages pp ON pp.id = par.project_page_id
             WHERE pp.project_id = :pid AND pp.url_status = 'active'
               AND par.result_status IN ('FAIL', 'WARNING', 'ERROR')
             GROUP BY par.module_name, par.result_status
             ORDER BY cnt DESC
             LIMIT 5",
            ['pid' => $projectId]
        ) ?: [];

        // Pages needing attention: pages with most failures
        $pagesNeedingAttention = $this->db->query(
            "SELECT pp.original_url, COUNT(*) as fail_count
             FROM page_audit_results par
             INNER JOIN project_pages pp ON pp.id = par.project_page_id
             WHERE pp.project_id = :pid AND pp.url_status = 'active'
               AND par.result_status IN ('FAIL', 'ERROR')
             GROUP BY pp.id, pp.original_url
             ORDER BY fail_count DESC
             LIMIT 5",
            ['pid' => $projectId]
        ) ?: [];

        // Overall health score (percentage of passes)
        $healthData = $this->db->query(
            "SELECT 
                COUNT(*) as total,
                SUM(result_status = 'PASS') as passed
             FROM page_audit_results par
             INNER JOIN project_pages pp ON pp.id = par.project_page_id
             WHERE pp.project_id = :pid AND pp.url_status = 'active'",
            ['pid' => $projectId]
        );

        $total = $healthData ? (int) $healthData[0]['total'] : 0;
        $passed = $healthData ? (int) $healthData[0]['passed'] : 0;
        $healthScore = $total > 0 ? round(($passed / $total) * 100) : 0;

        // Enrich top problems with labels
        foreach ($topProblems as &$problem) {
            $reg = ModuleRegistry::getByModuleName($problem['module_name']);
            $problem['label'] = $reg ? $reg['label'] : $problem['module_name'];
            $problem['icon'] = $reg ? $reg['icon'] : 'bi-square';
        }

        // Enrich common issues with labels
        foreach ($commonIssues as &$issue) {
            $reg = ModuleRegistry::getByModuleName($issue['module_name']);
            $issue['label'] = $reg ? $reg['label'] : $issue['module_name'];
        }

        return [
            'health_score' => $healthScore,
            'total_checks' => $total,
            'total_passed' => $passed,
            'top_problems' => $topProblems,
            'common_issues' => $commonIssues,
            'pages_needing_attention' => $pagesNeedingAttention,
        ];
    }
}
