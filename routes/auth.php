<?php
/**
 * Authentication Routes
 * 
 * Handles authentication-related requests
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Controllers\AuthController;

$controller = new AuthController();
$action = input('action');

// Route handling
switch ($action) {
    case 'login':
        if (isMethod('POST')) {
            $controller->login();
        } else {
            redirect('/login.php');
        }
        break;
        
    case 'logout':
        $controller->logout();
        break;
        
    case 'api_login':
        if (isMethod('POST')) {
            $controller->apiLogin();
        } else {
            jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
        }
        break;
        
    default:
        redirect('/login.php');
        break;
}
