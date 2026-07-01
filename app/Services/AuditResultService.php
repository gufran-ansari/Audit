<?php

namespace App\Services;

/**
 * Audit Result Service
 * 
 * Standardizes audit result structure across all modules
 * Provides helpers for creating, formatting, and managing audit results
 */
class AuditResultService
{
    /**
     * Valid result statuses
     */
    const STATUS_PASS = 'PASS';
    const STATUS_WARNING = 'WARNING';
    const STATUS_FAIL = 'FAIL';
    const STATUS_ERROR = 'ERROR';
    const STATUS_NOT_RUN = 'NOT_RUN';

    /**
     * Valid run types
     */
    const RUN_TYPE_MANUAL = 'manual';
    const RUN_TYPE_RETRY = 'retry';
    const RUN_TYPE_PRESET = 'preset';
    const RUN_TYPE_SCHEDULED = 'scheduled';

    /**
     * Create standardized audit result
     * 
     * @param string $status Result status (PASS, WARNING, FAIL, ERROR, NOT_RUN)
     * @param string|null $value Primary result value
     * @param array $data Complete audit data
     * @param string|null $summary Human-readable summary
     * @param string|null $errorMessage Error message if status is ERROR
     * @param int|null $executionTimeMs Execution time in milliseconds
     * @return array Standardized result structure
     */
    public static function createResult(
        string $status,
        ?string $value = null,
        array $data = [],
        ?string $summary = null,
        ?string $errorMessage = null,
        ?int $executionTimeMs = null
    ): array {
        // Validate status
        if (!self::isValidStatus($status)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        return [
            'status' => $status,
            'value' => $value,
            'summary' => $summary ?? self::generateSummary($status, $value, $data),
            'error' => $errorMessage,
            'execution_time_ms' => $executionTimeMs,
            'data' => $data,
            'checked_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Create PASS result
     */
    public static function pass(?string $value = null, array $data = [], ?string $summary = null, ?int $executionTimeMs = null): array
    {
        return self::createResult(self::STATUS_PASS, $value, $data, $summary, null, $executionTimeMs);
    }

    /**
     * Create WARNING result
     */
    public static function warning(?string $value = null, array $data = [], ?string $summary = null, ?int $executionTimeMs = null): array
    {
        return self::createResult(self::STATUS_WARNING, $value, $data, $summary, null, $executionTimeMs);
    }

    /**
     * Create FAIL result
     */
    public static function fail(?string $value = null, array $data = [], ?string $summary = null, ?int $executionTimeMs = null): array
    {
        return self::createResult(self::STATUS_FAIL, $value, $data, $summary, null, $executionTimeMs);
    }

    /**
     * Create ERROR result
     */
    public static function error(string $errorMessage, array $data = [], ?int $executionTimeMs = null): array
    {
        return self::createResult(
            self::STATUS_ERROR,
            null,
            $data,
            "Error: {$errorMessage}",
            $errorMessage,
            $executionTimeMs
        );
    }

    /**
     * Create NOT_RUN result
     */
    public static function notRun(?string $reason = null): array
    {
        return self::createResult(
            self::STATUS_NOT_RUN,
            null,
            [],
            $reason ?? 'Audit not run yet',
            null,
            null
        );
    }

    /**
     * Check if status is valid
     */
    public static function isValidStatus(string $status): bool
    {
        return in_array($status, [
            self::STATUS_PASS,
            self::STATUS_WARNING,
            self::STATUS_FAIL,
            self::STATUS_ERROR,
            self::STATUS_NOT_RUN
        ]);
    }

    /**
     * Generate human-readable summary from result
     */
    private static function generateSummary(string $status, ?string $value, array $data): string
    {
        switch ($status) {
            case self::STATUS_PASS:
                return $value ?? 'Check passed';
            
            case self::STATUS_WARNING:
                return $value ?? 'Warning detected';
            
            case self::STATUS_FAIL:
                return $value ?? 'Check failed';
            
            case self::STATUS_ERROR:
                return 'Error during audit execution';
            
            case self::STATUS_NOT_RUN:
                return 'Audit not executed';
            
            default:
                return 'Unknown status';
        }
    }

    /**
     * Compare two results to detect changes
     * 
     * @param array|null $oldResult Previous result
     * @param array $newResult New result
     * @return bool True if results differ significantly
     */
    public static function hasChanged(?array $oldResult, array $newResult): bool
    {
        if ($oldResult === null) {
            return true;
        }

        // Check if status changed
        if (($oldResult['status'] ?? null) !== ($newResult['status'] ?? null)) {
            return true;
        }

        // Check if value changed
        if (($oldResult['value'] ?? null) !== ($newResult['value'] ?? null)) {
            return true;
        }

        // Check if critical data changed (deep comparison)
        $oldData = $oldResult['data'] ?? [];
        $newData = $newResult['data'] ?? [];

        return self::dataHasChanged($oldData, $newData);
    }

    /**
     * Deep comparison of audit data
     */
    private static function dataHasChanged(array $oldData, array $newData): bool
    {
        // Remove non-critical fields that change every time
        $criticalFields = [
            'h1_count', 'h1_text',
            'title', 'title_length', 'meta_description', 'description_length',
            'robots_content', 'index_status', 'follow_status',
            'schema_found', 'schema_type', 'content_found'
        ];

        foreach ($criticalFields as $field) {
            $oldVal = $oldData[$field] ?? null;
            $newVal = $newData[$field] ?? null;

            if ($oldVal !== $newVal) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate change summary for history
     */
    public static function generateChangeSummary(string $moduleName, ?array $oldResult, array $newResult): string
    {
        if ($oldResult === null) {
            return "Initial audit: {$newResult['status']}";
        }

        $oldStatus = $oldResult['status'] ?? 'NOT_RUN';
        $newStatus = $newResult['status'] ?? 'NOT_RUN';

        if ($oldStatus !== $newStatus) {
            return "Status changed: {$oldStatus} → {$newStatus}";
        }

        $oldValue = $oldResult['value'] ?? '';
        $newValue = $newResult['value'] ?? '';

        if ($oldValue !== $newValue) {
            return "Value changed";
        }

        return "Data updated";
    }

    /**
     * Get status badge HTML class
     */
    public static function getStatusBadgeClass(string $status): string
    {
        switch ($status) {
            case self::STATUS_PASS:
                return 'badge-success';
            case self::STATUS_WARNING:
                return 'badge-warning';
            case self::STATUS_FAIL:
                return 'badge-danger';
            case self::STATUS_ERROR:
                return 'badge-danger';
            case self::STATUS_NOT_RUN:
                return 'badge-secondary';
            default:
                return 'badge-secondary';
        }
    }

    /**
     * Get status icon class
     */
    public static function getStatusIcon(string $status): string
    {
        switch ($status) {
            case self::STATUS_PASS:
                return 'bi-check-circle';
            case self::STATUS_WARNING:
                return 'bi-exclamation-triangle';
            case self::STATUS_FAIL:
                return 'bi-x-circle';
            case self::STATUS_ERROR:
                return 'bi-exclamation-octagon';
            case self::STATUS_NOT_RUN:
                return 'bi-dash';
            default:
                return 'bi-question-circle';
        }
    }

    /**
     * Format execution time
     */
    public static function formatExecutionTime(?int $milliseconds): string
    {
        if ($milliseconds === null) {
            return 'N/A';
        }

        if ($milliseconds < 1000) {
            return "{$milliseconds}ms";
        }

        $seconds = round($milliseconds / 1000, 2);
        return "{$seconds}s";
    }

    /**
     * Get all valid statuses
     */
    public static function getAllStatuses(): array
    {
        return [
            self::STATUS_PASS,
            self::STATUS_WARNING,
            self::STATUS_FAIL,
            self::STATUS_ERROR,
            self::STATUS_NOT_RUN
        ];
    }

    /**
     * Get all valid run types
     */
    public static function getAllRunTypes(): array
    {
        return [
            self::RUN_TYPE_MANUAL,
            self::RUN_TYPE_RETRY,
            self::RUN_TYPE_PRESET,
            self::RUN_TYPE_SCHEDULED
        ];
    }
}
