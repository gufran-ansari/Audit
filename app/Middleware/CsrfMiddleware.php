<?php
/**
 * CSRF Middleware
 * 
 * Protects against Cross-Site Request Forgery attacks
 */

namespace App\Middleware;

use App\Services\ActivityLogService;

class CsrfMiddleware {
    
    /**
     * Generate CSRF token
     */
    public static function generateToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get current CSRF token
     */
    public static function getToken() {
        return $_SESSION['csrf_token'] ?? null;
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Handle CSRF validation
     */
    public function handle() {
        // Only check for POST, PUT, DELETE requests
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            
            if (!$token || !self::verifyToken($token)) {
                // Log CSRF failure
                $activityLog = ActivityLogService::getInstance();
                $activityLog->logSecurity('csrf_failed', 'CSRF token validation failed', [
                    'provided_token' => $token ? substr($token, 0, 8) . '...' : 'missing',
                ], $_SERVER['REQUEST_URI'] ?? 'unknown', $method);
                
                http_response_code(419);
                die(json_encode([
                    'success' => false,
                    'message' => 'CSRF token mismatch. Please refresh and try again.'
                ]));
            }
        }
        
        return true;
    }
    
    /**
     * Regenerate token
     */
    public static function regenerateToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}
