-- ============================================
-- Phase 6 Part 2: Smart Retry Engine + Auto Resume
-- ============================================

USE audit_withgufran;

-- ============================================
-- Page Retry Tracking Table
-- Tracks per-page, per-module failure/retry state
-- ============================================
CREATE TABLE IF NOT EXISTS page_retry_tracking (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_page_id INT UNSIGNED NOT NULL,
    module_name VARCHAR(50) NOT NULL,
    retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_retries TINYINT UNSIGNED NOT NULL DEFAULT 3,
    error_type VARCHAR(50) NULL COMMENT 'timeout, connection, invalid_response, malformed_html, processing, unknown',
    last_error TEXT NULL,
    last_attempt_at TIMESTAMP NULL,
    status ENUM('retry_pending', 'retrying', 'retry_failed', 'retry_exhausted', 'resolved') NOT NULL DEFAULT 'retry_pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_page_id) REFERENCES project_pages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_page_module_retry (project_page_id, module_name),
    INDEX idx_status (status),
    INDEX idx_module_name (module_name),
    INDEX idx_error_type (error_type),
    INDEX idx_project_page (project_page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
