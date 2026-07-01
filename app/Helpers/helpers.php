<?php
/**
 * Global Helper Functions
 * 
 * Utility functions used throughout the application
 */

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to URL
 */
function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit;
}

/**
 * Get base URL
 */
function baseUrl($path = '') {
    $config = require __DIR__ . '/../../config/app.php';
    $baseUrl = $config['url'];
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

/**
 * Get asset URL
 */
function asset($path) {
    $config = require __DIR__ . '/../../config/app.php';
    return $config['paths']['assets'] . '/' . ltrim($path, '/');
}

/**
 * Flash message to session
 */
function flash($key, $message = null) {
    if ($message === null) {
        $value = $_SESSION["flash_$key"] ?? null;
        unset($_SESSION["flash_$key"]);
        return $value;
    }
    
    $_SESSION["flash_$key"] = $message;
}

/**
 * Get old input value
 */
function old($key, $default = '') {
    return $_SESSION['old'][$key] ?? $default;
}

/**
 * Set old input values
 */
function setOld($data) {
    $_SESSION['old'] = $data;
}

/**
 * Clear old input values
 */
function clearOld() {
    unset($_SESSION['old']);
}

/**
 * JSON response (with security headers)
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Log message to file
 */
function logMessage($message, $level = 'info') {
    $logFile = __DIR__ . '/../../storage/logs/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Check if request is AJAX
 */
function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get request method
 */
function requestMethod() {
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

/**
 * Check if request method matches
 */
function isMethod($method) {
    return strtoupper(requestMethod()) === strtoupper($method);
}

/**
 * Get request input (with cached JSON body)
 */
function input($key, $default = null) {
    static $jsonBody = null;
    static $jsonParsed = false;

    // Check POST
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    
    // Check GET
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }
    
    // Check JSON body (read once, cache for subsequent calls)
    if (!$jsonParsed) {
        $raw = file_get_contents('php://input');
        $jsonBody = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
        $jsonParsed = true;
    }

    if (is_array($jsonBody) && array_key_exists($key, $jsonBody)) {
        return $jsonBody[$key];
    }
    
    return $default;
}

/**
 * Get all request inputs (with cached JSON body)
 */
function inputs() {
    static $allInputs = null;
    if ($allInputs !== null) return $allInputs;

    $data = array_merge($_GET, $_POST);
    $raw = file_get_contents('php://input');
    $json = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    if (is_array($json)) {
        $data = array_merge($data, $json);
    }
    $allInputs = $data;
    return $data;
}

/**
 * dd - Dump and die (for debugging)
 */
function dd(...$vars) {
    echo '<pre>';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    die();
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

/**
 * Time ago
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M d, Y', $time);
    }
}

// ============================================
// Validation Helpers (reduce repeated checks)
// ============================================

/**
 * Require a POST method or respond 405.
 */
function requirePost(): void
{
    if (!isMethod('POST')) {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

/**
 * Require a valid positive integer input or respond 400.
 * Returns the validated integer.
 */
function requireInt(string $key, string $label = null): int
{
    $value = (int) input($key);
    if ($value <= 0) {
        $label = $label ?? str_replace('_', ' ', $key);
        jsonResponse(['error' => ucfirst($label) . ' is required'], 400);
    }
    return $value;
}

/**
 * Require a non-empty array input or respond 400.
 */
function requireArray(string $key, string $label = null): array
{
    $value = input($key);
    if (!$value || !is_array($value)) {
        $label = $label ?? str_replace('_', ' ', $key);
        jsonResponse(['error' => ucfirst($label) . ' array is required'], 400);
    }
    return $value;
}

/**
 * Require a non-empty string input or respond 400.
 */
function requireString(string $key, string $label = null): string
{
    $value = input($key);
    if (!$value || !is_string($value) || trim($value) === '') {
        $label = $label ?? str_replace('_', ' ', $key);
        jsonResponse(['error' => ucfirst($label) . ' is required'], 400);
    }
    return trim($value);
}
