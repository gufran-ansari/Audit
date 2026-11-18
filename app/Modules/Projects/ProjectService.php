<?php
 
namespace App\Modules\Projects;
 
use App\Services\AuthService;
use App\Services\AuthorizationService;
use Database\Database;
 
/**
 * Project Service
 * 
 * Business logic for project management
 */
class ProjectService
{
    private $repository;
    private $validator;
    private $authService;
    private $authorizationService;
 
    public function __construct()
    {
        $this->repository = new ProjectRepository();
        $this->validator = new ProjectValidator();
        $this->authService = new AuthService();
        $this->authorizationService = AuthorizationService::getInstance();
    }

    /**
     * Create new project
     */
    public function createProject(array $data): array
    {
        // Validate data
        if (!$this->validator->validateCreate($data, $this->repository)) {
            return [
                'success' => false,
                'errors' => $this->validator->getErrors()
            ];
        }

        // Extract and normalize domain
        $domain = $this->validator->extractDomain($data['primary_domain']);
        $normalizedDomain = $this->validator->normalizeDomain($domain);

        // Generate slug
        $slug = $this->validator->generateSlug($data['project_name']);

        // Get current user
        $user = $this->authService->user();

        // Prepare project data
        $projectData = [
            'uuid' => $this->generateUuid(),
            'project_name' => sanitize($data['project_name']),
            'primary_domain' => $domain,
            'normalized_domain' => $normalizedDomain,
            'report_slug' => $slug,
            'project_status' => 'active',
            'created_by' => $user['id']
        ];

        // Create project
        $projectId = $this->repository->create($projectData);

        if (!$projectId) {
            return [
                'success' => false,
                'errors' => ['general' => 'Failed to create project. Please try again.']
            ];
        }

        // Log activity
        $this->logActivity($projectId, 'created', 'Project created');

        // Get created project
        $project = $this->repository->findById($projectId);

        return [
            'success' => true,
            'project' => $project
        ];
    }

    /**
     * Update project
     */
    public function updateProject(int $projectId, array $data): array
    {
        // Check if project exists
        $project = $this->repository->findById($projectId);
        
        if (!$project) {
            return [
                'success' => false,
                'errors' => ['general' => 'Project not found']
            ];
        }

        // Check access
        if (!$this->canAccessProject($project)) {
            return [
                'success' => false,
                'errors' => ['general' => 'Access denied']
            ];
        }

        // Validate data
        if (!$this->validator->validateUpdate($data, $projectId, $this->repository)) {
            return [
                'success' => false,
                'errors' => $this->validator->getErrors()
            ];
        }

        // Prepare update data
        $updateData = [];

        if (isset($data['project_name'])) {
            $updateData['project_name'] = sanitize($data['project_name']);
        }

        if (isset($data['primary_domain'])) {
            $domain = $this->validator->extractDomain($data['primary_domain']);
            $updateData['primary_domain'] = $domain;
            $updateData['normalized_domain'] = $this->validator->normalizeDomain($domain);
        }

        if (empty($updateData)) {
            return [
                'success' => false,
                'errors' => ['general' => 'No data to update']
            ];
        }

        // Update project
        $updated = $this->repository->update($projectId, $updateData);

        if (!$updated) {
            return [
                'success' => false,
                'errors' => ['general' => 'Failed to update project']
            ];
        }

        // Log activity
        $this->logActivity($projectId, 'updated', 'Project updated');

        // Get updated project
        $project = $this->repository->findById($projectId);

        return [
            'success' => true,
            'project' => $project
        ];
    }

    /**
     * Archive project
     */
    public function archiveProject(int $projectId): array
    {
        $project = $this->repository->findById($projectId);
        
        if (!$project) {
            return [
                'success' => false,
                'message' => 'Project not found'
            ];
        }

        // Check access
        if (!$this->canAccessProject($project)) {
            return [
                'success' => false,
                'message' => 'Access denied'
            ];
        }

        $archived = $this->repository->archive($projectId);

        if (!$archived) {
            return [
                'success' => false,
                'message' => 'Failed to archive project'
            ];
        }

        // Log activity
        $this->logActivity($projectId, 'archived', 'Project archived');

        return [
            'success' => true,
            'message' => 'Project archived successfully'
        ];
    }

    /**
     * Restore archived project
     */
    public function restoreProject(int $projectId): array
    {
        $project = $this->repository->findById($projectId);
        
        if (!$project) {
            return [
                'success' => false,
                'message' => 'Project not found'
            ];
        }

        // Check access
        if (!$this->canAccessProject($project)) {
            return [
                'success' => false,
                'message' => 'Access denied'
            ];
        }

        $restored = $this->repository->restore($projectId);

        if (!$restored) {
            return [
                'success' => false,
                'message' => 'Failed to restore project'
            ];
        }

        // Log activity
        $this->logActivity($projectId, 'restored', 'Project restored from archive');

        return [
            'success' => true,
            'message' => 'Project restored successfully'
        ];
    }

    /**
     * Get project by ID
     */
    public function getProject(int $projectId): ?array
    {
        $project = $this->repository->findById($projectId);
        
        if (!$project || !$this->canAccessProject($project)) {
            return null;
        }

        // Add statistics
        $project['stats'] = $this->repository->getStats($projectId);

        return $project;
    }

    /**
     * Get project by UUID
     */
    public function getProjectByUuid(string $uuid): ?array
    {
        $project = $this->repository->findByUuid($uuid);
        
        if (!$project || !$this->canAccessProject($project)) {
            return null;
        }

        // Add statistics
        $project['stats'] = $this->repository->getStats($project['id']);

        return $project;
    }

    /**
     * Get all projects with filters
     */
    public function getProjects(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $user = $this->authService->user();

        // If admin (not super_admin), only show their projects
        if (!$this->authService->isSuperAdmin()) {
            $filters['created_by'] = $user['id'];
        }

        $projects = $this->repository->getAll($filters, $page, $perPage);
        $total = $this->repository->getTotalCount($filters);

        return [
            'projects' => $projects,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * Check if current user can access project
     */
    private function canAccessProject(array $project): bool
    {
        return $this->authorizationService->canAccessProject((int) $project['id']);
    }

    /**
     * Log project activity
     */
    private function logActivity(int $projectId, string $type, string $description, array $metadata = []): void
    {
        $user = $this->authService->user();

        $query = "INSERT INTO project_activity_logs 
                  (project_id, user_id, activity_type, activity_description, metadata) 
                  VALUES (:project_id, :user_id, :type, :description, :metadata)";

        $params = [
            'project_id' => $projectId,
            'user_id' => $user['id'],
            'type' => $type,
            'description' => $description,
            'metadata' => json_encode($metadata)
        ];

        Database::getInstance()->execute($query, $params);
    }

    /**
     * Get project activity logs
     */
    public function getActivityLogs(int $projectId, int $limit = 50): array
    {
        $project = $this->repository->findById($projectId);
        
        if (!$project || !$this->canAccessProject($project)) {
            return [];
        }

        $query = "SELECT pal.*, u.name as user_name 
                  FROM project_activity_logs pal
                  LEFT JOIN users u ON pal.user_id = u.id
                  WHERE pal.project_id = :project_id
                  ORDER BY pal.created_at DESC
                  LIMIT :limit";

        $params = [
            'project_id' => $projectId,
            'limit' => $limit
        ];

        return Database::getInstance()->query($query, $params) ?: [];
    }

    /**
     * Generate UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
