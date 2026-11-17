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
    case 'pdf':
        exportPdf($data, $report);
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

/**
 * Export audit results as PDF using FPDF.
 */
function exportPdf(array $data, array $report): void
{
    require_once __DIR__ . '/../libs/fpdf.php';

    $projectName = preg_replace('/[^a-z0-9-]/i', '-', $report['project_name']);
    $filename = $projectName . '-audit-' . date('Y-m-d') . '.pdf';

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetCreator('Audit withGufran');
    $pdf->SetAuthor('Codezyners');
    $pdf->SetTitle($report['project_name'] . ' - SEO Audit Report');
    $pdf->SetSubject('SEO Audit Report');
    $pdf->SetKeywords('SEO, audit, report');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $hero = $data['hero'] ?? [];
    $overview = $data['overview'] ?? [];
    $modules = $data['modules'] ?? [];
    $pages = $data['pages'] ?? [];
    $summary = $data['summary'] ?? [];

    // Header
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->SetTextColor(79, 70, 229);
    $pdf->Cell(0, 8, 'Audit withGufran', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->Cell(0, 5, 'SEO Audit Report', 0, 1, 'C');
    $pdf->Line(15, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);
    $pdf->Ln(8);

    // Project Info
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetTextColor(55, 65, 81);
    $pdf->Cell(0, 6, $hero['project_name'] ?? '', 0, 1);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->Cell(0, 5, 'Domain: ' . ($hero['domain'] ?? ''), 0, 1);
    if (!empty($hero['last_audit_date'])) {
        $pdf->Cell(0, 5, 'Audit Date: ' . date('M d, Y', strtotime($hero['last_audit_date'])), 0, 1);
    }
    $pdf->Cell(0, 5, 'Total URLs: ' . ($hero['total_urls'] ?? 0) . ' | Audited: ' . ($hero['audited_pages'] ?? 0) . ' | Health Score: ' . ($summary['health_score'] ?? 0) . '%', 0, 1);
    $pdf->Ln(4);

    // Overview Summary
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(55, 65, 81);
    $pdf->Cell(0, 6, 'Overview Summary', 0, 1);
    $pdf->Ln(2);

    $pdf->SetFont('Helvetica', 'B', 7);
    $pdf->SetFillColor(79, 70, 229);
    $pdf->SetTextColor(255, 255, 255);
    $colWidths = [28, 28, 28, 28, 28, 28];
    $headers = ['Total', 'Passed', 'Warnings', 'Failed', 'Errors', 'Not Audited'];
    foreach ($headers as $i => $h) {
        $pdf->Cell($colWidths[$i], 5, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();

    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(55, 65, 81);
    $pdf->SetFillColor(249, 250, 251);
    $vals = [
        $overview['total'] ?? 0,
        $overview['passed'] ?? 0,
        $overview['warnings'] ?? 0,
        $overview['failed'] ?? 0,
        $overview['errors'] ?? 0,
        $overview['not_audited'] ?? 0,
    ];
    foreach ($vals as $i => $v) {
        $pdf->Cell($colWidths[$i], 5, (string)$v, 1, 0, 'C', true);
    }
    $pdf->Ln(8);

    // Module Breakdown
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(55, 65, 81);
    $pdf->Cell(0, 6, 'Issue Breakdown by Module', 0, 1);
    $pdf->Ln(2);

    if (!empty($modules)) {
        $pdf->SetFont('Helvetica', 'B', 7);
        $pdf->SetFillColor(79, 70, 229);
        $pdf->SetTextColor(255, 255, 255);
        $modColWidths = [55, 22, 22, 22, 22, 22];
        $modHeaders = ['Module', 'Total', 'Pass', 'Warning', 'Fail', 'Error'];
        foreach ($modHeaders as $i => $h) {
            $pdf->Cell($modColWidths[$i], 5, $h, 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(55, 65, 81);
        $pdf->SetFillColor(249, 250, 251);
        foreach ($modules as $mod) {
            $pdf->Cell($modColWidths[0], 5, htmlspecialchars($mod['label'] ?? $mod['module_name']), 1, 0, 'L', true);
            $pdf->Cell($modColWidths[1], 5, (string)($mod['total'] ?? 0), 1, 0, 'C', true);
            $pdf->Cell($modColWidths[2], 5, (string)($mod['pass'] ?? 0), 1, 0, 'C', true);
            $pdf->Cell($modColWidths[3], 5, (string)($mod['warning'] ?? 0), 1, 0, 'C', true);
            $pdf->Cell($modColWidths[4], 5, (string)($mod['fail'] ?? 0), 1, 0, 'C', true);
            $pdf->Cell($modColWidths[5], 5, (string)($mod['error'] ?? 0), 1, 0, 'C', true);
            $pdf->Ln();
        }
    }
    $pdf->Ln(4);

    // Detailed Results
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(55, 65, 81);
    $pdf->Cell(0, 6, 'Detailed Results', 0, 1);
    $pdf->Ln(2);

    if (!empty($pages)) {
        $pdf->SetFont('Helvetica', 'B', 6);
        $pdf->SetFillColor(79, 70, 229);
        $pdf->SetTextColor(255, 255, 255);
        $detColWidths = [65, 15, 15, 18, 15, 15, 18, 18];
        $detHeaders = ['URL', 'H1', 'Meta', 'Noindex', 'FAQ', 'Lorem', 'Staging', 'Word'];
        foreach ($detHeaders as $i => $h) {
            $pdf->Cell($detColWidths[$i], 5, $h, 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('Helvetica', '', 5.5);
        $pdf->SetTextColor(55, 65, 81);
        $pdf->SetFillColor(249, 250, 251);

        $maxRows = min(count($pages), 400);
        for ($i = 0; $i < $maxRows; $i++) {
            $p = $pages[$i];
            $url = $p['original_url'] ?? '';
            if (strlen($url) > 80) $url = substr($url, 0, 77) . '...';

            $pdf->Cell($detColWidths[0], 5, htmlspecialchars($url), 1, 0, 'L', true);
            $pdf->Cell($detColWidths[1], 5, formatStatusShort($p['h1_status'] ?? null), 1, 0, 'C', true);
            $pdf->Cell($detColWidths[2], 5, formatStatusShort($p['meta_status'] ?? null), 1, 0, 'C', true);
            $pdf->Cell($detColWidths[3], 5, formatStatusShort($p['noindex_status'] ?? null), 1, 0, 'C', true);
            $pdf->Cell($detColWidths[4], 5, formatStatusShort($p['faq_status'] ?? null), 1, 0, 'C', true);
            $pdf->Cell($detColWidths[5], 5, formatStatusShort($p['lorem_status'] ?? null), 1, 0, 'C', true);
            $pdf->Cell($detColWidths[6], 5, formatStatusShort($p['staging_status'] ?? null), 1, 0, 'C', true);
            $pdf->Cell($detColWidths[7], 5, formatStatusShort($p['word_status'] ?? null), 1, 0, 'C', true);
            $pdf->Ln();

            // Check if we need a new page
            if ($pdf->GetY() > 270) {
                $pdf->AddPage();
                // Re-draw header row
                $pdf->SetFont('Helvetica', 'B', 6);
                $pdf->SetFillColor(79, 70, 229);
                $pdf->SetTextColor(255, 255, 255);
                foreach ($detHeaders as $j => $h) {
                    $pdf->Cell($detColWidths[$j], 5, $h, 1, 0, 'C', true);
                }
                $pdf->Ln();
                $pdf->SetFont('Helvetica', '', 5.5);
                $pdf->SetTextColor(55, 65, 81);
                $pdf->SetFillColor(249, 250, 251);
            }
        }

        if (count($pages) > $maxRows) {
            $pdf->SetFont('Helvetica', 'I', 6);
            $pdf->SetTextColor(107, 114, 128);
            $pdf->Cell(0, 6, 'Showing ' . $maxRows . ' of ' . count($pages) . ' pages. Full results available online.', 0, 1, 'C');
        }
    }

    // Footer
    $pdf->Ln(8);
    $pdf->SetFont('Helvetica', 'I', 5.5);
    $pdf->SetTextColor(156, 163, 175);
    $pdf->Cell(0, 4, 'Generated by Audit withGufran | Powered by Codezyners', 0, 1, 'C');
    $pdf->Cell(0, 4, 'Report URL: ' . 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 0, 1, 'C');

    $pdf->Output('D', $filename);
    exit;
}

/**
 * Format status for PDF (short version).
 */
function formatStatusShort(?string $status): string
{
    if (!$status) return '—';
    $map = ['PASS' => '✓', 'WARNING' => '!', 'FAIL' => '✗', 'ERROR' => '✗✗'];
    return $map[$status] ?? '—';
}
