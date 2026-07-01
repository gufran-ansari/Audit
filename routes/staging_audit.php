<?php
/**
 * Staging Audit Routes
 * 
 * Handles staging URL audit module requests
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\StagingAudit\StagingAuditController;

$controller = new StagingAuditController();
$controller->handle();
