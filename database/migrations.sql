-- ============================================
-- Audit withGufran Database Migrations
-- Phase 2: Authentication & Project Management
-- ============================================

-- Create database
CREATE DATABASE IF NOT EXISTS audit_withgufran CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE audit_withgufran;

-- ============================================
-- Users Table
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Settings Table
-- ============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Insert Default Super Admin
-- Password: admin123 (Please change this immediately after first login)
-- ============================================
INSERT INTO users (name, email, password, role, status) VALUES 
('Super Admin', 'admin@auditwithgufran.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active')
ON DUPLICATE KEY UPDATE id=id;

-- ============================================
-- Insert Default Settings
-- ============================================
INSERT INTO settings (setting_key, setting_value) VALUES 
('app_name', 'Audit withGufran'),
('app_timezone', 'UTC'),
('maintenance_mode', '0')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- ============================================
-- Projects Table (Phase 2, Part 2)
-- ============================================
CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    project_name VARCHAR(255) NOT NULL,
    primary_domain VARCHAR(255) NOT NULL,
    normalized_domain VARCHAR(255) NOT NULL,
    report_slug VARCHAR(255) NOT NULL UNIQUE,
    project_status ENUM('active', 'archived', 'paused') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_uuid (uuid),
    INDEX idx_status (project_status),
    INDEX idx_created_by (created_by),
    INDEX idx_report_slug (report_slug),
    INDEX idx_normalized_domain (normalized_domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Project Activity Logs Table
-- ============================================
CREATE TABLE IF NOT EXISTS project_activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    activity_type ENUM('created', 'updated', 'archived', 'restored', 'status_changed') NOT NULL,
    activity_description TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_project_id (project_id),
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Project Sitemaps Table (Phase 3, Part 1)
-- ============================================
CREATE TABLE IF NOT EXISTS project_sitemaps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    sitemap_url VARCHAR(500) NOT NULL,
    sitemap_type ENUM('sitemap', 'sitemap_index', 'nested_sitemap') NOT NULL DEFAULT 'sitemap',
    status ENUM('discovered', 'validated', 'invalid', 'unreachable') NOT NULL DEFAULT 'discovered',
    sitemap_status ENUM('active', 'disabled') NOT NULL DEFAULT 'active' COMMENT 'Active or disabled for imports',
    discovered_from VARCHAR(255) NULL COMMENT 'Source of discovery: robots.txt, manual, common_location',
    total_urls INT UNSIGNED DEFAULT 0,
    last_fetched_at TIMESTAMP NULL,
    last_imported_at TIMESTAMP NULL COMMENT 'Last time URLs were imported from this sitemap',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sitemap_per_project (project_id, sitemap_url),
    INDEX idx_project_id (project_id),
    INDEX idx_status (status),
    INDEX idx_sitemap_type (sitemap_type),
    INDEX idx_sitemap_status (sitemap_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Project Pages Table (Phase 3, Part 2)
-- ============================================
CREATE TABLE IF NOT EXISTS project_pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    sitemap_id INT UNSIGNED NULL COMMENT 'Source sitemap, NULL if manually added',
    original_url VARCHAR(2000) NOT NULL COMMENT 'Original URL as found in sitemap',
    normalized_url VARCHAR(2000) NOT NULL COMMENT 'Normalized URL for comparison',
    url_hash CHAR(64) NOT NULL COMMENT 'SHA256 hash of normalized URL for fast lookups',
    page_status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    url_status ENUM('active', 'disabled', 'removed', 'pending_audit') NOT NULL DEFAULT 'active' COMMENT 'URL lifecycle status',
    first_discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (sitemap_id) REFERENCES project_sitemaps(id) ON DELETE SET NULL,
    UNIQUE KEY unique_page_per_project (project_id, url_hash),
    INDEX idx_project_id (project_id),
    INDEX idx_sitemap_id (sitemap_id),
    INDEX idx_url_hash (url_hash),
    INDEX idx_page_status (page_status),
    INDEX idx_url_status (url_status),
    INDEX idx_normalized_url (normalized_url(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Page Audit Results Table (Phase 4, Part 1)
-- ============================================
CREATE TABLE IF NOT EXISTS page_audit_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_page_id INT UNSIGNED NOT NULL,
    module_name VARCHAR(50) NOT NULL COMMENT 'h1_audit, meta_audit, noindex_audit, etc.',
    result_status ENUM('PASS', 'WARNING', 'FAIL', 'ERROR') NOT NULL,
    result_value TEXT NULL COMMENT 'Primary result value (e.g., H1 text, meta description)',
    raw_data_json JSON NULL COMMENT 'Complete audit data in JSON format',
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_page_id) REFERENCES project_pages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_module_per_page (project_page_id, module_name),
    INDEX idx_project_page_id (project_page_id),
    INDEX idx_module_name (module_name),
    INDEX idx_result_status (result_status),
    INDEX idx_checked_at (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
