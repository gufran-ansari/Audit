<?php

namespace App\Jobs;

use App\Services\JobService;
use App\Services\RetryService;
use App\Services\PageFetchService;
use App\Services\ModuleRegistry;

/**
 * Retry Job Processor
 * 
 * Specialized processor for retry jobs. Only processes pages that
 * have failed and are eligible for retry. Skips successful pages.
 * Integrates with RetryService for failure tracking and limit enforcement.
 */
class RetryJobProcessor
{
    private JobService $jobService;
    private RetryService $retryService;
    private PageFetchService $fetchService;

    /** Max seconds to run before yielding */
    private int $maxExecutionSeconds = 55;

    public function __construct()
    {
        $this->jobService = new JobService();
        $this->retryService = new RetryService();
        $this->fetchService = new PageFetchService();
    }

    public function setMaxExecutionSeconds(int $seconds): void
    {
        $this->maxExecutionSeconds = max(10, $seconds);
    }

    /**
     * Process a retry job. Only retries failed pages, skipping successful ones.
     */
    public function processRetryJob(int $jobId): int
    {
        $job = $this->jobService->getById($jobId);

        if (!$job) {
            return $jobId;
        }

        // Start the job if still queued
        if ($job['status'] === 'queued') {
            if (!$this->jobService->startJob($jobId)) {
                return $jobId;
            }
        } elseif ($job['status'] !== 'processing') {
            return $jobId;
        }

        $projectId = (int) $job['project_id'];
        $moduleKeys = $job['selected_modules'];
        $moduleParams = $job['module_params'] ?? [];
        $batchSize = (int) ($job['batch_size'] ?: 25);
        $lastPageId = $job['last_page_id'] ? (int) $job['last_page_id'] : 0;
        $processedPages = (int) $job['processed_pages'];
        $successCount = (int) $job['success_count'];
        $failedCount = (int) $job['failed_count'];

        // Resolve modules
        $resolvedModules = ModuleRegistry::resolve($moduleKeys);
        if (empty($resolvedModules)) {
            $this->jobService->failJob($jobId, 'No valid modules');
            return $jobId;
        }

        // Instantiate processors and repositories
        $processors = [];
        $repositories = [];
        foreach (array_keys($resolvedModules) as $key) {
            $processors[$key] = ModuleRegistry::makeProcessor($key);
            $repositories[$key] = ModuleRegistry::makeRepository($key);
        }

        $startTime = time();

        $this->jobService->log($jobId, 'info', 'Retry processing started', [
            'resume_after' => $lastPageId,
        ]);

        try {
            while (true) {
                if ((time() - $startTime) >= $this->maxExecutionSeconds) {
                    $this->jobService->updateProgress($jobId, $processedPages, $successCount, $failedCount, $lastPageId);
                    return $jobId;
                }

                // Get next batch of retryable pages
                $batch = $this->getRetryBatch($projectId, $moduleKeys, $lastPageId, $batchSize);

                if (empty($batch)) {
                    break;
                }

                foreach ($batch as $row) {
                    $pageId = (int) $row['project_page_id'];
                    $moduleName = $row['module_name'];

                    if ((time() - $startTime) >= $this->maxExecutionSeconds) {
                        $this->jobService->updateProgress($jobId, $processedPages, $successCount, $failedCount, $lastPageId);
                        return $jobId;
                    }

                    // Find the module key for this module_name
                    $moduleKey = $this->findModuleKey($moduleName, $resolvedModules);
                    if (!$moduleKey || !isset($processors[$moduleKey])) {
                        $lastPageId = max($lastPageId, $pageId);
                        continue;
                    }

                    // Skip if already resolved or exhausted
                    if ($this->retryService->shouldSkip($pageId, $moduleName)) {
                        $lastPageId = max($lastPageId, $pageId);
                        $processedPages++;
                        continue;
                    }

                    // Increment retry count
                    $this->retryService->incrementRetry($pageId, $moduleName);

                    // Get the URL for this page
                    $url = $this->getPageUrl($pageId);
                    if (!$url) {
                        $this->retryService->markRetryFailed($pageId, $moduleName, 'Page URL not found');
                        $failedCount++;
                        $processedPages++;
                        $lastPageId = max($lastPageId, $pageId);
                        continue;
                    }

                    // Fetch the page
                    $fetchResult = $this->fetchService->fetch($url, false); // No cache for retries

                    if (!$fetchResult['success']) {
                        $this->retryService->markRetryFailed($pageId, $moduleName, $fetchResult['error'] ?? 'Fetch failed');
                        $repositories[$moduleKey]->saveResult($pageId, [
                            'status' => 'ERROR',
                            'error' => $fetchResult['error'],
                        ]);
                        $failedCount++;
                    } else {
                        // Run the specific module
                        try {
                            $html = $fetchResult['html'];

                            if ($moduleKey === 'word') {
                                $keywords = $moduleParams['word']['keywords'] ?? [];
                                $result = $processors[$moduleKey]->processHtml($html, $keywords);
                            } else {
                                $result = $processors[$moduleKey]->processHtml($html);
                            }

                            $repositories[$moduleKey]->saveResult($pageId, $result);

                            if (($result['status'] ?? 'ERROR') === 'ERROR') {
                                $this->retryService->markRetryFailed($pageId, $moduleName, $result['error'] ?? 'Processing error');
                                $failedCount++;
                            } else {
                                // Success — mark resolved
                                $this->retryService->markResolved($pageId, $moduleName);
                                $successCount++;
                            }
                        } catch (\Throwable $e) {
                            $this->retryService->markRetryFailed($pageId, $moduleName, $e->getMessage());
                            $failedCount++;
                        }
                    }

                    $this->fetchService->forget($url);
                    $processedPages++;
                    $lastPageId = max($lastPageId, $pageId);
                }

                // Update progress after batch
                $this->jobService->updateProgress($jobId, $processedPages, $successCount, $failedCount, $lastPageId);
            }

            // Completed
            $this->jobService->updateProgress($jobId, $processedPages, $successCount, $failedCount, $lastPageId);
            $this->jobService->completeJob($jobId);

            $this->jobService->log($jobId, 'info', 'Retry job completed', [
                'processed' => $processedPages,
                'success' => $successCount,
                'failed' => $failedCount,
            ]);

        } catch (\Throwable $e) {
            $this->jobService->updateProgress($jobId, $processedPages, $successCount, $failedCount, $lastPageId);
            $this->jobService->failJob($jobId, $e->getMessage());
        }

        return $jobId;
    }

    /**
     * Get next batch of retryable entries, ordered for resumption.
     */
    private function getRetryBatch(int $projectId, array $moduleKeys, int $afterPageId, int $limit): array
    {
        $db = \Database\Database::getInstance();

        // Build module_name IN clause
        $moduleNames = [];
        foreach ($moduleKeys as $key) {
            $mod = ModuleRegistry::getByKey($key);
            if ($mod) {
                $moduleNames[] = $mod['module_name'];
            }
        }

        if (empty($moduleNames)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($moduleNames), '?'));

        $query = "SELECT prt.project_page_id, prt.module_name
                  FROM page_retry_tracking prt
                  INNER JOIN project_pages pp ON pp.id = prt.project_page_id
                  WHERE pp.project_id = ?
                    AND prt.status IN ('retry_pending', 'retry_failed')
                    AND prt.retry_count < prt.max_retries
                    AND prt.module_name IN ({$placeholders})
                    AND prt.project_page_id > ?
                  ORDER BY prt.project_page_id ASC
                  LIMIT ?";

        $params = array_merge([$projectId], $moduleNames, [$afterPageId, $limit]);

        $stmt = $db->getPdo()->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get the URL for a page by its ID.
     */
    private function getPageUrl(int $pageId): ?string
    {
        $db = \Database\Database::getInstance();
        $query = "SELECT original_url FROM project_pages WHERE id = ? LIMIT 1";
        $stmt = $db->getPdo()->prepare($query);
        $stmt->execute([$pageId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['original_url'] ?? null;
    }

    /**
     * Find the module key from a module_name given the resolved map.
     */
    private function findModuleKey(string $moduleName, array $resolvedModules): ?string
    {
        foreach ($resolvedModules as $key => $mod) {
            if ($mod['module_name'] === $moduleName) {
                return $key;
            }
        }
        return null;
    }
}
