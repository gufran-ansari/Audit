/**
 * Audit withGufran - Application JavaScript
 * Phase 1, Part 1: Base Dashboard Layout
 * 
 * This file handles base interactions for the dashboard layout
 * MODULE ATTACHMENT POINTS are marked for future feature integration
 */

// ============================================
// Wait for DOM to be fully loaded
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // DOM Elements
    // ============================================
    const sidebar = document.getElementById('sidebar');
    const openSidebarBtn = document.getElementById('openSidebar');
    const closeSidebarBtn = document.getElementById('closeSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // ============================================
    // Sidebar Toggle Functions
    // MODULE ATTACHMENT POINT: Navigation state management
    // ============================================
    
    function openSidebar() {
        if (sidebar && sidebarOverlay) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            sidebarOverlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeSidebar() {
        if (sidebar && sidebarOverlay) {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            sidebarOverlay.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    // ============================================
    // Event Listeners
    // ============================================
    
    // Open sidebar on mobile
    if (openSidebarBtn) {
        openSidebarBtn.addEventListener('click', openSidebar);
    }
    
    // Close sidebar on mobile
    if (closeSidebarBtn) {
        closeSidebarBtn.addEventListener('click', closeSidebar);
    }
    
    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    
    // Close sidebar on window resize to desktop size
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            closeSidebar();
        }
    });
    
    // Close sidebar on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });
    
    // ============================================
    // Navigation Active State (Placeholder)
    // MODULE ATTACHMENT POINT: Router integration
    // ============================================
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Prevent default for placeholder links
            if (this.getAttribute('href') === '#') {
                e.preventDefault();
            }
            
            // Visual feedback only (no actual routing yet)
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
            
            // Close sidebar on mobile after selection
            if (window.innerWidth < 1024) {
                closeSidebar();
            }
        });
    });
    
    // ============================================
    // Theme Toggle (Placeholder)
    // MODULE ATTACHMENT POINT: Theme switching functionality
    // ============================================
    const themeToggle = document.getElementById('themeToggle');
    let isDarkMode = false;
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            isDarkMode = !isDarkMode;
            const icon = this.querySelector('i');
            
            if (isDarkMode) {
                icon.classList.remove('bi-moon');
                icon.classList.add('bi-sun');
                document.body.classList.add('dark-mode');
            } else {
                icon.classList.remove('bi-sun');
                icon.classList.add('bi-moon');
                document.body.classList.remove('dark-mode');
            }
            
            console.log('Theme toggled:', isDarkMode ? 'dark' : 'light');
        });
    }
    
    // ============================================
    // Detail Drawer Functions
    // MODULE ATTACHMENT POINT: Detail view functionality
    // ============================================
    const detailDrawer = document.getElementById('detailDrawer');
    const drawerOverlay = document.getElementById('drawerOverlay');
    const closeDrawerBtn = document.getElementById('closeDrawer');
    const tableRows = document.querySelectorAll('.table-row');
    const mobileCards = document.querySelectorAll('.mobile-card');
    
    function openDrawer(urlId) {
        if (detailDrawer && drawerOverlay) {
            detailDrawer.classList.remove('translate-x-full');
            detailDrawer.classList.add('translate-x-0');
            drawerOverlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Update drawer content based on urlId
            console.log('Opening drawer for URL ID:', urlId);
        }
    }
    
    function closeDrawer() {
        if (detailDrawer && drawerOverlay) {
            detailDrawer.classList.add('translate-x-full');
            detailDrawer.classList.remove('translate-x-0');
            drawerOverlay.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    // Table row clicks
    if (tableRows) {
        tableRows.forEach(row => {
            row.addEventListener('click', function(e) {
                // Don't open drawer if clicking checkbox or action button
                if (e.target.type === 'checkbox' || e.target.closest('button')) {
                    return;
                }
                const urlId = this.getAttribute('data-url-id');
                openDrawer(urlId);
            });
        });
    }
    
    // Mobile card clicks
    if (mobileCards) {
        mobileCards.forEach(card => {
            card.addEventListener('click', function(e) {
                // Don't open drawer if clicking checkbox or action button
                if (e.target.type === 'checkbox' || e.target.closest('button')) {
                    return;
                }
                const urlId = this.getAttribute('data-url-id');
                openDrawer(urlId);
            });
        });
    }
    
    // Close drawer button
    if (closeDrawerBtn) {
        closeDrawerBtn.addEventListener('click', closeDrawer);
    }
    
    // Close drawer on overlay click
    if (drawerOverlay) {
        drawerOverlay.addEventListener('click', closeDrawer);
    }
    
    // Close drawer on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDrawer();
        }
    });
    
    // ============================================
    // Module Chip Selection
    // MODULE ATTACHMENT POINT: Module selection logic
    // ============================================
    const moduleChips = document.querySelectorAll('.module-chip');
    
    if (moduleChips) {
        moduleChips.forEach(chip => {
            chip.addEventListener('click', function() {
                this.classList.toggle('active');
                console.log('Module chip toggled:', this.textContent.trim());
            });
        });
    }
    
    // ============================================
    // Create Project Modal
    // MODULE ATTACHMENT POINT: Project creation functionality
    // ============================================
    const createProjectBtn = document.getElementById('createProjectBtn');
    const createProjectModal = document.getElementById('createProjectModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    const createProjectForm = document.getElementById('createProjectForm');
    
    function openModal() {
        if (createProjectModal) {
            createProjectModal.classList.remove('hidden');
            createProjectModal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeModal() {
        if (createProjectModal) {
            createProjectModal.classList.add('hidden');
            createProjectModal.classList.remove('flex');
            document.body.style.overflow = '';
        }
    }
    
    if (createProjectBtn) {
        createProjectBtn.addEventListener('click', openModal);
    }
    
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
    }
    
    if (cancelModalBtn) {
        cancelModalBtn.addEventListener('click', closeModal);
    }
    
    // Close modal on overlay click
    if (createProjectModal) {
        createProjectModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    }
    
    // Form submission
    if (createProjectForm) {
        createProjectForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Project creation form submitted');
            // MODULE ATTACHMENT POINT: Actual form submission logic
            closeModal();
        });
    }
    
    // ============================================
    // Placeholder Button Handlers
    // MODULE ATTACHMENT POINT: Action button functionality
    // ============================================
    
    // Search button
    const searchBtn = document.querySelector('.bi-search').closest('button');
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            console.log('Search clicked - MODULE ATTACHMENT POINT');
        });
    }
    
    // Notification button
    const notificationBtn = document.querySelector('.bi-bell').closest('button');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            console.log('Notifications clicked - MODULE ATTACHMENT POINT');
        });
    }
    
    // Filter button
    const filterBtn = document.querySelector('.bi-funnel')?.closest('button');
    if (filterBtn) {
        filterBtn.addEventListener('click', function() {
            console.log('Filter clicked - MODULE ATTACHMENT POINT');
        });
    }
    
    // New Audit button
    const newAuditBtn = document.querySelector('.bi-plus-lg')?.closest('button');
    if (newAuditBtn) {
        newAuditBtn.addEventListener('click', function() {
            console.log('New Audit clicked - MODULE ATTACHMENT POINT');
        });
    }
    
    // ============================================
    // Initialize
    // ============================================
    console.log('Audit withGufran - Base Dashboard Layout Initialized');
    console.log('Phase 1, Part 1: Ready for module integration');
    
});

// ============================================
// MODULE ATTACHMENT POINTS for Future Features
// ============================================

/*
 * MODULE ATTACHMENT POINT: API Integration
 * - Fetch user data
 * - Load dashboard statistics
 * - Get recent audits list
 */

/*
 * MODULE ATTACHMENT POINT: State Management
 * - Application state
 * - User preferences
 * - Active project tracking
 */

/*
 * MODULE ATTACHMENT POINT: Real-time Updates
 * - WebSocket connections
 * - Notification system
 * - Live data refresh
 */

/*
 * MODULE ATTACHMENT POINT: Authentication
 * - Login/logout handlers
 * - Token management
 * - Session persistence
 */

/*
 * MODULE ATTACHMENT POINT: Data Operations
 * - CRUD operations
 * - Form submissions
 * - Data validation
 */

/*
 * MODULE ATTACHMENT POINT: UI Components
 * - Modal dialogs
 * - Toast notifications
 * - Loading states
 * - Error handling
 */
