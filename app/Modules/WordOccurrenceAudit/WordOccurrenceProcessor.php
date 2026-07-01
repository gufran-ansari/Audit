<?php

namespace App\Modules\WordOccurrenceAudit;

/**
 * Word Occurrence Processor
 * 
 * Fetches page HTML and counts occurrences of user-provided keywords
 * across visible content, headings, paragraphs, lists, links, and buttons.
 */
class WordOccurrenceProcessor
{
    private const TIMEOUT = 30; // seconds
    private const MAX_REDIRECTS = 5;
    private const USER_AGENT = 'AuditWithGufran/1.0 (SEO Audit Bot)';
    private const SNIPPET_LENGTH = 140; // characters of context around a match
    private const MAX_SNIPPETS_PER_KEYWORD = 8;

    /**
     * Process a single URL against one or more keywords.
     *
     * @param string $url Page URL
     * @param array $keywords List of keywords/phrases to search for
     * @return array Standardized result
     */
    public function process(string $url, array $keywords): array
    {
        $html = $this->fetchUrl($url);

        if ($html === false) {
            return [
                'status' => 'ERROR',
                'keywords' => $keywords,
                'total_occurrences' => 0,
                'keyword_results' => [],
                'locations' => [],
                'snippets' => [],
                'error' => 'Failed to fetch URL'
            ];
        }

        return $this->processHtml($html, $keywords);
    }

    /**
     * Process pre-fetched HTML for word occurrence audit.
     * Used by the orchestration layer to share a single fetch across modules.
     *
     * @param string $html
     * @param array $keywords List of keywords/phrases to search for
     * @return array Standardized result
     */
    public function processHtml(string $html, array $keywords): array
    {
        try {
            // Extract content grouped by location type
            $sections = $this->extractSections($html);

            // Combined visible text for total counting/snippets
            $visibleText = $sections['content'];

            $keywordResults = [];
            $allSnippets = [];
            $locationTotals = [];
            $grandTotal = 0;

            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    continue;
                }

                // Count per location
                $locationCounts = [];
                foreach ($sections['by_location'] as $location => $text) {
                    $count = $this->countOccurrences($text, $keyword);
                    if ($count > 0) {
                        $locationCounts[$location] = $count;
                        $locationTotals[$location] = ($locationTotals[$location] ?? 0) + $count;
                    }
                }

                // Total occurrences from visible content
                $total = $this->countOccurrences($visibleText, $keyword);

                // Collect snippets from visible content
                $snippets = $this->collectSnippets($visibleText, $keyword);
                foreach ($snippets as $snippet) {
                    $allSnippets[] = [
                        'keyword' => $keyword,
                        'snippet' => $snippet
                    ];
                }

                $grandTotal += $total;

                $keywordResults[] = [
                    'keyword' => $keyword,
                    'occurrences' => $total,
                    'found' => $total > 0,
                    'locations' => $locationCounts
                ];
            }

            // PASS if any keyword found, otherwise FAIL
            $status = $grandTotal > 0 ? 'PASS' : 'FAIL';

            return [
                'status' => $status,
                'keywords' => $keywords,
                'total_occurrences' => $grandTotal,
                'keyword_results' => $keywordResults,
                'locations' => $locationTotals,
                'snippets' => array_slice($allSnippets, 0, self::MAX_SNIPPETS_PER_KEYWORD * max(1, count($keywords))),
                'error' => null
            ];

        } catch (\Exception $e) {
            logMessage("Word Occurrence Audit processing error: " . $e->getMessage(), 'error');

            return [
                'status' => 'ERROR',
                'keywords' => $keywords,
                'total_occurrences' => 0,
                'keyword_results' => [],
                'locations' => [],
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
     * Extract content sections from HTML.
     * Returns:
     *  - 'content': combined visible text
     *  - 'by_location': text grouped by location (headings, paragraphs, lists, links, buttons)
     */
    private function extractSections(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        libxml_clear_errors();

        // Remove script/style/noscript to avoid noise
        foreach (['script', 'style', 'noscript'] as $tagName) {
            $nodes = $dom->getElementsByTagName($tagName);
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

        $byLocation = [
            'headings' => $this->collectTagText($dom, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']),
            'paragraphs' => $this->collectTagText($dom, ['p']),
            'lists' => $this->collectTagText($dom, ['li']),
            'links' => $this->collectTagText($dom, ['a']),
            'buttons' => $this->collectTagText($dom, ['button']),
        ];

        // Combined visible body text
        $body = $dom->getElementsByTagName('body')->item(0);
        $content = $body ? $body->textContent : $dom->textContent;
        $content = $this->normalizeWhitespace($content);

        return [
            'content' => $content,
            'by_location' => $byLocation
        ];
    }

    /**
     * Collect normalized text content from a set of tags
     */
    private function collectTagText(\DOMDocument $dom, array $tags): string
    {
        $parts = [];

        foreach ($tags as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return $this->normalizeWhitespace(implode(' ', $parts));
    }

    /**
     * Normalize whitespace in text
     */
    private function normalizeWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Count case-insensitive occurrences of a keyword in text.
     * Uses multibyte-safe counting.
     */
    private function countOccurrences(string $text, string $keyword): int
    {
        if ($text === '' || $keyword === '') {
            return 0;
        }

        $haystack = mb_strtolower($text);
        $needle = mb_strtolower($keyword);

        $count = 0;
        $offset = 0;

        while (($pos = mb_strpos($haystack, $needle, $offset)) !== false) {
            $count++;
            $offset = $pos + mb_strlen($needle);
        }

        return $count;
    }

    /**
     * Collect text snippets around keyword matches
     */
    private function collectSnippets(string $text, string $keyword): array
    {
        $snippets = [];

        if ($text === '' || $keyword === '') {
            return $snippets;
        }

        $haystack = mb_strtolower($text);
        $needle = mb_strtolower($keyword);
        $needleLen = mb_strlen($needle);
        $offset = 0;

        while (($pos = mb_strpos($haystack, $needle, $offset)) !== false) {
            if (count($snippets) >= self::MAX_SNIPPETS_PER_KEYWORD) {
                break;
            }

            $start = max(0, $pos - 50);
            $snippet = mb_substr($text, $start, self::SNIPPET_LENGTH);

            $prefix = $start > 0 ? '...' : '';
            $suffix = ($start + self::SNIPPET_LENGTH) < mb_strlen($text) ? '...' : '';

            $snippets[] = $prefix . trim($snippet) . $suffix;

            $offset = $pos + $needleLen;
        }

        return $snippets;
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
