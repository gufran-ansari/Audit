<?php
/**
 * Report Export Routes (public, no auth required)
 * 
 * Handles CSV and PDF export for public reports.
 * Validates report slug and visibility before exporting.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\Reports\ReportService;
use App\Modules\Reports\ReportGenerator;

$slug = input('slug');
$format = input('format', 'csv');

if (!$slug) {
    http_response_code(400);
    echo json_encode(['error' => 'Slug required']);
    exit;
}

$reportService = new ReportService();
$report = $reportService->getPublicReport($slug);

if (!$report) {
    http_response_code(404);
    echo json_encode(['error' => 'Report not found or private']);
    exit;
}

$projectId = (int) $report['project_id'];
$data = $reportService->generateData($projectId);

switch ($format) {
    case 'csv':
        exportCsv($data, $report);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid format']);
        exit;
}

/**
 * Export audit results as CSV.
 */
function exportCsv(array $data, array $report): void
{
    $projectName = preg_replace('/[^a-z0-9-]/i', '-', $report['project_name']);
    $filename = $projectName . '-audit-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");

    // Header row
    fputcsv($output, [
        'URL',
        'H1 Status',
        'Meta Status',
        'Noindex Status',
        'FAQ Status',
        'Lorem Status',
        'Staging Status',
        'Word Occurrence Status',
        'Last Audited'
    ]);

    // Data rows
    $pages = $data['pages'] ?? [];
    foreach ($pages as $page) {
        fputcsv($output, [
            $page['original_url'] ?? '',
            formatStatus($page['h1_status'] ?? null),
            formatStatus($page['meta_status'] ?? null),
            formatStatus($page['noindex_status'] ?? null),
            formatStatus($page['faq_status'] ?? null),
            formatStatus($page['lorem_status'] ?? null),
            formatStatus($page['staging_status'] ?? null),
            formatStatus($page['word_status'] ?? null),
            $page['last_audited'] ?? ''
        ]);
    }

    fclose($output);
    exit;
}

function formatStatus(?string $status): string
{
    if (!$status) return 'Not Audited';
    return ucfirst(strtolower($status));
}
