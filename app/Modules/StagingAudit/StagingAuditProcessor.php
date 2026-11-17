<?php

namespace App\Modules\StagingAudit;

use App\Services\SecureUrlFetcher;

/**
 * Staging Audit Processor
 * 
 * Scans HTML source for staging/development URL references
 */
class StagingAuditProcessor
{
    private const MAX_MATCHES = 50; // max matched URLs to store

    /**
     * Staging/dev hostname patterns to detect.
     * Matched against host portions of discovered URLs.
     */
    private array $stagingPatterns = [
        'staging.',
        'stage.',
        'dev.',
        'test.',
        'testing.',
        'demo.',
        'preprod.',
        'pre-prod.',
        'uat.',
        'qa.',
        'sandbox.',
        'localhost',
        '127.0.0.1',
        '.local',
        '.test',
        '.dev',
        '.staging',
    ];

    /**
     * Allow injecting custom patterns (future expansion)
     */
    public function __construct(array $customPatterns = [])
    {
        if (!empty($customPatterns)) {
            $merged = array_merge($this->stagingPatterns, $customPatterns);
            $this->stagingPatterns = array_values(array_unique(array_map('strtolower', $merged)));
        }
    }

    /**
     * Fetch and process URL for staging audit
     */
    public function process(string $url): array
    {
        $fetcher = new SecureUrlFetcher();
        $result = $fetcher->fetch($url);

        if (!$result['success']) {
            return [
                'status' => 'ERROR',
                'staging_urls' => [],
                'total_occurrences' => 0,
                'source_locations' => [],
                'error' => $result['error'] ?? 'Failed to fetch URL'
            ];
        }

        return $this->processHtml($result['html']);
    }

    /**
     * Process pre-fetched HTML for staging audit.
     * Used by the orchestration layer to share a single fetch across modules.
     */
    public function processHtml(string $html): array
    {
        try {
            // Extract URLs from various source locations
            $extractedUrls = $this->extractUrls($html);

            // Scan extracted URLs for staging references
            $matches = $this->scanForStaging($extractedUrls);

            $stagingUrls = array_keys($matches);
            $totalOccurrences = array_sum(array_column($matches, 'count'));
            $sourceLocations = $this->collectSourceLocations($matches);

            // Determine status
            $status = $totalOccurrences > 0 ? 'FAIL' : 'PASS';

            return [
                'status' => $status,
                'staging_urls' => array_slice($stagingUrls, 0, self::MAX_MATCHES),
                'total_occurrences' => $totalOccurrences,
                'url_counts' => array_map(fn($m) => $m['count'], $matches),
                'source_locations' => $sourceLocations,
                'error' => null
            ];

        } catch (\Exception $e) {
            logMessage("Staging Audit processing error: " . $e->getMessage(), 'error');

            return [
                'status' => 'ERROR',
                'staging_urls' => [],
                'total_occurrences' => 0,
                'source_locations' => [],
                'error' => $e->getMessage()
            ];
        }
}

    /**
     * Extract URLs from various source locations:
     * links, images, scripts, stylesheets, canonical
     * Returns array of ['url' => string, 'location' => string]
     */
    private function extractUrls(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        libxml_clear_errors();

        $urls = [];

        // Links: <a href> and <link href>
        foreach ($dom->getElementsByTagName('a') as $node) {
            $href = trim($node->getAttribute('href'));
            if ($href !== '') {
                $urls[] = ['url' => $href, 'location' => 'link'];
            }
        }

        // <link> tags (stylesheets, canonical, etc.)
        foreach ($dom->getElementsByTagName('link') as $node) {
            $href = trim($node->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $rel = strtolower(trim($node->getAttribute('rel')));
            if ($rel === 'canonical') {
                $location = 'canonical';
            } elseif ($rel === 'stylesheet') {
                $location = 'stylesheet';
            } else {
                $location = 'link';
            }
            $urls[] = ['url' => $href, 'location' => $location];
        }

        // Images: <img src> and srcset
        foreach ($dom->getElementsByTagName('img') as $node) {
            $src = trim($node->getAttribute('src'));
            if ($src !== '') {
                $urls[] = ['url' => $src, 'location' => 'image'];
            }
            $srcset = trim($node->getAttribute('srcset'));
            if ($srcset !== '') {
                foreach (explode(',', $srcset) as $candidate) {
                    $parts = preg_split('/\s+/', trim($candidate));
                    if (!empty($parts[0])) {
                        $urls[] = ['url' => $parts[0], 'location' => 'image'];
                    }
                }
            }
        }

        // Scripts: <script src>
        foreach ($dom->getElementsByTagName('script') as $node) {
            $src = trim($node->getAttribute('src'));
            if ($src !== '') {
                $urls[] = ['url' => $src, 'location' => 'script'];
            }
        }

        // Meta og:url and similar
        foreach ($dom->getElementsByTagName('meta') as $node) {
            $property = strtolower($node->getAttribute('property'));
            if (in_array($property, ['og:url', 'og:image', 'twitter:image'])) {
                $content = trim($node->getAttribute('content'));
                if ($content !== '') {
                    $urls[] = ['url' => $content, 'location' => 'meta'];
                }
            }
        }

        return $urls;
    }

    /**
     * Scan extracted URLs for staging patterns.
     * Returns associative array keyed by matched URL with count and locations.
     */
    private function scanForStaging(array $extractedUrls): array
    {
        $matches = [];

        foreach ($extractedUrls as $item) {
            $url = $item['url'];
            $location = $item['location'];

            // Skip relative URLs, anchors, data URIs, mailto, tel
            if ($this->shouldSkipUrl($url)) {
                continue;
            }

            $host = $this->extractHost($url);
            if ($host === null) {
                continue;
            }

            $hostLower = strtolower($host);

            foreach ($this->stagingPatterns as $pattern) {
                if ($this->hostMatchesPattern($hostLower, $pattern)) {
                    if (!isset($matches[$url])) {
                        $matches[$url] = [
                            'count' => 0,
                            'locations' => [],
                            'matched_pattern' => $pattern
                        ];
                    }
                    $matches[$url]['count']++;
                    if (!in_array($location, $matches[$url]['locations'])) {
                        $matches[$url]['locations'][] = $location;
                    }
                    break; // one pattern match per URL is enough
                }
            }
        }

        return $matches;
    }

    /**
     * Determine if a URL should be skipped from staging analysis
     */
    private function shouldSkipUrl(string $url): bool
    {
        // Skip anchors, data URIs, javascript, mailto, tel
        $skipPrefixes = ['#', 'data:', 'javascript:', 'mailto:', 'tel:'];
        foreach ($skipPrefixes as $prefix) {
            if (stripos($url, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract host from a URL.
     * Handles protocol-relative URLs (//host/path).
     */
    private function extractHost(string $url): ?string
    {
        // Handle protocol-relative URLs
        if (strpos($url, '//') === 0) {
            $url = 'http:' . $url;
        }

        // Only consider absolute URLs with a scheme
        if (!preg_match('#^https?://#i', $url)) {
            // Could be a relative URL — no host to analyze
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host ?: null;
    }

    /**
     * Check if a host matches a staging pattern.
     * Patterns ending with '.' are treated as subdomain prefixes.
     * Patterns starting with '.' are treated as TLD/suffix matches.
     */
    private function hostMatchesPattern(string $host, string $pattern): bool
    {
        // Exact tokens like localhost / 127.0.0.1
        if ($pattern === 'localhost' || $pattern === '127.0.0.1') {
            return $host === $pattern || strpos($host, $pattern) !== false;
        }

        // Suffix patterns: .local, .test, .dev, .staging
        if (strpos($pattern, '.') === 0) {
            return substr($host, -strlen($pattern)) === $pattern;
        }

        // Prefix/subdomain patterns: staging., dev., test.
        if (substr($pattern, -1) === '.') {
            // Match at start (staging.example.com) or as a subdomain segment (www.staging.example.com)
            return strpos($host, $pattern) === 0 || strpos($host, '.' . $pattern) !== false;
        }

        // Fallback: substring match
        return strpos($host, $pattern) !== false;
    }

    /**
     * Collect source location summary from matches
     */
    private function collectSourceLocations(array $matches): array
    {
        $locationCounts = [];

        foreach ($matches as $url => $data) {
            foreach ($data['locations'] as $location) {
                if (!isset($locationCounts[$location])) {
                    $locationCounts[$location] = 0;
                }
                $locationCounts[$location]++;
            }
        }

        return $locationCounts;
    }

    /**
     * Get configured staging patterns
     */
    public function getPatterns(): array
    {
        return $this->stagingPatterns;
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
