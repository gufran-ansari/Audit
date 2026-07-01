<?php

namespace App\Modules\WordOccurrenceAudit;

use App\Middleware\AuthMiddleware;
use App\Services\AuthService;

/**
 * Word Occurrence Controller
 * 
 * Handles HTTP requests for word occurrence audits
 */
class WordOccurrenceController
{
    private $service;
    private $authMiddleware;
    private $authService;

    public function __construct()
    {
        $this->service = new WordOccurrenceService();
        $this->authMiddleware = new AuthMiddleware();
        $this->authService = new AuthService();
    }

    /**
     * Handle audit requests
     */
    public function handle(): void
    {
        // Protect all routes
        $this->authMiddleware->handle();

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

            case 'history':
                $this->getHistory();
                break;

            case 'keywords':
                $this->getKeywords();
                break;

            case 'delete_search':
                if ($method === 'POST') {
                    $this->deleteSearch();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
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
     * Get current user ID safely
     */
    private function getUserId(): ?int
    {
        $user = $this->authService->user();
        return $user['id'] ?? null;
    }

    /**
     * Audit a single page
     */
    private function auditPage(): void
    {
        $pageId = (int)input('page_id');
        $keywordInput = input('keyword');

        if (!$pageId) {
            jsonResponse(['error' => 'Page ID is required'], 400);
            return;
        }

        $validator = new WordOccurrenceValidator();
        $keywords = $validator->parseKeywords($keywordInput);

        if (empty($keywords)) {
            jsonResponse(['error' => $validator->getFirstError() ?? 'Invalid keyword'], 400);
            return;
        }

        $result = $this->service->auditPage($pageId, $keywords);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => 'Word occurrence audit completed successfully',
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
        $keywordInput = input('keyword');

        if (!$pageIds || !is_array($pageIds)) {
            jsonResponse(['error' => 'Page IDs array is required'], 400);
            return;
        }

        $validator = new WordOccurrenceValidator();
        $keywords = $validator->parseKeywords($keywordInput);

        if (empty($keywords)) {
            jsonResponse(['error' => $validator->getFirstError() ?? 'Invalid keyword'], 400);
            return;
        }

        $result = $this->service->auditPages($pageIds, $keywords);

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
        $keywordInput = input('keyword');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $result = $this->service->auditProject($projectId, (string)$keywordInput, $this->getUserId());

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => "Project audit completed: {$result['successful']} successful, {$result['failed']} failed",
                'total' => $result['total'],
                'successful' => $result['successful'],
                'failed' => $result['failed'],
                'keywords' => $result['keywords'] ?? []
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

        $stats = $this->service->getStats($projectId);

        jsonResponse([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Get keyword search history
     */
    private function getHistory(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $history = $this->service->getSearchHistory($projectId);

        jsonResponse([
            'success' => true,
            'history' => $history
        ]);
    }

    /**
     * Get distinct previously-searched keywords
     */
    private function getKeywords(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $keywords = $this->service->getDistinctKeywords($projectId);

        jsonResponse([
            'success' => true,
            'keywords' => $keywords
        ]);
    }

    /**
     * Delete a search history entry
     */
    private function deleteSearch(): void
    {
        $searchId = (int)input('search_id');
        $projectId = (int)input('project_id');

        if (!$searchId || !$projectId) {
            jsonResponse(['error' => 'Search ID and Project ID are required'], 400);
            return;
        }

        $deleted = $this->service->deleteSearch($searchId, $projectId);

        if ($deleted) {
            jsonResponse([
                'success' => true,
                'message' => 'Search history entry deleted'
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => 'Failed to delete search history entry'
            ], 500);
        }
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
