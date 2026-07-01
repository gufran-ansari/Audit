<?php

namespace App\Modules\Pages;

use App\Modules\Sitemaps\SitemapService;
use App\Modules\Sitemaps\SitemapRepository;

/**
 * Page Service
 * 
 * Handles URL extraction, normalization, and import
 */
class PageService
{
    private $repository;
    private $validator;
    private $sitemapService;
    private $sitemapRepository;

    // Memory limit for XML processing (in bytes)
    private $memoryLimit = 50 * 1024 * 1024; // 50MB

    public function __construct()
    {
        $this->repository = new PageRepository();
        $this->validator = new PageValidator();
        $this->sitemapService = new SitemapService();
        $this->sitemapRepository = new SitemapRepository();
    }

    /**
     * Import URLs from a sitemap
     */
    public function importFromSitemap(int $sitemapId): array
    {
        $sitemap = $this->sitemapRepository->findById($sitemapId);
        
        if (!$sitemap) {
            return [
                'success' => false,
                'error' => 'Sitemap not found'
            ];
        }

        // Check sitemap type
        if ($sitemap['sitemap_type'] === 'sitemap_index') {
            return $this->importFromSitemapIndex($sitemapId, $sitemap['sitemap_url'], $sitemap['project_id']);
        }

        // Extract URLs from regular sitemap
        $extraction = $this->extractUrlsFromSitemap($sitemap['sitemap_url']);

        if (!$extraction['success']) {
            return [
                'success' => false,
                'error' => $extraction['error']
            ];
        }

        // Import URLs
        $result = $this->importUrls($sitemap['project_id'], $sitemapId, $extraction['urls']);

        // Update sitemap total_urls count and last_imported_at
        $this->sitemapRepository->update($sitemapId, [
            'total_urls' => $result['imported'] + $result['duplicates'],
            'last_imported_at' => date('Y-m-d H:i:s')
        ]);

        return [
            'success' => true,
            'imported' => $result['imported'],
            'duplicates' => $result['duplicates'],
            'invalid' => $result['invalid'],
            'total_processed' => $result['total_processed']
        ];
    }

    /**
     * Import URLs from sitemap index
     */
    private function importFromSitemapIndex(int $sitemapIndexId, string $indexUrl, int $projectId): array
    {
        $extraction = $this->extractSitemapsFromIndex($indexUrl);

        if (!$extraction['success']) {
            return [
                'success' => false,
                'error' => $extraction['error']
            ];
        }

        $totalImported = 0;
        $totalDuplicates = 0;
        $totalInvalid = 0;
        $nestedSitemaps = 0;

        foreach ($extraction['sitemaps'] as $nestedSitemapUrl) {
            // Check if nested sitemap already exists
            if (!$this->sitemapRepository->findByUrl($projectId, $nestedSitemapUrl)) {
                // Add nested sitemap
                $nestedSitemapId = $this->sitemapRepository->create([
                    'project_id' => $projectId,
                    'sitemap_url' => $nestedSitemapUrl,
                    'sitemap_type' => 'nested_sitemap',
                    'status' => 'discovered',
                    'discovered_from' => 'sitemap_index',
                    'total_urls' => 0,
                    'last_fetched_at' => date('Y-m-d H:i:s')
                ]);

                if ($nestedSitemapId) {
                    $nestedSitemaps++;
                    
                    // Import URLs from nested sitemap
                    $nestedExtraction = $this->extractUrlsFromSitemap($nestedSitemapUrl);
                    
                    if ($nestedExtraction['success']) {
                        $result = $this->importUrls($projectId, $nestedSitemapId, $nestedExtraction['urls']);
                        
                        $totalImported += $result['imported'];
                        $totalDuplicates += $result['duplicates'];
                        $totalInvalid += $result['invalid'];
                        
                        // Update nested sitemap count
                        $this->sitemapRepository->updateTotalUrls($nestedSitemapId, $result['imported']);
                        $this->sitemapRepository->updateStatus($nestedSitemapId, 'validated');
                    }
                }
            }
        }

        // Update sitemap index with total from nested sitemaps
        $this->sitemapRepository->update($sitemapIndexId, [
            'total_urls' => $totalImported + $totalDuplicates,
            'last_imported_at' => date('Y-m-d H:i:s')
        ]);

        return [
            'success' => true,
            'imported' => $totalImported,
            'duplicates' => $totalDuplicates,
            'invalid' => $totalInvalid,
            'nested_sitemaps' => $nestedSitemaps,
            'total_processed' => $totalImported + $totalDuplicates + $totalInvalid
        ];
    }

    /**
     * Extract URLs from sitemap XML
     */
    private function extractUrlsFromSitemap(string $sitemapUrl): array
    {
        try {
            // Fetch sitemap content
            $xmlContent = $this->fetchUrl($sitemapUrl);

            if ($xmlContent === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch sitemap'
                ];
            }

            // Use XMLReader for memory-efficient parsing
            $urls = $this->parseXmlForUrls($xmlContent);

            return [
                'success' => true,
                'urls' => $urls
            ];

        } catch (\Exception $e) {
            logMessage("Failed to extract URLs from sitemap $sitemapUrl: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => 'Failed to parse sitemap: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extract sitemap URLs from sitemap index
     */
    private function extractSitemapsFromIndex(string $indexUrl): array
    {
        try {
            $xmlContent = $this->fetchUrl($indexUrl);

            if ($xmlContent === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch sitemap index'
                ];
            }

            $sitemaps = $this->parseXmlForSitemaps($xmlContent);

            return [
                'success' => true,
                'sitemaps' => $sitemaps
            ];

        } catch (\Exception $e) {
            logMessage("Failed to extract sitemaps from index $indexUrl: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => 'Failed to parse sitemap index'
            ];
        }
    }

    /**
     * Parse XML for URLs (memory-efficient)
     */
    private function parseXmlForUrls(string $xmlContent): array
    {
        $urls = [];

        // Use XMLReader for memory-efficient parsing
        $reader = new \XMLReader();
        $reader->XML($xmlContent);

        while ($reader->read()) {
            if ($reader->nodeType == \XMLReader::ELEMENT && $reader->name === 'url') {
                // Read url element
                $urlElement = $reader->expand();
                
                // Get loc element
                $locNodes = $urlElement->getElementsByTagName('loc');
                if ($locNodes->length > 0) {
                    $url = trim($locNodes->item(0)->textContent);
                    if (!empty($url)) {
                        $urls[] = $url;
                    }
                }
            }
        }

        $reader->close();

        return $urls;
    }

    /**
     * Parse XML for sitemap URLs (from sitemap index)
     */
    private function parseXmlForSitemaps(string $xmlContent): array
    {
        $sitemaps = [];

        $reader = new \XMLReader();
        $reader->XML($xmlContent);

        while ($reader->read()) {
            if ($reader->nodeType == \XMLReader::ELEMENT && $reader->name === 'sitemap') {
                $sitemapElement = $reader->expand();
                
                $locNodes = $sitemapElement->getElementsByTagName('loc');
                if ($locNodes->length > 0) {
                    $url = trim($locNodes->item(0)->textContent);
                    if (!empty($url)) {
                        $sitemaps[] = $url;
                    }
                }
            }
        }

        $reader->close();

        return $sitemaps;
    }

    /**
     * Import URLs into database
     */
    private function importUrls(int $projectId, int $sitemapId, array $urls): array
    {
        $imported = 0;
        $duplicates = 0;
        $invalid = 0;
        
        $pagesToInsert = [];

        foreach ($urls as $url) {
            // Validate URL
            if (!$this->validator->validateUrl($url)) {
                $invalid++;
                continue;
            }

            // Normalize URL
            $normalizedUrl = $this->validator->normalizeUrl($url);
            
            // Generate hash
            $urlHash = $this->validator->generateUrlHash($normalizedUrl);

            // Check if already exists
            if ($this->repository->exists($projectId, $urlHash)) {
                $duplicates++;
                // Update last_seen_at for existing URL
                $existing = $this->repository->findByUrlHash($projectId, $urlHash);
                if ($existing) {
                    $this->repository->updateLastSeen($existing['id']);
                }
                continue;
            }

            // Add to batch insert
            $pagesToInsert[] = [
                'project_id' => $projectId,
                'sitemap_id' => $sitemapId,
                'original_url' => $url,
                'normalized_url' => $normalizedUrl,
                'url_hash' => $urlHash,
                'page_status' => 'pending'
            ];

            // Batch insert every 100 records for performance
            if (count($pagesToInsert) >= 100) {
                $inserted = $this->repository->batchInsert($pagesToInsert);
                $imported += $inserted;
                $pagesToInsert = [];
            }
        }

        // Insert remaining
        if (!empty($pagesToInsert)) {
            $inserted = $this->repository->batchInsert($pagesToInsert);
            $imported += $inserted;
        }

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'invalid' => $invalid,
            'total_processed' => count($urls)
        ];
    }

    /**
     * Get all pages for a project
     */
    public function getProjectPages(int $projectId, int $page = 1, int $perPage = 50, array $filters = []): array
    {
        $pages = $this->repository->getByProject($projectId, $page, $perPage, $filters);
        $total = $this->repository->getTotalCount($projectId, $filters);

        return [
            'pages' => $pages,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * Search pages
     */
    public function searchPages(int $projectId, string $searchTerm, int $page = 1, int $perPage = 50): array
    {
        $pages = $this->repository->search($projectId, $searchTerm, $page, $perPage);
        
        return [
            'pages' => $pages,
            'search_term' => $searchTerm
        ];
    }

    /**
     * Get page statistics
     */
    public function getPageStats(int $projectId): array
    {
        return $this->repository->getStats($projectId);
    }

    /**
     * Delete page
     */
    public function deletePage(int $pageId): array
    {
        $page = $this->repository->findById($pageId);
        
        if (!$page) {
            return [
                'success' => false,
                'error' => 'Page not found'
            ];
        }

        $deleted = $this->repository->delete($pageId);

        if (!$deleted) {
            return [
                'success' => false,
                'error' => 'Failed to delete page'
            ];
        }

        return [
            'success' => true,
            'message' => 'Page deleted successfully'
        ];
    }

    /**
     * Fetch URL content
     */
    private function fetchUrl(string $url): string|false
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AuditWithGufran/1.0');
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 400 && $content !== false) {
            return $content;
        }

        return false;
    }
}
