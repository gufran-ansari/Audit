<?php
/**
 * Health & Monitoring Routes
 * 
 * Project health checks, system status, and processing overview.
 * Actions:
 *   - project: Get project health status
 *   - system: Get system-wide health
 *   - safeguards: Run processing safeguards
 *   - metrics: Get recent performance metrics
 *   - logs: Get recent log entries
 *   - cleanup: Clean up old logs and resolved retries
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Middleware\ApiAuthMiddleware;
use App\Services\HealthMonitorService;
use App\Services\LoggingService;
use App\Services\RetryService;

// Use new ApiAuthMiddleware for consistent API authentication
$apiAuth = new ApiAuthMiddleware([
    'auth' => true,
    'csrf' => true,
    'api' => true
]);
$apiAuth->handle();

$action = input('action', 'project');

switch ($action) {
    case 'project':
        handleProjectHealth();
        break;

    case 'system':
        handleSystemHealth();
        break;

    case 'safeguards':
        handleSafeguards();
        break;

    case 'logs':
        handleLogs();
        break;

    case 'cleanup':
        handleCleanup();
        break;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleProjectHealth(): void
{
    $projectId = (int) input('project_id');
    if (!$projectId) {
        jsonResponse(['error' => 'Project ID is required'], 400);
        return;
    }

    $monitor = new HealthMonitorService();
    $health = $monitor->getProjectHealth($projectId);

    jsonResponse([
        'success' => true,
        'health' => $health,
    ]);
}

function handleSystemHealth(): void
{
    $monitor = new HealthMonitorService();
    $health = $monitor->getSystemHealth();

    jsonResponse([
        'success' => true,
        'health' => $health,
    ]);
}

function handleSafeguards(): void
{
    if (!isMethod('POST')) {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }

    $monitor = new HealthMonitorService();
    $report = $monitor->runSafeguards();

    jsonResponse([
        'success' => true,
        'report' => $report,
    ]);
}

function handleLogs(): void
{
    $category = input('category', 'system');
    $lines = (int) (input('lines') ?: 50);

    $logger = LoggingService::getInstance();
    $logs = $logger->getRecentLogs($category, min($lines, 200));

    jsonResponse([
        'success' => true,
        'category' => $category,
        'logs' => $logs,
    ]);
}

function handleCleanup(): void
{
    if (!isMethod('POST')) {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }

    $logger = LoggingService::getInstance();
    $logsDeleted = $logger->cleanupOldLogs(14);

    $retryService = new RetryService();
    $retryService->cleanupResolved(30);

    jsonResponse([
        'success' => true,
        'message' => "Cleanup complete. {$logsDeleted} old log files removed.",
    ]);
}
