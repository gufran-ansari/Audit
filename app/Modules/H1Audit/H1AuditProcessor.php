<?php

namespace App\Modules\H1Audit;

/**
 * H1 Audit Processor
 * 
 * Processes HTML and extracts H1 information
 */
class H1AuditProcessor
{
    private const TIMEOUT = 30; // seconds
    private const MAX_REDIRECTS = 5;
    private const USER_AGENT = 'AuditWithGufran/1.0 (SEO Audit Bot)';

    /**
     * Fetch and process URL for H1 audit
     */
    public function process(string $url): array
    {
        // Fetch HTML
        $html = $this->fetchUrl($url);

        if ($html === false) {
            return [
                'status' => 'ERROR',
                'h1_count' => 0,
                'h1_text' => null,
                'h1_tags' => [],
                'error' => 'Failed to fetch URL'
            ];
        }

        return $this->processHtml($html);
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
     * Fetch URL content
     */
    private function fetchUrl(string $url): string|false
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, self::MAX_REDIRECTS);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Accept all encodings
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($content === false || $httpCode < 200 || $httpCode >= 400) {
            logMessage("Failed to fetch URL $url: HTTP $httpCode, Error: $error", 'warning');
            return false;
        }

        return $content;
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
