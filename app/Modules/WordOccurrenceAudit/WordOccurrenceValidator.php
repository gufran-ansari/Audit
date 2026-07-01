<?php

namespace App\Modules\WordOccurrenceAudit;

/**
 * Word Occurrence Validator
 * 
 * Validates word occurrence audit input and results
 */
class WordOccurrenceValidator
{
    private array $errors = [];

    private const MAX_KEYWORD_LENGTH = 500;
    private const MAX_KEYWORDS = 25;

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
     * Validate raw keyword input (string from request)
     */
    public function validateKeywordInput(?string $input): bool
    {
        if ($input === null || trim($input) === '') {
            $this->errors[] = 'Keyword is required';
            return false;
        }

        if (mb_strlen($input) > self::MAX_KEYWORD_LENGTH) {
            $this->errors[] = 'Keyword input is too long (max ' . self::MAX_KEYWORD_LENGTH . ' characters)';
            return false;
        }

        return true;
    }

    /**
     * Parse and validate keywords from comma-separated input.
     * Returns array of cleaned keywords, or empty array on failure.
     */
    public function parseKeywords(?string $input): array
    {
        if (!$this->validateKeywordInput($input)) {
            return [];
        }

        $parts = explode(',', $input);
        $keywords = [];

        foreach ($parts as $part) {
            $keyword = trim($part);
            // Normalize internal whitespace
            $keyword = preg_replace('/\s+/', ' ', $keyword);

            if ($keyword !== '') {
                $keywords[] = $keyword;
            }
        }

        // Remove duplicates while preserving order
        $keywords = array_values(array_unique($keywords));

        if (empty($keywords)) {
            $this->errors[] = 'No valid keywords provided';
            return [];
        }

        if (count($keywords) > self::MAX_KEYWORDS) {
            $this->errors[] = 'Too many keywords (max ' . self::MAX_KEYWORDS . ')';
            return [];
        }

        return $keywords;
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

        if (!isset($result['total_occurrences'])) {
            $this->errors[] = 'Missing occurrence count';
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
