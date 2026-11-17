/**
 * Report Export — PDF, CSV, and Sharing
 * 
 * Handles:
 * - PDF export via server endpoint (FPDF)
 * - CSV export via server endpoint
 * - Copy Report Link with tooltip feedback
 */
(function () {
    'use strict';

    function init() {
        bindExportPdf();
        bindExportCsv();
        bindCopyLink();
    }

    // --- PDF Export (Server-side via FPDF) ---
    function bindExportPdf() {
        var btn = document.getElementById('exportPdfBtn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            generatePdf();
        });
    }

    function generatePdf() {
        var btn = document.getElementById('exportPdfBtn');
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i><span class="hidden lg:inline">Generating...</span>';
        btn.disabled = true;

        // Get the report slug from the current URL
        var pathParts = window.location.pathname.split('/').filter(function(p) { return p; });
        var slug = pathParts[pathParts.length - 1];
        
        // Build export URL
        var exportUrl = window.location.origin + '/report/' + slug + '/export?format=pdf';
        window.location.href = exportUrl;

        // Reset button state after a short delay (the download will start)
        setTimeout(function () {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 2000);
    }

    // --- CSV Export ---
    function bindExportCsv() {
        var btn = document.getElementById('exportCsvBtn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var exportUrl = window.location.pathname + '/export?format=csv';
            // Handle trailing slashes
            exportUrl = exportUrl.replace(/\/+/g, '/');
            window.location.href = exportUrl;
        });
    }

    // --- Copy Link ---
    function bindCopyLink() {
        var btn = document.getElementById('copyLinkBtn');
        var tooltip = document.getElementById('copyTooltip');
        if (!btn || !tooltip) return;

        btn.addEventListener('click', function () {
            var reportUrl = window.location.origin + window.location.pathname;
            // Remove trailing slash for clean URL
            reportUrl = reportUrl.replace(/\/$/, '');

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(reportUrl).then(function () {
                    showCopyTooltip(tooltip);
                }).catch(function () {
                    fallbackCopy(reportUrl, tooltip);
                });
            } else {
                fallbackCopy(reportUrl, tooltip);
            }
        });
    }

    function fallbackCopy(text, tooltip) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            showCopyTooltip(tooltip);
        } catch (e) {
            // Silent fail
        }
        document.body.removeChild(textarea);
    }

    function showCopyTooltip(tooltip) {
        tooltip.classList.add('show');
        setTimeout(function () {
            tooltip.classList.remove('show');
        }, 2000);
    }

    // Boot
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();