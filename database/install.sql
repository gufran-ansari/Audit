-- ============================================
-- Audit withGufran Database Installation Script
-- Unified schema combining all migration phases
-- Generated from individual migration files
-- ============================================

-- NOTE: This script does NOT include CREATE DATABASE or USE statements.
-- The user must select their target database in phpMyAdmin before importing.
-- All tables use InnoDB engine with utf8mb4_unicode_ci collation.

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SETTINGS TABLE
-- Includes columns from base + Phase 8 Part 2 extensions
-- ============================================
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(255) NOT NULL UNIQUE,
    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
    `setting_value` TEXT,
    `setting_type` VARCHAR(30) NOT NULL DEFAULT 'text',
    `description` VARCHAR(500) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`setting_key`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROJECTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid` VARCHAR(36) NOT NULL UNIQUE,
    `project_name` VARCHAR(255) NOT NULL,
    `primary_domain` VARCHAR(255) NOT NULL,
    `normalized_domain` VARCHAR(255) NOT NULL,
    `report_slug` VARCHAR(255) NOT NULL UNIQUE,
    `project_status` ENUM('active', 'archived', 'paused') NOT NULL DEFAULT 'active',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX `idx_uuid` (`uuid`),
    INDEX `idx_status` (`project_status`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_report_slug` (`report_slug`),
    INDEX `idx_normalized_domain` (`normalized_domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROJECT ACTIVITY LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `project_activity_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `activity_type` ENUM('created', 'updated', 'archived', 'restored', 'status_changed') NOT NULL,
    `activity_description` TEXT,
    `metadata` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_activity_type` (`activity_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROJECT SITEMAPS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `project_sitemaps` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT UNSIGNED NOT NULL,
    `sitemap_url` VARCHAR(500) NOT NULL,
    `sitemap_type` ENUM('sitemap', 'sitemap_index', 'nested_sitemap') NOT NULL DEFAULT 'sitemap',
    `status` ENUM('discovered', 'validated', 'invalid', 'unreachable') NOT NULL DEFAULT 'discovered',
    `sitemap_status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active' COMMENT 'Active or disabled for imports',
    `discovered_from` VARCHAR(255) NULL COMMENT 'Source of discovery: robots.txt, manual, common_location',
    `total_urls` INT UNSIGNED DEFAULT 0,
    `last_fetched_at` TIMESTAMP NULL,
    `last_imported_at` TIMESTAMP NULL COMMENT 'Last time URLs were imported from this sitemap',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_sitemap_per_project` (`project_id`, `sitemap_url`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_sitemap_type` (`sitemap_type`),
    INDEX `idx_sitemap_status` (`sitemap_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROJECT PAGES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `project_pages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT UNSIGNED NOT NULL,
    `sitemap_id` INT UNSIGNED NULL COMMENT 'Source sitemap, NULL if manually added',
    `original_url` VARCHAR(2000) NOT NULL COMMENT 'Original URL as found in sitemap',
    `normalized_url` VARCHAR(2000) NOT NULL COMMENT 'Normalized URL for comparison',
    `url_hash` CHAR(64) NOT NULL COMMENT 'SHA256 hash of normalized URL for fast lookups',
    `page_status` ENUM('pending', 'processing', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    `url_status` ENUM('active', 'disabled', 'removed', 'pending_audit') NOT NULL DEFAULT 'active' COMMENT 'URL lifecycle status',
    `first_discovered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sitemap_id`) REFERENCES `project_sitemaps`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_page_per_project` (`project_id`, `url_hash`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_sitemap_id` (`sitemap_id`),
    INDEX `idx_url_hash` (`url_hash`),
    INDEX `idx_page_status` (`page_status`),
    INDEX `idx_url_status` (`url_status`),
    INDEX `idx_normalized_url` (`normalized_url`(255)),
    INDEX `idx_project_url_status` (`project_id`, `url_status`, `id`),
    INDEX `idx_project_url_status_2` (`project_id`, `url_status`, `page_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAGE AUDIT RESULTS TABLE
-- Includes all columns from base + Phase 4 Part 3 enhancements
-- ============================================
CREATE TABLE IF NOT EXISTS `page_audit_results` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_page_id` INT UNSIGNED NOT NULL,
    `audit_run_id` INT UNSIGNED NULL COMMENT 'Reference to audit run',
    `module_name` VARCHAR(50) NOT NULL COMMENT 'h1_audit, meta_audit, noindex_audit, etc.',
    `result_status` ENUM('PASS', 'WARNING', 'FAIL', 'ERROR', 'NOT_RUN') NOT NULL DEFAULT 'NOT_RUN',
    `result_value` TEXT NULL COMMENT 'Primary result value (e.g., H1 text, meta description)',
    `result_summary` VARCHAR(500) NULL COMMENT 'Human-readable summary',
    `error_message` TEXT NULL COMMENT 'Error message if status is ERROR',
    `execution_time_ms` INT UNSIGNED NULL COMMENT 'Execution time in milliseconds',
    `raw_data_json` JSON NULL COMMENT 'Complete audit data in JSON format',
    `checked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_page_id`) REFERENCES `project_pages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`audit_run_id`) REFERENCES `audit_runs`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_module_per_page` (`project_page_id`, `module_name`),
    INDEX `idx_project_page_id` (`project_page_id`),
    INDEX `idx_module_name` (`module_name`),
    INDEX `idx_result_status` (`result_status`),
    INDEX `idx_checked_at` (`checked_at`),
    INDEX `idx_audit_run_id` (`audit_run_id`),
    INDEX `idx_project_status_filter` (`project_page_id`, `result_status`, `module_name`),
    INDEX `idx_checked_at_status` (`checked_at`, `result_status`),
    INDEX `idx_page_module_status` (`project_page_id`, `module_name`, `result_status`),
    INDEX `idx_module_status` (`module_name`, `result_status`),
    INDEX `idx_page_module_cover` (`project_page_id`, `module_name`, `result_status`, `result_value`(100), `checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUDIT RUNS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `audit_runs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid` VARCHAR(36) NOT NULL UNIQUE,
    `project_id` INT UNSIGNED NOT NULL,
    `run_type` ENUM('manual', 'retry', 'preset', 'scheduled') NOT NULL DEFAULT 'manual',
    `selected_modules` JSON NOT NULL COMMENT 'Array of module names that were selected',
    `total_pages` INT UNSIGNED NOT NULL DEFAULT 0,
    `processed_pages` INT UNSIGNED NOT NULL DEFAULT 0,
    `success_pages` INT UNSIGNED NOT NULL DEFAULT 0,
    `failed_pages` INT UNSIGNED NOT NULL DEFAULT 0,
    `run_status` ENUM('pending', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    `started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `execution_time_seconds` INT UNSIGNED NULL COMMENT 'Total execution time in seconds',
    `error_message` TEXT NULL,
    `metadata` JSON NULL COMMENT 'Additional run metadata',
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_uuid` (`uuid`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_run_type` (`run_type`),
    INDEX `idx_run_status` (`run_status`),
    INDEX `idx_started_at` (`started_at`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAGE AUDIT HISTORY TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `page_audit_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_page_id` INT UNSIGNED NOT NULL,
    `audit_run_id` INT UNSIGNED NULL COMMENT 'Reference to audit run that caused this change',
    `module_name` VARCHAR(50) NOT NULL,
    `previous_status` ENUM('PASS', 'WARNING', 'FAIL', 'ERROR', 'NOT_RUN') NULL,
    `new_status` ENUM('PASS', 'WARNING', 'FAIL', 'ERROR', 'NOT_RUN') NOT NULL,
    `previous_value` TEXT NULL,
    `new_value` TEXT NULL,
    `previous_data` JSON NULL COMMENT 'Previous raw_data_json snapshot',
    `new_data` JSON NULL COMMENT 'New raw_data_json snapshot',
    `change_summary` VARCHAR(500) NULL COMMENT 'Human-readable summary of what changed',
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_page_id`) REFERENCES `project_pages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`audit_run_id`) REFERENCES `audit_runs`(`id`) ON DELETE SET NULL,
    INDEX `idx_project_page_id` (`project_page_id`),
    INDEX `idx_audit_run_id` (`audit_run_id`),
    INDEX `idx_module_name` (`module_name`),
    INDEX `idx_new_status` (`new_status`),
    INDEX `idx_changed_at` (`changed_at`),
    INDEX `idx_page_module` (`project_page_id`, `module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- WORD AUDIT SEARCHES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `word_audit_searches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT UNSIGNED NOT NULL,
    `keyword` VARCHAR(500) NOT NULL COMMENT 'Single keyword or phrase searched',
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_keyword` (`keyword`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUDIT JOBS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `audit_jobs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `audit_run_id` INT UNSIGNED NULL COMMENT 'Reference to parent audit run',
    `project_id` INT UNSIGNED NOT NULL,
    `job_type` VARCHAR(50) NOT NULL DEFAULT 'audit' COMMENT 'audit, retry, scheduled',
    `selected_modules` JSON NOT NULL COMMENT 'Array of module keys to execute',
    `module_params` JSON NULL COMMENT 'Extra params per module (e.g. keywords)',
    `status` ENUM('queued', 'processing', 'paused', 'interrupted', 'completed', 'failed') NOT NULL DEFAULT 'queued',
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1=highest, 10=lowest',
    `total_pages` INT UNSIGNED NOT NULL DEFAULT 0,
    `processed_pages` INT UNSIGNED NOT NULL DEFAULT 0,
    `success_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_retries` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `batch_size` INT UNSIGNED NOT NULL DEFAULT 25 COMMENT 'Pages per batch',
    `last_page_id` INT UNSIGNED NULL COMMENT 'Last processed page ID for resumption',
    `error_message` TEXT NULL,
    `started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `last_heartbeat` TIMESTAMP NULL COMMENT 'Updated during processing to detect interruptions',
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`audit_run_id`) REFERENCES `audit_runs`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_priority_status` (`priority`, `status`),
    INDEX `idx_last_heartbeat` (`last_heartbeat`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_audit_run_id` (`audit_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUDIT JOB LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `audit_job_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_id` INT UNSIGNED NOT NULL,
    `log_level` ENUM('info', 'warning', 'error', 'debug') NOT NULL DEFAULT 'info',
    `message` TEXT NOT NULL,
    `context` JSON NULL COMMENT 'Additional structured data',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_id`) REFERENCES `audit_jobs`(`id`) ON DELETE CASCADE,
    INDEX `idx_job_id` (`job_id`),
    INDEX `idx_log_level` (`log_level`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAGE RETRY TRACKING TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `page_retry_tracking` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_page_id` INT UNSIGNED NOT NULL,
    `module_name` VARCHAR(50) NOT NULL,
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_retries` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `error_type` VARCHAR(50) NULL COMMENT 'timeout, connection, invalid_response, malformed_html, processing, unknown',
    `last_error` TEXT NULL,
    `last_attempt_at` TIMESTAMP NULL,
    `status` ENUM('retry_pending', 'retrying', 'retry_failed', 'retry_exhausted', 'resolved') NOT NULL DEFAULT 'retry_pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_page_id`) REFERENCES `project_pages`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_page_module_retry` (`project_page_id`, `module_name`),
    INDEX `idx_status` (`status`),
    INDEX `idx_module_name` (`module_name`),
    INDEX `idx_error_type` (`error_type`),
    INDEX `idx_project_page` (`project_page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- REPORTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `reports` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT UNSIGNED NOT NULL,
    `report_slug` VARCHAR(255) NOT NULL UNIQUE COMMENT 'URL-friendly slug e.g. client-name-a81x',
    `visibility` ENUM('public', 'private') NOT NULL DEFAULT 'private',
    `last_generated_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_project_report` (`project_id`),
    INDEX `idx_report_slug` (`report_slug`),
    INDEX `idx_visibility` (`visibility`),
    INDEX `idx_slug_visibility` (`report_slug`, `visibility`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAGE MANUAL OVERRIDES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `page_manual_overrides` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_page_id` INT UNSIGNED NOT NULL,
    `module_name` VARCHAR(50) NOT NULL COMMENT 'h1_audit, meta_audit, noindex_audit, etc.',
    `original_status` ENUM('PASS', 'WARNING', 'FAIL', 'ERROR') NOT NULL COMMENT 'Original automated audit result',
    `override_status` ENUM('ignored', 'fixed', 'accepted', 'false_positive', 'needs_review') NOT NULL,
    `notes` TEXT NULL COMMENT 'Admin notes explaining the override',
    `updated_by` INT UNSIGNED NOT NULL COMMENT 'User who made the override',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_page_id`) REFERENCES `project_pages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `unique_override_per_module` (`project_page_id`, `module_name`),
    INDEX `idx_project_page_id` (`project_page_id`),
    INDEX `idx_module_name` (`module_name`),
    INDEX `idx_override_status` (`override_status`),
    INDEX `idx_updated_by` (`updated_by`),
    INDEX `idx_page_module` (`project_page_id`, `module_name`, `override_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- OVERRIDE HISTORY TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `override_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `override_id` INT UNSIGNED NOT NULL,
    `project_page_id` INT UNSIGNED NOT NULL,
    `module_name` VARCHAR(50) NOT NULL,
    `old_status` VARCHAR(50) NULL COMMENT 'Previous override status (NULL if first override)',
    `new_status` VARCHAR(50) NOT NULL COMMENT 'New override status',
    `notes` TEXT NULL,
    `changed_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`override_id`) REFERENCES `page_manual_overrides`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`project_page_id`) REFERENCES `project_pages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX `idx_override_id` (`override_id`),
    INDEX `idx_project_page_id` (`project_page_id`),
    INDEX `idx_changed_by` (`changed_by`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NOTIFICATIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL COMMENT 'NULL means broadcast to all',
    `type` VARCHAR(50) NOT NULL COMMENT 'audit_complete, project_created, error, etc.',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NULL,
    `link` VARCHAR(500) NULL COMMENT 'Optional action URL',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_user_read_created` (`user_id`, `is_read`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ACTIVITY LOG TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `project_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL COMMENT 'project_created, audit_started, audit_completed, etc.',
    `description` TEXT NULL,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_created_user` (`created_at` DESC, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VIEWS
-- ============================================

-- Project Audit Summary View
CREATE OR REPLACE VIEW `project_audit_summary` AS
SELECT 
    `p`.`id` AS `project_id`,
    `p`.`uuid` AS `project_uuid`,
    `p`.`project_name`,
    COUNT(DISTINCT `pp`.`id`) AS `total_pages`,
    COUNT(DISTINCT CASE WHEN `par`.`result_status` = 'PASS' THEN `pp`.`id` END) AS `passed_pages`,
    COUNT(DISTINCT CASE WHEN `par`.`result_status` = 'WARNING' THEN `pp`.`id` END) AS `warning_pages`,
    COUNT(DISTINCT CASE WHEN `par`.`result_status` = 'FAIL' THEN `pp`.`id` END) AS `failed_pages`,
    COUNT(DISTINCT CASE WHEN `par`.`result_status` = 'ERROR' THEN `pp`.`id` END) AS `error_pages`,
    COUNT(DISTINCT CASE WHEN `par`.`result_status` = 'NOT_RUN' OR `par`.`id` IS NULL THEN `pp`.`id` END) AS `not_audited_pages`,
    MAX(`par`.`checked_at`) AS `last_audit_date`,
    (SELECT COUNT(*) FROM `audit_runs` `ar` WHERE `ar`.`project_id` = `p`.`id`) AS `total_audit_runs`
FROM `projects` `p`
LEFT JOIN `project_pages` `pp` ON `pp`.`project_id` = `p`.`id`
LEFT JOIN `page_audit_results` `par` ON `par`.`project_page_id` = `pp`.`id`
WHERE `p`.`project_status` = 'active'
GROUP BY `p`.`id`, `p`.`uuid`, `p`.`project_name`;

-- Module Audit Summary View
CREATE OR REPLACE VIEW `module_audit_summary` AS
SELECT 
    `p`.`id` AS `project_id`,
    `p`.`uuid` AS `project_uuid`,
    `par`.`module_name`,
    COUNT(*) AS `total_audits`,
    COUNT(CASE WHEN `par`.`result_status` = 'PASS' THEN 1 END) AS `pass_count`,
    COUNT(CASE WHEN `par`.`result_status` = 'WARNING' THEN 1 END) AS `warning_count`,
    COUNT(CASE WHEN `par`.`result_status` = 'FAIL' THEN 1 END) AS `fail_count`,
    COUNT(CASE WHEN `par`.`result_status` = 'ERROR' THEN 1 END) AS `error_count`,
    MAX(`par`.`checked_at`) AS `last_checked`
FROM `projects` `p`
INNER JOIN `project_pages` `pp` ON `pp`.`project_id` = `p`.`id`
INNER JOIN `page_audit_results` `par` ON `par`.`project_page_id` = `pp`.`id`
WHERE `p`.`project_status` = 'active'
GROUP BY `p`.`id`, `p`.`uuid`, `par`.`module_name`;

-- ============================================
-- DEFAULT DATA
-- ============================================

-- Default Super Admin
-- Password hash for 'admin123' (bcrypt cost 10)
-- IMPORTANT: Change this password immediately after first login!
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES 
('Super Admin', 'admin@auditwithgufran.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Default Settings (General)
INSERT INTO `settings` (`setting_key`, `category`, `setting_value`, `setting_type`, `description`) VALUES 
('app_name', 'general', 'Audit withGufran', 'text', 'Application name'),
('app_timezone', 'general', 'UTC', 'text', 'Application timezone'),
('company_name', 'general', 'Codezyners', 'text', 'Company name shown in reports and branding'),
('default_theme', 'general', 'light', 'select', 'Default application theme'),
('date_format', 'general', 'M d, Y', 'text', 'Date display format'),
('time_format', 'general', 'h:i A', 'text', 'Time display format')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;

-- Default Settings (System)
INSERT INTO `settings` (`setting_key`, `category`, `setting_value`, `setting_type`, `description`) VALUES 
('maintenance_mode', 'system', '0', 'boolean', 'Maintenance mode toggle'),
('system_version', 'system', '1.8.0', 'text', 'Current system version')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;

-- Default Settings (Audit)
INSERT INTO `settings` (`setting_key`, `category`, `setting_value`, `setting_type`, `description`) VALUES 
('audit_batch_size', 'audit', '10', 'number', 'Number of pages to audit per batch'),
('audit_max_retries', 'audit', '3', 'number', 'Maximum retry attempts for failed audits'),
('audit_request_timeout', 'audit', '30', 'number', 'HTTP request timeout in seconds'),
('audit_user_agent', 'audit', 'AuditWithGufran/1.0', 'text', 'User-Agent string for HTTP requests'),
('audit_default_modules', 'audit', 'h1,meta,noindex,faq,lorem,staging,word', 'text', 'Default enabled modules (comma-separated keys)')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;

-- Default Settings (Reports)
INSERT INTO `settings` (`setting_key`, `category`, `setting_value`, `setting_type`, `description`) VALUES 
('report_default_visibility', 'reports', 'private', 'select', 'Default visibility for new reports'),
('report_default_title', 'reports', 'SEO Audit Report', 'text', 'Default title shown on reports'),
('report_branding_text', 'reports', 'Audit withGufran', 'text', 'Branding text in report header'),
('report_footer_text', 'reports', 'Powered by Codezyners', 'text', 'Footer text on reports'),
('report_enable_pdf', 'reports', '1', 'boolean', 'Enable PDF export on public reports'),
('report_enable_csv', 'reports', '1', 'boolean', 'Enable CSV export on public reports')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;

-- Default Settings (Security)
INSERT INTO `settings` (`setting_key`, `category`, `setting_value`, `setting_type`, `description`) VALUES 
('session_timeout', 'security', '7200', 'number', 'Session timeout in seconds'),
('max_login_attempts', 'security', '5', 'number', 'Maximum failed login attempts before lockout'),
('password_min_length', 'security', '8', 'number', 'Minimum password length'),
('force_https', 'security', '0', 'boolean', 'Force HTTPS connections')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- Installation Complete
-- ============================================
-- All tables, indexes, constraints, views, and default data installed.
-- 
-- Tables created:
--   users, settings, projects, project_activity_logs, project_sitemaps,
--   project_pages, page_audit_results, audit_runs, page_audit_history,
--   word_audit_searches, audit_jobs, audit_job_logs, page_retry_tracking,
--   reports, page_manual_overrides, override_history, notifications, activity_log
-- 
-- Views created:
--   project_audit_summary, module_audit_summary
-- 
-- Default accounts:
--   Super Admin: admin@auditwithgufran.com / admin123
--   (Change password immediately after first login)
-- 
-- Next steps:
--   1. Configure .env with database credentials
--   2. Run the application and log in with the super admin account
--   3. Change the default password