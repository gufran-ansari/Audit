<?php
/**
 * Security Dashboard API Routes (super_admin only)
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\Security\SecurityDashboardController;

$controller = new SecurityDashboardController();
$controller->handle();