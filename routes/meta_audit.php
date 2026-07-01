<?php
/**
 * Meta Audit Routes
 * 
 * Handles meta audit module requests
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\MetaAudit\MetaAuditController;

$controller = new MetaAuditController();
$controller->handle();
