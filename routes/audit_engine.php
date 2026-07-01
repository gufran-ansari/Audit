<?php
/**
 * Audit Engine Routes
 * 
 * Central orchestration endpoint for all audit execution.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\AuditEngine\AuditEngineController;

$controller = new AuditEngineController();
$controller->handle();
