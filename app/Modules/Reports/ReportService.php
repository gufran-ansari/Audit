<?php

namespace App\Modules\Reports;

/**
 * Report Service
 * 
 * Business logic for creating, managing, and generating reports.
 */
class ReportService
{
    private ReportRepository $repository;
    private ReportGenerator $generator;

    public function __construct()
    {
        $this->repository = new ReportRepository();
        $this->generator = new ReportGenerator();
    }

    /**
     * Get or create a report for a project.
     */
    public function getOrCreate(int $projectId, string $projectName): array
    {
        $report = $this->repository->findByProject($projectId);

        if ($report) {
            return $report;
        }

        $slug = $this->generateSlug($projectName);
        $this->repository->create($projectId, $slug, 'private');

        return $this->repository->findByProject($projectId);
    }

    /**
     * Get report by slug (for public access).
     * Returns null if report doesn't exist or is private.
     */
    public function getPublicReport(string $slug): ?array
    {
        $report = $this->repository->findBySlug($slug);

        if (!$report) {
            return null;
        }

        if ($report['visibility'] !== 'public') {
            return null;
        }

        return $report;
    }

    /**
     * Get report by slug regardless of visibility (for admin preview).
     */
    public function getReportBySlug(string $slug): ?array
    {
        return $this->repository->findBySlug($slug);
    }

    /**
     * Generate report data for a project.
     */
    public function generateData(int $projectId): array
    {
        $report = $this->repository->findByProject($projectId);
        if ($report) {
            $this->repository->touchGenerated((int) $report['id']);
        }

        return $this->generator->generate($projectId);
    }

    /**
     * Set report visibility.
     */
    public function setVisibility(int $projectId, string $visibility): bool
    {
        $report = $this->repository->findByProject($projectId);
        if (!$report) {
            return false;
        }

        return $this->repository->updateVisibility((int) $report['id'], $visibility);
    }

    /**
     * Update report slug.
     */
    public function updateSlug(int $projectId, string $newSlug): bool
    {
        $report = $this->repository->findByProject($projectId);
        if (!$report) {
            return false;
        }

        $newSlug = $this->sanitizeSlug($newSlug);
        if (empty($newSlug)) {
            return false;
        }

        return $this->repository->updateSlug((int) $report['id'], $newSlug);
    }

    /**
     * Generate a URL-friendly slug from project name.
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 40);

        // Append short random suffix for uniqueness
        $suffix = substr(bin2hex(random_bytes(2)), 0, 4);

        return $slug . '-' . $suffix;
    }

    /**
     * Sanitize a user-provided slug.
     */
    private function sanitizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return substr($slug, 0, 100);
    }
}
