-- ============================================
-- Phase 9 Part 1: Performance Optimization
-- Additional indexes for fast queries at scale
-- ============================================

USE audit_withgufran;

-- ============================================
-- Composite indexes for common query patterns
-- ============================================

-- Project pages: common filters (project + status + active URLs)
ALTER TABLE project_pages
    ADD INDEX idx_project_url_status (project_id, url_status, id) ALGORITHM=INPLACE;

-- Audit results: module lookups by page (used in report generation)
ALTER TABLE page_audit_results
    ADD INDEX idx_page_module_status (project_page_id, module_name, result_status) ALGORITHM=INPLACE;

-- Audit results: project-wide aggregations via join
ALTER TABLE page_audit_results
    ADD INDEX idx_module_status (module_name, result_status) ALGORITHM=INPLACE;

-- Manual overrides: fast project-level lookups
ALTER TABLE page_manual_overrides
    ADD INDEX idx_page_module (project_page_id, module_name, override_status) ALGORITHM=INPLACE;

-- Activity log: recent activity with user
ALTER TABLE activity_log
    ADD INDEX idx_created_user (created_at DESC, user_id) ALGORITHM=INPLACE;

-- Notifications: user inbox query
ALTER TABLE notifications
    ADD INDEX idx_user_read_created (user_id, is_read, created_at DESC) ALGORITHM=INPLACE;

-- Reports: slug lookup (already unique, adding visibility for public access)
ALTER TABLE reports
    ADD INDEX idx_slug_visibility (report_slug, visibility) ALGORITHM=INPLACE;

-- ============================================
-- Optimize page_audit_results for report generator
-- Covering index for the LEFT JOIN pattern used in getDetailedResults
-- ============================================
ALTER TABLE page_audit_results
    ADD INDEX idx_page_module_cover (project_page_id, module_name, result_status, result_value(100), checked_at) ALGORITHM=INPLACE;
