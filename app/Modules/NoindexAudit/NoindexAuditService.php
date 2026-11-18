<?php

namespace App\Modules\NoindexAudit;

use App\Modules\Pages\PageRepository;
use App\Services\AuthorizationService;

/**
 * Noindex Audit Service
 * 
 * Handles noindex audit business logic
 */
class NoindexAuditService
{
    private $repository;
    private $processor;
    private $validator;
    private $pageRepository;
    private $authorizationService;

    public function __construct()
    {
        $this->repository = new NoindexAuditRepository();
        $this->processor = new NoindexAuditProcessor();
        $this->validator = new NoindexAuditValidator();
        $this->pageRepository = new PageRepository();
        $this->authorizationService = AuthorizationService::getInstance();
    }

    /**
     * Audit a single page
     */
    public function auditPage(int $pageId): array
    {
        if (!$this->authorizationService->canAccessPage($pageId)) {
            return [
                'success' => false,
                'error' => 'Page not found or access denied'
            ];
        }

        if (!$this->validator->validatePageId($pageId)) {
            return [
                'success' => false,
                'error' => $this->validator->getFirstError()
            ];
        }

        // Get page
        $page = $this->pageRepository->findById($pageId);
        
        if (!$page) {
            return [
                'success' => false,
                'error' => 'Page not found'
            ];
        }

        // Process noindex audit
        $result = $this->processor->process($page['original_url']);

        // Validate result
        if (!$this->validator->validateResult($result)) {
            return [
                'success' => false,
                'error' => 'Invalid audit result: ' . $this->validator->getFirstError()
            ];
        }

        // Save result
        $this->repository->saveResult($pageId, $result);

        // Update page status
        $newPageStatus = $result['status'] === 'ERROR' ? 'failed' : 'completed';
        $this->pageRepository->update($pageId, ['page_status' => $newPageStatus]);

        return [
            'success' => true,
            'result' => $result
        ];
    }

    /**
     * Audit multiple pages
     */
    public function auditPages(array $pageIds): array
    {
        if (!$this->validator->validatePageIds($pageIds)) {
            return [
                'success' => false,
                'error' => $this->validator->getFirstError()
            ];
        }

        // Check authorization for all pages (they should belong to the same project)
        if (!empty($pageIds)) {
            $firstPage = $this->pageRepository->findById($pageIds[0]);
            if ($firstPage) {
                $projectId = $firstPage['project_id'];
                if (!$this->authorizationService->canAccessProjectPages($projectId, $pageIds)) {
                    return [
                        'success' => false,
                        'error' => 'One or more pages not found or access denied'
                    ];
                }
            }
        }

        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($pageIds as $pageId) {
            $result = $this->auditPage($pageId);
            
            if ($result['success']) {
                $successful++;
            } else {
                $failed++;
            }
            
            $results[] = [
                'page_id' => $pageId,
                'success' => $result['success'],
                'result' => $result['result'] ?? null,
                'error' => $result['error'] ?? null
            ];
        }

        return [
            'success' => true,
            'total' => count($pageIds),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Audit all pages in a project
     */
    public function auditProject(int $projectId): array
    {
        if (!$this->authorizationService->canAccessProject($projectId)) {
            return [
                'success' => false,
                'error' => 'Project not found or access denied'
            ];
        }

        if (!$this->validator->validateProjectId($projectId)) {
            return [
                'success' => false,
                'error' => $this->validator->getFirstError()
            ];
        }

        // Get all pages for project
        $pages = $this->pageRepository->getByProject($projectId, 1, 10000); // Get all pages

        if (empty($pages)) {
            return [
                'success' => false,
                'error' => 'No pages found for this project'
            ];
        }

        $pageIds = array_column($pages, 'id');
        
        return $this->auditPages($pageIds);
    }

    /**
     * Get audit result for a page
     */
    public function getPageResult(int $pageId): ?array
    {
        if (!$this->authorizationService->canAccessPage($pageId)) {
            return null;
        }
        return $this->repository->getResultByPage($pageId);
    }

    /**
     * Get all audit results for a project
     */
    public function getProjectResults(int $projectId): array
    {
        if (!$this->authorizationService->canAccessProject($projectId)) {
            return [];
        }
        return $this->repository->getResultsByProject($projectId);
    }

    /**
     * Get audit statistics for a project
     */
    public function getStats(int $projectId): array
    {
        if (!$this->authorizationService->canAccessProject($projectId)) {
            return [];
        }
        return $this->repository->getStats($projectId);
    }

    /**
     * Delete audit result
     */
    public function deleteResult(int $pageId): bool
    {
        if (!$this->authorizationService->canAccessPage($pageId)) {
            return false;
        }
        return $this->repository->delete($pageId);
    }

    /**
     * Delete all audit results for a project
     */
    public function deleteProjectResults(int $projectId): bool
    {
        if (!$this->authorizationService->canAccessProject($projectId)) {
            return false;
        }
        return $this->repository->deleteByProject($projectId);
    }
}
