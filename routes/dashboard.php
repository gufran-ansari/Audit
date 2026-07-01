<?php
/**
 * Dashboard API Routes (auth-protected)
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\Dashboard\DashboardController;

$controller = new DashboardController();
$controller->handle();
