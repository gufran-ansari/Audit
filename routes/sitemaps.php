<?php
/**
 * Sitemaps API Routes
 * 
 * Handles all sitemap-related API requests
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Modules\Sitemaps\SitemapController;

// Create controller instance and handle request
$controller = new SitemapController();
$controller->handle();
