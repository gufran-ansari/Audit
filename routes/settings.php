<?php
/**
 * Settings API Routes (Super Admin only)
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\Settings\SettingsController;

$controller = new SettingsController();
$controller->handle();
