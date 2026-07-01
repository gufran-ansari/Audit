<?php

namespace App\Modules\Dashboard;

use Database\Database;
use App\Services\AuthService;

/**
 * Dashboard Service
 * 
 * Aggregates data for the dashboard overview, activity feed,
 * global search, and notifications.
 */
class DashboardService
{
    private $db;
    private AuthService $auth;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AuthService();
    }

    /**
     * Get full dashboard overview data.
     */
    public function getOverview(): array
    {
        return [
            'stats' => $this->getStats(),
            'recent_projects' => $this->getRecentProjects(),
            'recent_reports' => $this->getRecentReports(),
            'running_jobs' => $this->getRunningJobs(),
            'failed_jobs' => $this->getFailedJobs(),
            'unread_notifications' => $this->getUnreadCount(),
        ];
    }

    /**
     * Global statistics (cached for 60s).
     */
    private function getStats(): array
    {
        $cache = \App\Services\CacheService::getInstance();

        return $cache->remember('dashboard_stats', 60, function () {
            $projects = $this->db->query("SELECT COUNT(*) as cnt FROM projects WHERE project_status = 'active'");
            $pages = $this->db->query("SELECT COUNT(*) as cnt FROM project_pages WHERE url_status = 'active'");
            $audits = $this->db->query("SELECT COUNT(*) as cnt FROM page_audit_results");
            $overrides = $this->db->query("SELECT COUNT(*) as cnt FROM page_manual_overrides");

            return [
                'total_projects' => $projects ? (int) $projects[0]['cnt'] : 0,
                'total_pages' => $pages ? (int) $pages[0]['cnt'] : 0,
                'total_audits' => $audits ? (int) $audits[0]['cnt'] : 0,
                'total_overrides' => $overrides ? (int) $overrides[0]['cnt'] : 0,
            ];
        });
    }

    /**
     * Recent projects (last 5).
     */
    private function getRecentProjects(): array
    {
        return $this->db->query(
            "SELECT id, project_name, primary_domain, project_status, created_at
             FROM projects ORDER BY created_at DESC LIMIT 5"
        ) ?: [];
    }

    /**
     * Recent reports (last 5).
     */
    private function getRecentReports(): array
    {
        return $this->db->query(
            "SELECT r.id, r.report_slug, r.visibility, r.last_generated_at, p.project_name
             FROM reports r
             INNER JOIN projects p ON p.id = r.project_id
             ORDER BY r.updated_at DESC LIMIT 5"
        ) ?: [];
    }

    /**
     * Running jobs.
     */
    private function getRunningJobs(): array
    {
        $result = $this->db->query(
            "SELECT COUNT(*) as cnt FROM project_pages WHERE page_status = 'processing'"
        );
        return ['count' => $result ? (int) $result[0]['cnt'] : 0];
    }

    /**
     * Failed jobs.
     */
    private function getFailedJobs(): array
    {
        $result = $this->db->query(
            "SELECT COUNT(*) as cnt FROM project_pages WHERE page_status = 'failed'"
        );
        return ['count' => $result ? (int) $result[0]['cnt'] : 0];
    }

    // ==========================================
    // ACTIVITY
    // ==========================================

    /**
     * Get recent activity entries.
     */
    public function getRecentActivity(int $limit = 20): array
    {
        return $this->db->query(
            "SELECT al.*, u.name as user_name, p.project_name
             FROM activity_log al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN projects p ON p.id = al.project_id
             ORDER BY al.created_at DESC
             LIMIT :lim",
            ['lim' => $limit]
        ) ?: [];
    }

    /**
     * Log an activity (static helper for other modules to call).
     */
    public static function logActivity(string $action, ?string $description = null, ?int $projectId = null, ?array $metadata = null): void
    {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? null;

        $db->insert(
            "INSERT INTO activity_log (user_id, project_id, action, description, metadata)
             VALUES (:user, :project, :action, :desc, :meta)",
            [
                'user' => $userId,
                'project' => $projectId,
                'action' => $action,
                'desc' => $description,
                'meta' => $metadata ? json_encode($metadata) : null,
            ]
        );
    }

    // ==========================================
    // GLOBAL SEARCH
    // ==========================================

    /**
     * Search across projects, pages, and reports.
     */
    public function search(string $query): array
    {
        $q = '%' . $query . '%';
        $results = [];

        // Search projects
        $projects = $this->db->query(
            "SELECT id, project_name, primary_domain, 'project' as type
             FROM projects WHERE project_name LIKE :q OR primary_domain LIKE :q
             ORDER BY project_name LIMIT 5",
            ['q' => $q]
        ) ?: [];
        foreach ($projects as $p) {
            $results[] = [
                'type' => 'project',
                'title' => $p['project_name'],
                'subtitle' => $p['primary_domain'],
                'id' => $p['id'],
                'icon' => 'bi-folder',
            ];
        }

        // Search pages (URLs)
        $pages = $this->db->query(
            "SELECT pp.id, pp.original_url, p.project_name, 'page' as type
             FROM project_pages pp
             INNER JOIN projects p ON p.id = pp.project_id
             WHERE pp.original_url LIKE :q
             ORDER BY pp.id DESC LIMIT 5",
            ['q' => $q]
        ) ?: [];
        foreach ($pages as $pg) {
            $results[] = [
                'type' => 'page',
                'title' => $pg['original_url'],
                'subtitle' => $pg['project_name'],
                'id' => $pg['id'],
                'icon' => 'bi-link-45deg',
            ];
        }

        // Search reports
        $reports = $this->db->query(
            "SELECT r.id, r.report_slug, p.project_name, 'report' as type
             FROM reports r
             INNER JOIN projects p ON p.id = r.project_id
             WHERE r.report_slug LIKE :q OR p.project_name LIKE :q
             LIMIT 5",
            ['q' => $q]
        ) ?: [];
        foreach ($reports as $r) {
            $results[] = [
                'type' => 'report',
                'title' => $r['project_name'] . ' Report',
                'subtitle' => '/report/' . $r['report_slug'],
                'id' => $r['id'],
                'icon' => 'bi-file-earmark-text',
            ];
        }

        return $results;
    }

    // ==========================================
    // NOTIFICATIONS
    // ==========================================

    /**
     * Get notifications for current user.
     */
    public function getNotifications(int $limit = 20): array
    {
        $user = $this->auth->user();
        if (!$user) return [];

        return $this->db->query(
            "SELECT * FROM notifications
             WHERE (user_id = :uid OR user_id IS NULL)
             ORDER BY created_at DESC LIMIT :lim",
            ['uid' => $user['id'], 'lim' => $limit]
        ) ?: [];
    }

    /**
     * Get unread count.
     */
    public function getUnreadCount(): int
    {
        $user = $this->auth->user();
        if (!$user) return 0;

        $result = $this->db->query(
            "SELECT COUNT(*) as cnt FROM notifications
             WHERE (user_id = :uid OR user_id IS NULL) AND is_read = 0",
            ['uid' => $user['id']]
        );
        return $result ? (int) $result[0]['cnt'] : 0;
    }

    /**
     * Mark notification as read.
     */
    public function markNotificationRead(int $id): void
    {
        $this->db->execute(
            "UPDATE notifications SET is_read = 1 WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Dismiss (delete) a notification.
     */
    public function dismissNotification(int $id): void
    {
        $this->db->execute("DELETE FROM notifications WHERE id = :id", ['id' => $id]);
    }

    /**
     * Mark all as read for current user.
     */
    public function markAllRead(): void
    {
        $user = $this->auth->user();
        if (!$user) return;

        $this->db->execute(
            "UPDATE notifications SET is_read = 1 WHERE (user_id = :uid OR user_id IS NULL)",
            ['uid' => $user['id']]
        );
    }

    /**
     * Create a notification (static helper).
     */
    public static function notify(string $type, string $title, ?string $message = null, ?int $userId = null, ?string $link = null): void
    {
        $db = Database::getInstance();
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link)
             VALUES (:uid, :type, :title, :msg, :link)",
            ['uid' => $userId, 'type' => $type, 'title' => $title, 'msg' => $message, 'link' => $link]
        );
    }
}
