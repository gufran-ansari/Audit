<?php

namespace App\Services;

use Database\Database;

/**
 * Settings Service
 * 
 * Provides centralized access to audit system settings.
 * Reads from config/audit.php as defaults, with optional database
 * overrides from the settings table. Prepares the architecture for a
 * future settings UI without requiring code changes.
 */
class SettingsService
{
    private static ?array $configCache = null;
    private static ?array $dbOverrides = null;

    /**
     * Get a setting value. Checks DB overrides first, then config file.
     *
     * @param string $key Dot-notation key (e.g. 'background.batch_size')
     * @param mixed $default Fallback if key not found anywhere
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        // Check DB overrides first
        $dbValue = self::getDbOverride("audit.{$key}");
        if ($dbValue !== null) {
            return self::castValue($dbValue);
        }

        // Fall back to config file
        $config = self::loadConfig();
        return self::dotGet($config, $key, $default);
    }

    /**
     * Get the batch size setting.
     */
    public static function getBatchSize(): int
    {
        return (int) self::get('background.batch_size', 10);
    }

    /**
     * Get the max execution seconds for background processing.
     */
    public static function getMaxExecutionSeconds(): int
    {
        return (int) self::get('background.max_execution_seconds', 55);
    }

    /**
     * Get the heartbeat timeout (for interruption detection).
     */
    public static function getHeartbeatTimeout(): int
    {
        return (int) self::get('background.heartbeat_timeout', 120);
    }

    /**
     * Get the max retry limit per page/module.
     */
    public static function getMaxRetries(): int
    {
        return (int) self::get('background.max_retries', 3);
    }

    /**
     * Get the fetch timeout in seconds.
     */
    public static function getFetchTimeout(): int
    {
        return (int) self::get('fetch.timeout', 30);
    }

    /**
     * Check if unchanged writes should be skipped.
     */
    public static function shouldSkipUnchangedWrites(): bool
    {
        return (bool) self::get('writes.skip_unchanged', true);
    }

    /**
     * Check if metrics collection is enabled.
     */
    public static function isMetricsEnabled(): bool
    {
        return (bool) self::get('metrics.enabled', true);
    }

    /**
     * Get max consecutive errors before aborting a job.
     */
    public static function getMaxConsecutiveErrors(): int
    {
        return (int) self::get('safeguards.max_consecutive_errors', 50);
    }

    // ─── INTERNALS ─────────────────────────────────────────────

    /**
     * Load the audit config file (cached per request).
     */
    private static function loadConfig(): array
    {
        if (self::$configCache === null) {
            $file = __DIR__ . '/../../config/audit.php';
            self::$configCache = file_exists($file) ? require $file : [];
        }
        return self::$configCache;
    }

    /**
     * Get a value from DB settings table (with caching).
     */
    private static function getDbOverride(string $key): ?string
    {
        if (self::$dbOverrides === null) {
            self::$dbOverrides = [];
            try {
                $db = Database::getInstance();
                $query = "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'audit.%'";
                $rows = $db->query($query, []);
                if ($rows) {
                    foreach ($rows as $row) {
                        self::$dbOverrides[$row['setting_key']] = $row['setting_value'];
                    }
                }
            } catch (\Throwable $e) {
                // DB not available — use file config only
            }
        }

        return self::$dbOverrides[$key] ?? null;
    }

    /**
     * Access a nested array value using dot notation.
     */
    private static function dotGet(array $array, string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Cast a string value from DB to appropriate PHP type.
     */
    private static function castValue(string $value)
    {
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }
        if (strtolower($value) === 'true') return true;
        if (strtolower($value) === 'false') return false;
        if (strtolower($value) === 'null') return null;
        return $value;
    }

    /**
     * Clear the settings cache (for testing or after settings change).
     */
    public static function clearCache(): void
    {
        self::$configCache = null;
        self::$dbOverrides = null;
    }
}
