-- ============================================
-- Phase 8 Part 1: Manual Overrides + Workflow Management
-- ============================================

USE audit_withgufran;

-- ============================================
-- Page Manual Overrides Table
-- Stores admin overrides without modifying original audit data
-- ============================================
CREATE TABLE IF NOT EXISTS page_manual_overrides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_page_id INT UNSIGNED NOT NULL,
    module_name VARCHAR(50) NOT NULL COMMENT 'h1_audit, meta_audit, noindex_audit, etc.',
    original_status ENUM('PASS', 'WARNING', 'FAIL', 'ERROR') NOT NULL COMMENT 'Original automated audit result',
    override_status ENUM('ignored', 'fixed', 'accepted', 'false_positive', 'needs_review') NOT NULL,
    notes TEXT NULL COMMENT 'Admin notes explaining the override',
    updated_by INT UNSIGNED NOT NULL COMMENT 'User who made the override',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_page_id) REFERENCES project_pages(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_override_per_module (project_page_id, module_name),
    INDEX idx_project_page_id (project_page_id),
    INDEX idx_module_name (module_name),
    INDEX idx_override_status (override_status),
    INDEX idx_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Override History Table
-- Tracks all changes to overrides for audit trail
-- ============================================
CREATE TABLE IF NOT EXISTS override_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    override_id INT UNSIGNED NOT NULL,
    project_page_id INT UNSIGNED NOT NULL,
    module_name VARCHAR(50) NOT NULL,
    old_status VARCHAR(50) NULL COMMENT 'Previous override status (NULL if first override)',
    new_status VARCHAR(50) NOT NULL COMMENT 'New override status',
    notes TEXT NULL,
    changed_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (override_id) REFERENCES page_manual_overrides(id) ON DELETE CASCADE,
    FOREIGN KEY (project_page_id) REFERENCES project_pages(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_override_id (override_id),
    INDEX idx_project_page_id (project_page_id),
    INDEX idx_changed_by (changed_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
