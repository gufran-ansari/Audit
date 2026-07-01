<?php

namespace App\Modules\WordOccurrenceAudit;

use App\Modules\Pages\PageRepository;

/**
 * Word Occurrence Service
 * 
 * Handles word occurrence audit business logic.
 * Searches user-provided keywords across project pages.
 */
class WordOccurrenceService
{
    private $repository;
    private $processor;
    private $validator;
    private $pageRepository;

    public function __construct()
    {
        $this->repository = new WordOccurrenceRepository();
        $this->processor = new WordOccurrenceProcessor();
        $this->validator = new WordOccurrenceValidator();
        $this->pageRepository = new PageRepository();
    }

    /**
     * Audit a single page for the given keywords.
     *
     * @param int $pageId
     * @param array $keywords Pre-parsed keyword list
     */
    public function auditPage(int $pageId, array $keywords): array
    {
        if (!$this->validator->validatePageId($pageId)) {
            return [
                'success' => false,
                'error' => $this->validator->getFirstError()
            ];
        }

        if (empty($keywords)) {
            return [
                'success' => false,
                'error' => 'No keywords provided'
            ];
        }

        $page = $this->pageRepository->findById($pageId);

        if (!$page) {
            return [
                'success' => false,
                'error' => 'Page not found'
            ];
        }

        // Process word occurrence audit
        $result = $this->processor->process($page['original_url'], $keywords);

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
     * Audit multiple pages for the given keywords.
     */
    public function auditPages(array $pageIds, array $keywords): array
    {
        if (!$this->validator->validatePageIds($pageIds)) {
            return [
                'success' => false,
                'error' => $this->validator->getFirstError()
            ];
        }

        if (empty($keywords)) {
            return [
                'success' => false,
                'error' => 'No keywords provided'
            ];
        }

        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($pageIds as $pageId) {
            $result = $this->auditPage($pageId, $keywords);

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
     * Audit all pages in a project for the given keyword input.
     *
     * @param int $projectId
     * @param string $keywordInput Raw comma-separated keyword input
     * @param int|null $userId User initiating the search (for history)
     */
    public function auditProject(int $projectId, string $keywordInput, ?int $userId = null): array
    {
        if (!$this->validator->validateProjectId($projectId)) {
            return [
                'success' => false,
                'error' => $this->validator->getFirstError()
            ];
        }

        // Parse and validate keywords
        $keywords = $this->validator->parseKeywords($keywordInput);

        if (empty($keywords)) {
            return [
                'success' => false,
                'error' => $this->validator->getFirstError() ?? 'No valid keywords provided'
            ];
        }

        // Get all pages for project
        $pages = $this->pageRepository->getByProject($projectId, 1, 10000);

        if (empty($pages)) {
            return [
                'success' => false,
                'error' => 'No pages found for this project'
            ];
        }

        // Save search history (one entry per keyword for granular history)
        foreach ($keywords as $keyword) {
            $this->repository->saveSearch($projectId, $keyword, $userId);
        }

        $pageIds = array_column($pages, 'id');

        $auditResult = $this->auditPages($pageIds, $keywords);
        $auditResult['keywords'] = $keywords;

        return $auditResult;
    }

    /**
     * Get audit result for a page
     */
    public function getPageResult(int $pageId): ?array
    {
        return $this->repository->getResultByPage($pageId);
    }

    /**
     * Get all audit results for a project
     */
    public function getProjectResults(int $projectId): array
    {
        return $this->repository->getResultsByProject($projectId);
    }

    /**
     * Get audit statistics for a project
     */
    public function getStats(int $projectId): array
    {
        return $this->repository->getStats($projectId);
    }

    /**
     * Get keyword search history for a project
     */
    public function getSearchHistory(int $projectId, int $limit = 20): array
    {
        return $this->repository->getSearchHistory($projectId, $limit);
    }

    /**
     * Get distinct previously-searched keywords
     */
    public function getDistinctKeywords(int $projectId, int $limit = 50): array
    {
        return $this->repository->getDistinctKeywords($projectId, $limit);
    }

    /**
     * Delete a search history entry
     */
    public function deleteSearch(int $searchId, int $projectId): bool
    {
        return $this->repository->deleteSearch($searchId, $projectId);
    }

    /**
     * Delete audit result
     */
    public function deleteResult(int $pageId): bool
    {
        return $this->repository->delete($pageId);
    }

    /**
     * Delete all audit results for a project
     */
    public function deleteProjectResults(int $projectId): bool
    {
        return $this->repository->deleteByProject($projectId);
    }
}
