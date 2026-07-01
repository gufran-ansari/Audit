<?php

namespace App\Modules\LoremAudit;

/**
 * Lorem Audit Processor
 * 
 * Processes HTML and detects placeholder/lorem ipsum content
 */
class LoremAuditProcessor
{
    private const TIMEOUT = 30; // seconds
    private const MAX_REDIRECTS = 5;
    private const USER_AGENT = 'AuditWithGufran/1.0 (SEO Audit Bot)';
    private const SNIPPET_LENGTH = 120; // characters of context around match
    private const MAX_SNIPPETS = 10; // max snippets to store

    /**
     * Default placeholder keywords to detect.
     * Stored as patterns for case-insensitive matching.
     */
    private array $keywords = [
        'lorem ipsum',
        'placeholder text',
        'sample content',
        'dummy content',
        'dummy text',
        'placeholder content',
    ];

    /**
     * Allow injecting custom keywords (future expansion)
     */
    public function __construct(array $customKeywords = [])
    {
        if (!empty($customKeywords)) {
            // Merge and normalize to lowercase, unique
            $merged = array_merge($this->keywords, $customKeywords);
            $this->keywords = array_values(array_unique(array_map('strtolower', $merged)));
        }
    }

    /**
     * Fetch and process URL for lorem ipsum audit
     */
    public function process(string $url): array
    {
        // Fetch HTML
        $html = $this->fetchUrl($url);

        if ($html === false) {
            return [
                'status' => 'ERROR',
                'keywords_found' => [],
                'total_occurrences' => 0,
                'snippets' => [],
                'error' => 'Failed to fetch URL'
            ];
        }

        return $this->processHtml($html);
    }

    /**
     * Process pre-fetched HTML for lorem ipsum audit.
     * Used by the orchestration layer to share a single fetch across modules.
     */
    public function processHtml(string $html): array
    {
        try {
            // Extract visible text content from HTML
            $text = $this->extractText($html);

            // Scan for placeholder keywords
            $matches = $this->scanForKeywords($text);

            $keywordsFound = array_keys($matches);
            $totalOccurrences = array_sum(array_column($matches, 'count'));
            $snippets = $this->collectSnippets($matches);

            // Determine status
            $status = $totalOccurrences > 0 ? 'FAIL' : 'PASS';

            return [
                'status' => $status,
                'keywords_found' => $keywordsFound,
                'total_occurrences' => $totalOccurrences,
                'keyword_counts' => array_map(fn($m) => $m['count'], $matches),
                'snippets' => $snippets,
                'error' => null
            ];

        } catch (\Exception $e) {
            logMessage("Lorem Audit processing error: " . $e->getMessage(), 'error');

            return [
                'status' => 'ERROR',
                'keywords_found' => [],
                'total_occurrences' => 0,
                'snippets' => [],
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
        curl_setopt($ch, CURLOPT_ENCODING, '');

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
     * Extract visible text content from HTML.
     * Strips script/style tags to avoid false positives.
     */
    private function extractText(string $html): string
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        libxml_clear_errors();

        // Remove script and style elements
        foreach (['script', 'style', 'noscript'] as $tagName) {
            $nodes = $dom->getElementsByTagName($tagName);
            // Iterate backwards because the list is live
            $toRemove = [];
            foreach ($nodes as $node) {
                $toRemove[] = $node;
            }
            foreach ($toRemove as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $text = $body ? $body->textContent : $dom->textContent;

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Scan text for placeholder keywords.
     * Returns associative array keyed by keyword with count and positions.
     */
    private function scanForKeywords(string $text): array
    {
        $matches = [];
        $lowerText = mb_strtolower($text);

        foreach ($this->keywords as $keyword) {
            $keywordLower = mb_strtolower($keyword);
            $count = 0;
            $positions = [];
            $offset = 0;

            while (($pos = mb_strpos($lowerText, $keywordLower, $offset)) !== false) {
                $count++;
                if (count($positions) < self::MAX_SNIPPETS) {
                    $positions[] = $pos;
                }
                $offset = $pos + mb_strlen($keywordLower);
            }

            if ($count > 0) {
                $matches[$keyword] = [
                    'count' => $count,
                    'positions' => $positions,
                    'text' => $text // reference for snippet extraction
                ];
            }
        }

        return $matches;
    }

    /**
     * Collect text snippets around matched keywords
     */
    private function collectSnippets(array $matches): array
    {
        $snippets = [];

        foreach ($matches as $keyword => $data) {
            $text = $data['text'];

            foreach ($data['positions'] as $pos) {
                if (count($snippets) >= self::MAX_SNIPPETS) {
                    break 2;
                }

                $start = max(0, $pos - 40);
                $length = self::SNIPPET_LENGTH;
                $snippet = mb_substr($text, $start, $length);

                // Add ellipsis indicators
                $prefix = $start > 0 ? '...' : '';
                $suffix = ($start + $length) < mb_strlen($text) ? '...' : '';

                $snippets[] = [
                    'keyword' => $keyword,
                    'snippet' => $prefix . trim($snippet) . $suffix
                ];
            }
        }

        return $snippets;
    }

    /**
     * Get configured keywords
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * Validate result data
     */
    public function validateResult(array $result): bool
    {
        return isset($result['status'])
            && in_array($result['status'], ['PASS', 'WARNING', 'FAIL', 'ERROR']);
    }
}
