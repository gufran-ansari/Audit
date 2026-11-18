<?php
/**
 * Migration: Create activity_log table
 *
 * Required by ActivityLogService (added in commit fc37aa9, phase 14.7).
 * The table exists in install.sql but is NOT created by the migration
 * sequence listed in DEPLOY.md, so existing deployments are missing it.
 *
 * Run this once against the live database via phpMyAdmin SQL tab,
 * or via: mysql -h localhost -u <user> -p <db> < migrations_phase14_part1.sql
 */

require __DIR__ . '/../app/bootstrap.php';

use Database\Database;

$db = Database::getInstance();

// Idempotent: skip if the table already exists
$exists = $db->query(
    "SELECT COUNT(*) AS cnt FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'activity_log'"
);

if ($exists && (int) ($exists[0]['cnt'] ?? 0) > 0) {
    echo "activity_log table already exists. Skipping.\n";
    exit(0);
}

$sql = <<<SQL
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
SQL;

$db->execute($sql);
echo "activity_log table created successfully.\n";