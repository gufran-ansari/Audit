<?php
/**
 * Override API Routes (admin, auth-protected)
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\Overrides\OverrideController;

$controller = new OverrideController();
$controller->handle();
