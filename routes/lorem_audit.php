<?php
/**
 * Lorem Audit Routes
 * 
 * Handles lorem ipsum audit module requests
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\LoremAudit\LoremAuditController;

$controller = new LoremAuditController();
$controller->handle();
