<?php
/**
 * Public Report Page — Interactive Client-Facing SEO Report
 * 
 * URL: /report/client-name-a81x
 * 
 * No authentication required for public reports.
 * Read-only. No admin controls, no internal IDs, no system data exposed.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Modules\Reports\ReportService;

$slug = input('slug');

if (!$slug) {
    http_response_code(404);
    include __DIR__ . '/templates/report-404.php';
    exit;
}

$reportService = new ReportService();
$report = $reportService->getPublicReport($slug);

if (!$report) {
    http_response_code(404);
    include __DIR__ . '/templates/report-404.php';
    exit;
}

$projectId = (int) $report['project_id'];
$data = $reportService->generateData($projectId);

$hero = $data['hero'];
$overview = $data['overview'];
$modules = $data['modules'];
$pages = $data['pages'];
$summary = $data['summary'] ?? [];

// Strip internal IDs before exposing to public page
$publicPages = array_map(function ($p) {
    unset($p['id']);
    return $p;
}, $pages);

// Encode pages as JSON for JS-driven interactivity
$pagesJson = json_encode($publicPages, JSON_HEX_TAG | JSON_HEX_AMP);
$modulesJson = json_encode($modules, JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($hero['project_name']) ?> — SEO Audit Report</title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- html2pdf for client-side PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { 50:'#eef2ff',100:'#e0e7ff',500:'#6366f1',600:'#4f46e5',700:'#4338ca',800:'#3730a3' }
                    }
                }
            }
        }
    </script>
    <style>
        /* Status badge system — unified across all modules */
        .s-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 10px; border-radius:9999px; font-size:11px; font-weight:600; white-space:nowrap; }
        .s-pass { color:#15803d; background:#f0fdf4; border:1px solid #bbf7d0; }
        .s-warning { color:#a16207; background:#fefce8; border:1px solid #fef08a; }
        .s-fail { color:#dc2626; background:#fef2f2; border:1px solid #fecaca; }
        .s-error { color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; }
        .s-none { color:#6b7280; background:#f9fafb; border:1px solid #e5e7eb; }

        /* Expandable row animation */
        .expand-content { max-height:0; overflow:hidden; transition:max-height 0.3s ease-out, opacity 0.2s ease; opacity:0; }
        .expand-content.open { max-height:800px; opacity:1; transition:max-height 0.4s ease-in, opacity 0.3s ease; }

        /* Filter chips */
        .filter-chip { cursor:pointer; transition:all 0.15s ease; }
        .filter-chip.active { background:#4f46e5; color:#fff; border-color:#4f46e5; }
        .filter-chip:not(.active):hover { background:#eef2ff; border-color:#c7d2fe; }

        /* Sort indicators */
        .sort-btn .sort-icon { transition:transform 0.2s; }
        .sort-btn.asc .sort-icon { transform:rotate(0deg); }
        .sort-btn.desc .sort-icon { transform:rotate(180deg); }

        /* Smooth scroll */
        html { scroll-behavior:smooth; }

        /* Custom scrollbar */
        .custom-scroll::-webkit-scrollbar { width:6px; height:6px; }
        .custom-scroll::-webkit-scrollbar-track { background:#f1f5f9; border-radius:3px; }
        .custom-scroll::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:3px; }

        /* Health score ring */
        .health-ring { position:relative; width:100px; height:100px; }
        .health-ring svg { transform:rotate(-90deg); }

        /* Print styles */
        @media print {
            body { background:#fff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            header.sticky { position:static !important; box-shadow:none !important; border:none !important; }
            .no-print, .no-print * { display:none !important; }
            .print-only { display:block !important; }
            .filter-chip, .filter-chip-card, .module-filter-card { pointer-events:none; }
            #reportSearch, #clearSearch, .sort-btn .sort-icon, .expand-toggle, .pg-btn,
            #paginationControls, .mobile-expand-toggle i.bi-chevron-down, .mobile-expand-toggle i.bi-chevron-up { display:none !important; }
            .expand-content { max-height:none !important; opacity:1 !important; overflow:visible !important; }
            section, main, footer { break-inside:avoid; }
            table { font-size:10px !important; }
            .max-w-7xl { max-width:100% !important; padding:0 12px !important; }
            footer { border-top:1px solid #e5e7eb; margin-top:20px; }
        }

        /* Copy tooltip */
        .copy-tooltip { position:absolute; top:-32px; left:50%; transform:translateX(-50%); background:#1f2937; color:#fff; padding:4px 10px; border-radius:6px; font-size:11px; white-space:nowrap; opacity:0; transition:opacity 0.2s; pointer-events:none; }
        .copy-tooltip.show { opacity:1; }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

    <!-- Sticky Header -->
    <header class="bg-white/95 backdrop-blur-sm border-b border-gray-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-gradient-to-br from-brand-600 to-purple-600 rounded-lg flex items-center justify-center">
                        <i class="bi bi-clipboard-check text-white text-lg"></i>
                    </div>
                    <div class="hidden sm:block">
                        <p class="text-sm font-bold text-gray-800">Audit withGufran</p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($hero['project_name']) ?></p>
                    </div>
                </div>
                <nav class="hidden md:flex items-center gap-1 text-sm no-print">
                    <a href="#overview" class="px-3 py-1.5 rounded-md text-gray-600 hover:text-brand-600 hover:bg-brand-50 transition">Overview</a>
                    <a href="#summary" class="px-3 py-1.5 rounded-md text-gray-600 hover:text-brand-600 hover:bg-brand-50 transition">Summary</a>
                    <a href="#modules" class="px-3 py-1.5 rounded-md text-gray-600 hover:text-brand-600 hover:bg-brand-50 transition">Modules</a>
                    <a href="#results" class="px-3 py-1.5 rounded-md text-gray-600 hover:text-brand-600 hover:bg-brand-50 transition">Results</a>
                </nav>
                <!-- Export & Share Actions -->
                <div class="flex items-center gap-2 no-print">
                    <div class="relative" id="copyLinkWrap">
                        <button id="copyLinkBtn" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition" title="Copy report link">
                            <i class="bi bi-link-45deg"></i><span class="hidden lg:inline">Copy Link</span>
                        </button>
                        <span class="copy-tooltip" id="copyTooltip">Copied!</span>
                    </div>
                    <button id="exportCsvBtn" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition" title="Download CSV">
                        <i class="bi bi-filetype-csv"></i><span class="hidden lg:inline">CSV</span>
                    </button>
                    <button id="exportPdfBtn" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition" title="Download PDF">
                        <i class="bi bi-file-earmark-pdf"></i><span class="hidden lg:inline">PDF</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-brand-700 via-brand-600 to-purple-700 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8">
                <div class="flex-1">
                    <div class="inline-flex items-center px-3 py-1 bg-white/15 rounded-full text-xs font-medium mb-4 backdrop-blur-sm">
                        <i class="bi bi-shield-check mr-1.5"></i>SEO Audit Report
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-bold mb-2"><?= htmlspecialchars($hero['project_name']) ?></h1>
                    <p class="text-indigo-200 text-lg"><?= htmlspecialchars($hero['domain']) ?></p>
                    <div class="flex flex-wrap items-center gap-6 mt-6">
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center">
                                <i class="bi bi-link-45deg text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold"><?= $hero['total_urls'] ?></p>
                                <p class="text-xs text-indigo-200">Total URLs</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center">
                                <i class="bi bi-clipboard-check text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold"><?= $hero['audited_pages'] ?></p>
                                <p class="text-xs text-indigo-200">Audited</p>
                            </div>
                        </div>
                        <?php if ($hero['last_audit_date']): ?>
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center">
                                <i class="bi bi-calendar-check text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold"><?= date('M d', strtotime($hero['last_audit_date'])) ?></p>
                                <p class="text-xs text-indigo-200">Last Audit</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Health Score Ring -->
                <div class="flex-shrink-0 flex flex-col items-center">
                    <div class="health-ring">
                        <svg width="100" height="100" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="8"/>
                            <circle cx="50" cy="50" r="42" fill="none" stroke="white" stroke-width="8"
                                stroke-dasharray="<?= round(($summary['health_score'] ?? 0) / 100 * 263.9) ?> 263.9"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-3xl font-bold"><?= $summary['health_score'] ?? 0 ?>%</span>
                        </div>
                    </div>
                    <p class="text-xs text-indigo-200 mt-2">Health Score</p>
                </div>
            </div>
        </div>
    </section>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Overview Cards -->
        <section id="overview" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 mb-10">
            <div class="bg-white rounded-xl p-4 sm:p-5 border border-gray-200 text-center shadow-sm hover:shadow-md transition-shadow cursor-pointer filter-chip-card" data-filter="all">
                <p class="text-2xl font-bold text-gray-800"><?= $overview['total'] ?></p>
                <p class="text-xs text-gray-500 mt-1">Total Pages</p>
            </div>
            <div class="bg-white rounded-xl p-4 sm:p-5 border border-green-200 text-center shadow-sm hover:shadow-md transition-shadow cursor-pointer filter-chip-card" data-filter="pass">
                <p class="text-2xl font-bold text-green-600"><?= $overview['passed'] ?></p>
                <p class="text-xs text-gray-500 mt-1">Passed</p>
            </div>
            <div class="bg-white rounded-xl p-4 sm:p-5 border border-yellow-200 text-center shadow-sm hover:shadow-md transition-shadow cursor-pointer filter-chip-card" data-filter="warning">
                <p class="text-2xl font-bold text-yellow-600"><?= $overview['warnings'] ?></p>
                <p class="text-xs text-gray-500 mt-1">Warnings</p>
            </div>
            <div class="bg-white rounded-xl p-4 sm:p-5 border border-red-200 text-center shadow-sm hover:shadow-md transition-shadow cursor-pointer filter-chip-card" data-filter="fail">
                <p class="text-2xl font-bold text-red-600"><?= $overview['failed'] ?></p>
                <p class="text-xs text-gray-500 mt-1">Failed</p>
            </div>
            <div class="bg-white rounded-xl p-4 sm:p-5 border border-red-100 text-center shadow-sm hover:shadow-md transition-shadow cursor-pointer filter-chip-card" data-filter="error">
                <p class="text-2xl font-bold text-red-400"><?= $overview['errors'] ?></p>
                <p class="text-xs text-gray-500 mt-1">Errors</p>
            </div>
            <div class="bg-white rounded-xl p-4 sm:p-5 border border-gray-200 text-center shadow-sm hover:shadow-md transition-shadow cursor-pointer filter-chip-card" data-filter="none">
                <p class="text-2xl font-bold text-gray-400"><?= $overview['not_audited'] ?></p>
                <p class="text-xs text-gray-500 mt-1">Not Audited</p>
            </div>
        </section>

        <!-- Report Summary Section -->
        <?php if (!empty($summary)): ?>
        <section id="summary" class="mb-10">
            <h2 class="text-xl font-bold text-gray-800 mb-4"><i class="bi bi-graph-up mr-2 text-brand-600"></i>Report Summary</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- Overall Health -->
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center <?= ($summary['health_score'] ?? 0) >= 80 ? 'bg-green-100' : (($summary['health_score'] ?? 0) >= 50 ? 'bg-yellow-100' : 'bg-red-100') ?>">
                            <i class="bi bi-heart-pulse text-lg <?= ($summary['health_score'] ?? 0) >= 80 ? 'text-green-600' : (($summary['health_score'] ?? 0) >= 50 ? 'text-yellow-600' : 'text-red-600') ?>"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Overall Health</p>
                            <p class="text-lg font-bold text-gray-800"><?= $summary['health_score'] ?? 0 ?>%</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500"><?= $summary['total_passed'] ?? 0 ?> of <?= $summary['total_checks'] ?? 0 ?> checks passed</p>
                </div>

                <!-- Top Problems -->
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="bi bi-exclamation-triangle text-red-600 text-lg"></i>
                        </div>
                        <p class="text-xs text-gray-500">Top Problems</p>
                    </div>
                    <?php if (!empty($summary['top_problems'])): ?>
                    <div class="space-y-2">
                        <?php foreach (array_slice($summary['top_problems'], 0, 3) as $prob): ?>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-700"><i class="bi <?= htmlspecialchars($prob['icon']) ?> mr-1 text-brand-600"></i><?= htmlspecialchars($prob['label']) ?></span>
                            <span class="font-semibold text-red-600"><?= $prob['issue_count'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-green-600 font-medium">No critical problems found</p>
                    <?php endif; ?>
                </div>

                <!-- Most Common Issues -->
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="bi bi-list-check text-orange-600 text-lg"></i>
                        </div>
                        <p class="text-xs text-gray-500">Most Common Issues</p>
                    </div>
                    <?php if (!empty($summary['common_issues'])): ?>
                    <div class="space-y-2">
                        <?php foreach (array_slice($summary['common_issues'], 0, 3) as $issue): ?>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-700"><?= htmlspecialchars($issue['label']) ?></span>
                            <span class="s-badge <?= $issue['result_status'] === 'FAIL' ? 's-fail' : ($issue['result_status'] === 'WARNING' ? 's-warning' : 's-error') ?>"><?= $issue['cnt'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-green-600 font-medium">All clear</p>
                    <?php endif; ?>
                </div>

                <!-- Pages Needing Attention -->
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-10 h-10 bg-brand-100 rounded-lg flex items-center justify-center">
                            <i class="bi bi-flag text-brand-600 text-lg"></i>
                        </div>
                        <p class="text-xs text-gray-500">Needs Attention</p>
                    </div>
                    <?php if (!empty($summary['pages_needing_attention'])): ?>
                    <div class="space-y-2">
                        <?php foreach (array_slice($summary['pages_needing_attention'], 0, 3) as $p): ?>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-700 truncate max-w-[140px]" title="<?= htmlspecialchars($p['original_url']) ?>"><?= htmlspecialchars(strlen($p['original_url']) > 25 ? '...' . substr($p['original_url'], -22) : $p['original_url']) ?></span>
                            <span class="font-semibold text-red-600"><?= $p['fail_count'] ?> fails</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-green-600 font-medium">All pages healthy</p>
                    <?php endif; ?>
                </div>

            </div>
        </section>
        <?php endif; ?>

        <!-- Module Issue Breakdown -->
        <?php if (!empty($modules)): ?>
        <section id="modules" class="mb-10">
            <h2 class="text-xl font-bold text-gray-800 mb-4"><i class="bi bi-grid-3x3-gap mr-2 text-brand-600"></i>Issue Breakdown by Module</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <?php foreach ($modules as $mod): ?>
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm hover:shadow-md transition-shadow cursor-pointer module-filter-card" data-module="<?= htmlspecialchars($mod['module_name']) ?>">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-9 h-9 bg-brand-50 rounded-lg flex items-center justify-center">
                            <i class="bi <?= htmlspecialchars($mod['icon']) ?> text-brand-600"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($mod['label']) ?></h3>
                    </div>
                    <!-- Progress bar -->
                    <?php $passRate = $mod['total'] > 0 ? round(($mod['pass'] / $mod['total']) * 100) : 0; ?>
                    <div class="w-full h-2 bg-gray-100 rounded-full mb-3 overflow-hidden">
                        <div class="h-full rounded-full transition-all <?= $passRate >= 80 ? 'bg-green-500' : ($passRate >= 50 ? 'bg-yellow-500' : 'bg-red-500') ?>" style="width:<?= $passRate ?>%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500 mb-2">
                        <span><?= $passRate ?>% passed</span>
                        <span><?= $mod['total'] ?> total</span>
                    </div>
                    <div class="grid grid-cols-2 gap-1 text-xs">
                        <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span><span class="text-gray-600">Pass: <?= $mod['pass'] ?></span></div>
                        <?php if ($mod['warning'] > 0): ?><div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-500"></span><span class="text-gray-600">Warn: <?= $mod['warning'] ?></span></div><?php endif; ?>
                        <?php if ($mod['fail'] > 0): ?><div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span><span class="text-gray-600">Fail: <?= $mod['fail'] ?></span></div><?php endif; ?>
                        <?php if ($mod['error'] > 0): ?><div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-300"></span><span class="text-gray-600">Error: <?= $mod['error'] ?></span></div><?php endif; ?>
                    </div>
                    <?php if ($mod['issues'] > 0): ?>
                    <div class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-1">
                        <i class="bi bi-exclamation-circle text-red-500 text-xs"></i>
                        <span class="text-xs font-semibold text-red-600"><?= $mod['issues'] ?> issue<?= $mod['issues'] > 1 ? 's' : '' ?> found</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Detailed Results Section -->
        <?php if (!empty($pages)): ?>
        <section id="results" class="mb-10">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">

                <!-- Toolbar: Search, Filters, Sorting -->
                <div class="p-4 sm:p-6 border-b border-gray-200">
                    <div class="flex flex-col gap-4">
                        <!-- Title and count -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-bold text-gray-800">Detailed Results</h2>
                                <p class="text-sm text-gray-500 mt-0.5">
                                    <span id="resultCount"><?= count($pages) ?></span> pages audited
                                </p>
                            </div>
                            <!-- Search -->
                            <div class="relative w-full sm:w-72">
                                <input type="text" id="reportSearch" placeholder="Search URL, H1, meta title..."
                                    class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent bg-gray-50 focus:bg-white transition">
                                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <button id="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 hidden">
                                    <i class="bi bi-x-lg text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Status Filters -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-medium text-gray-500 mr-1">Status:</span>
                            <button class="filter-chip active text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-status="all">All</button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-status="pass">
                                <i class="bi bi-check-circle mr-0.5"></i>Pass
                            </button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-status="warning">
                                <i class="bi bi-exclamation-triangle mr-0.5"></i>Warning
                            </button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-status="fail">
                                <i class="bi bi-x-circle mr-0.5"></i>Failed
                            </button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-status="error">
                                <i class="bi bi-exclamation-octagon mr-0.5"></i>Error
                            </button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-status="none">
                                <i class="bi bi-dash-circle mr-0.5"></i>Not Audited
                            </button>
                        </div>

                        <!-- Module Filters -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-medium text-gray-500 mr-1">Module:</span>
                            <button class="filter-chip active text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-module-filter="all">All</button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-module-filter="h1"><i class="bi bi-tag mr-0.5"></i>H1</button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-module-filter="meta"><i class="bi bi-card-text mr-0.5"></i>Meta</button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-module-filter="noindex"><i class="bi bi-eye-slash mr-0.5"></i>Noindex</button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-module-filter="faq"><i class="bi bi-question-circle mr-0.5"></i>FAQ</button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-module-filter="lorem"><i class="bi bi-file-text mr-0.5"></i>Lorem</button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-module-filter="staging"><i class="bi bi-hdd-network mr-0.5"></i>Staging</button>
                            <button class="filter-chip text-xs px-3 py-1.5 rounded-full border border-gray-300 font-medium" data-module-filter="word"><i class="bi bi-search mr-0.5"></i>Word</button>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="hidden lg:block custom-scroll overflow-x-auto">
                    <table class="w-full text-sm" id="resultsTable">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase sort-btn cursor-pointer select-none" data-sort="url">
                                    <span class="flex items-center gap-1">URL <i class="bi bi-arrow-down-up sort-icon text-gray-400"></i></span>
                                </th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase sort-btn cursor-pointer select-none" data-sort="h1">
                                    <span class="flex items-center justify-center gap-1">H1 <i class="bi bi-arrow-down-up sort-icon text-gray-400"></i></span>
                                </th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase sort-btn cursor-pointer select-none" data-sort="meta">
                                    <span class="flex items-center justify-center gap-1">Meta <i class="bi bi-arrow-down-up sort-icon text-gray-400"></i></span>
                                </th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase sort-btn cursor-pointer select-none" data-sort="noindex">
                                    <span class="flex items-center justify-center gap-1">Noindex <i class="bi bi-arrow-down-up sort-icon text-gray-400"></i></span>
                                </th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase sort-btn cursor-pointer select-none" data-sort="faq">
                                    <span class="flex items-center justify-center gap-1">FAQ <i class="bi bi-arrow-down-up sort-icon text-gray-400"></i></span>
                                </th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase sort-btn cursor-pointer select-none" data-sort="lorem">
                                    <span class="flex items-center justify-center gap-1">Lorem <i class="bi bi-arrow-down-up sort-icon text-gray-400"></i></span>
                                </th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase sort-btn cursor-pointer select-none" data-sort="staging">
                                    <span class="flex items-center justify-center gap-1">Staging <i class="bi bi-arrow-down-up sort-icon text-gray-400"></i></span>
                                </th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase sort-btn cursor-pointer select-none" data-sort="word">
                                    <span class="flex items-center justify-center gap-1">Word <i class="bi bi-arrow-down-up sort-icon text-gray-400"></i></span>
                                </th>
                                <th class="px-3 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="resultsBody" class="divide-y divide-gray-100">
                            <!-- Rendered by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="lg:hidden" id="mobileResults">
                    <!-- Rendered by JavaScript -->
                </div>

                <!-- Pagination -->
                <div class="px-4 sm:px-6 py-4 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <p class="text-sm text-gray-500" id="paginationInfo">Showing 1–25 of <?= count($pages) ?></p>
                    <div class="flex items-center gap-2" id="paginationControls">
                        <!-- Rendered by JavaScript -->
                    </div>
                </div>

                <!-- No results message -->
                <div id="noResults" class="hidden px-6 py-12 text-center">
                    <i class="bi bi-search text-4xl text-gray-300"></i>
                    <p class="text-gray-500 mt-3 text-sm">No pages match your current filters.</p>
                    <button onclick="resetFilters()" class="mt-3 text-sm text-brand-600 font-medium hover:underline">Reset Filters</button>
                </div>

            </div>
        </section>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 bg-gradient-to-br from-brand-600 to-purple-600 rounded flex items-center justify-center">
                    <i class="bi bi-clipboard-check text-white text-xs"></i>
                </div>
                <p class="text-xs text-gray-500"><strong class="text-gray-700">Audit withGufran</strong></p>
            </div>
            <p class="text-xs text-gray-400">Powered by <strong class="text-gray-600">Codezyners</strong></p>
        </div>
        <?php if ($hero['last_audit_date']): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-3 pt-3 border-t border-gray-100 text-center">
            <p class="text-[11px] text-gray-400"><i class="bi bi-arrow-repeat mr-1"></i>Last updated: <?= date('F d, Y \a\t h:i A', strtotime($hero['last_audit_date'])) ?></p>
        </div>
        <?php endif; ?>
    </footer>

    <!-- Page Data (JSON for JavaScript) -->
    <script>
        const REPORT_DATA = <?= $pagesJson ?>;
        const MODULE_DATA = <?= $modulesJson ?>;
        const REPORT_SLUG = <?= json_encode($slug) ?>;
        const REPORT_META = {
            projectName: <?= json_encode($hero['project_name']) ?>,
            domain: <?= json_encode($hero['domain']) ?>,
            lastAudit: <?= json_encode($hero['last_audit_date']) ?>,
            totalUrls: <?= json_encode($hero['total_urls']) ?>,
            auditedPages: <?= json_encode($hero['audited_pages']) ?>,
            healthScore: <?= json_encode($summary['health_score'] ?? 0) ?>
        };
    </script>
    <script src="assets/js/report.js"></script>
    <script src="assets/js/report-export.js"></script>

</body>
</html>
