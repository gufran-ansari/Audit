<?php
 
namespace App\Modules\Sitemaps;
 
use App\Modules\Projects\ProjectService;
use App\Services\AuthorizationService;
use App\Services\SecureUrlFetcher;
 
/**
 * Sitemap Service
 * 
 * Handles sitemap discovery and validation
 */
class SitemapService
{
    private $repository;
    private $validator;
    private $projectService;
    private SecureUrlFetcher $fetcher;
    private $authorizationService;
 
    // Common sitemap locations to check
    private $commonLocations = [
        '/sitemap.xml',
        '/sitemap_index.xml',
        '/sitemap1.xml',
        '/sitemap-index.xml'
    ];
 
    public function __construct()
    {
        $this->repository = new SitemapRepository();
        $this->validator = new SitemapValidator();
        $this->projectService = new ProjectService();
        $this->authorizationService = AuthorizationService::getInstance();
        $this->fetcher = new SecureUrlFetcher(
            timeout: 30,
            connectTimeout: 10,
            maxRedirects: 5,
            userAgent: 'AuditWithGufran/1.0 (SEO Audit Bot)',
            maxContentLength: 5 * 1024 * 1024
        );
    }

    /**
     * Discover sitemaps for a project
     */
    public function discoverSitemaps(int $projectId): array
    {
        if (!$this->authorizationService->canAccessProject($projectId)) {
            return [
                'success' => false,
                'error' => 'Project not found or access denied'
            ];
        }
        
        $project = $this->projectService->getProject($projectId);
        
        if (!$project) {
            return [
                'success' => false,
                'error' => 'Project not found'
            ];
        }

        $domain = $project['primary_domain'];
        $discoveredSitemaps = [];
        $errors = [];

        // Step 1: Check robots.txt
        $robotsSitemaps = $this->discoverFromRobotsTxt($domain);
        if (!empty($robotsSitemaps)) {
            foreach ($robotsSitemaps as $sitemapUrl) {
                $result = $this->addSitemap($projectId, $sitemapUrl, 'robots.txt');
                if ($result['success']) {
                    $discoveredSitemaps[] = $result['sitemap'];
                }
            }
        }

        // Step 2: Check common locations if nothing found in robots.txt
        if (empty($discoveredSitemaps)) {
            foreach ($this->commonLocations as $location) {
                $sitemapUrl = 'https://' . $domain . $location;
                
                if ($this->urlExists($sitemapUrl)) {
                    $result = $this->addSitemap($projectId, $sitemapUrl, 'common_location');
                    if ($result['success']) {
                        $discoveredSitemaps[] = $result['sitemap'];
                        break; // Stop after finding the first one
                    }
                }
            }
        }

        // If still nothing found, try HTTP instead of HTTPS
        if (empty($discoveredSitemaps)) {
            foreach ($this->commonLocations as $location) {
                $sitemapUrl = 'http://' . $domain . $location;
                
                if ($this->urlExists($sitemapUrl)) {
                    $result = $this->addSitemap($projectId, $sitemapUrl, 'common_location');
                    if ($result['success']) {
                        $discoveredSitemaps[] = $result['sitemap'];
                        break;
                    }
                }
            }
        }

        if (empty($discoveredSitemaps)) {
            return [
                'success' => false,
                'error' => 'No sitemaps found. Please provide a sitemap URL manually.'
            ];
        }

        return [
            'success' => true,
            'sitemaps' => $discoveredSitemaps,
            'count' => count($discoveredSitemaps)
        ];
    }

    /**
     * Discover sitemaps from robots.txt
     */
    private function discoverFromRobotsTxt(string $domain): array
    {
        $robotsUrls = [
            'https://' . $domain . '/robots.txt',
            'http://' . $domain . '/robots.txt'
        ];

        foreach ($robotsUrls as $robotsUrl) {
            try {
                $content = $this->fetchUrl($robotsUrl);
                
                if ($content === false) {
                    continue;
                }

                // Parse robots.txt for Sitemap entries
                $sitemaps = [];
                $lines = explode("\n", $content);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (stripos($line, 'Sitemap:') === 0) {
                        $sitemapUrl = trim(substr($line, 8));
                        if (!empty($sitemapUrl)) {
                            $sitemaps[] = $sitemapUrl;
                        }
                    }
                }

                if (!empty($sitemaps)) {
                    return $sitemaps;
                }
            } catch (\Exception $e) {
                logMessage("Failed to fetch robots.txt from $robotsUrl: " . $e->getMessage());
                continue;
            }
        }

        return [];
    }

    /**
     * Add sitemap manually or from discovery
     */
    public function addSitemap(int $projectId, string $sitemapUrl, string $discoveredFrom = 'manual'): array
    {
        if (!$this->authorizationService->canAccessProject($projectId)) {
            return [
                'success' => false,
                'error' => 'Project not found or access denied'
            ];
        }
        
        // Validate URL
        if (!$this->validator->validateUrl($sitemapUrl)) {
            return [
                'success' => false,
                'error' => $this->validator->getFirstError()
            ];
        }

        // Check if already exists
        if ($this->repository->exists($projectId, $sitemapUrl)) {
            return [
                'success' => false,
                'error' => 'This sitemap has already been added to the project'
            ];
        }

        // Fetch and validate sitemap
        $validation = $this->validateSitemap($sitemapUrl);

        if (!$validation['success']) {
            // Store as unreachable/invalid
            $sitemapId = $this->repository->create([
                'project_id' => $projectId,
                'sitemap_url' => $sitemapUrl,
                'sitemap_type' => 'sitemap',
                'status' => $validation['status'] ?? 'invalid',
                'discovered_from' => $discoveredFrom,
                'total_urls' => 0,
                'last_fetched_at' => date('Y-m-d H:i:s')
            ]);

            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        // Store validated sitemap
        $sitemapId = $this->repository->create([
            'project_id' => $projectId,
            'sitemap_url' => $sitemapUrl,
            'sitemap_type' => $validation['type'],
            'status' => 'validated',
            'discovered_from' => $discoveredFrom,
            'total_urls' => $validation['count'] ?? 0,
            'last_fetched_at' => date('Y-m-d H:i:s')
        ]);

        if (!$sitemapId) {
            return [
                'success' => false,
                'error' => 'Failed to save sitemap'
            ];
        }

        $sitemap = $this->repository->findById($sitemapId);

        return [
            'success' => true,
            'sitemap' => $sitemap
        ];
    }

    /**
     * Validate sitemap URL and content
     */
    private function validateSitemap(string $url): array
    {
        // Check if URL is reachable
        if (!$this->urlExists($url)) {
            return [
                'success' => false,
                'status' => 'unreachable',
                'error' => 'Sitemap URL is not reachable'
            ];
        }

        // Fetch sitemap content
        $content = $this->fetchUrl($url);

        if ($content === false) {
            return [
                'success' => false,
                'status' => 'unreachable',
                'error' => 'Failed to fetch sitemap content'
            ];
        }

        // Validate XML structure
        $schemaValidation = $this->validator->validateSitemapSchema($content);

        if (!$schemaValidation['valid']) {
            return [
                'success' => false,
                'status' => 'invalid',
                'error' => $schemaValidation['error'] ?? 'Invalid sitemap format'
            ];
        }

        return [
            'success' => true,
            'type' => $schemaValidation['type'],
            'count' => $schemaValidation['count'] ?? 0
        ];
    }

    /**
     * Check if URL exists and is reachable
     */
    private function urlExists(string $url): bool
    {
        $result = $this->fetcher->fetch($url, true); // headOnly = true
        return $result['success'] && $result['http_code'] >= 200 && $result['http_code'] < 400;
    }

    /**
     * Fetch URL content
     */
    private function fetchUrl(string $url): string|false
    {
        $result = $this->fetcher->fetch($url);

        if (!$result['success']) {
            return false;
        }

        return $result['html'];
    }

/**
     * Get all sitemaps for a project
     */
    public function getProjectSitemaps(int $projectId): array
    {
        if (!$this->authorizationService->canAccessProject($projectId)) {
            return [];
        }
        return $this->repository->getByProject($projectId);
    }
 
    /**
     * Get active sitemaps for a project
     */
    public function getActiveSitemaps(int $projectId): array
    {
        if (!$this->authorizationService->canAccessProject($projectId)) {
            return [];
        }
        return $this->repository->getByStatus($projectId, 'active');
    }
 
    /**
     * Disable sitemap (prevent imports)
     */
    public function disableSitemap(int $sitemapId): array
    {
        if (!$this->authorizationService->canAccessSitemap($sitemapId)) {
            return [
                'success' => false,
                'error' => 'Sitemap not found or access denied'
            ];
        }
        
        $sitemap = $this->repository->findById($sitemapId);
        
        if (!$sitemap) {
            return [
                'success' => false,
                'error' => 'Sitemap not found'
            ];
        }

        $updated = $this->repository->update($sitemapId, ['sitemap_status' => 'disabled']);

        if (!$updated) {
            return [
                'success' => false,
                'error' => 'Failed to disable sitemap'
            ];
        }

        return [
            'success' => true,
            'message' => 'Sitemap disabled successfully'
        ];
    }

    /**
     * Enable sitemap
     */
    public function enableSitemap(int $sitemapId): array
    {
        if (!$this->authorizationService->canAccessSitemap($sitemapId)) {
            return [
                'success' => false,
                'error' => 'Sitemap not found or access denied'
            ];
        }
        
        $sitemap = $this->repository->findById($sitemapId);
        
        if (!$sitemap) {
            return [
                'success' => false,
                'error' => 'Sitemap not found'
            ];
        }

        $updated = $this->repository->update($sitemapId, ['sitemap_status' => 'active']);

        if (!$updated) {
            return [
                'success' => false,
                'error' => 'Failed to enable sitemap'
            ];
        }

        return [
            'success' => true,
            'message' => 'Sitemap enabled successfully'
        ];
    }

/**
     * Get sitemap by ID
     */
    public function getSitemap(int $sitemapId): ?array
    {
        if (!$this->authorizationService->canAccessSitemap($sitemapId)) {
            return null;
        }
        return $this->repository->findById($sitemapId);
    }
 
    /**
     * Delete sitemap
     */
    public function deleteSitemap(int $sitemapId): array
    {
        if (!$this->authorizationService->canAccessSitemap($sitemapId)) {
            return [
                'success' => false,
                'error' => 'Sitemap not found or access denied'
            ];
        }
        
        $sitemap = $this->repository->findById($sitemapId);
        
        if (!$sitemap) {
            return [
                'success' => false,
                'error' => 'Sitemap not found'
            ];
        }

        $deleted = $this->repository->delete($sitemapId);

        if (!$deleted) {
            return [
                'success' => false,
                'error' => 'Failed to delete sitemap'
            ];
        }

        return [
            'success' => true,
            'message' => 'Sitemap deleted successfully'
        ];
    }

    /**
     * Re-validate sitemap
     */
    public function revalidateSitemap(int $sitemapId): array
    {
        if (!$this->authorizationService->canAccessSitemap($sitemapId)) {
            return [
                'success' => false,
                'error' => 'Sitemap not found or access denied'
            ];
        }
        
        $sitemap = $this->repository->findById($sitemapId);
        
        if (!$sitemap) {
            return [
                'success' => false,
                'error' => 'Sitemap not found'
            ];
        }

        $validation = $this->validateSitemap($sitemap['sitemap_url']);

        if (!$validation['success']) {
            $this->repository->update($sitemapId, [
                'status' => $validation['status'] ?? 'invalid',
                'last_fetched_at' => date('Y-m-d H:i:s')
            ]);

            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        $this->repository->update($sitemapId, [
            'sitemap_type' => $validation['type'],
            'status' => 'validated',
            'total_urls' => $validation['count'] ?? 0,
            'last_fetched_at' => date('Y-m-d H:i:s')
        ]);

        $sitemap = $this->repository->findById($sitemapId);

        return [
            'success' => true,
            'sitemap' => $sitemap
        ];
    }

    /**
     * Get sitemap statistics for a project
     */
    public function getSitemapStats(int $projectId): array
    {
        if (!$this->authorizationService->canAccessProject($projectId)) {
            return [
                'total_sitemaps' => 0,
                'validated' => 0,
                'invalid' => 0,
                'unreachable' => 0,
                'total_urls' => 0
            ];
        }
        return $this->repository->getStats($projectId);
    }
}
