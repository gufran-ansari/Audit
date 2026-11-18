<?php

namespace App\Modules\Reports;

use App\Middleware\ApiAuthMiddleware;
use App\Services\ActivityLogService;

/**
 * Report Controller
 * 
 * Handles report management (admin-side) via API calls.
 */
class ReportController
{
    private ReportService $service;
    private ApiAuthMiddleware $apiAuth;
    private ActivityLogService $activityLog;

    public function __construct()
    {
        $this->service = new ReportService();
        $this->apiAuth = new ApiAuthMiddleware(['auth' => true, 'csrf' => true, 'api' => true]);
        $this->activityLog = ActivityLogService::getInstance();
    }

    public function handle(): void
    {
        $this->apiAuth->handle();

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

        $this->activityLog->log(
            'report_created',
            "Report created for project",
            $projectId,
            ['slug' => $report['report_slug'], 'visibility' => $report['visibility']],
            'report',
            $report['id']
        );
        
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

        if ($success) {
            $repo = new ReportRepository();
            $report = $repo->findByProject($projectId);
            
            $this->activityLog->log(
                'report_visibility_changed',
                "Report visibility changed to {$visibility}",
                $projectId,
                ['visibility' => $visibility, 'slug' => $report['report_slug'] ?? null],
                'report',
                $report['id'] ?? null
            );
        }
        
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

        if ($success) {
            $repo = new ReportRepository();
            $report = $repo->findByProject($projectId);
            
            $this->activityLog->log(
                'report_slug_updated',
                "Report slug updated to {$slug}",
                $projectId,
                ['slug' => $slug],
                'report',
                $report['id'] ?? null
            );
        }
        
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
