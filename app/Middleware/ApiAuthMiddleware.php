<?php
/**
 * API Authentication Middleware
 * 
 * Provides consistent authentication, CSRF validation, and role checking
 * for API endpoints with JSON responses instead of HTML redirects.
 */

namespace App\Middleware;

use App\Services\AuthService;
use App\Services\ActivityLogService;

class ApiAuthMiddleware
{
    private $authService;
    private $activityLog;
    private $requireAuth = true;
    private $requireCsrf = true;
    private $requiredRoles = [];
    private $isApiRequest = true;

    public function __construct(array $options = [])
    {
        $this->authService = new AuthService();
        $this->activityLog = ActivityLogService::getInstance();
        $this->requireAuth = $options['auth'] ?? true;
        $this->requireCsrf = $options['csrf'] ?? true;
        $this->requiredRoles = $options['roles'] ?? [];
        $this->isApiRequest = $options['api'] ?? true;
    }

    /**
     * Handle authentication, CSRF, and role checks
     * 
     * @return bool|void Returns true on success, exits on failure
     */
    public function handle(): bool
    {
        // Check authentication
        if ($this->requireAuth && !$this->authService->check()) {
            $this->logAndFail(401, 'authz_denied', 'Authentication required');
        }

        // If auth not required and user not logged in, skip remaining checks
        if (!$this->requireAuth && !$this->authService->check()) {
            return true;
        }

        // Check roles if specified
        if (!empty($this->requiredRoles)) {
            $this->checkRoles();
        }

        // Check CSRF for state-changing requests
        if ($this->requireCsrf && $this->isMutatingRequest()) {
            $this->validateCsrf();
        }

        return true;
    }

    /**
     * Check if the current request method is a mutating/state-changing method
     */
    private function isMutatingRequest(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Validate CSRF token from request
     */
    private function validateCsrf(): void
    {
        // Check for token in POST body or X-CSRF-Token header
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!$token || !\App\Middleware\CsrfMiddleware::verifyToken($token)) {
            $this->logAndFail(419, 'csrf_failed', 'CSRF token mismatch. Please refresh and try again.');
        }
    }

    /**
     * Check if user has required role(s)
     */
    private function checkRoles(): void
    {
        $user = $this->authService->user();
        if (!$user) {
            $this->logAndFail(401, 'authz_denied', 'Authentication required');
        }

        $userRole = $user['role'] ?? null;
        $hasRole = false;

        foreach ($this->requiredRoles as $requiredRole) {
            if ($requiredRole === 'admin') {
                if ($this->authService->isAdmin()) {
                    $hasRole = true;
                    break;
                }
            } elseif ($requiredRole === 'super_admin') {
                if ($this->authService->isSuperAdmin()) {
                    $hasRole = true;
                    break;
                }
            } elseif ($userRole === $requiredRole) {
                $hasRole = true;
                break;
            }
        }

        if (!$hasRole) {
            $this->logAndFail(403, 'authz_denied', 'Insufficient permissions. Required role: ' . implode(' or ', $this->requiredRoles));
        }
    }

    /**
     * Log security event and return JSON error response
     */
    private function logAndFail(int $statusCode, string $eventType, string $message): void
    {
        $endpoint = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        
        $this->activityLog->logSecurity($eventType, $message, [
            'status_code' => $statusCode,
            'required_roles' => $this->requiredRoles,
        ], $endpoint, $method);

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $statusCode
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ... rest of the class remains the same

    /**
     * Disable authentication requirement (for public endpoints)
     */
    public function withoutAuth(): self
    {
        $this->requireAuth = false;
        return $this;
    }

    /**
     * Disable CSRF requirement (for webhooks, cron-compatible endpoints)
     */
    public function withoutCsrf(): self
    {
        $this->requireCsrf = false;
        return $this;
    }

    /**
     * Require specific role(s)
     * 
     * @param string|array $roles Single role or array of roles (OR logic)
     */
    public function requireRoles(string|array $roles): self
    {
        $this->requiredRoles = is_array($roles) ? $roles : [$roles];
        return $this;
    }

    /**
     * Require admin role (includes super_admin)
     */
    public function requireAdmin(): self
    {
        return $this->requireRoles('admin');
    }

    /**
     * Require super_admin role
     */
    public function requireSuperAdmin(): self
    {
        return $this->requireRoles('super_admin');
    }

    /**
     * Set whether this is an API request (affects response format)
     */
    public function setApiRequest(bool $isApi): self
    {
        $this->isApiRequest = $isApi;
        return $this;
    }

    /**
     * Get current user data if authenticated
     */
    public function user(): ?array
    {
        return $this->authService->user();
    }

    /**
     * Check if user is authenticated
     */
    public function check(): bool
    {
        return $this->authService->check();
    }
}