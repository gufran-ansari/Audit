/**
 * Settings Module — Dashboard Integration
 * 
 * Provides:
 * - Settings page rendering (grouped by category)
 * - Admin management CRUD
 * - System info display
 * - Form handling and validation
 * 
 * Super Admin only.
 */
(function () {
    'use strict';

    const API = 'api/settings';

    const CATEGORIES = {
        general: { label: 'General', icon: 'bi-gear', description: 'Application name, timezone, and display preferences' },
        audit: { label: 'Audit Engine', icon: 'bi-cpu', description: 'Batch sizes, timeouts, and module configuration' },
        reports: { label: 'Reports', icon: 'bi-file-earmark-text', description: 'Report visibility, branding, and export options' },
        security: { label: 'Security', icon: 'bi-shield-lock', description: 'Session, login, and password policies' },
        system: { label: 'System', icon: 'bi-hdd-rack', description: 'Version and system-level configuration' }
    };

    // --- Public API ---
    window.SettingsModule = {
        loadSettings: loadSettings,
        loadCategory: loadCategory,
        saveCategory: saveCategory,
        loadAdmins: loadAdmins,
        createAdmin: createAdmin,
        updateAdmin: updateAdmin,
        deactivateAdmin: deactivateAdmin,
        activateAdmin: activateAdmin,
        resetPassword: resetPassword,
        loadSystemInfo: loadSystemInfo,
        renderSettingsPage: renderSettingsPage
    };

    // --- Load all settings ---
    function loadSettings(callback) {
        fetch(API + '?action=list')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && callback) callback(data.settings);
            })
            .catch(function () { if (callback) callback(null); });
    }

    // --- Load a category ---
    function loadCategory(category, callback) {
        fetch(API + '?action=category&category=' + encodeURIComponent(category))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && callback) callback(data.settings);
            })
            .catch(function () { if (callback) callback(null); });
    }

    // --- Save category settings ---
    function saveCategory(category, values, callback) {
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update', category: category, values: values })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) { if (callback) callback(data); })
        .catch(function () { if (callback) callback({ success: false, error: 'Network error' }); });
    }

    // --- Admin Management ---
    function loadAdmins(callback) {
        fetch(API + '?action=admins')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && callback) callback(data.admins);
            })
            .catch(function () { if (callback) callback(null); });
    }

    function createAdmin(data, callback) {
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action: 'admin_create' }, data))
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (callback) callback(res); })
        .catch(function () { if (callback) callback({ success: false }); });
    }

    function updateAdmin(userId, data, callback) {
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action: 'admin_update', user_id: userId }, data))
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (callback) callback(res); })
        .catch(function () { if (callback) callback({ success: false }); });
    }

    function deactivateAdmin(userId, callback) {
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'admin_deactivate', user_id: userId })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (callback) callback(res); })
        .catch(function () { if (callback) callback({ success: false }); });
    }

    function activateAdmin(userId, callback) {
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'admin_activate', user_id: userId })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (callback) callback(res); })
        .catch(function () { if (callback) callback({ success: false }); });
    }

    function resetPassword(userId, password, callback) {
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'admin_reset_password', user_id: userId, password: password })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (callback) callback(res); })
        .catch(function () { if (callback) callback({ success: false }); });
    }

    // --- System Info ---
    function loadSystemInfo(callback) {
        fetch(API + '?action=system_info')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && callback) callback(data.info);
            })
            .catch(function () { if (callback) callback(null); });
    }

    // --- Render Settings Page ---
    function renderSettingsPage(container) {
        if (!container) return;

        var html = '<div class="space-y-6">';

        // Header
        html += '<div class="flex items-center justify-between">';
        html += '<div><h2 class="text-2xl font-bold text-gray-800">Settings</h2>';
        html += '<p class="text-sm text-gray-500 mt-1">Configure your application settings</p></div>';
        html += '</div>';

        // Tab navigation
        html += '<div class="border-b border-gray-200">';
        html += '<nav class="flex gap-1 overflow-x-auto" id="settingsTabs">';
        var cats = Object.keys(CATEGORIES);
        for (var i = 0; i < cats.length; i++) {
            var c = CATEGORIES[cats[i]];
            var isActive = i === 0 ? ' border-brand-600 text-brand-600' : ' border-transparent text-gray-500 hover:text-gray-700';
            html += '<button class="settings-tab whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition' + isActive + '" data-cat="' + cats[i] + '">';
            html += '<i class="bi ' + c.icon + ' mr-1.5"></i>' + c.label;
            html += '</button>';
        }
        // Admin tab
        html += '<button class="settings-tab whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition" data-cat="admins">';
        html += '<i class="bi bi-people mr-1.5"></i>Admins</button>';
        // System info tab
        html += '<button class="settings-tab whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition" data-cat="sysinfo">';
        html += '<i class="bi bi-info-circle mr-1.5"></i>System Info</button>';
        html += '</nav></div>';

        // Content area
        html += '<div id="settingsContent" class="min-h-[300px]"><div class="text-center py-12 text-gray-400"><i class="bi bi-arrow-repeat animate-spin text-2xl"></i></div></div>';

        html += '</div>';
        container.innerHTML = html;

        // Bind tab clicks
        container.querySelectorAll('.settings-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                container.querySelectorAll('.settings-tab').forEach(function (t) {
                    t.classList.remove('border-brand-600', 'text-brand-600');
                    t.classList.add('border-transparent', 'text-gray-500');
                });
                this.classList.remove('border-transparent', 'text-gray-500');
                this.classList.add('border-brand-600', 'text-brand-600');
                loadTabContent(this.dataset.cat);
            });
        });

        // Load first tab
        loadTabContent(cats[0]);
    }

    function loadTabContent(category) {
        var content = document.getElementById('settingsContent');
        if (!content) return;

        content.innerHTML = '<div class="text-center py-12 text-gray-400"><i class="bi bi-arrow-repeat animate-spin text-2xl"></i></div>';

        if (category === 'admins') {
            loadAdmins(function (admins) { renderAdminsTab(content, admins); });
        } else if (category === 'sysinfo') {
            loadSystemInfo(function (info) { renderSystemInfoTab(content, info); });
        } else {
            loadCategory(category, function (settings) { renderCategoryTab(content, category, settings); });
        }
    }

    function renderCategoryTab(container, category, settings) {
        var meta = CATEGORIES[category] || { label: category, description: '' };
        var html = '<div class="bg-white rounded-xl border border-gray-200 shadow-sm">';
        html += '<div class="px-6 py-4 border-b border-gray-200">';
        html += '<h3 class="text-base font-semibold text-gray-800">' + meta.label + ' Settings</h3>';
        html += '<p class="text-xs text-gray-500 mt-0.5">' + meta.description + '</p>';
        html += '</div>';
        html += '<div class="p-6 space-y-5" id="settingsForm_' + category + '">';

        if (!settings || settings.length === 0) {
            html += '<p class="text-sm text-gray-400">No settings available.</p>';
        } else {
            for (var i = 0; i < settings.length; i++) {
                html += renderSettingField(settings[i]);
            }
        }

        html += '</div>';
        html += '<div class="px-6 py-4 border-t border-gray-100 flex justify-end">';
        html += '<button class="save-settings-btn px-5 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition" data-cat="' + category + '">Save Changes</button>';
        html += '</div></div>';

        container.innerHTML = html;

        // Bind save
        container.querySelector('.save-settings-btn').addEventListener('click', function () {
            saveCategoryForm(category, this);
        });
    }

    function renderSettingField(setting) {
        var key = setting.setting_key;
        var value = setting.setting_value || '';
        var type = setting.setting_type || 'text';
        var desc = setting.description || '';
        var label = key.replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); });

        var html = '<div>';
        html += '<label class="block text-sm font-medium text-gray-700 mb-1">' + escHtml(label) + '</label>';
        if (desc) html += '<p class="text-xs text-gray-400 mb-1.5">' + escHtml(desc) + '</p>';

        switch (type) {
            case 'boolean':
                var checked = value === '1' ? ' checked' : '';
                html += '<label class="relative inline-flex items-center cursor-pointer">';
                html += '<input type="checkbox" class="sr-only peer setting-input" data-key="' + key + '"' + checked + '>';
                html += '<div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-brand-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[\'\'] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-600"></div>';
                html += '</label>';
                break;
            case 'number':
                html += '<input type="number" class="setting-input w-full max-w-xs px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent" data-key="' + key + '" value="' + escHtml(value) + '">';
                break;
            case 'select':
                html += '<input type="text" class="setting-input w-full max-w-xs px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent" data-key="' + key + '" value="' + escHtml(value) + '">';
                break;
            default:
                html += '<input type="text" class="setting-input w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent" data-key="' + key + '" value="' + escHtml(value) + '">';
        }

        html += '</div>';
        return html;
    }

    function saveCategoryForm(category, btn) {
        var form = document.getElementById('settingsForm_' + category);
        if (!form) return;

        var values = {};
        form.querySelectorAll('.setting-input').forEach(function (input) {
            var key = input.dataset.key;
            if (input.type === 'checkbox') {
                values[key] = input.checked ? '1' : '0';
            } else {
                values[key] = input.value;
            }
        });

        btn.textContent = 'Saving...';
        btn.disabled = true;

        saveCategory(category, values, function (res) {
            btn.textContent = 'Save Changes';
            btn.disabled = false;
            if (res.success) {
                btn.textContent = '✓ Saved';
                setTimeout(function () { btn.textContent = 'Save Changes'; }, 2000);
            } else {
                var errMsg = res.errors ? Object.values(res.errors).join(', ') : 'Save failed';
                alert(errMsg);
            }
        });
    }

    // --- Admins Tab ---
    function renderAdminsTab(container, admins) {
        var html = '<div class="bg-white rounded-xl border border-gray-200 shadow-sm">';
        html += '<div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">';
        html += '<div><h3 class="text-base font-semibold text-gray-800">Admin Users</h3>';
        html += '<p class="text-xs text-gray-500 mt-0.5">Manage administrator accounts</p></div>';
        html += '<button id="addAdminBtn" class="px-4 py-2 text-xs font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition"><i class="bi bi-plus-lg mr-1"></i>Add Admin</button>';
        html += '</div>';

        if (!admins || admins.length === 0) {
            html += '<div class="p-6 text-center text-gray-400"><p>No admin users found.</p></div>';
        } else {
            html += '<div class="divide-y divide-gray-100">';
            for (var i = 0; i < admins.length; i++) {
                var u = admins[i];
                var statusBadge = u.status === 'active'
                    ? '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-green-100 text-green-700">Active</span>'
                    : '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-gray-100 text-gray-600">Inactive</span>';
                var roleBadge = u.role === 'super_admin'
                    ? '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-purple-100 text-purple-700">Super Admin</span>'
                    : '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-blue-100 text-blue-700">Admin</span>';

                html += '<div class="px-6 py-4 flex items-center justify-between gap-4">';
                html += '<div class="flex items-center gap-3 min-w-0">';
                html += '<div class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="bi bi-person text-gray-500"></i></div>';
                html += '<div class="min-w-0">';
                html += '<p class="text-sm font-medium text-gray-800 truncate">' + escHtml(u.name) + '</p>';
                html += '<p class="text-xs text-gray-500 truncate">' + escHtml(u.email) + '</p>';
                html += '</div>';
                html += '<div class="flex gap-1.5 ml-2">' + roleBadge + statusBadge + '</div>';
                html += '</div>';

                // Actions (not for super_admin editing themselves or other super_admins)
                html += '<div class="flex gap-1 flex-shrink-0">';
                if (u.role !== 'super_admin') {
                    if (u.status === 'active') {
                        html += '<button class="admin-action px-2 py-1 text-xs text-gray-600 hover:text-red-600 rounded border border-gray-200 hover:border-red-200 transition" data-action="deactivate" data-id="' + u.id + '" title="Deactivate"><i class="bi bi-pause-circle"></i></button>';
                    } else {
                        html += '<button class="admin-action px-2 py-1 text-xs text-green-600 hover:text-green-700 rounded border border-gray-200 hover:border-green-200 transition" data-action="activate" data-id="' + u.id + '" title="Activate"><i class="bi bi-play-circle"></i></button>';
                    }
                    html += '<button class="admin-action px-2 py-1 text-xs text-gray-600 hover:text-brand-600 rounded border border-gray-200 hover:border-brand-200 transition" data-action="reset_pw" data-id="' + u.id + '" data-name="' + escHtml(u.name) + '" title="Reset Password"><i class="bi bi-key"></i></button>';
                }
                html += '</div>';
                html += '</div>';
            }
            html += '</div>';
        }

        html += '</div>';
        container.innerHTML = html;

        // Bind actions
        container.querySelectorAll('.admin-action').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = this.dataset.action;
                var id = parseInt(this.dataset.id);
                if (action === 'deactivate') {
                    if (confirm('Deactivate this admin?')) deactivateAdmin(id, function () { loadTabContent('admins'); });
                } else if (action === 'activate') {
                    activateAdmin(id, function () { loadTabContent('admins'); });
                } else if (action === 'reset_pw') {
                    var pw = prompt('Enter new password for ' + (btn.dataset.name || 'user') + ' (min 8 chars):');
                    if (pw && pw.length >= 8) resetPassword(id, pw, function (res) {
                        if (res.success) alert('Password reset successfully');
                        else alert(res.error || 'Failed');
                    });
                    else if (pw) alert('Password must be at least 8 characters');
                }
            });
        });

        // Add admin button
        var addBtn = document.getElementById('addAdminBtn');
        if (addBtn) {
            addBtn.addEventListener('click', function () { showAddAdminForm(container); });
        }
    }

    function showAddAdminForm(container) {
        var html = '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">';
        html += '<h3 class="text-base font-semibold text-gray-800 mb-4">Create New Admin</h3>';
        html += '<div class="space-y-4 max-w-md">';
        html += '<div><label class="block text-sm font-medium text-gray-700 mb-1">Name</label><input type="text" id="newAdminName" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500"></div>';
        html += '<div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label><input type="email" id="newAdminEmail" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500"></div>';
        html += '<div><label class="block text-sm font-medium text-gray-700 mb-1">Password</label><input type="password" id="newAdminPassword" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500" placeholder="Min 8 characters"></div>';
        html += '<div><label class="block text-sm font-medium text-gray-700 mb-1">Role</label>';
        html += '<select id="newAdminRole" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500"><option value="admin">Admin</option></select></div>';
        html += '</div>';
        html += '<div class="flex gap-3 mt-6">';
        html += '<button id="saveNewAdmin" class="px-5 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition">Create Admin</button>';
        html += '<button id="cancelNewAdmin" class="px-5 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>';
        html += '</div></div>';

        container.innerHTML = html;

        document.getElementById('cancelNewAdmin').addEventListener('click', function () { loadTabContent('admins'); });
        document.getElementById('saveNewAdmin').addEventListener('click', function () {
            var data = {
                name: document.getElementById('newAdminName').value,
                email: document.getElementById('newAdminEmail').value,
                password: document.getElementById('newAdminPassword').value,
                role: document.getElementById('newAdminRole').value
            };
            createAdmin(data, function (res) {
                if (res.success) { loadTabContent('admins'); }
                else { alert(res.errors ? Object.values(res.errors).join(', ') : 'Failed'); }
            });
        });
    }

    // --- System Info Tab ---
    function renderSystemInfoTab(container, info) {
        if (!info) {
            container.innerHTML = '<p class="text-sm text-gray-400 p-6">Failed to load system information.</p>';
            return;
        }

        var html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';

        // Server Info
        html += '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">';
        html += '<h3 class="text-sm font-semibold text-gray-800 mb-3"><i class="bi bi-server mr-1.5 text-brand-600"></i>Server</h3>';
        html += infoRow('PHP Version', info.php_version);
        html += infoRow('Database', info.db_version);
        html += infoRow('Server', info.server_software);
        html += infoRow('OS', info.os);
        html += infoRow('Memory Limit', info.memory_limit);
        html += infoRow('Max Upload', info.max_upload_size);
        html += infoRow('Timezone', info.timezone);
        html += '</div>';

        // Application Info
        html += '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">';
        html += '<h3 class="text-sm font-semibold text-gray-800 mb-3"><i class="bi bi-app-indicator mr-1.5 text-brand-600"></i>Application</h3>';
        html += infoRow('Version', info.system_version);
        html += infoRow('Total Projects', info.total_projects);
        html += infoRow('Total URLs', info.total_urls);
        html += infoRow('Total Audits', info.total_audits);
        html += infoRow('Storage Used', info.storage_used);
        html += '</div>';

        html += '</div>';

        // Backup preparation note
        html += '<div class="mt-4 bg-gray-50 rounded-xl border border-gray-200 p-5">';
        html += '<div class="flex items-start gap-3">';
        html += '<i class="bi bi-info-circle text-brand-600 text-lg flex-shrink-0 mt-0.5"></i>';
        html += '<div>';
        html += '<h4 class="text-sm font-medium text-gray-700">Backup & Restore</h4>';
        html += '<p class="text-xs text-gray-500 mt-1">Database backup, restore, and settings import/export will be available in a future update.</p>';
        html += '</div></div></div>';

        container.innerHTML = html;
    }

    function infoRow(label, value) {
        return '<div class="flex justify-between py-1.5 border-b border-gray-50 last:border-0">' +
            '<span class="text-xs text-gray-500">' + label + '</span>' +
            '<span class="text-xs font-medium text-gray-800">' + escHtml(String(value || '—')) + '</span></div>';
    }

    // --- Utilities ---
    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})();
