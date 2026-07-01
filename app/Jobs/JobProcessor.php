<?php

namespace App\Jobs;

use App\Services\JobService;
use App\Services\PageFetchService;
use App\Services\ModuleRegistry;
use App\Modules\Pages\PageRepository;

/**
 * Job Processor
 * 
 * Processes audit jobs in batches. Designed for shared hosting:
 * - Works within PHP time limits via batch processing
 * - Sends heartbeats to indicate liveness
 * - Saves progress after each batch so work is never lost
 * - Can be invoked by cron or triggered via HTTP (worker endpoint)
 *
 * Usage:
 *   $processor = new JobProcessor();
 *   $processor->processNext();   // Process one batch of the next queued job
 *   $processor->runJob($jobId);  // Process a specific job to completion
 */
class JobProcessor
{
    private JobService $jobService;
    private PageFetchService $fetchService;
    private PageRepository $pageRepository;

    /** Max seconds to run before yielding (prevents timeout on shared hosting) */
    private int $maxExecutionSeconds = 55;

    public function __construct()
    {
        $this->jobService = new JobService();
        $this->fetchService = new PageFetchService();
        $this->pageRepository = new PageRepository();
    }

    /**
     * Set max execution time (for environments with shorter limits).
     */
    public function setMaxExecutionSeconds(int $seconds): void
    {
        $this->maxExecutionSeconds = max(10, $seconds);
    }

    /**
     * Pick up and process the next queued job.
     * Returns the job ID processed, or null if nothing queued.
     */
    public function processNext(): ?int
    {
        // First, detect and mark any interrupted jobs
        $this->jobService->markAllInterrupted();

        // Get next job
        $job = $this->jobService->getNextQueued();

        if (!$job) {
            return null;
        }

        $jobId = (int) $job['id'];

        // Claim the job
        if (!$this->jobService->startJob($jobId)) {
            return null; // Another worker grabbed it
        }

        return $this->executeJob($jobId, $job);
    }

    /**
     * Run a specific job (must be in 'queued' or 'processing' state).
     */
    public function runJob(int $jobId): ?int
    {
        $job = $this->jobService->getById($jobId);

        if (!$job) {
            return null;
        }

        // If still queued, start it
        if ($job['status'] === 'queued') {
            if (!$this->jobService->startJob($jobId)) {
                return null;
            }
        } elseif ($job['status'] !== 'processing') {
            // Can only process queued or processing jobs
            return null;
        }

        return $this->executeJob($jobId, $job);
    }

    /**
     * Core execution loop for a job.
     * Processes pages in batches, updating progress and heartbeat.
     */
    private function executeJob(int $jobId, array $job): int
    {
        $projectId = (int) $job['project_id'];
        $modules = $job['selected_modules'];
        $moduleParams = $job['module_params'] ?? [];
        $batchSize = (int) ($job['batch_size'] ?: 25);
        $lastPageId = $job['last_page_id'] ? (int) $job['last_page_id'] : 0;
        $processedPages = (int) $job['processed_pages'];
        $successCount = (int) $job['success_count'];
        $failedCount = (int) $job['failed_count'];

        // Resolve modules through registry
        $resolvedModules = ModuleRegistry::resolve($modules);

        if (empty($resolvedModules)) {
            $this->jobService->failJob($jobId, 'No valid modules in job');
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

        $this->jobService->log($jobId, 'info', 'Processing started', [
            'batch_size' => $batchSize,
            'resume_after_page_id' => $lastPageId,
        ]);

        try {
            while (true) {
                // Check execution time limit
                if ((time() - $startTime) >= $this->maxExecutionSeconds) {
                    $this->jobService->log($jobId, 'info', 'Yielding due to time limit', [
                        'processed_so_far' => $processedPages,
                    ]);
                    // Save progress and leave job in 'processing' for next invocation
                    $this->jobService->updateProgress($jobId, $processedPages, $successCount, $failedCount, $lastPageId);
                    return $jobId;
                }

                // Fetch next batch of pages (ordered by ID, after last_page_id)
                $batch = $this->getPageBatch($projectId, $lastPageId, $batchSize);

                if (empty($batch)) {
                    // All pages processed
                    break;
                }

                // Process the batch
                foreach ($batch as $page) {
                    $pageId = (int) $page['id'];
                    $url = $page['original_url'];

                    // Check time limit inside inner loop too
                    if ((time() - $startTime) >= $this->maxExecutionSeconds) {
                        $this->jobService->updateProgress($jobId, $processedPages, $successCount, $failedCount, $lastPageId);
                        return $jobId;
                    }

                    // Fetch page
                    $fetchResult = $this->fetchService->fetch($url);
                    $pageHadError = false;

                    if (!$fetchResult['success']) {
                        // Store error result for each module
                        foreach (array_keys($resolvedModules) as $key) {
                            $errorResult = ['status' => 'ERROR', 'error' => $fetchResult['error']];
                            $this->storeResult($repositories[$key], $pageId, $key, $errorResult, $moduleParams);
                        }
                        $pageHadError = true;
                    } else {
                        $html = $fetchResult['html'];

                        // Run each module
                        foreach (array_keys($resolvedModules) as $key) {
                            try {
                                if ($key === 'word') {
                                    $keywords = $moduleParams['word']['keywords'] ?? [];
                                    $result = $processors[$key]->processHtml($html, $keywords);
                                } else {
                                    $result = $processors[$key]->processHtml($html);
                                }

                                $this->storeResult($repositories[$key], $pageId, $key, $result, $moduleParams);

                                if (($result['status'] ?? '') === 'ERROR') {
                                    $pageHadError = true;
                                }
                            } catch (\Throwable $e) {
                                $this->storeResult($repositories[$key], $pageId, $key, [
                                    'status' => 'ERROR',
                                    'error' => $e->getMessage(),
                                ], $moduleParams);
                                $pageHadError = true;
                            }
                        }
                    }

                    // Free memory
                    $this->fetchService->forget($url);

                    $processedPages++;
                    $lastPageId = $pageId;

                    if ($pageHadError) {
                        $failedCount++;
                    } else {
                        $successCount++;
                    }
                }

                // Update progress after each batch + heartbeat
                $this->jobService->updateProgress($jobId, $processedPages, $successCount, $failedCount, $lastPageId);
            }

            // Job completed
            $this->jobService->updateProgress($jobId, $processedPages, $successCount, $failedCount, $lastPageId);
            $this->jobService->completeJob($jobId);

            $this->jobService->log($jobId, 'info', 'Job completed', [
                'total_processed' => $processedPages,
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
     * Get a batch of pages ordered by ID, starting after $afterId.
     */
    private function getPageBatch(int $projectId, int $afterId, int $batchSize): array
    {
        $db = \Database\Database::getInstance();

        $query = "SELECT id, original_url FROM project_pages
                  WHERE project_id = :project_id 
                  AND url_status = 'active'
                  AND id > :after_id
                  ORDER BY id ASC
                  LIMIT :limit";

        return $db->query($query, [
            'project_id' => $projectId,
            'after_id' => $afterId,
            'limit' => $batchSize,
        ]) ?: [];
    }

    /**
     * Store a module result via the module's repository.
     * Also records failures in retry tracking and marks resolved on success.
     */
    private function storeResult(object $repository, int $pageId, string $key, array $result, array $moduleParams): void
    {
        if (!method_exists($repository, 'saveResult')) {
            return;
        }

        // Add module-specific defaults for error cases
        if ($result['status'] === 'ERROR') {
            switch ($key) {
                case 'word':
                    $result['keywords'] = $moduleParams['word']['keywords'] ?? [];
                    $result['total_occurrences'] = 0;
                    $result['keyword_results'] = [];
                    $result['locations'] = [];
                    $result['snippets'] = [];
                    break;
                case 'lorem':
                    $result['keywords_found'] = $result['keywords_found'] ?? [];
                    $result['total_occurrences'] = $result['total_occurrences'] ?? 0;
                    break;
                case 'staging':
                    $result['staging_urls'] = $result['staging_urls'] ?? [];
                    $result['total_occurrences'] = $result['total_occurrences'] ?? 0;
                    break;
            }
        }

        $repository->saveResult($pageId, $result);

        // Track failures/successes in retry system
        $module = \App\Services\ModuleRegistry::getByKey($key);
        $moduleName = $module ? $module['module_name'] : $key;

        try {
            $retryService = new \App\Services\RetryService();

            if ($result['status'] === 'ERROR') {
                $retryService->recordFailure($pageId, $moduleName, $result['error'] ?? 'Unknown error');
            } else {
                $retryService->markResolved($pageId, $moduleName);
            }
        } catch (\Throwable $e) {
            // Retry tracking is non-critical
            logMessage("JobProcessor: retry tracking error - " . $e->getMessage(), 'warning');
        }
    }
}
