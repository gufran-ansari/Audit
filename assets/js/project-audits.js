/**
 * Project Audits JavaScript
 * 
 * Handles audit execution and result display
 */

// Audit state
let isAuditRunning = false;

/**
 * Initialize audit controls
 */
function initializeAuditControls() {
    const runAuditsBtn = document.getElementById('runAuditsBtn');
    
    if (runAuditsBtn) {
        runAuditsBtn.addEventListener('click', runSelectedAudits);
    }

    const runSelectedPagesBtn = document.getElementById('runSelectedPagesBtn');
    if (runSelectedPagesBtn) {
        runSelectedPagesBtn.addEventListener('click', runSelectedPages);
    }
    
    // Toggle Word Occurrence keyword input visibility
    const wordCheckbox = document.getElementById('wordAuditCheckbox');
    const wordInputArea = document.getElementById('wordOccurrenceInputArea');
    
    if (wordCheckbox && wordInputArea) {
        wordCheckbox.addEventListener('change', function() {
            if (this.checked) {
                wordInputArea.classList.remove('hidden');
                loadWordSearchHistory();
                document.getElementById('wordKeywordInput')?.focus();
            } else {
                wordInputArea.classList.add('hidden');
            }
        });
    }
}

/**
 * Load and render word occurrence search history as clickable chips
 */
async function loadWordSearchHistory() {
    const container = document.getElementById('wordSearchHistory');
    if (!container || typeof projectId === 'undefined') return;
    
    try {
        const response = await fetch(`/routes/word_occurrence_audit.php?action=keywords&project_id=${projectId}`);
        const data = await response.json();
        
        if (data.success && data.keywords && data.keywords.length > 0) {
            let html = '<span class="text-xs text-gray-500 mr-1">Recent:</span>';
            data.keywords.slice(0, 10).forEach(item => {
                const kw = item.keyword;
                html += `<button type="button" class="word-history-chip text-xs px-2 py-1 bg-gray-100 hover:bg-indigo-100 text-gray-700 rounded-full transition-colors" data-keyword="${escapeHtmlAttr(kw)}">${escapeHtmlAttr(kw)}</button>`;
            });
            container.innerHTML = html;
            
            // Attach click handlers to chips
            container.querySelectorAll('.word-history-chip').forEach(chip => {
                chip.addEventListener('click', function() {
                    const input = document.getElementById('wordKeywordInput');
                    if (input) {
                        input.value = this.getAttribute('data-keyword');
                        input.focus();
                    }
                });
            });
        } else {
            container.innerHTML = '';
        }
    } catch (error) {
        console.error('Error loading word search history:', error);
    }
}

/**
 * Escape HTML for use in attributes
 */
function escapeHtmlAttr(text) {
    if (text === null || typeof text === 'undefined') return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

/**
 * Run selected audits for the entire project
 */
async function runSelectedAudits() {
    if (isAuditRunning) {
        alert('An audit is already running. Please wait for it to complete.');
        return;
    }
    
    const h1Checked = document.getElementById('h1AuditCheckbox')?.checked;
    const metaChecked = document.getElementById('metaAuditCheckbox')?.checked;
    const noindexChecked = document.getElementById('noindexAuditCheckbox')?.checked;
    const faqChecked = document.getElementById('faqAuditCheckbox')?.checked;
    const loremChecked = document.getElementById('loremAuditCheckbox')?.checked;
    const stagingChecked = document.getElementById('stagingAuditCheckbox')?.checked;
    const wordChecked = document.getElementById('wordAuditCheckbox')?.checked;
    
    if (!h1Checked && !metaChecked && !noindexChecked && !faqChecked && !loremChecked && !stagingChecked && !wordChecked) {
        alert('Please select at least one audit module to run.');
        return;
    }
    
    // Word Occurrence requires a keyword
    let wordKeyword = '';
    if (wordChecked) {
        wordKeyword = (document.getElementById('wordKeywordInput')?.value || '').trim();
        if (wordKeyword === '') {
            alert('Please enter at least one keyword for the Word Occurrence audit.');
            document.getElementById('wordKeywordInput')?.focus();
            return;
        }
    }
    
    const selectedModules = [];
    if (h1Checked) selectedModules.push('H1 Audit');
    if (metaChecked) selectedModules.push('Meta Audit');
    if (noindexChecked) selectedModules.push('Noindex Audit');
    if (faqChecked) selectedModules.push('FAQ Schema Audit');
    if (loremChecked) selectedModules.push('Lorem Ipsum Audit');
    if (stagingChecked) selectedModules.push('Staging URL Audit');
    if (wordChecked) selectedModules.push('Word Occurrence Audit');
    
    const confirmMsg = `Run ${selectedModules.join(', ')} for all URLs in this project?\n\nThis may take several minutes depending on the number of URLs.`;
    
    if (!confirm(confirmMsg)) {
        return;
    }
    
    isAuditRunning = true;
    
    // Disable button
    const runBtn = document.getElementById('runAuditsBtn');
    const originalHtml = runBtn.innerHTML;
    runBtn.disabled = true;
    runBtn.innerHTML = '<i class="bi bi-hourglass-split mr-2 animate-spin"></i>Running Audits...';
    
    try {
        // Collect module keys for the orchestrator
        const moduleKeys = [];
        if (h1Checked) moduleKeys.push('h1');
        if (metaChecked) moduleKeys.push('meta');
        if (noindexChecked) moduleKeys.push('noindex');
        if (faqChecked) moduleKeys.push('faq');
        if (loremChecked) moduleKeys.push('lorem');
        if (stagingChecked) moduleKeys.push('staging');
        if (wordChecked) moduleKeys.push('word');

        // Create a background job (returns immediately) via the existing job system.
        // Backend: routes/jobs.php?action=create -> JobService -> queued audit_job.
        const job = await submitBackgroundJob(moduleKeys, wordKeyword);

        showNotification(`Audit queued: ${selectedModules.join(', ')} for all URLs. Processing in the background...`, 'info');

        // Refresh word search history if word module was run
        if (wordChecked && typeof loadWordSearchHistory === 'function') {
            loadWordSearchHistory();
        }

        // Kick off background processing and live progress polling (no page refresh).
        // loadJobStatusPanel + startJobPolling drive live progress and workspace updates.
        loadJobStatusPanel();
        triggerJobProcessing();
        startJobPolling();

    } catch (error) {
        console.error('Audit error:', error);
        showNotification('Could not start audit: ' + error.message, 'error');
    } finally {
        // Background job created — release the button immediately.
        isAuditRunning = false;
        runBtn.disabled = false;
        runBtn.innerHTML = originalHtml;
    }
}

/**
 * Run selected audits for only the checked pages.
 * Reuses module selection + the existing run_pages engine action.
 */
async function runSelectedPages() {
    if (isAuditRunning) {
        alert('An audit is already running. Please wait for it to complete.');
        return;
    }

    const pageIds = (typeof getSelectedPageIds === 'function') ? getSelectedPageIds() : [];
    if (pageIds.length === 0) {
        alert('Select one or more pages using the checkboxes first.');
        return;
    }

    // Reuse the same module selection as the main run button
    const moduleKeys = [];
    if (document.getElementById('h1AuditCheckbox')?.checked) moduleKeys.push('h1');
    if (document.getElementById('metaAuditCheckbox')?.checked) moduleKeys.push('meta');
    if (document.getElementById('noindexAuditCheckbox')?.checked) moduleKeys.push('noindex');
    if (document.getElementById('faqAuditCheckbox')?.checked) moduleKeys.push('faq');
    if (document.getElementById('loremAuditCheckbox')?.checked) moduleKeys.push('lorem');
    if (document.getElementById('stagingAuditCheckbox')?.checked) moduleKeys.push('staging');
    const wordChecked = document.getElementById('wordAuditCheckbox')?.checked;
    if (wordChecked) moduleKeys.push('word');

    if (moduleKeys.length === 0) {
        alert('Please select at least one audit module to run.');
        return;
    }

    let wordKeyword = '';
    if (wordChecked) {
        wordKeyword = (document.getElementById('wordKeywordInput')?.value || '').trim();
        if (wordKeyword === '') {
            alert('Please enter at least one keyword for the Word Occurrence audit.');
            document.getElementById('wordKeywordInput')?.focus();
            return;
        }
    }

    if (!confirm(`Run selected modules for ${pageIds.length} selected page(s)?`)) {
        return;
    }

    isAuditRunning = true;
    const btn = document.getElementById('runSelectedPagesBtn');
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split mr-2 animate-spin"></i>Running...';
    }

    try {
        // Reuse the existing orchestrator run_pages action (synchronous for a bounded selection).
        const formData = new FormData();
        formData.append('action', 'run_pages');
        formData.append('project_id', projectId);
        formData.append('page_ids', pageIds.join(','));
        formData.append('modules', moduleKeys.join(','));
        if (wordChecked && wordKeyword) {
            formData.append('keyword', wordKeyword);
        }

        const response = await fetch('/routes/audit_engine.php', { method: 'POST', body: formData });
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Audit failed');
        }

        showNotification(`Audit completed: ${data.success_pages} ok, ${data.failed_pages} failed of ${data.total_pages} page(s)`, 'success');

        // Refresh workspace to reflect new results
        if (typeof loadPages === 'function') loadPages();
        if (typeof loadPageStats === 'function') loadPageStats();
        if (typeof loadAuditSummary === 'function') loadAuditSummary();
    } catch (error) {
        console.error('Selected pages audit error:', error);
        showNotification('Could not run selected pages: ' + error.message, 'error');
    } finally {
        isAuditRunning = false;
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }
}

/**
 * Run audit for entire project
 */
async function runProjectAudit(type, extraParams = {}) {
    let endpoint;
    
    switch(type) {
        case 'h1':
            endpoint = '/routes/h1_audit.php';
            break;
        case 'meta':
            endpoint = '/routes/meta_audit.php';
            break;
        case 'noindex':
            endpoint = '/routes/noindex_audit.php';
            break;
        case 'faq':
            endpoint = '/routes/faq_schema_audit.php';
            break;
        case 'lorem':
            endpoint = '/routes/lorem_audit.php';
            break;
        case 'staging':
            endpoint = '/routes/staging_audit.php';
            break;
        case 'word':
            endpoint = '/routes/word_occurrence_audit.php';
            break;
        default:
            throw new Error('Invalid audit type: ' + type);
    }
    
    const formData = new FormData();
    formData.append('action', 'audit_project');
    formData.append('project_id', projectId);
    
    // Append any extra parameters (e.g. keyword for word occurrence)
    Object.keys(extraParams).forEach(key => {
        formData.append(key, extraParams[key]);
    });
    
    const response = await fetch(endpoint, {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    
    if (!data.success) {
        throw new Error(data.error || 'Audit failed');
    }
    
    return data;
}

/**
 * Run audit for a single page
 */
async function runPageAudit(pageId, type, extraParams = {}) {
    let endpoint;
    
    switch(type) {
        case 'h1':
            endpoint = '/routes/h1_audit.php';
            break;
        case 'meta':
            endpoint = '/routes/meta_audit.php';
            break;
        case 'noindex':
            endpoint = '/routes/noindex_audit.php';
            break;
        case 'faq':
            endpoint = '/routes/faq_schema_audit.php';
            break;
        case 'lorem':
            endpoint = '/routes/lorem_audit.php';
            break;
        case 'staging':
            endpoint = '/routes/staging_audit.php';
            break;
        case 'word':
            endpoint = '/routes/word_occurrence_audit.php';
            break;
        default:
            throw new Error('Invalid audit type: ' + type);
    }
    
    const formData = new FormData();
    formData.append('action', 'audit_page');
    formData.append('page_id', pageId);
    
    // Append any extra parameters (e.g. keyword for word occurrence)
    Object.keys(extraParams).forEach(key => {
        formData.append(key, extraParams[key]);
    });
    
    const response = await fetch(endpoint, {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    
    if (!data.success) {
        throw new Error(data.error || 'Audit failed');
    }
    
    return data;
}

/**
 * Get audit status badge HTML
 */
function getAuditStatusBadge(status, moduleName = '') {
    if (!status) {
        return '<span class="status-badge badge-secondary text-xs"><i class="bi bi-dash mr-1"></i>Not Audited</span>';
    }
    
    const badges = {
        'PASS': '<span class="status-badge badge-success text-xs"><i class="bi bi-check-circle mr-1"></i>Pass</span>',
        'WARNING': '<span class="status-badge badge-warning text-xs"><i class="bi bi-exclamation-triangle mr-1"></i>Warning</span>',
        'FAIL': '<span class="status-badge badge-danger text-xs"><i class="bi bi-x-circle mr-1"></i>Fail</span>',
        'ERROR': '<span class="status-badge badge-danger text-xs"><i class="bi bi-exclamation-octagon mr-1"></i>Error</span>'
    };
    
    return badges[status] || badges.ERROR;
}

/**
 * Format audit result for tooltip
 */
function formatAuditTooltip(result, type) {
    if (!result || !result.raw_data) {
        return 'Not audited yet';
    }
    
    const data = result.raw_data;
    
    if (type === 'h1') {
        if (data.error) {
            return `Error: ${data.error}`;
        }
        if (data.h1_count === 0) {
            return 'No H1 tag found';
        }
        if (data.h1_count === 1) {
            return `H1: ${data.h1_text}`;
        }
        return `Multiple H1 tags (${data.h1_count})`;
    }
    
    if (type === 'meta') {
        if (data.error) {
            return `Error: ${data.error}`;
        }
        let tooltip = '';
        if (data.title) {
            tooltip += `Title: ${data.title} (${data.title_length} chars)\n`;
        }
        if (data.meta_description) {
            tooltip += `Description: ${data.meta_description.substring(0, 100)}... (${data.description_length} chars)`;
        }
        return tooltip || 'No meta data found';
    }
    
    if (type === 'noindex') {
        if (data.error) {
            return `Error: ${data.error}`;
        }
        if (data.robots_content) {
            return `Robots: ${data.robots_content}\nIndex: ${data.index_status}\nFollow: ${data.follow_status}`;
        }
        return 'No robots meta tag found';
    }
    
    if (type === 'faq') {
        if (data.error) {
            return `Error: ${data.error}`;
        }
        let tooltip = '';
        if (data.schema_found) {
            tooltip += `FAQ Schema found (${data.schema_count || 0} items)\n`;
        }
        if (data.content_found) {
            tooltip += 'FAQ content patterns detected\n';
        }
        if (!data.schema_found && !data.content_found) {
            tooltip = 'No FAQ schema or content found';
        }
        return tooltip.trim();
    }
    
    if (type === 'lorem') {
        if (data.error) {
            return `Error: ${data.error}`;
        }
        if (data.matches_found > 0) {
            return `Found ${data.matches_found} placeholder keyword(s), ${data.total_occurrences} occurrence(s)`;
        }
        return 'No placeholder content found';
    }
    
    if (type === 'staging') {
        if (data.error) {
            return `Error: ${data.error}`;
        }
        if (data.matches_found > 0) {
            return `Found ${data.matches_found} staging URL(s), ${data.total_occurrences} occurrence(s)`;
        }
        return 'No staging URLs found';
    }
    
    return 'Audit data unavailable';
}

/**
 * Load and display audit summary for the project dashboard
 */
async function loadAuditSummary() {
    if (typeof projectId === 'undefined') return;
    
    try {
        const response = await fetch(`/routes/audit_engine.php?action=summary&project_id=${projectId}`);
        const data = await response.json();
        
        if (!data.success || !data.summary) return;
        
        const project = data.summary.project;
        
        // Update the page stats section if it exists
        const statsContainer = document.getElementById('pagesStats');
        if (statsContainer && project) {
            statsContainer.innerHTML = `
                <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                    <div class="text-center">
                        <p class="text-xl font-bold text-gray-800">${project.total_pages}</p>
                        <p class="text-xs text-gray-600">Total Pages</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-green-600">${project.passed}</p>
                        <p class="text-xs text-gray-600">Passed</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-yellow-600">${project.warnings}</p>
                        <p class="text-xs text-gray-600">Warnings</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-red-600">${project.failed}</p>
                        <p class="text-xs text-gray-600">Failed</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-red-400">${project.errors}</p>
                        <p class="text-xs text-gray-600">Errors</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-gray-400">${project.not_audited_pages}</p>
                        <p class="text-xs text-gray-600">Not Audited</p>
                    </div>
                </div>
                ${project.last_audit_date ? `<p class="text-xs text-gray-500 text-center mt-2">Last audit: ${project.last_audit_date}</p>` : ''}
            `;
        }
    } catch (error) {
        console.error('Error loading audit summary:', error);
    }
}

// ─── BACKGROUND JOB SYSTEM ─────────────────────────────────────

let jobPollInterval = null;

/**
 * Submit audit as a background job (returns immediately to UI).
 */
async function submitBackgroundJob(moduleKeys, wordKeyword) {
    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('project_id', projectId);
    formData.append('modules', moduleKeys.join(','));
    if (moduleKeys.includes('word') && wordKeyword) {
        formData.append('keyword', wordKeyword);
    }
    const response = await fetch('/routes/jobs.php', { method: 'POST', body: formData });
    const data = await response.json();
    if (!data.success) throw new Error(data.error || 'Failed to create job');
    return data.job;
}

/**
 * Trigger background processing of the next queued job.
 */
async function triggerJobProcessing() {
    try {
        const response = await fetch('/routes/jobs.php?action=process');
        return await response.json();
    } catch (e) {
        return null;
    }
}

/**
 * Load and render the job status panel.
 */
async function loadJobStatusPanel() {
    if (typeof projectId === 'undefined') return;
    const panel = document.getElementById('jobStatusPanel');
    if (!panel) return;
    
    try {
        const response = await fetch(`/routes/jobs.php?action=list&project_id=${projectId}`);
        const data = await response.json();
        if (!data.success) return;
        
        const jobs = (data.jobs || []).slice(0, 5);
        if (jobs.length === 0) {
            panel.innerHTML = '<p class="text-xs text-gray-400 text-center py-2">No recent jobs</p>';
            return;
        }
        
        let html = '';
        jobs.forEach(job => {
            const badge = getJobStatusBadge(job.status);
            const pct = job.progress_percent || 0;
            const mods = (job.selected_modules || []).join(', ');
            html += `<div class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">${badge}<span class="text-xs text-gray-600 truncate">${mods}</span></div>
                    ${job.status === 'processing' ? `<div class="mt-1 w-full bg-gray-200 rounded-full h-1.5"><div class="bg-indigo-600 h-1.5 rounded-full" style="width:${pct}%"></div></div><p class="text-xs text-gray-500 mt-0.5">${job.processed_pages}/${job.total_pages} (${pct}%)</p>` : ''}
                    ${job.status === 'completed' ? `<p class="text-xs text-gray-500 mt-0.5">${job.success_count} ok, ${job.failed_count} failed</p>` : ''}
                    ${job.status === 'failed' ? `<p class="text-xs text-red-500 mt-0.5 truncate">${job.error_message || 'Error'}</p>` : ''}
                </div>
                ${job.status === 'processing' ? `<button onclick="pauseJob(${job.id})" class="text-gray-400 hover:text-yellow-600" title="Pause"><i class="bi bi-pause-circle"></i></button>` : ''}
                ${job.status === 'queued' ? `<button onclick="cancelJob(${job.id})" class="text-gray-400 hover:text-red-600" title="Cancel"><i class="bi bi-x-circle"></i></button>` : ''}
                ${(job.status === 'interrupted' || job.status === 'paused') ? `<button onclick="resumeJob(${job.id})" class="text-gray-400 hover:text-green-600" title="Resume"><i class="bi bi-play-circle"></i></button>` : ''}
            </div>`;
        });
        panel.innerHTML = html;
        
        const hasActive = jobs.some(j => j.status === 'processing' || j.status === 'queued');
        if (hasActive && !jobPollInterval) startJobPolling();
        else if (!hasActive && jobPollInterval) stopJobPolling();
    } catch (error) {
        console.error('Error loading job status:', error);
    }
}

function getJobStatusBadge(status) {
    const m = {
        'queued': '<span class="text-xs px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded">Queued</span>',
        'processing': '<span class="text-xs px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded">Running</span>',
        'paused': '<span class="text-xs px-1.5 py-0.5 bg-yellow-100 text-yellow-700 rounded">Paused</span>',
        'interrupted': '<span class="text-xs px-1.5 py-0.5 bg-orange-100 text-orange-700 rounded">Interrupted</span>',
        'completed': '<span class="text-xs px-1.5 py-0.5 bg-green-100 text-green-700 rounded">Done</span>',
        'failed': '<span class="text-xs px-1.5 py-0.5 bg-red-100 text-red-700 rounded">Failed</span>',
    };
    return m[status] || m['queued'];
}

function startJobPolling() {
    if (jobPollInterval) return;
    jobPollInterval = setInterval(async () => {
        await loadJobStatusPanel();
        await triggerJobProcessing();
        // Live workspace updates while a job is active (Step 5):
        // refresh status badges/results and summary cards without a page refresh.
        if (typeof loadPages === 'function') loadPages();
        if (typeof loadPageStats === 'function') loadPageStats();
        if (typeof loadAuditSummary === 'function') loadAuditSummary();
        if (typeof loadRetryInfo === 'function') loadRetryInfo();
    }, 5000);
}

function stopJobPolling() {
    if (jobPollInterval) { clearInterval(jobPollInterval); jobPollInterval = null; }
    // Final refresh once processing has stopped so the workspace shows the completed state.
    if (typeof loadPages === 'function') loadPages();
    if (typeof loadPageStats === 'function') loadPageStats();
    if (typeof loadAuditSummary === 'function') loadAuditSummary();
}

async function pauseJob(jobId) {
    const fd = new FormData(); fd.append('action', 'pause'); fd.append('job_id', jobId);
    const r = await fetch('/routes/jobs.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) { showNotification('Job paused', 'info'); loadJobStatusPanel(); }
    else showNotification(d.message || 'Could not pause', 'error');
}

async function cancelJob(jobId) {
    if (!confirm('Cancel this audit job?')) return;
    const fd = new FormData(); fd.append('action', 'cancel'); fd.append('job_id', jobId);
    const r = await fetch('/routes/jobs.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) { showNotification('Job cancelled', 'info'); loadJobStatusPanel(); }
    else showNotification(d.message || 'Could not cancel', 'error');
}

/**
 * Resume an interrupted/paused job.
 */
async function resumeJob(jobId) {
    const fd = new FormData();
    fd.append('action', 'resume');
    fd.append('job_id', jobId);
    const r = await fetch('/routes/jobs.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) {
        showNotification('Job resumed', 'success');
        loadJobStatusPanel();
        startJobPolling();
    } else {
        showNotification(d.message || 'Could not resume', 'error');
    }
}

/**
 * Resume all interrupted jobs for the current project.
 */
async function resumeAllJobs() {
    const fd = new FormData();
    fd.append('action', 'resume_all');
    fd.append('project_id', projectId);
    const r = await fetch('/routes/jobs.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) {
        showNotification(`${d.resumed_count} job(s) resumed`, 'success');
        loadJobStatusPanel();
        startJobPolling();
    } else {
        showNotification(d.message || 'Could not resume', 'error');
    }
}

/**
 * Retry failed pages for the current project.
 */
async function retryFailedPages() {
    // Gather currently selected module keys
    const moduleKeys = [];
    if (document.getElementById('h1AuditCheckbox')?.checked) moduleKeys.push('h1');
    if (document.getElementById('metaAuditCheckbox')?.checked) moduleKeys.push('meta');
    if (document.getElementById('noindexAuditCheckbox')?.checked) moduleKeys.push('noindex');
    if (document.getElementById('faqAuditCheckbox')?.checked) moduleKeys.push('faq');
    if (document.getElementById('loremAuditCheckbox')?.checked) moduleKeys.push('lorem');
    if (document.getElementById('stagingAuditCheckbox')?.checked) moduleKeys.push('staging');
    if (document.getElementById('wordAuditCheckbox')?.checked) moduleKeys.push('word');

    if (moduleKeys.length === 0) {
        alert('Please select at least one module to retry.');
        return;
    }

    if (!confirm('Retry all failed pages for the selected modules?')) return;

    const fd = new FormData();
    fd.append('action', 'retry');
    fd.append('project_id', projectId);
    fd.append('modules', moduleKeys.join(','));

    // Include keyword if word module is selected
    if (moduleKeys.includes('word')) {
        const kw = (document.getElementById('wordKeywordInput')?.value || '').trim();
        if (kw) fd.append('keyword', kw);
    }

    const r = await fetch('/routes/jobs.php', { method: 'POST', body: fd });
    const d = await r.json();

    if (d.success) {
        if (d.job) {
            showNotification('Retry job created', 'success');
            loadJobStatusPanel();
            startJobPolling();
        } else {
            showNotification(d.message || 'No pages need retrying', 'info');
        }
    } else {
        showNotification(d.error || 'Retry failed', 'error');
    }
}

/**
 * Load retry statistics and show recovery controls if needed.
 */
async function loadRetryInfo() {
    if (typeof projectId === 'undefined') return;

    const retryPanel = document.getElementById('retryInfoPanel');
    if (!retryPanel) return;

    try {
        const response = await fetch(`/routes/jobs.php?action=recoverable&project_id=${projectId}`);
        const data = await response.json();
        if (!data.success) return;

        const info = data.recoverable;
        const hasWork = info.has_resumable_jobs || info.has_retryable_pages;

        if (!hasWork) {
            retryPanel.innerHTML = '';
            retryPanel.classList.add('hidden');
            return;
        }

        retryPanel.classList.remove('hidden');
        let html = '<div class="flex flex-wrap items-center gap-3">';

        if (info.has_resumable_jobs) {
            html += `<div class="flex items-center gap-2">
                <span class="text-xs text-orange-600"><i class="bi bi-exclamation-triangle mr-1"></i>${info.resumable_count} interrupted job(s)</span>
                <button onclick="resumeAllJobs()" class="text-xs px-2 py-1 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded transition-colors">
                    <i class="bi bi-play-circle mr-1"></i>Resume All
                </button>
            </div>`;
        }

        if (info.has_retryable_pages) {
            html += `<div class="flex items-center gap-2">
                <span class="text-xs text-red-600"><i class="bi bi-arrow-repeat mr-1"></i>${info.retryable_count} page(s) need retry</span>
                <button onclick="retryFailedPages()" class="text-xs px-2 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded transition-colors">
                    <i class="bi bi-arrow-clockwise mr-1"></i>Retry Failed
                </button>
            </div>`;
        }

        if (info.exhausted_count > 0) {
            html += `<span class="text-xs text-gray-500"><i class="bi bi-dash-circle mr-1"></i>${info.exhausted_count} exhausted</span>`;
        }

        html += '</div>';
        retryPanel.innerHTML = html;
    } catch (e) {
        console.error('Error loading retry info:', e);
    }
}

/**
 * Load processing health overview for the workspace.
 */
async function loadHealthPanel() {
    if (typeof projectId === 'undefined') return;

    const panel = document.getElementById('healthPanel');
    if (!panel) return;

    try {
        const response = await fetch(`/routes/health.php?action=project&project_id=${projectId}`);
        const data = await response.json();
        if (!data.success) return;

        const h = data.health;
        const statusColor = h.status === 'healthy' ? 'green' : h.status === 'needs_attention' ? 'yellow' : 'red';
        const statusIcon = h.status === 'healthy' ? 'bi-check-circle' : 'bi-exclamation-triangle';

        let html = `
            <div class="flex items-center gap-4 text-xs">
                <span class="text-${statusColor}-600"><i class="bi ${statusIcon} mr-1"></i>${h.status.replace('_', ' ')}</span>
                <span class="text-gray-500">${h.active_jobs} active</span>
                <span class="text-gray-500">${h.retry_queue} retry queue</span>
                ${h.processing_rate ? `<span class="text-gray-500">${h.processing_rate} pages/min</span>` : ''}
            </div>
        `;
        panel.innerHTML = html;
    } catch (e) {
        // Health panel is informational, don't error loudly
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeAuditControls();
    loadAuditSummary();
    loadJobStatusPanel();
    loadRetryInfo();
    loadHealthPanel();
});
