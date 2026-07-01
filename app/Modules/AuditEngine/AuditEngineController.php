<?php

namespace App\Modules\AuditEngine;

use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Services\AuditOrchestrator;
use App\Services\AuditSummaryService;
use App\Services\ModuleRegistry;
use App\Modules\WordOccurrenceAudit\WordOccurrenceValidator;

/**
 * Audit Engine Controller
 * 
 * Single entry point for all audit execution. The UI calls this
 * controller, which delegates to the AuditOrchestrator. No module
 * is invoked directly from the UI.
 */
class AuditEngineController
{
    private $orchestrator;
    private $summaryService;
    private $authMiddleware;
    private $authService;

    public function __construct()
    {
        $this->orchestrator = new AuditOrchestrator();
        $this->summaryService = new AuditSummaryService();
        $this->authMiddleware = new AuthMiddleware();
        $this->authService = new AuthService();
    }

    /**
     * Handle requests
     */
    public function handle(): void
    {
        $this->authMiddleware->handle();

        $action = input('action', 'modules');
        $method = isMethod('POST') ? 'POST' : 'GET';

        switch ($action) {
            case 'run_project':
                if ($method === 'POST') {
                    $this->runProject();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;

            case 'run_pages':
                if ($method === 'POST') {
                    $this->runPages();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;

            case 'modules':
                $this->getModules();
                break;

            case 'summary':
                $this->getSummary();
                break;

            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Get current user ID
     */
    private function getUserId(): ?int
    {
        $user = $this->authService->user();
        return $user['id'] ?? null;
    }

    /**
     * Parse and validate selected module keys from input.
     */
    private function parseModules(): array
    {
        $modules = input('modules');

        // Accept comma-separated string or array
        if (is_string($modules)) {
            $modules = array_filter(array_map('trim', explode(',', $modules)));
        }

        if (!is_array($modules)) {
            return [];
        }

        // Keep only valid registered keys
        return array_values(array_filter($modules, fn($k) => ModuleRegistry::exists($k)));
    }

    /**
     * Build module params (e.g. word occurrence keywords) from input.
     */
    private function buildModuleParams(array $moduleKeys): array
    {
        $params = [];

        if (in_array('word', $moduleKeys, true)) {
            $keywordInput = input('keyword');
            $validator = new WordOccurrenceValidator();
            $keywords = $validator->parseKeywords($keywordInput);
            $params['word'] = ['keywords' => $keywords];
        }

        return $params;
    }

    /**
     * Run selected modules across an entire project.
     */
    private function runProject(): void
    {
        $projectId = (int) input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $moduleKeys = $this->parseModules();

        if (empty($moduleKeys)) {
            jsonResponse(['error' => 'No valid modules selected'], 400);
            return;
        }

        $moduleParams = $this->buildModuleParams($moduleKeys);

        // Validate word keywords if word module is selected
        if (in_array('word', $moduleKeys, true) && empty($moduleParams['word']['keywords'])) {
            jsonResponse(['error' => 'Keywords are required for the Word Occurrence audit'], 400);
            return;
        }

        $result = $this->orchestrator->runProject($projectId, $moduleKeys, [
            'run_type' => 'manual',
            'user_id' => $this->getUserId(),
            'module_params' => $moduleParams
        ]);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => "Audit completed: {$result['success_pages']} ok, {$result['failed_pages']} failed",
                'run_id' => $result['run_id'],
                'total_pages' => $result['total_pages'],
                'processed_pages' => $result['processed_pages'],
                'success_pages' => $result['success_pages'],
                'failed_pages' => $result['failed_pages'],
                'module_summary' => $result['module_summary'],
                'error_count' => $result['error_count']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 422);
        }
    }

    /**
     * Run selected modules across specific pages.
     */
    private function runPages(): void
    {
        $projectId = (int) input('project_id');
        $pageIds = input('page_ids');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        if (is_string($pageIds)) {
            $pageIds = array_filter(array_map('intval', explode(',', $pageIds)));
        }

        if (!is_array($pageIds) || empty($pageIds)) {
            jsonResponse(['error' => 'Page IDs are required'], 400);
            return;
        }

        $moduleKeys = $this->parseModules();

        if (empty($moduleKeys)) {
            jsonResponse(['error' => 'No valid modules selected'], 400);
            return;
        }

        $moduleParams = $this->buildModuleParams($moduleKeys);

        if (in_array('word', $moduleKeys, true) && empty($moduleParams['word']['keywords'])) {
            jsonResponse(['error' => 'Keywords are required for the Word Occurrence audit'], 400);
            return;
        }

        $result = $this->orchestrator->runPages($projectId, $pageIds, $moduleKeys, [
            'run_type' => 'manual',
            'user_id' => $this->getUserId(),
            'module_params' => $moduleParams
        ]);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => "Audit completed: {$result['success_pages']} ok, {$result['failed_pages']} failed",
                'run_id' => $result['run_id'],
                'total_pages' => $result['total_pages'],
                'processed_pages' => $result['processed_pages'],
                'success_pages' => $result['success_pages'],
                'failed_pages' => $result['failed_pages'],
                'module_summary' => $result['module_summary'],
                'error_count' => $result['error_count']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 422);
        }
    }

    /**
     * Return the registered modules (for building the toolbar dynamically).
     */
    private function getModules(): void
    {
        $modules = array_values(array_map(function ($m) {
            return [
                'key' => $m['key'],
                'module_name' => $m['module_name'],
                'label' => $m['label'],
                'icon' => $m['icon'],
                'order' => $m['order'],
                'enabled' => $m['enabled'],
                'requires_input' => $m['requires_input'],
            ];
        }, ModuleRegistry::enabled()));

        jsonResponse([
            'success' => true,
            'modules' => $modules
        ]);
    }

    /**
     * Return the project + module summary for the workspace dashboard.
     */
    private function getSummary(): void
    {
        $projectId = (int) input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $dashboard = $this->summaryService->getDashboard($projectId);

        jsonResponse([
            'success' => true,
            'summary' => $dashboard
        ]);
    }
}
