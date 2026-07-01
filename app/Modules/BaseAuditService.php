<?php

namespace App\Modules;

use App\Modules\Pages\PageRepository;

/**
 * Base Audit Service
 * 
 * Extracts the repeated audit orchestration logic shared by all
 * standard audit module services (H1, Meta, Noindex, FAQ, Lorem, Staging).
 * 
 * Subclasses override:
 *   - createRepository()
 *   - createProcessor()
 *   - createValidator()
 * 
 * The auditPage/auditPages/auditProject flow is identical across all modules.
 */
abstract class BaseAuditService
{
    protected $repository;
    protected $processor;
    protected $validator;
    protected PageRepository $pageRepository;

    public function __construct()
    {
        $this->repository = $this->createRepository();
        $this->processor = $this->createProcessor();
        $this->validator = $this->createValidator();
        $this->pageRepository = new PageRepository();
    }

    abstract protected function createRepository(): object;
    abstract protected function createProcessor(): object;
    abstract protected function createValidator(): object;

    /**
     * Audit a single page.
     */
    public function auditPage(int $pageId): array
    {
        if (!$this->validator->validatePageId($pageId)) {
            return ['success' => false, 'error' => $this->validator->getFirstError()];
        }

        $page = $this->pageRepository->findById($pageId);
        if (!$page) {
            return ['success' => false, 'error' => 'Page not found'];
        }

        $result = $this->processor->process($page['original_url']);

        if (!$this->validator->validateResult($result)) {
            return ['success' => false, 'error' => 'Invalid audit result: ' . $this->validator->getFirstError()];
        }

        $this->repository->saveResult($pageId, $result);
        $this->pageRepository->update($pageId, [
            'page_status' => $result['status'] === 'ERROR' ? 'failed' : 'completed'
        ]);

        return ['success' => true, 'result' => $result];
    }

    /**
     * Audit multiple pages.
     */
    public function auditPages(array $pageIds): array
    {
        if (!$this->validator->validatePageIds($pageIds)) {
            return ['success' => false, 'error' => $this->validator->getFirstError()];
        }

        $successful = 0;
        $failed = 0;

        foreach ($pageIds as $pageId) {
            $result = $this->auditPage($pageId);
            $result['success'] ? $successful++ : $failed++;
        }

        return [
            'success' => true,
            'total' => count($pageIds),
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    /**
     * Audit all pages in a project.
     */
    public function auditProject(int $projectId): array
    {
        if (!$this->validator->validateProjectId($projectId)) {
            return ['success' => false, 'error' => $this->validator->getFirstError()];
        }

        $pages = $this->pageRepository->getByProject($projectId, 1, 10000);
        if (empty($pages)) {
            return ['success' => false, 'error' => 'No pages found for this project'];
        }

        return $this->auditPages(array_column($pages, 'id'));
    }

    /**
     * Get result for a page.
     */
    public function getPageResult(int $pageId): ?array
    {
        return $this->repository->getResultByPage($pageId);
    }

    /**
     * Get all results for a project.
     */
    public function getProjectResults(int $projectId): array
    {
        return $this->repository->getResultsByProject($projectId);
    }

    /**
     * Get statistics for a project.
     */
    public function getStats(int $projectId): array
    {
        return $this->repository->getStats($projectId);
    }

    /**
     * Delete result for a page.
     */
    public function deleteResult(int $pageId): bool
    {
        return $this->repository->delete($pageId);
    }

    /**
     * Delete all results for a project.
     */
    public function deleteProjectResults(int $projectId): bool
    {
        return $this->repository->deleteByProject($projectId);
    }
}
