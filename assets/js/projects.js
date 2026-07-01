/**
 * Projects Page JavaScript
 * 
 * Handles project listing, creation, and management
 */

// State management
let currentPage = 1;
let currentFilters = {
    status: 'active',
    search: ''
};

// DOM Elements
const projectsGrid = document.getElementById('projectsGrid');
const emptyState = document.getElementById('emptyState');
const loadingState = document.getElementById('loadingState');
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const paginationContainer = document.getElementById('paginationContainer');

// Modal Elements
const createProjectModal = document.getElementById('createProjectModal');
const createProjectBtn = document.getElementById('createProjectBtn');
const closeModalBtn = document.getElementById('closeModalBtn');
const cancelModalBtn = document.getElementById('cancelModalBtn');
const createProjectForm = document.getElementById('createProjectForm');
const createProjectSubmitBtn = document.getElementById('createProjectSubmitBtn');

// Form Elements
const projectNameInput = document.getElementById('projectNameInput');
const domainInput = document.getElementById('domainInput');
const projectNameError = document.getElementById('projectNameError');
const domainError = document.getElementById('domainError');
const validationMessage = document.getElementById('validationMessage');
const validationText = document.getElementById('validationText');
const successMessage = document.getElementById('successMessage');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadProjects();
    initializeEventListeners();
});

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // Modal controls
    createProjectBtn.addEventListener('click', openCreateModal);
    closeModalBtn.addEventListener('click', closeCreateModal);
    cancelModalBtn.addEventListener('click', closeCreateModal);
    
    // Close modal on backdrop click
    createProjectModal.addEventListener('click', function(e) {
        if (e.target === createProjectModal) {
            closeCreateModal();
        }
    });
    
    // Form submission
    createProjectForm.addEventListener('submit', handleCreateProject);
    
    // Search with debounce
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentFilters.search = this.value.trim();
            currentPage = 1;
            loadProjects();
        }, 500);
    });
    
    // Status filter
    statusFilter.addEventListener('change', function() {
        currentFilters.status = this.value;
        currentPage = 1;
        loadProjects();
    });
}

/**
 * Load projects from API
 */
async function loadProjects() {
    showLoading();
    
    try {
        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            per_page: 9,
            status: currentFilters.status,
            search: currentFilters.search
        });
        
        const response = await fetch(`/routes/projects.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            renderProjects(data.data);
            renderPagination(data.pagination);
        } else {
            showError('Failed to load projects');
        }
    } catch (error) {
        console.error('Error loading projects:', error);
        showError('Failed to load projects');
    }
}

/**
 * Render projects grid
 */
function renderProjects(projects) {
    hideLoading();
    
    if (projects.length === 0) {
        projectsGrid.innerHTML = '';
        emptyState.classList.remove('hidden');
        paginationContainer.classList.add('hidden');
        return;
    }
    
    emptyState.classList.add('hidden');
    
    projectsGrid.innerHTML = projects.map(project => `
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden group">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1 min-w-0">
                        <h4 class="text-lg font-semibold text-gray-800 truncate group-hover:text-indigo-600 transition-colors">
                            ${escapeHtml(project.project_name)}
                        </h4>
                        <p class="text-sm text-gray-600 mt-1 truncate">${escapeHtml(project.primary_domain)}</p>
                    </div>
                    <div class="relative">
                        <button onclick="toggleProjectMenu(${project.id})" class="text-gray-400 hover:text-gray-600 ml-2">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <div id="menu-${project.id}" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                            <button onclick="openProject('${project.uuid}')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-t-lg">
                                <i class="bi bi-box-arrow-up-right mr-2"></i>Open
                            </button>
                            <button onclick="editProject(${project.id})" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="bi bi-pencil mr-2"></i>Edit
                            </button>
                            ${project.project_status === 'active' ? `
                                <button onclick="archiveProject(${project.id})" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-b-lg">
                                    <i class="bi bi-archive mr-2"></i>Archive
                                </button>
                            ` : `
                                <button onclick="restoreProject(${project.id})" class="w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-green-50 rounded-b-lg">
                                    <i class="bi bi-arrow-counterclockwise mr-2"></i>Restore
                                </button>
                            `}
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-2 mb-4">
                    ${getStatusBadge(project.project_status)}
                    <span class="text-xs text-gray-500">${project.stats.total_urls} URLs</span>
                </div>
                
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="text-center p-2 bg-green-50 rounded-lg">
                        <p class="text-lg font-bold text-green-600">${project.stats.passed}</p>
                        <p class="text-xs text-gray-600">Passed</p>
                    </div>
                    <div class="text-center p-2 bg-orange-50 rounded-lg">
                        <p class="text-lg font-bold text-orange-600">${project.stats.warnings}</p>
                        <p class="text-xs text-gray-600">Warnings</p>
                    </div>
                    <div class="text-center p-2 bg-red-50 rounded-lg">
                        <p class="text-lg font-bold text-red-600">${project.stats.failed}</p>
                        <p class="text-xs text-gray-600">Failed</p>
                    </div>
                </div>
                
                <p class="text-xs text-gray-500 mb-4">
                    <i class="bi bi-clock mr-1"></i>
                    Last updated ${formatDate(project.updated_at)}
                </p>
                
                <div class="flex gap-2">
                    <button onclick="openProject('${project.uuid}')" class="flex-1 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                        <i class="bi bi-box-arrow-up-right mr-1"></i>
                        Open
                    </button>
                    <button onclick="viewReport('${project.report_slug}')" class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="bi bi-file-earmark-text mr-1"></i>
                        Report
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

/**
 * Render pagination
 */
function renderPagination(pagination) {
    if (pagination.total_pages <= 1) {
        paginationContainer.classList.add('hidden');
        return;
    }
    
    paginationContainer.classList.remove('hidden');
    
    let paginationHTML = '';
    
    // Previous button
    if (pagination.current_page > 1) {
        paginationHTML += `
            <button onclick="goToPage(${pagination.current_page - 1})" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                <i class="bi bi-chevron-left"></i>
            </button>
        `;
    }
    
    // Page numbers
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === pagination.current_page) {
            paginationHTML += `
                <button class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg">
                    ${i}
                </button>
            `;
        } else if (i === 1 || i === pagination.total_pages || Math.abs(i - pagination.current_page) <= 2) {
            paginationHTML += `
                <button onclick="goToPage(${i})" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    ${i}
                </button>
            `;
        } else if (Math.abs(i - pagination.current_page) === 3) {
            paginationHTML += `<span class="px-2">...</span>`;
        }
    }
    
    // Next button
    if (pagination.current_page < pagination.total_pages) {
        paginationHTML += `
            <button onclick="goToPage(${pagination.current_page + 1})" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                <i class="bi bi-chevron-right"></i>
            </button>
        `;
    }
    
    paginationContainer.innerHTML = paginationHTML;
}

/**
 * Go to specific page
 */
function goToPage(page) {
    currentPage = page;
    loadProjects();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Open create project modal
 */
function openCreateModal() {
    createProjectModal.classList.remove('hidden');
    resetForm();
}

/**
 * Close create project modal
 */
function closeCreateModal() {
    createProjectModal.classList.add('hidden');
    resetForm();
}

/**
 * Reset form
 */
function resetForm() {
    createProjectForm.reset();
    hideFormErrors();
    successMessage.classList.add('hidden');
    createProjectSubmitBtn.disabled = false;
}

/**
 * Hide form errors
 */
function hideFormErrors() {
    projectNameError.classList.add('hidden');
    domainError.classList.add('hidden');
    validationMessage.classList.add('hidden');
}

/**
 * Handle create project form submission
 */
async function handleCreateProject(e) {
    e.preventDefault();
    
    hideFormErrors();
    createProjectSubmitBtn.disabled = true;
    createProjectSubmitBtn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin mr-2"></i>Creating...';
    
    const formData = new FormData(createProjectForm);
    formData.append('action', 'create');
    
    try {
        const response = await fetch('/routes/projects.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            successMessage.classList.remove('hidden');
            
            setTimeout(() => {
                closeCreateModal();
                loadProjects();
            }, 1500);
        } else {
            // Show validation errors
            if (data.errors) {
                if (data.errors.project_name) {
                    projectNameError.textContent = data.errors.project_name;
                    projectNameError.classList.remove('hidden');
                }
                if (data.errors.primary_domain) {
                    domainError.textContent = data.errors.primary_domain;
                    domainError.classList.remove('hidden');
                }
                if (data.errors.general) {
                    validationText.textContent = data.errors.general;
                    validationMessage.classList.remove('hidden');
                }
            }
            
            createProjectSubmitBtn.disabled = false;
            createProjectSubmitBtn.innerHTML = '<i class="bi bi-plus-lg mr-2"></i>Create Project';
        }
    } catch (error) {
        console.error('Error creating project:', error);
        validationText.textContent = 'An error occurred. Please try again.';
        validationMessage.classList.remove('hidden');
        
        createProjectSubmitBtn.disabled = false;
        createProjectSubmitBtn.innerHTML = '<i class="bi bi-plus-lg mr-2"></i>Create Project';
    }
}

/**
 * Toggle project menu
 */
function toggleProjectMenu(projectId) {
    const menu = document.getElementById(`menu-${projectId}`);
    const allMenus = document.querySelectorAll('[id^="menu-"]');
    
    // Close all other menus
    allMenus.forEach(m => {
        if (m.id !== `menu-${projectId}`) {
            m.classList.add('hidden');
        }
    });
    
    // Toggle current menu
    menu.classList.toggle('hidden');
}

// Close menus when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('[id^="menu-"]') && !e.target.closest('button')) {
        document.querySelectorAll('[id^="menu-"]').forEach(menu => {
            menu.classList.add('hidden');
        });
    }
});

/**
 * Open project
 */
function openProject(uuid) {
    window.location.href = `project.php?uuid=${uuid}`;
}

/**
 * View report
 */
function viewReport(slug) {
    window.open(`report.php?slug=${slug}`, '_blank');
}

/**
 * Edit project (placeholder)
 */
function editProject(projectId) {
    alert('Edit project feature coming soon!');
}

/**
 * Archive project
 */
async function archiveProject(projectId) {
    if (!confirm('Are you sure you want to archive this project?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'archive');
        formData.append('project_id', projectId);
        
        const response = await fetch('/routes/projects.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadProjects();
        } else {
            alert(data.error || 'Failed to archive project');
        }
    } catch (error) {
        console.error('Error archiving project:', error);
        alert('Failed to archive project');
    }
}

/**
 * Restore project
 */
async function restoreProject(projectId) {
    try {
        const formData = new FormData();
        formData.append('action', 'restore');
        formData.append('project_id', projectId);
        
        const response = await fetch('/routes/projects.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadProjects();
        } else {
            alert(data.error || 'Failed to restore project');
        }
    } catch (error) {
        console.error('Error restoring project:', error);
        alert('Failed to restore project');
    }
}

/**
 * Utility functions
 */

function showLoading() {
    loadingState.classList.remove('hidden');
    projectsGrid.classList.add('hidden');
    emptyState.classList.add('hidden');
}

function hideLoading() {
    loadingState.classList.add('hidden');
    projectsGrid.classList.remove('hidden');
}

function showError(message) {
    hideLoading();
    alert(message);
}

function getStatusBadge(status) {
    const badges = {
        'active': '<span class="status-badge badge-success"><i class="bi bi-check-circle mr-1"></i>Active</span>',
        'archived': '<span class="status-badge badge-secondary"><i class="bi bi-archive mr-1"></i>Archived</span>',
        'paused': '<span class="status-badge badge-warning"><i class="bi bi-pause-circle mr-1"></i>Paused</span>'
    };
    return badges[status] || badges.active;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
