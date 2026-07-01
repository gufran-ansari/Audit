<?php
/**
 * Application Entry Point
 *
 * The single dynamic entry for the app. Sends authenticated users to the
 * dashboard and everyone else to the login page. There is no static markup
 * here — all UI lives in the dynamic PHP pages (dashboard.php, projects.php,
 * project.php, settings.php, report.php).
 */

require __DIR__ . '/app/bootstrap.php';

use App\Services\AuthService;

$authService = new AuthService();

if ($authService->check()) {
    redirect('/dashboard.php');
} else {
    redirect('/login.php');
}
