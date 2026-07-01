<?php

namespace App\Modules\NoindexAudit;

/**
 * Noindex Audit Processor
 * 
 * Processes HTML and detects noindex directives
 */
class NoindexAuditProcessor
{
    private const TIMEOUT = 30; // seconds
    private const MAX_REDIRECTS = 5;
    private const USER_AGENT = 'AuditWithGufran/1.0 (SEO Audit Bot)';

    /**
     * Fetch and process URL for noindex audit
     */
    public function process(string $url): array
    {
        // Fetch HTML
        $html = $this->fetchUrl($url);

        if ($html === false) {
            return [
                'status' => 'ERROR',
                'robots_content' => null,
                'index_status' => 'error',
                'follow_status' => 'error',
                'has_noindex' => null,
                'has_nofollow' => null,
                'error' => 'Failed to fetch URL'
            ];
        }

        return $this->processHtml($html);
    }

    /**
     * Process pre-fetched HTML for noindex audit.
     * Used by the orchestration layer to share a single fetch across modules.
     */
    public function processHtml(string $html): array
    {
        try {
            // Extract robots meta tag
            $robotsContent = $this->extractRobotsTag($html);

            // Parse robots directives
            $indexStatus = 'index'; // default
            $followStatus = 'follow'; // default
            $hasNoindex = false;
            $hasNofollow = false;

            if ($robotsContent) {
                $directives = $this->parseRobotsContent($robotsContent);

                $hasNoindex = in_array('noindex', $directives);
                $hasNofollow = in_array('nofollow', $directives);
                $hasIndex = in_array('index', $directives);
                $hasFollow = in_array('follow', $directives);

                // Determine index status
                if ($hasNoindex) {
                    $indexStatus = 'noindex';
                } elseif ($hasIndex) {
                    $indexStatus = 'index';
                }

                // Determine follow status
                if ($hasNofollow) {
                    $followStatus = 'nofollow';
                } elseif ($hasFollow) {
                    $followStatus = 'follow';
                }
            }

            // Determine overall status
            $status = $this->determineStatus($indexStatus, $followStatus, $robotsContent);

            return [
                'status' => $status,
                'robots_content' => $robotsContent,
                'index_status' => $indexStatus,
                'follow_status' => $followStatus,
                'has_noindex' => $hasNoindex,
                'has_nofollow' => $hasNofollow,
                'error' => null
            ];

        } catch (\Exception $e) {
            logMessage("Noindex Audit processing error: " . $e->getMessage(), 'error');

            return [
                'status' => 'ERROR',
                'robots_content' => null,
                'index_status' => 'error',
                'follow_status' => 'error',
                'has_noindex' => null,
                'has_nofollow' => null,
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
     * Extract robots meta tag content
     */
    private function extractRobotsTag(string $html): ?string
    {
        libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        
        libxml_clear_errors();

        $metaTags = $dom->getElementsByTagName('meta');
        
        foreach ($metaTags as $meta) {
            $name = strtolower($meta->getAttribute('name'));
            
            if ($name === 'robots') {
                $content = trim($meta->getAttribute('content'));
                return !empty($content) ? $content : null;
            }
        }

        return null;
    }

    /**
     * Parse robots content into directives array
     */
    private function parseRobotsContent(string $content): array
    {
        // Split by comma and clean
        $parts = explode(',', strtolower($content));
        $directives = [];
        
        foreach ($parts as $part) {
            $directive = trim($part);
            if (!empty($directive)) {
                $directives[] = $directive;
            }
        }
        
        return $directives;
    }

    /**
     * Determine overall status
     */
    private function determineStatus(string $indexStatus, string $followStatus, ?string $robotsContent): string
    {
        // FAIL if noindex is present
        if ($indexStatus === 'noindex') {
            return 'FAIL';
        }

        // WARNING if robots tag exists but has mixed/unclear directives
        if ($robotsContent && ($followStatus === 'nofollow' || $indexStatus === 'index')) {
            // Check for conflicting or unusual patterns
            $directives = $this->parseRobotsContent($robotsContent);
            
            // If both index and noindex (conflict)
            if (in_array('index', $directives) && in_array('noindex', $directives)) {
                return 'WARNING';
            }
            
            // If nofollow present (minor concern)
            if ($followStatus === 'nofollow') {
                return 'WARNING';
            }
        }

        // PASS if indexable (no robots tag or index directive)
        return 'PASS';
    }

    /**
     * Validate result data
     */
    public function validateResult(array $result): bool
    {
        return isset($result['status']) 
            && isset($result['index_status']) 
            && in_array($result['status'], ['PASS', 'WARNING', 'FAIL', 'ERROR']);
    }
}
