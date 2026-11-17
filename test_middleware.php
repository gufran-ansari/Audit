<?php
/**
 * Test script for ApiAuthMiddleware (standalone, no database)
 * 
 * Tests the middleware behavior with various scenarios using mocked AuthService.
 * Run with: php test_middleware.php
 */

// Mock the AuthService and CsrfMiddleware for testing
class MockAuthService {
    private $session;
    
    public function __construct(&$session) {
        $this->session = &$session;
    }
    
    public function check(): bool {
        return isset($this->session['logged_in']) && $this->session['logged_in'] === true;
    }
    
    public function user(): ?array {
        if (!$this->check()) {
            return null;
        }
        return [
            'id' => $this->session['user_id'] ?? null,
            'name' => $this->session['user_name'] ?? null,
            'email' => $this->session['user_email'] ?? null,
            'role' => $this->session['user_role'] ?? null,
        ];
    }
    
    public function hasRole(string $role): bool {
        if (!$this->check()) {
            return false;
        }
        return isset($this->session['user_role']) && $this->session['user_role'] === $role;
    }
    
    public function isSuperAdmin(): bool {
        return $this->hasRole('super_admin');
    }
    
    public function isAdmin(): bool {
        return $this->hasRole('admin') || $this->hasRole('super_admin');
    }
}

class MockCsrfMiddleware {
    private static $token = null;
    
    public static function generateToken(): string {
        if (self::$token === null) {
            self::$token = bin2hex(random_bytes(32));
        }
        return self::$token;
    }
    
    public static function getToken(): ?string {
        return self::$token;
    }
    
    public static function verifyToken(string $token): bool {
        if (self::$token === null) {
            return false;
        }
        return hash_equals(self::$token, $token);
    }
}

// Simplified ApiAuthMiddleware for testing (uses mocks)
class TestApiAuthMiddleware {
    private $authService;
    private $requireAuth = true;
    private $requireCsrf = true;
    private $requiredRole = null;

    public function __construct(array $options = [], $authService = null)
    {
        $this->authService = $authService;
        $this->requireAuth = $options['auth'] ?? true;
        $this->requireCsrf = $options['csrf'] ?? true;
        $this->requiredRole = $options['role'] ?? null;
    }

    public function handle(): void
    {
        if ($this->requireAuth && !$this->authService->check()) {
            $this->fail(401, 'Authentication required');
        }

        if (!$this->requireAuth && !$this->authService->check()) {
            return;
        }

        if ($this->requiredRole !== null) {
            $this->checkRole();
        }

        if ($this->requireCsrf && $this->isMutatingRequest()) {
            $this->validateCsrf();
        }
    }

    private function isMutatingRequest(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function validateCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? null;
        }

        if (!$token || !MockCsrfMiddleware::verifyToken($token)) {
            $this->fail(419, 'CSRF token mismatch. Please refresh and try again.');
        }
    }

    private function checkRole(): void
    {
        $hasRole = false;

        switch ($this->requiredRole) {
            case 'super_admin':
                $hasRole = $this->authService->isSuperAdmin();
                break;
            case 'admin':
                $hasRole = $this->authService->isAdmin();
                break;
            default:
                $hasRole = $this->authService->hasRole($this->requiredRole);
                break;
        }

        if (!$hasRole) {
            $this->fail(403, 'Insufficient permissions');
        }
    }

    private function fail(int $statusCode, string $message): void
    {
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

    public function withoutAuth(): self
    {
        $this->requireAuth = false;
        return $this;
    }

    public function withoutCsrf(): self
    {
        $this->requireCsrf = false;
        return $this;
    }

    public function requireRole(string $role): self
    {
        $this->requiredRole = $role;
        return $this;
    }

    public function requireSuperAdmin(): self
    {
        return $this->requireRole('super_admin');
    }

    public function requireAdmin(): self
    {
        return $this->requireRole('admin');
    }

    public function user(): ?array
    {
        return $this->authService->user();
    }

    public function check(): bool
    {
        return $this->authService->check();
    }
}

// Test helper functions
function setupSession(array &$session, array $data = []) {
    $session = array_merge([
        'logged_in' => false,
        'user_id' => null,
        'user_email' => null,
        'user_name' => null,
        'user_role' => null,
        'login_time' => time(),
        'csrf_token' => MockCsrfMiddleware::generateToken(),
    ], $data);
}

function setRequestMethod(string $method) {
    $_SERVER['REQUEST_METHOD'] = $method;
}

function setCsrfHeader(string $token) {
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
}

function setCsrfPost(string $token) {
    $_POST['csrf_token'] = $token;
}

function captureOutput(callable $fn): string {
    ob_start();
    try {
        $fn();
    } catch (Exception $e) {
        // Ignore exit() calls
    }
    return ob_get_clean();
}

echo "=== ApiAuthMiddleware Tests (Mocked) ===\n\n";

$passed = 0;
$failed = 0;

function runTest(string $name, callable $test) {
    global $passed, $failed;
    echo "Test: $name\n";
    try {
        $result = $test();
        if ($result) {
            echo "  PASS\n";
            $passed++;
        } else {
            echo "  FAIL\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
        $failed++;
    }
    echo "\n";
}

// Test 1: Unauthenticated request with auth required (default)
runTest("Unauthenticated request with auth required", function() {
    $session = [];
    setupSession($session, ['logged_in' => false]);
    $_SESSION = &$session;
    setRequestMethod('GET');
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
    });
    return strpos($output, '"code":401') !== false;
});

// Test 2: Authenticated request (GET, no CSRF needed)
runTest("Authenticated GET request (no CSRF needed)", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('GET');
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 3: Authenticated POST without CSRF token
runTest("Authenticated POST without CSRF token", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('POST');
    $_POST = [];
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'csrf' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
    });
    return strpos($output, '"code":419') !== false;
});

// Test 4: Authenticated POST with valid CSRF token in header
runTest("Authenticated POST with valid CSRF token in X-CSRF-Token header", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('POST');
    $token = $session['csrf_token'];
    setCsrfHeader($token);
    $_POST = [];
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'csrf' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 5: Authenticated POST with valid CSRF token in form field
runTest("Authenticated POST with valid CSRF token in form field", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('POST');
    $token = $session['csrf_token'];
    setCsrfPost($token);
    $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'csrf' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 6: Authenticated POST with invalid CSRF token
runTest("Authenticated POST with invalid CSRF token", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('POST');
    setCsrfHeader('invalid-token');
    $_POST = [];
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'csrf' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
    });
    return strpos($output, '"code":419') !== false;
});

// Test 7: GET request does not require CSRF
runTest("GET request does not require CSRF", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('GET');
    $_POST = [];
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'csrf' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 8: Role check - super_admin required, user is admin
runTest("Role check - super_admin required, user is admin", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('GET');
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'role' => 'super_admin'], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
    });
    return strpos($output, '"code":403') !== false;
});

// Test 9: Role check - admin required, user is admin
runTest("Role check - admin required, user is admin", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('GET');
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'role' => 'admin'], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 10: Role check - super_admin required, user is super_admin
runTest("Role check - super_admin required, user is super_admin", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'super_admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('GET');
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'role' => 'super_admin'], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 11: Fluent API - withoutAuth()
runTest("Fluent API - withoutAuth()", function() {
    $session = [];
    setupSession($session, ['logged_in' => false]);
    $_SESSION = &$session;
    setRequestMethod('GET');
    
    $authService = new MockAuthService($session);
    $mw = new TestApiAuthMiddleware(['auth' => true], $authService);
    $middleware = $mw->withoutAuth();
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 12: Fluent API - withoutCsrf()
runTest("Fluent API - withoutCsrf()", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('POST');
    $_POST = [];
    
    $authService = new MockAuthService($session);
    $mw = new TestApiAuthMiddleware(['auth' => true, 'csrf' => true], $authService);
    $middleware = $mw->withoutCsrf();
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 13: Fluent API - requireSuperAdmin()
runTest("Fluent API - requireSuperAdmin()", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'super_admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('GET');
    
    $authService = new MockAuthService($session);
    $mw = new TestApiAuthMiddleware([], $authService);
    $middleware = $mw->requireSuperAdmin();
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 14: Fluent API - requireAdmin() with admin user
runTest("Fluent API - requireAdmin() with admin user", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('GET');
    
    $authService = new MockAuthService($session);
    $mw = new TestApiAuthMiddleware([], $authService);
    $middleware = $mw->requireAdmin();
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 15: Fluent API - requireAdmin() with super_admin user
runTest("Fluent API - requireAdmin() with super_admin user", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'super_admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('GET');
    
    $authService = new MockAuthService($session);
    $mw = new TestApiAuthMiddleware([], $authService);
    $middleware = $mw->requireAdmin();
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 16: JSON response structure validation
runTest("JSON response structure for 401", function() {
    $session = [];
    setupSession($session, ['logged_in' => false]);
    $_SESSION = &$session;
    setRequestMethod('GET');
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
    });
    
    $hasSuccess = strpos($output, '"success":false') !== false;
    $hasError = strpos($output, '"error"') !== false;
    $hasCode = strpos($output, '"code":401') !== false;
    return $hasSuccess && $hasError && $hasCode;
});

// Test 17: PUT method requires CSRF
runTest("PUT method requires CSRF", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('PUT');
    $_POST = [];
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'csrf' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
    });
    return strpos($output, '"code":419') !== false;
});

// Test 18: PATCH method requires CSRF
runTest("PATCH method requires CSRF", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('PATCH');
    $_POST = [];
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'csrf' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
    });
    return strpos($output, '"code":419') !== false;
});

// Test 19: DELETE method requires CSRF
runTest("DELETE method requires CSRF", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('DELETE');
    $_POST = [];
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'csrf' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
    });
    return strpos($output, '"code":419') !== false;
});

// Test 20: HEAD method does not require CSRF
runTest("HEAD method does not require CSRF", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('HEAD');
    $_POST = [];
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'csrf' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 21: OPTIONS method does not require CSRF
runTest("OPTIONS method does not require CSRF", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 1,
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    setRequestMethod('OPTIONS');
    $_POST = [];
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware(['auth' => true, 'csrf' => true], $authService);
    $output = captureOutput(function() use ($middleware) {
        $middleware->handle();
        echo "  Middleware passed\n";
    });
    return strpos($output, 'Middleware passed') !== false;
});

// Test 22: user() method returns user data when authenticated
runTest("user() method returns user data when authenticated", function() {
    $session = [];
    setupSession($session, [
        'logged_in' => true,
        'user_id' => 42,
        'user_email' => 'api@example.com',
        'user_name' => 'API User',
        'user_role' => 'admin',
    ]);
    $_SESSION = &$session;
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware([], $authService);
    $user = $middleware->user();
    
    return $user['id'] === 42
        && $user['email'] === 'api@example.com'
        && $user['name'] === 'API User'
        && $user['role'] === 'admin';
});

// Test 23: user() method returns null when not authenticated
runTest("user() method returns null when not authenticated", function() {
    $session = [];
    setupSession($session, ['logged_in' => false]);
    $_SESSION = &$session;
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware([], $authService);
    $user = $middleware->user();
    
    return $user === null;
});

// Test 24: check() method returns true when authenticated
runTest("check() method returns true when authenticated", function() {
    $session = [];
    setupSession($session, ['logged_in' => true]);
    $_SESSION = &$session;
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware([], $authService);
    $result = $middleware->check();
    
    return $result === true;
});

// Test 25: check() method returns false when not authenticated
runTest("check() method returns false when not authenticated", function() {
    $session = [];
    setupSession($session, ['logged_in' => false]);
    $_SESSION = &$session;
    
    $authService = new MockAuthService($session);
    $middleware = new TestApiAuthMiddleware([], $authService);
    $result = $middleware->check();
    
    return $result === false;
});

echo "=== Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n";