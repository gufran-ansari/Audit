<?php

namespace App\Modules\Security;

use App\Middleware\ApiAuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Services\ActivityLogService;
use App\Services\SecurityMonitoringService;

/**
 * Security Dashboard Controller
 * 
 * Super-admin only endpoints for security monitoring dashboard:
 * - Security event statistics and timeline
 * - Filtered security events with pagination
 * - Alert threshold configuration
 * - Anomaly detection results
 * - Notification settings
 * - SIEM integration settings
 */
class SecurityDashboardController
{
    private ActivityLogService $activityLog;
    private SecurityMonitoringService $monitoring;
    private ApiAuthMiddleware $apiAuth;

    public function __construct()
    {
        $this->activityLog = ActivityLogService::getInstance();
        $this->monitoring = SecurityMonitoringService::getInstance();
        $this->apiAuth = new ApiAuthMiddleware(['auth' => true, 'csrf' => true, 'api' => true]);
    }

    public function handle(): void
    {
        // Require authentication and CSRF
        $this->apiAuth->handle();
        
        // Require super_admin role
        (new RoleMiddleware())->superAdmin();

        $action = input('action', 'stats');
        $method = isMethod('POST') ? 'POST' : 'GET';

        switch ($action) {
            case 'stats':
                $this->getStats();
                break;

            case 'events':
                $this->getEvents();
                break;

            case 'event_detail':
                $this->getEventDetail();
                break;

            case 'anomalies':
                $this->getAnomalies();
                break;

            case 'thresholds':
                if ($method === 'GET') $this->getThresholds();
                elseif ($method === 'POST') $this->updateThreshold();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'notification_settings':
                if ($method === 'GET') $this->getNotificationSettings();
                elseif ($method === 'POST') $this->updateNotificationSettings();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'siem_settings':
                if ($method === 'GET') $this->getSiemSettings();
                elseif ($method === 'POST') $this->updateSiemSettings();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            case 'test_notification':
                if ($method === 'POST') $this->testNotification();
                else jsonResponse(['error' => 'Method not allowed'], 405);
                break;

            default:
                jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Get security event statistics and dashboard overview
     */
    private function getStats(): void
    {
        $hours = (int) input('hours', 24);
        $hours = max(1, min($hours, 720)); // Clamp 1 hour to 30 days

        $stats = $this->activityLog->getSecurityEventStats($hours);
        
        // Add alert threshold status
        $thresholds = $this->monitoring->getConfiguredThresholds();
        $stats['thresholds'] = $thresholds;
        $stats['recipients'] = $this->monitoring->getNotificationRecipients();

        jsonResponse(['success' => true, 'data' => $stats]);
    }

    /**
     * Get filtered security events with pagination
     */
    private function getEvents(): void
    {
        $page = max(1, (int) input('page', 1));
        $perPage = max(1, min(100, (int) input('per_page', 50)));

        $filters = [];
        if ($dateFrom = input('date_from')) $filters['date_from'] = $dateFrom;
        if ($dateTo = input('date_to')) $filters['date_to'] = $dateTo;
        if ($eventType = input('event_type')) $filters['event_type'] = $eventType;
        if ($severity = input('severity')) $filters['severity'] = $severity;
        if ($ip = input('ip')) $filters['ip'] = $ip;
        if ($userId = input('user_id')) $filters['user_id'] = $userId;
        if ($requestId = input('request_id')) $filters['request_id'] = $requestId;

        $result = $this->activityLog->getSecurityEventsFiltered($filters, $page, $perPage);
        
        jsonResponse(['success' => true, 'data' => $result]);
    }

    /**
     * Get detailed view of a single security event
     */
    private function getEventDetail(): void
    {
        $id = (int) input('id');
        if (!$id) {
            jsonResponse(['error' => 'Event ID required'], 400);
            return;
        }

        $result = $this->activityLog->db->query(
            "SELECT al.*, u.name as user_name, u.email as user_email
             FROM activity_log al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.id = :id AND al.action LIKE 'security.%'",
            ['id' => $id]
        );

        if (!$result) {
            jsonResponse(['error' => 'Event not found'], 404);
            return;
        }

        $event = $result[0];
        $event['metadata_parsed'] = json_decode($event['metadata'] ?? '{}', true);
        
        jsonResponse(['success' => true, 'event' => $event]);
    }

    /**
     * Get anomaly detection results
     */
    private function getAnomalies(): void
    {
        $lookback = max(60, min(3600, (int) input('lookback_seconds', 300)));
        $anomalies = $this->monitoring->detectAnomalies($lookback);
        
        jsonResponse(['success' => true, 'anomalies' => $anomalies]);
    }

    /**
     * Get current alert threshold configuration
     */
    private function getThresholds(): void
    {
        $thresholds = $this->monitoring->getConfiguredThresholds();
        jsonResponse(['success' => true, 'thresholds' => $thresholds]);
    }

    /**
     * Update alert threshold for an event type
     */
    private function updateThreshold(): void
    {
        $eventType = input('event_type');
        if (!$eventType) {
            jsonResponse(['error' => 'event_type required'], 400);
            return;
        }

        $config = [];
        if (isset($_POST['max_events'])) $config['max_events'] = max(1, (int) $_POST['max_events']);
        if (isset($_POST['window_seconds'])) $config['window_seconds'] = max(60, (int) $_POST['window_seconds']);
        if (isset($_POST['cooldown_seconds'])) $config['cooldown_seconds'] = max(60, (int) $_POST['cooldown_seconds']);
        if (isset($_POST['severity'])) $config['severity'] = $_POST['severity'];

        if (empty($config)) {
            jsonResponse(['error' => 'No configuration provided'], 400);
            return;
        }

        $success = $this->monitoring->updateThreshold($eventType, $config);
        
        if ($success) {
            jsonResponse(['success' => true, 'message' => 'Threshold updated']);
        } else {
            jsonResponse(['error' => 'Invalid event type or update failed'], 400);
        }
    }

    /**
     * Get notification recipient settings
     */
    private function getNotificationSettings(): void
    {
        $settings = \App\Modules\Settings\SettingsService::getInstance();
        
        $data = [
            'email' => $settings->get('security_alert_email') ?? '',
            'webhook' => $settings->get('security_alert_webhook') ?? '',
        ];
        
        jsonResponse(['success' => true, 'settings' => $data]);
    }

    /**
     * Update notification recipient settings
     */
    private function updateNotificationSettings(): void
    {
        $settings = \App\Modules\Settings\SettingsService::getInstance();
        $repo = $settings->getRepository();

        if (isset($_POST['email'])) {
            $repo->set('security_alert_email', trim((string) $_POST['email']));
        }
        if (isset($_POST['webhook'])) {
            $repo->set('security_alert_webhook', trim((string) $_POST['webhook']));
        }

        jsonResponse(['success' => true, 'message' => 'Notification settings updated']);
    }

    /**
     * Get SIEM integration settings
     */
    private function getSiemSettings(): void
    {
        $settings = \App\Modules\Settings\SettingsService::getInstance();
        
        $data = [
            'webhook' => $settings->get('security_siem_webhook') ?? '',
        ];
        
        jsonResponse(['success' => true, 'settings' => $data]);
    }

    /**
     * Update SIEM integration settings
     */
    private function updateSiemSettings(): void
    {
        $settings = \App\Modules\Settings\SettingsService::getInstance();
        $repo = $settings->getRepository();

        if (isset($_POST['webhook'])) {
            $url = trim((string) $_POST['webhook']);
            if ($url && !$this->isValidWebhookUrl($url)) {
                jsonResponse(['error' => 'Invalid webhook URL (must be HTTPS, no internal IPs)'], 400);
                return;
            }
            $repo->set('security_siem_webhook', $url);
        }

        jsonResponse(['success' => true, 'message' => 'SIEM settings updated']);
    }

    /**
     * Send a test notification
     */
    private function testNotification(): void
    {
        $type = input('type', 'email'); // 'email' or 'webhook'
        
        $testAlert = [
            'type' => 'test_alert',
            'severity' => 'info',
            'message' => 'This is a test alert from Audit withGufran Security Monitoring',
            'timestamp' => date('c'),
        ];

        try {
            if ($type === 'email') {
                $recipients = $this->monitoring->getNotificationRecipients();
                if (!empty($recipients['email'])) {
                    $this->monitoring->sendEmailAlert($testAlert, $recipients['email']);
                } else {
                    jsonResponse(['error' => 'No email recipients configured'], 400);
                    return;
                }
            } elseif ($type === 'webhook') {
                $recipients = $this->monitoring->getNotificationRecipients();
                if (!empty($recipients['webhook'])) {
                    $this->monitoring->sendWebhookAlert($testAlert, $recipients['webhook']);
                } else {
                    jsonResponse(['error' => 'No webhook recipients configured'], 400);
                    return;
                }
            } else {
                jsonResponse(['error' => 'Invalid test type'], 400);
                return;
            }

            jsonResponse(['success' => true, 'message' => "Test $type sent"]);
        } catch (\Throwable $e) {
            jsonResponse(['error' => 'Test failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Validate webhook URL against SSRF (reused from SecurityMonitoringService)
     */
    private function isValidWebhookUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);
        if (!$parsed || !in_array($parsed['scheme'] ?? '', ['https'])) {
            return false;
        }

        $host = $parsed['host'] ?? '';
        
        $blockedPatterns = [
            '/^localhost$/',
            '/^127\./',
            '/^10\./',
            '/^172\.(1[6-9]|2[0-9]|3[0-1])\./',
            '/^192\.168\./',
            '/^169\.254\./',
            '/^::1$/',
            '/^fe80::/',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $host)) {
                return false;
            }
        }

        return true;
    }
}