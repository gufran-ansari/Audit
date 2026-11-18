<?php
/**
 * Settings Page
 *
 * Super Admin only. All data is loaded dynamically via assets/js/settings.js
 * from the existing SettingsController (routes/settings.php). No static data.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Middleware\RoleMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\AuthService;

// Protect this route — super admin only (matches SettingsController gating)
$role = new RoleMiddleware();
$role->superAdmin();

$authService = new AuthService();
$currentUser = $authService->user();

// Generate CSRF token for AJAX requests
$csrfToken = CsrfMiddleware::getToken() ?: CsrfMiddleware::generateToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>Settings - Audit withGufran</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // settings.js uses brand-* utility classes; register the palette.
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { 50:'#eef2ff',100:'#e0e7ff',200:'#c7d2fe',300:'#a5b4fc',500:'#6366f1',600:'#4f46e5',700:'#4338ca',800:'#3730a3' }
                    }
                }
            }
        };
    </script>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-50 font-sans antialiased overflow-hidden">

    <div class="flex h-screen">

        <!-- Sidebar -->
        <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 transform transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0">

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

            <nav class="flex-1 px-4 py-6 overflow-y-auto">
                <div class="space-y-1">
                    <a href="dashboard.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-speedometer2 text-lg mr-3"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="projects.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-folder text-lg mr-3"></i>
                        <span>Projects</span>
                    </a>
                    <a href="settings.php" class="nav-item active flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-gear text-lg mr-3"></i>
                        <span>Settings</span>
                    </a>
                    <a href="security.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-shield-lock text-lg mr-3"></i>
                        <span>Security</span>
                    </a>
                </div>
            </nav>

            <div class="border-t border-gray-200 p-4">
                <div class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-gray-50">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-full flex items-center justify-center">
                        <i class="bi bi-person text-white text-lg"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($currentUser['name']) ?></p>
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars(ucfirst($currentUser['role'])) ?></p>
                    </div>
                    <a href="/login.php?action=logout" class="text-gray-400 hover:text-gray-600" title="Logout">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>

        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
                <div class="flex items-center space-x-4">
                    <button id="openSidebar" class="lg:hidden text-gray-500 hover:text-gray-700">
                        <i class="bi bi-list text-2xl"></i>
                    </button>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Settings</h2>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button data-search-trigger class="text-gray-500 hover:text-gray-700" title="Search (Ctrl+K)">
                        <i class="bi bi-search text-xl"></i>
                    </button>
                    <button data-notif-trigger class="relative text-gray-500 hover:text-gray-700" title="Notifications">
                        <i class="bi bi-bell text-xl"></i>
                        <span data-notif-badge class="hidden absolute -top-1 -right-1 min-w-[16px] h-4 px-1 bg-red-500 rounded-full text-xs text-white flex items-center justify-center"></span>
                    </button>
                    <button id="themeToggle" class="text-gray-500 hover:text-gray-700" title="Toggle theme">
                        <i class="bi bi-moon text-xl"></i>
                    </button>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
                <!-- Rendered by SettingsModule.renderSettingsPage() from routes/settings.php -->
                <div id="settingsContainer"></div>
            </main>

        </div>

    </div>

    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>

    <!-- JavaScript -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/settings.js"></script>
    <script>
        (function () {
            function boot() {
                if (window.SettingsModule && typeof window.SettingsModule.renderSettingsPage === 'function') {
                    window.SettingsModule.renderSettingsPage(document.getElementById('settingsContainer'));
                }
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
