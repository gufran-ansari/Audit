<?php

namespace App\Modules\Sitemaps;

use Database\Database;
use PDO;

/**
 * Sitemap Repository
 * 
 * Handles all database operations for sitemaps
 */
class SitemapRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find sitemap by ID
     */
    public function findById(int $id): ?array
    {
        $query = "SELECT * FROM project_sitemaps WHERE id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $id]);
        return $result ? $result[0] : null;
    }

    /**
     * Find sitemap by URL for a project
     */
    public function findByUrl(int $projectId, string $url): ?array
    {
        $query = "SELECT * FROM project_sitemaps 
                  WHERE project_id = :project_id AND sitemap_url = :url 
                  LIMIT 1";
        $result = $this->db->query($query, [
            'project_id' => $projectId,
            'url' => $url
        ]);
        return $result ? $result[0] : null;
    }

    /**
     * Check if sitemap URL exists for project
     */
    public function exists(int $projectId, string $url): bool
    {
        $query = "SELECT COUNT(*) as count FROM project_sitemaps 
                  WHERE project_id = :project_id AND sitemap_url = :url";
        $result = $this->db->query($query, [
            'project_id' => $projectId,
            'url' => $url
        ]);
        return $result && $result[0]['count'] > 0;
    }

    /**
     * Get all sitemaps for a project
     */
    public function getByProject(int $projectId): array
    {
        $query = "SELECT * FROM project_sitemaps 
                  WHERE project_id = :project_id 
                  ORDER BY created_at DESC";
        return $this->db->query($query, ['project_id' => $projectId]) ?: [];
    }

    /**
     * Get sitemaps by status
     */
    public function getByStatus(int $projectId, string $status): array
    {
        $query = "SELECT * FROM project_sitemaps 
                  WHERE project_id = :project_id AND status = :status
                  ORDER BY created_at DESC";
        return $this->db->query($query, [
            'project_id' => $projectId,
            'status' => $status
        ]) ?: [];
    }

    /**
     * Create new sitemap entry
     */
    public function create(array $data): ?int
    {
        $query = "INSERT INTO project_sitemaps (
                    project_id, sitemap_url, sitemap_type, status, 
                    discovered_from, total_urls, last_fetched_at
                  ) VALUES (
                    :project_id, :sitemap_url, :sitemap_type, :status,
                    :discovered_from, :total_urls, :last_fetched_at
                  )";
        
        return $this->db->insert($query, $data);
    }

    /**
     * Update sitemap
     */
    public function update(int $id, array $data): bool
    {
        $setClauses = [];
        $params = ['id' => $id];
        
        foreach ($data as $key => $value) {
            $setClauses[] = "$key = :$key";
            $params[$key] = $value;
        }
        
        $query = "UPDATE project_sitemaps SET " . implode(', ', $setClauses) . " WHERE id = :id";
        
        return $this->db->execute($query, $params);
    }

    /**
     * Update sitemap status
     */
    public function updateStatus(int $id, string $status): bool
    {
        $query = "UPDATE project_sitemaps SET status = :status WHERE id = :id";
        return $this->db->execute($query, [
            'id' => $id,
            'status' => $status
        ]);
    }

    /**
     * Update total URLs count
     */
    public function updateTotalUrls(int $id, int $totalUrls): bool
    {
        $query = "UPDATE project_sitemaps 
                  SET total_urls = :total_urls, last_fetched_at = NOW() 
                  WHERE id = :id";
        return $this->db->execute($query, [
            'id' => $id,
            'total_urls' => $totalUrls
        ]);
    }

    /**
     * Delete sitemap
     */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM project_sitemaps WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }

    /**
     * Delete all sitemaps for a project
     */
    public function deleteByProject(int $projectId): bool
    {
        $query = "DELETE FROM project_sitemaps WHERE project_id = :project_id";
        return $this->db->execute($query, ['project_id' => $projectId]);
    }

    /**
     * Get sitemap statistics for a project
     */
    public function getStats(int $projectId): array
    {
        $query = "SELECT 
                    COUNT(*) as total_sitemaps,
                    SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated,
                    SUM(CASE WHEN status = 'invalid' THEN 1 ELSE 0 END) as invalid,
                    SUM(CASE WHEN status = 'unreachable' THEN 1 ELSE 0 END) as unreachable,
                    SUM(total_urls) as total_urls
                  FROM project_sitemaps 
                  WHERE project_id = :project_id";
        
        $result = $this->db->query($query, ['project_id' => $projectId]);
        
        if ($result) {
            return [
                'total_sitemaps' => (int)$result[0]['total_sitemaps'],
                'validated' => (int)$result[0]['validated'],
                'invalid' => (int)$result[0]['invalid'],
                'unreachable' => (int)$result[0]['unreachable'],
                'total_urls' => (int)$result[0]['total_urls']
            ];
        }
        
        return [
            'total_sitemaps' => 0,
            'validated' => 0,
            'invalid' => 0,
            'unreachable' => 0,
            'total_urls' => 0
        ];
    }
}
