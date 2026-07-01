-- ============================================
-- Phase 7 Part 1: Dynamic Public Report System
-- ============================================

USE audit_withgufran;

CREATE TABLE IF NOT EXISTS reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    report_slug VARCHAR(255) NOT NULL UNIQUE COMMENT 'URL-friendly slug e.g. client-name-a81x',
    visibility ENUM('public', 'private') NOT NULL DEFAULT 'private',
    last_generated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_report (project_id),
    INDEX idx_report_slug (report_slug),
    INDEX idx_visibility (visibility)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
