<?php

namespace App\Modules\Settings;

use App\Models\User;
use Database\Database;

/**
 * Settings Service
 * 
 * Business logic for application settings and admin management.
 */
class SettingsService
{
    private SettingsRepository $repository;
    private SettingsValidator $validator;

    public function __construct()
    {
        $this->repository = new SettingsRepository();
        $this->validator = new SettingsValidator();
    }

    // ==========================================
    // SETTINGS MANAGEMENT
    // ==========================================

    /**
     * Get all settings grouped by category.
     */
    public function getAllGrouped(): array
    {
        return $this->repository->getAllGrouped();
    }

    /**
     * Get settings for a specific category.
     */
    public function getCategory(string $category): array
    {
        return $this->repository->getByCategory($category);
    }

    /**
     * Get a single setting value.
     */
    public function get(string $key, $default = null): ?string
    {
        return $this->repository->get($key, $default);
    }

    /**
     * Update settings for a category.
     */
    public function updateCategory(string $category, array $values): array
    {
        $definitions = $this->repository->getByCategory($category);

        if (!$this->validator->validate($values, $definitions)) {
            return ['success' => false, 'errors' => $this->validator->getErrors()];
        }

        // Only update keys that exist in the category
        $validKeys = array_column($definitions, 'setting_key');
        $filtered = array_intersect_key($values, array_flip($validKeys));

        $updated = $this->repository->setMany($filtered);

        return ['success' => true, 'updated' => $updated];
    }

    /**
     * Get available categories.
     */
    public function getCategories(): array
    {
        return $this->repository->getCategories();
    }

    // ==========================================
    // ADMIN MANAGEMENT
    // ==========================================

    /**
     * Get all admin users.
     */
    public function getAdmins(array $filters = []): array
    {
        $userModel = new User();
        return $userModel->getAll($filters) ?: [];
    }

    /**
     * Create a new admin user.
     */
    public function createAdmin(array $data): array
    {
        if (!$this->validator->validateAdmin($data)) {
            return ['success' => false, 'errors' => $this->validator->getErrors()];
        }

        $userModel = new User();

        // Check email uniqueness
        $existing = $userModel->findByEmail($data['email']);
        if ($existing) {
            return ['success' => false, 'errors' => ['email' => 'Email already exists']];
        }

        $userId = $userModel->create([
            'name' => sanitize($data['name']),
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'admin',
            'status' => 'active',
        ]);

        if (!$userId) {
            return ['success' => false, 'errors' => ['general' => 'Failed to create user']];
        }

        return ['success' => true, 'user_id' => $userId];
    }

    /**
     * Update an admin user.
     */
    public function updateAdmin(int $userId, array $data): array
    {
        if (!$this->validator->validateAdmin($data, true)) {
            return ['success' => false, 'errors' => $this->validator->getErrors()];
        }

        $userModel = new User();
        $user = $userModel->findById($userId);

        if (!$user) {
            return ['success' => false, 'errors' => ['general' => 'User not found']];
        }

        // Check email uniqueness if changed
        if (isset($data['email']) && $data['email'] !== $user['email']) {
            $existing = $userModel->findByEmail($data['email']);
            if ($existing) {
                return ['success' => false, 'errors' => ['email' => 'Email already exists']];
            }
        }

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = sanitize($data['name']);
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (!empty($data['password'])) $updateData['password'] = $data['password'];
        if (isset($data['role'])) $updateData['role'] = $data['role'];

        if (empty($updateData)) {
            return ['success' => false, 'errors' => ['general' => 'No changes provided']];
        }

        $userModel->update($userId, $updateData);

        return ['success' => true];
    }

    /**
     * Deactivate an admin user.
     */
    public function deactivateAdmin(int $userId): array
    {
        $userModel = new User();
        $user = $userModel->findById($userId);

        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        if ($user['role'] === 'super_admin') {
            return ['success' => false, 'error' => 'Cannot deactivate super admin'];
        }

        $userModel->update($userId, ['status' => 'inactive']);
        return ['success' => true];
    }

    /**
     * Activate an admin user.
     */
    public function activateAdmin(int $userId): array
    {
        $userModel = new User();
        $user = $userModel->findById($userId);

        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        $userModel->update($userId, ['status' => 'active']);
        return ['success' => true];
    }

    /**
     * Reset admin password.
     */
    public function resetPassword(int $userId, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }

        $userModel = new User();
        $user = $userModel->findById($userId);

        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        $userModel->update($userId, ['password' => $newPassword]);
        return ['success' => true];
    }

    // ==========================================
    // SYSTEM INFORMATION
    // ==========================================

    /**
     * Get system information.
     */
    public function getSystemInfo(): array
    {
        $db = Database::getInstance();

        // Database version
        $dbVersion = $db->query("SELECT VERSION() as version");
        $dbVersionStr = $dbVersion ? $dbVersion[0]['version'] : 'Unknown';

        // Counts
        $projectCount = $db->query("SELECT COUNT(*) as cnt FROM projects");
        $pageCount = $db->query("SELECT COUNT(*) as cnt FROM project_pages");
        $auditCount = $db->query("SELECT COUNT(*) as cnt FROM page_audit_results");

        // Storage (rough estimate from data directory)
        $storageUsed = $this->getDirectorySize(__DIR__ . '/../../../storage');

        return [
            'php_version' => PHP_VERSION,
            'db_version' => $dbVersionStr,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS,
            'system_version' => $this->repository->get('system_version', '1.8.0'),
            'total_projects' => $projectCount ? (int) $projectCount[0]['cnt'] : 0,
            'total_urls' => $pageCount ? (int) $pageCount[0]['cnt'] : 0,
            'total_audits' => $auditCount ? (int) $auditCount[0]['cnt'] : 0,
            'storage_used' => $this->formatBytes($storageUsed),
            'max_upload_size' => ini_get('upload_max_filesize'),
            'memory_limit' => ini_get('memory_limit'),
            'timezone' => date_default_timezone_get(),
        ];
    }

    /**
     * Get directory size recursively.
     */
    private function getDirectorySize(string $path): int
    {
        if (!is_dir($path)) return 0;
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) $size += $file->getSize();
        }
        return $size;
    }

    /**
     * Format bytes to human readable.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
