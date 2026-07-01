<?php

namespace App\Modules\Projects;

use App\Middleware\AuthMiddleware;

/**
 * Project Controller
 * 
 * Handles HTTP requests for project management
 */
class ProjectController
{
    private $service;
    private $authMiddleware;

    public function __construct()
    {
        $this->service = new ProjectService();
        $this->authMiddleware = new AuthMiddleware();
    }

    /**
     * Handle project requests
     */
    public function handle(): void
    {
        // Protect all routes
        $this->authMiddleware->handle();

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
            jsonResponse([
                'success' => true,
                'message' => 'Project created successfully',
                'project' => $result['project']
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
            jsonResponse([
                'success' => true,
                'message' => 'Project updated successfully',
                'project' => $result['project']
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

        $result = $this->service->archiveProject($projectId);

        if ($result['success']) {
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

        $result = $this->service->restoreProject($projectId);

        if ($result['success']) {
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
