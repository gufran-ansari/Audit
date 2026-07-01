<?php
/**
 * Public Report Data API
 * 
 * Provides paginated, cached report data for large datasets.
 * No auth required (public reports only).
 * Read-only — no mutations.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\Reports\ReportService;
use App\Modules\Reports\ReportDataLoader;

$slug = input('slug');
$action = input('action', 'pages');

if (!$slug) {
    jsonResponse(['error' => 'Slug required'], 400);
}

$reportService = new ReportService();
$report = $reportService->getPublicReport($slug);

if (!$report) {
    jsonResponse(['error' => 'Report not found or private'], 404);
}

$projectId = (int) $report['project_id'];

switch ($action) {
    case 'pages':
        $page = max(1, (int) input('page', 1));
        $perPage = min(100, max(10, (int) input('per_page', 50)));

        $loader = new ReportDataLoader();
        $data = $loader->loadPaginated($projectId, $page, $perPage);

        jsonResponse(['success' => true, 'data' => $data]);
        break;

    case 'overview':
        $loader = new ReportDataLoader();
        $data = $loader->loadPaginated($projectId, 1, 1);
        // Return only overview data, not pages
        unset($data['pages'], $data['pagination']);
        jsonResponse(['success' => true, 'data' => $data]);
        break;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
