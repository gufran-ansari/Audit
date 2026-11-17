    -- ============================================================
    -- Audit withGufran complete first-boot schema
    -- Generated from the PHP Models, Repositories, Services, and
    -- existing migrations in this project.
    -- ============================================================

    SET NAMES utf8mb4;
    SET time_zone = '+00:00';
    SET FOREIGN_KEY_CHECKS = 0;

    -- CREATE DATABASE IF NOT EXISTS `u989048027_audit`
    --     CHARACTER SET utf8mb4
    --     COLLATE utf8mb4_unicode_ci;

    -- USE `u989048027_audit`;

    -- Drop in dependency order so re-importing into an empty/dev DB is deterministic.
    DROP TABLE IF EXISTS `audit_job_logs`;
    DROP TABLE IF EXISTS `page_retry_tracking`;
    DROP TABLE IF EXISTS `override_history`;
    DROP TABLE IF EXISTS `page_manual_overrides`;
    DROP TABLE IF EXISTS `page_audit_history`;
    DROP TABLE IF EXISTS `page_audit_results`;
    DROP TABLE IF EXISTS `audit_jobs`;
    DROP TABLE IF EXISTS `audit_runs`;
    DROP TABLE IF EXISTS `word_audit_searches`;
    DROP TABLE IF EXISTS `notifications`;
    DROP TABLE IF EXISTS `activity_log`;
    DROP TABLE IF EXISTS `reports`;
    DROP TABLE IF EXISTS `project_pages`;
    DROP TABLE IF EXISTS `project_sitemaps`;
    DROP TABLE IF EXISTS `project_activity_logs`;
    DROP TABLE IF EXISTS `projects`;
    DROP TABLE IF EXISTS `settings`;
    DROP TABLE IF EXISTS `users`;

    SET FOREIGN_KEY_CHECKS = 1;

    CREATE TABLE `users` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
        `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_user_email` (`email`),
        KEY `idx_email` (`email`),
        KEY `idx_role` (`role`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `settings` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `setting_key` VARCHAR(255) NOT NULL,
        `category` VARCHAR(50) NOT NULL DEFAULT 'general',
        `setting_value` TEXT NULL,
        `setting_type` VARCHAR(30) NOT NULL DEFAULT 'text',
        `description` VARCHAR(500) NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_setting_key` (`setting_key`),
        KEY `idx_key` (`setting_key`),
        KEY `idx_category` (`category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `projects` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` VARCHAR(36) NOT NULL,
        `project_name` VARCHAR(255) NOT NULL,
        `primary_domain` VARCHAR(255) NOT NULL,
        `normalized_domain` VARCHAR(255) NOT NULL,
        `report_slug` VARCHAR(255) NOT NULL,
        `project_status` ENUM('active', 'archived', 'paused') NOT NULL DEFAULT 'active',
        `created_by` INT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_project_uuid` (`uuid`),
        UNIQUE KEY `unique_project_report_slug` (`report_slug`),
        KEY `idx_uuid` (`uuid`),
        KEY `idx_status` (`project_status`),
        KEY `idx_created_by` (`created_by`),
        KEY `idx_report_slug` (`report_slug`),
        KEY `idx_normalized_domain` (`normalized_domain`),
        CONSTRAINT `fk_projects_created_by`
            FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `project_activity_logs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `activity_type` ENUM('created', 'updated', 'archived', 'restored', 'status_changed') NOT NULL,
        `activity_description` TEXT NULL,
        `metadata` JSON NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_project_id` (`project_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_activity_type` (`activity_type`),
        KEY `idx_created_at` (`created_at`),
        CONSTRAINT `fk_project_activity_logs_project`
            FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_project_activity_logs_user`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `project_sitemaps` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_id` INT UNSIGNED NOT NULL,
        `sitemap_url` VARCHAR(500) NOT NULL,
        `sitemap_type` ENUM('sitemap', 'sitemap_index', 'nested_sitemap') NOT NULL DEFAULT 'sitemap',
        `status` ENUM('discovered', 'validated', 'invalid', 'unreachable') NOT NULL DEFAULT 'discovered',
        `sitemap_status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
        `discovered_from` VARCHAR(255) NULL,
        `total_urls` INT UNSIGNED NULL DEFAULT 0,
        `last_fetched_at` TIMESTAMP NULL DEFAULT NULL,
        `last_imported_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_sitemap_per_project` (`project_id`, `sitemap_url`),
        KEY `idx_project_id` (`project_id`),
        KEY `idx_status` (`status`),
        KEY `idx_sitemap_type` (`sitemap_type`),
        KEY `idx_sitemap_status` (`sitemap_status`),
        CONSTRAINT `fk_project_sitemaps_project`
            FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `project_pages` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_id` INT UNSIGNED NOT NULL,
        `sitemap_id` INT UNSIGNED NULL,
        `original_url` VARCHAR(2000) NOT NULL,
        `normalized_url` VARCHAR(2000) NOT NULL,
        `url_hash` CHAR(64) NOT NULL,
        `page_status` ENUM('pending', 'processing', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
        `url_status` ENUM('active', 'disabled', 'removed', 'pending_audit') NOT NULL DEFAULT 'active',
        `first_discovered_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `last_seen_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_page_per_project` (`project_id`, `url_hash`),
        KEY `idx_project_id` (`project_id`),
        KEY `idx_sitemap_id` (`sitemap_id`),
        KEY `idx_url_hash` (`url_hash`),
        KEY `idx_page_status` (`page_status`),
        KEY `idx_url_status` (`url_status`),
        KEY `idx_normalized_url` (`normalized_url`(255)),
        KEY `idx_project_url_status` (`project_id`, `url_status`, `id`),
        CONSTRAINT `fk_project_pages_project`
            FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_project_pages_sitemap`
            FOREIGN KEY (`sitemap_id`) REFERENCES `project_sitemaps` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `audit_runs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` VARCHAR(36) NOT NULL,
        `project_id` INT UNSIGNED NOT NULL,
        `run_type` ENUM('manual', 'retry', 'preset', 'scheduled') NOT NULL DEFAULT 'manual',
        `selected_modules` JSON NOT NULL,
        `total_pages` INT UNSIGNED NOT NULL DEFAULT 0,
        `processed_pages` INT UNSIGNED NOT NULL DEFAULT 0,
        `success_pages` INT UNSIGNED NOT NULL DEFAULT 0,
        `failed_pages` INT UNSIGNED NOT NULL DEFAULT 0,
        `run_status` ENUM('pending', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
        `started_at` TIMESTAMP NULL DEFAULT NULL,
        `completed_at` TIMESTAMP NULL DEFAULT NULL,
        `execution_time_seconds` INT UNSIGNED NULL,
        `error_message` TEXT NULL,
        `metadata` JSON NULL,
        `created_by` INT UNSIGNED NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_audit_run_uuid` (`uuid`),
        KEY `idx_uuid` (`uuid`),
        KEY `idx_project_id` (`project_id`),
        KEY `idx_run_type` (`run_type`),
        KEY `idx_run_status` (`run_status`),
        KEY `idx_started_at` (`started_at`),
        KEY `idx_created_by` (`created_by`),
        CONSTRAINT `fk_audit_runs_project`
            FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_audit_runs_created_by`
            FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `audit_jobs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `audit_run_id` INT UNSIGNED NULL,
        `project_id` INT UNSIGNED NOT NULL,
        `job_type` VARCHAR(50) NOT NULL DEFAULT 'audit',
        `selected_modules` JSON NOT NULL,
        `module_params` JSON NULL,
        `status` ENUM('queued', 'processing', 'paused', 'interrupted', 'completed', 'failed') NOT NULL DEFAULT 'queued',
        `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5,
        `total_pages` INT UNSIGNED NOT NULL DEFAULT 0,
        `processed_pages` INT UNSIGNED NOT NULL DEFAULT 0,
        `success_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `max_retries` TINYINT UNSIGNED NOT NULL DEFAULT 3,
        `batch_size` INT UNSIGNED NOT NULL DEFAULT 25,
        `last_page_id` INT UNSIGNED NULL,
        `error_message` TEXT NULL,
        `started_at` TIMESTAMP NULL DEFAULT NULL,
        `completed_at` TIMESTAMP NULL DEFAULT NULL,
        `last_heartbeat` TIMESTAMP NULL DEFAULT NULL,
        `created_by` INT UNSIGNED NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_audit_run_id` (`audit_run_id`),
        KEY `idx_status` (`status`),
        KEY `idx_project_id` (`project_id`),
        KEY `idx_priority_status` (`priority`, `status`),
        KEY `idx_last_heartbeat` (`last_heartbeat`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_created_by` (`created_by`),
        CONSTRAINT `fk_audit_jobs_audit_run`
            FOREIGN KEY (`audit_run_id`) REFERENCES `audit_runs` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_audit_jobs_project`
            FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_audit_jobs_created_by`
            FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `page_audit_results` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_page_id` INT UNSIGNED NOT NULL,
        `audit_run_id` INT UNSIGNED NULL,
        `module_name` VARCHAR(50) NOT NULL,
        `result_status` ENUM('PASS', 'WARNING', 'FAIL', 'ERROR', 'NOT_RUN') NOT NULL,
        `result_value` TEXT NULL,
        `result_summary` VARCHAR(500) NULL,
        `error_message` TEXT NULL,
        `execution_time_ms` INT UNSIGNED NULL,
        `raw_data_json` JSON NULL,
        `checked_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_module_per_page` (`project_page_id`, `module_name`),
        KEY `idx_project_page_id` (`project_page_id`),
        KEY `idx_audit_run_id` (`audit_run_id`),
        KEY `idx_module_name` (`module_name`),
        KEY `idx_result_status` (`result_status`),
        KEY `idx_checked_at` (`checked_at`),
        KEY `idx_project_status_filter` (`project_page_id`, `result_status`, `module_name`),
        KEY `idx_checked_at_status` (`checked_at`, `result_status`),
        KEY `idx_page_module_status` (`project_page_id`, `module_name`, `result_status`),
        KEY `idx_module_status` (`module_name`, `result_status`),
        KEY `idx_page_module_cover` (`project_page_id`, `module_name`, `result_status`, `result_value`(100), `checked_at`),
        CONSTRAINT `fk_page_audit_results_page`
            FOREIGN KEY (`project_page_id`) REFERENCES `project_pages` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_page_audit_results_audit_run`
            FOREIGN KEY (`audit_run_id`) REFERENCES `audit_runs` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `page_audit_history` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_page_id` INT UNSIGNED NOT NULL,
        `audit_run_id` INT UNSIGNED NULL,
        `module_name` VARCHAR(50) NOT NULL,
        `previous_status` ENUM('PASS', 'WARNING', 'FAIL', 'ERROR', 'NOT_RUN') NULL,
        `new_status` ENUM('PASS', 'WARNING', 'FAIL', 'ERROR', 'NOT_RUN') NOT NULL,
        `previous_value` TEXT NULL,
        `new_value` TEXT NULL,
        `previous_data` JSON NULL,
        `new_data` JSON NULL,
        `change_summary` VARCHAR(500) NULL,
        `changed_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_project_page_id` (`project_page_id`),
        KEY `idx_audit_run_id` (`audit_run_id`),
        KEY `idx_module_name` (`module_name`),
        KEY `idx_new_status` (`new_status`),
        KEY `idx_changed_at` (`changed_at`),
        KEY `idx_page_module` (`project_page_id`, `module_name`),
        CONSTRAINT `fk_page_audit_history_page`
            FOREIGN KEY (`project_page_id`) REFERENCES `project_pages` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_page_audit_history_audit_run`
            FOREIGN KEY (`audit_run_id`) REFERENCES `audit_runs` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `page_manual_overrides` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_page_id` INT UNSIGNED NOT NULL,
        `module_name` VARCHAR(50) NOT NULL,
        `original_status` ENUM('PASS', 'WARNING', 'FAIL', 'ERROR') NOT NULL,
        `override_status` ENUM('ignored', 'fixed', 'accepted', 'false_positive', 'needs_review') NOT NULL,
        `notes` TEXT NULL,
        `updated_by` INT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_override_per_module` (`project_page_id`, `module_name`),
        KEY `idx_project_page_id` (`project_page_id`),
        KEY `idx_module_name` (`module_name`),
        KEY `idx_override_status` (`override_status`),
        KEY `idx_updated_by` (`updated_by`),
        KEY `idx_page_module` (`project_page_id`, `module_name`, `override_status`),
        CONSTRAINT `fk_page_manual_overrides_page`
            FOREIGN KEY (`project_page_id`) REFERENCES `project_pages` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_page_manual_overrides_updated_by`
            FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `override_history` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `override_id` INT UNSIGNED NOT NULL,
        `project_page_id` INT UNSIGNED NOT NULL,
        `module_name` VARCHAR(50) NOT NULL,
        `old_status` VARCHAR(50) NULL,
        `new_status` VARCHAR(50) NOT NULL,
        `notes` TEXT NULL,
        `changed_by` INT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_override_id` (`override_id`),
        KEY `idx_project_page_id` (`project_page_id`),
        KEY `idx_changed_by` (`changed_by`),
        KEY `idx_created_at` (`created_at`),
        CONSTRAINT `fk_override_history_override`
            FOREIGN KEY (`override_id`) REFERENCES `page_manual_overrides` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_override_history_page`
            FOREIGN KEY (`project_page_id`) REFERENCES `project_pages` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_override_history_changed_by`
            FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `page_retry_tracking` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_page_id` INT UNSIGNED NOT NULL,
        `module_name` VARCHAR(50) NOT NULL,
        `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `max_retries` TINYINT UNSIGNED NOT NULL DEFAULT 3,
        `error_type` VARCHAR(50) NULL,
        `last_error` TEXT NULL,
        `last_attempt_at` TIMESTAMP NULL DEFAULT NULL,
        `status` ENUM('retry_pending', 'retrying', 'retry_failed', 'retry_exhausted', 'resolved') NOT NULL DEFAULT 'retry_pending',
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_page_module_retry` (`project_page_id`, `module_name`),
        KEY `idx_status` (`status`),
        KEY `idx_module_name` (`module_name`),
        KEY `idx_error_type` (`error_type`),
        KEY `idx_project_page` (`project_page_id`),
        CONSTRAINT `fk_page_retry_tracking_page`
            FOREIGN KEY (`project_page_id`) REFERENCES `project_pages` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `audit_job_logs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `job_id` INT UNSIGNED NOT NULL,
        `log_level` ENUM('info', 'warning', 'error', 'debug') NOT NULL DEFAULT 'info',
        `message` TEXT NOT NULL,
        `context` JSON NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_job_id` (`job_id`),
        KEY `idx_log_level` (`log_level`),
        KEY `idx_created_at` (`created_at`),
        CONSTRAINT `fk_audit_job_logs_job`
            FOREIGN KEY (`job_id`) REFERENCES `audit_jobs` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `word_audit_searches` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_id` INT UNSIGNED NOT NULL,
        `keyword` VARCHAR(500) NOT NULL,
        `created_by` INT UNSIGNED NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_project_id` (`project_id`),
        KEY `idx_keyword` (`keyword`),
        KEY `idx_created_at` (`created_at`),
        CONSTRAINT `fk_word_audit_searches_project`
            FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_word_audit_searches_created_by`
            FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `reports` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_id` INT UNSIGNED NOT NULL,
        `report_slug` VARCHAR(255) NOT NULL,
        `visibility` ENUM('public', 'private') NOT NULL DEFAULT 'private',
        `last_generated_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_report_slug` (`report_slug`),
        UNIQUE KEY `unique_project_report` (`project_id`),
        KEY `idx_report_slug` (`report_slug`),
        KEY `idx_visibility` (`visibility`),
        KEY `idx_slug_visibility` (`report_slug`, `visibility`),
        CONSTRAINT `fk_reports_project`
            FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `notifications` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NULL,
        `type` VARCHAR(50) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT NULL,
        `link` VARCHAR(500) NULL,
        `is_read` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_is_read` (`is_read`),
        KEY `idx_type` (`type`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_user_read_created` (`user_id`, `is_read`, `created_at`),
        CONSTRAINT `fk_notifications_user`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `activity_log` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NULL,
        `project_id` INT UNSIGNED NULL,
        `action` VARCHAR(100) NOT NULL,
        `description` TEXT NULL,
        `metadata` JSON NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_project_id` (`project_id`),
        KEY `idx_action` (`action`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_created_user` (`created_at`, `user_id`),
        CONSTRAINT `fk_activity_log_user`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_activity_log_project`
            FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- ============================================================
    -- First-boot data
    -- Default credentials:
    --   Email: admin@auditwithgufran.com
    --   Password: admin123
    -- Change this password immediately after first login.
    -- ============================================================

    INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES
    ('Super Admin', 'admin@auditwithgufran.com', '$2y$10$NHMFTio4YZr/.2IDELLD..tmiNtYiiEIS5Qm2sGG2C7mo09D9dTMy', 'super_admin', 'active');

    INSERT INTO `settings` (`setting_key`, `category`, `setting_value`, `setting_type`, `description`) VALUES
    ('app_name', 'general', 'Audit withGufran', 'text', 'Application name'),
    ('app_timezone', 'general', 'UTC', 'text', 'Application timezone'),
    ('company_name', 'general', 'Codezyners', 'text', 'Company name shown in reports and branding'),
    ('default_theme', 'general', 'light', 'select', 'Default application theme'),
    ('date_format', 'general', 'M d, Y', 'text', 'Date display format'),
    ('time_format', 'general', 'h:i A', 'text', 'Time display format'),
    ('audit_batch_size', 'audit', '10', 'number', 'Number of pages to audit per batch'),
    ('audit_max_retries', 'audit', '3', 'number', 'Maximum retry attempts for failed audits'),
    ('audit_request_timeout', 'audit', '30', 'number', 'HTTP request timeout in seconds'),
    ('audit_user_agent', 'audit', 'AuditWithGufran/1.0', 'text', 'User-Agent string for HTTP requests'),
    ('audit_default_modules', 'audit', 'h1,meta,noindex,faq,lorem,staging,word', 'text', 'Default enabled modules (comma-separated keys)'),
    ('report_default_visibility', 'reports', 'private', 'select', 'Default visibility for new reports'),
    ('report_default_title', 'reports', 'SEO Audit Report', 'text', 'Default title shown on reports'),
    ('report_branding_text', 'reports', 'Audit withGufran', 'text', 'Branding text in report header'),
    ('report_footer_text', 'reports', 'Powered by Codezyners', 'text', 'Footer text on reports'),
    ('report_enable_pdf', 'reports', '1', 'boolean', 'Enable PDF export on public reports'),
    ('report_enable_csv', 'reports', '1', 'boolean', 'Enable CSV export on public reports'),
    ('session_timeout', 'security', '7200', 'number', 'Session timeout in seconds'),
    ('max_login_attempts', 'security', '5', 'number', 'Maximum failed login attempts before lockout'),
    ('password_min_length', 'security', '8', 'number', 'Minimum password length'),
    ('force_https', 'security', '0', 'boolean', 'Force HTTPS connections (future)'),
    ('maintenance_mode', 'system', '0', 'boolean', 'Maintenance mode flag'),
    ('system_version', 'system', '1.8.0', 'text', 'Current system version');
