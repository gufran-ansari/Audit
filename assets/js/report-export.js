/**
 * Report Export — PDF, CSV, and Sharing
 * 
 * Handles:
 * - PDF export via html2pdf.js (client-side, A4 friendly)
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

    // --- PDF Export ---
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

        // Build a clean PDF-friendly version of the report
        var pdfContent = buildPdfContent();

        var opt = {
            margin: [10, 10, 10, 10],
            filename: sanitizeFilename(REPORT_META.projectName) + '-audit-report.pdf',
            image: { type: 'jpeg', quality: 0.95 },
            html2canvas: { scale: 2, useCORS: true, letterRendering: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };

        html2pdf().set(opt).from(pdfContent).save().then(function () {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }).catch(function () {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('PDF generation failed. Please try again.');
        });
    }

    function buildPdfContent() {
        var div = document.createElement('div');
        div.style.fontFamily = 'Arial, sans-serif';
        div.style.fontSize = '12px';
        div.style.color = '#1f2937';
        div.style.padding = '20px';
        div.style.maxWidth = '800px';

        // Header / Branding
        var header = '<div style="text-align:center; margin-bottom:30px; padding-bottom:20px; border-bottom:2px solid #4f46e5;">';
        header += '<h1 style="font-size:22px; font-weight:bold; color:#4f46e5; margin:0 0 4px 0;">Audit withGufran</h1>';
        header += '<p style="font-size:11px; color:#6b7280; margin:0;">SEO Audit Report</p>';
        header += '</div>';

        // Project Info
        var info = '<div style="margin-bottom:24px;">';
        info += '<h2 style="font-size:18px; font-weight:bold; margin:0 0 8px 0;">' + escHtml(REPORT_META.projectName) + '</h2>';
        info += '<p style="color:#6b7280; margin:0 0 4px 0;">Domain: ' + escHtml(REPORT_META.domain) + '</p>';
        if (REPORT_META.lastAudit) {
            info += '<p style="color:#6b7280; margin:0 0 4px 0;">Audit Date: ' + formatDateSimple(REPORT_META.lastAudit) + '</p>';
        }
        info += '<p style="color:#6b7280; margin:0;">Total URLs: ' + REPORT_META.totalUrls + ' | Audited: ' + REPORT_META.auditedPages + ' | Health Score: ' + REPORT_META.healthScore + '%</p>';
        info += '</div>';

        // Overview Summary
        var overview = '<div style="margin-bottom:24px;">';
        overview += '<h3 style="font-size:14px; font-weight:bold; margin:0 0 12px 0; color:#374151;">Overview Summary</h3>';
        overview += '<table style="width:100%; border-collapse:collapse; margin-bottom:12px;">';
        overview += '<tr>';
        overview += summaryCell('Total', countByStatus('all'), '#374151');
        overview += summaryCell('Passed', countByStatus('pass'), '#16a34a');
        overview += summaryCell('Warnings', countByStatus('warning'), '#ca8a04');
        overview += summaryCell('Failed', countByStatus('fail'), '#dc2626');
        overview += summaryCell('Errors', countByStatus('error'), '#b91c1c');
        overview += summaryCell('Not Audited', countByStatus('none'), '#6b7280');
        overview += '</tr></table>';
        overview += '</div>';

        // Module Breakdown
        var modules = '<div style="margin-bottom:24px;">';
        modules += '<h3 style="font-size:14px; font-weight:bold; margin:0 0 12px 0; color:#374151;">Issue Breakdown</h3>';
        if (MODULE_DATA && MODULE_DATA.length > 0) {
            modules += '<table style="width:100%; border-collapse:collapse; font-size:11px;">';
            modules += '<tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">';
            modules += '<th style="padding:6px 8px; text-align:left;">Module</th>';
            modules += '<th style="padding:6px 8px; text-align:center;">Total</th>';
            modules += '<th style="padding:6px 8px; text-align:center;">Pass</th>';
            modules += '<th style="padding:6px 8px; text-align:center;">Warning</th>';
            modules += '<th style="padding:6px 8px; text-align:center;">Fail</th>';
            modules += '<th style="padding:6px 8px; text-align:center;">Error</th>';
            modules += '</tr>';
            for (var i = 0; i < MODULE_DATA.length; i++) {
                var m = MODULE_DATA[i];
                modules += '<tr style="border-bottom:1px solid #f3f4f6;">';
                modules += '<td style="padding:5px 8px;">' + escHtml(m.label) + '</td>';
                modules += '<td style="padding:5px 8px; text-align:center;">' + m.total + '</td>';
                modules += '<td style="padding:5px 8px; text-align:center; color:#16a34a;">' + m.pass + '</td>';
                modules += '<td style="padding:5px 8px; text-align:center; color:#ca8a04;">' + m.warning + '</td>';
                modules += '<td style="padding:5px 8px; text-align:center; color:#dc2626;">' + m.fail + '</td>';
                modules += '<td style="padding:5px 8px; text-align:center; color:#b91c1c;">' + m.error + '</td>';
                modules += '</tr>';
            }
            modules += '</table>';
        }
        modules += '</div>';

        // Results table
        var results = '<div style="margin-bottom:24px;">';
        results += '<h3 style="font-size:14px; font-weight:bold; margin:0 0 12px 0; color:#374151;">Detailed Results</h3>';
        results += '<table style="width:100%; border-collapse:collapse; font-size:10px;">';
        results += '<tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">';
        results += '<th style="padding:5px 4px; text-align:left;">URL</th>';
        results += '<th style="padding:5px 4px; text-align:center;">H1</th>';
        results += '<th style="padding:5px 4px; text-align:center;">Meta</th>';
        results += '<th style="padding:5px 4px; text-align:center;">Noindex</th>';
        results += '<th style="padding:5px 4px; text-align:center;">FAQ</th>';
        results += '<th style="padding:5px 4px; text-align:center;">Lorem</th>';
        results += '<th style="padding:5px 4px; text-align:center;">Staging</th>';
        results += '<th style="padding:5px 4px; text-align:center;">Word</th>';
        results += '</tr>';

        var pages = REPORT_DATA || [];
        var maxRows = Math.min(pages.length, 200); // Limit for PDF performance
        for (var j = 0; j < maxRows; j++) {
            var p = pages[j];
            var url = p.original_url || '';
            if (url.length > 50) url = url.substring(0, 50) + '...';
            results += '<tr style="border-bottom:1px solid #f3f4f6;">';
            results += '<td style="padding:4px; word-break:break-all;">' + escHtml(url) + '</td>';
            results += '<td style="padding:4px; text-align:center;">' + statusText(p.h1_status) + '</td>';
            results += '<td style="padding:4px; text-align:center;">' + statusText(p.meta_status) + '</td>';
            results += '<td style="padding:4px; text-align:center;">' + statusText(p.noindex_status) + '</td>';
            results += '<td style="padding:4px; text-align:center;">' + statusText(p.faq_status) + '</td>';
            results += '<td style="padding:4px; text-align:center;">' + statusText(p.lorem_status) + '</td>';
            results += '<td style="padding:4px; text-align:center;">' + statusText(p.staging_status) + '</td>';
            results += '<td style="padding:4px; text-align:center;">' + statusText(p.word_status) + '</td>';
            results += '</tr>';
        }
        if (pages.length > maxRows) {
            results += '<tr><td colspan="8" style="padding:8px; text-align:center; color:#6b7280; font-style:italic;">Showing ' + maxRows + ' of ' + pages.length + ' pages. Full results available online.</td></tr>';
        }
        results += '</table>';
        results += '</div>';

        // Footer
        var footer = '<div style="margin-top:30px; padding-top:15px; border-top:1px solid #e5e7eb; text-align:center; color:#9ca3af; font-size:10px;">';
        footer += '<p>Generated by <strong style="color:#374151;">Audit withGufran</strong> | Powered by <strong style="color:#374151;">Codezyners</strong></p>';
        footer += '<p style="margin-top:4px;">Report URL: ' + window.location.href + '</p>';
        footer += '</div>';

        div.innerHTML = header + info + overview + modules + results + footer;
        return div;
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

    // --- Helpers ---
    function countByStatus(status) {
        var pages = REPORT_DATA || [];
        if (status === 'all') return pages.length;

        var count = 0;
        for (var i = 0; i < pages.length; i++) {
            if (getWorst(pages[i]) === status) count++;
        }
        return count;
    }

    function getWorst(page) {
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
        return hasAny ? 'pass' : 'none';
    }

    function summaryCell(label, value, color) {
        return '<td style="padding:8px 12px; text-align:center; border:1px solid #e5e7eb;">' +
            '<p style="font-size:18px; font-weight:bold; color:' + color + '; margin:0;">' + value + '</p>' +
            '<p style="font-size:10px; color:#6b7280; margin:4px 0 0 0;">' + label + '</p></td>';
    }

    function statusText(status) {
        if (!status) return '<span style="color:#9ca3af;">—</span>';
        var colors = { PASS: '#16a34a', WARNING: '#ca8a04', FAIL: '#dc2626', ERROR: '#b91c1c' };
        var labels = { PASS: '✓', WARNING: '!', FAIL: '✗', ERROR: '✗✗' };
        return '<span style="color:' + (colors[status] || '#6b7280') + '; font-weight:600;">' + (labels[status] || '—') + '</span>';
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function formatDateSimple(dateStr) {
        if (!dateStr) return '';
        try {
            var d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
        } catch (e) {
            return dateStr;
        }
    }

    function sanitizeFilename(name) {
        return (name || 'report').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').substring(0, 40);
    }

    // Boot
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
