<?php
/**
 * Login Page
 * 
 * Entry point for user authentication
 */

require __DIR__ . '/app/bootstrap.php';

use App\Controllers\AuthController;

$controller = new AuthController();

if (isMethod('POST')) {
    $controller->login();
}

if (input('action') === 'logout') {
    $controller->logout();
}

$controller->showLogin();
