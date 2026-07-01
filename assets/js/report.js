/**
 * Interactive Report — Client-Side Engine
 * 
 * Handles filtering, searching, sorting, pagination, and expandable rows.
 * All operations are client-side for instant responsiveness.
 * Read-only — no mutations, no admin actions.
 */
(function () {
    'use strict';

    // --- State ---
    const state = {
        pages: REPORT_DATA || [],
        filtered: [],
        statusFilter: 'all',
        moduleFilter: 'all',
        searchQuery: '',
        sortField: null,
        sortDir: 'asc',
        currentPage: 1,
        perPage: 25,
        expandedRows: new Set()
    };

    // --- Status priority for sorting ---
    const STATUS_PRIORITY = { ERROR: 0, FAIL: 1, WARNING: 2, PASS: 3 };

    // --- DOM refs ---
    const els = {};

    // --- Init ---
    function init() {
        els.searchInput = document.getElementById('reportSearch');
        els.clearSearch = document.getElementById('clearSearch');
        els.resultsBody = document.getElementById('resultsBody');
        els.mobileResults = document.getElementById('mobileResults');
        els.resultCount = document.getElementById('resultCount');
        els.paginationInfo = document.getElementById('paginationInfo');
        els.paginationControls = document.getElementById('paginationControls');
        els.noResults = document.getElementById('noResults');
        els.resultsTable = document.getElementById('resultsTable');

        bindEvents();
        applyFilters();
    }

    // --- Event Binding ---
    function bindEvents() {
        // Search
        els.searchInput.addEventListener('input', debounce(function () {
            state.searchQuery = this.value.trim().toLowerCase();
            state.currentPage = 1;
            els.clearSearch.classList.toggle('hidden', !state.searchQuery);
            applyFilters();
        }, 200));

        els.clearSearch.addEventListener('click', function () {
            els.searchInput.value = '';
            state.searchQuery = '';
            state.currentPage = 1;
            this.classList.add('hidden');
            applyFilters();
        });

        // Status filter chips
        document.querySelectorAll('.filter-chip[data-status]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                document.querySelectorAll('.filter-chip[data-status]').forEach(function (c) { c.classList.remove('active'); });
                this.classList.add('active');
                state.statusFilter = this.dataset.status;
                state.currentPage = 1;
                applyFilters();
            });
        });

        // Module filter chips
        document.querySelectorAll('.filter-chip[data-module-filter]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                document.querySelectorAll('.filter-chip[data-module-filter]').forEach(function (c) { c.classList.remove('active'); });
                this.classList.add('active');
                state.moduleFilter = this.dataset.moduleFilter;
                state.currentPage = 1;
                applyFilters();
            });
        });

        // Overview card quick filters
        document.querySelectorAll('.filter-chip-card[data-filter]').forEach(function (card) {
            card.addEventListener('click', function () {
                var filter = this.dataset.filter;
                if (filter === 'all') {
                    state.statusFilter = 'all';
                } else {
                    state.statusFilter = filter;
                }
                // Activate corresponding chip
                document.querySelectorAll('.filter-chip[data-status]').forEach(function (c) { c.classList.remove('active'); });
                var target = document.querySelector('.filter-chip[data-status="' + state.statusFilter + '"]');
                if (target) target.classList.add('active');
                state.currentPage = 1;
                applyFilters();
                document.getElementById('results').scrollIntoView({ behavior: 'smooth' });
            });
        });

        // Module card filters
        document.querySelectorAll('.module-filter-card[data-module]').forEach(function (card) {
            card.addEventListener('click', function () {
                var moduleMap = {
                    'h1_audit': 'h1', 'meta_audit': 'meta', 'noindex_audit': 'noindex',
                    'faq_schema_audit': 'faq', 'lorem_audit': 'lorem',
                    'staging_audit': 'staging', 'word_occurrence_audit': 'word'
                };
                var modKey = moduleMap[this.dataset.module] || 'all';
                state.moduleFilter = modKey;
                document.querySelectorAll('.filter-chip[data-module-filter]').forEach(function (c) { c.classList.remove('active'); });
                var target = document.querySelector('.filter-chip[data-module-filter="' + modKey + '"]');
                if (target) target.classList.add('active');
                state.currentPage = 1;
                applyFilters();
                document.getElementById('results').scrollIntoView({ behavior: 'smooth' });
            });
        });

        // Sort buttons
        document.querySelectorAll('.sort-btn[data-sort]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var field = this.dataset.sort;
                if (state.sortField === field) {
                    state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sortField = field;
                    state.sortDir = 'asc';
                }
                // Visual feedback
                document.querySelectorAll('.sort-btn').forEach(function (b) { b.classList.remove('asc', 'desc'); });
                this.classList.add(state.sortDir);
                state.currentPage = 1;
                applyFilters();
            });
        });
    }

    // --- Filter Logic ---
    function applyFilters() {
        var result = state.pages.slice();

        // Status filter
        if (state.statusFilter !== 'all') {
            result = result.filter(function (p) {
                return getPageWorstStatus(p) === state.statusFilter;
            });
        }

        // Module filter: show only pages with a specific status for that module
        if (state.moduleFilter !== 'all') {
            var key = state.moduleFilter + '_status';
            result = result.filter(function (p) {
                return p[key] !== null && p[key] !== undefined;
            });
        }

        // Search
        if (state.searchQuery) {
            var q = state.searchQuery;
            result = result.filter(function (p) {
                var url = (p.original_url || '').toLowerCase();
                var h1 = (p.h1_value || '').toLowerCase();
                var meta = (p.meta_value || '').toLowerCase();
                return url.indexOf(q) !== -1 || h1.indexOf(q) !== -1 || meta.indexOf(q) !== -1;
            });
        }

        // Sort
        if (state.sortField) {
            result.sort(function (a, b) {
                var valA, valB;
                if (state.sortField === 'url') {
                    valA = (a.original_url || '').toLowerCase();
                    valB = (b.original_url || '').toLowerCase();
                    return state.sortDir === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                } else {
                    var key = state.sortField + '_status';
                    valA = statusPriority(a[key]);
                    valB = statusPriority(b[key]);
                    return state.sortDir === 'asc' ? valA - valB : valB - valA;
                }
            });
        }

        state.filtered = result;
        render();
    }

    function getPageWorstStatus(page) {
        var statuses = [
            page.h1_status, page.meta_status, page.noindex_status,
            page.faq_status, page.lorem_status, page.staging_status, page.word_status
        ];
        var hasAny = false;
        for (var i = 0; i < statuses.length; i++) {
            if (statuses[i]) hasAny = true;
            if (statuses[i] === 'ERROR') return 'error';
            if (statuses[i] === 'FAIL') return 'fail';
        }
        for (var j = 0; j < statuses.length; j++) {
            if (statuses[j] === 'WARNING') return 'warning';
        }
        if (!hasAny) return 'none';
        return 'pass';
    }

    function statusPriority(s) {
        if (!s) return 99;
        return STATUS_PRIORITY[s] !== undefined ? STATUS_PRIORITY[s] : 50;
    }

    // --- Rendering ---
    function render() {
        var total = state.filtered.length;
        var start = (state.currentPage - 1) * state.perPage;
        var end = Math.min(start + state.perPage, total);
        var pageData = state.filtered.slice(start, end);

        // Update count
        els.resultCount.textContent = total;

        // Show/hide no results
        if (total === 0) {
            els.noResults.classList.remove('hidden');
            if (els.resultsTable) els.resultsTable.parentElement.classList.add('hidden');
            if (els.mobileResults) els.mobileResults.classList.add('hidden');
        } else {
            els.noResults.classList.add('hidden');
            if (els.resultsTable) els.resultsTable.parentElement.classList.remove('hidden');
            if (els.mobileResults) els.mobileResults.classList.remove('hidden');
        }

        // Desktop table
        renderDesktopTable(pageData);

        // Mobile cards
        renderMobileCards(pageData);

        // Pagination
        renderPagination(total, start, end);
    }

    function renderDesktopTable(pageData) {
        if (!els.resultsBody) return;
        var html = '';
        for (var i = 0; i < pageData.length; i++) {
            var p = pageData[i];
            var rowIdx = (state.currentPage - 1) * state.perPage + i;
            var isExpanded = state.expandedRows.has(rowIdx);
            html += renderDesktopRow(p, rowIdx, isExpanded);
        }
        els.resultsBody.innerHTML = html;

        // Bind expand clicks
        els.resultsBody.querySelectorAll('.expand-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleExpand(parseInt(this.dataset.idx));
            });
        });
        els.resultsBody.querySelectorAll('.row-clickable').forEach(function (row) {
            row.addEventListener('click', function () {
                toggleExpand(parseInt(this.dataset.idx));
            });
        });
    }

    function renderDesktopRow(p, idx, isExpanded) {
        var url = escHtml(p.original_url || '');
        var shortUrl = url.length > 55 ? url.substring(0, 55) + '...' : url;
        var chevron = isExpanded ? 'bi-chevron-up' : 'bi-chevron-down';

        var row = '<tr class="row-clickable hover:bg-gray-50 cursor-pointer transition-colors" data-idx="' + idx + '">';
        row += '<td class="px-4 py-3 text-gray-700 max-w-xs"><span class="text-xs font-medium" title="' + url + '">' + shortUrl + '</span></td>';
        row += '<td class="px-3 py-3 text-center">' + badge(p.h1_status) + '</td>';
        row += '<td class="px-3 py-3 text-center">' + badge(p.meta_status) + '</td>';
        row += '<td class="px-3 py-3 text-center">' + badge(p.noindex_status) + '</td>';
        row += '<td class="px-3 py-3 text-center">' + badge(p.faq_status) + '</td>';
        row += '<td class="px-3 py-3 text-center">' + badge(p.lorem_status) + '</td>';
        row += '<td class="px-3 py-3 text-center">' + badge(p.staging_status) + '</td>';
        row += '<td class="px-3 py-3 text-center">' + badge(p.word_status) + '</td>';
        row += '<td class="px-3 py-3 text-center"><button class="expand-toggle text-gray-400 hover:text-brand-600 transition" data-idx="' + idx + '"><i class="bi ' + chevron + '"></i></button></td>';
        row += '</tr>';

        // Expanded detail row
        row += '<tr class="expanded-row" data-expand-idx="' + idx + '">';
        row += '<td colspan="9" class="p-0 border-0">';
        row += '<div class="expand-content ' + (isExpanded ? 'open' : '') + '">';
        row += renderExpandedContent(p);
        row += '</div></td></tr>';

        return row;
    }

    function renderExpandedContent(p) {
        var html = '<div class="bg-gray-50 border-t border-gray-200 px-6 py-5">';
        html += '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

        // Full URL
        html += '<div class="lg:col-span-3 mb-2">';
        html += '<p class="text-xs text-gray-500 mb-1">Full URL</p>';
        html += '<p class="text-sm text-gray-800 break-all font-medium">' + escHtml(p.original_url || '') + '</p>';
        html += '</div>';

        // Module details
        var mods = [
            { key: 'h1', label: 'H1 Audit', icon: 'bi-tag', status: p.h1_status, value: p.h1_value, checked: p.h1_checked },
            { key: 'meta', label: 'Meta Audit', icon: 'bi-card-text', status: p.meta_status, value: p.meta_value, checked: p.meta_checked },
            { key: 'noindex', label: 'Noindex Audit', icon: 'bi-eye-slash', status: p.noindex_status, value: p.noindex_value, checked: p.noindex_checked },
            { key: 'faq', label: 'FAQ Schema', icon: 'bi-question-circle', status: p.faq_status, value: p.faq_value, checked: p.faq_checked },
            { key: 'lorem', label: 'Lorem Ipsum', icon: 'bi-file-text', status: p.lorem_status, value: p.lorem_value, checked: p.lorem_checked },
            { key: 'staging', label: 'Staging URLs', icon: 'bi-hdd-network', status: p.staging_status, value: p.staging_value, checked: p.staging_checked },
            { key: 'word', label: 'Word Occurrence', icon: 'bi-search', status: p.word_status, value: p.word_value, checked: p.word_checked }
        ];

        for (var i = 0; i < mods.length; i++) {
            var m = mods[i];
            html += '<div class="bg-white rounded-lg border border-gray-200 p-3">';
            html += '<div class="flex items-center justify-between mb-2">';
            html += '<div class="flex items-center gap-2"><i class="bi ' + m.icon + ' text-brand-600 text-sm"></i><span class="text-xs font-semibold text-gray-700">' + m.label + '</span></div>';
            html += badge(m.status);
            html += '</div>';
            if (m.value) {
                var val = escHtml(m.value);
                if (val.length > 120) val = val.substring(0, 120) + '...';
                html += '<p class="text-xs text-gray-600 mt-1 break-all">' + val + '</p>';
            } else if (!m.status) {
                html += '<p class="text-xs text-gray-400 italic">Not audited</p>';
            }
            if (m.checked) {
                html += '<p class="text-[10px] text-gray-400 mt-1.5"><i class="bi bi-clock mr-0.5"></i>' + formatDate(m.checked) + '</p>';
            }
            html += '</div>';
        }

        // Last audited
        if (p.last_audited && p.last_audited !== '1970-01-01 00:00:00') {
            html += '<div class="lg:col-span-3 mt-2 pt-3 border-t border-gray-200">';
            html += '<p class="text-xs text-gray-500"><i class="bi bi-clock-history mr-1"></i>Last audited: <strong>' + formatDate(p.last_audited) + '</strong></p>';
            html += '</div>';
        }

        html += '</div></div>';
        return html;
    }

    function renderMobileCards(pageData) {
        if (!els.mobileResults) return;
        var html = '<div class="divide-y divide-gray-200">';
        for (var i = 0; i < pageData.length; i++) {
            var p = pageData[i];
            var rowIdx = (state.currentPage - 1) * state.perPage + i;
            var isExpanded = state.expandedRows.has(rowIdx);
            html += renderMobileCard(p, rowIdx, isExpanded);
        }
        html += '</div>';
        els.mobileResults.innerHTML = html;

        // Bind expand
        els.mobileResults.querySelectorAll('.mobile-expand-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                toggleExpand(parseInt(this.dataset.idx));
            });
        });
    }

    function renderMobileCard(p, idx, isExpanded) {
        var url = escHtml(p.original_url || '');
        var shortUrl = url.length > 40 ? url.substring(0, 40) + '...' : url;
        var worstStatus = getPageWorstStatus(p);
        var statusColor = worstStatus === 'pass' ? 'border-green-200' :
            worstStatus === 'fail' ? 'border-red-200' :
            worstStatus === 'warning' ? 'border-yellow-200' :
            worstStatus === 'error' ? 'border-red-300' : 'border-gray-200';

        var html = '<div class="mobile-expand-toggle p-4 cursor-pointer hover:bg-gray-50 transition-colors" data-idx="' + idx + '">';
        html += '<div class="flex items-start justify-between mb-3">';
        html += '<p class="text-xs text-gray-800 font-medium break-all flex-1 pr-2" title="' + url + '">' + shortUrl + '</p>';
        html += '<i class="bi ' + (isExpanded ? 'bi-chevron-up' : 'bi-chevron-down') + ' text-gray-400 text-sm flex-shrink-0 mt-0.5"></i>';
        html += '</div>';

        // Compact status grid
        html += '<div class="grid grid-cols-4 gap-2 text-center">';
        html += '<div><p class="text-[10px] text-gray-400 mb-0.5">H1</p>' + badgeMini(p.h1_status) + '</div>';
        html += '<div><p class="text-[10px] text-gray-400 mb-0.5">Meta</p>' + badgeMini(p.meta_status) + '</div>';
        html += '<div><p class="text-[10px] text-gray-400 mb-0.5">Noindex</p>' + badgeMini(p.noindex_status) + '</div>';
        html += '<div><p class="text-[10px] text-gray-400 mb-0.5">FAQ</p>' + badgeMini(p.faq_status) + '</div>';
        html += '</div>';

        // Second row of modules
        html += '<div class="grid grid-cols-3 gap-2 text-center mt-2">';
        html += '<div><p class="text-[10px] text-gray-400 mb-0.5">Lorem</p>' + badgeMini(p.lorem_status) + '</div>';
        html += '<div><p class="text-[10px] text-gray-400 mb-0.5">Staging</p>' + badgeMini(p.staging_status) + '</div>';
        html += '<div><p class="text-[10px] text-gray-400 mb-0.5">Word</p>' + badgeMini(p.word_status) + '</div>';
        html += '</div>';

        // Expanded content
        html += '<div class="expand-content ' + (isExpanded ? 'open' : '') + '">';
        html += renderMobileExpandedContent(p);
        html += '</div>';

        html += '</div>';
        return html;
    }

    function renderMobileExpandedContent(p) {
        var html = '<div class="mt-4 pt-4 border-t border-gray-100 space-y-3">';

        // Full URL
        html += '<div>';
        html += '<p class="text-[10px] text-gray-400 mb-0.5">Full URL</p>';
        html += '<p class="text-xs text-gray-700 break-all">' + escHtml(p.original_url || '') + '</p>';
        html += '</div>';

        // Module details stacked
        var mods = [
            { label: 'H1 Audit', icon: 'bi-tag', status: p.h1_status, value: p.h1_value, checked: p.h1_checked },
            { label: 'Meta Audit', icon: 'bi-card-text', status: p.meta_status, value: p.meta_value, checked: p.meta_checked },
            { label: 'Noindex', icon: 'bi-eye-slash', status: p.noindex_status, value: p.noindex_value, checked: p.noindex_checked },
            { label: 'FAQ Schema', icon: 'bi-question-circle', status: p.faq_status, value: p.faq_value, checked: p.faq_checked },
            { label: 'Lorem', icon: 'bi-file-text', status: p.lorem_status, value: p.lorem_value, checked: p.lorem_checked },
            { label: 'Staging', icon: 'bi-hdd-network', status: p.staging_status, value: p.staging_value, checked: p.staging_checked },
            { label: 'Word', icon: 'bi-search', status: p.word_status, value: p.word_value, checked: p.word_checked }
        ];

        for (var i = 0; i < mods.length; i++) {
            var m = mods[i];
            if (!m.status && !m.value) continue; // skip unaudited in mobile
            html += '<div class="bg-white rounded-lg border border-gray-200 p-3">';
            html += '<div class="flex items-center justify-between">';
            html += '<span class="text-xs font-medium text-gray-700"><i class="bi ' + m.icon + ' mr-1 text-brand-600"></i>' + m.label + '</span>';
            html += badge(m.status);
            html += '</div>';
            if (m.value) {
                var val = escHtml(m.value);
                if (val.length > 80) val = val.substring(0, 80) + '...';
                html += '<p class="text-[11px] text-gray-500 mt-1.5 break-all">' + val + '</p>';
            }
            if (m.checked) {
                html += '<p class="text-[10px] text-gray-400 mt-1"><i class="bi bi-clock mr-0.5"></i>' + formatDate(m.checked) + '</p>';
            }
            html += '</div>';
        }

        // Last audited
        if (p.last_audited && p.last_audited !== '1970-01-01 00:00:00') {
            html += '<p class="text-[10px] text-gray-400 pt-2 border-t border-gray-100"><i class="bi bi-clock-history mr-1"></i>Last: ' + formatDate(p.last_audited) + '</p>';
        }

        html += '</div>';
        return html;
    }

    // --- Pagination ---
    function renderPagination(total, start, end) {
        if (!els.paginationInfo || !els.paginationControls) return;

        if (total === 0) {
            els.paginationInfo.textContent = 'No results';
            els.paginationControls.innerHTML = '';
            return;
        }

        els.paginationInfo.textContent = 'Showing ' + (start + 1) + '–' + end + ' of ' + total;

        var totalPages = Math.ceil(total / state.perPage);
        if (totalPages <= 1) {
            els.paginationControls.innerHTML = '';
            return;
        }

        var html = '';

        // Previous
        html += '<button class="pg-btn px-2.5 py-1.5 text-xs font-medium rounded-md border border-gray-300 ' +
            (state.currentPage === 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-700 hover:bg-gray-50') +
            '" data-page="' + (state.currentPage - 1) + '" ' + (state.currentPage === 1 ? 'disabled' : '') + '>' +
            '<i class="bi bi-chevron-left"></i></button>';

        // Page numbers
        var pages = getVisiblePages(state.currentPage, totalPages);
        for (var i = 0; i < pages.length; i++) {
            if (pages[i] === '...') {
                html += '<span class="px-1 text-xs text-gray-400">...</span>';
            } else {
                var active = pages[i] === state.currentPage;
                html += '<button class="pg-btn px-3 py-1.5 text-xs font-medium rounded-md ' +
                    (active ? 'bg-brand-600 text-white border border-brand-600' : 'border border-gray-300 text-gray-700 hover:bg-gray-50') +
                    '" data-page="' + pages[i] + '">' + pages[i] + '</button>';
            }
        }

        // Next
        html += '<button class="pg-btn px-2.5 py-1.5 text-xs font-medium rounded-md border border-gray-300 ' +
            (state.currentPage === totalPages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-700 hover:bg-gray-50') +
            '" data-page="' + (state.currentPage + 1) + '" ' + (state.currentPage === totalPages ? 'disabled' : '') + '>' +
            '<i class="bi bi-chevron-right"></i></button>';

        els.paginationControls.innerHTML = html;

        // Bind page clicks
        els.paginationControls.querySelectorAll('.pg-btn:not([disabled])').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var page = parseInt(this.dataset.page);
                if (page >= 1 && page <= totalPages) {
                    state.currentPage = page;
                    state.expandedRows.clear();
                    render();
                    // Scroll to top of results
                    var resultsSection = document.getElementById('results');
                    if (resultsSection) resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    function getVisiblePages(current, total) {
        if (total <= 7) {
            var arr = [];
            for (var i = 1; i <= total; i++) arr.push(i);
            return arr;
        }
        var pages = [];
        pages.push(1);
        if (current > 3) pages.push('...');
        var rangeStart = Math.max(2, current - 1);
        var rangeEnd = Math.min(total - 1, current + 1);
        for (var j = rangeStart; j <= rangeEnd; j++) pages.push(j);
        if (current < total - 2) pages.push('...');
        pages.push(total);
        return pages;
    }

    // --- Toggle Expand ---
    function toggleExpand(idx) {
        if (state.expandedRows.has(idx)) {
            state.expandedRows.delete(idx);
        } else {
            state.expandedRows.add(idx);
        }
        render();
    }

    // --- Badge Helpers ---
    function badge(status) {
        if (!status) return '<span class="s-badge s-none">—</span>';
        switch (status) {
            case 'PASS': return '<span class="s-badge s-pass"><i class="bi bi-check-circle"></i>Pass</span>';
            case 'WARNING': return '<span class="s-badge s-warning"><i class="bi bi-exclamation-triangle"></i>Warn</span>';
            case 'FAIL': return '<span class="s-badge s-fail"><i class="bi bi-x-circle"></i>Fail</span>';
            case 'ERROR': return '<span class="s-badge s-error"><i class="bi bi-exclamation-octagon"></i>Err</span>';
            default: return '<span class="s-badge s-none">—</span>';
        }
    }

    function badgeMini(status) {
        if (!status) return '<span class="s-badge s-none text-[10px]">—</span>';
        switch (status) {
            case 'PASS': return '<span class="s-badge s-pass text-[10px]">✓</span>';
            case 'WARNING': return '<span class="s-badge s-warning text-[10px]">!</span>';
            case 'FAIL': return '<span class="s-badge s-fail text-[10px]">✗</span>';
            case 'ERROR': return '<span class="s-badge s-error text-[10px]">✗</span>';
            default: return '<span class="s-badge s-none text-[10px]">—</span>';
        }
    }

    // --- Utility Functions ---
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        try {
            var d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() + ' ' +
                padZero(d.getHours()) + ':' + padZero(d.getMinutes());
        } catch (e) {
            return dateStr;
        }
    }

    function padZero(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var context = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    // --- Global reset function (called from no-results button) ---
    window.resetFilters = function () {
        state.statusFilter = 'all';
        state.moduleFilter = 'all';
        state.searchQuery = '';
        state.sortField = null;
        state.sortDir = 'asc';
        state.currentPage = 1;
        state.expandedRows.clear();

        els.searchInput.value = '';
        els.clearSearch.classList.add('hidden');

        document.querySelectorAll('.filter-chip[data-status]').forEach(function (c) { c.classList.remove('active'); });
        document.querySelectorAll('.filter-chip[data-module-filter]').forEach(function (c) { c.classList.remove('active'); });
        var allStatus = document.querySelector('.filter-chip[data-status="all"]');
        var allModule = document.querySelector('.filter-chip[data-module-filter="all"]');
        if (allStatus) allStatus.classList.add('active');
        if (allModule) allModule.classList.add('active');

        document.querySelectorAll('.sort-btn').forEach(function (b) { b.classList.remove('asc', 'desc'); });

        applyFilters();
    };

    // --- Boot ---
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
