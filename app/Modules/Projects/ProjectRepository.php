<?php

namespace App\Modules\Projects;

use Database\Database;
use PDO;

/**
 * Project Repository
 * 
 * Handles all database operations for projects
 */
class ProjectRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find project by ID
     */
    public function findById(int $id): ?array
    {
        $query = "SELECT * FROM projects WHERE id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $id]);
        return $result ? $result[0] : null;
    }

    /**
     * Find project by UUID
     */
    public function findByUuid(string $uuid): ?array
    {
        $query = "SELECT * FROM projects WHERE uuid = :uuid LIMIT 1";
        $result = $this->db->query($query, ['uuid' => $uuid]);
        return $result ? $result[0] : null;
    }

    /**
     * Find project by report slug
     */
    public function findBySlug(string $slug): ?array
    {
        $query = "SELECT * FROM projects WHERE report_slug = :slug LIMIT 1";
        $result = $this->db->query($query, ['slug' => $slug]);
        return $result ? $result[0] : null;
    }

    /**
     * Check if domain already exists
     */
    public function domainExists(string $normalizedDomain, ?int $excludeId = null): bool
    {
        $query = "SELECT COUNT(*) as count FROM projects 
                  WHERE normalized_domain = :domain 
                  AND project_status != 'archived'";
        
        $params = ['domain' => $normalizedDomain];
        
        if ($excludeId) {
            $query .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        
        $result = $this->db->query($query, $params);
        return $result && $result[0]['count'] > 0;
    }

    /**
     * Get all projects with filters and pagination
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        
        $query = "SELECT p.*, u.name as creator_name 
                  FROM projects p
                  LEFT JOIN users u ON p.created_by = u.id
                  WHERE 1=1";
        
        // Filter by status
        if (!empty($filters['status'])) {
            $query .= " AND p.project_status = :status";
            $params['status'] = $filters['status'];
        }
        
        // Filter by search term
        if (!empty($filters['search'])) {
            $query .= " AND (p.project_name LIKE :search OR p.primary_domain LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        // Filter by created_by (for admin users)
        if (!empty($filters['created_by'])) {
            $query .= " AND p.created_by = :created_by";
            $params['created_by'] = $filters['created_by'];
        }
        
        // Order by newest first
        $query .= " ORDER BY p.created_at DESC";
        
        // Add pagination
        $query .= " LIMIT :limit OFFSET :offset";
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        
        return $this->db->query($query, $params) ?: [];
    }

    /**
     * Get total count with filters
     */
    public function getTotalCount(array $filters = []): int
    {
        $params = [];
        
        $query = "SELECT COUNT(*) as count FROM projects WHERE 1=1";
        
        // Filter by status
        if (!empty($filters['status'])) {
            $query .= " AND project_status = :status";
            $params['status'] = $filters['status'];
        }
        
        // Filter by search term
        if (!empty($filters['search'])) {
            $query .= " AND (project_name LIKE :search OR primary_domain LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        // Filter by created_by (for admin users)
        if (!empty($filters['created_by'])) {
            $query .= " AND created_by = :created_by";
            $params['created_by'] = $filters['created_by'];
        }
        
        $result = $this->db->query($query, $params);
        return $result ? (int)$result[0]['count'] : 0;
    }

    /**
     * Create new project
     */
    public function create(array $data): ?int
    {
        $query = "INSERT INTO projects (
                    uuid, project_name, primary_domain, normalized_domain, 
                    report_slug, project_status, created_by
                  ) VALUES (
                    :uuid, :project_name, :primary_domain, :normalized_domain,
                    :report_slug, :project_status, :created_by
                  )";
        
        return $this->db->insert($query, $data);
    }

    /**
     * Update project
     */
    public function update(int $id, array $data): bool
    {
        $setClauses = [];
        $params = ['id' => $id];
        
        foreach ($data as $key => $value) {
            $setClauses[] = "$key = :$key";
            $params[$key] = $value;
        }
        
        $query = "UPDATE projects SET " . implode(', ', $setClauses) . " WHERE id = :id";
        
        return $this->db->execute($query, $params);
    }

    /**
     * Archive project (soft delete)
     */
    public function archive(int $id): bool
    {
        $query = "UPDATE projects SET project_status = 'archived' WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }

    /**
     * Restore archived project
     */
    public function restore(int $id): bool
    {
        $query = "UPDATE projects SET project_status = 'active' WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }

    /**
     * Get projects by user (for admin role)
     */
    public function getByUser(int $userId, array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $filters['created_by'] = $userId;
        return $this->getAll($filters, $page, $perPage);
    }

    /**
     * Get project statistics
     */
    public function getStats(int $projectId): array
    {
        // Placeholder for future URL statistics
        // Now integrated with actual page counts
        $query = "SELECT COUNT(*) as total_urls FROM project_pages WHERE project_id = :project_id";
        $result = $this->db->query($query, ['project_id' => $projectId]);
        $totalUrls = $result ? (int)$result[0]['total_urls'] : 0;
        
        return [
            'total_urls' => $totalUrls,
            'passed' => 0,
            'failed' => 0,
            'warnings' => 0,
            'processing' => 0,
            'last_audit' => null
        ];
    }
}
