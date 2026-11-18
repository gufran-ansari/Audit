<?php
 
namespace App\Services;
 
use App\Modules\Pages\PageRepository;
use App\Services\AuthorizationService;
 
/**
 * Audit Orchestrator
 * 
 * Centralized execution engine for all audit modules.
 *
 * Responsibilities:
 *  - receive selected modules and pages
 *  - fetch each page ONCE (via PageFetchService)
 *  - execute selected modules against shared HTML
 *  - collect and store results in a unified format
 *  - track execution state via AuditRunService
 *
 * No module should be invoked directly from the UI. All audit
 * execution flows through this orchestrator.
 */
class AuditOrchestrator
{
    private PageFetchService $fetchService;
    private PageRepository $pageRepository;
    private ?AuditRunService $runService;
    private ?AuditHistoryService $historyService;
    private AuthorizationService $authorizationService;
 
    /**
     * Cached processor/repository instances per module key.
     */
    private array $processors = [];
    private array $repositories = [];
 
    /**
     * Optional extra parameters per module (e.g. word occurrence keywords).
     */
    private array $moduleParams = [];
 
    public function __construct()
    {
        $this->fetchService = new PageFetchService();
        $this->pageRepository = new PageRepository();
        $this->authorizationService = AuthorizationService::getInstance();
 
        // These services exist from Phase 4 Part 3. Guard in case of partial setup.
        $this->runService = class_exists(AuditRunService::class) ? new AuditRunService() : null;
        $this->historyService = class_exists(AuditHistoryService::class) ? new AuditHistoryService() : null;
    }

    /**
     * Set extra parameters for a specific module key.
     * Example: setModuleParams('word', ['keywords' => ['free shipping']])
     */
    public function setModuleParams(string $moduleKey, array $params): void
    {
        $this->moduleParams[$moduleKey] = $params;
    }

    /**
     * Run selected modules across an entire project.
     *
     * @param int $projectId
     * @param array $moduleKeys Selected module keys (e.g. ['h1', 'meta'])
     * @param array $options ['run_type' => 'manual', 'user_id' => int, 'module_params' => [...]]
     * @return array Execution summary
     */
    public function runProject(int $projectId, array $moduleKeys, array $options = []): array
    {
        // Check authorization
        if (!$this->authorizationService->canAccessProject($projectId)) {
            return [
                'success' => false,
                'error' => 'Project not found or access denied'
            ];
        }
        
        // Resolve modules through the registry (validates + orders them)
        $modules = ModuleRegistry::resolve($moduleKeys);

        if (empty($modules)) {
            return [
                'success' => false,
                'error' => 'No valid modules selected'
            ];
        }

        // Apply any module params passed via options
        if (!empty($options['module_params']) && is_array($options['module_params'])) {
            foreach ($options['module_params'] as $key => $params) {
                $this->setModuleParams($key, $params);
            }
        }

        // Load active pages for the project
        $pages = $this->pageRepository->getByProject($projectId, 1, 100000);

        if (empty($pages)) {
            return [
                'success' => false,
                'error' => 'No pages found for this project'
            ];
        }

        return $this->execute($projectId, $pages, $modules, $options);
    }

    /**
     * Run selected modules across a specific set of pages.
     *
     * @param int $projectId
     * @param array $pageIds
     * @param array $moduleKeys
     * @param array $options
     * @return array Execution summary
     */
    public function runPages(int $projectId, array $pageIds, array $moduleKeys, array $options = []): array
    {
        // Check authorization
        if (!$this->authorizationService->canAccessProject($projectId)) {
            return [
                'success' => false,
                'error' => 'Project not found or access denied'
            ];
        }
        
        // Verify all pages belong to this project and user has access
        if (!$this->authorizationService->canAccessProjectPages($projectId, $pageIds)) {
            return [
                'success' => false,
                'error' => 'One or more pages not found or access denied'
            ];
        }

        $modules = ModuleRegistry::resolve($moduleKeys);

        if (empty($modules)) {
            return [
                'success' => false,
                'error' => 'No valid modules selected'
            ];
        }

        if (!empty($options['module_params']) && is_array($options['module_params'])) {
            foreach ($options['module_params'] as $key => $params) {
                $this->setModuleParams($key, $params);
            }
        }

        $pages = $this->pageRepository->getByIds($pageIds);

        if (empty($pages)) {
            return [
                'success' => false,
                'error' => 'Selected pages not found'
            ];
        }

        return $this->execute($projectId, $pages, $modules, $options);
    }

    /**
     * Core execution loop.
     *
     * Fetches each page once and runs every selected module against the
     * shared HTML, storing results through each module's repository.
     */
    private function execute(int $projectId, array $pages, array $modules, array $options): array
    {
        $runType = $options['run_type'] ?? AuditResultService::RUN_TYPE_MANUAL;
        $userId = $options['user_id'] ?? null;

        $moduleKeys = array_keys($modules);

        // Create an audit run record for tracking (if service available)
        $runId = null;
        if ($this->runService) {
            try {
                $run = $this->runService->createRun($projectId, $moduleKeys, $runType, $userId);
                $runId = $run['id'] ?? null;
                if ($runId) {
                    $this->runService->startRun($runId);
                }
            } catch (\Throwable $e) {
                logMessage("AuditOrchestrator: failed to create run - " . $e->getMessage(), 'warning');
            }
        }

        $totalPages = count($pages);
        $processedPages = 0;
        $successPages = 0;
        $failedPages = 0;

        // Aggregated module-level tallies
        $moduleSummary = [];
        foreach ($moduleKeys as $key) {
            $moduleSummary[$key] = [
                'module_name' => $modules[$key]['module_name'],
                'label' => $modules[$key]['label'],
                'PASS' => 0,
                'WARNING' => 0,
                'FAIL' => 0,
                'ERROR' => 0,
            ];
        }

        $errors = [];

        foreach ($pages as $page) {
            $pageId = (int) $page['id'];
            $url = $page['original_url'];

            // Fetch the page ONCE for all modules
            $fetchResult = $this->fetchService->fetch($url);

            $pageHadError = false;

            if (!$fetchResult['success']) {
                // Record a fetch error result for each selected module
                $pageHadError = true;
                $errors[] = [
                    'page_id' => $pageId,
                    'url' => $url,
                    'type' => 'fetch',
                    'message' => $fetchResult['error']
                ];

                foreach ($moduleKeys as $key) {
                    $errorResult = $this->buildFetchErrorResult($key, $fetchResult['error']);
                    $this->storeResult($key, $pageId, $errorResult, $runId);
                    $moduleSummary[$key]['ERROR']++;
                }

                $this->pageRepository->update($pageId, ['page_status' => 'failed']);
            } else {
                $html = $fetchResult['html'];

                // Run each selected module against the shared HTML
                foreach ($moduleKeys as $key) {
                    $result = $this->runModuleOnHtml($key, $html, $page);

                    // Track per-module status
                    $status = $result['status'] ?? 'ERROR';
                    if (isset($moduleSummary[$key][$status])) {
                        $moduleSummary[$key][$status]++;
                    }

                    if ($status === 'ERROR') {
                        $pageHadError = true;
                        $errors[] = [
                            'page_id' => $pageId,
                            'url' => $url,
                            'type' => 'module',
                            'module' => $key,
                            'message' => $result['error'] ?? 'Module execution error'
                        ];
                    }

                    $this->storeResult($key, $pageId, $result, $runId);
                }

                // Update page status based on overall outcome
                $this->pageRepository->update($pageId, [
                    'page_status' => $pageHadError ? 'failed' : 'completed'
                ]);
            }

            // Free the cached HTML for this URL to keep memory low
            $this->fetchService->forget($url);

            $processedPages++;
            if ($pageHadError) {
                $failedPages++;
            } else {
                $successPages++;
            }

            // Update run progress periodically (every 10 pages) and at the end
            if ($this->runService && $runId && ($processedPages % 10 === 0 || $processedPages === $totalPages)) {
                $this->runService->updateProgress($runId, $processedPages, $successPages, $failedPages);
            }
        }

        // Complete the run
        if ($this->runService && $runId) {
            $this->runService->updateProgress($runId, $processedPages, $successPages, $failedPages);
            $this->runService->completeRun($runId, true);
        }

        return [
            'success' => true,
            'run_id' => $runId,
            'total_pages' => $totalPages,
            'processed_pages' => $processedPages,
            'success_pages' => $successPages,
            'failed_pages' => $failedPages,
            'modules' => $moduleKeys,
            'module_summary' => $moduleSummary,
            'errors' => $errors,
            'error_count' => count($errors)
        ];
    }

    /**
     * Run a single module against shared HTML, passing any required params.
     */
    private function runModuleOnHtml(string $key, string $html, array $page): array
    {
        try {
            $processor = $this->getProcessor($key);

            if (!$processor || !method_exists($processor, 'processHtml')) {
                return AuditResultHelper::error($key, "Module '{$key}' does not support shared HTML processing");
            }

            // Word occurrence module requires keywords
            if ($key === 'word') {
                $keywords = $this->moduleParams['word']['keywords'] ?? [];
                if (empty($keywords)) {
                    return AuditResultHelper::error($key, 'No keywords provided for word occurrence audit');
                }
                $raw = $processor->processHtml($html, $keywords);
            } else {
                $raw = $processor->processHtml($html);
            }

            return $raw;

        } catch (\Throwable $e) {
            logMessage("AuditOrchestrator: module '{$key}' failed - " . $e->getMessage(), 'error');
            return AuditResultHelper::error($key, $e->getMessage());
        }
    }

    /**
     * Build a standardized fetch-error result for a module.
     */
    private function buildFetchErrorResult(string $key, ?string $error): array
    {
        $module = ModuleRegistry::getByKey($key);
        $base = [
            'status' => 'ERROR',
            'error' => $error ?? 'Failed to fetch page'
        ];

        // Provide module-shaped defaults so repositories store consistent data
        switch ($key) {
            case 'word':
                $base['keywords'] = $this->moduleParams['word']['keywords'] ?? [];
                $base['total_occurrences'] = 0;
                break;
            case 'lorem':
                $base['keywords_found'] = [];
                $base['total_occurrences'] = 0;
                break;
            case 'staging':
                $base['staging_urls'] = [];
                $base['total_occurrences'] = 0;
                break;
        }

        return $base;
    }

    /**
     * Store a module result via the module's repository.
     * Records history if the AuditHistoryService is available.
     */
    private function storeResult(string $key, int $pageId, array $result, ?int $runId): void
    {
        try {
            $repository = $this->getRepository($key);
            if (!$repository || !method_exists($repository, 'saveResult')) {
                return;
            }

            // Capture previous result for history tracking (best-effort)
            $previous = null;
            if ($this->historyService && method_exists($repository, 'getResultByPage')) {
                $previousRow = $repository->getResultByPage($pageId);
                if ($previousRow) {
                    $previous = [
                        'status' => $previousRow['result_status'] ?? null,
                        'value' => $previousRow['result_value'] ?? null,
                        'data' => $previousRow['raw_data'] ?? []
                    ];
                }
            }

            // Save the new result
            $repository->saveResult($pageId, $result);

            // Record history if changed
            if ($this->historyService) {
                $module = ModuleRegistry::getByKey($key);
                $moduleName = $module['module_name'] ?? $key;

                $newForHistory = [
                    'status' => $result['status'] ?? 'ERROR',
                    'value' => $result['result_value'] ?? ($result['summary'] ?? null),
                    'data' => $result
                ];

                try {
                    $this->historyService->recordIfChanged($pageId, $moduleName, $previous, $newForHistory, $runId);
                } catch (\Throwable $e) {
                    // History is non-critical; log and continue
                    logMessage("AuditOrchestrator: history record failed for {$moduleName} - " . $e->getMessage(), 'warning');
                }
            }

        } catch (\Throwable $e) {
            logMessage("AuditOrchestrator: failed to store result for '{$key}' - " . $e->getMessage(), 'error');
        }
    }

    /**
     * Lazily instantiate and cache a processor.
     */
    private function getProcessor(string $key): ?object
    {
        if (!isset($this->processors[$key])) {
            $this->processors[$key] = ModuleRegistry::makeProcessor($key);
        }
        return $this->processors[$key];
    }

    /**
     * Lazily instantiate and cache a repository.
     */
    private function getRepository(string $key): ?object
    {
        if (!isset($this->repositories[$key])) {
            $this->repositories[$key] = ModuleRegistry::makeRepository($key);
        }
        return $this->repositories[$key];
    }
}
