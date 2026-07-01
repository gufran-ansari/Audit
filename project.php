<?php
/**
 * Project Details Page
 * 
 * Display project workspace and audit results
 */

require __DIR__ . '/app/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Modules\Projects\ProjectService;
use App\Modules\Sitemaps\SitemapService;

// Protect this route
$auth = new AuthMiddleware();
$auth->handle();

// Get current user
$authService = new AuthService();
$currentUser = $authService->user();

// Get project UUID from query string
$uuid = input('uuid');

if (!$uuid) {
    redirect('/projects.php');
    exit;
}

// Get project details
$projectService = new ProjectService();
$project = $projectService->getProjectByUuid($uuid);

if (!$project) {
    flash('error', 'Project not found or access denied');
    redirect('/projects.php');
    exit;
}

// Get sitemap statistics
$sitemapService = new SitemapService();
$sitemapStats = $sitemapService->getSitemapStats($project['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($project['project_name']) ?> - Audit withGufran</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-50 font-sans antialiased overflow-hidden">
    
    <!-- Main Container -->
    <div class="flex h-screen">
        
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 transform transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0">
            
            <!-- Sidebar Header -->
            <div class="flex items-center justify-between h-16 px-6 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-lg flex items-center justify-center">
                        <i class="bi bi-clipboard-check text-white text-lg"></i>
                    </div>
                    <h1 class="text-lg font-bold text-gray-800">Audit withGufran</h1>
                </div>
                <button id="closeSidebar" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="flex-1 px-4 py-6 overflow-y-auto">
                <div class="space-y-1">
                    <a href="dashboard.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-speedometer2 text-lg mr-3"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="projects.php" class="nav-item active flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-folder text-lg mr-3"></i>
                        <span>Projects</span>
                    </a>
                </div>
            </nav>
            
            <!-- User Profile Section -->
            <div class="border-t border-gray-200 p-4">
                <div class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-gray-50">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-full flex items-center justify-center">
                        <i class="bi bi-person text-white text-lg"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($currentUser['name']) ?></p>
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars(ucfirst($currentUser['role'])) ?></p>
                    </div>
                    <a href="/routes/auth.php?action=logout" class="text-gray-400 hover:text-gray-600" title="Logout">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
            
        </aside>
        
        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            
            <!-- Topbar -->
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
                <div class="flex items-center space-x-4">
                    <button id="openSidebar" class="lg:hidden text-gray-500 hover:text-gray-700">
                        <i class="bi bi-list text-2xl"></i>
                    </button>
                    <a href="projects.php" class="text-gray-500 hover:text-gray-700">
                        <i class="bi bi-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($project['project_name']) ?></h2>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($project['primary_domain']) ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button data-notif-trigger class="relative text-gray-500 hover:text-gray-700" title="Notifications">
                        <i class="bi bi-bell text-xl"></i>
                        <span data-notif-badge class="hidden absolute -top-1 -right-1 min-w-[16px] h-4 px-1 bg-red-500 rounded-full text-xs text-white flex items-center justify-center"></span>
                    </button>
                    <button id="themeToggle" class="text-gray-500 hover:text-gray-700" title="Toggle theme">
                        <i class="bi bi-moon text-xl"></i>
                    </button>
                </div>
            </header>
            
            <!-- Main Workspace -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
                
                <!-- Project Info Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total URLs</p>
                                <p class="text-3xl font-bold text-gray-800 mt-2"><?= $project['stats']['total_urls'] ?></p>
                            </div>
                            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                                <i class="bi bi-link-45deg text-indigo-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Passed</p>
                                <p class="text-3xl font-bold text-green-600 mt-2"><?= $project['stats']['passed'] ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="bi bi-check-circle text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Failed</p>
                                <p class="text-3xl font-bold text-red-600 mt-2"><?= $project['stats']['failed'] ?></p>
                            </div>
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="bi bi-x-circle text-red-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Status</p>
                                <p class="text-sm font-semibold text-gray-800 mt-3 capitalize"><?= htmlspecialchars($project['project_status']) ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="bi bi-info-circle text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Workspace Tabs -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    
                    <!-- Tab Navigation -->
                    <div class="px-6 pt-4 border-b border-gray-200">
                        <div class="flex items-center gap-4">
                            <button id="sitemapsTab" class="workspace-tab active px-4 py-3 text-sm font-medium border-b-2 border-indigo-600 text-indigo-600">
                                <i class="bi bi-diagram-3 mr-2"></i>
                                Sitemaps
                            </button>
                            <button id="urlsTab" class="workspace-tab px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-800">
                                <i class="bi bi-link-45deg mr-2"></i>
                                URLs
                            </button>
                        </div>
                    </div>
                    
                    <!-- Sitemaps Tab Content -->
                    <div id="sitemapsTabContent" class="workspace-tab-content">
                        <!-- Sitemap Section Header -->
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800">Sitemaps</h4>
                                    <p class="text-sm text-gray-600 mt-1">Discover and manage sitemaps for this project</p>
                                </div>
                                <div class="flex gap-2">
                                    <button id="discoverSitemapsBtn" class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg hover:opacity-90 transition-opacity">
                                        <i class="bi bi-search mr-2"></i>
                                        Auto-Discover
                                    </button>
                                    <button id="addSitemapBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                        <i class="bi bi-plus-lg mr-2"></i>
                                        Add Manually
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sitemap Stats -->
                        <?php if ($sitemapStats['total_sitemaps'] > 0): ?>
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-gray-800"><?= $sitemapStats['total_sitemaps'] ?></p>
                                    <p class="text-xs text-gray-600 mt-1">Total Sitemaps</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-green-600"><?= $sitemapStats['validated'] ?></p>
                                    <p class="text-xs text-gray-600 mt-1">Validated</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-red-600"><?= $sitemapStats['invalid'] ?></p>
                                    <p class="text-xs text-gray-600 mt-1">Invalid</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-orange-600"><?= $sitemapStats['unreachable'] ?></p>
                                    <p class="text-xs text-gray-600 mt-1">Unreachable</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-indigo-600"><?= $sitemapStats['total_urls'] ?></p>
                                    <p class="text-xs text-gray-600 mt-1">Total URLs</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Sitemaps List -->
                        <div class="p-6">
                            <div id="sitemapsContainer">
                                <!-- Sitemaps will be loaded here -->
                                <div class="text-center py-8">
                                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-3">
                                        <i class="bi bi-diagram-3 text-3xl text-gray-400"></i>
                                    </div>
                                    <p class="text-sm text-gray-600">Loading sitemaps...</p>
                                </div>
                            </div>
                            
                            <!-- Empty State -->
                            <div id="sitemapsEmptyState" class="hidden text-center py-8">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-3">
                                    <i class="bi bi-diagram-3 text-3xl text-gray-400"></i>
                                </div>
                                <h5 class="text-base font-semibold text-gray-800 mb-2">No Sitemaps Yet</h5>
                                <p class="text-sm text-gray-600 mb-4">Discover sitemaps automatically or add them manually</p>
                                <button onclick="document.getElementById('discoverSitemapsBtn').click()" class="px-5 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg hover:opacity-90 transition-opacity">
                                    <i class="bi bi-search mr-2"></i>
                                    Discover Sitemaps
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- URLs Tab Content -->
                    <div id="urlsTabContent" class="workspace-tab-content hidden">
                        <!-- URLs Section Header -->
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800">Pages & URLs</h4>
                                    <p class="text-sm text-gray-600 mt-1">All URLs imported from sitemaps</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <!-- Search -->
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            id="pagesSearchInput" 
                                            placeholder="Search URLs..." 
                                            class="pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 w-64"
                                        >
                                        <i class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                    </div>
                                    
                                    <!-- Sitemap Filter -->
                                    <select id="sitemapFilterSelect" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="">All Sitemaps</option>
                                        <!-- Will be populated dynamically -->
                                    </select>
                                    
                                    <!-- Status Filter -->
                                    <select id="urlStatusFilterSelect" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="">All Statuses</option>
                                        <option value="active">Active</option>
                                        <option value="disabled">Disabled</option>
                                        <option value="removed">Removed</option>
                                        <option value="pending_audit">Pending Audit</option>
                                    </select>
                                    
                                    <!-- Word Occurrence Filter -->
                                    <select id="wordResultFilterSelect" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="">All Word Results</option>
                                        <option value="found">Keyword Found</option>
                                        <option value="missing">Keyword Missing</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Audit Module Controls -->
                        <div class="px-6 py-4 bg-white border-b border-gray-200">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div class="flex flex-wrap items-center gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" id="h1AuditCheckbox" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" checked>
                                        <span class="text-sm font-medium text-gray-700">
                                            <i class="bi bi-tag mr-1"></i>
                                            H1
                                        </span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" id="metaAuditCheckbox" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" checked>
                                        <span class="text-sm font-medium text-gray-700">
                                            <i class="bi bi-card-text mr-1"></i>
                                            Meta
                                        </span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" id="noindexAuditCheckbox" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" checked>
                                        <span class="text-sm font-medium text-gray-700">
                                            <i class="bi bi-eye-slash mr-1"></i>
                                            Noindex
                                        </span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" id="faqAuditCheckbox" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" checked>
                                        <span class="text-sm font-medium text-gray-700">
                                            <i class="bi bi-question-circle mr-1"></i>
                                            FAQ
                                        </span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" id="loremAuditCheckbox" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" checked>
                                        <span class="text-sm font-medium text-gray-700">
                                            <i class="bi bi-file-text mr-1"></i>
                                            Lorem
                                        </span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" id="stagingAuditCheckbox" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" checked>
                                        <span class="text-sm font-medium text-gray-700">
                                            <i class="bi bi-hdd-network mr-1"></i>
                                            Staging
                                        </span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" id="wordAuditCheckbox" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                        <span class="text-sm font-medium text-gray-700">
                                            <i class="bi bi-search mr-1"></i>
                                            Word Occurrence
                                        </span>
                                    </label>
                                </div>
                                <button id="runAuditsBtn" class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg hover:opacity-90 transition-opacity">
                                    <i class="bi bi-play-circle mr-2"></i>
                                    Run Selected Audits
                                </button>
                                <button id="runSelectedPagesBtn" class="px-4 py-2 text-sm font-medium text-indigo-700 bg-white border border-indigo-300 rounded-lg hover:bg-indigo-50 transition-colors">
                                    <i class="bi bi-check2-square mr-2"></i>
                                    Run Selected Pages
                                </button>
                            </div>

                            <!-- Word Occurrence Keyword Input -->
                            <div id="wordOccurrenceInputArea" class="mt-4 pt-4 border-t border-gray-100 hidden">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="bi bi-search mr-1"></i>
                                    Word Occurrence Keywords
                                </label>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <input
                                        type="text"
                                        id="wordKeywordInput"
                                        placeholder="e.g. solar table lamp, free shipping, contact us"
                                        class="flex-1 px-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    Enter a single keyword, a phrase, or multiple comma-separated keywords. Required when Word Occurrence is selected.
                                </p>
                                <!-- Search History / Suggestions -->
                                <div id="wordSearchHistory" class="mt-2 flex flex-wrap gap-2"></div>
                            </div>
                        </div>
                        
                        <!-- Job Status Panel -->
                        <div id="jobStatusWrapper" class="px-6 py-3 bg-white border-b border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <h5 class="text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <i class="bi bi-lightning mr-1"></i>Background Jobs
                                </h5>
                                <button onclick="loadJobStatusPanel()" class="text-xs text-gray-400 hover:text-indigo-600" title="Refresh">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                            <div id="jobStatusPanel">
                                <p class="text-xs text-gray-400 text-center py-1">Loading...</p>
                            </div>
                        </div>
                        
                        <!-- Retry / Resume Info Panel -->
                        <div id="retryInfoPanel" class="px-6 py-3 bg-orange-50 border-b border-orange-100 hidden">
                            <!-- Populated by loadRetryInfo() -->
                        </div>
                        
                        <!-- Processing Health Panel -->
                        <div id="healthPanel" class="px-6 py-2 bg-gray-50 border-b border-gray-200">
                            <!-- Populated by loadHealthPanel() -->
                        </div>
                        
                        <!-- URL Stats -->
                        <div id="pagesStats" class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <!-- Stats will be loaded here -->
                            <div class="text-center py-2">
                                <p class="text-sm text-gray-600">Loading statistics...</p>
                            </div>
                        </div>
                        
                        <!-- URLs Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-4 py-3 text-left">
                                            <input type="checkbox" id="selectAllPages" />
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            URL
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            H1
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Meta
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Noindex
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            FAQ
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Lorem
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Staging
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Word
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="pagesTableBody" class="bg-white divide-y divide-gray-200">
                                    <!-- URLs will be loaded here -->
                                    <tr>
                                        <td colspan="10" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mb-4"></div>
                                                <p class="text-sm text-gray-600">Loading URLs...</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div id="pagesPagination" class="px-6 py-4 border-t border-gray-200 flex items-center justify-center gap-2 hidden">
                            <!-- Pagination will be loaded here -->
                        </div>
                    </div>
                    
                </div>
                
                <!-- Project Activity Log -->
                <div class="mt-6 bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h4 class="text-lg font-semibold text-gray-800">Activity Log</h4>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <?php 
                            $activities = $projectService->getActivityLogs($project['id'], 10);
                            if (empty($activities)): 
                            ?>
                                <p class="text-sm text-gray-500 text-center py-4">No activity yet</p>
                            <?php else: ?>
                                <?php foreach ($activities as $activity): ?>
                                    <div class="flex items-start gap-3 pb-4 border-b border-gray-100 last:border-0">
                                        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                                            <i class="bi bi-clock text-indigo-600 text-sm"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($activity['activity_description']) ?></p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                by <?= htmlspecialchars($activity['user_name']) ?> • 
                                                <?= timeAgo($activity['created_at']) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            </main>
            
        </div>
        
    </div>
    
    <!-- Sidebar Overlay for Mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>
    
    <!-- Add Sitemap Modal -->
    <div id="addSitemapModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            <div class="px-6 py-5 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-800">Add Sitemap Manually</h3>
                    <button id="closeAddSitemapModal" class="text-gray-400 hover:text-gray-600">
                        <i class="bi bi-x-lg text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="px-6 py-6">
                <form id="addSitemapForm">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Sitemap URL <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="sitemapUrlInput"
                            name="sitemap_url"
                            placeholder="https://example.com/sitemap.xml"
                            class="w-full px-4 py-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="bi bi-info-circle mr-1"></i>
                            Enter the full URL of your sitemap XML file
                        </p>
                        <p id="sitemapUrlError" class="text-xs text-red-600 mt-1 hidden"></p>
                    </div>
                    <div class="mt-4 hidden" id="addSitemapValidationMessage">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <p class="text-sm text-red-700">
                                <i class="bi bi-exclamation-circle mr-2"></i>
                                <span id="addSitemapValidationText"></span>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 hidden" id="addSitemapSuccessMessage">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <p class="text-sm text-green-700">
                                <i class="bi bi-check-circle mr-2"></i>
                                Sitemap added successfully!
                            </p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="px-6 py-5 border-t border-gray-200 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
                <button id="cancelAddSitemapModal" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" id="addSitemapSubmitBtn" form="addSitemapForm" class="px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg hover:opacity-90">
                    <i class="bi bi-plus-lg mr-2"></i>
                    Add Sitemap
                </button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Project data
        const projectId = <?= $project['id'] ?>;
        const projectUuid = '<?= $project['uuid'] ?>';
    </script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/project-sitemaps.js"></script>
    <script src="assets/js/project-pages.js"></script>
    <script src="assets/js/project-audits.js"></script>
    
    <script>
        // Tab switching functionality
        const tabs = {
            sitemaps: {
                btn: document.getElementById('sitemapsTab'),
                content: document.getElementById('sitemapsTabContent')
            },
            urls: {
                btn: document.getElementById('urlsTab'),
                content: document.getElementById('urlsTabContent')
            }
        };
        
        // Set active tab
        function setActiveTab(tabName) {
            // Remove active class from all tabs
            Object.keys(tabs).forEach(key => {
                tabs[key].btn.classList.remove('active', 'border-indigo-600', 'text-indigo-600');
                tabs[key].btn.classList.add('border-transparent', 'text-gray-600');
                tabs[key].content.classList.add('hidden');
            });
            
            // Add active class to selected tab
            tabs[tabName].btn.classList.add('active', 'border-indigo-600', 'text-indigo-600');
            tabs[tabName].btn.classList.remove('border-transparent', 'text-gray-600');
            tabs[tabName].content.classList.remove('hidden');
            
            // Initialize tab content
            if (tabName === 'urls' && typeof initializePagesTab === 'function') {
                initializePagesTab();
            }
        }
        
        // Tab click handlers
        tabs.sitemaps.btn.addEventListener('click', () => setActiveTab('sitemaps'));
        tabs.urls.btn.addEventListener('click', () => setActiveTab('urls'));
    </script>
    
</body>
</html>
