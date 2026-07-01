<?php

namespace App\Modules\Overrides;

use Database\Database;

/**
 * Override Repository
 * 
 * Database operations for manual overrides.
 * Never modifies page_audit_results — overrides are stored separately.
 */
class OverrideRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find override by page and module.
     */
    public function findByPageAndModule(int $pageId, string $moduleName): ?array
    {
        $result = $this->db->query(
            "SELECT pmo.*, u.name as user_name
             FROM page_manual_overrides pmo
             LEFT JOIN users u ON u.id = pmo.updated_by
             WHERE pmo.project_page_id = :page_id AND pmo.module_name = :module
             LIMIT 1",
            ['page_id' => $pageId, 'module' => $moduleName]
        );
        return $result ? $result[0] : null;
    }

    /**
     * Find all overrides for a page.
     */
    public function findByPage(int $pageId): array
    {
        return $this->db->query(
            "SELECT pmo.*, u.name as user_name
             FROM page_manual_overrides pmo
             LEFT JOIN users u ON u.id = pmo.updated_by
             WHERE pmo.project_page_id = :page_id
             ORDER BY pmo.module_name",
            ['page_id' => $pageId]
        ) ?: [];
    }

    /**
     * Get all overrides for a project (with page URLs).
     */
    public function findByProject(int $projectId, array $filters = []): array
    {
        $params = ['project_id' => $projectId];
        $query = "SELECT pmo.*, pp.original_url, u.name as user_name
                  FROM page_manual_overrides pmo
                  INNER JOIN project_pages pp ON pp.id = pmo.project_page_id
                  LEFT JOIN users u ON u.id = pmo.updated_by
                  WHERE pp.project_id = :project_id";

        if (!empty($filters['override_status'])) {
            $query .= " AND pmo.override_status = :status";
            $params['status'] = $filters['override_status'];
        }

        if (!empty($filters['module_name'])) {
            $query .= " AND pmo.module_name = :module";
            $params['module'] = $filters['module_name'];
        }

        $query .= " ORDER BY pmo.updated_at DESC";

        return $this->db->query($query, $params) ?: [];
    }

    /**
     * Create or update an override (upsert).
     */
    public function upsert(int $pageId, string $moduleName, string $originalStatus, string $overrideStatus, ?string $notes, int $userId): int
    {
        $existing = $this->findByPageAndModule($pageId, $moduleName);

        if ($existing) {
            // Update
            $this->db->execute(
                "UPDATE page_manual_overrides
                 SET override_status = :status, notes = :notes, updated_by = :user, updated_at = NOW()
                 WHERE id = :id",
                ['status' => $overrideStatus, 'notes' => $notes, 'user' => $userId, 'id' => $existing['id']]
            );
            return (int) $existing['id'];
        } else {
            // Insert
            return (int) $this->db->insert(
                "INSERT INTO page_manual_overrides
                 (project_page_id, module_name, original_status, override_status, notes, updated_by)
                 VALUES (:page_id, :module, :original, :status, :notes, :user)",
                [
                    'page_id' => $pageId,
                    'module' => $moduleName,
                    'original' => $originalStatus,
                    'status' => $overrideStatus,
                    'notes' => $notes,
                    'user' => $userId,
                ]
            );
        }
    }

    /**
     * Delete an override (revert to original audit status).
     */
    public function delete(int $overrideId): bool
    {
        return (bool) $this->db->execute(
            "DELETE FROM page_manual_overrides WHERE id = :id",
            ['id' => $overrideId]
        );
    }

    /**
     * Log a history entry.
     */
    public function logHistory(int $overrideId, int $pageId, string $moduleName, ?string $oldStatus, string $newStatus, ?string $notes, int $userId): void
    {
        $this->db->insert(
            "INSERT INTO override_history
             (override_id, project_page_id, module_name, old_status, new_status, notes, changed_by)
             VALUES (:oid, :page_id, :module, :old, :new, :notes, :user)",
            [
                'oid' => $overrideId,
                'page_id' => $pageId,
                'module' => $moduleName,
                'old' => $oldStatus,
                'new' => $newStatus,
                'notes' => $notes,
                'user' => $userId,
            ]
        );
    }

    /**
     * Get history for a specific override.
     */
    public function getHistory(int $overrideId): array
    {
        return $this->db->query(
            "SELECT oh.*, u.name as user_name
             FROM override_history oh
             LEFT JOIN users u ON u.id = oh.changed_by
             WHERE oh.override_id = :oid
             ORDER BY oh.created_at DESC",
            ['oid' => $overrideId]
        ) ?: [];
    }

    /**
     * Get history for a page+module.
     */
    public function getHistoryByPageModule(int $pageId, string $moduleName): array
    {
        return $this->db->query(
            "SELECT oh.*, u.name as user_name
             FROM override_history oh
             LEFT JOIN users u ON u.id = oh.changed_by
             WHERE oh.project_page_id = :page_id AND oh.module_name = :module
             ORDER BY oh.created_at DESC",
            ['page_id' => $pageId, 'module' => $moduleName]
        ) ?: [];
    }

    /**
     * Get override summary counts for a project.
     */
    public function getProjectSummary(int $projectId): array
    {
        $result = $this->db->query(
            "SELECT 
                COUNT(*) as total,
                SUM(pmo.override_status = 'ignored') as ignored_count,
                SUM(pmo.override_status = 'fixed') as fixed_count,
                SUM(pmo.override_status = 'accepted') as accepted_count,
                SUM(pmo.override_status = 'false_positive') as false_positive_count,
                SUM(pmo.override_status = 'needs_review') as needs_review_count
             FROM page_manual_overrides pmo
             INNER JOIN project_pages pp ON pp.id = pmo.project_page_id
             WHERE pp.project_id = :pid",
            ['pid' => $projectId]
        );

        $r = $result ? $result[0] : [];
        return [
            'total' => (int) ($r['total'] ?? 0),
            'ignored' => (int) ($r['ignored_count'] ?? 0),
            'fixed' => (int) ($r['fixed_count'] ?? 0),
            'accepted' => (int) ($r['accepted_count'] ?? 0),
            'false_positive' => (int) ($r['false_positive_count'] ?? 0),
            'needs_review' => (int) ($r['needs_review_count'] ?? 0),
        ];
    }

    /**
     * Get all overrides for a project keyed by page_id + module for fast lookups.
     */
    public function getOverrideMap(int $projectId): array
    {
        $rows = $this->db->query(
            "SELECT pmo.project_page_id, pmo.module_name, pmo.override_status, pmo.notes
             FROM page_manual_overrides pmo
             INNER JOIN project_pages pp ON pp.id = pmo.project_page_id
             WHERE pp.project_id = :pid",
            ['pid' => $projectId]
        ) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $key = $row['project_page_id'] . '_' . $row['module_name'];
            $map[$key] = $row;
        }
        return $map;
    }
}
