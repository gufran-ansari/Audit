<?php

namespace App\Services;

use Database\Database;

/**
 * Security Monitoring Service
 * 
 * Provides alert threshold evaluation, anomaly detection, and notification
 * delivery for security events. Reuses existing activity_log infrastructure.
 */
class SecurityMonitoringService
{
    private static ?self $instance = null;
    private $db;
    private $settings;

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->settings = \App\Modules\Settings\SettingsService::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─── ALERT THRESHOLDS ──────────────────────────────────────────

    /**
     * Get default alert thresholds configuration
     */
    public function getDefaultThresholds(): array
    {
        return [
            'auth_failed' => [
                'max_events' => 10,
                'window_seconds' => 300,  // 5 minutes
                'severity' => 'high',
                'cooldown_seconds' => 900, // 15 minutes
            ],
            'csrf_failed' => [
                'max_events' => 5,
                'window_seconds' => 300,
                'severity' => 'high',
                'cooldown_seconds' => 900,
            ],
            'authz_denied' => [
                'max_events' => 20,
                'window_seconds' => 300,
                'severity' => 'medium',
                'cooldown_seconds' => 900,
            ],
            'suspicious_activity' => [
                'max_events' => 5,
                'window_seconds' => 300,
                'severity' => 'high',
                'cooldown_seconds' => 1800,
            ],
        ];
    }

    /**
     * Get configured alert thresholds from settings
     */
    public function getConfiguredThresholds(): array
    {
        $defaults = $this->getDefaultThresholds();
        $configured = [];

        foreach ($defaults as $eventType => $default) {
            $key = "security_alert_{$eventType}";
            $value = $this->settings->get($key);
            
            if ($value) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $configured[$eventType] = array_merge($default, $decoded);
                    continue;
                }
            }
            $configured[$eventType] = $default;
        }

        return $configured;
    }

    /**
     * Update alert threshold configuration
     */
    public function updateThreshold(string $eventType, array $config): bool
    {
        $defaults = $this->getDefaultThresholds();
        if (!isset($defaults[$eventType])) {
            return false;
        }

        $merged = array_merge($defaults[$eventType], $config);
        $key = "security_alert_{$eventType}";
        
        return $this->settings->getRepository()->set($key, json_encode($merged));
    }

    /**
     * Check if alert should fire for an event type
     */
    public function evaluateThreshold(string $eventType, int $eventCount, int $windowSeconds): bool
    {
        $thresholds = $this->getConfiguredThresholds();
        $threshold = $thresholds[$eventType] ?? null;

        if (!$threshold) {
            return false;
        }

        // Check if event count exceeds threshold
        if ($eventCount < $threshold['max_events']) {
            return false;
        }

        // Check cooldown - has an alert been fired recently?
        $lastAlert = $this->getLastAlertTime($eventType);
        if ($lastAlert && (time() - $lastAlert < ($threshold['cooldown_seconds'] ?? 900))) {
            return false;
        }

        return true;
    }

    /**
     * Record that an alert was fired
     */
    public function recordAlertFired(string $eventType, array $context = []): void
    {
        $key = "security_alert_last_fired_{$eventType}";
        $this->settings->getRepository()->set($key, (string) time());
        
        // Also log the alert firing as a security event
        \App\Services\ActivityLogService::getInstance()->logSecurity(
            'alert_fired',
            "Security alert triggered for {$eventType}",
            array_merge($context, ['event_type' => $eventType])
        );
    }

    /**
     * Get timestamp of last alert for event type
     */
    private function getLastAlertTime(string $eventType): ?int
    {
        $key = "security_alert_last_fired_{$eventType}";
        $value = $this->settings->get($key);
        return $value ? (int) $value : null;
    }

    // ─── ANOMALY DETECTION ─────────────────────────────────────────

    /**
     * Detect anomalies in recent security events
     */
    public function detectAnomalies(int $lookbackSeconds = 300): array
    {
        $alerts = [];
        $now = time();
        $cutoff = $now - $lookbackSeconds;

        // Get recent security events grouped by IP
        $query = "
            SELECT 
                al.*,
                JSON_EXTRACT(al.metadata, '$.ip') as ip,
                JSON_EXTRACT(al.metadata, '$.endpoint') as endpoint,
                JSON_EXTRACT(al.metadata, '$.method') as method
            FROM activity_log al
            WHERE al.action LIKE 'security.%'
              AND al.created_at >= FROM_UNIXTIME(:cutoff)
            ORDER BY al.created_at DESC
        ";

        $events = $this->db->query($query, ['cutoff' => $cutoff]) ?: [];

        if (empty($events)) {
            return $alerts;
        }

        // Group by IP
        $byIp = [];
        foreach ($events as $event) {
            $meta = json_decode($event['metadata'] ?? '{}', true);
            $ip = $meta['ip'] ?? 'unknown';
            if (!isset($byIp[$ip])) {
                $byIp[$ip] = [];
            }
            $byIp[$ip][] = $event;
        }

        $thresholds = $this->getConfiguredThresholds();

        // Check each IP for threshold violations
        foreach ($byIp as $ip => $ipEvents) {
            // Count by event type
            $counts = [];
            foreach ($ipEvents as $event) {
                $action = $event['action'];
                $eventType = str_replace('security.', '', $action);
                $counts[$eventType] = ($counts[$eventType] ?? 0) + 1;
            }

            foreach ($counts as $eventType => $count) {
                if (isset($thresholds[$eventType])) {
                    $threshold = $thresholds[$eventType];
                    if ($count >= $threshold['max_events']) {
                        // Check cooldown
                        $lastAlert = $this->getLastAlertTime("anomaly_{$eventType}_{$ip}");
                        if (!$lastAlert || ($now - $lastAlert >= $threshold['cooldown_seconds'])) {
                            $alerts[] = [
                                'type' => 'anomaly_detected',
                                'event_type' => $eventType,
                                'ip' => $ip,
                                'count' => $count,
                                'threshold' => $threshold['max_events'],
                                'window_seconds' => $lookbackSeconds,
                                'severity' => $threshold['severity'],
                                'sample_events' => array_slice($ipEvents, 0, 5),
                            ];
                            // Record cooldown for this IP+event combo
                            $this->settings->getRepository()->set(
                                "security_alert_last_fired_anomaly_{$eventType}_{$ip}",
                                (string) $now
                            );
                        }
                    }
                }
            }
        }

        return $alerts;
    }

    /**
     * Detect unusual request frequency from a single IP
     */
    public function detectRequestFrequencyAnomaly(string $ip, int $windowSeconds = 60, int $maxRequests = 100): ?array
    {
        $cutoff = time() - $windowSeconds;

        $query = "
            SELECT COUNT(*) as cnt
            FROM activity_log
            WHERE JSON_EXTRACT(metadata, '$.ip') = :ip
              AND created_at >= FROM_UNIXTIME(:cutoff)
        ";

        $result = $this->db->query($query, ['ip' => $ip, 'cutoff' => $cutoff]);
        $count = $result ? (int) $result[0]['cnt'] : 0;

        if ($count >= $maxRequests) {
            return [
                'type' => 'request_frequency_anomaly',
                'ip' => $ip,
                'count' => $count,
                'window_seconds' => $windowSeconds,
                'threshold' => $maxRequests,
                'severity' => 'medium',
            ];
        }

        return null;
    }

    // ─── NOTIFICATION DELIVERY ─────────────────────────────────────

    /**
     * Get configured notification recipients
     */
    public function getNotificationRecipients(): array
    {
        $email = $this->settings->get('security_alert_email');
        $webhook = $this->settings->get('security_alert_webhook');
        
        $recipients = [];
        if ($email) {
            $recipients['email'] = array_map('trim', explode(',', $email));
        }
        if ($webhook) {
            $recipients['webhook'] = array_map('trim', explode(',', $webhook));
        }
        
        return $recipients;
    }

    /**
     * Send security alert notifications
     */
    public function sendAlertNotifications(array $alert): void
    {
        $recipients = $this->getNotificationRecipients();
        
        if (!empty($recipients['email'])) {
            $this->sendEmailAlert($alert, $recipients['email']);
        }
        
        if (!empty($recipients['webhook'])) {
            $this->sendWebhookAlert($alert, $recipients['webhook']);
        }
    }

    /**
     * Send email alert
     */
    private function sendEmailAlert(array $alert, array $recipients): void
    {
        // Use existing mail infrastructure if available
        $subject = "[Security Alert] {$alert['type']} - {$alert['severity']}";
        
        $body = "Security Alert Detected\n\n";
        $body .= "Type: {$alert['type']}\n";
        $body .= "Severity: {$alert['severity']}\n";
        $body .= "Time: " . date('Y-m-d H:i:s') . " UTC\n";
        
        if (isset($alert['ip'])) {
            $body .= "Source IP: {$alert['ip']}\n";
        }
        if (isset($alert['event_type'])) {
            $body .= "Event Type: {$alert['event_type']}\n";
        }
        if (isset($alert['count'])) {
            $body .= "Event Count: {$alert['count']} (threshold: {$alert['threshold']})\n";
        }
        if (isset($alert['window_seconds'])) {
            $body .= "Window: {$alert['window_seconds']} seconds\n";
        }
        
        $body .= "\n---\nAudit withGufran Security Monitoring\n";
        
        foreach ($recipients as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                @mail($email, $subject, $body, "From: noreply@" . parse_url(config('app.url'), PHP_URL_HOST) . "\r\n");
            }
        }
    }

    /**
     * Send webhook alert
     */
    private function sendWebhookAlert(array $alert, array $webhooks): void
    {
        $payload = [
            'timestamp' => date('c'),
            'source' => 'audit-withgufran',
            'alert' => $alert,
        ];

        foreach ($webhooks as $url) {
            if (!$this->isValidWebhookUrl($url)) {
                continue;
            }

            $this->deliverWebhook($url, $payload);
        }
    }

    /**
     * Validate webhook URL against SSRF
     */
    private function isValidWebhookUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);
        if (!$parsed || !in_array($parsed['scheme'] ?? '', ['https'])) {
            return false; // Only HTTPS
        }

        $host = $parsed['host'] ?? '';
        
        // Block internal IPs and localhost
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

    /**
     * Deliver webhook with timeout and retry
     */
    private function deliverWebhook(string $url, array $payload): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Audit-withGufran-Security/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false, // Prevent redirects
            CURLOPT_MAXREDIRS => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log delivery attempt
        \App\Services\LoggingService::getInstance()->system('Security webhook delivery', [
            'url' => $url,
            'http_code' => $httpCode,
            'success' => $error === '' && $httpCode >= 200 && $httpCode < 300,
            'error' => $error,
        ]);

        // Simple retry on failure (could be queued for better reliability)
        if ($error !== '' || $httpCode < 200 || $httpCode >= 300) {
            sleep(1);
            $this->deliverWebhook($url, $payload); // One retry
        }
    }

    // ─── SIEM INTEGRATION ──────────────────────────────────────────

    /**
     * Forward security event to external SIEM
     */
    public function forwardToSiem(array $event): void
    {
        $siemUrl = $this->settings->get('security_siem_webhook');
        if (!$siemUrl || !$this->isValidWebhookUrl($siemUrl)) {
            return;
        }

        $payload = [
            'timestamp' => $event['created_at'] ?? date('c'),
            'source' => 'audit-withgufran',
            'event_type' => $event['action'] ?? 'unknown',
            'severity' => $this->inferSeverity($event['action'] ?? ''),
            'request_id' => $this->extractRequestId($event['metadata'] ?? ''),
            'user_id' => $event['user_id'] ?? null,
            'ip' => $this->extractIp($event['metadata'] ?? ''),
            'endpoint' => $this->extractEndpoint($event['metadata'] ?? ''),
            'method' => $this->extractMethod($event['metadata'] ?? ''),
            'description' => $event['description'] ?? '',
            'metadata' => $this->sanitizeForSiem($event['metadata'] ?? ''),
        ];

        $this->deliverWebhook($siemUrl, $payload);
    }

    private function inferSeverity(string $action): string
    {
        if (str_starts_with($action, 'security.auth_failed')) return 'high';
        if (str_starts_with($action, 'security.csrf_failed')) return 'high';
        if (str_starts_with($action, 'security.authz_denied')) return 'medium';
        if (str_starts_with($action, 'security.suspicious_activity')) return 'high';
        if (str_starts_with($action, 'security.alert_fired')) return 'high';
        return 'low';
    }

    private function extractRequestId($metadata): ?string
    {
        $meta = json_decode($metadata, true);
        return $meta['request_id'] ?? null;
    }

    private function extractIp($metadata): ?string
    {
        $meta = json_decode($metadata, true);
        return $meta['ip'] ?? null;
    }

    private function extractEndpoint($metadata): ?string
    {
        $meta = json_decode($metadata, true);
        return $meta['endpoint'] ?? null;
    }

    private function extractMethod($metadata): ?string
    {
        $meta = json_decode($metadata, true);
        return $meta['method'] ?? null;
    }

    private function sanitizeForSiem($metadata): array
    {
        $meta = json_decode($metadata, true);
        if (!is_array($meta)) return [];

        $sensitiveKeys = ['password', 'token', 'csrf', 'secret', 'key', 'auth', 'session', 'cookie', 'authorization'];
        foreach ($sensitiveKeys as $key) {
            if (isset($meta[$key])) {
                $meta[$key] = '[REDACTED]';
            }
        }
        return $meta;
    }
}