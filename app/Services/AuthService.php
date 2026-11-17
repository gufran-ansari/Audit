<?php
/**
 * Authentication Service
 * 
 * Handles authentication logic
 */

namespace App\Services;

use App\Models\User;

class AuthService {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    /**
     * Attempt to login user
     */
    public function attempt($email, $password) {
        // Find user by email
        $user = $this->userModel->findByEmail($email);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }
        
        // Check if user is active
        if (!$this->userModel->isActive($user)) {
            return [
                'success' => false,
                'message' => 'Your account has been deactivated.'
            ];
        }
        
        // Verify password
        if (!$this->userModel->verifyPassword($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }
        
        // Login successful - start session
        $this->login($user);
        
        return [
            'success' => true,
            'message' => 'Login successful.',
            'user' => $this->getUserData($user)
        ];
    }
    
    /**
     * Login user (create session)
     */
    private function login($user) {
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Clear session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy session
        session_destroy();

        // Start a fresh anonymous session so post-logout flash messages persist
        session_start();
        session_regenerate_id(true);
        
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function check() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current user
     */
    public function user() {
        if (!$this->check()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
        ];
    }
    
    /**
     * Get user data without sensitive information
     */
    private function getUserData($user) {
        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        if (!$this->check()) {
            return false;
        }
        
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }
    
    /**
     * Check if user is super admin
     */
    public function isSuperAdmin() {
        return $this->hasRole('super_admin');
    }
    
    /**
     * Check if user is admin (includes super_admin)
     */
    public function isAdmin() {
        return $this->hasRole('admin') || $this->hasRole('super_admin');
    }
}
