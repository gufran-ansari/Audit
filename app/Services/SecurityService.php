<?php

namespace App\Services;

/**
 * Security Service
 * 
 * Centralized security utilities:
 * - Security headers
 * - Session fingerprinting
 * - Rate limiting
 * - Input sanitization
 * - Output escaping
 * - CSRF token management
 */
class SecurityService
{
    /**
     * Apply security response headers.
     * Call early in the request lifecycle.
     */
    public static function applyHeaders(): void
    {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // Prevent MIME-type sniffing
        header('X-Content-Type-Options: nosniff');

        // XSS Protection (legacy browsers)
        header('X-XSS-Protection: 1; mode=block');

        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions policy (disable features not needed)
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        // Content Security Policy (report-only to avoid breaking things)
        // Can be tightened in production
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'");
    }

    /**
     * Apply security headers for API responses (JSON).
     */
    public static function applyApiHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }

    /**
     * Generate a session fingerprint to detect session hijacking.
     */
    public static function generateFingerprint(): string
    {
        $data = ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        return hash('sha256', $data);
    }

    /**
     * Validate session fingerprint. Returns false if session may be hijacked.
     */
    public static function validateFingerprint(): bool
    {
        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = self::generateFingerprint();
            return true;
        }

        return hash_equals($_SESSION['_fingerprint'], self::generateFingerprint());
    }

    /**
     * Regenerate session ID periodically (every 15 minutes).
     */
    public static function rotateSessionIfNeeded(): void
    {
        $rotateInterval = 900; // 15 minutes

        if (!isset($_SESSION['_last_rotation'])) {
            $_SESSION['_last_rotation'] = time();
            return;
        }

        if (time() - $_SESSION['_last_rotation'] > $rotateInterval) {
            session_regenerate_id(true);
            $_SESSION['_last_rotation'] = time();
        }
    }

    /**
     * Escape output for HTML context.
     */
    public static function escHtml(?string $value): string
    {
        if ($value === null) return '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape output for JavaScript context.
     */
    public static function escJs(?string $value): string
    {
        if ($value === null) return '';
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * Escape output for URL context.
     */
    public static function escUrl(?string $value): string
    {
        if ($value === null) return '';
        return filter_var($value, FILTER_SANITIZE_URL);
    }

    /**
     * Validate that a value is a safe integer (prevents injection via numeric fields).
     */
    public static function safeInt($value, int $min = 0, int $max = PHP_INT_MAX): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max]
        ]);
        return $int !== false ? $int : null;
    }

    /**
     * Validate URL format.
     */
    public static function isValidUrl(?string $url): bool
    {
        if (!$url) return false;
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Strip dangerous characters from filenames.
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove path separators and null bytes
        $filename = str_replace(["\0", '/', '\\', '..'], '', $filename);
        // Keep only safe chars
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return substr($filename, 0, 255);
    }

    /**
     * Verify password strength.
     */
    public static function isStrongPassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        // Check for common weak passwords
        $weak = ['password', '12345678', 'qwerty', 'admin123', 'letmein', 'welcome'];
        if (in_array(strtolower($password), $weak)) {
            $errors[] = 'Password is too common';
        }

        return $errors;
    }

    /**
     * Get client IP address (handles proxies).
     */
    public static function getClientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // X-Forwarded-For can have multiple IPs; take the first
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
