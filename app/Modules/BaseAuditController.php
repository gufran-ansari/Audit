<?php

namespace App\Modules;

use App\Middleware\AuthMiddleware;

/**
 * Base Audit Controller
 * 
 * Extracts the repeated action routing and response formatting
 * found in all 7 audit module controllers.
 * 
 * Subclasses only need to:
 *   - Set $moduleName (for success messages)
 *   - Override createService() to return their specific service
 * 
 * Standard actions: audit_page, audit_pages, audit_project, results, stats, delete
 */
abstract class BaseAuditController
{
    protected $service;
    protected AuthMiddleware $authMiddleware;

    /** Human-readable module name for messages (e.g., "H1", "Meta") */
    protected string $moduleName = 'Audit';

    public function __construct()
    {
        $this->service = $this->createService();
        $this->authMiddleware = new AuthMiddleware();
    }

    /**
     * Create the module-specific service instance.
     */
    abstract protected function createService(): object;

    /**
     * Handle incoming request. Authenticates and routes to action.
     */
    public function handle(): void
    {
        $this->authMiddleware->handle();

        $action = input('action', 'list');
        $method = isMethod('POST') ? 'POST' : 'GET';

        switch ($action) {
            case 'audit_page':
                $method === 'POST' ? $this->auditPage() : jsonResponse(['error' => 'Method not allowed'], 405);
                break;
            case 'audit_pages':
                $method === 'POST' ? $this->auditPages() : jsonResponse(['error' => 'Method not allowed'], 405);
                break;
            case 'audit_project':
                $method === 'POST' ? $this->auditProject() : jsonResponse(['error' => 'Method not allowed'], 405);
                break;
            case 'results':
                $this->getResults();
                break;
            case 'stats':
                $this->getStats();
                break;
            case 'delete':
                $method === 'POST' ? $this->deleteResult() : jsonResponse(['error' => 'Method not allowed'], 405);
                break;
            default:
                $this->handleCustomAction($action, $method);
        }
    }

    /**
     * Override in child classes to add custom actions.
     * Default: returns 400 invalid action.
     */
    protected function handleCustomAction(string $action, string $method): void
    {
        jsonResponse(['error' => 'Invalid action'], 400);
    }

    protected function auditPage(): void
    {
        $pageId = (int) input('page_id');
        if (!$pageId) {
            jsonResponse(['error' => 'Page ID is required'], 400);
            return;
        }

        $result = $this->service->auditPage($pageId);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => "{$this->moduleName} audit completed successfully",
                'result' => $result['result']
            ]);
        } else {
            jsonResponse(['success' => false, 'error' => $result['error']], 422);
        }
    }

    protected function auditPages(): void
    {
        $pageIds = input('page_ids');
        if (!$pageIds || !is_array($pageIds)) {
            jsonResponse(['error' => 'Page IDs array is required'], 400);
            return;
        }

        $result = $this->service->auditPages($pageIds);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => "Audited {$result['successful']} pages successfully",
                'total' => $result['total'],
                'successful' => $result['successful'],
                'failed' => $result['failed']
            ]);
        } else {
            jsonResponse(['success' => false, 'error' => $result['error']], 422);
        }
    }

    protected function auditProject(): void
    {
        $projectId = (int) input('project_id');
        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $result = $this->service->auditProject($projectId);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => "Project audit completed: {$result['successful']} successful, {$result['failed']} failed",
                'total' => $result['total'],
                'successful' => $result['successful'],
                'failed' => $result['failed']
            ]);
        } else {
            jsonResponse(['success' => false, 'error' => $result['error']], 422);
        }
    }

    protected function getResults(): void
    {
        $projectId = (int) input('project_id');
        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        jsonResponse(['success' => true, 'results' => $this->service->getProjectResults($projectId)]);
    }

    protected function getStats(): void
    {
        $projectId = (int) input('project_id');
        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        jsonResponse(['success' => true, 'stats' => $this->service->getStats($projectId)]);
    }

    protected function deleteResult(): void
    {
        $pageId = (int) input('page_id');
        if (!$pageId) {
            jsonResponse(['error' => 'Page ID is required'], 400);
            return;
        }

        $deleted = $this->service->deleteResult($pageId);
        if ($deleted) {
            jsonResponse(['success' => true, 'message' => 'Audit result deleted successfully']);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to delete audit result'], 500);
        }
    }
}
