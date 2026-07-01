<?php

namespace App\Modules\Pages;

use App\Middleware\AuthMiddleware;

/**
 * Page Controller
 * 
 * Handles HTTP requests for page/URL management
 */
class PageController
{
    private $service;
    private $authMiddleware;

    public function __construct()
    {
        $this->service = new PageService();
        $this->authMiddleware = new AuthMiddleware();
    }

    /**
     * Handle page requests
     */
    public function handle(): void
    {
        // Protect all routes
        $this->authMiddleware->handle();

        $action = input('action', 'list');
        $method = isMethod('POST') ? 'POST' : 'GET';

        switch ($action) {
            case 'import':
                if ($method === 'POST') {
                    $this->importFromSitemap();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'list':
                $this->listPages();
                break;
            
            case 'search':
                $this->searchPages();
                break;
            
            case 'view':
                $this->viewPage();
                break;

            case 'page_detail':
                $this->pageDetail();
                break;
            
            case 'delete':
                if ($method === 'POST') {
                    $this->deletePage();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'stats':
                $this->getPageStats();
                break;
            
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Import URLs from sitemap
     */
    private function importFromSitemap(): void
    {
        $sitemapId = (int)input('sitemap_id');

        if (!$sitemapId) {
            jsonResponse(['error' => 'Sitemap ID is required'], 400);
            return;
        }

        $result = $this->service->importFromSitemap($sitemapId);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => 'URLs imported successfully',
                'imported' => $result['imported'],
                'duplicates' => $result['duplicates'],
                'invalid' => $result['invalid'],
                'nested_sitemaps' => $result['nested_sitemaps'] ?? 0,
                'total_processed' => $result['total_processed']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 422);
        }
    }

    /**
     * List pages for a project
     */
    private function listPages(): void
    {
        $projectId = (int)input('project_id');
        $page = (int)input('page', 1);
        $perPage = (int)input('per_page', 50);

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        // Build filters
        $filters = [];
        
        if (input('sitemap_id')) {
            $filters['sitemap_id'] = (int)input('sitemap_id');
        }
        
        if (input('url_status')) {
            $filters['url_status'] = input('url_status');
        }
        
        if (input('search')) {
            $filters['search'] = input('search');
        }
        
        if (input('audit_status')) {
            $filters['audit_status'] = input('audit_status');
        }
        
        if (input('module_name') && input('module_status')) {
            $filters['module_name'] = input('module_name');
            $filters['module_status'] = input('module_status');
        }

        $result = $this->service->getProjectPages($projectId, $page, $perPage, $filters);

        jsonResponse([
            'success' => true,
            'pages' => $result['pages'],
            'pagination' => $result['pagination']
        ]);
    }

    /**
     * Search pages
     */
    private function searchPages(): void
    {
        $projectId = (int)input('project_id');
        $searchTerm = input('search', '');
        $page = (int)input('page', 1);
        $perPage = (int)input('per_page', 50);

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        if (empty($searchTerm)) {
            jsonResponse(['error' => 'Search term is required'], 400);
            return;
        }

        $result = $this->service->searchPages($projectId, $searchTerm, $page, $perPage);

        jsonResponse([
            'success' => true,
            'pages' => $result['pages'],
            'search_term' => $result['search_term']
        ]);
    }

    /**
     * View single page
     */
    private function viewPage(): void
    {
        $pageId = (int)input('page_id');

        if (!$pageId) {
            jsonResponse(['error' => 'Page ID is required'], 400);
            return;
        }

        $repository = new PageRepository();
        $page = $repository->findById($pageId);

        if (!$page) {
            jsonResponse(['error' => 'Page not found'], 404);
            return;
        }

        jsonResponse([
            'success' => true,
            'page' => $page
        ]);
    }

    /**
     * Aggregate detail for a single page: audit history, retry history,
     * and manual overrides. Reuses existing services only — no new logic.
     */
    private function pageDetail(): void
    {
        $pageId = (int) input('page_id');

        if (!$pageId) {
            jsonResponse(['error' => 'Page ID is required'], 400);
            return;
        }

        $repository = new PageRepository();
        $page = $repository->findById($pageId);

        if (!$page) {
            jsonResponse(['error' => 'Page not found'], 404);
            return;
        }

        // Audit history (page_audit_history). AuditHistoryService resolves its
        // own PDO from the Database singleton when no global $pdo is present.
        $history = [];
        try {
            $historyService = new \App\Services\AuditHistoryService();
            $history = $historyService->getPageHistory($pageId, 100);
        } catch (\Throwable $e) {
            $history = [];
        }

        // Retry history (page_retry_tracking)
        $retryHistory = [];
        try {
            $retryService = new \App\Services\RetryService();
            $retryHistory = $retryService->getPageRetryHistory($pageId);
        } catch (\Throwable $e) {
            $retryHistory = [];
        }

        // Manual overrides (page_manual_overrides)
        $overrides = [];
        try {
            $overrideService = new \App\Modules\Overrides\OverrideService();
            $overrides = $overrideService->getPageOverrides($pageId);
        } catch (\Throwable $e) {
            $overrides = [];
        }

        jsonResponse([
            'success' => true,
            'page' => $page,
            'history' => $history,
            'retry_history' => $retryHistory,
            'overrides' => $overrides,
        ]);
    }

    /**
     * Delete page
     */
    private function deletePage(): void
    {
        $pageId = (int)input('page_id');

        if (!$pageId) {
            jsonResponse(['error' => 'Page ID is required'], 400);
            return;
        }

        $result = $this->service->deletePage($pageId);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 404);
        }
    }

    /**
     * Get page statistics
     */
    private function getPageStats(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $stats = $this->service->getPageStats($projectId);

        jsonResponse([
            'success' => true,
            'stats' => $stats
        ]);
    }
}
