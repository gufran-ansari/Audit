<?php
/**
 * Authentication Middleware
 * 
 * Protects routes that require authentication
 */

namespace App\Middleware;

use App\Services\AuthService;

class AuthMiddleware {
    private $authService;
    
    public function __construct() {
        $this->authService = new AuthService();
    }
    
    /**
     * Handle middleware
     */
    public function handle() {
        if (!$this->authService->check()) {
            // Redirect to login page
            header('Location: /login.php');
            exit;
        }
        
        return true;
    }
    
    /**
     * Check authentication without redirect
     */
    public function check() {
        return $this->authService->check();
    }
}
