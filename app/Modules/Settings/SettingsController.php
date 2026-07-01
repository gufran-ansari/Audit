<?php

namespace App\Modules\Settings;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

/**
 * Settings Controller
 * 
 * Handles all settings and admin management API requests.
 * Super Admin only.
 */
class SettingsController
{
    private SettingsService $service;
    private AuthMiddleware $authMiddleware;
    private RoleMiddleware $roleMiddleware;

    public function __construct()
    {
        $this->service = new SettingsService();
        $this->authMiddleware = new AuthMiddleware();
        $this->roleMiddleware = new RoleMiddleware();
    }

    public function handle(): void
    {
        // Require authentication
        $this->authMiddleware->handle();

        // Require super_admin role
        $this->roleMiddleware->superAdmin();

        $action = input('action', 'list');
        $method = isMethod('POST') ? 'POST' : 'GET';

        switch ($action) {
            // --- Settings ---
            case 'list':
                $this->listSettings();
                break;

            case 'category':
                $this->getCategory();
                break;

            case 'update':
                if ($method === 'POST') $this->updateSettings();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'get':
                $this->getSetting();
                break;

            // --- Admin Management ---
            case 'admins':
                $this->listAdmins();
                break;

            case 'admin_create':
                if ($method === 'POST') $this->createAdmin();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'admin_update':
                if ($method === 'POST') $this->updateAdmin();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'admin_deactivate':
                if ($method === 'POST') $this->deactivateAdmin();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'admin_activate':
                if ($method === 'POST') $this->activateAdmin();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'admin_reset_password':
                if ($method === 'POST') $this->resetPassword();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            // --- System Info ---
            case 'system_info':
                $this->systemInfo();
                break;

            case 'categories':
                $this->listCategories();
                break;

            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    // ==========================================
    // SETTINGS ENDPOINTS
    // ==========================================

    private function listSettings(): void
    {
        $grouped = $this->service->getAllGrouped();
        jsonResponse(['success' => true, 'settings' => $grouped]);
    }

    private function getCategory(): void
    {
        $category = input('category');
        if (!$category) {
            jsonResponse(['error' => 'Category required'], 400);
            return;
        }

        $settings = $this->service->getCategory($category);
        jsonResponse(['success' => true, 'settings' => $settings, 'category' => $category]);
    }

    private function updateSettings(): void
    {
        $category = input('category');
        $values = input('values');

        if (!$category || !is_array($values)) {
            jsonResponse(['error' => 'Category and values required'], 400);
            return;
        }

        $result = $this->service->updateCategory($category, $values);

        if ($result['success']) {
            jsonResponse(['success' => true, 'updated' => $result['updated']]);
        } else {
            jsonResponse(['success' => false, 'errors' => $result['errors']], 422);
        }
    }

    private function getSetting(): void
    {
        $key = input('key');
        if (!$key) {
            jsonResponse(['error' => 'Key required'], 400);
            return;
        }

        $value = $this->service->get($key);
        jsonResponse(['success' => true, 'key' => $key, 'value' => $value]);
    }

    private function listCategories(): void
    {
        $categories = $this->service->getCategories();
        jsonResponse(['success' => true, 'categories' => $categories]);
    }

    // ==========================================
    // ADMIN MANAGEMENT ENDPOINTS
    // ==========================================

    private function listAdmins(): void
    {
        $filters = [];
        if (input('status')) $filters['status'] = input('status');
        if (input('role')) $filters['role'] = input('role');

        $admins = $this->service->getAdmins($filters);
        jsonResponse(['success' => true, 'admins' => $admins]);
    }

    private function createAdmin(): void
    {
        $data = [
            'name' => input('name'),
            'email' => input('email'),
            'password' => input('password'),
            'role' => input('role', 'admin'),
        ];

        $result = $this->service->createAdmin($data);

        if ($result['success']) {
            jsonResponse(['success' => true, 'user_id' => $result['user_id']], 201);
        } else {
            jsonResponse(['success' => false, 'errors' => $result['errors']], 422);
        }
    }

    private function updateAdmin(): void
    {
        $userId = (int) input('user_id');
        if (!$userId) {
            jsonResponse(['error' => 'user_id required'], 400);
            return;
        }

        $data = [];
        if (input('name') !== null) $data['name'] = input('name');
        if (input('email') !== null) $data['email'] = input('email');
        if (input('password') !== null) $data['password'] = input('password');
        if (input('role') !== null) $data['role'] = input('role');

        $result = $this->service->updateAdmin($userId, $data);

        if ($result['success']) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'errors' => $result['errors']], 422);
        }
    }

    private function deactivateAdmin(): void
    {
        $userId = (int) input('user_id');
        if (!$userId) {
            jsonResponse(['error' => 'user_id required'], 400);
            return;
        }

        $result = $this->service->deactivateAdmin($userId);

        if ($result['success']) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => $result['error']], 422);
        }
    }

    private function activateAdmin(): void
    {
        $userId = (int) input('user_id');
        if (!$userId) {
            jsonResponse(['error' => 'user_id required'], 400);
            return;
        }

        $result = $this->service->activateAdmin($userId);

        if ($result['success']) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => $result['error']], 422);
        }
    }

    private function resetPassword(): void
    {
        $userId = (int) input('user_id');
        $password = input('password');

        if (!$userId || !$password) {
            jsonResponse(['error' => 'user_id and password required'], 400);
            return;
        }

        $result = $this->service->resetPassword($userId, $password);

        if ($result['success']) {
            jsonResponse(['success' => true, 'message' => 'Password reset successfully']);
        } else {
            jsonResponse(['success' => false, 'error' => $result['error']], 422);
        }
    }

    // ==========================================
    // SYSTEM INFO
    // ==========================================

    private function systemInfo(): void
    {
        $info = $this->service->getSystemInfo();
        jsonResponse(['success' => true, 'info' => $info]);
    }
}
