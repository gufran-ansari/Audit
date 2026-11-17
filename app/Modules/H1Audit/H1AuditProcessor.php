<?php

namespace App\Modules\H1Audit;

use App\Services\SecureUrlFetcher;

/**
 * H1 Audit Processor
 * 
 * Processes HTML and extracts H1 information
 */
class H1AuditProcessor
{
    /**
     * Fetch and process URL for H1 audit
     */
    public function process(string $url): array
    {
        $fetcher = new SecureUrlFetcher();
        $result = $fetcher->fetch($url);

        if (!$result['success']) {
            return [
                'status' => 'ERROR',
                'h1_count' => 0,
                'h1_text' => null,
                'h1_tags' => [],
                'error' => $result['error'] ?? 'Failed to fetch URL'
            ];
        }

        return $this->processHtml($result['html']);
    }

    /**
     * Process pre-fetched HTML for H1 audit.
     * Used by the orchestration layer to share a single fetch across modules.
     */
    public function processHtml(string $html): array
    {
        try {
            // Extract H1 tags
            $h1Tags = $this->extractH1Tags($html);
            $h1Count = count($h1Tags);

            // Determine status
            if ($h1Count === 0) {
                $status = 'FAIL';
                $h1Text = null;
            } elseif ($h1Count === 1) {
                $status = 'PASS';
                $h1Text = $h1Tags[0];
            } else {
                $status = 'WARNING';
                $h1Text = $h1Tags[0]; // Store first H1
            }

            return [
                'status' => $status,
                'h1_count' => $h1Count,
                'h1_text' => $h1Text,
                'h1_tags' => $h1Tags,
                'error' => null
            ];

        } catch (\Exception $e) {
            logMessage("H1 Audit processing error: " . $e->getMessage(), 'error');

            return [
                'status' => 'ERROR',
                'h1_count' => 0,
                'h1_text' => null,
                'h1_tags' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract H1 tags from HTML
     */
    private function extractH1Tags(string $html): array
    {
        $h1Tags = [];

        // Use DOMDocument to parse HTML
        libxml_use_internal_errors(true); // Suppress HTML parsing warnings
        
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        
        libxml_clear_errors();

        // Get all H1 elements
        $h1Elements = $dom->getElementsByTagName('h1');

        foreach ($h1Elements as $h1) {
            $text = trim($h1->textContent);
            if (!empty($text)) {
                $h1Tags[] = $text;
            }
        }

        return $h1Tags;
    }

    /**
     * Validate result data
     */
    public function validateResult(array $result): bool
    {
        return isset($result['status']) 
            && isset($result['h1_count']) 
            && in_array($result['status'], ['PASS', 'WARNING', 'FAIL', 'ERROR']);
    }
}
