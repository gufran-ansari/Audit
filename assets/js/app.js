/**
 * Audit withGufran - Application JavaScript
 * Phase 1: Base UI/UX and Responsive Layout
 * 
 * This file handles base interactions and responsive behavior
 * MODULE ATTACHMENT POINTS are marked for future feature integration
 */

// ============================================
// DOM Elements
// ============================================
const sidebar = document.getElementById('sidebar');
const openSidebarBtn = document.getElementById('openSidebar');
const closeSidebarBtn = document.getElementById('closeSidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const sidebarItems = document.querySelectorAll('.sidebar-item');

// ============================================
// Mobile Sidebar Toggle
// MODULE ATTACHMENT POINT: Navigation state management
// ============================================
function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    sidebar.classList.add('translate-x-0');
    sidebarOverlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    sidebar.classList.remove('translate-x-0');
    sidebarOverlay.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Event Listeners for Sidebar
if (openSidebarBtn) {
    openSidebarBtn.addEventListener('click', openSidebar);
}

if (closeSidebarBtn) {
    closeSidebarBtn.addEventListener('click', closeSidebar);
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebar);
}

// Close sidebar on window resize to desktop
window.addEventListener('resize', () => {
    if (window.innerWidth >= 1024) {
        closeSidebar();
    }
});

// ============================================
// Sidebar Navigation Active State
// MODULE ATTACHMENT POINT: Router integration
// ============================================
sidebarItems.forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all items
        sidebarItems.forEach(i => i.classList.remove('active'));
        
        // Add active class to clicked item
        this.classList.add('active');
        
        // Close sidebar on mobile after selection
        if (window.innerWidth < 1024) {
            closeSidebar();
        }
        
        // MODULE ATTACHMENT POINT: Page routing logic will be added here
        console.log('Navigation item clicked:', this.textContent.trim());
    });
});

// ============================================
// Table Row Click Handlers
// MODULE ATTACHMENT POINT: Detail view navigation
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Desktop table rows
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't trigger if clicking action buttons
            if (e.target.closest('button')) {
                return;
            }
            
            // MODULE ATTACHMENT POINT: Open audit detail view
            const auditId = this.querySelector('.text-indigo-600').textContent;
            console.log('Opening audit details for:', auditId);
            
            // Add visual feedback
            this.classList.add('bg-indigo-50');
            setTimeout(() => {
                this.classList.remove('bg-indigo-50');
            }, 300);
        });
    });
    
    // Mobile card clicks
    const mobileCards = document.querySelectorAll('.md\\:hidden > div');
    mobileCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking action buttons
            if (e.target.closest('button')) {
                return;
            }
            
            // MODULE ATTACHMENT POINT: Open audit detail view
            const auditId = this.querySelector('.text-indigo-600').textContent;
            console.log('Opening audit details for:', auditId);
            
            // Add visual feedback
            this.classList.add('bg-indigo-50');
            setTimeout(() => {
                this.classList.remove('bg-indigo-50');
            }, 300);
        });
    });
});

// ============================================
// Action Button Handlers
// MODULE ATTACHMENT POINT: CRUD operations
// ============================================

// View buttons
document.querySelectorAll('.fa-eye').forEach(btn => {
    btn.closest('button').addEventListener('click', function(e) {
        e.stopPropagation();
        console.log('View action triggered');
        // MODULE ATTACHMENT POINT: Open detail modal or page
    });
});

// Edit buttons
document.querySelectorAll('.fa-edit').forEach(btn => {
    btn.closest('button').addEventListener('click', function(e) {
        e.stopPropagation();
        console.log('Edit action triggered');
        // MODULE ATTACHMENT POINT: Open edit modal or page
    });
});

// Delete buttons
document.querySelectorAll('.fa-trash').forEach(btn => {
    btn.closest('button').addEventListener('click', function(e) {
        e.stopPropagation();
        console.log('Delete action triggered');
        // MODULE ATTACHMENT POINT: Show confirmation modal
    });
});

// ============================================
// Stats Card Click Handlers
// MODULE ATTACHMENT POINT: Filter integration
// ============================================
document.querySelectorAll('.card-hover').forEach(card => {
    if (card.closest('.grid')) {
        card.addEventListener('click', function() {
            const statType = this.querySelector('p').textContent;
            console.log('Stat card clicked:', statType);
            // MODULE ATTACHMENT POINT: Filter table by stat type
        });
    }
});

// ============================================
// Quick Action Button Handlers
// MODULE ATTACHMENT POINT: Action workflows
// ============================================

// Start New Audit button (already has inline handler via button element)
const newAuditBtn = document.querySelector('.bg-gradient-to-r');
if (newAuditBtn) {
    newAuditBtn.addEventListener('click', function() {
        console.log('Starting new audit workflow');
        // MODULE ATTACHMENT POINT: Navigate to new audit page or open modal
    });
}

// ============================================
// Search Functionality
// MODULE ATTACHMENT POINT: Search/filter module
// ============================================
const searchInput = document.querySelector('input[placeholder*="Search"]');
if (searchInput) {
    let searchTimeout;
    
    searchInput.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        
        // Debounce search to avoid excessive calls
        searchTimeout = setTimeout(() => {
            const query = e.target.value.trim();
            console.log('Search query:', query);
            // MODULE ATTACHMENT POINT: Implement search/filter logic
        }, 300);
    });
}

// ============================================
// Notification Bell Click
// MODULE ATTACHMENT POINT: Notification system
// ============================================
const notificationBtn = document.querySelector('.fa-bell');
if (notificationBtn) {
    notificationBtn.closest('button').addEventListener('click', function() {
        console.log('Notifications clicked');
        // MODULE ATTACHMENT POINT: Open notifications panel
    });
}

// ============================================
// Table Filter and Export Handlers
// MODULE ATTACHMENT POINT: Data export module
// ============================================
const filterBtn = document.querySelector('.fa-filter');
if (filterBtn) {
    filterBtn.closest('button').addEventListener('click', function() {
        console.log('Filter clicked');
        // MODULE ATTACHMENT POINT: Open filter panel
    });
}

const exportBtn = document.querySelector('.fa-download');
if (exportBtn) {
    exportBtn.closest('button').addEventListener('click', function() {
        console.log('Export clicked');
        // MODULE ATTACHMENT POINT: Export table data (CSV, PDF, Excel)
    });
}

// ============================================
// Pagination Handlers
// MODULE ATTACHMENT POINT: Pagination module
// ============================================
const paginationButtons = document.querySelectorAll('.px-6.py-4.border-t button');
paginationButtons.forEach(btn => {
    btn.addEventListener('click', function() {
        const buttonText = this.textContent.trim();
        console.log('Pagination:', buttonText);
        
        // Remove active state from all pagination buttons
        paginationButtons.forEach(b => {
            b.classList.remove('bg-indigo-600', 'text-white');
            if (!b.textContent.includes('Previous') && !b.textContent.includes('Next')) {
                b.classList.add('border', 'border-gray-300', 'hover:bg-gray-50');
            }
        });
        
        // Add active state if it's a number button
        if (!buttonText.includes('Previous') && !buttonText.includes('Next')) {
            this.classList.add('bg-indigo-600', 'text-white');
            this.classList.remove('border', 'border-gray-300', 'hover:bg-gray-50');
        }
        
        // MODULE ATTACHMENT POINT: Load page data
    });
});

// ============================================
// Utility Functions
// ============================================

/**
 * Show toast notification (placeholder for future module)
 * MODULE ATTACHMENT POINT: Toast notification system
 */
function showToast(message, type = 'info') {
    console.log(`Toast [${type}]:`, message);
    // MODULE ATTACHMENT POINT: Implement toast UI
}

/**
 * Show loading spinner (placeholder for future module)
 * MODULE ATTACHMENT POINT: Loading state management
 */
function showLoading() {
    console.log('Loading started');
    // MODULE ATTACHMENT POINT: Show loading overlay
}

function hideLoading() {
    console.log('Loading finished');
    // MODULE ATTACHMENT POINT: Hide loading overlay
}

/**
 * Confirm dialog (placeholder for future module)
 * MODULE ATTACHMENT POINT: Modal/confirmation system
 */
function confirmAction(message, callback) {
    const confirmed = confirm(message);
    if (confirmed && callback) {
        callback();
    }
    // MODULE ATTACHMENT POINT: Replace with custom modal
}

// ============================================
// Initialize Application
// ============================================
console.log('Audit withGufran - Application initialized');
console.log('Phase 1: Base UI/UX loaded successfully');

// MODULE ATTACHMENT POINT: Initialize additional modules
// - Authentication module
// - Data fetching module
// - State management
// - WebSocket connections
// - Analytics tracking
