<?php

namespace App\Modules\Overrides;

use App\Services\AuthService;

/**
 * Override Service
 * 
 * Business logic for manual audit overrides.
 * Validates permissions, processes overrides, logs history.
 */
class OverrideService
{
    private OverrideRepository $repository;
    private AuthService $authService;

    /** Valid override states */
    private const VALID_STATUSES = ['ignored', 'fixed', 'accepted', 'false_positive', 'needs_review'];

    /** Valid module names */
    private const VALID_MODULES = [
        'h1_audit', 'meta_audit', 'noindex_audit', 'faq_schema_audit',
        'lorem_audit', 'staging_audit', 'word_occurrence_audit'
    ];

    public function __construct()
    {
        $this->repository = new OverrideRepository();
        $this->authService = new AuthService();
    }

    /**
     * Create or update a manual override.
     */
    public function setOverride(int $pageId, string $moduleName, string $originalStatus, string $overrideStatus, ?string $notes = null): array
    {
        // Validate module
        if (!in_array($moduleName, self::VALID_MODULES)) {
            return ['success' => false, 'error' => 'Invalid module name'];
        }

        // Validate override status
        if (!in_array($overrideStatus, self::VALID_STATUSES)) {
            return ['success' => false, 'error' => 'Invalid override status'];
        }

        // Validate original status
        $validOriginals = ['PASS', 'WARNING', 'FAIL', 'ERROR'];
        if (!in_array($originalStatus, $validOriginals)) {
            return ['success' => false, 'error' => 'Invalid original status'];
        }

        // Get current user
        $user = $this->authService->user();
        if (!$user) {
            return ['success' => false, 'error' => 'Not authenticated'];
        }

        // Check if an existing override exists to capture old status
        $existing = $this->repository->findByPageAndModule($pageId, $moduleName);
        $oldStatus = $existing ? $existing['override_status'] : null;

        // Upsert the override
        $overrideId = $this->repository->upsert(
            $pageId, $moduleName, $originalStatus, $overrideStatus, $notes, (int) $user['id']
        );

        // Log history
        $this->repository->logHistory(
            $overrideId, $pageId, $moduleName, $oldStatus, $overrideStatus, $notes, (int) $user['id']
        );

        // Fetch the updated override
        $override = $this->repository->findByPageAndModule($pageId, $moduleName);

        return ['success' => true, 'override' => $override];
    }

    /**
     * Remove an override (revert to original automated status).
     */
    public function removeOverride(int $pageId, string $moduleName): array
    {
        $existing = $this->repository->findByPageAndModule($pageId, $moduleName);
        if (!$existing) {
            return ['success' => false, 'error' => 'Override not found'];
        }

        $user = $this->authService->user();
        if (!$user) {
            return ['success' => false, 'error' => 'Not authenticated'];
        }

        // Log history before deletion
        $this->repository->logHistory(
            (int) $existing['id'], $pageId, $moduleName,
            $existing['override_status'], 'removed', 'Override reverted to original', (int) $user['id']
        );

        $this->repository->delete((int) $existing['id']);

        return ['success' => true, 'message' => 'Override removed'];
    }

    /**
     * Get overrides for a specific page.
     */
    public function getPageOverrides(int $pageId): array
    {
        return $this->repository->findByPage($pageId);
    }

    /**
     * Get override for a specific page + module.
     */
    public function getOverride(int $pageId, string $moduleName): ?array
    {
        return $this->repository->findByPageAndModule($pageId, $moduleName);
    }

    /**
     * Get override history for a page + module.
     */
    public function getHistory(int $pageId, string $moduleName): array
    {
        return $this->repository->getHistoryByPageModule($pageId, $moduleName);
    }

    /**
     * Get all overrides for a project.
     */
    public function getProjectOverrides(int $projectId, array $filters = []): array
    {
        return $this->repository->findByProject($projectId, $filters);
    }

    /**
     * Get override summary counts for a project.
     */
    public function getProjectSummary(int $projectId): array
    {
        return $this->repository->getProjectSummary($projectId);
    }

    /**
     * Get override map for a project (for dashboard table display).
     */
    public function getOverrideMap(int $projectId): array
    {
        return $this->repository->getOverrideMap($projectId);
    }

    /**
     * Get available override statuses.
     */
    public function getAvailableStatuses(): array
    {
        return [
            ['key' => 'ignored', 'label' => 'Ignored', 'color' => 'gray', 'icon' => 'bi-eye-slash'],
            ['key' => 'fixed', 'label' => 'Fixed', 'color' => 'green', 'icon' => 'bi-check-circle'],
            ['key' => 'accepted', 'label' => 'Accepted', 'color' => 'blue', 'icon' => 'bi-hand-thumbs-up'],
            ['key' => 'false_positive', 'label' => 'False Positive', 'color' => 'purple', 'icon' => 'bi-flag'],
            ['key' => 'needs_review', 'label' => 'Needs Review', 'color' => 'orange', 'icon' => 'bi-hourglass-split'],
        ];
    }
}
