<?php

namespace App\Modules\NoindexAudit;

use App\Middleware\ApiAuthMiddleware;
use App\Services\AuthorizationService;

/**
 * Noindex Audit Controller
 * 
 * Handles HTTP requests for noindex audits
 */
class NoindexAuditController
{
    private $service;
    private $apiAuth;
    private $authorizationService;

    public function __construct()
    {
        $this->service = new NoindexAuditService();
        $this->apiAuth = new ApiAuthMiddleware(['auth' => true, 'csrf' => true, 'api' => true]);
        $this->authorizationService = AuthorizationService::getInstance();
    }

    /**
     * Handle audit requests
     */
    public function handle(): void
    {
        // Protect all routes with API auth middleware (includes CSRF for mutating requests)
        $this->apiAuth->handle();

        $action = input('action', 'list');
        $method = isMethod('POST') ? 'POST' : 'GET';

        switch ($action) {
            case 'audit_page':
                if ($method === 'POST') {
                    $this->auditPage();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'audit_pages':
                if ($method === 'POST') {
                    $this->auditPages();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'audit_project':
                if ($method === 'POST') {
                    $this->auditProject();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'results':
                $this->getResults();
                break;
            
            case 'stats':
                $this->getStats();
                break;
            
            case 'delete':
                if ($method === 'POST') {
                    $this->deleteResult();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Audit a single page
     */
    private function auditPage(): void
    {
        $pageId = (int)input('page_id');

        if (!$pageId) {
            jsonResponse(['error' => 'Page ID is required'], 400);
            return;
        }

        if (!$this->authorizationService->canAccessPage($pageId)) {
            jsonResponse(['error' => 'Page not found or access denied'], 404);
            return;
        }

        $result = $this->service->auditPage($pageId);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => 'Noindex audit completed successfully',
                'result' => $result['result']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 422);
        }
    }

    /**
     * Audit multiple pages
     */
    private function auditPages(): void
    {
        $pageIds = input('page_ids');

        if (!$pageIds || !is_array($pageIds)) {
            jsonResponse(['error' => 'Page IDs array is required'], 400);
            return;
        }

        if (!$this->authorizationService->canAccessProjectPages(
            (int)input('project_id'),
            array_map('intval', $pageIds)
        )) {
            jsonResponse(['error' => 'One or more pages not found or access denied'], 404);
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
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 422);
        }
    }

    /**
     * Audit all pages in a project
     */
    private function auditProject(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        if (!$this->authorizationService->canAccessProject($projectId)) {
            jsonResponse(['error' => 'Project not found or access denied'], 404);
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
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 422);
        }
    }

    /**
     * Get audit results for a project
     */
    private function getResults(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        if (!$this->authorizationService->canAccessProject($projectId)) {
            jsonResponse(['error' => 'Project not found or access denied'], 404);
            return;
        }

        $results = $this->service->getProjectResults($projectId);

        jsonResponse([
            'success' => true,
            'results' => $results
        ]);
    }

    /**
     * Get audit statistics
     */
    private function getStats(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        if (!$this->authorizationService->canAccessProject($projectId)) {
            jsonResponse(['error' => 'Project not found or access denied'], 404);
            return;
        }

        $stats = $this->service->getStats($projectId);

        jsonResponse([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Delete audit result
     */
    private function deleteResult(): void
    {
        $pageId = (int)input('page_id');

        if (!$pageId) {
            jsonResponse(['error' => 'Page ID is required'], 400);
            return;
        }

        if (!$this->authorizationService->canAccessPage($pageId)) {
            jsonResponse(['error' => 'Page not found or access denied'], 404);
            return;
        }

        $deleted = $this->service->deleteResult($pageId);

        if ($deleted) {
            jsonResponse([
                'success' => true,
                'message' => 'Audit result deleted successfully'
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => 'Failed to delete audit result'
            ], 500);
        }
    }
}
