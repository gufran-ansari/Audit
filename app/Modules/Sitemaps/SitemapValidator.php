<?php

namespace App\Modules\Sitemaps;

/**
 * Sitemap Validator
 * 
 * Validates sitemap URLs and XML content
 */
class SitemapValidator
{
    private $errors = [];

    /**
     * Validate sitemap URL
     */
    public function validateUrl(string $url): bool
    {
        $this->errors = [];

        // Check if URL is empty
        if (empty($url)) {
            $this->errors['url'] = 'Sitemap URL is required';
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
     * Validate XML content
     */
    public function validateXml(string $xmlContent): bool
    {
        $this->errors = [];

        if (empty($xmlContent)) {
            $this->errors['xml'] = 'XML content is empty';
            return false;
        }

        // Disable XML errors
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        // Try to parse XML
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $errorMessages = [];
            
            foreach ($errors as $error) {
                $errorMessages[] = trim($error->message);
            }
            
            libxml_clear_errors();
            
            $this->errors['xml'] = 'Invalid XML: ' . implode('; ', $errorMessages);
            return false;
        }

        libxml_clear_errors();
        return true;
    }

    /**
     * Validate sitemap schema
     */
    public function validateSitemapSchema(string $xmlContent): array
    {
        $this->errors = [];

        if (!$this->validateXml($xmlContent)) {
            return [
                'valid' => false,
                'type' => null,
                'error' => $this->errors['xml'] ?? 'Invalid XML'
            ];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            return [
                'valid' => false,
                'type' => null,
                'error' => 'Failed to parse XML'
            ];
        }

        // Register namespaces
        $namespaces = $xml->getNamespaces(true);
        
        // Check for sitemap index
        if (isset($xml->sitemap) || (isset($namespaces['']) && strpos($namespaces[''], 'sitemaps.org') !== false && isset($xml->sitemap))) {
            return [
                'valid' => true,
                'type' => 'sitemap_index',
                'count' => count($xml->sitemap)
            ];
        }

        // Check for regular sitemap
        if (isset($xml->url) || (isset($namespaces['']) && strpos($namespaces[''], 'sitemaps.org') !== false && isset($xml->url))) {
            return [
                'valid' => true,
                'type' => 'sitemap',
                'count' => count($xml->url)
            ];
        }

        // Check if it's a nested sitemap (in sitemap index)
        if ($xml->getName() === 'urlset') {
            return [
                'valid' => true,
                'type' => 'nested_sitemap',
                'count' => count($xml->url)
            ];
        }

        return [
            'valid' => false,
            'type' => null,
            'error' => 'Not a valid sitemap format (missing url or sitemap elements)'
        ];
    }

    /**
     * Validate domain
     */
    public function validateDomain(string $domain): bool
    {
        $this->errors = [];

        if (empty($domain)) {
            $this->errors['domain'] = 'Domain is required';
            return false;
        }

        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);

        // Validate domain format
        if (!preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $domain)) {
            $this->errors['domain'] = 'Invalid domain format';
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
     * Check if content type is XML
     */
    public function isXmlContentType(string $contentType): bool
    {
        $xmlTypes = [
            'application/xml',
            'text/xml',
            'application/x-xml'
        ];

        foreach ($xmlTypes as $type) {
            if (stripos($contentType, $type) !== false) {
                return true;
            }
        }

        return false;
    }
}
