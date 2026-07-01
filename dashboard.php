<?php
/**
 * Dashboard Page
 *
 * Protected dashboard - requires authentication.
 * All data is loaded dynamically via assets/js/dashboard.js from the
 * existing DashboardController (routes/dashboard.php). No static data.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Services\AuthService;

// Protect this route
$auth = new AuthMiddleware();
$auth->handle();

// Get current user
$authService = new AuthService();
$currentUser = $authService->user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard - Audit withGufran</title>

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
                    <a href="dashboard.php" class="nav-item active flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-speedometer2 text-lg mr-3"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="projects.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-folder text-lg mr-3"></i>
                        <span>Projects</span>
                    </a>
                    <?php if (($currentUser['role'] ?? '') === 'super_admin'): ?>
                    <a href="settings.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-gear text-lg mr-3"></i>
                        <span>Settings</span>
                    </a>
                    <?php endif; ?>
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

                <!-- Left: Mobile menu and title -->
                <div class="flex items-center space-x-4">
                    <button id="openSidebar" class="lg:hidden text-gray-500 hover:text-gray-700">
                        <i class="bi bi-list text-2xl"></i>
                    </button>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Dashboard</h2>
                    </div>
                </div>

                <!-- Right: Actions (wired by dashboard.js) -->
                <div class="flex items-center space-x-4">
                    <!-- Global search (dashboard.js binds data-search-trigger + Ctrl/Cmd+K) -->
                    <button data-search-trigger class="text-gray-500 hover:text-gray-700" title="Search (Ctrl+K)">
                        <i class="bi bi-search text-xl"></i>
                    </button>

                    <!-- Notifications (dashboard.js binds data-notif-trigger + data-notif-badge) -->
                    <button data-notif-trigger class="relative text-gray-500 hover:text-gray-700" title="Notifications">
                        <i class="bi bi-bell text-xl"></i>
                        <span data-notif-badge class="hidden absolute -top-1 -right-1 min-w-[16px] h-4 px-1 bg-red-500 rounded-full text-xs text-white flex items-center justify-center"></span>
                    </button>

                    <!-- Theme Toggle (dashboard.js binds #themeToggle) -->
                    <button id="themeToggle" class="text-gray-500 hover:text-gray-700" title="Toggle theme">
                        <i class="bi bi-moon text-xl"></i>
                    </button>
                </div>

            </header>

            <!-- Main Workspace -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">

                <!-- Welcome Section -->
                <div class="mb-6">
                    <h3 class="text-2xl font-bold text-gray-800">SEO Audit Dashboard</h3>
                    <p class="text-gray-600 mt-1">Welcome back, <?= htmlspecialchars($currentUser['name']) ?>! Monitor and manage your SEO audit results</p>
                </div>

                <!-- Dynamic Overview (stats, quick actions, recent projects, jobs, recent reports) -->
                <!-- Populated by Dashboard.loadOverview() from routes/dashboard.php?action=overview -->
                <div id="dashboardOverview"></div>

                <!-- Recent Activity (populated from routes/dashboard.php?action=activity) -->
                <div class="mt-2">
                    <h3 class="text-sm font-semibold text-gray-600 mb-3 uppercase tracking-wide">Recent Activity</h3>
                    <div id="activityFeed" class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-50">
                        <p class="px-5 py-4 text-xs text-gray-400">Loading activity...</p>
                    </div>
                </div>

            </main>

        </div>

    </div>

    <!-- Sidebar Overlay for Mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>

    <!-- JavaScript -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        // Wire the dashboard to the existing DashboardController/DashboardService.
        // dashboard.js self-initializes search/notifications/theme; we trigger the
        // overview render and the recent activity feed here.
        (function () {
            function esc(str) {
                if (!str) return '';
                var d = document.createElement('div');
                d.appendChild(document.createTextNode(str));
                return d.innerHTML;
            }

            function timeAgo(dateStr) {
                if (!dateStr) return '';
                var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
                if (isNaN(diff)) return '';
                if (diff < 60) return 'just now';
                if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
                if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
                if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
                return new Date(dateStr).toLocaleDateString();
            }

            function loadActivity() {
                var feed = document.getElementById('activityFeed');
                if (!feed) return;

                fetch('routes/dashboard.php?action=activity&limit=15')
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.success || !data.activity || data.activity.length === 0) {
                            feed.innerHTML = '<p class="px-5 py-4 text-xs text-gray-400">No recent activity.</p>';
                            return;
                        }
                        var html = '';
                        data.activity.forEach(function (a) {
                            html += '<div class="px-5 py-3 flex items-start gap-3">';
                            html += '<div class="w-7 h-7 bg-indigo-50 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"><i class="bi bi-activity text-indigo-500 text-xs"></i></div>';
                            html += '<div class="flex-1 min-w-0">';
                            html += '<p class="text-sm text-gray-800">' + esc(a.description || a.action) + '</p>';
                            var meta = [];
                            if (a.user_name) meta.push(esc(a.user_name));
                            if (a.project_name) meta.push(esc(a.project_name));
                            if (meta.length) html += '<p class="text-xs text-gray-400 mt-0.5">' + meta.join(' · ') + '</p>';
                            html += '</div>';
                            html += '<span class="text-[10px] text-gray-400 flex-shrink-0">' + timeAgo(a.created_at) + '</span>';
                            html += '</div>';
                        });
                        feed.innerHTML = html;
                    })
                    .catch(function () {
                        feed.innerHTML = '<p class="px-5 py-4 text-xs text-red-400">Failed to load activity.</p>';
                    });
            }

            function boot() {
                if (window.Dashboard && typeof window.Dashboard.loadOverview === 'function') {
                    window.Dashboard.loadOverview(document.getElementById('dashboardOverview'));
                }
                loadActivity();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
    </script>

</body>
</html>
