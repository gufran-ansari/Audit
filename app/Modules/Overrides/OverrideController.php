<?php

namespace App\Modules\Overrides;

use App\Middleware\AuthMiddleware;

/**
 * Override Controller
 * 
 * Handles API requests for manual audit overrides.
 * Auth-protected — only Admin/Super Admin can manage overrides.
 */
class OverrideController
{
    private OverrideService $service;
    private AuthMiddleware $authMiddleware;

    public function __construct()
    {
        $this->service = new OverrideService();
        $this->authMiddleware = new AuthMiddleware();
    }

    public function handle(): void
    {
        $this->authMiddleware->handle();

        $action = input('action', 'list');
        $method = isMethod('POST') ? 'POST' : 'GET';

        switch ($action) {
            case 'set':
                if ($method === 'POST') $this->setOverride();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'remove':
                if ($method === 'POST') $this->removeOverride();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'get':
                $this->getOverride();
                break;

            case 'page':
                $this->getPageOverrides();
                break;

            case 'project':
                $this->getProjectOverrides();
                break;

            case 'summary':
                $this->getProjectSummary();
                break;

            case 'history':
                $this->getHistory();
                break;

            case 'statuses':
                $this->getStatuses();
                break;

            case 'map':
                $this->getOverrideMap();
                break;

            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Set (create/update) a manual override.
     */
    private function setOverride(): void
    {
        $pageId = (int) input('page_id');
        $moduleName = input('module_name');
        $originalStatus = input('original_status');
        $overrideStatus = input('override_status');
        $notes = input('notes');

        if (!$pageId || !$moduleName || !$originalStatus || !$overrideStatus) {
            jsonResponse(['error' => 'page_id, module_name, original_status, and override_status are required'], 400);
            return;
        }

        $result = $this->service->setOverride($pageId, $moduleName, $originalStatus, $overrideStatus, $notes);

        if ($result['success']) {
            jsonResponse(['success' => true, 'override' => $result['override']]);
        } else {
            jsonResponse(['success' => false, 'error' => $result['error']], 422);
        }
    }

    /**
     * Remove an override (revert to automated status).
     */
    private function removeOverride(): void
    {
        $pageId = (int) input('page_id');
        $moduleName = input('module_name');

        if (!$pageId || !$moduleName) {
            jsonResponse(['error' => 'page_id and module_name are required'], 400);
            return;
        }

        $result = $this->service->removeOverride($pageId, $moduleName);

        if ($result['success']) {
            jsonResponse(['success' => true, 'message' => $result['message']]);
        } else {
            jsonResponse(['success' => false, 'error' => $result['error']], 422);
        }
    }

    /**
     * Get a specific override for a page + module.
     */
    private function getOverride(): void
    {
        $pageId = (int) input('page_id');
        $moduleName = input('module_name');

        if (!$pageId || !$moduleName) {
            jsonResponse(['error' => 'page_id and module_name are required'], 400);
            return;
        }

        $override = $this->service->getOverride($pageId, $moduleName);
        jsonResponse(['success' => true, 'override' => $override]);
    }

    /**
     * Get all overrides for a page.
     */
    private function getPageOverrides(): void
    {
        $pageId = (int) input('page_id');

        if (!$pageId) {
            jsonResponse(['error' => 'page_id is required'], 400);
            return;
        }

        $overrides = $this->service->getPageOverrides($pageId);
        jsonResponse(['success' => true, 'overrides' => $overrides]);
    }

    /**
     * Get all overrides for a project with optional filters.
     */
    private function getProjectOverrides(): void
    {
        $projectId = (int) input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'project_id is required'], 400);
            return;
        }

        $filters = [];
        if (input('override_status')) $filters['override_status'] = input('override_status');
        if (input('module_name')) $filters['module_name'] = input('module_name');

        $overrides = $this->service->getProjectOverrides($projectId, $filters);
        jsonResponse(['success' => true, 'overrides' => $overrides]);
    }

    /**
     * Get override summary for a project.
     */
    private function getProjectSummary(): void
    {
        $projectId = (int) input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'project_id is required'], 400);
            return;
        }

        $summary = $this->service->getProjectSummary($projectId);
        jsonResponse(['success' => true, 'summary' => $summary]);
    }

    /**
     * Get override history for a page + module.
     */
    private function getHistory(): void
    {
        $pageId = (int) input('page_id');
        $moduleName = input('module_name');

        if (!$pageId || !$moduleName) {
            jsonResponse(['error' => 'page_id and module_name are required'], 400);
            return;
        }

        $history = $this->service->getHistory($pageId, $moduleName);
        jsonResponse(['success' => true, 'history' => $history]);
    }

    /**
     * Get available override statuses.
     */
    private function getStatuses(): void
    {
        $statuses = $this->service->getAvailableStatuses();
        jsonResponse(['success' => true, 'statuses' => $statuses]);
    }

    /**
     * Get override map for a project (for dashboard table rendering).
     */
    private function getOverrideMap(): void
    {
        $projectId = (int) input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'project_id is required'], 400);
            return;
        }

        $map = $this->service->getOverrideMap($projectId);
        $summary = $this->service->getProjectSummary($projectId);

        jsonResponse(['success' => true, 'map' => $map, 'summary' => $summary]);
    }
}
