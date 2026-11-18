<?php
/**
 * Cron: Process Audit Jobs
 * 
 * Picks up the next queued job and processes one batch.
 * Also runs processing safeguards (stale job detection, orphan cleanup).
 *
 * Designed to be called every minute by cron:
 *   * * * * * php /path/to/Audit/cron/process_jobs.php >> /path/to/Audit/storage/logs/cron.log 2>&1
 *
 * On shared hosting without cron, the frontend polls
 * `POST /api/jobs` with `action=process` and CSRF token to drive processing.
 */

// Set execution limits
set_time_limit(120);
ini_set('memory_limit', '256M');

// Generate correlation ID for this cron run
$cronRequestId = 'cron-' . bin2hex(random_bytes(8));
$_SERVER['REQUEST_ID'] = $cronRequestId;

require __DIR__ . '/../app/bootstrap.php';

use App\Jobs\JobProcessor;
use App\Services\ActivityLogService;
use App\Services\JobService;
use App\Services\HealthMonitorService;
use App\Services\LoggingService;

$timestamp = date('Y-m-d H:i:s');
$logger = LoggingService::getInstance();
$activityLog = ActivityLogService::getInstance();

// 1. Run safeguards (detect interrupted, stale, orphaned jobs)
$monitor = new HealthMonitorService();
$safeguardReport = $monitor->runSafeguards();

$safeguardActions = $safeguardReport['interrupted_marked'] + $safeguardReport['stale_failed'];
if ($safeguardActions > 0) {
    $activityLog->log(
        'cron_safeguards_run',
        "Cron safeguards executed",
        null,
        ['interrupted_marked' => $safeguardReport['interrupted_marked'], 'stale_failed' => $safeguardReport['stale_failed']],
        'system',
        null
    );
    echo "[{$timestamp}] Safeguards: {$safeguardReport['interrupted_marked']} interrupted, {$safeguardReport['stale_failed']} stale\n";
}

// 2. Process the next queued job
$processor = new JobProcessor();
$processor->setMaxExecutionSeconds(55);

$jobId = $processor->processNext();

$jobService = new JobService();

if ($jobId === null) {
    echo "[{$timestamp}] No jobs to process\n";
} else {
    $job = $jobService->getById($jobId);
    $status = $job['status'] ?? 'unknown';
    $processed = $job['processed_pages'] ?? 0;
    $total = $job['total_pages'] ?? 0;
    echo "[{$timestamp}] Processed job #{$jobId} - status: {$status}, progress: {$processed}/{$total}\n";
    $logger->job("Cron processed job #{$jobId}", ['status' => $status, 'progress' => "{$processed}/{$total}"]);
    
    $activityLog->log(
        'cron_job_processed',
        "Cron processed job batch",
        $job['project_id'] ?? null,
        ['job_id' => $jobId, 'status' => $status, 'progress' => "{$processed}/{$total}"],
        'job',
        $jobId
    );
}
