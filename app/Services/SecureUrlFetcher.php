<?php

namespace App\Services;

/**
 * Secure URL Fetcher
 * 
 * Provides SSRF-protected, SSL-verified HTTP fetching.
 * Prevents access to internal/private IP addresses and validates redirects.
 */
class SecureUrlFetcher
{
    private int $timeout;
    private int $connectTimeout;
    private int $maxRedirects;
    private string $userAgent;
    private int $maxContentLength;
    
    // Private IPv4 ranges (RFC 1918)
    private const PRIVATE_IPV4_RANGES = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',        // Loopback
        '169.254.0.0/16',     // Link-local
        '100.64.0.0/10',      // Carrier-grade NAT (RFC 6598)
        '192.0.0.0/24',       // IETF protocol assignments
        '192.0.2.0/24',       // TEST-NET-1 (RFC 5737)
        '198.51.100.0/24',    // TEST-NET-2 (RFC 5737)
        '203.0.113.0/24',     // TEST-NET-3 (RFC 5737)
        '224.0.0.0/4',        // Multicast
        '240.0.0.0/4',        // Reserved
        '255.255.255.255/32', // Broadcast
    ];

    // Private IPv6 ranges
    private const PRIVATE_IPV6_RANGES = [
        '::1/128',            // Loopback
        '::/128',             // Unspecified
        'fe80::/10',          // Link-local
        'fc00::/7',           // Unique local addresses (ULA)
        'ff00::/8',           // Multicast
        '2001:db8::/32',      // Documentation (RFC 3849)
        '2001:10::/28',       // ORCHID (RFC 4843)
        '2001:20::/28',       // ORCHIDv2 (RFC 7343)
        '100::/64',           // Discard prefix (RFC 6666)
        '::ffff:0:0/96',      // IPv4-mapped IPv6
    ];

    // Cloud metadata endpoints to block
    private const CLOUD_METADATA_IPS = [
        '169.254.169.254',    // AWS, GCP, Azure, DigitalOcean
        '169.254.169.250',    // Azure IMDS
        'metadata.google.internal', // GCP
    ];

    public function __construct(
        int $timeout = 30,
        int $connectTimeout = 10,
        int $maxRedirects = 5,
        string $userAgent = 'AuditWithGufran/1.0 (SEO Audit Bot)',
        int $maxContentLength = 5 * 1024 * 1024 // 5MB
    ) {
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->maxRedirects = $maxRedirects;
        $this->userAgent = $userAgent;
        $this->maxContentLength = $maxContentLength;
    }

    /**
     * Fetch a URL with full SSRF and SSL protection.
     * 
     * @param string $url The URL to fetch
     * @param bool $headOnly If true, only fetch headers (HEAD request)
     * @return array Response array with keys: success, html, http_code, final_url, content_type, fetch_time_ms, error
     */
    public function fetch(string $url, bool $headOnly = false): array
    {
        $startTime = microtime(true);

        // Validate initial URL
        $validation = $this->validateUrl($url);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'html' => null,
                'http_code' => 0,
                'final_url' => $url,
                'content_type' => null,
                'fetch_time_ms' => 0,
                'error' => $validation['error']
            ];
        }

        // Fetch with redirect validation
        $result = $this->fetchWithRedirectValidation($url, $headOnly);
        
        $result['fetch_time_ms'] = (int) round((microtime(true) - $startTime) * 1000);
        return $result;
    }

    /**
     * Validate a URL for SSRF protection.
     * Checks scheme, host, IP resolution, and rejects private/internal addresses.
     */
    public function validateUrl(string $url): array
    {
        // Basic format validation
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }

        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return ['valid' => false, 'error' => 'Invalid URL structure'];
        }

        // Only allow HTTP/HTTPS
        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['valid' => false, 'error' => 'Only HTTP and HTTPS URLs are allowed'];
        }

        // Reject URLs with embedded credentials
        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return ['valid' => false, 'error' => 'URLs with embedded credentials are not allowed'];
        }

        $host = strtolower($parsed['host']);
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);

        // Block cloud metadata hostnames
        if (in_array($host, ['metadata.google.internal', 'metadata'], true)) {
            return ['valid' => false, 'error' => 'Cloud metadata endpoints are blocked'];
        }

        // Resolve hostname to IPs
        $ips = $this->resolveHost($host);
        if (empty($ips)) {
            return ['valid' => false, 'error' => 'Failed to resolve hostname'];
        }

        // Validate all resolved IPs
        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                return ['valid' => false, 'error' => 'Access to private/internal IP addresses is blocked'];
            }
            if ($this->isCloudMetadataIp($ip)) {
                return ['valid' => false, 'error' => 'Cloud metadata endpoints are blocked'];
            }
        }

        return ['valid' => true, 'ips' => $ips, 'host' => $host, 'port' => $port];
    }

    /**
     * Fetch with manual redirect handling to validate each hop.
     */
    private function fetchWithRedirectValidation(string $url, bool $headOnly): array
    {
        $redirectCount = 0;
        $currentUrl = $url;
        $finalUrl = $url;
        $finalContent = null;
        $finalHttpCode = 0;
        $finalContentType = null;
        $lastError = null;

        while ($redirectCount <= $this->maxRedirects) {
            $ch = curl_init($currentUrl);
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // We handle redirects manually
            curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
            
            // Enable SSL verification
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_MAXFILESIZE, $this->maxContentLength);
            
            // Resolve to specific IPs to prevent DNS rebinding
            $validation = $this->validateUrl($currentUrl);
            if (!$validation['valid']) {
                curl_close($ch);
                return [
                    'success' => false,
                    'html' => null,
                    'http_code' => 0,
                    'final_url' => $finalUrl,
                    'content_type' => null,
                    'fetch_time_ms' => 0,
                    'error' => 'Redirect validation failed: ' . $validation['error']
                ];
            }
            
            // Set resolved IPs to prevent DNS rebinding
            if (!empty($validation['ips'])) {
                curl_setopt($ch, CURLOPT_RESOLVE, array_map(
                    fn($ip) => "{$validation['host']}:{$validation['port']}:{$ip}",
                    $validation['ips']
                ));
            }
            
            if ($headOnly) {
                curl_setopt($ch, CURLOPT_NOBODY, true);
            } else {
                curl_setopt($ch, CURLOPT_NOBODY, false);
            }

            $content = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            $finalHttpCode = $httpCode;
            $finalContentType = $contentType;
            $finalUrl = $currentUrl;
            $finalContent = $content;

            if ($content === false || $httpCode < 200 || $httpCode >= 400) {
                $lastError = $error !== '' ? $error : "HTTP {$httpCode}";
                break;
            }

            // Check for redirect
            if (in_array($httpCode, [301, 302, 303, 307, 308], true)) {
                $redirectCount++;
                
                // Get redirect URL from headers
                $redirectUrl = $this->getRedirectUrl($currentUrl, $httpCode);
                if (!$redirectUrl) {
                    $lastError = 'Redirect received but no Location header found';
                    break;
                }

                // Validate redirect destination
                $redirectValidation = $this->validateUrl($redirectUrl);
                if (!$redirectValidation['valid']) {
                    $lastError = 'Redirect blocked: ' . $redirectValidation['error'];
                    break;
                }

                $currentUrl = $redirectUrl;
                continue;
            }

            // Successful non-redirect response
            $lastError = null;
            break;
        }

        if ($redirectCount > $this->maxRedirects) {
            return [
                'success' => false,
                'html' => null,
                'http_code' => $finalHttpCode,
                'final_url' => $finalUrl,
                'content_type' => $finalContentType,
                'fetch_time_ms' => 0,
                'error' => 'Too many redirects'
            ];
        }

        if ($finalContent === false || $lastError !== null) {
            return [
                'success' => false,
                'html' => null,
                'http_code' => $finalHttpCode,
                'final_url' => $finalUrl,
                'content_type' => $finalContentType,
                'fetch_time_ms' => 0,
                'error' => "Failed to fetch URL: {$lastError}"
            ];
        }

        return [
            'success' => true,
            'html' => $headOnly ? null : $finalContent,
            'http_code' => $finalHttpCode,
            'final_url' => $finalUrl,
            'content_type' => $finalContentType,
            'fetch_time_ms' => 0, // Will be set by caller
            'error' => null
        ];
    }

    /**
     * Get redirect URL from Location header.
     * We need to re-fetch headers to get the Location header since we disabled FOLLOWLOCATION.
     */
    private function getRedirectUrl(string $currentUrl, int $httpCode): ?string
    {
        // Re-fetch just headers to get Location
        $ch = curl_init($currentUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        
        $validation = $this->validateUrl($currentUrl);
        if (!empty($validation['ips'])) {
            curl_setopt($ch, CURLOPT_RESOLVE, array_map(
                fn($ip) => "{$validation['host']}:{$validation['port']}:{$ip}",
                $validation['ips']
            ));
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response === false) {
            return null;
        }
        
        // Parse Location header
        if (preg_match('/^Location:\s*(.+)$/mi', $response, $matches)) {
            $location = trim($matches[1]);
            
            // Handle relative redirects
            if (str_starts_with($location, '/')) {
                $parsed = parse_url($currentUrl);
                $location = $parsed['scheme'] . '://' . $parsed['host'] . $location;
            } elseif (!filter_var($location, FILTER_VALIDATE_URL)) {
                // Relative path
                $parsed = parse_url($currentUrl);
                $basePath = dirname($parsed['path'] ?? '/');
                $location = $parsed['scheme'] . '://' . $parsed['host'] . $basePath . '/' . ltrim($location, '/');
            }
            
            return $location;
        }
        
        return null;
    }

    /**
     * Resolve hostname to IP addresses (IPv4 and IPv6).
     */
    private function resolveHost(string $host): array
    {
        // Check if already an IP address
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return [$host];
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return [$host];
        }

        // Resolve DNS
        $ips = [];
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        
        foreach ($records as $record) {
            if (($record['type'] === 'A' || $record['type'] === 'AAAA') && isset($record['ip'])) {
                $ips[] = $record['ip'];
            }
        }
        
        // Fallback to gethostbynamel for IPv4
        if (empty($ips)) {
            $v4 = gethostbynamel($host);
            if ($v4) {
                $ips = array_merge($ips, $v4);
            }
        }
        
        return array_unique($ips);
    }

    /**
     * Check if an IP address is private/internal.
     */
    private function isPrivateIp(string $ip): bool
    {
        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $longIp = ip2long($ip);
            if ($longIp === false) {
                return true;
            }
            
            foreach (self::PRIVATE_IPV4_RANGES as $range) {
                if ($this->ipInRange($longIp, $range)) {
                    return true;
                }
            }
            return false;
        }
        
        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $binaryIp = inet_pton($ip);
            if ($binaryIp === false) {
                return true;
            }
            
            foreach (self::PRIVATE_IPV6_RANGES as $range) {
                if ($this->ipv6InRange($binaryIp, $range)) {
                    return true;
                }
            }
            return false;
        }
        
        // Unknown format - block for safety
        return true;
    }

    /**
     * Check if IPv4 is in CIDR range.
     */
    private function ipInRange(int $ip, string $cidr): bool
    {
        [$rangeIp, $bits] = explode('/', $cidr);
        $rangeLong = ip2long($rangeIp);
        if ($rangeLong === false) {
            return false;
        }
        $mask = -1 << (32 - (int)$bits);
        return ($ip & $mask) === ($rangeLong & $mask);
    }

    /**
     * Check if IPv6 is in CIDR range.
     */
    private function ipv6InRange(string $binaryIp, string $cidr): bool
    {
        [$rangeIp, $bits] = explode('/', $cidr);
        $rangeBinary = inet_pton($rangeIp);
        if ($rangeBinary === false) {
            return false;
        }
        
        $bits = (int)$bits;
        $bytes = (int)ceil($bits / 8);
        
        for ($i = 0; $i < $bytes; $i++) {
            if ($i >= 16) break;
            
            $mask = 0xFF;
            if ($i === $bytes - 1) {
                $remainingBits = $bits % 8;
                if ($remainingBits !== 0) {
                    $mask = 0xFF << (8 - $remainingBits);
                }
            }
            
            if (($binaryIp[$i] & $mask) !== ($rangeBinary[$i] & $mask)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check if IP is a known cloud metadata endpoint.
     */
    private function isCloudMetadataIp(string $ip): bool
    {
        return in_array($ip, self::CLOUD_METADATA_IPS, true);
    }
}