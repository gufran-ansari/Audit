-- ============================================
-- Phase 8 Part 2: System Settings & Administration
-- ============================================

USE audit_withgufran;

-- ============================================
-- Expand settings table with category grouping
-- ============================================
ALTER TABLE settings
    ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'general' AFTER setting_key,
    ADD COLUMN setting_type VARCHAR(30) NOT NULL DEFAULT 'text' AFTER setting_value,
    ADD COLUMN description VARCHAR(500) NULL AFTER setting_type,
    ADD INDEX idx_category (category);

-- ============================================
-- Update existing settings with categories
-- ============================================
UPDATE settings SET category = 'general' WHERE setting_key = 'app_name';
UPDATE settings SET category = 'general' WHERE setting_key = 'app_timezone';
UPDATE settings SET category = 'system' WHERE setting_key = 'maintenance_mode';

-- ============================================
-- Insert default settings by category
-- ============================================

-- General
INSERT INTO settings (setting_key, category, setting_value, setting_type, description) VALUES
('company_name', 'general', 'Codezyners', 'text', 'Company name shown in reports and branding'),
('default_theme', 'general', 'light', 'select', 'Default application theme'),
('date_format', 'general', 'M d, Y', 'text', 'Date display format'),
('time_format', 'general', 'h:i A', 'text', 'Time display format')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- Audit
INSERT INTO settings (setting_key, category, setting_value, setting_type, description) VALUES
('audit_batch_size', 'audit', '10', 'number', 'Number of pages to audit per batch'),
('audit_max_retries', 'audit', '3', 'number', 'Maximum retry attempts for failed audits'),
('audit_request_timeout', 'audit', '30', 'number', 'HTTP request timeout in seconds'),
('audit_user_agent', 'audit', 'AuditWithGufran/1.0', 'text', 'User-Agent string for HTTP requests'),
('audit_default_modules', 'audit', 'h1,meta,noindex,faq,lorem,staging,word', 'text', 'Default enabled modules (comma-separated keys)')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- Reports
INSERT INTO settings (setting_key, category, setting_value, setting_type, description) VALUES
('report_default_visibility', 'reports', 'private', 'select', 'Default visibility for new reports'),
('report_default_title', 'reports', 'SEO Audit Report', 'text', 'Default title shown on reports'),
('report_branding_text', 'reports', 'Audit withGufran', 'text', 'Branding text in report header'),
('report_footer_text', 'reports', 'Powered by Codezyners', 'text', 'Footer text on reports'),
('report_enable_pdf', 'reports', '1', 'boolean', 'Enable PDF export on public reports'),
('report_enable_csv', 'reports', '1', 'boolean', 'Enable CSV export on public reports')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- Security
INSERT INTO settings (setting_key, category, setting_value, setting_type, description) VALUES
('session_timeout', 'security', '7200', 'number', 'Session timeout in seconds'),
('max_login_attempts', 'security', '5', 'number', 'Maximum failed login attempts before lockout'),
('password_min_length', 'security', '8', 'number', 'Minimum password length'),
('force_https', 'security', '0', 'boolean', 'Force HTTPS connections (future)')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- System
INSERT INTO settings (setting_key, category, setting_value, setting_type, description) VALUES
('system_version', 'system', '1.8.0', 'text', 'Current system version')
ON DUPLICATE KEY UPDATE setting_key=setting_key;
