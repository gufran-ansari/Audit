<?php
/**
 * Application Bootstrap
 * 
 * Initializes the application and loads required files
 */

// Start session with secure settings
$config = require __DIR__ . '/../config/app.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.sid_length', 48);
ini_set('session.sid_bits_per_character', 6);

if ($config['session']['secure']) {
    ini_set('session.cookie_secure', 1);
}

session_name($config['session']['name']);
session_start();

// Set session lifetime
if (isset($_SESSION['login_time'])) {
    $elapsed = time() - $_SESSION['login_time'];
    if ($elapsed > $config['session']['lifetime']) {
        session_unset();
        session_destroy();
        session_start();
    }
}

// Error reporting based on environment
if ($config['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../storage/logs/errors.log');
}

// Set timezone
date_default_timezone_set('UTC');

// Generate/validate Request ID for correlation
$requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
if ($requestId) {
    // Validate externally supplied ID - alphanumeric + hyphens only, max 64 chars
    if (!preg_match('/^[a-zA-Z0-9\-]{1,64}$/', $requestId)) {
        $requestId = null;
    }
}
if (!$requestId) {
    $requestId = bin2hex(random_bytes(16)); // 32 char hex
}

// Store in $_SERVER for application-wide access and $_SESSION for cross-request correlation
$_SERVER['REQUEST_ID'] = $requestId;
$_SESSION['request_id'] = $requestId;

// Return Request ID in response header for client correlation
// Use register_shutdown_function to ensure it's set even on early exit
register_shutdown_function(function () use ($requestId) {
    if (!headers_sent()) {
        header('X-Request-ID: ' . $requestId);
    }
});

// Register autoloader
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/';
    
    // Remove namespace prefix if present
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) === 0) {
        $class = substr($class, $len);
    }
    
    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $class) . '.php';
    
    // If file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Autoload Database class separately
spl_autoload_register(function ($class) {
    if (strpos($class, 'Database\\') === 0) {
        $file = __DIR__ . '/../database/' . str_replace('Database\\', '', $class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

// Load helper functions
require __DIR__ . '/Helpers/helpers.php';

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logMessage("Error [$errno]: $errstr in $errfile on line $errline", 'error');
    
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Set exception handler
set_exception_handler(function($exception) {
    logMessage('Uncaught Exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine(), 'critical');
    
    $config = require __DIR__ . '/../config/app.php';
    
    if ($config['debug']) {
        http_response_code(500);
        header('Content-Type: text/plain');
        echo 'Error: ' . $exception->getMessage() . "\n";
        echo 'File: ' . $exception->getFile() . "\n";
        echo 'Line: ' . $exception->getLine() . "\n";
        echo "\nStack trace:\n" . $exception->getTraceAsString();
    } else {
        http_response_code(500);
        // Never expose internals in production
        if (isAjax() || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'An internal error occurred.']);
        } else {
            echo '<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Something went wrong</h1><p>Please try again later.</p></body></html>';
        }
    }
    exit;
});

// Initialize CSRF token
use App\Middleware\CsrfMiddleware;
CsrfMiddleware::generateToken();

// Apply security headers and session hardening
use App\Services\SecurityService;
SecurityService::applyHeaders();

// Validate session fingerprint (detect hijacking)
if (!SecurityService::validateFingerprint()) {
    // Fingerprint mismatch — possible session hijacking
    session_unset();
    session_destroy();
    session_start();
    // Re-generate fingerprint for the new session
    SecurityService::validateFingerprint();
}

SecurityService::rotateSessionIfNeeded();
