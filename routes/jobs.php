<?php
/**
 * Job Routes
 * 
 * Handles background job management and worker invocation.
 * Actions:
 *   - create: Create a new audit job (returns to UI immediately)
 *   - process: Process the next queued job (called by cron/worker)
 *   - run: Process a specific job
 *   - status: Get job status
 *   - list: List jobs for a project
 *   - pause: Pause a running job
 *   - cancel: Cancel/fail a job
 *   - logs: Get job logs
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Middleware\ApiAuthMiddleware;
use App\Services\AuthService;
use App\Services\JobService;
use App\Services\ModuleRegistry;
use App\Jobs\JobProcessor;
use App\Modules\WordOccurrenceAudit\WordOccurrenceValidator;

// Check if this is a cron request (no HTTP referer, specific user agent, or CLI)
// Cron bypasses HTTP entirely by calling JobProcessor directly
$isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0 || strpos($_SERVER['REQUEST_URI'] ?? '', '/api?') !== false;
$isCronRequest = php_sapi_name() === 'cli' || 
    (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'cron') !== false) ||
    (isset($_SERVER['HTTP_X_CRON']) && $_SERVER['HTTP_X_CRON'] === '1');

// Use new ApiAuthMiddleware for consistent API authentication
$apiAuth = new ApiAuthMiddleware([
    'auth' => true,
    'csrf' => !$isCronRequest, // Disable CSRF for cron requests
    'api' => $isApiRequest
]);
$apiAuth->handle();

$action = input('action', 'list');
$method = isMethod('POST') ? 'POST' : 'GET';

$jobService = new JobService();

switch ($action) {
    case 'create':
        handleCreate($jobService);
        break;

    case 'process':
        // Require POST for process action (mutates job queue state)
        if ($method !== 'POST') {
            jsonResponse(['error' => 'Method not allowed. Use POST.'], 405);
        }
        handleProcess();
        break;

    case 'run':
        handleRun();
        break;

    case 'status':
        handleStatus($jobService);
        break;

    case 'list':
        handleList($jobService);
        break;

    case 'counts':
        handleCounts($jobService);
        break;

    case 'pause':
        handlePause($jobService);
        break;

    case 'cancel':
        handleCancel($jobService);
        break;

    case 'logs':
        handleLogs($jobService);
        break;

    case 'resume':
        handleResume();
        break;

    case 'resume_all':
        handleResumeAll();
        break;

    case 'retry':
        handleRetry();
        break;

    case 'retry_stats':
        handleRetryStats();
        break;

    case 'recoverable':
        handleRecoverable();
        break;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

// ─── HANDLERS ──────────────────────────────────────────────────

function handleCreate(JobService $jobService): void
{
    if (!isMethod('POST')) {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }

    $projectId = (int) input('project_id');
    if (!$projectId) {
        jsonResponse(['error' => 'Project ID is required'], 400);
        return;
    }

    // Parse module keys
    $modulesInput = input('modules');
    if (is_string($modulesInput)) {
        $moduleKeys = array_filter(array_map('trim', explode(',', $modulesInput)));
    } elseif (is_array($modulesInput)) {
        $moduleKeys = $modulesInput;
    } else {
        jsonResponse(['error' => 'Modules are required'], 400);
        return;
    }

    // Validate module keys
    $moduleKeys = array_values(array_filter($moduleKeys, fn($k) => ModuleRegistry::exists($k)));
    if (empty($moduleKeys)) {
        jsonResponse(['error' => 'No valid modules selected'], 400);
        return;
    }

    // Build module params
    $moduleParams = [];
    if (in_array('word', $moduleKeys, true)) {
        $keywordInput = input('keyword');
        $validator = new WordOccurrenceValidator();
        $keywords = $validator->parseKeywords($keywordInput);
        if (empty($keywords)) {
            jsonResponse(['error' => 'Keywords required for Word Occurrence audit'], 400);
            return;
        }
        $moduleParams['word'] = ['keywords' => $keywords];
    }

    // Check if project already has running jobs
    if ($jobService->isProjectBusy($projectId)) {
        jsonResponse(['error' => 'Project already has running or queued jobs. Wait for them to finish.'], 422);
        return;
    }

    // Get user
    $authService = new AuthService();
    $user = $authService->user();
    $userId = $user['id'] ?? null;

    // Create the job
    $job = $jobService->createJob($projectId, $moduleKeys, [
        'module_params' => !empty($moduleParams) ? $moduleParams : null,
        'user_id' => $userId,
        'batch_size' => (int) (input('batch_size') ?: 25),
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Audit job created and queued',
        'job' => $job,
    ]);
}

function handleProcess(): void
{
    // Increase time limit for background processing
    set_time_limit(120);

    $processor = new JobProcessor();
    $processor->setMaxExecutionSeconds(55);

    $jobId = $processor->processNext();

    if ($jobId === null) {
        jsonResponse([
            'success' => true,
            'message' => 'No jobs to process',
            'job_id' => null,
        ]);
    } else {
        $jobService = new JobService();
        $job = $jobService->getById($jobId);

        jsonResponse([
            'success' => true,
            'message' => 'Processed job batch',
            'job_id' => $jobId,
            'job' => $job,
        ]);
    }
}

function handleRun(): void
{
    if (!isMethod('POST')) {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }

    $jobId = (int) input('job_id');
    if (!$jobId) {
        jsonResponse(['error' => 'Job ID is required'], 400);
        return;
    }

    set_time_limit(120);

    $processor = new JobProcessor();
    $processor->setMaxExecutionSeconds(55);

    $result = $processor->runJob($jobId);

    $jobService = new JobService();
    $job = $jobService->getById($jobId);

    jsonResponse([
        'success' => true,
        'job' => $job,
    ]);
}

function handleStatus(JobService $jobService): void
{
    $jobId = (int) input('job_id');
    if (!$jobId) {
        jsonResponse(['error' => 'Job ID is required'], 400);
        return;
    }

    $job = $jobService->getById($jobId);
    if (!$job) {
        jsonResponse(['error' => 'Job not found'], 404);
        return;
    }

    jsonResponse([
        'success' => true,
        'job' => $job,
    ]);
}

function handleList(JobService $jobService): void
{
    $projectId = (int) input('project_id');
    if (!$projectId) {
        jsonResponse(['error' => 'Project ID is required'], 400);
        return;
    }

    $jobs = $jobService->getByProject($projectId);

    jsonResponse([
        'success' => true,
        'jobs' => $jobs,
    ]);
}

function handleCounts(JobService $jobService): void
{
    $projectId = (int) input('project_id');
    if (!$projectId) {
        jsonResponse(['error' => 'Project ID is required'], 400);
        return;
    }

    $counts = $jobService->getStatusCounts($projectId);

    jsonResponse([
        'success' => true,
        'counts' => $counts,
    ]);
}

function handlePause(JobService $jobService): void
{
    if (!isMethod('POST')) {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }

    $jobId = (int) input('job_id');
    if (!$jobId) {
        jsonResponse(['error' => 'Job ID is required'], 400);
        return;
    }

    $success = $jobService->pauseJob($jobId);

    jsonResponse([
        'success' => $success,
        'message' => $success ? 'Job paused' : 'Could not pause job (may not be running)',
    ]);
}

function handleCancel(JobService $jobService): void
{
    if (!isMethod('POST')) {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }

    $jobId = (int) input('job_id');
    if (!$jobId) {
        jsonResponse(['error' => 'Job ID is required'], 400);
        return;
    }

    $success = $jobService->failJob($jobId, 'Cancelled by user');

    jsonResponse([
        'success' => $success,
        'message' => $success ? 'Job cancelled' : 'Could not cancel job',
    ]);
}

function handleLogs(JobService $jobService): void
{
    $jobId = (int) input('job_id');
    if (!$jobId) {
        jsonResponse(['error' => 'Job ID is required'], 400);
        return;
    }

    $logs = $jobService->getLogs($jobId);

    jsonResponse([
        'success' => true,
        'logs' => $logs,
    ]);
}

// ─── RETRY & RESUME HANDLERS ───────────────────────────────────

function handleResume(): void
{
    if (!isMethod('POST')) {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }

    $jobId = (int) input('job_id');
    if (!$jobId) {
        jsonResponse(['error' => 'Job ID is required'], 400);
        return;
    }

    $resumeService = new \App\Services\ResumeService();
    $success = $resumeService->resumeJob($jobId);

    jsonResponse([
        'success' => $success,
        'message' => $success ? 'Job resumed and re-queued' : 'Could not resume job',
    ]);
}

function handleResumeAll(): void
{
    if (!isMethod('POST')) {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }

    $projectId = (int) input('project_id');
    if (!$projectId) {
        jsonResponse(['error' => 'Project ID is required'], 400);
        return;
    }

    $resumeService = new \App\Services\ResumeService();
    $count = $resumeService->resumeAllInterrupted($projectId);

    jsonResponse([
        'success' => true,
        'message' => "{$count} job(s) resumed",
        'resumed_count' => $count,
    ]);
}

function handleRetry(): void
{
    if (!isMethod('POST')) {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }

    $projectId = (int) input('project_id');
    if (!$projectId) {
        jsonResponse(['error' => 'Project ID is required'], 400);
        return;
    }

    // Parse modules
    $modulesInput = input('modules');
    if (is_string($modulesInput)) {
        $moduleKeys = array_filter(array_map('trim', explode(',', $modulesInput)));
    } elseif (is_array($modulesInput)) {
        $moduleKeys = $modulesInput;
    } else {
        jsonResponse(['error' => 'Modules are required'], 400);
        return;
    }

    $moduleKeys = array_values(array_filter($moduleKeys, fn($k) => \App\Services\ModuleRegistry::exists($k)));
    if (empty($moduleKeys)) {
        jsonResponse(['error' => 'No valid modules selected'], 400);
        return;
    }

    // Build module params for word module
    $moduleParams = null;
    if (in_array('word', $moduleKeys, true)) {
        $keywordInput = input('keyword');
        if ($keywordInput) {
            $validator = new \App\Modules\WordOccurrenceAudit\WordOccurrenceValidator();
            $keywords = $validator->parseKeywords($keywordInput);
            if (!empty($keywords)) {
                $moduleParams = ['word' => ['keywords' => $keywords]];
            }
        }
    }

    $authService = new \App\Services\AuthService();
    $user = $authService->user();

    $resumeService = new \App\Services\ResumeService();
    $job = $resumeService->createRetryJob($projectId, $moduleKeys, [
        'user_id' => $user['id'] ?? null,
        'module_params' => $moduleParams,
    ]);

    if (!$job) {
        jsonResponse([
            'success' => true,
            'message' => 'No pages need retrying',
            'job' => null,
        ]);
        return;
    }

    jsonResponse([
        'success' => true,
        'message' => 'Retry job created',
        'job' => $job,
    ]);
}

function handleRetryStats(): void
{
    $projectId = (int) input('project_id');
    if (!$projectId) {
        jsonResponse(['error' => 'Project ID is required'], 400);
        return;
    }

    $retryService = new \App\Services\RetryService();
    $stats = $retryService->getRetryStats($projectId);
    $errorTypes = $retryService->getErrorTypeStats($projectId);

    jsonResponse([
        'success' => true,
        'stats' => $stats,
        'error_types' => $errorTypes,
    ]);
}

function handleRecoverable(): void
{
    $projectId = (int) input('project_id');
    if (!$projectId) {
        jsonResponse(['error' => 'Project ID is required'], 400);
        return;
    }

    $resumeService = new \App\Services\ResumeService();
    $info = $resumeService->hasRecoverableWork($projectId);

    jsonResponse([
        'success' => true,
        'recoverable' => $info,
    ]);
}
