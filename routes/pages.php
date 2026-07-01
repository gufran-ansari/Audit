<?php
/**
 * Pages API Routes
 * 
 * Handles all page/URL-related API requests
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\Pages\PageController;

// Create controller instance and handle request
$controller = new PageController();
$controller->handle();
