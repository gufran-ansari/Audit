/**
 * Security Monitoring Module — Super Admin Dashboard
 * 
 * Provides:
 * - Security event statistics and timeline chart
 * - Filtered security events table with pagination
 * - Alert threshold configuration
 * - Anomaly detection results
 * - Notification settings (email, webhook)
 * - SIEM integration settings
 * - Test notification functionality
 * 
 * Super Admin only.
 */
(function () {
    'use strict';

    const API = 'api/security';

    const SEVERITY_COLORS = {
        high: 'danger',
        medium: 'warning',
        low: 'success'
    };

    const EVENT_TYPES = [
        { key: 'auth_failed', label: 'Failed Authentication', severity: 'high' },
        { key: 'csrf_failed', label: 'CSRF Failure', severity: 'high' },
        { key: 'authz_denied', label: 'Authorization Denied', severity: 'medium' },
        { key: 'suspicious_activity', label: 'Suspicious Activity', severity: 'high' },
        { key: 'alert_fired', label: 'Alert Fired', severity: 'high' }
    ];

    // --- Public API ---
    window.SecurityModule = {
        renderSecurityDashboard: renderSecurityDashboard,
        loadStats: loadStats,
        loadEvents: loadEvents,
        loadAnomalies: loadAnomalies,
        loadThresholds: loadThresholds,
        saveThreshold: saveThreshold,
        loadNotificationSettings: loadNotificationSettings,
        saveNotificationSettings: saveNotificationSettings,
        loadSiemSettings: loadSiemSettings,
        saveSiemSettings: saveSiemSettings,
        sendTestNotification: sendTestNotification
    };

    // --- Render Main Dashboard ---
    function renderSecurityDashboard(container) {
        if (!container) return;

        var html = '<div class="space-y-6">';

        // Header
        html += '<div class="flex items-center justify-between">';
        html += '<div><h2 class="text-2xl font-bold text-gray-800">Security Monitoring</h2>';
        html += '<p class="text-sm text-gray-500 mt-1">Monitor security events, configure alerts, and manage integrations</p></div>';
        html += '<div class="flex gap-2">';
        html += '<button id="refreshBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"><i class="bi bi-arrow-clockwise mr-1.5"></i>Refresh</button>';
        html += '</div></div>';

        // Tab navigation
        html += '<div class="border-b border-gray-200">';
        html += '<nav class="flex gap-1 overflow-x-auto" id="securityTabs">';
        var tabs = [
            { key: 'overview', label: 'Overview', icon: 'bi-graph-up' },
            { key: 'events', label: 'Events', icon: 'bi-list-ul' },
            { key: 'anomalies', label: 'Anomalies', icon: 'bi-exclamation-triangle' },
            { key: 'thresholds', label: 'Alert Thresholds', icon: 'bi-sliders' },
            { key: 'notifications', label: 'Notifications', icon: 'bi-bell' },
            { key: 'siem', label: 'SIEM Integration', icon: 'bi-hdd-network' }
        ];
        for (var i = 0; i < tabs.length; i++) {
            var t = tabs[i];
            var isActive = i === 0 ? ' border-brand-600 text-brand-600' : ' border-transparent text-gray-500 hover:text-gray-700';
            html += '<button class="security-tab whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition' + isActive + '" data-tab="' + t.key + '">';
            html += '<i class="bi ' + t.icon + ' mr-1.5"></i>' + t.label;
            html += '</button>';
        }
        html += '</nav></div>';

        // Content area
        html += '<div id="securityContent" class="min-h-[400px]"><div class="text-center py-12 text-gray-400"><i class="bi bi-arrow-repeat animate-spin text-2xl"></i></div></div>';

        html += '</div>';
        container.innerHTML = html;

        // Bind tab clicks
        container.querySelectorAll('.security-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                container.querySelectorAll('.security-tab').forEach(function (t) {
                    t.classList.remove('border-brand-600', 'text-brand-600');
                    t.classList.add('border-transparent', 'text-gray-500');
                });
                this.classList.remove('border-transparent', 'text-gray-500');
                this.classList.add('border-brand-600', 'text-brand-600');
                loadTabContent(this.dataset.tab);
            });
        });

        // Refresh button
        var refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                var activeTab = container.querySelector('.security-tab.border-brand-600');
                if (activeTab) loadTabContent(activeTab.dataset.tab);
            });
        }

        // Load first tab
        loadTabContent(tabs[0].key);
    }

    function loadTabContent(tab) {
        var content = document.getElementById('securityContent');
        if (!content) return;

        content.innerHTML = '<div class="text-center py-12 text-gray-400"><i class="bi bi-arrow-repeat animate-spin text-2xl"></i></div>';

        switch (tab) {
            case 'overview':
                loadOverview(content);
                break;
            case 'events':
                loadEventsTab(content);
                break;
            case 'anomalies':
                loadAnomaliesTab(content);
                break;
            case 'thresholds':
                loadThresholdsTab(content);
                break;
            case 'notifications':
                loadNotificationsTab(content);
                break;
            case 'siem':
                loadSiemTab(content);
                break;
        }
    }

    // --- Overview Tab ---
    function loadOverview(content) {
        loadStats(function (data) {
            if (!data || !data.success) {
                content.innerHTML = '<p class="text-sm text-gray-400 p-6">Failed to load statistics.</p>';
                return;
            }
            renderOverviewTab(content, data.data);
        });
    }

    function renderOverviewTab(content, data) {
        var stats = data;
        var total = stats.total || 0;
        var bySeverity = stats.by_severity || { high: 0, medium: 0, low: 0 };
        var byType = stats.by_type || [];
        var topIps = stats.top_ips || [];
        var timeline = stats.timeline || [];

        var html = '<div class="space-y-6">';

        // Summary cards
        html += '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">';
        html += statCard('Total Events (24h)', total, 'bi-activity', 'brand');
        html += statCard('High Severity', bySeverity.high || 0, 'bi-exclamation-octagon', 'danger');
        html += statCard('Medium Severity', bySeverity.medium || 0, 'bi-exclamation-triangle', 'warning');
        html += statCard('Low Severity', bySeverity.low || 0, 'bi-info-circle', 'success');
        html += '</div>';

        // Charts row
        html += '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">';
        
        // Timeline chart
        html += '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">';
        html += '<h3 class="text-sm font-semibold text-gray-800 mb-3"><i class="bi bi-graph-up mr-1.5 text-brand-600"></i>Event Timeline (24h)</h3>';
        html += '<div class="h-64"><canvas id="timelineChart"></canvas></div>';
        html += '</div>';

        // Event types breakdown
        html += '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">';
        html += '<h3 class="text-sm font-semibold text-gray-800 mb-3"><i class="bi bi-pie-chart mr-1.5 text-brand-600"></i>Events by Type</h3>';
        html += '<div class="space-y-3" id="eventTypesList">';
        if (byType.length === 0) {
            html += '<p class="text-sm text-gray-400 text-center py-4">No security events in the last 24 hours.</p>';
        } else {
            for (var i = 0; i < byType.length; i++) {
                var bt = byType[i];
                var severity = SEVERITY_COLORS[inferSeverity(bt.action)] || 'gray';
                var label = bt.action.replace('security.', '').replace(/_/g, ' ').replace(/\b\w/g, function(l){return l.toUpperCase();});
                html += '<div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">';
                html += '<div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-' + severity + '-500"></span><span class="text-sm font-medium text-gray-700">' + label + '</span></div>';
                html += '<span class="text-sm font-semibold text-gray-800">' + bt.cnt + '</span>';
                html += '</div>';
            }
        }
        html += '</div>';
        html += '</div>';

        html += '</div>';

        // Top IPs
        html += '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">';
        html += '<h3 class="text-sm font-semibold text-gray-800 mb-3"><i class="bi bi-geo-alt mr-1.5 text-brand-600"></i>Top Source IPs</h3>';
        if (topIps.length === 0) {
            html += '<p class="text-sm text-gray-400">No data available.</p>';
        } else {
            html += '<div class="space-y-2">';
            for (var j = 0; j < Math.min(topIps.length, 10); j++) {
                var ip = topIps[j].ip ? topIps[j].ip.replace(/"/g, '') : 'unknown';
                html += '<div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg">';
                html += '<span class="font-mono text-sm text-gray-700">' + escHtml(ip) + '</span>';
                html += '<span class="px-2 py-0.5 text-xs font-medium bg-brand-100 text-brand-700 rounded">' + topIps[j].cnt + ' events</span>';
                html += '</div>';
            }
            html += '</div>';
        }
        html += '</div>';

        html += '</div>';
        content.innerHTML = html;

        // Render timeline chart
        renderTimelineChart(timeline);
    }

    function statCard(label, value, icon, color) {
        var colors = {
            brand: 'bg-brand-50 text-brand-700 border-brand-200',
            danger: 'bg-danger-50 text-danger-700 border-danger-200',
            warning: 'bg-warning-50 text-warning-700 border-warning-200',
            success: 'bg-success-50 text-success-700 border-success-200'
        };
        return '<div class="bg-white rounded-xl border ' + colors[color] + ' shadow-sm p-5">' +
            '<div class="flex items-start justify-between">' +
            '<div><p class="text-xs font-medium text-gray-500 uppercase tracking-wide">' + label + '</p>' +
            '<p class="text-3xl font-bold ' + colors[color].replace('bg-', 'text-').replace('border-', '') + ' mt-1">' + value + '</p></div>' +
            '<div class="w-10 h-10 ' + colors[color] + ' rounded-lg flex items-center justify-center"><i class="bi ' + icon + ' text-xl"></i></div>' +
            '</div></div>';
    }

    function inferSeverity(action) {
        if (action.includes('auth_failed') || action.includes('csrf_failed') || action.includes('suspicious') || action.includes('alert_fired')) return 'high';
        if (action.includes('authz_denied')) return 'medium';
        return 'low';
    }

    function renderTimelineChart(timelineData) {
        var ctx = document.getElementById('timelineChart');
        if (!ctx) return;

        // Prepare data for last 24 hours
        var labels = [];
        var data = [];
        var now = new Date();
        for (var h = 23; h >= 0; h--) {
            var d = new Date(now.getTime() - h * 3600000);
            var label = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            labels.push(label);
            
            // Find matching data
            var hourKey = d.toISOString().slice(0, 13) + ':00';
            var found = timelineData.find(function(t) { return t.hour === hourKey; });
            data.push(found ? parseInt(t.cnt) : 0);
        }

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Security Events',
                    data: data,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 12, font: { size: 10 } } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { stepSize: 1, font: { size: 10 } } }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }

    // --- Events Tab ---
    function loadEventsTab(content) {
        var html = '<div class="space-y-4">';
        
        // Filters
        html += '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">';
        html += '<h3 class="text-sm font-semibold text-gray-800 mb-3">Filters</h3>';
        html += '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">';
        
        html += '<div><label class="block text-xs font-medium text-gray-500 mb-1">Event Type</label>';
        html += '<select id="filterEventType" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500">';
        html += '<option value="">All Types</option>';
        for (var i = 0; i < EVENT_TYPES.length; i++) {
            html += '<option value="' + EVENT_TYPES[i].key + '">' + EVENT_TYPES[i].label + '</option>';
        }
        html += '</select></div>';

        html += '<div><label class="block text-xs font-medium text-gray-500 mb-1">Severity</label>';
        html += '<select id="filterSeverity" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500">';
        html += '<option value="">All Severities</option>';
        html += '<option value="high">High</option>';
        html += '<option value="medium">Medium</option>';
        html += '<option value="low">Low</option>';
        html += '</select></div>';

        html += '<div><label class="block text-xs font-medium text-gray-500 mb-1">Source IP</label>';
        html += '<input type="text" id="filterIp" placeholder="e.g. 192.168.1.1" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500"></div>';

        html += '<div><label class="block text-xs font-medium text-gray-500 mb-1">Date From</label>';
        html += '<input type="date" id="filterDateFrom" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500"></div>';

        html += '<div><label class="block text-xs font-medium text-gray-500 mb-1">Date To</label>';
        html += '<input type="date" id="filterDateTo" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500"></div>';

        html += '</div>';
        html += '<div class="flex gap-2 pt-2">';
        html += '<button id="applyFiltersBtn" class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition"><i class="bi bi-funnel mr-1"></i>Apply Filters</button>';
        html += '<button id="clearFiltersBtn" class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition"><i class="bi bi-x-circle mr-1"></i>Clear</button>';
        html += '</div>';
        html += '</div>';

        // Events table
        html += '<div class="bg-white rounded-xl border border-gray-200 shadow-sm">';
        html += '<div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">';
        html += '<h3 class="text-sm font-semibold text-gray-800">Security Events</h3>';
        html += '<span id="eventsCount" class="text-xs text-gray-500"></span>';
        html += '</div>';
        html += '<div class="overflow-x-auto">';
        html += '<table class="w-full" id="eventsTable">';
        html += '<thead class="bg-gray-50"><tr>';
        html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Time</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Severity</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Source IP</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Endpoint</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>';
        html += '</tr></thead>';
        html += '<tbody id="eventsBody" class="divide-y divide-gray-100"></tbody>';
        html += '</table>';
        html += '</div>';
        
        // Pagination
        html += '<div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between" id="eventsPagination">';
        html += '<span id="paginationInfo" class="text-sm text-gray-500"></span>';
        html += '<div class="flex gap-1" id="paginationControls"></div>';
        html += '</div>';
        html += '</div>';

        html += '</div>';
        content.innerHTML = html;

        // Bind events
        document.getElementById('applyFiltersBtn').addEventListener('click', function () { loadEvents(1); });
        document.getElementById('clearFiltersBtn').addEventListener('click', function () {
            document.getElementById('filterEventType').value = '';
            document.getElementById('filterSeverity').value = '';
            document.getElementById('filterIp').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            loadEvents(1);
        });

        // Load first page
        loadEvents(1);
    }

    function loadEvents(page) {
        var filters = {
            event_type: document.getElementById('filterEventType').value || null,
            severity: document.getElementById('filterSeverity').value || null,
            ip: document.getElementById('filterIp').value || null,
            date_from: document.getElementById('filterDateFrom').value || null,
            date_to: document.getElementById('filterDateTo').value || null
        };

        fetch(API + '?action=events&page=' + page + '&per_page=25' + buildQueryString(filters))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                renderEventsTable(data.data);
            })
            .catch(function () {
                document.getElementById('eventsBody').innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Failed to load events.</td></tr>';
            });
    }

    function buildQueryString(params) {
        var parts = [];
        for (var key in params) {
            if (params[key] !== null && params[key] !== '') {
                parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));
            }
        }
        return parts.length ? '&' + parts.join('&') : '';
    }

    function renderEventsTable(data) {
        var body = document.getElementById('eventsBody');
        var pagination = data.pagination || {};
        var events = data.events || [];

        if (events.length === 0) {
            body.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No security events found.</td></tr>';
        } else {
            var html = '';
            for (var i = 0; i < events.length; i++) {
                var e = events[i];
                var severity = inferSeverityFromMeta(e);
                var ip = e.ip ? e.ip.replace(/"/g, '') : 'unknown';
                var endpoint = e.endpoint ? e.endpoint.replace(/"/g, '') : '—';
                var method = e.method ? e.method.replace(/"/g, '') : '—';
                var requestId = e.request_id ? e.request_id.replace(/"/g, '') : null;
                var user = e.user_name ? escHtml(e.user_name) : (e.user_id ? 'User #' + e.user_id : '—');
                
                html += '<tr class="hover:bg-gray-50">';
                html += '<td class="px-4 py-3 text-sm text-gray-600">' + escHtml(e.created_at) + '</td>';
                html += '<td class="px-4 py-3"><span class="px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 rounded">' + escHtml(e.action.replace('security.', '').replace(/_/g, ' ')) + '</span></td>';
                html += '<td class="px-4 py-3"><span class="px-2 py-0.5 text-xs font-medium bg-' + SEVERITY_COLORS[severity] + '-100 text-' + SEVERITY_COLORS[severity] + '-700 rounded">' + severity.toUpperCase() + '</span></td>';
                html += '<td class="px-4 py-3"><code class="text-sm text-gray-700 bg-gray-50 px-2 py-0.5 rounded">' + escHtml(ip) + '</code></td>';
                html += '<td class="px-4 py-3 text-sm text-gray-700">' + user + '</td>';
                html += '<td class="px-4 py-3"><code class="text-sm text-gray-600">' + escHtml(method) + ' ' + escHtml(endpoint.length > 50 ? endpoint.slice(0, 50) + '…' : endpoint) + '</code></td>';
                html += '<td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate block">' + escHtml(e.description || '—') + '</td>';
                html += '<td class="px-4 py-3">';
                html += '<button class="event-detail-btn px-2 py-1 text-xs text-brand-600 hover:text-brand-700 underline" data-id="' + e.id + '" title="View details"><i class="bi bi-eye mr-0.5"></i>Details</button>';
                if (requestId) {
                    html += ' <button class="copy-request-id px-2 py-1 text-xs text-gray-500 hover:text-gray-700" data-request-id="' + escHtml(requestId) + '" title="Copy Request ID"><i class="bi bi-clipboard"></i></button>';
                }
                html += '</td>';
                html += '</tr>';
            }
            body.innerHTML = html;

            // Bind detail buttons
            body.querySelectorAll('.event-detail-btn').forEach(function (btn) {
                btn.addEventListener('click', function () { showEventDetail(this.dataset.id); });
            });
            body.querySelectorAll('.copy-request-id').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    navigator.clipboard.writeText(this.dataset.requestId);
                    this.textContent = '✓ Copied';
                    setTimeout(function () { btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1500);
                });
            });
        }

        // Update pagination
        var info = document.getElementById('paginationInfo');
        var controls = document.getElementById('paginationControls');
        if (info) {
            var total = pagination.total || 0;
            var page = pagination.page || 1;
            var perPage = pagination.per_page || 25;
            var start = total === 0 ? 0 : (page - 1) * perPage + 1;
            var end = Math.min(page * perPage, total);
            info.textContent = 'Showing ' + start + '–' + end + ' of ' + total;
        }
        if (controls) {
            var totalPages = pagination.total_pages || 1;
            var currentPage = pagination.page || 1;
            var html = '';
            if (currentPage > 1) {
                html += '<button class="pagination-btn px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50" data-page="' + (currentPage - 1) + '">Previous</button>';
            }
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, currentPage + 2);
            for (var p = startPage; p <= endPage; p++) {
                var active = p === currentPage ? 'bg-brand-600 text-white border-brand-600' : 'border-gray-300 hover:bg-gray-50';
                html += '<button class="pagination-btn px-3 py-1 text-sm border rounded ' + active + '" data-page="' + p + '">' + p + '</button>';
            }
            if (currentPage < totalPages) {
                html += '<button class="pagination-btn px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50" data-page="' + (currentPage + 1) + '">Next</button>';
            }
            controls.innerHTML = html;

            controls.querySelectorAll('.pagination-btn').forEach(function (btn) {
                btn.addEventListener('click', function () { loadEvents(parseInt(this.dataset.page)); });
            });
        }

        document.getElementById('eventsCount').textContent = (pagination.total || 0) + ' total';
    }

    function inferSeverityFromMeta(event) {
        var action = event.action || '';
        if (action.includes('auth_failed') || action.includes('csrf_failed') || action.includes('suspicious') || action.includes('alert_fired')) return 'high';
        if (action.includes('authz_denied')) return 'medium';
        return 'low';
    }

    function showEventDetail(eventId) {
        fetch(API + '?action=event_detail&id=' + eventId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.event) {
                    alert('Failed to load event details');
                    return;
                }
                showEventDetailModal(data.event);
            });
    }

    function showEventDetailModal(event) {
        var meta = event.metadata_parsed || {};
        var html = '<div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" id="eventDetailModal">';
        html += '<div class="bg-white rounded-xl shadow-xl max-w-3xl w-full mx-4 max-h-[80vh] overflow-y-auto">';
        html += '<div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">';
        html += '<h3 class="text-lg font-semibold text-gray-800">Event Details</h3>';
        html += '<button class="text-gray-400 hover:text-gray-600" onclick="closeEventDetailModal()"><i class="bi bi-x-lg text-xl"></i></button>';
        html += '</div>';
        html += '<div class="p-6 space-y-4">';

        // Basic info
        html += '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">';
        html += detailRow('Event ID', event.id);
        html += detailRow('Type', escHtml(event.action.replace('security.', '').replace(/_/g, ' ')));
        html += detailRow('Severity', '<span class="px-2 py-0.5 text-xs font-medium bg-' + SEVERITY_COLORS[inferSeverityFromMeta(event)] + '-100 text-' + SEVERITY_COLORS[inferSeverityFromMeta(event)] + '-700 rounded">' + inferSeverityFromMeta(event).toUpperCase() + '</span>');
        html += detailRow('Timestamp', escHtml(event.created_at));
        html += detailRow('Source IP', '<code>' + escHtml((meta.ip || 'unknown').replace(/"/g, '')) + '</code>');
        html += detailRow('User', escHtml(event.user_name || (event.user_id ? 'User #' + event.user_id : '—')));
        html += detailRow('Endpoint', '<code>' + escHtml((meta.endpoint || 'unknown').replace(/"/g, '')) + '</code>');
        html += detailRow('Method', '<code>' + escHtml((meta.method || 'unknown').replace(/"/g, '')) + '</code>');
        html += detailRow('Request ID', escHtml(meta.request_id || '—'));
        html += '</div>';

        // Description
        html += '<div>';
        html += '<label class="block text-xs font-medium text-gray-500 mb-1">Description</label>';
        html += '<p class="text-sm text-gray-700">' + escHtml(event.description || '—') + '</p>';
        html += '</div>';

        // Metadata
        html += '<div>';
        html += '<label class="block text-xs font-medium text-gray-500 mb-1">Full Metadata</label>';
        html += '<pre class="bg-gray-50 p-3 rounded text-xs overflow-auto max-h-64"><code>' + escHtml(JSON.stringify(meta, null, 2)) + '</code></pre>';
        html += '</div>';

        html += '</div></div></div>';
        document.body.insertAdjacentHTML('beforeend', html);
    }

    function detailRow(label, value) {
        return '<div><label class="block text-xs font-medium text-gray-500 mb-0.5">' + label + '</label>' +
            '<div class="text-sm text-gray-700">' + value + '</div></div>';
    }

    window.closeEventDetailModal = function () {
        var modal = document.getElementById('eventDetailModal');
        if (modal) modal.remove();
    };

    // --- Anomalies Tab ---
    function loadAnomaliesTab(content) {
        var html = '<div class="space-y-4">';
        html += '<div class="flex items-center justify-between">';
        html += '<h3 class="text-sm font-semibold text-gray-800">Anomaly Detection</h3>';
        html += '<div class="flex gap-2">';
        html += '<label class="text-sm text-gray-500">Lookback: </label>';
        html += '<select id="anomalyLookback" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500">';
        html += '<option value="300">5 minutes</option>';
        html += '<option value="600">10 minutes</option>';
        html += '<option value="900">15 minutes</option>';
        html += '<option value="1800">30 minutes</option>';
        html += '<option value="3600">1 hour</option>';
        html += '</select>';
        html += '<button id="refreshAnomaliesBtn" class="px-3 py-1.5 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"><i class="bi bi-arrow-clockwise mr-1"></i>Check Now</button>';
        html += '</div></div>';

        html += '<div id="anomaliesContainer" class="bg-white rounded-xl border border-gray-200 shadow-sm">';
        html += '<div class="p-5 text-center text-gray-400"><i class="bi bi-arrow-repeat animate-spin text-2xl"></i></div>';
        html += '</div>';
        html += '</div>';
        content.innerHTML = html;

        document.getElementById('refreshAnomaliesBtn').addEventListener('click', loadAnomalies);
        document.getElementById('anomalyLookback').addEventListener('change', loadAnomalies);

        loadAnomalies();
    }

    function loadAnomalies() {
        var lookback = parseInt(document.getElementById('anomalyLookback').value) || 300;
        var container = document.getElementById('anomaliesContainer');
        
        container.innerHTML = '<div class="p-5 text-center text-gray-400"><i class="bi bi-arrow-repeat animate-spin text-2xl"></i></div>';

        fetch(API + '?action=anomalies&lookback_seconds=' + lookback)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    container.innerHTML = '<div class="p-5 text-center text-red-400">Failed to load anomalies.</div>';
                    return;
                }
                renderAnomalies(container, data.anomalies || []);
            });
    }

    function renderAnomalies(container, anomalies) {
        if (anomalies.length === 0) {
            container.innerHTML = '<div class="p-8 text-center"><i class="bi bi-check-circle text-success-500 text-3xl"></i><p class="mt-2 text-gray-500">No anomalies detected in the selected time window.</p></div>';
            return;
        }

        var html = '<div class="divide-y divide-gray-100">';
        for (var i = 0; i < anomalies.length; i++) {
            var a = anomalies[i];
            var sev = a.severity || 'medium';
            var sevColor = SEVERITY_COLORS[sev] || 'warning';
            var typeLabel = a.type === 'anomaly_detected' ? 'Threshold Violation' : 'Request Frequency Anomaly';
            
            html += '<div class="p-5 border-l-4 border-' + sevColor + '-500 bg-' + sevColor + '-50">';
            html += '<div class="flex items-start justify-between gap-4">';
            html += '<div class="flex-1">';
            html += '<div class="flex items-center gap-2 mb-1">';
            html += '<span class="px-2 py-0.5 text-xs font-medium bg-' + sevColor + '-100 text-' + sevColor + '-700 rounded">' + sev.toUpperCase() + '</span>';
            html += '<span class="px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 rounded">' + typeLabel + '</span>';
            if (a.event_type) {
                html += '<span class="px-2 py-0.5 text-xs text-gray-600 bg-gray-100 rounded">' + a.event_type.replace(/_/g, ' ') + '</span>';
            }
            html += '</div>';
            html += '<p class="text-sm text-gray-700 mb-2">' + escHtml(a.description || '') + '</p>';
            
            var details = [];
            if (a.ip) details.push('<strong>IP:</strong> ' + escHtml(a.ip));
            if (a.count) details.push('<strong>Count:</strong> ' + a.count + ' (threshold: ' + a.threshold + ')');
            if (a.window_seconds) details.push('<strong>Window:</strong> ' + a.window_seconds + 's');
            html += '<p class="text-xs text-gray-500">' + details.join(' · ') + '</p>';
            html += '</div>';
            
            if (a.sample_events && a.sample_events.length) {
                html += '<details class="mt-2"><summary class="text-xs text-gray-500 cursor-pointer">Sample Events</summary>';
                html += '<pre class="bg-gray-100 p-2 mt-1 rounded text-[10px] overflow-auto max-h-32"><code>' + escHtml(JSON.stringify(a.sample_events, null, 2)) + '</code></pre>';
                html += '</details>';
            }
            html += '</div>';
            html += '</div>';
        }
        html += '</div>';
        container.innerHTML = html;
    }

    // --- Thresholds Tab ---
    function loadThresholdsTab(content) {
        loadThresholds(function (thresholds) {
            if (!thresholds) {
                content.innerHTML = '<p class="text-sm text-gray-400 p-6">Failed to load thresholds.</p>';
                return;
            }
            renderThresholdsTab(content, thresholds);
        });
    }

    function renderThresholdsTab(content, thresholds) {
        var html = '<div class="space-y-4">';
        
        html += '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">';
        html += '<h3 class="text-sm font-semibold text-gray-800 mb-4">Alert Thresholds</h3>';
        html += '<p class="text-xs text-gray-500 mb-4">Configure when alerts should fire for each security event type. Changes take effect immediately.</p>';

        for (var key in thresholds) {
            var t = thresholds[key];
            var label = key.replace(/_/g, ' ').replace(/\b\w/g, function(l){return l.toUpperCase();});
            var sevColor = SEVERITY_COLORS[t.severity] || 'gray';
            
            html += '<div class="border border-gray-200 rounded-lg p-4 mb-4" data-event-type="' + key + '">';
            html += '<div class="flex items-center justify-between mb-3">';
            html += '<div>';
            html += '<h4 class="font-medium text-gray-800">' + label + '</h4>';
            html += '<p class="text-xs text-gray-500 mt-0.5">Alert when more than <strong>' + t.max_events + '</strong> events occur within <strong>' + t.window_seconds + 's</strong></p>';
            html += '</div>';
            html += '<span class="px-2 py-0.5 text-xs font-medium bg-' + sevColor + '-100 text-' + sevColor + '-700 rounded">' + t.severity.toUpperCase() + ' SEVERITY</span>';
            html += '</div>';
            
            html += '<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">';
            html += '<div><label class="block text-xs font-medium text-gray-500 mb-1">Max Events</label>';
            html += '<input type="number" class="threshold-input w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500" data-field="max_events" value="' + t.max_events + '" min="1"></div>';
            html += '<div><label class="block text-xs font-medium text-gray-500 mb-1">Window (seconds)</label>';
            html += '<input type="number" class="threshold-input w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500" data-field="window_seconds" value="' + t.window_seconds + '" min="60"></div>';
            html += '<div><label class="block text-xs font-medium text-gray-500 mb-1">Cooldown (seconds)</label>';
            html += '<input type="number" class="threshold-input w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500" data-field="cooldown_seconds" value="' + t.cooldown_seconds + '" min="60"></div>';
            html += '</div>';
            
            html += '<div class="flex gap-2">';
            html += '<select class="threshold-input px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500" data-field="severity">';
            html += '<option value="high" ' + (t.severity === 'high' ? 'selected' : '') + '>High</option>';
            html += '<option value="medium" ' + (t.severity === 'medium' ? 'selected' : '') + '>Medium</option>';
            html += '<option value="low" ' + (t.severity === 'low' ? 'selected' : '') + '>Low</option>';
            html += '</select>';
            html += '<button class="save-threshold-btn px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition" data-event-type="' + key + '">Save</button>';
            html += '</div>';
            html += '</div>';
        }
        html += '</div>';
        content.innerHTML = html;

        // Bind save buttons
        content.querySelectorAll('.save-threshold-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var card = this.closest('[data-event-type]');
                var eventType = card.dataset.eventType;
                var inputs = card.querySelectorAll('.threshold-input');
                var config = {};
                inputs.forEach(function (input) {
                    config[input.dataset.field] = input.type === 'number' ? parseInt(input.value) : input.value;
                });
                saveThresholdConfig(eventType, config, this);
            });
        });
    }

    function saveThresholdConfig(eventType, config, btn) {
        var originalText = btn.textContent;
        btn.textContent = 'Saving...';
        btn.disabled = true;

        fetch(API, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({ action: 'thresholds', event_type: eventType, values: config })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.textContent = originalText;
            btn.disabled = false;
            if (res.success) {
                btn.textContent = '✓ Saved';
                setTimeout(function () { btn.textContent = originalText; }, 2000);
            } else {
                var err = res.errors ? Object.values(res.errors).join(', ') : 'Save failed';
                alert(err);
                btn.textContent = originalText;
            }
        });
    }

    // --- Notifications Tab ---
    function loadNotificationsTab(content) {
        loadNotificationSettings(function (settings) {
            renderNotificationsTab(content, settings || {});
        });
    }

    function renderNotificationsTab(content, settings) {
        var email = settings.email || '';
        var webhook = settings.webhook || '';

        var html = '<div class="space-y-6">';

        // Email notifications
        html += '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">';
        html += '<h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2"><i class="bi bi-envelope text-brand-600"></i>Email Notifications</h3>';
        html += '<p class="text-xs text-gray-500 mb-4">Comma-separated email addresses to receive security alerts.</p>';
        html += '<div class="mb-4">';
        html += '<label class="block text-xs font-medium text-gray-500 mb-1">Recipients</label>';
        html += '<textarea id="emailRecipients" rows="3" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500" placeholder="security@example.com, admin@example.com">' + escHtml(email) + '</textarea>';
        html += '<p class="text-xs text-gray-400 mt-1">Leave empty to disable email notifications.</p>';
        html += '</div>';
        html += '<div class="flex gap-2">';
        html += '<button id="saveEmailBtn" class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition">Save Email Settings</button>';
        html += '<button id="testEmailBtn" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition"><i class="bi bi-send mr-1"></i>Send Test Email</button>';
        html += '</div>';
        html += '</div>';

        // Webhook notifications
        html += '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">';
        html += '<h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2"><i class="bi bi-webhook text-brand-600"></i>Webhook Notifications</h3>';
        html += '<p class="text-xs text-gray-500 mb-4">Comma-separated HTTPS webhook URLs to receive security alerts (POST with JSON payload). Internal IPs and localhost are blocked.</p>';
        html += '<div class="mb-4">';
        html += '<label class="block text-xs font-medium text-gray-500 mb-1">Webhook URLs</label>';
        html += '<textarea id="webhookUrls" rows="3" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500" placeholder="https://hooks.slack.com/..., https://my-siem.example.com/webhook">' + escHtml(webhook) + '</textarea>';
        html += '<p class="text-xs text-gray-400 mt-1">Must use HTTPS. Private IPs (10.x, 172.16-31.x, 192.168.x, localhost) are blocked for security.</p>';
        html += '</div>';
        html += '<div class="flex gap-2">';
        html += '<button id="saveWebhookBtn" class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition">Save Webhook Settings</button>';
        html += '<button id="testWebhookBtn" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition"><i class="bi bi-send mr-1"></i>Send Test Webhook</button>';
        html += '</div>';
        html += '</div>';

        html += '</div>';
        content.innerHTML = html;

        document.getElementById('saveEmailBtn').addEventListener('click', function () {
            saveNotificationSettings('email', document.getElementById('emailRecipients').value, this);
        });
        document.getElementById('saveWebhookBtn').addEventListener('click', function () {
            saveNotificationSettings('webhook', document.getElementById('webhookUrls').value, this);
        });
        document.getElementById('testEmailBtn').addEventListener('click', function () {
            sendTestNotification('email', this);
        });
        document.getElementById('testWebhookBtn').addEventListener('click', function () {
            sendTestNotification('webhook', this);
        });
    }

    function saveNotificationSettings(type, value, btn) {
        var originalText = btn.textContent;
        btn.textContent = 'Saving...';
        btn.disabled = true;

        fetch(API, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({ action: 'notification_settings', [type]: value })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.textContent = originalText;
            btn.disabled = false;
            if (res.success) {
                btn.textContent = '✓ Saved';
                setTimeout(function () { btn.textContent = originalText; }, 2000);
            } else {
                alert(res.error || 'Save failed');
            }
        });
    }

    // --- SIEM Tab ---
    function loadSiemTab(content) {
        loadSiemSettings(function (settings) {
            renderSiemTab(content, settings || {});
        });
    }

    function renderSiemTab(content, settings) {
        var webhook = settings.webhook || '';

        var html = '<div class="space-y-6">';

        html += '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">';
        html += '<h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2"><i class="bi bi-hdd-network text-brand-600"></i>SIEM Integration</h3>';
        html += '<p class="text-xs text-gray-500 mb-4">Forward all security events to an external SIEM system (Splunk, Elastic, Datadog, custom, etc.) via HTTPS webhook.</p>';
        
        html += '<div class="mb-4">';
        html += '<label class="block text-xs font-medium text-gray-500 mb-1">SIEM Webhook URL</label>';
        html += '<input type="url" id="siemWebhook" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500" placeholder="https://siem.example.com/collector" value="' + escHtml(webhook) + '">';
        html += '<p class="text-xs text-gray-400 mt-1">Must use HTTPS. Private IPs and localhost are blocked. Events are sent as structured JSON with sanitized metadata.</p>';
        html += '</div>';

        html += '<div class="mb-4 p-4 bg-gray-50 rounded-lg">';
        html += '<h4 class="text-xs font-semibold text-gray-700 mb-2">Payload Format</h4>';
        html += '<pre class="bg-white p-3 rounded text-[10px] overflow-auto"><code>' + escHtml(JSON.stringify({
            timestamp: '2026-01-15T10:30:00+00:00',
            source: 'audit-withgufran',
            event_type: 'security.auth_failed',
            severity: 'high',
            request_id: 'abc123...',
            user_id: 1,
            ip: '203.0.113.45',
            endpoint: '/api/login',
            method: 'POST',
            description: 'Failed login attempt for user@example.com',
            metadata: { user_agent: 'Mozilla/5.0...', redacted_fields: ['password'] }
        }, null, 2)) + '</code></pre>';
        html += '</div>';

        html += '<div class="flex gap-2">';
        html += '<button id="saveSiemBtn" class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition">Save SIEM Settings</button>';
        html += '<button id="testSiemBtn" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition"><i class="bi bi-send mr-1"></i>Send Test Event</button>';
        html += '</div>';
        html += '</div>';

        html += '</div>';
        content.innerHTML = html;

        document.getElementById('saveSiemBtn').addEventListener('click', function () {
            saveSiemSettings(document.getElementById('siemWebhook').value, this);
        });
        document.getElementById('testSiemBtn').addEventListener('click', function () {
            sendTestNotification('siem', this);
        });
    }

    // --- API Functions ---
    function loadStats(callback) {
        fetch(API + '?action=stats&hours=24')
            .then(function (r) { return r.json(); })
            .then(function (data) { if (callback) callback(data); })
            .catch(function () { if (callback) callback(null); });
    }

    function loadEvents(page, callback) {
        // Used by events tab
    }

    function loadAnomalies(callback) {
        // Used by anomalies tab
    }

    function loadThresholds(callback) {
        fetch(API + '?action=thresholds')
            .then(function (r) { return r.json(); })
            .then(function (data) { if (callback) callback(data.thresholds); })
            .catch(function () { if (callback) callback(null); });
    }

    function saveThreshold(eventType, config, callback) {
        fetch(API, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({ action: 'thresholds', event_type: eventType, values: config })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) { if (callback) callback(data); })
        .catch(function () { if (callback) callback({ success: false }); });
    }

    function loadNotificationSettings(callback) {
        fetch(API + '?action=notification_settings')
            .then(function (r) { return r.json(); })
            .then(function (data) { if (callback) callback(data.settings); })
            .catch(function () { if (callback) callback(null); });
    }

    function saveNotificationSettings(type, value, callback) {
        // Handled inline in renderNotificationsTab
    }

    function loadSiemSettings(callback) {
        fetch(API + '?action=siem_settings')
            .then(function (r) { return r.json(); })
            .then(function (data) { if (callback) callback(data.settings); })
            .catch(function () { if (callback) callback(null); });
    }

    function saveSiemSettings(webhook, callback) {
        fetch(API, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({ action: 'siem_settings', webhook: webhook })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) { if (callback) callback(data); })
        .catch(function () { if (callback) callback({ success: false }); });
    }

    function sendTestNotification(type, btn) {
        var originalText = btn.textContent;
        btn.textContent = 'Sending...';
        btn.disabled = true;

        fetch(API, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
            },
            body: JSON.stringify({ action: 'test_notification', type: type })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.textContent = originalText;
            btn.disabled = false;
            if (res.success) {
                btn.textContent = '✓ Sent';
                setTimeout(function () { btn.textContent = originalText; }, 2000);
            } else {
                alert(res.error || 'Test failed');
                btn.textContent = originalText;
            }
        });
    }

    // --- Utilities ---
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})();