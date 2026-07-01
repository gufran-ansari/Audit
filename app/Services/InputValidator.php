<?php

namespace App\Services;

/**
 * Input Validator
 * 
 * Centralized input validation for consistent security across all modules.
 * All validation methods return clean data or throw/flag errors.
 */
class InputValidator
{
    /**
     * Validate and sanitize a required string input.
     */
    public static function string(?string $value, int $minLength = 1, int $maxLength = 1000): ?string
    {
        if ($value === null || $value === '') return null;
        $value = trim($value);
        if (strlen($value) < $minLength || strlen($value) > $maxLength) return null;
        return $value;
    }

    /**
     * Validate and sanitize integer input.
     */
    public static function integer($value, int $min = 0, int $max = PHP_INT_MAX): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max]
        ]);
        return $int !== false ? $int : null;
    }

    /**
     * Validate email format.
     */
    public static function email(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim(strtolower($value));
        return filter_var($value, FILTER_VALIDATE_EMAIL) ?: null;
    }

    /**
     * Validate URL format.
     */
    public static function url(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim($value);
        return filter_var($value, FILTER_VALIDATE_URL) ?: null;
    }

    /**
     * Validate a value is in a whitelist of allowed values.
     */
    public static function inList($value, array $allowed, $default = null)
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Validate a slug (URL-safe string).
     */
    public static function slug(?string $value, int $maxLength = 100): ?string
    {
        if (!$value) return null;
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $value)) return null;
        if (strlen($value) > $maxLength) return null;
        return $value;
    }

    /**
     * Sanitize HTML output (for rendering user-provided content).
     */
    public static function escapeHtml(?string $value): string
    {
        if ($value === null) return '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Validate JSON string.
     */
    public static function json(?string $value): ?array
    {
        if (!$value) return null;
        $decoded = json_decode($value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }

    /**
     * Validate array of integers.
     */
    public static function intArray($value): array
    {
        if (!is_array($value)) return [];
        return array_filter(array_map('intval', $value), function ($v) { return $v > 0; });
    }

    /**
     * Validate comma-separated list.
     */
    public static function csvList(?string $value, array $allowedValues = []): array
    {
        if (!$value) return [];
        $items = array_map('trim', explode(',', $value));
        $items = array_filter($items, function ($item) { return $item !== ''; });

        if (!empty($allowedValues)) {
            $items = array_intersect($items, $allowedValues);
        }

        return array_values($items);
    }

    /**
     * Validate file upload (future-ready).
     */
    public static function fileUpload(array $file, array $allowedMimes = [], int $maxSizeMb = 10): array
    {
        $errors = [];

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed';
            return $errors;
        }

        if ($file['size'] > ($maxSizeMb * 1024 * 1024)) {
            $errors[] = "File exceeds maximum size of {$maxSizeMb}MB";
        }

        if (!empty($allowedMimes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowedMimes)) {
                $errors[] = 'File type not allowed';
            }
        }

        // Check for path traversal in filename
        $filename = basename($file['name']);
        if ($filename !== $file['name'] || strpos($file['name'], '..') !== false) {
            $errors[] = 'Invalid filename';
        }

        return $errors;
    }
}
