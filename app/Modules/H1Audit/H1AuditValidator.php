<?php

namespace App\Modules\H1Audit;

/**
 * H1 Audit Validator
 * 
 * Validates H1 audit input and results
 */
class H1AuditValidator
{
    private array $errors = [];

    /**
     * Validate page ID
     */
    public function validatePageId(?int $pageId): bool
    {
        if (empty($pageId) || $pageId <= 0) {
            $this->errors[] = 'Invalid page ID';
            return false;
        }
        
        return true;
    }

    /**
     * Validate project ID
     */
    public function validateProjectId(?int $projectId): bool
    {
        if (empty($projectId) || $projectId <= 0) {
            $this->errors[] = 'Invalid project ID';
            return false;
        }
        
        return true;
    }

    /**
     * Validate page IDs array
     */
    public function validatePageIds(?array $pageIds): bool
    {
        if (empty($pageIds) || !is_array($pageIds)) {
            $this->errors[] = 'Invalid page IDs';
            return false;
        }

        foreach ($pageIds as $id) {
            if (!is_numeric($id) || $id <= 0) {
                $this->errors[] = 'Invalid page ID in array';
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate audit result
     */
    public function validateResult(array $result): bool
    {
        if (!isset($result['status'])) {
            $this->errors[] = 'Missing status in result';
            return false;
        }

        if (!in_array($result['status'], ['PASS', 'WARNING', 'FAIL', 'ERROR'])) {
            $this->errors[] = 'Invalid status value';
            return false;
        }

        if (!isset($result['h1_count']) || !is_numeric($result['h1_count'])) {
            $this->errors[] = 'Missing or invalid H1 count';
            return false;
        }

        return true;
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Clear errors
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }
}
