<?php

namespace App\Modules\Sitemaps;

use App\Middleware\AuthMiddleware;

/**
 * Sitemap Controller
 * 
 * Handles HTTP requests for sitemap management
 */
class SitemapController
{
    private $service;
    private $authMiddleware;

    public function __construct()
    {
        $this->service = new SitemapService();
        $this->authMiddleware = new AuthMiddleware();
    }

    /**
     * Handle sitemap requests
     */
    public function handle(): void
    {
        // Protect all routes
        $this->authMiddleware->handle();

        $action = input('action', 'list');
        $method = isMethod('POST') ? 'POST' : 'GET';

        switch ($action) {
            case 'discover':
                if ($method === 'POST') {
                    $this->discoverSitemaps();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'add':
                if ($method === 'POST') {
                    $this->addSitemap();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'list':
                $this->listSitemaps();
                break;
            
            case 'view':
                $this->viewSitemap();
                break;
            
            case 'delete':
                if ($method === 'POST') {
                    $this->deleteSitemap();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'revalidate':
                if ($method === 'POST') {
                    $this->revalidateSitemap();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'stats':
                $this->getSitemapStats();
                break;
            
            case 'disable':
                if ($method === 'POST') {
                    $this->disableSitemap();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            case 'enable':
                if ($method === 'POST') {
                    $this->enableSitemap();
                } else {
                    jsonResponse(['error' => 'Method not allowed'], 405);
                }
                break;
            
            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Discover sitemaps automatically
     */
    private function discoverSitemaps(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $result = $this->service->discoverSitemaps($projectId);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => 'Sitemaps discovered successfully',
                'sitemaps' => $result['sitemaps'],
                'count' => $result['count']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 404);
        }
    }

    /**
     * Add sitemap manually
     */
    private function addSitemap(): void
    {
        $projectId = (int)input('project_id');
        $sitemapUrl = input('sitemap_url');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        if (!$sitemapUrl) {
            jsonResponse(['error' => 'Sitemap URL is required'], 400);
            return;
        }

        $result = $this->service->addSitemap($projectId, $sitemapUrl, 'manual');

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => 'Sitemap added successfully',
                'sitemap' => $result['sitemap']
            ], 201);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 422);
        }
    }

    /**
     * List sitemaps for a project
     */
    private function listSitemaps(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $sitemaps = $this->service->getProjectSitemaps($projectId);

        jsonResponse([
            'success' => true,
            'sitemaps' => $sitemaps
        ]);
    }

    /**
     * View single sitemap
     */
    private function viewSitemap(): void
    {
        $sitemapId = (int)input('sitemap_id');

        if (!$sitemapId) {
            jsonResponse(['error' => 'Sitemap ID is required'], 400);
            return;
        }

        $sitemap = $this->service->getSitemap($sitemapId);

        if (!$sitemap) {
            jsonResponse(['error' => 'Sitemap not found'], 404);
            return;
        }

        jsonResponse([
            'success' => true,
            'sitemap' => $sitemap
        ]);
    }

    /**
     * Delete sitemap
     */
    private function deleteSitemap(): void
    {
        $sitemapId = (int)input('sitemap_id');

        if (!$sitemapId) {
            jsonResponse(['error' => 'Sitemap ID is required'], 400);
            return;
        }

        $result = $this->service->deleteSitemap($sitemapId);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 404);
        }
    }

    /**
     * Re-validate sitemap
     */
    private function revalidateSitemap(): void
    {
        $sitemapId = (int)input('sitemap_id');

        if (!$sitemapId) {
            jsonResponse(['error' => 'Sitemap ID is required'], 400);
            return;
        }

        $result = $this->service->revalidateSitemap($sitemapId);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => 'Sitemap re-validated successfully',
                'sitemap' => $result['sitemap']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 422);
        }
    }

    /**
     * Get sitemap statistics
     */
    private function getSitemapStats(): void
    {
        $projectId = (int)input('project_id');

        if (!$projectId) {
            jsonResponse(['error' => 'Project ID is required'], 400);
            return;
        }

        $stats = $this->service->getSitemapStats($projectId);

        jsonResponse([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Disable sitemap
     */
    private function disableSitemap(): void
    {
        $sitemapId = (int)input('sitemap_id');

        if (!$sitemapId) {
            jsonResponse(['error' => 'Sitemap ID is required'], 400);
            return;
        }

        $result = $this->service->disableSitemap($sitemapId);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 404);
        }
    }

    /**
     * Enable sitemap
     */
    private function enableSitemap(): void
    {
        $sitemapId = (int)input('sitemap_id');

        if (!$sitemapId) {
            jsonResponse(['error' => 'Sitemap ID is required'], 400);
            return;
        }

        $result = $this->service->enableSitemap($sitemapId);

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => $result['error']
            ], 404);
        }
    }
}
