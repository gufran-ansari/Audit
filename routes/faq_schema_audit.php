<?php
/**
 * FAQ Schema Audit Routes
 * 
 * Handles FAQ schema audit module requests
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\FaqSchemaAudit\FaqSchemaAuditController;

$controller = new FaqSchemaAuditController();
$controller->handle();
