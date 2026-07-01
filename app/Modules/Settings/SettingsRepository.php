<?php

namespace App\Modules\Settings;

use Database\Database;

/**
 * Settings Repository
 * 
 * Database operations for application settings.
 */
class SettingsRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get a single setting by key.
     */
    public function get(string $key, $default = null): ?string
    {
        $result = $this->db->query(
            "SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1",
            ['key' => $key]
        );
        return $result ? ($result[0]['setting_value'] ?? $default) : $default;
    }

    /**
     * Get all settings as key-value array.
     */
    public function getAll(): array
    {
        $rows = $this->db->query("SELECT setting_key, setting_value FROM settings") ?: [];
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    /**
     * Get settings by category with full metadata.
     */
    public function getByCategory(string $category): array
    {
        return $this->db->query(
            "SELECT setting_key, setting_value, setting_type, description, category
             FROM settings WHERE category = :cat ORDER BY id ASC",
            ['cat' => $category]
        ) ?: [];
    }

    /**
     * Get all settings grouped by category.
     */
    public function getAllGrouped(): array
    {
        $rows = $this->db->query(
            "SELECT setting_key, setting_value, setting_type, description, category
             FROM settings ORDER BY category, id ASC"
        ) ?: [];

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['category']][] = $row;
        }
        return $grouped;
    }

    /**
     * Set a single setting value.
     */
    public function set(string $key, ?string $value): bool
    {
        $exists = $this->db->query(
            "SELECT id FROM settings WHERE setting_key = :key LIMIT 1",
            ['key' => $key]
        );

        if ($exists) {
            return (bool) $this->db->execute(
                "UPDATE settings SET setting_value = :val, updated_at = NOW() WHERE setting_key = :key",
                ['val' => $value, 'key' => $key]
            );
        }

        return false;
    }

    /**
     * Set multiple settings at once.
     */
    public function setMany(array $settings): int
    {
        $updated = 0;
        foreach ($settings as $key => $value) {
            if ($this->set($key, $value)) {
                $updated++;
            }
        }
        return $updated;
    }

    /**
     * Create a new setting (used for adding new config options).
     */
    public function create(string $key, string $category, ?string $value, string $type = 'text', ?string $description = null): bool
    {
        $exists = $this->db->query(
            "SELECT id FROM settings WHERE setting_key = :key LIMIT 1",
            ['key' => $key]
        );

        if ($exists) return false;

        $this->db->insert(
            "INSERT INTO settings (setting_key, category, setting_value, setting_type, description)
             VALUES (:key, :cat, :val, :type, :desc)",
            ['key' => $key, 'cat' => $category, 'val' => $value, 'type' => $type, 'desc' => $description]
        );
        return true;
    }

    /**
     * Delete a setting.
     */
    public function delete(string $key): bool
    {
        return (bool) $this->db->execute(
            "DELETE FROM settings WHERE setting_key = :key",
            ['key' => $key]
        );
    }

    /**
     * Get available categories.
     */
    public function getCategories(): array
    {
        $rows = $this->db->query(
            "SELECT DISTINCT category FROM settings ORDER BY FIELD(category, 'general', 'audit', 'reports', 'security', 'system')"
        ) ?: [];
        return array_column($rows, 'category');
    }
}
