<?php
/**
 * H1 Audit Routes
 * 
 * Handles H1 audit module requests
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\H1Audit\H1AuditController;

$controller = new H1AuditController();
$controller->handle();
