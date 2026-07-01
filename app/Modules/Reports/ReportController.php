<?php

namespace App\Modules\Reports;

use App\Middleware\AuthMiddleware;

/**
 * Report Controller
 * 
 * Handles report management (admin-side) via API calls.
 */
class ReportController
{
    private ReportService $service;
    private AuthMiddleware $authMiddleware;

    public function __construct()
    {
        $this->service = new ReportService();
        $this->authMiddleware = new AuthMiddleware();
    }

    public function handle(): void
    {
        $this->authMiddleware->handle();

        $action = input('action', 'get');
        $method = isMethod('POST') ? 'POST' : 'GET';

        switch ($action) {
            case 'get':
                $this->getReport();
                break;

            case 'create':
                if ($method === 'POST') $this->createReport();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'visibility':
                if ($method === 'POST') $this->setVisibility();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'update_slug':
                if ($method === 'POST') $this->updateSlug();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'preview':
                $this->previewReport();
                break;

            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    private function getReport(): void
    {
        $projectId = (int) input('project_id');
        if (!$projectId) {
            jsonResponse(['error' => 'Project ID required'], 400);
            return;
        }

        $repo = new ReportRepository();
        $report = $repo->findByProject($projectId);

        jsonResponse(['success' => true, 'report' => $report]);
    }

    private function createReport(): void
    {
        $projectId = (int) input('project_id');
        $projectName = input('project_name', 'audit-report');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID required'], 400);
            return;
        }

        $report = $this->service->getOrCreate($projectId, $projectName);

        jsonResponse(['success' => true, 'report' => $report]);
    }

    private function setVisibility(): void
    {
        $projectId = (int) input('project_id');
        $visibility = input('visibility');

        if (!$projectId || !in_array($visibility, ['public', 'private'])) {
            jsonResponse(['error' => 'Project ID and valid visibility required'], 400);
            return;
        }

        $success = $this->service->setVisibility($projectId, $visibility);

        jsonResponse(['success' => $success]);
    }

    private function updateSlug(): void
    {
        $projectId = (int) input('project_id');
        $slug = input('slug');

        if (!$projectId || !$slug) {
            jsonResponse(['error' => 'Project ID and slug required'], 400);
            return;
        }

        $success = $this->service->updateSlug($projectId, $slug);

        jsonResponse(['success' => $success, 'message' => $success ? 'Slug updated' : 'Failed to update slug']);
    }

    private function previewReport(): void
    {
        $projectId = (int) input('project_id');
        if (!$projectId) {
            jsonResponse(['error' => 'Project ID required'], 400);
            return;
        }

        $data = $this->service->generateData($projectId);

        jsonResponse(['success' => true, 'data' => $data]);
    }
}
