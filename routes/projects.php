<?php
/**
 * Projects API Routes
 * 
 * Handles all project-related API requests
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\Projects\ProjectController;

// Create controller instance and handle request
$controller = new ProjectController();
$controller->handle();
