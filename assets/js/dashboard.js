/**
 * Dashboard Module — Overview, Activity, Search, Notifications
 * 
 * Provides the polished dashboard experience with:
 * - Overview stats and quick actions
 * - Activity timeline
 * - Global search (instant results)
 * - In-app notifications (dismissible)
 * - Toast messages
 * - Dark mode toggle
 */
(function () {
    'use strict';

    const API = 'routes/dashboard.php';
    let notificationPollInterval = null;

    // --- Public API ---
    window.Dashboard = {
        init: init,
        loadOverview: loadOverview,
        search: doSearch,
        showToast: showToast,
        loadNotifications: loadNotifications
    };

    function init() {
        injectGlobalUI();
        bindSearch();
        bindNotifications();
        bindDarkMode();
        startNotificationPolling();
    }

    // ==========================================
    // GLOBAL UI INJECTION
    // ==========================================
    function injectGlobalUI() {
        // Toast container
        if (!document.getElementById('toastContainer')) {
            var tc = document.createElement('div');
            tc.id = 'toastContainer';
            tc.className = 'fixed bottom-4 right-4 z-[9999] space-y-2 max-w-sm w-full pointer-events-none';
            document.body.appendChild(tc);
        }

        // Search modal
        if (!document.getElementById('searchModal')) {
            var sm = document.createElement('div');
            sm.id = 'searchModal';
            sm.className = 'fixed inset-0 z-[9990] hidden';
            sm.innerHTML = renderSearchModal();
            document.body.appendChild(sm);
        }

        // Notification dropdown
        if (!document.getElementById('notifDropdown')) {
            var nd = document.createElement('div');
            nd.id = 'notifDropdown';
            nd.className = 'absolute right-0 top-full mt-2 w-80 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 hidden z-[9980] overflow-hidden';
            var notifBtn = document.querySelector('[data-notif-trigger]');
            if (notifBtn && notifBtn.parentElement) {
                notifBtn.parentElement.style.position = 'relative';
                notifBtn.parentElement.appendChild(nd);
            }
        }
    }

    // ==========================================
    // OVERVIEW
    // ==========================================
    function loadOverview(container) {
        if (!container) return;
        container.innerHTML = renderSkeleton();

        fetch(API + '?action=overview')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) renderOverview(container, data.data);
                else container.innerHTML = '<p class="text-sm text-red-500 p-4">Failed to load dashboard.</p>';
            })
            .catch(function () {
                container.innerHTML = '<p class="text-sm text-red-500 p-4">Network error.</p>';
            });
    }

    function renderOverview(container, data) {
        var s = data.stats;
        var html = '';

        // Stats row
        html += '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">';
        html += statCard('Projects', s.total_projects, 'bi-folder', 'indigo');
        html += statCard('URLs', s.total_pages, 'bi-link-45deg', 'blue');
        html += statCard('Audits', s.total_audits, 'bi-clipboard-check', 'green');
        html += statCard('Overrides', s.total_overrides, 'bi-pencil-square', 'purple');
        html += '</div>';

        // Quick Actions
        html += '<div class="mb-6">';
        html += '<h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-3 uppercase tracking-wide">Quick Actions</h3>';
        html += '<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">';
        html += quickAction('Create Project', 'bi-plus-circle', 'indigo');
        html += quickAction('Import Sitemap', 'bi-upload', 'blue');
        html += quickAction('Run Audit', 'bi-play-circle', 'green');
        html += quickAction('Open Reports', 'bi-file-earmark-text', 'purple');
        html += quickAction('Retry Failed', 'bi-arrow-clockwise', 'orange');
        html += quickAction('Settings', 'bi-gear', 'gray');
        html += '</div></div>';

        // Two-column layout: Recent Projects + Jobs
        html += '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">';

        // Recent Projects
        html += '<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">';
        html += '<div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">';
        html += '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Recent Projects</h4>';
        html += '<span class="text-xs text-gray-400">' + (data.recent_projects.length) + ' latest</span>';
        html += '</div>';
        html += '<div class="divide-y divide-gray-50 dark:divide-gray-700">';
        if (data.recent_projects.length === 0) {
            html += '<p class="px-5 py-4 text-xs text-gray-400">No projects yet.</p>';
        }
        for (var i = 0; i < data.recent_projects.length; i++) {
            var p = data.recent_projects[i];
            html += '<div class="px-5 py-3 flex items-center justify-between">';
            html += '<div class="min-w-0"><p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">' + esc(p.project_name) + '</p>';
            html += '<p class="text-xs text-gray-400 truncate">' + esc(p.primary_domain) + '</p></div>';
            html += '<span class="text-[10px] text-gray-400 flex-shrink-0 ml-2">' + timeAgo(p.created_at) + '</span>';
            html += '</div>';
        }
        html += '</div></div>';

        // Jobs status
        html += '<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">';
        html += '<div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">';
        html += '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Job Status</h4>';
        html += '</div>';
        html += '<div class="p-5 space-y-4">';
        html += jobRow('Running', data.running_jobs.count, 'blue', 'bi-arrow-repeat');
        html += jobRow('Failed', data.failed_jobs.count, 'red', 'bi-x-circle');
        html += '</div>';

        // Recent Reports
        if (data.recent_reports.length > 0) {
            html += '<div class="px-5 pb-4 pt-2 border-t border-gray-100 dark:border-gray-700">';
            html += '<p class="text-xs font-medium text-gray-500 mb-2">Recent Reports</p>';
            for (var j = 0; j < Math.min(data.recent_reports.length, 3); j++) {
                var r = data.recent_reports[j];
                html += '<div class="flex items-center justify-between py-1">';
                html += '<span class="text-xs text-gray-700 dark:text-gray-300 truncate">' + esc(r.project_name) + '</span>';
                html += '<span class="text-[10px] px-1.5 py-0.5 rounded bg-' + (r.visibility === 'public' ? 'green' : 'gray') + '-100 text-' + (r.visibility === 'public' ? 'green' : 'gray') + '-700">' + r.visibility + '</span>';
                html += '</div>';
            }
            html += '</div>';
        }
        html += '</div>';

        html += '</div>';

        container.innerHTML = html;
    }

    function statCard(label, value, icon, color) {
        return '<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm hover:shadow-md transition-shadow">' +
            '<div class="flex items-center justify-between">' +
            '<div><p class="text-2xl font-bold text-gray-800 dark:text-gray-100">' + (value || 0) + '</p>' +
            '<p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">' + label + '</p></div>' +
            '<div class="w-10 h-10 bg-' + color + '-100 dark:bg-' + color + '-900/30 rounded-lg flex items-center justify-center">' +
            '<i class="bi ' + icon + ' text-' + color + '-600 dark:text-' + color + '-400 text-lg"></i></div>' +
            '</div></div>';
    }

    function quickAction(label, icon, color) {
        return '<button class="quick-action bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-center hover:border-' + color + '-300 hover:shadow-sm transition group" data-action="' + label.toLowerCase().replace(/\s/g, '_') + '">' +
            '<i class="bi ' + icon + ' text-lg text-' + color + '-500 group-hover:text-' + color + '-600 block mb-1"></i>' +
            '<span class="text-[11px] font-medium text-gray-600 dark:text-gray-400">' + label + '</span></button>';
    }

    function jobRow(label, count, color, icon) {
        return '<div class="flex items-center justify-between">' +
            '<div class="flex items-center gap-2"><i class="bi ' + icon + ' text-' + color + '-500"></i>' +
            '<span class="text-sm text-gray-700 dark:text-gray-300">' + label + '</span></div>' +
            '<span class="text-sm font-bold text-' + color + '-600">' + (count || 0) + '</span></div>';
    }

    // ==========================================
    // GLOBAL SEARCH
    // ==========================================
    function renderSearchModal() {
        return '<div class="fixed inset-0 bg-black/50 backdrop-blur-sm" id="searchOverlay"></div>' +
            '<div class="fixed top-[15%] left-1/2 -translate-x-1/2 w-[90vw] max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">' +
            '<div class="flex items-center gap-3 px-4 py-3 border-b border-gray-200 dark:border-gray-700">' +
            '<i class="bi bi-search text-gray-400"></i>' +
            '<input type="text" id="globalSearchInput" class="flex-1 text-sm bg-transparent outline-none text-gray-800 dark:text-gray-200 placeholder-gray-400" placeholder="Search projects, URLs, reports..." autocomplete="off">' +
            '<kbd class="hidden sm:inline-block px-1.5 py-0.5 text-[10px] font-mono bg-gray-100 dark:bg-gray-700 text-gray-500 rounded">ESC</kbd>' +
            '</div>' +
            '<div id="searchResults" class="max-h-80 overflow-y-auto p-2">' +
            '<p class="text-xs text-gray-400 text-center py-6">Type to search...</p>' +
            '</div></div>';
    }

    function bindSearch() {
        // Keyboard shortcut: Ctrl+K or Cmd+K
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openSearch();
            }
        });

        // Search button in header
        var searchBtns = document.querySelectorAll('[data-search-trigger]');
        searchBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openSearch();
            });
        });
    }

    function openSearch() {
        var modal = document.getElementById('searchModal');
        if (!modal) return;
        modal.classList.remove('hidden');

        var input = document.getElementById('globalSearchInput');
        if (input) {
            input.value = '';
            input.focus();
            input.addEventListener('input', debounce(function () {
                doSearch(this.value);
            }, 200));
        }

        // Close handlers
        var overlay = document.getElementById('searchOverlay');
        if (overlay) overlay.onclick = closeSearch;
        document.addEventListener('keydown', searchEscHandler);
    }

    function closeSearch() {
        var modal = document.getElementById('searchModal');
        if (modal) modal.classList.add('hidden');
        document.removeEventListener('keydown', searchEscHandler);
    }

    function searchEscHandler(e) {
        if (e.key === 'Escape') closeSearch();
    }

    function doSearch(query) {
        var resultsDiv = document.getElementById('searchResults');
        if (!resultsDiv) return;
        if (!query || query.length < 2) {
            resultsDiv.innerHTML = '<p class="text-xs text-gray-400 text-center py-6">Type to search...</p>';
            return;
        }

        resultsDiv.innerHTML = '<p class="text-xs text-gray-400 text-center py-4"><i class="bi bi-arrow-repeat animate-spin"></i> Searching...</p>';

        fetch(API + '?action=search&q=' + encodeURIComponent(query))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) renderSearchResults(resultsDiv, data.results, query);
            })
            .catch(function () {
                resultsDiv.innerHTML = '<p class="text-xs text-red-400 text-center py-4">Search failed.</p>';
            });
    }

    function renderSearchResults(container, results, query) {
        if (!results || results.length === 0) {
            container.innerHTML = '<p class="text-xs text-gray-400 text-center py-6">No results for "' + esc(query) + '"</p>';
            return;
        }

        var html = '';
        for (var i = 0; i < results.length; i++) {
            var r = results[i];
            html += '<button class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition text-left search-result-item" data-type="' + r.type + '" data-id="' + r.id + '">';
            html += '<div class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center flex-shrink-0"><i class="bi ' + r.icon + ' text-gray-500"></i></div>';
            html += '<div class="min-w-0 flex-1">';
            html += '<p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">' + esc(r.title) + '</p>';
            html += '<p class="text-xs text-gray-400 truncate">' + esc(r.subtitle) + '</p>';
            html += '</div>';
            html += '<span class="text-[10px] text-gray-400 bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded capitalize">' + r.type + '</span>';
            html += '</button>';
        }
        container.innerHTML = html;
    }

    // ==========================================
    // NOTIFICATIONS
    // ==========================================
    function bindNotifications() {
        var btn = document.querySelector('[data-notif-trigger]');
        if (!btn) return;

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleNotifDropdown();
        });

        document.addEventListener('click', function () {
            var dd = document.getElementById('notifDropdown');
            if (dd) dd.classList.add('hidden');
        });
    }

    function toggleNotifDropdown() {
        var dd = document.getElementById('notifDropdown');
        if (!dd) return;
        if (dd.classList.contains('hidden')) {
            loadNotifications();
            dd.classList.remove('hidden');
        } else {
            dd.classList.add('hidden');
        }
    }

    function loadNotifications() {
        fetch(API + '?action=notifications')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) renderNotifications(data.notifications);
            })
            .catch(function () { /* silent */ });
    }

    function renderNotifications(notifications) {
        var dd = document.getElementById('notifDropdown');
        if (!dd) return;

        var html = '<div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">';
        html += '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Notifications</h4>';
        html += '<button class="text-xs text-brand-600 hover:underline" id="markAllReadBtn">Mark all read</button>';
        html += '</div>';

        if (!notifications || notifications.length === 0) {
            html += '<div class="px-4 py-8 text-center"><i class="bi bi-bell-slash text-2xl text-gray-300"></i>';
            html += '<p class="text-xs text-gray-400 mt-2">No notifications</p></div>';
        } else {
            html += '<div class="max-h-72 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">';
            for (var i = 0; i < Math.min(notifications.length, 10); i++) {
                var n = notifications[i];
                var unreadClass = n.is_read === '0' || n.is_read === 0 ? 'bg-blue-50/50 dark:bg-blue-900/10' : '';
                html += '<div class="px-4 py-3 ' + unreadClass + ' flex items-start gap-3 notif-item" data-id="' + n.id + '">';
                html += '<div class="w-7 h-7 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">';
                html += '<i class="bi ' + getNotifIcon(n.type) + ' text-xs text-gray-500"></i></div>';
                html += '<div class="flex-1 min-w-0">';
                html += '<p class="text-xs font-medium text-gray-800 dark:text-gray-200">' + esc(n.title) + '</p>';
                if (n.message) html += '<p class="text-[11px] text-gray-500 mt-0.5 truncate">' + esc(n.message) + '</p>';
                html += '<p class="text-[10px] text-gray-400 mt-1">' + timeAgo(n.created_at) + '</p>';
                html += '</div>';
                html += '<button class="notif-dismiss text-gray-300 hover:text-gray-500 flex-shrink-0" data-id="' + n.id + '"><i class="bi bi-x text-sm"></i></button>';
                html += '</div>';
            }
            html += '</div>';
        }

        dd.innerHTML = html;

        // Bind dismiss
        dd.querySelectorAll('.notif-dismiss').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var id = parseInt(this.dataset.id);
                fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'notification_dismiss', id: id }) });
                this.closest('.notif-item').remove();
            });
        });

        // Mark all read
        var markAllBtn = document.getElementById('markAllReadBtn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function () {
                fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'notifications_read_all' }) });
                dd.querySelectorAll('.notif-item').forEach(function (el) { el.classList.remove('bg-blue-50/50', 'dark:bg-blue-900/10'); });
                updateBadge(0);
            });
        }
    }

    function startNotificationPolling() {
        // Poll every 60 seconds
        notificationPollInterval = setInterval(function () {
            fetch(API + '?action=notifications')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        var unread = data.notifications.filter(function (n) { return n.is_read === '0' || n.is_read === 0; }).length;
                        updateBadge(unread);
                    }
                })
                .catch(function () { /* silent */ });
        }, 60000);
    }

    function updateBadge(count) {
        var badge = document.querySelector('[data-notif-badge]');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 9 ? '9+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    }

    function getNotifIcon(type) {
        var icons = {
            'audit_complete': 'bi-check-circle',
            'project_created': 'bi-folder-plus',
            'error': 'bi-exclamation-triangle',
            'report_updated': 'bi-file-earmark-check',
            'retry_finished': 'bi-arrow-clockwise'
        };
        return icons[type] || 'bi-bell';
    }

    // ==========================================
    // TOAST NOTIFICATIONS
    // ==========================================
    function showToast(message, type) {
        type = type || 'info';
        var container = document.getElementById('toastContainer');
        if (!container) return;

        var colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            warning: 'bg-yellow-500',
            info: 'bg-brand-600'
        };
        var icons = {
            success: 'bi-check-circle',
            error: 'bi-x-circle',
            warning: 'bi-exclamation-triangle',
            info: 'bi-info-circle'
        };

        var toast = document.createElement('div');
        toast.className = 'pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-lg text-white shadow-lg ' + (colors[type] || colors.info) + ' fade-in';
        toast.innerHTML = '<i class="bi ' + (icons[type] || icons.info) + '"></i><span class="text-sm font-medium flex-1">' + esc(message) + '</span>' +
            '<button class="text-white/70 hover:text-white" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>';

        container.appendChild(toast);

        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(function () { toast.remove(); }, 300);
        }, 4000);
    }

    // ==========================================
    // DARK MODE
    // ==========================================
    function bindDarkMode() {
        var toggle = document.getElementById('themeToggle');
        if (!toggle) return;

        // Check saved preference
        var saved = localStorage.getItem('theme');
        if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            document.body.classList.add('dark-mode');
            updateThemeIcon(true);
        }

        toggle.addEventListener('click', function () {
            var isDark = document.documentElement.classList.toggle('dark');
            document.body.classList.toggle('dark-mode', isDark);
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateThemeIcon(isDark);
        });
    }

    function updateThemeIcon(isDark) {
        var toggle = document.getElementById('themeToggle');
        if (!toggle) return;
        var icon = toggle.querySelector('i');
        if (icon) {
            icon.className = isDark ? 'bi bi-sun text-xl' : 'bi bi-moon text-xl';
        }
    }

    // ==========================================
    // SKELETON LOADER
    // ==========================================
    function renderSkeleton() {
        var html = '<div class="space-y-4 animate-pulse">';
        html += '<div class="grid grid-cols-2 md:grid-cols-4 gap-4">';
        for (var i = 0; i < 4; i++) {
            html += '<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 h-20"></div>';
        }
        html += '</div>';
        html += '<div class="grid grid-cols-2 sm:grid-cols-6 gap-3">';
        for (var j = 0; j < 6; j++) {
            html += '<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3 h-16"></div>';
        }
        html += '</div>';
        html += '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">';
        html += '<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 h-48"></div>';
        html += '<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 h-48"></div>';
        html += '</div></div>';
        return html;
    }

    // ==========================================
    // UTILITIES
    // ==========================================
    function esc(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function timeAgo(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr);
        var now = new Date();
        var diff = Math.floor((now - d) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return d.toLocaleDateString();
    }

    function debounce(fn, delay) {
        var timer;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

    // --- Boot ---
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
