-- ============================================
-- Phase 6 Part 1: Background Job System
-- ============================================

USE audit_withgufran;

-- ============================================
-- Audit Jobs Table
-- Tracks individual job execution units
-- ============================================
CREATE TABLE IF NOT EXISTS audit_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_run_id INT UNSIGNED NULL COMMENT 'Reference to parent audit run',
    project_id INT UNSIGNED NOT NULL,
    job_type VARCHAR(50) NOT NULL DEFAULT 'audit' COMMENT 'audit, retry, scheduled',
    selected_modules JSON NOT NULL COMMENT 'Array of module keys to execute',
    module_params JSON NULL COMMENT 'Extra params per module (e.g. keywords)',
    status ENUM('queued', 'processing', 'paused', 'interrupted', 'completed', 'failed') NOT NULL DEFAULT 'queued',
    priority TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1=highest, 10=lowest',
    total_pages INT UNSIGNED NOT NULL DEFAULT 0,
    processed_pages INT UNSIGNED NOT NULL DEFAULT 0,
    success_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_retries TINYINT UNSIGNED NOT NULL DEFAULT 3,
    batch_size INT UNSIGNED NOT NULL DEFAULT 25 COMMENT 'Pages per batch',
    last_page_id INT UNSIGNED NULL COMMENT 'Last processed page ID for resumption',
    error_message TEXT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    last_heartbeat TIMESTAMP NULL COMMENT 'Updated during processing to detect interruptions',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_project_id (project_id),
    INDEX idx_priority_status (priority, status),
    INDEX idx_last_heartbeat (last_heartbeat),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Audit Job Logs Table
-- Detailed execution logs per job
-- ============================================
CREATE TABLE IF NOT EXISTS audit_job_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    log_level ENUM('info', 'warning', 'error', 'debug') NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    context JSON NULL COMMENT 'Additional structured data',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES audit_jobs(id) ON DELETE CASCADE,
    INDEX idx_job_id (job_id),
    INDEX idx_log_level (log_level),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
