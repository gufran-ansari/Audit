<?php
/**
 * Authorization Service
 * 
 * Centralized resource-level authorization checks.
 * Determines if the current authenticated user can access specific resources
 * through ownership chains (user -> project -> child resources).
 * 
 * Role model:
 * - super_admin: Can access all resources across all projects
 * - admin: Can only access resources in projects they created (created_by)
 */

namespace App\Services;

use Database\Database;

class AuthorizationService
{
    private $db;
    private $authService;
    private static ?self $instance = null;

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->authService = new AuthService();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get current user ID from session
     */
    private function getCurrentUserId(): ?int
    {
        if (!$this->authService->check()) {
            return null;
        }
        return (int) ($this->authService->user()['id'] ?? 0);
    }

    /**
     * Check if current user is super_admin
     */
    private function isSuperAdmin(): bool
    {
        return $this->authService->isSuperAdmin();
    }

    /**
     * Check if current user is admin (includes super_admin)
     */
    private function isAdmin(): bool
    {
        return $this->authService->isAdmin();
    }

    /**
     * Get current user role
     */
    private function getCurrentRole(): ?string
    {
        return $this->authService->user()['role'] ?? null;
    }

    /**
     * Check if user can access a project
     * 
     * @param int $projectId
     * @return bool True if user can access the project
     */
    public function canAccessProject(int $projectId): bool
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Super admin can access all projects
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin can only access their own projects
        $query = "SELECT created_by FROM projects WHERE id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $projectId]);
        
        if (!$result || empty($result[0])) {
            return false; // Project not found
        }

        return (int) $result[0]['created_by'] === $userId;
    }

    /**
     * Check if user can access a project page
     * 
     * @param int $pageId
     * @return bool True if user can access the page
     */
    public function canAccessPage(int $pageId): bool
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Super admin can access all pages
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin can only access pages in their own projects
        $query = "SELECT p.created_by 
                  FROM project_pages pp
                  INNER JOIN projects p ON p.id = pp.project_id
                  WHERE pp.id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $pageId]);
        
        if (!$result || empty($result[0])) {
            return false; // Page not found
        }

        return (int) $result[0]['created_by'] === $userId;
    }

    /**
     * Check if user can access a sitemap
     * 
     * @param int $sitemapId
     * @return bool True if user can access the sitemap
     */
    public function canAccessSitemap(int $sitemapId): bool
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Super admin can access all sitemaps
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin can only access sitemaps in their own projects
        $query = "SELECT p.created_by 
                  FROM project_sitemaps ps
                  INNER JOIN projects p ON p.id = ps.project_id
                  WHERE ps.id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $sitemapId]);
        
        if (!$result || empty($result[0])) {
            return false; // Sitemap not found
        }

        return (int) $result[0]['created_by'] === $userId;
    }

    /**
     * Check if user can access an audit job
     * 
     * @param int $jobId
     * @return bool True if user can access the job
     */
    public function canAccessJob(int $jobId): bool
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Super admin can access all jobs
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin can only access jobs in their own projects
        $query = "SELECT p.created_by 
                  FROM audit_jobs aj
                  INNER JOIN projects p ON p.id = aj.project_id
                  WHERE aj.id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $jobId]);
        
        if (!$result || empty($result[0])) {
            return false; // Job not found
        }

        return (int) $result[0]['created_by'] === $userId;
    }

    /**
     * Check if user can access an audit run
     * 
     * @param int $auditRunId
     * @return bool True if user can access the audit run
     */
    public function canAccessAuditRun(int $auditRunId): bool
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Super admin can access all audit runs
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin can only access audit runs in their own projects
        $query = "SELECT p.created_by 
                  FROM audit_runs ar
                  INNER JOIN projects p ON p.id = ar.project_id
                  WHERE ar.id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $auditRunId]);
        
        if (!$result || empty($result[0])) {
            return false; // Audit run not found
        }

        return (int) $result[0]['created_by'] === $userId;
    }

    /**
     * Check if user can access an audit result (page audit result)
     * 
     * @param int $resultId
     * @return bool True if user can access the audit result
     */
    public function canAccessAuditResult(int $resultId): bool
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Super admin can access all audit results
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin can only access audit results for pages in their own projects
        $query = "SELECT p.created_by 
                  FROM page_audit_results par
                  INNER JOIN project_pages pp ON pp.id = par.project_page_id
                  INNER JOIN projects p ON p.id = pp.project_id
                  WHERE par.id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $resultId]);
        
        if (!$result || empty($result[0])) {
            return false; // Audit result not found
        }

        return (int) $result[0]['created_by'] === $userId;
    }

    /**
     * Check if user can access a manual override
     * 
     * @param int $overrideId
     * @return bool True if user can access the override
     */
    public function canAccessOverride(int $overrideId): bool
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Super admin can access all overrides
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin can only access overrides for pages in their own projects
        $query = "SELECT p.created_by 
                  FROM page_manual_overrides pmo
                  INNER JOIN project_pages pp ON pp.id = pmo.project_page_id
                  INNER JOIN projects p ON p.id = pp.project_id
                  WHERE pmo.id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $overrideId]);
        
        if (!$result || empty($result[0])) {
            return false; // Override not found
        }

        return (int) $result[0]['created_by'] === $userId;
    }

    /**
     * Check if user can access a report
     * 
     * @param int $reportId
     * @return bool True if user can access the report
     */
    public function canAccessReport(int $reportId): bool
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Super admin can access all reports
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin can only access reports for their own projects
        $query = "SELECT p.created_by 
                  FROM reports r
                  INNER JOIN projects p ON p.id = r.project_id
                  WHERE r.id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $reportId]);
        
        if (!$result || empty($result[0])) {
            return false; // Report not found
        }

        return (int) $result[0]['created_by'] === $userId;
    }

    /**
     * Check if user can access a report by slug (for private reports)
     * 
     * @param string $slug
     * @return bool True if user can access the private report
     */
    public function canAccessReportBySlug(string $slug): bool
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Super admin can access all reports
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin can only access reports for their own projects
        $query = "SELECT p.created_by 
                  FROM reports r
                  INNER JOIN projects p ON p.id = r.project_id
                  WHERE r.report_slug = :slug LIMIT 1";
        $result = $this->db->query($query, ['slug' => $slug]);
        
        if (!$result || empty($result[0])) {
            return false; // Report not found
        }

        return (int) $result[0]['created_by'] === $userId;
    }

    /**
     * Check if user can access a word audit search
     * 
     * @param int $searchId
     * @return bool True if user can access the search
     */
    public function canAccessWordAuditSearch(int $searchId): bool
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Super admin can access all searches
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin can only access searches for their own projects
        $query = "SELECT p.created_by 
                  FROM word_audit_searches was
                  INNER JOIN projects p ON p.id = was.project_id
                  WHERE was.id = :id LIMIT 1";
        $result = $this->db->query($query, ['id' => $searchId]);
        
        if (!$result || empty($result[0])) {
            return false; // Search not found
        }

        return (int) $result[0]['created_by'] === $userId;
    }

    /**
     * Check if user can access a project's pages (bulk check)
     * 
     * @param int $projectId
     * @param array $pageIds
     * @return bool True if user can access ALL specified pages
     */
    public function canAccessProjectPages(int $projectId, array $pageIds): bool
    {
        if (empty($pageIds)) {
            return true;
        }

        // First check project access
        if (!$this->canAccessProject($projectId)) {
            return false;
        }

        // Verify all pages belong to this project
        $placeholders = implode(',', array_fill(0, count($pageIds), '?'));
        $query = "SELECT COUNT(*) as count 
                  FROM project_pages 
                  WHERE id IN ($placeholders) AND project_id = :project_id";
        
        $params = array_merge($pageIds, ['project_id' => $projectId]);
        $result = $this->db->query($query, $params);
        
        if (!$result) {
            return false;
        }

        return (int) $result[0]['count'] === count($pageIds);
    }

    /**
     * Check if user can access multiple jobs (bulk check)
     * 
     * @param array $jobIds
     * @return bool True if user can access ALL specified jobs
     */
    public function canAccessJobs(array $jobIds): bool
    {
        if (empty($jobIds)) {
            return true;
        }

        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        // Super admin can access all jobs
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Verify all jobs belong to user's projects
        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $query = "SELECT COUNT(*) as count 
                  FROM audit_jobs aj
                  INNER JOIN projects p ON p.id = aj.project_id
                  WHERE aj.id IN ($placeholders) AND p.created_by = :user_id";
        
        $params = array_merge($jobIds, ['user_id' => $userId]);
        $result = $this->db->query($query, $params);
        
        if (!$result) {
            return false;
        }

        return (int) $result[0]['count'] === count($jobIds);
    }

    /**
     * Get all project IDs accessible by current user
     * 
     * @return array Array of project IDs
     */
    public function getAccessibleProjectIds(): array
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return [];
        }

        // Super admin can access all projects
        if ($this->isSuperAdmin()) {
            $result = $this->db->query("SELECT id FROM projects WHERE project_status != 'archived'");
            return $result ? array_column($result, 'id') : [];
        }

        // Admin can only access their own projects
        $result = $this->db->query(
            "SELECT id FROM projects WHERE created_by = :user_id AND project_status != 'archived'",
            ['user_id' => $userId]
        );
        return $result ? array_column($result, 'id') : [];
    }

    /**
     * Get project ID for a resource (for access checks)
     * 
     * @param string $resourceType 'page'|'sitemap'|'job'|'audit_run'|'override'|'report'
     * @param int $resourceId
     * @return int|null Project ID or null if not found
     */
    public function getProjectIdForResource(string $resourceType, int $resourceId): ?int
    {
        $tableMap = [
            'page' => ['project_pages', 'project_id'],
            'sitemap' => ['project_sitemaps', 'project_id'],
            'job' => ['audit_jobs', 'project_id'],
            'audit_run' => ['audit_runs', 'project_id'],
            'override' => ['page_manual_overrides', 'project_page_id'], // needs join
            'report' => ['reports', 'project_id'],
            'audit_result' => ['page_audit_results', 'project_page_id'], // needs join
            'word_search' => ['word_audit_searches', 'project_id'],
        ];

        if (!isset($tableMap[$resourceType])) {
            return null;
        }

        $table = $tableMap[$resourceType][0];
        $column = $tableMap[$resourceType][1];

        if ($resourceType === 'override') {
            $query = "SELECT p.id FROM page_manual_overrides pmo
                      INNER JOIN project_pages pp ON pp.id = pmo.project_page_id
                      INNER JOIN projects p ON p.id = pp.project_id
                      WHERE pmo.id = :id LIMIT 1";
        } elseif ($resourceType === 'audit_result') {
            $query = "SELECT p.id FROM page_audit_results par
                      INNER JOIN project_pages pp ON pp.id = par.project_page_id
                      INNER JOIN projects p ON p.id = pp.project_id
                      WHERE par.id = :id LIMIT 1";
        } else {
            $query = "SELECT project_id as id FROM {$table} WHERE id = :id LIMIT 1";
        }

        $result = $this->db->query($query, ['id' => $resourceId]);
        return $result ? (int) $result[0]['id'] : null;
    }
}