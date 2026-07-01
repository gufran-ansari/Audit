<?php

namespace App\Modules\Reports;

use Database\Database;

/**
 * Report Repository
 */
class ReportRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findBySlug(string $slug): ?array
    {
        $result = $this->db->query(
            "SELECT r.*, p.project_name, p.primary_domain, p.uuid as project_uuid
             FROM reports r
             INNER JOIN projects p ON p.id = r.project_id
             WHERE r.report_slug = :slug LIMIT 1",
            ['slug' => $slug]
        );
        return $result ? $result[0] : null;
    }

    public function findByProject(int $projectId): ?array
    {
        $result = $this->db->query(
            "SELECT * FROM reports WHERE project_id = :pid LIMIT 1",
            ['pid' => $projectId]
        );
        return $result ? $result[0] : null;
    }

    public function create(int $projectId, string $slug, string $visibility = 'private'): ?int
    {
        return $this->db->insert(
            "INSERT INTO reports (project_id, report_slug, visibility, created_at)
             VALUES (:pid, :slug, :vis, NOW())
             ON DUPLICATE KEY UPDATE report_slug = VALUES(report_slug), updated_at = NOW()",
            ['pid' => $projectId, 'slug' => $slug, 'vis' => $visibility]
        );
    }

    public function updateVisibility(int $reportId, string $visibility): bool
    {
        return $this->db->execute(
            "UPDATE reports SET visibility = :vis, updated_at = NOW() WHERE id = :id",
            ['id' => $reportId, 'vis' => $visibility]
        );
    }

    public function updateSlug(int $reportId, string $slug): bool
    {
        return $this->db->execute(
            "UPDATE reports SET report_slug = :slug, updated_at = NOW() WHERE id = :id",
            ['id' => $reportId, 'slug' => $slug]
        );
    }

    public function touchGenerated(int $reportId): bool
    {
        return $this->db->execute(
            "UPDATE reports SET last_generated_at = NOW(), updated_at = NOW() WHERE id = :id",
            ['id' => $reportId]
        );
    }

    public function delete(int $reportId): bool
    {
        return $this->db->execute("DELETE FROM reports WHERE id = :id", ['id' => $reportId]);
    }
}
