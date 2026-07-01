<?php

namespace App\Services;

/**
 * Audit Result Helper
 * 
 * Lightweight helpers for building module-shaped result arrays used by
 * processors and the orchestration layer. Complements AuditResultService
 * (which standardizes the higher-level result envelope).
 *
 * These helpers return the "raw" module result shape that processors
 * produce (with a 'status' and 'error' key), so repositories can store
 * them consistently.
 */
class AuditResultHelper
{
    /**
     * Build a generic error result for a module.
     */
    public static function error(string $moduleKey, string $message): array
    {
        return [
            'status' => 'ERROR',
            'error' => $message,
            'module_key' => $moduleKey
        ];
    }

    /**
     * Normalize a raw module result into the unified envelope expected
     * across the platform.
     *
     * Ensures every module result exposes:
     *  - module_name
     *  - status
     *  - summary
     *  - details
     *  - execution_time
     *  - checked_at
     *
     * @param string $moduleName Database module name
     * @param array $raw Raw processor result
     * @param int|null $executionTimeMs
     * @return array Unified result envelope
     */
    public static function normalize(string $moduleName, array $raw, ?int $executionTimeMs = null): array
    {
        $status = $raw['status'] ?? 'ERROR';

        return [
            'module_name' => $moduleName,
            'status' => $status,
            'summary' => self::buildSummary($moduleName, $raw),
            'details' => $raw,
            'execution_time' => $executionTimeMs,
            'checked_at' => date('Y-m-d H:i:s'),
            'error' => $raw['error'] ?? null
        ];
    }

    /**
     * Build a human-readable summary for a module result based on its data.
     */
    public static function buildSummary(string $moduleName, array $raw): string
    {
        $status = $raw['status'] ?? 'ERROR';

        if ($status === 'ERROR') {
            return 'Error: ' . ($raw['error'] ?? 'Unknown error');
        }

        switch ($moduleName) {
            case 'h1_audit':
                $count = $raw['h1_count'] ?? 0;
                if ($count === 0) return 'No H1 found';
                if ($count === 1) return 'Single H1 found';
                return "Multiple H1 tags found ({$count})";

            case 'meta_audit':
                $titleStatus = $raw['title_status'] ?? 'unknown';
                $descStatus = $raw['description_status'] ?? 'unknown';
                return "Title: {$titleStatus}, Description: {$descStatus}";

            case 'noindex_audit':
                $index = $raw['index_status'] ?? 'unknown';
                $follow = $raw['follow_status'] ?? 'unknown';
                return "Index: {$index}, Follow: {$follow}";

            case 'faq_schema_audit':
                if (!empty($raw['has_faq_schema'])) {
                    $count = $raw['question_count'] ?? 0;
                    return "FAQ schema found ({$count} questions)";
                }
                if (!empty($raw['has_faq_content'])) {
                    return 'FAQ content found but no schema';
                }
                return 'No FAQ schema or content';

            case 'lorem_audit':
                $occ = $raw['total_occurrences'] ?? 0;
                return $occ > 0 ? "{$occ} placeholder occurrence(s)" : 'No placeholder content';

            case 'staging_audit':
                $occ = $raw['total_occurrences'] ?? 0;
                return $occ > 0 ? "{$occ} staging reference(s)" : 'No staging references';

            case 'word_occurrence_audit':
                $occ = $raw['total_occurrences'] ?? 0;
                $kw = !empty($raw['keywords']) ? implode(', ', $raw['keywords']) : '';
                return $occ > 0 ? "{$occ} occurrence(s) of '{$kw}'" : "No occurrences of '{$kw}'";

            default:
                return ucfirst(strtolower($status));
        }
    }

    /**
     * Aggregate an empty module summary structure for the given module names.
     */
    public static function emptySummary(array $moduleNames): array
    {
        $summary = [];
        foreach ($moduleNames as $name) {
            $summary[$name] = [
                'PASS' => 0,
                'WARNING' => 0,
                'FAIL' => 0,
                'ERROR' => 0,
                'NOT_RUN' => 0,
            ];
        }
        return $summary;
    }
}
