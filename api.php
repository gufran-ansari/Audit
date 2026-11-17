<?php
/**
 * Public API Entry Point
 * 
 * Routes API requests to the appropriate controller.
 * This is the single public endpoint for all authenticated API operations.
 * Internal route files in /routes/ remain protected by .htaccess.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Modules\Projects\ProjectController;
use App\Modules\Pages\PageController;
use App\Modules\Sitemaps\SitemapController;
use App\Modules\Dashboard\DashboardController;
use App\Modules\AuditEngine\AuditEngineController;
use App\Modules\Overrides\OverrideController;
use App\Modules\Settings\SettingsController;
use App\Modules\Reports\ReportController;
use App\Modules\WordOccurrenceAudit\WordOccurrenceController;
use App\Modules\H1Audit\H1AuditController;
use App\Modules\MetaAudit\MetaAuditController;
use App\Modules\NoindexAudit\NoindexAuditController;
use App\Modules\FaqSchemaAudit\FaqSchemaAuditController;
use App\Modules\LoremAudit\LoremAuditController;
use App\Modules\StagingAudit\StagingAuditController;
use App\Controllers\AuthController;

// Parse the request path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim($path, '/');

// Remove the base path if present (e.g., /api/...)
if (strpos($path, 'api/') === 0) {
    $path = substr($path, 4);
}

// Split path into segments
$segments = array_filter(explode('/', $path));
$module = array_shift($segments);

// Map module names to controllers
$controllerMap = [
    'projects' => ProjectController::class,
    'pages' => PageController::class,
    'sitemaps' => SitemapController::class,
    'dashboard' => DashboardController::class,
    'audit_engine' => AuditEngineController::class,
    'overrides' => OverrideController::class,
    'settings' => SettingsController::class,
    'reports' => ReportController::class,
    'jobs' => null, // jobs.php uses functional style, handle separately
    'health' => null, // health.php uses functional style, handle separately
    'auth' => AuthController::class,
    'word_occurrence_audit' => WordOccurrenceController::class,
    'h1_audit' => H1AuditController::class,
    'meta_audit' => MetaAuditController::class,
    'noindex_audit' => NoindexAuditController::class,
    'faq_schema_audit' => FaqSchemaAuditController::class,
    'lorem_audit' => LoremAuditController::class,
    'staging_audit' => StagingAuditController::class,
];

// Handle functional-style route files (jobs.php, health.php)
$functionalRoutes = ['jobs', 'health'];

if (in_array($module, $functionalRoutes)) {
    $routeFile = __DIR__ . '/routes/' . $module . '.php';
    if (file_exists($routeFile)) {
        // Include the route file directly - it will handle its own routing
        require $routeFile;
        exit;
    }
    http_response_code(404);
    jsonResponse(['error' => 'API endpoint not found'], 404);
    exit;
}

// Handle controller-based routes
if (isset($controllerMap[$module])) {
    $controllerClass = $controllerMap[$module];
    if ($controllerClass) {
        $controller = new $controllerClass();
        $controller->handle();
        exit;
    }
}

// Fallback: try to include the route file directly for any other module
$routeFile = __DIR__ . '/routes/' . $module . '.php';
if (file_exists($routeFile)) {
    require $routeFile;
    exit;
}

http_response_code(404);
jsonResponse(['error' => 'API endpoint not found: ' . htmlspecialchars($module)], 404);