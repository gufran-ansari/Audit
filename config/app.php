<?php
/**
 * Application Configuration
 * 
 * This file loads environment variables and provides application-wide configuration
 */

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load .env file
loadEnv(__DIR__ . '/../.env');

// Helper function to get environment variables
function env($key, $default = null) {
    $value = getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // Convert string booleans to actual booleans
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }
    
    return $value;
}

// Application configuration
return [
    'name' => env('APP_NAME', 'Audit withGufran'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'key' => env('APP_KEY', ''),
    
    'paths' => [
        'base' => env('BASE_PATH', '/'),
        'assets' => env('ASSETS_PATH', '/assets'),
    ],
    
    'session' => [
        'lifetime' => env('SESSION_LIFETIME', 7200),
        'name' => env('SESSION_NAME', 'audit_session'),
        'secure' => env('SESSION_SECURE', false),
    ],
    
    'csrf' => [
        'token_name' => env('CSRF_TOKEN_NAME', 'csrf_token'),
    ],
];
