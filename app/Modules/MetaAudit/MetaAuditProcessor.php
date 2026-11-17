<?php

namespace App\Modules\MetaAudit;

use App\Services\SecureUrlFetcher;

/**
 * Meta Audit Processor
 * 
 * Processes HTML and extracts meta information
 */
class MetaAuditProcessor
{
    // Length thresholds
    private const TITLE_MIN = 30;
    private const TITLE_MAX = 60;
    private const DESC_MIN = 120;
    private const DESC_MAX = 160;

    /**
     * Fetch and process URL for meta audit
     */
    public function process(string $url): array
    {
        $fetcher = new SecureUrlFetcher();
        $result = $fetcher->fetch($url);

        if (!$result['success']) {
            return [
                'status' => 'ERROR',
                'title' => null,
                'title_length' => 0,
                'title_status' => 'error',
                'meta_description' => null,
                'description_length' => 0,
                'description_status' => 'error',
                'og_image' => null,
                'error' => $result['error'] ?? 'Failed to fetch URL'
            ];
        }

        return $this->processHtml($result['html']);
    }

    /**
     * Process pre-fetched HTML for meta audit.
     * Used by the orchestration layer to share a single fetch across modules.
     */
    public function processHtml(string $html): array
    {
        try {
            // Extract meta information
            $title = $this->extractTitle($html);
            $metaDescription = $this->extractMetaDescription($html);
            $ogImage = $this->extractOgImage($html);

            // Validate title
            $titleStatus = $this->validateTitle($title);

            // Validate meta description
            $descriptionStatus = $this->validateDescription($metaDescription);

            // Determine overall status
            $status = $this->determineOverallStatus($titleStatus, $descriptionStatus);

            return [
                'status' => $status,
                'title' => $title,
                'title_length' => mb_strlen($title ?? ''),
                'title_status' => $titleStatus,
                'meta_description' => $metaDescription,
                'description_length' => mb_strlen($metaDescription ?? ''),
                'description_status' => $descriptionStatus,
                'og_image' => $ogImage,
                'error' => null
            ];

        } catch (\Exception $e) {
            logMessage("Meta Audit processing error: " . $e->getMessage(), 'error');

            return [
                'status' => 'ERROR',
                'title' => null,
                'title_length' => 0,
                'title_status' => 'error',
                'meta_description' => null,
                'description_length' => 0,
                'description_status' => 'error',
                'og_image' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract page title
     */
    private function extractTitle(string $html): ?string
    {
        libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        
        libxml_clear_errors();

        $titleElements = $dom->getElementsByTagName('title');
        
        if ($titleElements->length > 0) {
            $title = trim($titleElements->item(0)->textContent);
            return !empty($title) ? $title : null;
        }

        return null;
    }

    /**
     * Extract meta description
     */
    private function extractMetaDescription(string $html): ?string
    {
        libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        
        libxml_clear_errors();

        $metaTags = $dom->getElementsByTagName('meta');
        
        foreach ($metaTags as $meta) {
            $name = $meta->getAttribute('name');
            
            if (strtolower($name) === 'description') {
                $content = trim($meta->getAttribute('content'));
                return !empty($content) ? $content : null;
            }
        }

        return null;
    }

    /**
     * Extract Open Graph image
     */
    private function extractOgImage(string $html): ?string
    {
        libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        
        libxml_clear_errors();

        $metaTags = $dom->getElementsByTagName('meta');
        
        foreach ($metaTags as $meta) {
            $property = $meta->getAttribute('property');
            
            if ($property === 'og:image') {
                $content = trim($meta->getAttribute('content'));
                return !empty($content) ? $content : null;
            }
        }

        return null;
    }

    /**
     * Validate title
     */
    private function validateTitle(?string $title): string
    {
        if (empty($title)) {
            return 'missing';
        }

        $length = mb_strlen($title);

        if ($length < self::TITLE_MIN) {
            return 'too_short';
        }

        if ($length > self::TITLE_MAX) {
            return 'too_long';
        }

        return 'valid';
    }

    /**
     * Validate meta description
     */
    private function validateDescription(?string $description): string
    {
        if (empty($description)) {
            return 'missing';
        }

        $length = mb_strlen($description);

        if ($length < self::DESC_MIN) {
            return 'too_short';
        }

        if ($length > self::DESC_MAX) {
            return 'too_long';
        }

        return 'valid';
    }

    /**
     * Determine overall status based on title and description validation
     */
    private function determineOverallStatus(string $titleStatus, string $descriptionStatus): string
    {
        // ERROR if both missing
        if ($titleStatus === 'error' || $descriptionStatus === 'error') {
            return 'ERROR';
        }

        // FAIL if title is missing
        if ($titleStatus === 'missing') {
            return 'FAIL';
        }

        // FAIL if description is missing
        if ($descriptionStatus === 'missing') {
            return 'FAIL';
        }

        // WARNING if either too short or too long
        if (in_array($titleStatus, ['too_short', 'too_long']) || 
            in_array($descriptionStatus, ['too_short', 'too_long'])) {
            return 'WARNING';
        }

        // PASS if both valid
        return 'PASS';
    }

    /**
     * Validate result data
     */
    public function validateResult(array $result): bool
    {
        return isset($result['status']) 
            && isset($result['title_status']) 
            && isset($result['description_status'])
            && in_array($result['status'], ['PASS', 'WARNING', 'FAIL', 'ERROR']);
    }
}
