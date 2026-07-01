/**
 * Project Pages (URLs) JavaScript
 * 
 * Handles URL listing, filtering, and management in workspace
 */

// State management
let currentPage = 1;
let currentFilters = {
    sitemap_id: '',
    url_status: '',
    search: '',
    word_result: ''
};

// DOM Elements - will be initialized when tab is loaded
let pagesTableBody, paginationContainer, pageStatsContainer;
let searchInput, sitemapFilter, urlStatusFilter, wordResultFilter;

/**
 * Initialize pages tab
 */
function initializePagesTab() {
    // Get DOM elements
    pagesTableBody = document.getElementById('pagesTableBody');
    paginationContainer = document.getElementById('pagesPagination');
    pageStatsContainer = document.getElementById('pagesStats');
    searchInput = document.getElementById('pagesSearchInput');
    sitemapFilter = document.getElementById('sitemapFilterSelect');
    urlStatusFilter = document.getElementById('urlStatusFilterSelect');
    wordResultFilter = document.getElementById('wordResultFilterSelect');
    
    if (!pagesTableBody) {
        console.error('Pages table body not found');
        return;
    }
    
    // Initialize event listeners
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentFilters.search = this.value.trim();
                currentPage = 1;
                loadPages();
            }, 500);
        });
    }
    
    if (sitemapFilter) {
        sitemapFilter.addEventListener('change', function() {
            currentFilters.sitemap_id = this.value;
            currentPage = 1;
            loadPages();
        });
    }
    
    if (urlStatusFilter) {
        urlStatusFilter.addEventListener('change', function() {
            currentFilters.url_status = this.value;
            currentPage = 1;
            loadPages();
        });
    }
    
    if (wordResultFilter) {
        wordResultFilter.addEventListener('change', function() {
            currentFilters.word_result = this.value;
            currentPage = 1;
            loadPages();
        });
    }
    
    // Load sitemaps for filter dropdown
    loadSitemapFilter();
    
    // Load pages
    loadPages();
    loadPageStats();
}

/**
 * Load sitemaps for filter dropdown
 */
async function loadSitemapFilter() {
    if (!sitemapFilter) return;
    
    try {
        const response = await fetch(`/routes/sitemaps.php?action=list&project_id=${projectId}`);
        const data = await response.json();
        
        if (data.success && data.sitemaps) {
            let options = '<option value="">All Sitemaps</option>';
            data.sitemaps.forEach(sitemap => {
                const label = sitemap.sitemap_url.length > 60 
                    ? sitemap.sitemap_url.substring(0, 60) + '...' 
                    : sitemap.sitemap_url;
                options += `<option value="${sitemap.id}">${escapeHtml(label)}</option>`;
            });
            sitemapFilter.innerHTML = options;
        }
    } catch (error) {
        console.error('Error loading sitemaps for filter:', error);
    }
}

/**
 * Load pages from API
 */
async function loadPages() {
    if (!pagesTableBody) return;
    
    showPagesLoading();
    
    try {
        const params = new URLSearchParams({
            action: 'list',
            project_id: projectId,
            page: currentPage,
            per_page: 50
        });
        
        // Add filters
        if (currentFilters.sitemap_id) {
            params.append('sitemap_id', currentFilters.sitemap_id);
        }
        if (currentFilters.url_status) {
            params.append('url_status', currentFilters.url_status);
        }
        if (currentFilters.search) {
            params.append('search', currentFilters.search);
        }
        // Word occurrence result filter: maps found->PASS, missing->FAIL on the word module
        if (currentFilters.word_result) {
            params.append('module_name', 'word_occurrence_audit');
            params.append('module_status', currentFilters.word_result === 'found' ? 'PASS' : 'FAIL');
        }
        
        const response = await fetch(`/routes/pages.php?${params}`);
        const data = await response.json();
        
        if (data.success && data.pages) {
            renderPages(data.pages, data.pagination);
        } else {
            showPagesEmpty();
        }
    } catch (error) {
        console.error('Error loading pages:', error);
        showPagesError('Failed to load URLs');
    }
}

/**
 * Render pages table
 */
function renderPages(pages, pagination) {
    if (pages.length === 0) {
        showPagesEmpty();
        return;
    }
    
    // Store current pages for detail drawer access
    window.currentPages = pages;
    
    pagesTableBody.innerHTML = pages.map(page => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3">
                <input type="checkbox" class="page-checkbox" data-page-id="${page.id}" />
            </td>
            <td class="px-6 py-4">
                <div class="flex items-start">
                    <i class="bi bi-link-45deg text-gray-400 mr-2 mt-0.5"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-900 font-medium break-all">${escapeHtml(page.original_url)}</p>
                        ${page.original_url !== page.normalized_url ? `
                            <p class="text-xs text-gray-500 mt-1">Normalized: ${escapeHtml(page.normalized_url)}</p>
                        ` : ''}
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                ${getAuditResultBadge(page.h1_status, page.h1_data)}
            </td>
            <td class="px-6 py-4">
                ${getAuditResultBadge(page.meta_status, page.meta_data)}
            </td>
            <td class="px-6 py-4">
                ${getAuditResultBadge(page.noindex_status, page.noindex_data)}
            </td>
            <td class="px-6 py-4">
                ${getAuditResultBadge(page.faq_status, page.faq_data)}
            </td>
            <td class="px-6 py-4">
                ${getAuditResultBadge(page.lorem_status, page.lorem_data)}
            </td>
            <td class="px-6 py-4">
                ${getAuditResultBadge(page.staging_status, page.staging_data)}
            </td>
            <td class="px-6 py-4">
                ${getAuditResultBadge(page.word_status, page.word_data)}
            </td>
            <td class="px-6 py-4">
                <button onclick="viewPageDetails(${page.id})" class="text-gray-400 hover:text-indigo-600 transition-colors" title="View details">
                    <i class="bi bi-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
    
    renderPagesPagination(pagination);
}

/**
 * Render pagination
 */
function renderPagesPagination(pagination) {
    if (!paginationContainer) return;
    
    if (pagination.total_pages <= 1) {
        paginationContainer.classList.add('hidden');
        return;
    }
    
    paginationContainer.classList.remove('hidden');
    
    let html = '';
    
    // Previous button
    if (pagination.current_page > 1) {
        html += `
            <button onclick="goToPageNumber(${pagination.current_page - 1})" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                <i class="bi bi-chevron-left"></i>
            </button>
        `;
    }
    
    // Page numbers
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === pagination.current_page) {
            html += `
                <button class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg">
                    ${i}
                </button>
            `;
        } else if (i === 1 || i === pagination.total_pages || Math.abs(i - pagination.current_page) <= 2) {
            html += `
                <button onclick="goToPageNumber(${i})" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    ${i}
                </button>
            `;
        } else if (Math.abs(i - pagination.current_page) === 3) {
            html += `<span class="px-2 text-gray-500">...</span>`;
        }
    }
    
    // Next button
    if (pagination.current_page < pagination.total_pages) {
        html += `
            <button onclick="goToPageNumber(${pagination.current_page + 1})" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                <i class="bi bi-chevron-right"></i>
            </button>
        `;
    }
    
    paginationContainer.innerHTML = html;
}

/**
 * Go to specific page
 */
function goToPageNumber(page) {
    currentPage = page;
    loadPages();
}

/**
 * Load page statistics
 */
async function loadPageStats() {
    if (!pageStatsContainer) return;
    
    try {
        const response = await fetch(`/routes/pages.php?action=stats&project_id=${projectId}`);
        const data = await response.json();
        
        if (data.success && data.stats) {
            renderPageStats(data.stats);
        }
    } catch (error) {
        console.error('Error loading page stats:', error);
    }
}

/**
 * Render page statistics
 */
function renderPageStats(stats) {
    if (!pageStatsContainer) return;
    
    pageStatsContainer.innerHTML = `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <p class="text-2xl font-bold text-gray-800">${stats.total_pages}</p>
                <p class="text-xs text-gray-600 mt-1">Total URLs</p>
            </div>
            <div class="text-center p-3 bg-green-50 rounded-lg">
                <p class="text-2xl font-bold text-green-600">${stats.unique_pages}</p>
                <p class="text-xs text-gray-600 mt-1">Unique URLs</p>
            </div>
            <div class="text-center p-3 bg-blue-50 rounded-lg">
                <p class="text-2xl font-bold text-blue-600">${stats.pending}</p>
                <p class="text-xs text-gray-600 mt-1">Pending Audit</p>
            </div>
            <div class="text-center p-3 bg-purple-50 rounded-lg">
                <p class="text-2xl font-bold text-purple-600">${stats.completed}</p>
                <p class="text-xs text-gray-600 mt-1">Completed</p>
            </div>
        </div>
    `;
}

/**
 * Show loading state
 */
function showPagesLoading() {
    if (!pagesTableBody) return;
    
    pagesTableBody.innerHTML = `
        <tr>
            <td colspan="10" class="px-6 py-12 text-center">
                <div class="flex flex-col items-center justify-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mb-4"></div>
                    <p class="text-sm text-gray-600">Loading URLs...</p>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Show empty state
 */
function showPagesEmpty() {
    if (!pagesTableBody) return;
    
    pagesTableBody.innerHTML = `
        <tr>
            <td colspan="10" class="px-6 py-12 text-center">
                <div class="flex flex-col items-center justify-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <i class="bi bi-link-45deg text-3xl text-gray-400"></i>
                    </div>
                    <h5 class="text-base font-semibold text-gray-800 mb-2">No URLs Found</h5>
                    <p class="text-sm text-gray-600 mb-4">Import URLs from a sitemap to get started</p>
                </div>
            </td>
        </tr>
    `;
    
    if (paginationContainer) {
        paginationContainer.classList.add('hidden');
    }
}

/**
 * Show error state
 */
function showPagesError(message) {
    if (!pagesTableBody) return;
    
    pagesTableBody.innerHTML = `
        <tr>
            <td colspan="10" class="px-6 py-12 text-center">
                <div class="flex flex-col items-center justify-center">
                    <i class="bi bi-exclamation-triangle text-4xl text-red-500 mb-4"></i>
                    <p class="text-sm text-gray-800">${escapeHtml(message)}</p>
                </div>
            </td>
        </tr>
    `;
}

/**
 * View page details - opens detail drawer with all audit results
 */
function viewPageDetails(pageId) {
    const page = (window.currentPages || []).find(p => String(p.id) === String(pageId));

    if (!page) {
        alert('Page details not available. Please reload the page.');
        return;
    }

    renderPageDetailDrawer(page);
}

/**
 * Build a section of module results for the detail drawer
 */
function buildModuleResultSection(label, icon, status, dataJson) {
    const badge = getAuditResultBadge(status, dataJson);
    let detailHtml = '<p class="text-xs text-gray-500">Not run yet</p>';

    if (dataJson) {
        try {
            const data = typeof dataJson === 'string' ? JSON.parse(dataJson) : dataJson;
            detailHtml = renderModuleDetail(label, data);
        } catch (e) {
            detailHtml = '<p class="text-xs text-red-500">Unable to parse result data</p>';
        }
    }

    return `
        <div class="border border-gray-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-semibold text-gray-800">
                    <i class="bi ${icon} mr-1"></i>${label}
                </h4>
                ${badge}
            </div>
            <div class="mt-2">${detailHtml}</div>
        </div>
    `;
}

/**
 * Render module-specific detail content
 */
function renderModuleDetail(label, data) {
    if (data.error) {
        return `<p class="text-xs text-red-600"><strong>Error:</strong> ${escapeHtml(data.error)}</p>`;
    }

    // Word Occurrence audit (has keyword_results array)
    if (data.keyword_results && Array.isArray(data.keyword_results)) {
        const kw = data.keywords ? data.keywords.join(', ') : '';
        let html = `<p class="text-xs text-gray-700 mb-1"><strong>Keywords:</strong> ${escapeHtml(kw)}</p>`;
        html += `<p class="text-xs text-gray-700 mb-2"><strong>Total occurrences:</strong> ${data.total_occurrences || 0}</p>`;

        if (data.keyword_results.length > 0) {
            html += '<div class="space-y-1 mb-2">';
            data.keyword_results.forEach(kr => {
                const foundClass = kr.found ? 'text-green-600' : 'text-red-500';
                const locs = kr.locations && Object.keys(kr.locations).length > 0
                    ? ' (' + Object.entries(kr.locations).map(([k, v]) => `${k}: ${v}`).join(', ') + ')'
                    : '';
                html += `<div class="text-xs ${foundClass}">
                    <strong>${escapeHtml(kr.keyword)}:</strong> ${kr.occurrences} occurrence(s)${escapeHtml(locs)}
                </div>`;
            });
            html += '</div>';
        }

        if (data.snippets && data.snippets.length > 0) {
            html += '<div class="space-y-1">';
            data.snippets.slice(0, 5).forEach(s => {
                html += `<div class="text-xs bg-gray-50 border border-gray-100 rounded px-2 py-1">
                    <span class="text-indigo-600 font-medium">${escapeHtml(s.keyword)}:</span>
                    <span class="text-gray-600">${escapeHtml(s.snippet)}</span>
                </div>`;
            });
            html += '</div>';
        }

        return html;
    }

    // Lorem Ipsum audit
    if (typeof data.total_occurrences !== 'undefined' && data.keywords_found) {
        if (data.total_occurrences === 0) {
            return '<p class="text-xs text-green-600">No placeholder content detected</p>';
        }
        let html = `<p class="text-xs text-gray-700 mb-1"><strong>Keywords found:</strong> ${escapeHtml((data.keywords_found || []).join(', '))}</p>`;
        html += `<p class="text-xs text-gray-700 mb-2"><strong>Occurrences:</strong> ${data.total_occurrences}</p>`;
        if (data.snippets && data.snippets.length > 0) {
            html += '<div class="space-y-1">';
            data.snippets.slice(0, 5).forEach(s => {
                html += `<div class="text-xs bg-gray-50 border border-gray-100 rounded px-2 py-1">
                    <span class="text-indigo-600 font-medium">${escapeHtml(s.keyword)}:</span>
                    <span class="text-gray-600">${escapeHtml(s.snippet)}</span>
                </div>`;
            });
            html += '</div>';
        }
        return html;
    }

    // Staging audit
    if (typeof data.total_occurrences !== 'undefined' && data.staging_urls) {
        if (data.total_occurrences === 0) {
            return '<p class="text-xs text-green-600">No staging references detected</p>';
        }
        let html = `<p class="text-xs text-gray-700 mb-1"><strong>Staging references:</strong> ${data.total_occurrences}</p>`;
        if (data.source_locations && Object.keys(data.source_locations).length > 0) {
            const locs = Object.entries(data.source_locations).map(([k, v]) => `${k} (${v})`).join(', ');
            html += `<p class="text-xs text-gray-700 mb-2"><strong>Locations:</strong> ${escapeHtml(locs)}</p>`;
        }
        if (data.staging_urls && data.staging_urls.length > 0) {
            html += '<div class="space-y-1">';
            data.staging_urls.slice(0, 8).forEach(url => {
                html += `<div class="text-xs bg-gray-50 border border-gray-100 rounded px-2 py-1 break-all text-gray-600">${escapeHtml(url)}</div>`;
            });
            html += '</div>';
        }
        return html;
    }

    // H1 audit
    if (typeof data.h1_count !== 'undefined') {
        let html = `<p class="text-xs text-gray-700"><strong>H1 count:</strong> ${data.h1_count}</p>`;
        if (data.h1_text) {
            html += `<p class="text-xs text-gray-700 mt-1"><strong>H1 text:</strong> ${escapeHtml(data.h1_text)}</p>`;
        }
        return html;
    }

    // Meta audit
    if (typeof data.title !== 'undefined' || typeof data.meta_description !== 'undefined') {
        let html = '';
        if (data.title) {
            html += `<p class="text-xs text-gray-700"><strong>Title:</strong> ${escapeHtml(data.title)} (${data.title_length || 0} chars)</p>`;
        }
        if (data.meta_description) {
            html += `<p class="text-xs text-gray-700 mt-1"><strong>Description:</strong> ${escapeHtml(data.meta_description)} (${data.description_length || 0} chars)</p>`;
        }
        if (data.og_image) {
            html += `<p class="text-xs text-gray-700 mt-1 break-all"><strong>OG Image:</strong> ${escapeHtml(data.og_image)}</p>`;
        }
        return html || '<p class="text-xs text-gray-500">No meta data found</p>';
    }

    // Noindex audit
    if (typeof data.index_status !== 'undefined') {
        let html = `<p class="text-xs text-gray-700"><strong>Index:</strong> ${escapeHtml(data.index_status)}</p>`;
        html += `<p class="text-xs text-gray-700 mt-1"><strong>Follow:</strong> ${escapeHtml(data.follow_status || 'n/a')}</p>`;
        if (data.robots_content) {
            html += `<p class="text-xs text-gray-700 mt-1"><strong>Robots:</strong> ${escapeHtml(data.robots_content)}</p>`;
        }
        return html;
    }

    // FAQ schema audit
    if (typeof data.schema_found !== 'undefined') {
        let html = `<p class="text-xs text-gray-700"><strong>Schema found:</strong> ${data.schema_found ? 'Yes' : 'No'}</p>`;
        if (data.schema_type) {
            html += `<p class="text-xs text-gray-700 mt-1"><strong>Type:</strong> ${escapeHtml(data.schema_type)}</p>`;
        }
        if (typeof data.content_found !== 'undefined') {
            html += `<p class="text-xs text-gray-700 mt-1"><strong>FAQ content:</strong> ${data.content_found ? 'Detected' : 'Not detected'}</p>`;
        }
        return html;
    }

    return '<p class="text-xs text-gray-500">No additional detail available</p>';
}

/**
 * Render the page detail drawer
 */
function renderPageDetailDrawer(page) {
    let drawer = document.getElementById('pageDetailDrawer');

    if (!drawer) {
        drawer = document.createElement('div');
        drawer.id = 'pageDetailDrawer';
        document.body.appendChild(drawer);
    }

    const modulesHtml = [
        buildModuleResultSection('H1 Audit', 'bi-tag', page.h1_status, page.h1_data),
        buildModuleResultSection('Meta Audit', 'bi-card-text', page.meta_status, page.meta_data),
        buildModuleResultSection('Noindex Audit', 'bi-eye-slash', page.noindex_status, page.noindex_data),
        buildModuleResultSection('FAQ Schema Audit', 'bi-question-circle', page.faq_status, page.faq_data),
        buildModuleResultSection('Lorem Ipsum Audit', 'bi-file-text', page.lorem_status, page.lorem_data),
        buildModuleResultSection('Staging URL Audit', 'bi-hdd-network', page.staging_status, page.staging_data),
        buildModuleResultSection('Word Occurrence Audit', 'bi-search', page.word_status, page.word_data)
    ].join('');

    drawer.innerHTML = `
        <div class="fixed inset-0 bg-black bg-opacity-50 z-50" onclick="closePageDetailDrawer()"></div>
        <div class="fixed inset-y-0 right-0 z-50 w-full max-w-xl bg-white shadow-2xl overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Page Audit Details</h3>
                    <p class="text-xs text-gray-500 break-all mt-1">${escapeHtml(page.original_url)}</p>
                </div>
                <button onclick="closePageDetailDrawer()" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            <div class="px-6 py-4 space-y-4">
                <!-- URL Info -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-gray-800 mb-2">URL Information</h4>
                    <p class="text-xs text-gray-700 break-all"><strong>Original:</strong> ${escapeHtml(page.original_url)}</p>
                    <p class="text-xs text-gray-700 break-all mt-1"><strong>Normalized:</strong> ${escapeHtml(page.normalized_url || page.original_url)}</p>
                    ${page.sitemap_url ? `<p class="text-xs text-gray-700 break-all mt-1"><strong>Sitemap:</strong> ${escapeHtml(page.sitemap_url)}</p>` : ''}
                    <p class="text-xs text-gray-700 mt-1"><strong>Status:</strong> ${escapeHtml(page.url_status || 'active')}</p>
                </div>

                <!-- Module Results -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-800 mb-3">Module Results</h4>
                    <div class="space-y-3">
                        ${modulesHtml}
                    </div>
                </div>

                <!-- Future sections placeholders -->
                <div class="border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-semibold text-gray-400 mb-2">Audit History</h4>
                    <p class="text-xs text-gray-400">History tracking available in audit management.</p>
                </div>
            </div>
        </div>
    `;

    drawer.classList.remove('hidden');
}

/**
 * Close the page detail drawer
 */
function closePageDetailDrawer() {
    const drawer = document.getElementById('pageDetailDrawer');
    if (drawer) {
        drawer.classList.add('hidden');
        drawer.innerHTML = '';
    }
}

/**
 * Utility functions
 */

function getAuditResultBadge(status, dataJson) {
    if (!status) {
        return '<span class="status-badge badge-secondary text-xs"><i class="bi bi-dash mr-1"></i>Not Run</span>';
    }
    
    const badges = {
        'PASS': '<span class="status-badge badge-success text-xs"><i class="bi bi-check-circle mr-1"></i>Pass</span>',
        'WARNING': '<span class="status-badge badge-warning text-xs"><i class="bi bi-exclamation-triangle mr-1"></i>Warning</span>',
        'FAIL': '<span class="status-badge badge-danger text-xs"><i class="bi bi-x-circle mr-1"></i>Fail</span>',
        'ERROR': '<span class="status-badge badge-danger text-xs"><i class="bi bi-exclamation-octagon mr-1"></i>Error</span>'
    };
    
    let title = status;
    if (dataJson) {
        try {
            const data = typeof dataJson === 'string' ? JSON.parse(dataJson) : dataJson;
            if (data.error) {
                title = `Error: ${data.error}`;
            } else if (data.h1_text) {
                title = `H1: ${data.h1_text}`;
            } else if (data.title) {
                title = `Title: ${data.title} (${data.title_length} chars)`;
            } else if (data.keywords_found && data.keywords_found.length > 0) {
                title = `Placeholder content: ${data.keywords_found.join(', ')} (${data.total_occurrences} occurrence(s))`;
            } else if (data.staging_urls && data.staging_urls.length > 0) {
                title = `Staging references: ${data.total_occurrences} found`;
            } else if (data.keyword_results && data.keyword_results.length > 0) {
                const kw = data.keywords ? data.keywords.join(', ') : '';
                title = `'${kw}': ${data.total_occurrences} occurrence(s)`;
            } else if (typeof data.total_occurrences !== 'undefined' && data.total_occurrences === 0) {
                title = status === 'PASS' ? 'No issues found' : status;
            }
        } catch (e) {
            console.error('Error parsing audit data:', e);
        }
    }
    
    return `<span title="${escapeHtml(title)}">${badges[status] || badges.ERROR}</span>`;
}

function getUrlStatusBadge(status) {
    const badges = {
        'active': '<span class="status-badge badge-success"><i class="bi bi-check-circle mr-1"></i>Active</span>',
        'disabled': '<span class="status-badge badge-secondary"><i class="bi bi-dash-circle mr-1"></i>Disabled</span>',
        'removed': '<span class="status-badge badge-danger"><i class="bi bi-x-circle mr-1"></i>Removed</span>',
        'pending_audit': '<span class="status-badge badge-info"><i class="bi bi-clock mr-1"></i>Pending Audit</span>'
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
    if (diffMins < 60) return `${diffMins}m ago`;
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
