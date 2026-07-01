<?php

namespace App\Modules\Pages;

use Database\Database;
use PDO;

/**
 * Page Repository
 * 
 * Handles all database operations for pages/URLs
 */
class PageRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find page by ID
     */
    public function findById(int $id): ?array
    {
        $query = "SELECT * FROM project_pages WHERE id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $id]);
        return $result ? $result[0] : null;
    }

    /**
     * Find page by URL hash
     */
    public function findByUrlHash(int $projectId, string $urlHash): ?array
    {
        $query = "SELECT * FROM project_pages 
                  WHERE project_id = :project_id AND url_hash = :url_hash 
                  LIMIT 1";
        $result = $this->db->query($query, [
            'project_id' => $projectId,
            'url_hash' => $urlHash
        ]);
        return $result ? $result[0] : null;
    }

    /**
     * Check if page exists by URL hash
     */
    public function exists(int $projectId, string $urlHash): bool
    {
        $query = "SELECT COUNT(*) as count FROM project_pages 
                  WHERE project_id = :project_id AND url_hash = :url_hash";
        $result = $this->db->query($query, [
            'project_id' => $projectId,
            'url_hash' => $urlHash
        ]);
        return $result && $result[0]['count'] > 0;
    }

    /**
     * Get all pages for a project with pagination
     */
    public function getByProject(int $projectId, int $page = 1, int $perPage = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = ['project_id' => $projectId];
        
        $query = "SELECT pp.*, ps.sitemap_url,
                    h1.result_status as h1_status, h1.raw_data_json as h1_data, h1.checked_at as h1_checked_at,
                    meta.result_status as meta_status, meta.raw_data_json as meta_data, meta.checked_at as meta_checked_at,
                    noindex.result_status as noindex_status, noindex.raw_data_json as noindex_data, noindex.checked_at as noindex_checked_at,
                    faq.result_status as faq_status, faq.raw_data_json as faq_data, faq.checked_at as faq_checked_at,
                    lorem.result_status as lorem_status, lorem.raw_data_json as lorem_data, lorem.checked_at as lorem_checked_at,
                    staging.result_status as staging_status, staging.raw_data_json as staging_data, staging.checked_at as staging_checked_at,
                    word.result_status as word_status, word.raw_data_json as word_data, word.checked_at as word_checked_at
                  FROM project_pages pp
                  LEFT JOIN project_sitemaps ps ON pp.sitemap_id = ps.id
                  LEFT JOIN page_audit_results h1 ON pp.id = h1.project_page_id AND h1.module_name = 'h1_audit'
                  LEFT JOIN page_audit_results meta ON pp.id = meta.project_page_id AND meta.module_name = 'meta_audit'
                  LEFT JOIN page_audit_results noindex ON pp.id = noindex.project_page_id AND noindex.module_name = 'noindex_audit'
                  LEFT JOIN page_audit_results faq ON pp.id = faq.project_page_id AND faq.module_name = 'faq_schema_audit'
                  LEFT JOIN page_audit_results lorem ON pp.id = lorem.project_page_id AND lorem.module_name = 'lorem_audit'
                  LEFT JOIN page_audit_results staging ON pp.id = staging.project_page_id AND staging.module_name = 'staging_audit'
                  LEFT JOIN page_audit_results word ON pp.id = word.project_page_id AND word.module_name = 'word_occurrence_audit'
                  WHERE pp.project_id = :project_id";
        
        // Filter by sitemap
        if (!empty($filters['sitemap_id'])) {
            $query .= " AND pp.sitemap_id = :sitemap_id";
            $params['sitemap_id'] = $filters['sitemap_id'];
        }
        
        // Filter by URL status
        if (!empty($filters['url_status'])) {
            $query .= " AND pp.url_status = :url_status";
            $params['url_status'] = $filters['url_status'];
        }
        
        // Search by URL
        if (!empty($filters['search'])) {
            $query .= " AND (pp.original_url LIKE :search OR pp.normalized_url LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        // Filter by audit status
        if (!empty($filters['audit_status'])) {
            $status = $filters['audit_status'];
            $query .= " AND (h1.result_status = :audit_status 
                         OR meta.result_status = :audit_status 
                         OR noindex.result_status = :audit_status 
                         OR faq.result_status = :audit_status
                         OR lorem.result_status = :audit_status
                         OR staging.result_status = :audit_status)";
            $params['audit_status'] = $status;
        }
        
        // Filter by specific module status
        if (!empty($filters['module_name']) && !empty($filters['module_status'])) {
            $moduleAlias = str_replace('_audit', '', $filters['module_name']);
            $query .= " AND {$moduleAlias}.result_status = :module_status";
            $params['module_status'] = $filters['module_status'];
        }
        
        // Filter by audit date range
        if (!empty($filters['audit_date_from'])) {
            $query .= " AND (h1.checked_at >= :date_from 
                         OR meta.checked_at >= :date_from 
                         OR noindex.checked_at >= :date_from 
                         OR faq.checked_at >= :date_from
                         OR lorem.checked_at >= :date_from
                         OR staging.checked_at >= :date_from)";
            $params['date_from'] = $filters['audit_date_from'];
        }
        
        if (!empty($filters['audit_date_to'])) {
            $query .= " AND (h1.checked_at <= :date_to 
                         OR meta.checked_at <= :date_to 
                         OR noindex.checked_at <= :date_to 
                         OR faq.checked_at <= :date_to
                         OR lorem.checked_at <= :date_to
                         OR staging.checked_at <= :date_to)";
            $params['date_to'] = $filters['audit_date_to'];
        }
        
        $query .= " ORDER BY pp.created_at DESC
                   LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        
        return $this->db->query($query, $params) ?: [];
    }

    /**
     * Get pages by sitemap
     */
    public function getBySitemap(int $sitemapId): array
    {
        $query = "SELECT * FROM project_pages 
                  WHERE sitemap_id = :sitemap_id 
                  ORDER BY created_at DESC";
        return $this->db->query($query, ['sitemap_id' => $sitemapId]) ?: [];
    }

    /**
     * Get pages by status
     */
    public function getByStatus(int $projectId, string $status): array
    {
        $query = "SELECT * FROM project_pages 
                  WHERE project_id = :project_id AND page_status = :status
                  ORDER BY created_at DESC";
        return $this->db->query($query, [
            'project_id' => $projectId,
            'status' => $status
        ]) ?: [];
    }

    /**
     * Create new page entry
     */
    public function create(array $data): ?int
    {
        $query = "INSERT INTO project_pages (
                    project_id, sitemap_id, original_url, normalized_url, 
                    url_hash, page_status
                  ) VALUES (
                    :project_id, :sitemap_id, :original_url, :normalized_url,
                    :url_hash, :page_status
                  )";
        
        return $this->db->insert($query, $data);
    }

    /**
     * Batch insert pages (for performance)
     */
    public function batchInsert(array $pages): int
    {
        if (empty($pages)) {
            return 0;
        }

        $inserted = 0;
        
        // Use transaction for better performance
        $this->db->getPdo()->beginTransaction();
        
        try {
            $query = "INSERT INTO project_pages (
                        project_id, sitemap_id, original_url, normalized_url, 
                        url_hash, page_status
                      ) VALUES (
                        :project_id, :sitemap_id, :original_url, :normalized_url,
                        :url_hash, :page_status
                      )
                      ON DUPLICATE KEY UPDATE 
                        last_seen_at = NOW(),
                        updated_at = NOW()";
            
            foreach ($pages as $page) {
                $result = $this->db->insert($query, $page);
                if ($result) {
                    $inserted++;
                }
            }
            
            $this->db->getPdo()->commit();
        } catch (\Exception $e) {
            $this->db->getPdo()->rollBack();
            logMessage("Batch insert failed: " . $e->getMessage(), 'error');
            throw $e;
        }
        
        return $inserted;
    }

    /**
     * Update page
     */
    public function update(int $id, array $data): bool
    {
        $setClauses = [];
        $params = ['id' => $id];
        
        foreach ($data as $key => $value) {
            $setClauses[] = "$key = :$key";
            $params[$key] = $value;
        }
        
        $query = "UPDATE project_pages SET " . implode(', ', $setClauses) . " WHERE id = :id";
        
        return $this->db->execute($query, $params);
    }

    /**
     * Update page status
     */
    public function updateStatus(int $id, string $status): bool
    {
        $query = "UPDATE project_pages SET page_status = :status WHERE id = :id";
        return $this->db->execute($query, [
            'id' => $id,
            'status' => $status
        ]);
    }

    /**
     * Update last seen timestamp
     */
    public function updateLastSeen(int $id): bool
    {
        $query = "UPDATE project_pages SET last_seen_at = NOW() WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }

    /**
     * Delete page
     */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM project_pages WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }

    /**
     * Delete all pages for a project
     */
    public function deleteByProject(int $projectId): bool
    {
        $query = "DELETE FROM project_pages WHERE project_id = :project_id";
        return $this->db->execute($query, ['project_id' => $projectId]);
    }

    /**
     * Delete all pages from a sitemap
     */
    public function deleteBySitemap(int $sitemapId): bool
    {
        $query = "DELETE FROM project_pages WHERE sitemap_id = :sitemap_id";
        return $this->db->execute($query, ['sitemap_id' => $sitemapId]);
    }

    /**
     * Get page statistics for a project
     */
    public function getStats(int $projectId): array
    {
        $query = "SELECT 
                    COUNT(*) as total_pages,
                    COUNT(DISTINCT url_hash) as unique_pages,
                    SUM(CASE WHEN page_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN page_status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN page_status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN page_status = 'failed' THEN 1 ELSE 0 END) as failed
                  FROM project_pages 
                  WHERE project_id = :project_id";
        
        $result = $this->db->query($query, ['project_id' => $projectId]);
        
        if ($result) {
            return [
                'total_pages' => (int)$result[0]['total_pages'],
                'unique_pages' => (int)$result[0]['unique_pages'],
                'pending' => (int)$result[0]['pending'],
                'processing' => (int)$result[0]['processing'],
                'completed' => (int)$result[0]['completed'],
                'failed' => (int)$result[0]['failed']
            ];
        }
        
        return [
            'total_pages' => 0,
            'unique_pages' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];
    }

    /**
     * Get total count for pagination
     */
    public function getTotalCount(int $projectId, array $filters = []): int
    {
        $params = ['project_id' => $projectId];
        
        $query = "SELECT COUNT(DISTINCT pp.id) as count 
                  FROM project_pages pp
                  LEFT JOIN page_audit_results h1 ON pp.id = h1.project_page_id AND h1.module_name = 'h1_audit'
                  LEFT JOIN page_audit_results meta ON pp.id = meta.project_page_id AND meta.module_name = 'meta_audit'
                  LEFT JOIN page_audit_results noindex ON pp.id = noindex.project_page_id AND noindex.module_name = 'noindex_audit'
                  LEFT JOIN page_audit_results faq ON pp.id = faq.project_page_id AND faq.module_name = 'faq_schema_audit'
                  LEFT JOIN page_audit_results lorem ON pp.id = lorem.project_page_id AND lorem.module_name = 'lorem_audit'
                  LEFT JOIN page_audit_results staging ON pp.id = staging.project_page_id AND staging.module_name = 'staging_audit'
                  WHERE pp.project_id = :project_id";
        
        // Filter by sitemap
        if (!empty($filters['sitemap_id'])) {
            $query .= " AND pp.sitemap_id = :sitemap_id";
            $params['sitemap_id'] = $filters['sitemap_id'];
        }
        
        // Filter by URL status
        if (!empty($filters['url_status'])) {
            $query .= " AND pp.url_status = :url_status";
            $params['url_status'] = $filters['url_status'];
        }
        
        // Search by URL
        if (!empty($filters['search'])) {
            $query .= " AND (pp.original_url LIKE :search OR pp.normalized_url LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        // Filter by audit status
        if (!empty($filters['audit_status'])) {
            $status = $filters['audit_status'];
            $query .= " AND (h1.result_status = :audit_status 
                         OR meta.result_status = :audit_status 
                         OR noindex.result_status = :audit_status 
                         OR faq.result_status = :audit_status
                         OR lorem.result_status = :audit_status
                         OR staging.result_status = :audit_status)";
            $params['audit_status'] = $status;
        }
        
        // Filter by specific module status
        if (!empty($filters['module_name']) && !empty($filters['module_status'])) {
            $moduleAlias = str_replace('_audit', '', $filters['module_name']);
            $query .= " AND {$moduleAlias}.result_status = :module_status";
            $params['module_status'] = $filters['module_status'];
        }
        
        $result = $this->db->query($query, $params);
        return $result ? (int)$result[0]['count'] : 0;
    }

    /**
     * Search pages by URL
     */
    public function search(int $projectId, string $searchTerm, int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;
        
        $query = "SELECT pp.*, ps.sitemap_url 
                  FROM project_pages pp
                  LEFT JOIN project_sitemaps ps ON pp.sitemap_id = ps.id
                  WHERE pp.project_id = :project_id 
                  AND (pp.original_url LIKE :search OR pp.normalized_url LIKE :search)
                  ORDER BY pp.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        return $this->db->query($query, [
            'project_id' => $projectId,
            'search' => '%' . $searchTerm . '%',
            'limit' => $perPage,
            'offset' => $offset
        ]) ?: [];
    }

    /**
     * Get pages by IDs (for bulk operations)
     */
    public function getByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "SELECT pp.*, ps.sitemap_url,
                    h1.result_status as h1_status,
                    meta.result_status as meta_status,
                    noindex.result_status as noindex_status,
                    faq.result_status as faq_status,
                    lorem.result_status as lorem_status,
                    staging.result_status as staging_status,
                    word.result_status as word_status
                  FROM project_pages pp
                  LEFT JOIN project_sitemaps ps ON pp.sitemap_id = ps.id
                  LEFT JOIN page_audit_results h1 ON pp.id = h1.project_page_id AND h1.module_name = 'h1_audit'
                  LEFT JOIN page_audit_results meta ON pp.id = meta.project_page_id AND meta.module_name = 'meta_audit'
                  LEFT JOIN page_audit_results noindex ON pp.id = noindex.project_page_id AND noindex.module_name = 'noindex_audit'
                  LEFT JOIN page_audit_results faq ON pp.id = faq.project_page_id AND faq.module_name = 'faq_schema_audit'
                  LEFT JOIN page_audit_results lorem ON pp.id = lorem.project_page_id AND lorem.module_name = 'lorem_audit'
                  LEFT JOIN page_audit_results staging ON pp.id = staging.project_page_id AND staging.module_name = 'staging_audit'
                  LEFT JOIN page_audit_results word ON pp.id = word.project_page_id AND word.module_name = 'word_occurrence_audit'
                  WHERE pp.id IN ($placeholders)";

        $stmt = $this->db->getPdo()->prepare($query);
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get audit summary statistics for a project
     */
    public function getAuditSummary(int $projectId): array
    {
        $query = "SELECT 
                    COUNT(DISTINCT pp.id) as total_pages,
                    COUNT(DISTINCT CASE WHEN par.result_status = 'PASS' THEN pp.id END) as passed_pages,
                    COUNT(DISTINCT CASE WHEN par.result_status = 'WARNING' THEN pp.id END) as warning_pages,
                    COUNT(DISTINCT CASE WHEN par.result_status = 'FAIL' THEN pp.id END) as failed_pages,
                    COUNT(DISTINCT CASE WHEN par.result_status = 'ERROR' THEN pp.id END) as error_pages,
                    COUNT(DISTINCT CASE WHEN par.id IS NULL THEN pp.id END) as not_audited_pages,
                    MAX(par.checked_at) as last_audit_date
                  FROM project_pages pp
                  LEFT JOIN page_audit_results par ON pp.id = par.project_page_id
                  WHERE pp.project_id = :project_id AND pp.url_status = 'active'";
        
        $result = $this->db->query($query, ['project_id' => $projectId]);
        
        if ($result) {
            return [
                'total_pages' => (int)$result[0]['total_pages'],
                'passed_pages' => (int)$result[0]['passed_pages'],
                'warning_pages' => (int)$result[0]['warning_pages'],
                'failed_pages' => (int)$result[0]['failed_pages'],
                'error_pages' => (int)$result[0]['error_pages'],
                'not_audited_pages' => (int)$result[0]['not_audited_pages'],
                'last_audit_date' => $result[0]['last_audit_date']
            ];
        }
        
        return [
            'total_pages' => 0,
            'passed_pages' => 0,
            'warning_pages' => 0,
            'failed_pages' => 0,
            'error_pages' => 0,
            'not_audited_pages' => 0,
            'last_audit_date' => null
        ];
    }

    /**
     * Get module-specific statistics
     */
    public function getModuleStats(int $projectId): array
    {
        $query = "SELECT 
                    par.module_name,
                    COUNT(*) as total_audits,
                    SUM(CASE WHEN par.result_status = 'PASS' THEN 1 ELSE 0 END) as pass_count,
                    SUM(CASE WHEN par.result_status = 'WARNING' THEN 1 ELSE 0 END) as warning_count,
                    SUM(CASE WHEN par.result_status = 'FAIL' THEN 1 ELSE 0 END) as fail_count,
                    SUM(CASE WHEN par.result_status = 'ERROR' THEN 1 ELSE 0 END) as error_count,
                    MAX(par.checked_at) as last_checked
                  FROM project_pages pp
                  INNER JOIN page_audit_results par ON pp.id = par.project_page_id
                  WHERE pp.project_id = :project_id AND pp.url_status = 'active'
                  GROUP BY par.module_name";
        
        return $this->db->query($query, ['project_id' => $projectId]) ?: [];
    }

    /**
     * Get pages that need recheck (failed or error)
     */
    public function getPagesNeedingRecheck(int $projectId, int $limit = 100): array
    {
        $query = "SELECT DISTINCT pp.*
                  FROM project_pages pp
                  INNER JOIN page_audit_results par ON pp.id = par.project_page_id
                  WHERE pp.project_id = :project_id 
                  AND pp.url_status = 'active'
                  AND par.result_status IN ('FAIL', 'ERROR')
                  LIMIT :limit";
        
        return $this->db->query($query, [
            'project_id' => $projectId,
            'limit' => $limit
        ]) ?: [];
    }

    /**
     * Bulk update pages status
     */
    public function bulkUpdateStatus(array $ids, string $status): bool
    {
        if (empty($ids)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "UPDATE project_pages 
                  SET url_status = ?, updated_at = NOW() 
                  WHERE id IN ($placeholders)";

        $params = array_merge([$status], $ids);
        $stmt = $this->db->getPdo()->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Get pages filtered by audit status
     */
    public function getByAuditStatus(
        int $projectId,
        string $status,
        ?string $moduleName = null,
        int $page = 1,
        int $perPage = 50
    ): array {
        $offset = ($page - 1) * $perPage;
        $params = ['project_id' => $projectId, 'status' => $status];
        
        $query = "SELECT DISTINCT pp.*, ps.sitemap_url
                  FROM project_pages pp
                  LEFT JOIN project_sitemaps ps ON pp.sitemap_id = ps.id
                  INNER JOIN page_audit_results par ON pp.id = par.project_page_id
                  WHERE pp.project_id = :project_id 
                  AND par.result_status = :status";
        
        if ($moduleName) {
            $query .= " AND par.module_name = :module_name";
            $params['module_name'] = $moduleName;
        }
        
        $query .= " ORDER BY pp.created_at DESC
                   LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        
        return $this->db->query($query, $params) ?: [];
    }
}
