<?php
/**
 * Role Middleware
 * 
 * Protects routes based on user roles
 */

namespace App\Middleware;

use App\Services\AuthService;

class RoleMiddleware {
    private $authService;
    
    public function __construct() {
        $this->authService = new AuthService();
    }
    
    /**
     * Handle middleware for specific role
     */
    public function handle($requiredRole) {
        // First check if user is authenticated
        if (!$this->authService->check()) {
            header('Location: /login.php');
            exit;
        }
        
        // Check if user has required role
        if (!$this->authService->hasRole($requiredRole)) {
            // Redirect to unauthorized page
            http_response_code(403);
            require __DIR__ . '/../../resources/views/errors/403.php';
            exit;
        }
        
        return true;
    }
    
    /**
     * Check if user has role without redirect
     */
    public function hasRole($role) {
        return $this->authService->hasRole($role);
    }
    
    /**
     * Super admin check
     */
    public function superAdmin() {
        return $this->handle('super_admin');
    }
    
    /**
     * Admin check (includes super_admin)
     */
    public function admin() {
        if (!$this->authService->check()) {
            header('Location: /login.php');
            exit;
        }
        
        if (!$this->authService->isAdmin()) {
            http_response_code(403);
            require __DIR__ . '/../../resources/views/errors/403.php';
            exit;
        }
        
        return true;
    }
}
