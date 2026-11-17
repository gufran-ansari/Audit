/**
 * Manual Override System — Dashboard Integration
 * 
 * Provides:
 * - Override popup when clicking audit status badges
 * - Override summary cards in project dashboard
 * - Override filtering
 * - History viewing
 * 
 * Auth-protected: only works within the authenticated dashboard.
 */
(function () {
    'use strict';

    const API_URL = 'api/overrides';

    const OVERRIDE_STATUSES = [
        { key: 'ignored', label: 'Ignored', color: '#6b7280', bg: '#f3f4f6', icon: 'bi-eye-slash' },
        { key: 'fixed', label: 'Fixed', color: '#16a34a', bg: '#f0fdf4', icon: 'bi-check-circle' },
        { key: 'accepted', label: 'Accepted', color: '#2563eb', bg: '#eff6ff', icon: 'bi-hand-thumbs-up' },
        { key: 'false_positive', label: 'False Positive', color: '#7c3aed', bg: '#f5f3ff', icon: 'bi-flag' },
        { key: 'needs_review', label: 'Needs Review', color: '#ea580c', bg: '#fff7ed', icon: 'bi-hourglass-split' }
    ];

    // --- State ---
    let currentOverrideMap = {};
    let currentProjectId = null;

    // --- Public API ---
    window.OverrideSystem = {
        init: init,
        loadMap: loadOverrideMap,
        openPopup: openOverridePopup,
        getSummary: loadSummary,
        getMap: function () { return currentOverrideMap; },
        getStatusBadge: getOverrideBadgeHtml
    };

    function init(projectId) {
        currentProjectId = projectId;
        loadOverrideMap(projectId);
        injectPopupHtml();
    }

    // --- Load override map for a project ---
    function loadOverrideMap(projectId) {
        currentProjectId = projectId;
        fetch(API_URL + '?action=map&project_id=' + projectId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    currentOverrideMap = data.map || {};
                    if (data.summary) {
                        renderSummaryCards(data.summary);
                    }
                    // Dispatch event so dashboard table can re-render
                    document.dispatchEvent(new CustomEvent('overridesLoaded', { detail: { map: currentOverrideMap } }));
                }
            })
            .catch(function () { /* silent */ });
    }

    // --- Load summary only ---
    function loadSummary(projectId) {
        fetch(API_URL + '?action=summary&project_id=' + projectId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) renderSummaryCards(data.summary);
            })
            .catch(function () { /* silent */ });
    }

    // --- Render summary cards in dashboard ---
    function renderSummaryCards(summary) {
        var container = document.getElementById('overrideSummaryCards');
        if (!container) return;

        var html = '';
        var items = [
            { label: 'Ignored', count: summary.ignored, color: 'gray', icon: 'bi-eye-slash' },
            { label: 'Fixed', count: summary.fixed, color: 'green', icon: 'bi-check-circle' },
            { label: 'Needs Review', count: summary.needs_review, color: 'orange', icon: 'bi-hourglass-split' },
            { label: 'False Positives', count: summary.false_positive, color: 'purple', icon: 'bi-flag' },
            { label: 'Accepted', count: summary.accepted, color: 'blue', icon: 'bi-hand-thumbs-up' }
        ];

        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            if (it.count > 0) {
                html += '<div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm text-center">';
                html += '<i class="bi ' + it.icon + ' text-' + it.color + '-600 text-lg"></i>';
                html += '<p class="text-xl font-bold text-' + it.color + '-600 mt-1">' + it.count + '</p>';
                html += '<p class="text-xs text-gray-500">' + it.label + '</p>';
                html += '</div>';
            }
        }

        container.innerHTML = html;
        container.style.display = html ? 'grid' : 'none';
    }

    // --- Override Popup ---
    function injectPopupHtml() {
        if (document.getElementById('overridePopup')) return;

        var overlay = document.createElement('div');
        overlay.id = 'overrideOverlay';
        overlay.className = 'fixed inset-0 bg-black/40 z-[9998] hidden';
        overlay.onclick = closePopup;

        var popup = document.createElement('div');
        popup.id = 'overridePopup';
        popup.className = 'fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-2xl border border-gray-200 z-[9999] w-[95vw] max-w-md p-6 hidden';
        popup.innerHTML = '<div id="overridePopupContent"></div>';

        document.body.appendChild(overlay);
        document.body.appendChild(popup);
    }

    function openOverridePopup(pageId, moduleName, originalStatus, currentOverride) {
        var overlay = document.getElementById('overrideOverlay');
        var popup = document.getElementById('overridePopup');
        var content = document.getElementById('overridePopupContent');
        if (!popup || !content) return;

        var moduleLabel = getModuleLabel(moduleName);
        var html = '';

        // Header
        html += '<div class="flex items-center justify-between mb-4">';
        html += '<h3 class="text-base font-bold text-gray-800">Manual Override</h3>';
        html += '<button onclick="OverrideSystem._closePopup()" class="text-gray-400 hover:text-gray-600"><i class="bi bi-x-lg"></i></button>';
        html += '</div>';

        // Info
        html += '<div class="mb-4 p-3 bg-gray-50 rounded-lg text-xs">';
        html += '<p class="text-gray-600"><span class="font-medium">Module:</span> ' + escHtml(moduleLabel) + '</p>';
        html += '<p class="text-gray-600 mt-1"><span class="font-medium">Original Result:</span> <span class="font-semibold ' + statusColorClass(originalStatus) + '">' + (originalStatus || 'N/A') + '</span></p>';
        if (currentOverride) {
            html += '<p class="text-gray-600 mt-1"><span class="font-medium">Current Override:</span> <span class="font-semibold">' + getOverrideLabel(currentOverride.override_status) + '</span></p>';
        }
        html += '</div>';

        // Override status options
        html += '<div class="mb-4">';
        html += '<label class="text-xs font-medium text-gray-700 mb-2 block">Override Status</label>';
        html += '<div class="grid grid-cols-2 gap-2" id="overrideStatusOptions">';
        for (var i = 0; i < OVERRIDE_STATUSES.length; i++) {
            var s = OVERRIDE_STATUSES[i];
            var isActive = currentOverride && currentOverride.override_status === s.key;
            html += '<button class="override-opt flex items-center gap-2 px-3 py-2 rounded-lg border text-xs font-medium transition ' +
                (isActive ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 hover:border-gray-300 text-gray-700') +
                '" data-status="' + s.key + '">';
            html += '<i class="bi ' + s.icon + '"></i>' + s.label;
            html += '</button>';
        }
        html += '</div>';
        html += '</div>';

        // Notes
        html += '<div class="mb-4">';
        html += '<label class="text-xs font-medium text-gray-700 mb-1 block">Notes (optional)</label>';
        html += '<textarea id="overrideNotes" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent resize-none" rows="3" placeholder="Add context for this override...">' + escHtml(currentOverride ? (currentOverride.notes || '') : '') + '</textarea>';
        html += '</div>';

        // Actions
        html += '<div class="flex items-center justify-between gap-2">';
        if (currentOverride) {
            html += '<button id="overrideRemoveBtn" class="px-3 py-2 text-xs font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition">Remove Override</button>';
        } else {
            html += '<div></div>';
        }
        html += '<div class="flex gap-2">';
        html += '<button onclick="OverrideSystem._closePopup()" class="px-4 py-2 text-xs font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>';
        html += '<button id="overrideSaveBtn" class="px-4 py-2 text-xs font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition" disabled>Save</button>';
        html += '</div>';
        html += '</div>';

        // History link
        html += '<div class="mt-3 pt-3 border-t border-gray-100 text-center">';
        html += '<button id="overrideHistoryBtn" class="text-xs text-gray-500 hover:text-brand-600 transition"><i class="bi bi-clock-history mr-1"></i>View History</button>';
        html += '</div>';

        content.innerHTML = html;

        // Bind events
        var selectedStatus = null;
        content.querySelectorAll('.override-opt').forEach(function (btn) {
            btn.addEventListener('click', function () {
                content.querySelectorAll('.override-opt').forEach(function (b) {
                    b.classList.remove('border-brand-500', 'bg-brand-50', 'text-brand-700');
                    b.classList.add('border-gray-200', 'text-gray-700');
                });
                this.classList.remove('border-gray-200', 'text-gray-700');
                this.classList.add('border-brand-500', 'bg-brand-50', 'text-brand-700');
                selectedStatus = this.dataset.status;
                document.getElementById('overrideSaveBtn').disabled = false;
            });
        });

        // Save
        document.getElementById('overrideSaveBtn').addEventListener('click', function () {
            if (!selectedStatus) return;
            saveOverride(pageId, moduleName, originalStatus, selectedStatus);
        });

        // Remove
        var removeBtn = document.getElementById('overrideRemoveBtn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                removeOverride(pageId, moduleName);
            });
        }

        // History
        document.getElementById('overrideHistoryBtn').addEventListener('click', function () {
            loadHistory(pageId, moduleName, content);
        });

        // Show
        overlay.classList.remove('hidden');
        popup.classList.remove('hidden');
    }

    function closePopup() {
        var overlay = document.getElementById('overrideOverlay');
        var popup = document.getElementById('overridePopup');
        if (overlay) overlay.classList.add('hidden');
        if (popup) popup.classList.add('hidden');
    }

    // Expose close for inline onclick
    window.OverrideSystem._closePopup = closePopup;

    // --- API calls ---
    function saveOverride(pageId, moduleName, originalStatus, overrideStatus) {
        var notes = document.getElementById('overrideNotes').value.trim();
        var btn = document.getElementById('overrideSaveBtn');
        btn.textContent = 'Saving...';
        btn.disabled = true;

        fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'set',
                page_id: pageId,
                module_name: moduleName,
                original_status: originalStatus,
                override_status: overrideStatus,
                notes: notes || null
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                // Update local map
                var key = pageId + '_' + moduleName;
                currentOverrideMap[key] = data.override;
                closePopup();
                document.dispatchEvent(new CustomEvent('overrideChanged', { detail: { pageId: pageId, moduleName: moduleName } }));
                // Reload summary
                if (currentProjectId) loadOverrideMap(currentProjectId);
            } else {
                alert(data.error || 'Failed to save override');
                btn.textContent = 'Save';
                btn.disabled = false;
            }
        })
        .catch(function () {
            alert('Network error. Please try again.');
            btn.textContent = 'Save';
            btn.disabled = false;
        });
    }

    function removeOverride(pageId, moduleName) {
        if (!confirm('Remove this override? The page will revert to the original automated status.')) return;

        fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'remove',
                page_id: pageId,
                module_name: moduleName
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var key = pageId + '_' + moduleName;
                delete currentOverrideMap[key];
                closePopup();
                document.dispatchEvent(new CustomEvent('overrideChanged', { detail: { pageId: pageId, moduleName: moduleName } }));
                if (currentProjectId) loadOverrideMap(currentProjectId);
            } else {
                alert(data.error || 'Failed to remove override');
            }
        })
        .catch(function () {
            alert('Network error.');
        });
    }

    function loadHistory(pageId, moduleName, container) {
        fetch(API_URL + '?action=history&page_id=' + pageId + '&module_name=' + encodeURIComponent(moduleName))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.history && data.history.length > 0) {
                    var html = '<div class="mt-4 pt-3 border-t border-gray-200">';
                    html += '<h4 class="text-xs font-bold text-gray-700 mb-2">Override History</h4>';
                    html += '<div class="space-y-2 max-h-40 overflow-y-auto">';
                    for (var i = 0; i < data.history.length; i++) {
                        var h = data.history[i];
                        html += '<div class="text-[11px] p-2 bg-gray-50 rounded-lg">';
                        html += '<div class="flex justify-between">';
                        html += '<span class="font-medium text-gray-700">' + escHtml(h.user_name || 'Unknown') + '</span>';
                        html += '<span class="text-gray-400">' + formatDate(h.created_at) + '</span>';
                        html += '</div>';
                        html += '<p class="text-gray-600 mt-0.5">';
                        if (h.old_status) {
                            html += '<span class="text-gray-400">' + getOverrideLabel(h.old_status) + '</span> → ';
                        }
                        html += '<span class="font-medium">' + getOverrideLabel(h.new_status) + '</span>';
                        html += '</p>';
                        if (h.notes) {
                            html += '<p class="text-gray-500 mt-0.5 italic">"' + escHtml(h.notes) + '"</p>';
                        }
                        html += '</div>';
                    }
                    html += '</div></div>';
                    // Append history below existing content
                    var existingHistory = container.querySelector('.history-section');
                    if (existingHistory) existingHistory.remove();
                    var histDiv = document.createElement('div');
                    histDiv.className = 'history-section';
                    histDiv.innerHTML = html;
                    container.appendChild(histDiv);
                } else {
                    alert('No history found for this override.');
                }
            })
            .catch(function () { alert('Failed to load history.'); });
    }

    // --- Helpers ---
    function getOverrideBadgeHtml(overrideStatus) {
        var s = OVERRIDE_STATUSES.find(function (st) { return st.key === overrideStatus; });
        if (!s) return '';
        return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold" style="color:' + s.color + '; background:' + s.bg + '; border:1px solid ' + s.color + '22;">' +
            '<i class="bi ' + s.icon + '"></i>' + s.label + '</span>';
    }

    function getModuleLabel(moduleName) {
        var labels = {
            'h1_audit': 'H1 Audit',
            'meta_audit': 'Meta Audit',
            'noindex_audit': 'Noindex Audit',
            'faq_schema_audit': 'FAQ Schema Audit',
            'lorem_audit': 'Lorem Ipsum Audit',
            'staging_audit': 'Staging URL Audit',
            'word_occurrence_audit': 'Word Occurrence Audit'
        };
        return labels[moduleName] || moduleName;
    }

    function getOverrideLabel(status) {
        var s = OVERRIDE_STATUSES.find(function (st) { return st.key === status; });
        return s ? s.label : (status || 'None');
    }

    function statusColorClass(status) {
        switch (status) {
            case 'PASS': return 'text-green-600';
            case 'WARNING': return 'text-yellow-600';
            case 'FAIL': return 'text-red-600';
            case 'ERROR': return 'text-red-700';
            default: return 'text-gray-500';
        }
    }

    function escHtml(str) {
        if (!str) return '';
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
            return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
        } catch (e) {
            return dateStr;
        }
    }

})();
