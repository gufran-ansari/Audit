<?php

namespace App\Modules\Pages;

/**
 * Page Validator
 * 
 * Validates and normalizes URLs
 */
class PageValidator
{
    private $errors = [];

    // Tracking parameters to remove during normalization
    private $trackingParams = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'fbclid',
        'gclid',
        'msclkid',
        'mc_cid',
        'mc_eid',
        '_ga',
        'ref',
        'source'
    ];

    /**
     * Validate URL
     */
    public function validateUrl(string $url): bool
    {
        $this->errors = [];

        if (empty($url)) {
            $this->errors['url'] = 'URL is required';
            return false;
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->errors['url'] = 'Invalid URL format';
            return false;
        }

        // Check if URL uses HTTP or HTTPS
        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
            $this->errors['url'] = 'URL must use HTTP or HTTPS protocol';
            return false;
        }

        return true;
    }

    /**
     * Normalize URL
     * 
     * Applies various normalization rules to create a consistent URL format
     */
    public function normalizeUrl(string $url): string
    {
        $url = trim($url);

        // Parse URL
        $parsed = parse_url($url);
        
        if (!$parsed) {
            return $url;
        }

        // Normalize scheme (force lowercase)
        $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : 'https';
        
        // Normalize host (force lowercase)
        $host = isset($parsed['host']) ? strtolower($parsed['host']) : '';
        
        // Normalize port (remove default ports)
        $port = '';
        if (isset($parsed['port'])) {
            if (!(($scheme === 'http' && $parsed['port'] == 80) || 
                  ($scheme === 'https' && $parsed['port'] == 443))) {
                $port = ':' . $parsed['port'];
            }
        }
        
        // Normalize path
        $path = isset($parsed['path']) ? $this->normalizePath($parsed['path']) : '/';
        
        // Normalize query (remove tracking parameters)
        $query = '';
        if (isset($parsed['query'])) {
            $cleanQuery = $this->normalizeQuery($parsed['query']);
            if (!empty($cleanQuery)) {
                $query = '?' . $cleanQuery;
            }
        }
        
        // Don't include fragment in normalized URL
        // Fragments are client-side only and don't represent different pages
        
        // Rebuild URL
        $normalized = $scheme . '://' . $host . $port . $path . $query;
        
        return $normalized;
    }

    /**
     * Normalize URL path
     */
    private function normalizePath(string $path): string
    {
        // Remove duplicate slashes
        $path = preg_replace('#/+#', '/', $path);
        
        // Decode percent-encoding
        $path = urldecode($path);
        
        // Re-encode properly
        $parts = explode('/', $path);
        $encoded = array_map('rawurlencode', $parts);
        $path = implode('/', $encoded);
        
        // Normalize trailing slash (remove it for consistency)
        // Exception: keep trailing slash for root path
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }
        
        // Ensure path starts with /
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }
        
        return $path;
    }

    /**
     * Normalize query string
     * 
     * Removes tracking parameters and sorts remaining parameters
     */
    private function normalizeQuery(string $query): string
    {
        parse_str($query, $params);
        
        // Remove tracking parameters
        foreach ($this->trackingParams as $param) {
            unset($params[$param]);
        }
        
        // Sort parameters for consistency
        ksort($params);
        
        // Rebuild query string
        return http_build_query($params);
    }

    /**
     * Generate URL hash
     * 
     * Creates a SHA256 hash of the normalized URL for fast lookups
     */
    public function generateUrlHash(string $normalizedUrl): string
    {
        return hash('sha256', $normalizedUrl);
    }

    /**
     * Check if URL belongs to domain
     */
    public function belongsToDomain(string $url, string $domain): bool
    {
        $parsed = parse_url($url);
        
        if (!isset($parsed['host'])) {
            return false;
        }
        
        $urlHost = strtolower($parsed['host']);
        $domain = strtolower($domain);
        
        // Remove www prefix for comparison
        $urlHost = preg_replace('/^www\./', '', $urlHost);
        $domain = preg_replace('/^www\./', '', $domain);
        
        return $urlHost === $domain || 
               str_ends_with($urlHost, '.' . $domain);
    }

    /**
     * Extract domain from URL
     */
    public function extractDomain(string $url): ?string
    {
        $parsed = parse_url($url);
        return isset($parsed['host']) ? strtolower($parsed['host']) : null;
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error message
     */
    public function getFirstError(): ?string
    {
        if (empty($this->errors)) {
            return null;
        }
        
        return reset($this->errors);
    }

    /**
     * Batch validate URLs
     */
    public function validateBatch(array $urls): array
    {
        $results = [
            'valid' => [],
            'invalid' => []
        ];
        
        foreach ($urls as $url) {
            if ($this->validateUrl($url)) {
                $results['valid'][] = $url;
            } else {
                $results['invalid'][] = [
                    'url' => $url,
                    'error' => $this->getFirstError()
                ];
            }
        }
        
        return $results;
    }
}
