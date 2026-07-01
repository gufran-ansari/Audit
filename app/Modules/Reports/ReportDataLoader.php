<?php

namespace App\Modules\Reports;

use Database\Database;
use App\Services\CacheService;
use App\Services\ModuleRegistry;

/**
 * Report Data Loader (Performance Optimized)
 * 
 * Provides paginated report data loading for large datasets.
 * Uses caching for overview/summary data and server-side pagination
 * for the detailed results table.
 * 
 * Designed for reports with thousands of pages.
 */
class ReportDataLoader
{
    private $db;
    private CacheService $cache;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cache = CacheService::getInstance();
    }

    /**
     * Get report data with server-side pagination.
     * Used by the public report API for large datasets.
     */
    public function loadPaginated(int $projectId, int $page = 1, int $perPage = 50): array
    {
        $cacheKey = "report_overview_{$projectId}";

        // Cache overview data (refreshes every 2 minutes)
        $overview = $this->cache->remember($cacheKey, 120, function () use ($projectId) {
            $generator = new ReportGenerator();
            return [
                'hero' => $this->getHeroCached($projectId),
                'overview' => $this->getOverviewCached($projectId),
                'modules' => $this->getModulesCached($projectId),
                'summary' => $generator->getSummary($projectId),
            ];
        });

        // Pages are always fresh (paginated, not cached)
        $offset = ($page - 1) * $perPage;
        $pages = $this->getPaginatedResults($projectId, $offset, $perPage);
        $totalPages = $this->getPageCount($projectId);

        return array_merge($overview, [
            'pages' => $pages,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalPages,
                'total_pages' => (int) ceil($totalPages / $perPage),
            ],
        ]);
    }

    /**
     * Invalidate report cache for a project.
     */
    public function invalidate(int $projectId): void
    {
        $this->cache->forget("report_overview_{$projectId}");
        $this->cache->forget("report_hero_{$projectId}");
        $this->cache->forget("report_overview_cards_{$projectId}");
        $this->cache->forget("report_modules_{$projectId}");
        $this->cache->forget("report_page_count_{$projectId}");
    }

    private function getHeroCached(int $projectId): array
    {
        return $this->cache->remember("report_hero_{$projectId}", 120, function () use ($projectId) {
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
        });
    }

    private function getOverviewCached(int $projectId): array
    {
        return $this->cache->remember("report_overview_cards_{$projectId}", 120, function () use ($projectId) {
            $generator = new ReportGenerator();
            // Use reflection or just call the method via generate
            $data = $generator->generate($projectId);
            return $data['overview'];
        });
    }

    private function getModulesCached(int $projectId): array
    {
        return $this->cache->remember("report_modules_{$projectId}", 120, function () use ($projectId) {
            $generator = new ReportGenerator();
            $data = $generator->generate($projectId);
            return $data['modules'];
        });
    }

    private function getPageCount(int $projectId): int
    {
        return $this->cache->remember("report_page_count_{$projectId}", 60, function () use ($projectId) {
            $result = $this->db->query(
                "SELECT COUNT(*) as cnt FROM project_pages WHERE project_id = :pid AND url_status = 'active'",
                ['pid' => $projectId]
            );
            return $result ? (int) $result[0]['cnt'] : 0;
        });
    }

    private function getPaginatedResults(int $projectId, int $offset, int $limit): array
    {
        $query = "SELECT pp.id, pp.original_url,
                    h1.result_status as h1_status, h1.result_value as h1_value, h1.checked_at as h1_checked,
                    meta.result_status as meta_status, meta.result_value as meta_value, meta.checked_at as meta_checked,
                    noindex.result_status as noindex_status, noindex.result_value as noindex_value, noindex.checked_at as noindex_checked,
                    faq.result_status as faq_status, faq.result_value as faq_value, faq.checked_at as faq_checked,
                    lorem.result_status as lorem_status, lorem.result_value as lorem_value, lorem.checked_at as lorem_checked,
                    staging.result_status as staging_status, staging.result_value as staging_value, staging.checked_at as staging_checked,
                    word.result_status as word_status, word.result_value as word_value, word.checked_at as word_checked
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
                  LIMIT :lim OFFSET :off";

        return $this->db->query($query, ['pid' => $projectId, 'lim' => $limit, 'off' => $offset]) ?: [];
    }
}
