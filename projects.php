<?php
/**
 * Projects Page
 * 
 * Protected page - requires authentication
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
    <title>Projects - Audit withGufran</title>
    
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
                    <a href="#" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-file-earmark-plus text-lg mr-3"></i>
                        <span>New Audit</span>
                    </a>
                    <a href="projects.php" class="nav-item active flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-folder text-lg mr-3"></i>
                        <span>Projects</span>
                    </a>
                    <a href="#" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-clock-history text-lg mr-3"></i>
                        <span>History</span>
                    </a>
                    <a href="#" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <i class="bi bi-file-earmark-text text-lg mr-3"></i>
                        <span>Reports</span>
                    </a>
                    <div class="pt-6 mt-6 border-t border-gray-200">
                        <a href="#" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                            <i class="bi bi-gear text-lg mr-3"></i>
                            <span>Settings</span>
                        </a>
                    </div>
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
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Projects</h2>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="text-gray-500 hover:text-gray-700">
                        <i class="bi bi-search text-xl"></i>
                    </button>
                    <button class="relative text-gray-500 hover:text-gray-700">
                        <i class="bi bi-bell text-xl"></i>
                        <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-xs text-white flex items-center justify-center">3</span>
                    </button>
                    <button id="themeToggle" class="text-gray-500 hover:text-gray-700">
                        <i class="bi bi-moon text-xl"></i>
                    </button>
                </div>
            </header>
            
            <!-- Main Workspace -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
                
                <!-- Page Header -->
                <div class="mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">Your Projects</h3>
                            <p class="text-gray-600 mt-1">Manage and monitor your SEO audit projects</p>
                        </div>
                        <button id="createProjectBtn" class="px-6 py-3 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg hover:opacity-90 transition-opacity shadow-lg">
                            <i class="bi bi-plus-lg mr-2"></i>
                            Create Project
                        </button>
                    </div>
                </div>
                
                <!-- Search and Filter Bar -->
                <div class="mb-6">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1 relative">
                            <input 
                                type="text" 
                                id="searchInput"
                                placeholder="Search projects..." 
                                class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            >
                            <i class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <select id="statusFilter" class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                            <option value="active">Active Projects</option>
                            <option value="archived">Archived Projects</option>
                            <option value="">All Projects</option>
                        </select>
                    </div>
                </div>
                
                <!-- Loading State -->
                <div id="loadingState" class="hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="bg-white rounded-xl border border-gray-200 p-6 animate-pulse">
                            <div class="h-6 bg-gray-200 rounded w-3/4 mb-4"></div>
                            <div class="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
                            <div class="grid grid-cols-3 gap-3 mb-4">
                                <div class="h-16 bg-gray-200 rounded"></div>
                                <div class="h-16 bg-gray-200 rounded"></div>
                                <div class="h-16 bg-gray-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Projects Grid -->
                <div id="projectsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Projects will be loaded here dynamically -->
                </div>
                
                <!-- Empty State -->
                <div id="emptyState" class="hidden text-center py-12">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
                        <i class="bi bi-folder text-4xl text-gray-400"></i>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-800 mb-2">No projects found</h4>
                    <p class="text-gray-600 mb-6">Create your first project to get started with SEO audits</p>
                    <button onclick="document.getElementById('createProjectBtn').click()" class="px-6 py-3 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg hover:opacity-90 transition-opacity">
                        <i class="bi bi-plus-lg mr-2"></i>
                        Create Your First Project
                    </button>
                </div>
                
                <!-- Pagination -->
                <div id="paginationContainer" class="mt-8 flex items-center justify-center gap-2 hidden">
                    <!-- Pagination will be rendered here -->
                </div>
                
            </main>
            
        </div>
        
    </div>
    
    <!-- Sidebar Overlay for Mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>
    
    <!-- Create Project Modal -->
    <div id="createProjectModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            
            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-800">Create New Project</h3>
                    <button id="closeModalBtn" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="bi bi-x-lg text-xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="px-6 py-6">
                <form id="createProjectForm" class="space-y-5">
                    
                    <!-- Project Name -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Project Name <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="projectNameInput"
                            name="project_name"
                            placeholder="Enter project name"
                            class="w-full px-4 py-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-1.5">
                            <i class="bi bi-info-circle mr-1"></i>
                            Give your project a descriptive name
                        </p>
                        <p id="projectNameError" class="text-xs text-red-600 mt-1 hidden"></p>
                    </div>
                    
                    <!-- Domain / Sitemap URL -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Domain or Sitemap URL <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="domainInput"
                            name="primary_domain"
                            placeholder="example.com or https://example.com/sitemap.xml"
                            class="w-full px-4 py-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-1.5">
                            <i class="bi bi-info-circle mr-1"></i>
                            Enter domain URL or direct sitemap URL
                        </p>
                        <p id="domainError" class="text-xs text-red-600 mt-1 hidden"></p>
                    </div>
                    
                    <!-- Validation Message -->
                    <div class="hidden" id="validationMessage">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <p class="text-sm text-red-700">
                                <i class="bi bi-exclamation-circle mr-2"></i>
                                <span id="validationText">Please check your inputs and try again</span>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Success Message -->
                    <div class="hidden" id="successMessage">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <p class="text-sm text-green-700">
                                <i class="bi bi-check-circle mr-2"></i>
                                Project created successfully!
                            </p>
                        </div>
                    </div>
                    
                </form>
            </div>
            
            <!-- Modal Footer -->
            <div class="px-6 py-5 border-t border-gray-200 bg-gray-50 flex items-center justify-end gap-3 rounded-b-2xl">
                <button id="cancelModalBtn" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" id="createProjectSubmitBtn" form="createProjectForm" class="px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg hover:opacity-90 transition-opacity">
                    <i class="bi bi-plus-lg mr-2"></i>
                    Create Project
                </button>
            </div>
            
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/projects.js"></script>
    
</body>
</html>
