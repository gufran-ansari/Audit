<?php
/**
 * Report Management Routes (admin, auth-protected)
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\Reports\ReportController;

$controller = new ReportController();
$controller->handle();
