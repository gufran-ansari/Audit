<?php

namespace App\Modules\Projects;

use App\Middleware\ApiAuthMiddleware;
use App\Services\ActivityLogService;

/**
 * Project Controller
 * 
 * Handles HTTP requests for project management
 */
class ProjectController
{
    private $service;
    private $apiAuth;
    private $activityLog;

    public function __construct()
    {
        $this->service = new ProjectService();
        $this->apiAuth = new ApiAuthMiddleware(['auth' => true, 'csrf' => true, 'api' => true]);
        $this->activityLog = ActivityLogService::getInstance();
    }

    /**
     * Handle project requests
     */
    public function handle(): void
    {
        // Protect all routes with API auth middleware (includes CSRF for mutating requests)
        $this->apiAuth->handle();

        $action = input('action', 'list');
        $method = isMethod('POST') ? 'POST' : 'GET';

        switch ($action) {
            case 'list':
                $this->listProjects();
                break;
            
            case 'create':
                if ($method === 'POST') {
                    $this->createProject();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'view':
                $this->viewProject();
                break;
            
            case 'update':
                if ($method === 'POST') {
                    $this->updateProject();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'archive':
                if ($method === 'POST') {
                    $this->archiveProject();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'restore':
                if ($method === 'POST') {
                    $this->restoreProject();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'activity':
                $this->getActivityLogs();
                break;
            
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * List projects with filters and pagination
     */
    private function listProjects(): void
    {
        $filters = [
            'status' => input('status', 'active'),
            'search' => input('search', '')
        ];

        $page = (int)input('page', 1);
        $perPage = (int)input('per_page', 10);

        $result = $this->service->getProjects($filters, $page, $perPage);

        jsonResponse([
            'success' => true,
            'data' => $result['projects'],
            'pagination' => $result['pagination']
        ]);
    }

    /**
     * Create new project
     */
    private function createProject(): void
    {
        $data = [
            'project_name' => input('project_name'),
            'primary_domain' => input('primary_domain')
        ];

        $result = $this->service->createProject($data);

        if ($result['success']) {
            $project = $result['project'];
            $this->activityLog->log(
                'project_created',
                "Project '{$project['project_name']}' created",
                $project['id'],
                ['domain' => $project['primary_domain']],
                'project',
                $project['id']
            );
            
            jsonResponse([
                'success' => true,
                'message' => 'Project created successfully',
                'project' => $project
            ], 201);
        } else {
            jsonResponse([
                'success' => false,
                'errors' => $result['errors']
            ], 422);
        }
    }

    /**
     * View project details
     */
    private function viewProject(): void
    {
        $uuid = input('uuid');

        if (!$uuid) {
            jsonResponse(['error' => 'Project UUID is required'], 400);
            return;
        }

        $project = $this->service->getProjectByUuid($uuid);

        if (!$project) {
            jsonResponse(['error' => 'Project not found or access denied'], 404);
            return;
        }

        jsonResponse([
            'success' => true,
            'project' => $project
        ]);
    }

    /**
     * Update project
     */
    private function updateProject(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $data = [];

        if (input('project_name') !== null) {
            $data['project_name'] = input('project_name');
        }

        if (input('primary_domain') !== null) {
            $data['primary_domain'] = input('primary_domain');
        }

        $result = $this->service->updateProject($projectId, $data);

        if ($result['success']) {
            $project = $result['project'];
            $this->activityLog->log(
                'project_updated',
                "Project '{$project['project_name']}' updated",
                $project['id'],
                ['changes' => array_keys($data)],
                'project',
                $project['id']
            );
            
            jsonResponse([
                'success' => true,
                'message' => 'Project updated successfully',
                'project' => $project
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'errors' => $result['errors']
            ], 422);
        }
    }

    /**
     * Archive project
     */
    private function archiveProject(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        // Get project name before archiving for logging
        $project = $this->service->getProjectById($projectId);
        $projectName = $project ? $project['project_name'] : "ID {$projectId}";

        $result = $this->service->archiveProject($projectId);

        if ($result['success']) {
            $this->activityLog->log(
                'project_archived',
                "Project '{$projectName}' archived",
                $projectId,
                ['archived' => true],
                'project',
                $projectId
            );
            
            jsonResponse([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['message']
            ], 403);
        }
    }

    /**
     * Restore archived project
     */
    private function restoreProject(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        // Get project name before restoring for logging
        $project = $this->service->getProjectById($projectId);
        $projectName = $project ? $project['project_name'] : "ID {$projectId}";

        $result = $this->service->restoreProject($projectId);

        if ($result['success']) {
            $this->activityLog->log(
                'project_restored',
                "Project '{$projectName}' restored from archive",
                $projectId,
                ['restored' => true],
                'project',
                $projectId
            );
            
            jsonResponse([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['message']
            ], 403);
        }
    }

    /**
     * Get project activity logs
     */
    private function getActivityLogs(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $limit = (int)input('limit', 50);
        $logs = $this->service->getActivityLogs($projectId, $limit);

        jsonResponse([
            'success' => true,
            'logs' => $logs
        ]);
    }
}
