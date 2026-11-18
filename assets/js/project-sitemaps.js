/**
 * Project Sitemaps JavaScript
 * 
 * Handles sitemap discovery and management
 */

// DOM Elements
const sitemapsContainer = document.getElementById('sitemapsContainer');
const sitemapsEmptyState = document.getElementById('sitemapsEmptyState');
const discoverSitemapsBtn = document.getElementById('discoverSitemapsBtn');
const addSitemapBtn = document.getElementById('addSitemapBtn');
const addSitemapModal = document.getElementById('addSitemapModal');
const closeAddSitemapModal = document.getElementById('closeAddSitemapModal');
const cancelAddSitemapModal = document.getElementById('cancelAddSitemapModal');
const addSitemapForm = document.getElementById('addSitemapForm');
const addSitemapSubmitBtn = document.getElementById('addSitemapSubmitBtn');
const sitemapUrlInput = document.getElementById('sitemapUrlInput');
const sitemapUrlError = document.getElementById('sitemapUrlError');
const addSitemapValidationMessage = document.getElementById('addSitemapValidationMessage');
const addSitemapValidationText = document.getElementById('addSitemapValidationText');
const addSitemapSuccessMessage = document.getElementById('addSitemapSuccessMessage');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadSitemaps();
    initializeEventListeners();
});

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    discoverSitemapsBtn.addEventListener('click', discoverSitemaps);
    addSitemapBtn.addEventListener('click', openAddSitemapModal);
    closeAddSitemapModal.addEventListener('click', closeAddModal);
    cancelAddSitemapModal.addEventListener('click', closeAddModal);
    addSitemapForm.addEventListener('submit', handleAddSitemap);
    
    // Close modal on backdrop click
    addSitemapModal.addEventListener('click', function(e) {
        if (e.target === addSitemapModal) {
            closeAddModal();
        }
    });
}

/**
 * Load sitemaps from API
 */
async function loadSitemaps() {
    try {
        const response = await fetch(`/api/sitemaps?action=list&project_id=${projectId}`);
        const data = await response.json();
        
        if (data.success && data.sitemaps) {
            renderSitemaps(data.sitemaps);
        } else {
            showEmptyState();
        }
    } catch (error) {
        console.error('Error loading sitemaps:', error);
        showError('Failed to load sitemaps');
    }
}

/**
 * Render sitemaps list
 */
function renderSitemaps(sitemaps) {
    if (sitemaps.length === 0) {
        showEmptyState();
        return;
    }
    
    sitemapsEmptyState.classList.add('hidden');
    sitemapsContainer.classList.remove('hidden');
    
    sitemapsContainer.innerHTML = `
        <div class="space-y-3">
            ${sitemaps.map(sitemap => `
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200 hover:border-gray-300 transition-colors">
                    <div class="flex-1 min-w-0 mr-4">
                        <div class="flex items-center gap-2 mb-2">
                            ${getStatusBadge(sitemap.status)}
                            ${getTypeBadge(sitemap.sitemap_type)}
                            ${sitemap.discovered_from ? `
                                <span class="text-xs text-gray-500">
                                    <i class="bi bi-info-circle mr-1"></i>
                                    Discovered from ${sitemap.discovered_from}
                                </span>
                            ` : ''}
                        </div>
                        <p class="text-sm font-medium text-gray-800 truncate mb-1">${escapeHtml(sitemap.sitemap_url)}</p>
                        <div class="flex items-center gap-4 text-xs text-gray-600">
                            <span><i class="bi bi-link-45deg mr-1"></i>${sitemap.total_urls} URLs</span>
                            ${sitemap.last_fetched_at ? `
                                <span><i class="bi bi-clock mr-1"></i>Last fetched ${formatDate(sitemap.last_fetched_at)}</span>
                            ` : ''}
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        ${sitemap.status !== 'validated' ? `
                            <button onclick="revalidateSitemap(${sitemap.id})" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50" title="Re-validate">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        ` : ''}
                        ${sitemap.status === 'validated' && sitemap.total_urls === 0 ? `
                            <button onclick="importUrls(${sitemap.id})" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700" title="Import URLs">
                                <i class="bi bi-download"></i>
                            </button>
                        ` : ''}
                        ${sitemap.total_urls > 0 ? `
                            <button onclick="viewUrls(${sitemap.id})" class="px-3 py-2 text-sm font-medium text-indigo-600 bg-white border border-indigo-300 rounded-lg hover:bg-indigo-50" title="View URLs">
                                <i class="bi bi-list-ul"></i>
                            </button>
                        ` : ''}
                        <button onclick="deleteSitemap(${sitemap.id})" class="px-3 py-2 text-sm font-medium text-red-600 bg-white border border-red-300 rounded-lg hover:bg-red-50" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

/**
 * Show empty state
 */
function showEmptyState() {
    sitemapsContainer.classList.add('hidden');
    sitemapsEmptyState.classList.remove('hidden');
}

/**
 * Discover sitemaps automatically
 */
async function discoverSitemaps() {
    discoverSitemapsBtn.disabled = true;
    discoverSitemapsBtn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin mr-2"></i>Discovering...';
    
    sitemapsContainer.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-full mb-3">
                <i class="bi bi-arrow-repeat animate-spin text-3xl text-indigo-600"></i>
            </div>
            <p class="text-sm text-gray-600">Discovering sitemaps...</p>
            <p class="text-xs text-gray-500 mt-2">Checking robots.txt and common locations</p>
        </div>
    `;
    
    try {
        const formData = new FormData();
        formData.append('action', 'discover');
        formData.append('project_id', projectId);
        formData.append('csrf_token', getCsrfToken());
        
        const response = await fetch('/api/sitemaps', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show success message
            showNotification(`Discovered ${data.count} sitemap(s)`, 'success');
            
            // Reload sitemaps and page stats
            await loadSitemaps();
            location.reload(); // Reload to update stats
        } else {
            showNotification(data.error || 'No sitemaps found. Try adding one manually.', 'error');
            showEmptyState();
        }
    } catch (error) {
        console.error('Error discovering sitemaps:', error);
        showNotification('Failed to discover sitemaps', 'error');
        showEmptyState();
    } finally {
        discoverSitemapsBtn.disabled = false;
        discoverSitemapsBtn.innerHTML = '<i class="bi bi-search mr-2"></i>Auto-Discover';
    }
}

/**
 * Open add sitemap modal
 */
function openAddSitemapModal() {
    addSitemapModal.classList.remove('hidden');
    resetAddForm();
}

/**
 * Close add sitemap modal
 */
function closeAddModal() {
    addSitemapModal.classList.add('hidden');
    resetAddForm();
}

/**
 * Reset add sitemap form
 */
function resetAddForm() {
    addSitemapForm.reset();
    sitemapUrlError.classList.add('hidden');
    addSitemapValidationMessage.classList.add('hidden');
    addSitemapSuccessMessage.classList.add('hidden');
    addSitemapSubmitBtn.disabled = false;
}

/**
 * Handle add sitemap form submission
 */
async function handleAddSitemap(e) {
    e.preventDefault();
    
    sitemapUrlError.classList.add('hidden');
    addSitemapValidationMessage.classList.add('hidden');
    addSitemapSuccessMessage.classList.add('hidden');
    
    addSitemapSubmitBtn.disabled = true;
    addSitemapSubmitBtn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin mr-2"></i>Adding...';
    
    const formData = new FormData(addSitemapForm);
    formData.append('action', 'add');
    formData.append('project_id', projectId);
    formData.append('csrf_token', getCsrfToken());
    
    try {
        const response = await fetch('/api/sitemaps', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            addSitemapSuccessMessage.classList.remove('hidden');
            
            setTimeout(() => {
                closeAddModal();
                loadSitemaps();
                location.reload(); // Reload to update stats
            }, 1500);
        } else {
            if (data.error) {
                addSitemapValidationText.textContent = data.error;
                addSitemapValidationMessage.classList.remove('hidden');
            }
            
            addSitemapSubmitBtn.disabled = false;
            addSitemapSubmitBtn.innerHTML = '<i class="bi bi-plus-lg mr-2"></i>Add Sitemap';
        }
    } catch (error) {
        console.error('Error adding sitemap:', error);
        addSitemapValidationText.textContent = 'An error occurred. Please try again.';
        addSitemapValidationMessage.classList.remove('hidden');
        
        addSitemapSubmitBtn.disabled = false;
        addSitemapSubmitBtn.innerHTML = '<i class="bi bi-plus-lg mr-2"></i>Add Sitemap';
    }
}

/**
 * Re-validate sitemap
 */
async function revalidateSitemap(sitemapId) {
    if (!confirm('Re-validate this sitemap?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'revalidate');
        formData.append('sitemap_id', sitemapId);
        formData.append('csrf_token', getCsrfToken());
        
        const response = await fetch('/api/sitemaps', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Sitemap re-validated successfully', 'success');
            loadSitemaps();
            location.reload();
        } else {
            showNotification(data.error || 'Failed to re-validate sitemap', 'error');
        }
    } catch (error) {
        console.error('Error re-validating sitemap:', error);
        showNotification('Failed to re-validate sitemap', 'error');
    }
}

/**
 * Delete sitemap
 */
async function deleteSitemap(sitemapId) {
    if (!confirm('Are you sure you want to delete this sitemap?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('sitemap_id', sitemapId);
        formData.append('csrf_token', getCsrfToken());
        
        const response = await fetch('/api/sitemaps', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Sitemap deleted successfully', 'success');
            loadSitemaps();
            location.reload();
        } else {
            showNotification(data.error || 'Failed to delete sitemap', 'error');
        }
    } catch (error) {
        console.error('Error deleting sitemap:', error);
        showNotification('Failed to delete sitemap', 'error');
    }
}

/**
 * Import URLs from sitemap
 */
async function importUrls(sitemapId) {
    if (!confirm('Import all URLs from this sitemap? This may take a few moments.')) {
        return;
    }
    
    try {
        showNotification('Importing URLs... Please wait.', 'info');
        
        const formData = new FormData();
        formData.append('action', 'import');
        formData.append('sitemap_id', sitemapId);
        formData.append('csrf_token', getCsrfToken());
        
        const response = await fetch('/api/pages', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            let message = `Successfully imported ${data.imported} URLs`;
            if (data.duplicates > 0) {
                message += ` (${data.duplicates} duplicates skipped)`;
            }
            if (data.invalid > 0) {
                message += ` (${data.invalid} invalid)`;
            }
            if (data.nested_sitemaps) {
                message += ` from ${data.nested_sitemaps} nested sitemaps`;
            }
            showNotification(message, 'success');
            
            // Reload sitemaps to show updated counts
            loadSitemaps();
            
            // Switch to URLs tab after 1.5 seconds
            setTimeout(() => {
                if (typeof setActiveTab === 'function') {
                    setActiveTab('urls');
                }
            }, 1500);
        } else {
            showNotification(data.error || 'Failed to import URLs', 'error');
        }
    } catch (error) {
        console.error('Error importing URLs:', error);
        showNotification('Failed to import URLs', 'error');
    }
}

/**
 * View URLs from sitemap
 */
function viewUrls(sitemapId) {
    // Switch to URLs tab with sitemap filter
    if (typeof setActiveTab === 'function') {
        setActiveTab('urls');
        // Set filter after a short delay to ensure tab is loaded
        setTimeout(() => {
            const sitemapFilterSelect = document.getElementById('sitemapFilterSelect');
            if (sitemapFilterSelect) {
                sitemapFilterSelect.value = sitemapId;
                sitemapFilterSelect.dispatchEvent(new Event('change'));
            }
        }, 300);
    }
}

/**
 * Utility functions
 */

function getStatusBadge(status) {
    const badges = {
        'validated': '<span class="status-badge badge-success"><i class="bi bi-check-circle mr-1"></i>Validated</span>',
        'invalid': '<span class="status-badge badge-danger"><i class="bi bi-x-circle mr-1"></i>Invalid</span>',
        'unreachable': '<span class="status-badge badge-warning"><i class="bi bi-exclamation-triangle mr-1"></i>Unreachable</span>',
        'discovered': '<span class="status-badge badge-info"><i class="bi bi-info-circle mr-1"></i>Discovered</span>'
    };
    return badges[status] || badges.discovered;
}

function getTypeBadge(type) {
    const badges = {
        'sitemap': '<span class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded">Sitemap</span>',
        'sitemap_index': '<span class="text-xs px-2 py-1 bg-purple-100 text-purple-700 rounded">Sitemap Index</span>',
        'nested_sitemap': '<span class="text-xs px-2 py-1 bg-indigo-100 text-indigo-700 rounded">Nested Sitemap</span>'
    };
    return badges[type] || badges.sitemap;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return `${diffMins} min ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    
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

function showNotification(message, type = 'info') {
    // Simple alert for now (can be enhanced with toast notifications later)
    alert(message);
}

function showError(message) {
    alert(message);
}

/**
 * Get CSRF token from meta tag
 */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}
