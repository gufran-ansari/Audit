-- ============================================
-- Phase 4 Part 3: Audit Result Architecture
-- Audit History, Audit Runs, Enhanced Management
-- ============================================

USE audit_withgufran;

-- ============================================
-- Audit Runs Table
-- Track every audit execution
-- ============================================
CREATE TABLE IF NOT EXISTS audit_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    project_id INT UNSIGNED NOT NULL,
    run_type ENUM('manual', 'retry', 'preset', 'scheduled') NOT NULL DEFAULT 'manual',
    selected_modules JSON NOT NULL COMMENT 'Array of module names that were selected',
    total_pages INT UNSIGNED NOT NULL DEFAULT 0,
    processed_pages INT UNSIGNED NOT NULL DEFAULT 0,
    success_pages INT UNSIGNED NOT NULL DEFAULT 0,
    failed_pages INT UNSIGNED NOT NULL DEFAULT 0,
    run_status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    execution_time_seconds INT UNSIGNED NULL COMMENT 'Total execution time in seconds',
    error_message TEXT NULL,
    metadata JSON NULL COMMENT 'Additional run metadata',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_uuid (uuid),
    INDEX idx_project_id (project_id),
    INDEX idx_run_type (run_type),
    INDEX idx_run_status (run_status),
    INDEX idx_started_at (started_at),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Page Audit History Table
-- Track changes over time
-- ============================================
CREATE TABLE IF NOT EXISTS page_audit_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_page_id INT UNSIGNED NOT NULL,
    audit_run_id INT UNSIGNED NULL COMMENT 'Reference to audit run that caused this change',
    module_name VARCHAR(50) NOT NULL,
    previous_status ENUM('PASS', 'WARNING', 'FAIL', 'ERROR', 'NOT_RUN') NULL,
    new_status ENUM('PASS', 'WARNING', 'FAIL', 'ERROR', 'NOT_RUN') NOT NULL,
    previous_value TEXT NULL,
    new_value TEXT NULL,
    previous_data JSON NULL COMMENT 'Previous raw_data_json snapshot',
    new_data JSON NULL COMMENT 'New raw_data_json snapshot',
    change_summary VARCHAR(500) NULL COMMENT 'Human-readable summary of what changed',
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_page_id) REFERENCES project_pages(id) ON DELETE CASCADE,
    FOREIGN KEY (audit_run_id) REFERENCES audit_runs(id) ON DELETE SET NULL,
    INDEX idx_project_page_id (project_page_id),
    INDEX idx_audit_run_id (audit_run_id),
    INDEX idx_module_name (module_name),
    INDEX idx_new_status (new_status),
    INDEX idx_changed_at (changed_at),
    INDEX idx_page_module (project_page_id, module_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Update page_audit_results table
-- Add execution time and audit run reference
-- ============================================
ALTER TABLE page_audit_results 
ADD COLUMN IF NOT EXISTS audit_run_id INT UNSIGNED NULL COMMENT 'Reference to audit run' AFTER project_page_id,
ADD COLUMN IF NOT EXISTS result_summary VARCHAR(500) NULL COMMENT 'Human-readable summary' AFTER result_value,
ADD COLUMN IF NOT EXISTS error_message TEXT NULL COMMENT 'Error message if status is ERROR' AFTER result_summary,
ADD COLUMN IF NOT EXISTS execution_time_ms INT UNSIGNED NULL COMMENT 'Execution time in milliseconds' AFTER error_message;

-- Add foreign key for audit_run_id if not exists
ALTER TABLE page_audit_results 
ADD CONSTRAINT fk_audit_run_id 
FOREIGN KEY (audit_run_id) REFERENCES audit_runs(id) ON DELETE SET NULL;

-- Add index for audit_run_id if not exists
ALTER TABLE page_audit_results 
ADD INDEX IF NOT EXISTS idx_audit_run_id (audit_run_id);

-- ============================================
-- Add NOT_RUN status to existing ENUM
-- ============================================
ALTER TABLE page_audit_results 
MODIFY COLUMN result_status ENUM('PASS', 'WARNING', 'FAIL', 'ERROR', 'NOT_RUN') NOT NULL;

-- ============================================
-- Audit Summary View (for performance)
-- Aggregates audit results by project
-- ============================================
CREATE OR REPLACE VIEW project_audit_summary AS
SELECT 
    p.id AS project_id,
    p.uuid AS project_uuid,
    p.project_name,
    COUNT(DISTINCT pp.id) AS total_pages,
    COUNT(DISTINCT CASE WHEN par.result_status = 'PASS' THEN pp.id END) AS passed_pages,
    COUNT(DISTINCT CASE WHEN par.result_status = 'WARNING' THEN pp.id END) AS warning_pages,
    COUNT(DISTINCT CASE WHEN par.result_status = 'FAIL' THEN pp.id END) AS failed_pages,
    COUNT(DISTINCT CASE WHEN par.result_status = 'ERROR' THEN pp.id END) AS error_pages,
    COUNT(DISTINCT CASE WHEN par.result_status = 'NOT_RUN' OR par.id IS NULL THEN pp.id END) AS not_audited_pages,
    MAX(par.checked_at) AS last_audit_date,
    (SELECT COUNT(*) FROM audit_runs ar WHERE ar.project_id = p.id) AS total_audit_runs
FROM projects p
LEFT JOIN project_pages pp ON pp.project_id = p.id
LEFT JOIN page_audit_results par ON par.project_page_id = pp.id
WHERE p.project_status = 'active'
GROUP BY p.id, p.uuid, p.project_name;

-- ============================================
-- Module Summary View
-- Aggregates results by module for a project
-- ============================================
CREATE OR REPLACE VIEW module_audit_summary AS
SELECT 
    p.id AS project_id,
    p.uuid AS project_uuid,
    par.module_name,
    COUNT(*) AS total_audits,
    COUNT(CASE WHEN par.result_status = 'PASS' THEN 1 END) AS pass_count,
    COUNT(CASE WHEN par.result_status = 'WARNING' THEN 1 END) AS warning_count,
    COUNT(CASE WHEN par.result_status = 'FAIL' THEN 1 END) AS fail_count,
    COUNT(CASE WHEN par.result_status = 'ERROR' THEN 1 END) AS error_count,
    MAX(par.checked_at) AS last_checked
FROM projects p
INNER JOIN project_pages pp ON pp.project_id = p.id
INNER JOIN page_audit_results par ON par.project_page_id = pp.id
WHERE p.project_status = 'active'
GROUP BY p.id, p.uuid, par.module_name;

-- ============================================
-- Indexes for Performance Optimization
-- ============================================

-- Composite index for filtering by project and status
CREATE INDEX IF NOT EXISTS idx_project_status_filter 
ON page_audit_results(project_page_id, result_status, module_name);

-- Composite index for date range queries
CREATE INDEX IF NOT EXISTS idx_checked_at_status 
ON page_audit_results(checked_at, result_status);

-- Index for quick URL lookups in project_pages
CREATE INDEX IF NOT EXISTS idx_project_url_status 
ON project_pages(project_id, url_status, page_status);

-- ============================================
-- Insert Sample Audit Run (for testing)
-- ============================================
-- This will be removed in production, used for development only
-- INSERT INTO audit_runs (uuid, project_id, run_type, selected_modules, total_pages, run_status)
-- VALUES (UUID(), 1, 'manual', '["h1_audit", "meta_audit"]', 0, 'pending');

-- ============================================
-- Migration Complete
-- ============================================
-- Phase 4 Part 3 database structures ready
-- Tables: audit_runs, page_audit_history
-- Enhanced: page_audit_results
-- Views: project_audit_summary, module_audit_summary
