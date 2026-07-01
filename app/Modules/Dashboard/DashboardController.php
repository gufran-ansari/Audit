<?php

namespace App\Modules\Dashboard;

use App\Middleware\AuthMiddleware;

/**
 * Dashboard Controller
 * 
 * Provides dashboard data: stats, activity, recent items, search, notifications.
 */
class DashboardController
{
    private DashboardService $service;
    private AuthMiddleware $authMiddleware;

    public function __construct()
    {
        $this->service = new DashboardService();
        $this->authMiddleware = new AuthMiddleware();
    }

    public function handle(): void
    {
        $this->authMiddleware->handle();

        $action = input('action', 'overview');
        $method = isMethod('POST') ? 'POST' : 'GET';

        switch ($action) {
            case 'overview':
                $this->overview();
                break;

            case 'activity':
                $this->recentActivity();
                break;

            case 'search':
                $this->globalSearch();
                break;

            case 'notifications':
                $this->getNotifications();
                break;

            case 'notification_read':
                if ($method === 'POST') $this->markRead();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'notification_dismiss':
                if ($method === 'POST') $this->dismissNotification();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'notifications_read_all':
                if ($method === 'POST') $this->markAllRead();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    private function overview(): void
    {
        $data = $this->service->getOverview();
        jsonResponse(['success' => true, 'data' => $data]);
    }

    private function recentActivity(): void
    {
        $limit = (int) input('limit', 20);
        $activity = $this->service->getRecentActivity($limit);
        jsonResponse(['success' => true, 'activity' => $activity]);
    }

    private function globalSearch(): void
    {
        $query = input('q', '');
        if (strlen($query) < 2) {
            jsonResponse(['success' => true, 'results' => []]);
            return;
        }

        $results = $this->service->search($query);
        jsonResponse(['success' => true, 'results' => $results]);
    }

    private function getNotifications(): void
    {
        $notifications = $this->service->getNotifications();
        jsonResponse(['success' => true, 'notifications' => $notifications]);
    }

    private function markRead(): void
    {
        $id = (int) input('id');
        if (!$id) {
            jsonResponse(['error' => 'ID required'], 400);
            return;
        }
        $this->service->markNotificationRead($id);
        jsonResponse(['success' => true]);
    }

    private function dismissNotification(): void
    {
        $id = (int) input('id');
        if (!$id) {
            jsonResponse(['error' => 'ID required'], 400);
            return;
        }
        $this->service->dismissNotification($id);
        jsonResponse(['success' => true]);
    }

    private function markAllRead(): void
    {
        $this->service->markAllRead();
        jsonResponse(['success' => true]);
    }
}
