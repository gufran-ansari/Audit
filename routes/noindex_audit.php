<?php
/**
 * Noindex Audit Routes
 * 
 * Handles noindex audit module requests
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\NoindexAudit\NoindexAuditController;

$controller = new NoindexAuditController();
$controller->handle();
