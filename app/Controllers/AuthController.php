<?php
/**
 * Authentication Controller
 * 
 * Handles login and logout requests
 */

namespace App\Controllers;

use App\Services\AuthService;
use App\Middleware\CsrfMiddleware;

class AuthController {
    private $authService;
    
    public function __construct() {
        $this->authService = new AuthService();
    }
    
    /**
     * Show login form
     */
    public function showLogin() {
        // If already logged in, redirect to dashboard
        if ($this->authService->check()) {
            redirect('/dashboard.php');
        }
        
        // Generate CSRF token
        $csrfToken = CsrfMiddleware::generateToken();
        
        // Load login view
        require __DIR__ . '/../../resources/views/auth/login.php';
    }
    
    /**
     * Handle login request
     */
    public function login() {
        // Validate CSRF token
        (new CsrfMiddleware())->handle();
        
        // Rate limiting
        $ip = \App\Services\SecurityService::getClientIp();
        $rateLimiter = new \App\Services\RateLimiter();
        $rateLimitKey = 'login:' . $ip;

        if (!$rateLimiter->attempt($rateLimitKey, 5, 900)) {
            $retryAfter = $rateLimiter->retryAfter($rateLimitKey, 900);
            flash('error', 'Too many login attempts. Please try again in ' . ceil($retryAfter / 60) . ' minutes.');
            redirect('/login.php');
        }

        // Get input
        $email = sanitize(input('email'));
        $password = input('password');
        $remember = input('remember') === 'on';
        
        // Validate input
        if (empty($email) || empty($password)) {
            flash('error', 'Please provide both email and password.');
            setOld($_POST);
            redirect('/login.php');
        }
        
        if (!isValidEmail($email)) {
            flash('error', 'Please provide a valid email address.');
            setOld($_POST);
            redirect('/login.php');
        }
        
        // Attempt login
        $result = $this->authService->attempt($email, $password);
        
        if (!$result['success']) {
            flash('error', $result['message']);
            setOld(['email' => $email]);
            redirect('/login.php');
        }

        // Successful login — clear rate limit
        $rateLimiter->clear($rateLimitKey);
        
        // Clear old input
        clearOld();
        
        // Set remember me cookie if checked (secure flags)
        if ($remember) {
            setcookie('remember_user', $email, [
                'expires' => time() + (86400 * 30),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        
        // Flash success message
        flash('success', 'Welcome back, ' . $result['user']['name'] . '!');
        
        // Redirect to dashboard
        redirect('/dashboard.php');
    }
    
    /**
     * Handle logout request
     */
    public function logout() {
        $this->authService->logout();
        flash('success', 'You have been logged out successfully.');
        redirect('/login.php');
    }
    
    /**
     * API login (for AJAX requests)
     */
    public function apiLogin() {
        // Validate CSRF token
        (new CsrfMiddleware())->handle();

        // Rate limiting
        $ip = \App\Services\SecurityService::getClientIp();
        $rateLimiter = new \App\Services\RateLimiter();
        $rateLimitKey = 'login:' . $ip;

        if (!$rateLimiter->attempt($rateLimitKey, 5, 900)) {
            $retryAfter = $rateLimiter->retryAfter($rateLimitKey, 900);
            jsonResponse([
                'success' => false,
                'message' => 'Too many login attempts. Try again in ' . ceil($retryAfter / 60) . ' minutes.'
            ], 429);
        }
        
        // Get input
        $email = sanitize(input('email'));
        $password = input('password');
        
        // Validate input
        if (empty($email) || empty($password)) {
            jsonResponse([
                'success' => false,
                'message' => 'Please provide both email and password.'
            ], 400);
        }
        
        if (!isValidEmail($email)) {
            jsonResponse([
                'success' => false,
                'message' => 'Please provide a valid email address.'
            ], 400);
        }
        
        // Attempt login
        $result = $this->authService->attempt($email, $password);
        
        if (!$result['success']) {
            jsonResponse($result, 401);
        }

        // Clear rate limit on success
        $rateLimiter->clear($rateLimitKey);

        jsonResponse($result, 200);
    }
}
