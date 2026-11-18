<?php

namespace App\Services;

use Database\Database;

/**
 * Activity Log Service
 * 
 * Centralized logging for user-initiated state changes and security events.
 * Provides request correlation IDs and structured metadata.
 * Writes to the `activity_log` database table.
 */
class ActivityLogService
{
    private static ?self $instance = null;
    private $db;

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get current request ID from bootstrap context
     */
    private function getRequestId(): ?string
    {
        return $_SERVER['REQUEST_ID'] ?? $_SESSION['request_id'] ?? null;
    }

    /**
     * Get current user ID from session
     */
    private function getCurrentUserId(): ?int
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return (int) $_SESSION['user_id'];
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        return \App\Services\SecurityService::getClientIp();
    }

    /**
     * Sanitize metadata to prevent log injection and sensitive data exposure
     */
    private function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $sensitiveKeys = [
            'password', 'token', 'csrf', 'secret', 'key', 'auth',
            'session', 'cookie', 'authorization', 'x-csrf', 'x-request-id'
        ];

        $sanitized = [];
        foreach ($metadata as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_string($value) && strlen($value) > 1000) {
                $sanitized[$key] = substr($value, 0, 1000) . '...[TRUNCATED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Log a user activity event (state-changing operation)
     * 
     * @param string $action Action type (e.g., 'project_created', 'sitemap_imported', 'override_set')
     * @param ?string $description Human-readable description
     * @param ?int $projectId Associated project ID
     * @param ?array $metadata Additional context (will be sanitized)
     * @param ?string $resourceType Type of resource affected (e.g., 'project', 'page', 'sitemap', 'job', 'override', 'report')
     * @param ?int $resourceId ID of the affected resource
     * @param string $level Log level: 'info', 'warning', 'error'
     */
    public function log(
        string $action,
        ?string $description = null,
        ?int $projectId = null,
        ?array $metadata = null,
        ?string $resourceType = null,
        ?int $resourceId = null,
        string $level = 'info'
    ): void {
        $userId = $this->getCurrentUserId();
        $requestId = $this->getRequestId();
        $ip = $this->getClientIp();

        $meta = $this->sanitizeMetadata($metadata);
        if ($requestId) {
            $meta['request_id'] = $requestId;
        }
        $meta['ip'] = $ip;
        $meta['level'] = $level;
        $meta['resource_type'] = $resourceType;
        $meta['resource_id'] = $resourceId;

        $query = "INSERT INTO activity_log (user_id, project_id, action, description, metadata)
                  VALUES (:user_id, :project_id, :action, :description, :metadata)";

        $this->db->execute($query, [
            'user_id' => $userId,
            'project_id' => $projectId,
            'action' => $action,
            'description' => $description,
            'metadata' => $meta ? json_encode($meta) : null,
        ]);
    }

    /**
     * Log a security event (failed auth, authz violation, CSRF failure, etc.)
     * 
     * @param string $eventType Event type (e.g., 'auth_failed', 'authz_denied', 'csrf_failed', 'suspicious_activity')
     * @param ?string $description Human-readable description
     * @param ?array $context Additional context (will be sanitized)
     * @param ?string $endpoint The endpoint that was accessed
     * @param ?string $method HTTP method
     */
    public function logSecurity(
        string $eventType,
        ?string $description = null,
        ?array $context = null,
        ?string $endpoint = null,
        ?string $method = null
    ): void {
        $userId = $this->getCurrentUserId();
        $requestId = $this->getRequestId();
        $ip = $this->getClientIp();

        $meta = $this->sanitizeMetadata($context);
        if ($requestId) {
            $meta['request_id'] = $requestId;
        }
        $meta['ip'] = $ip;
        $meta['endpoint'] = $endpoint ?? ($_SERVER['REQUEST_URI'] ?? 'unknown');
        $meta['method'] = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'unknown');
        $meta['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // Use a distinct action prefix for security events
        $action = 'security.' . $eventType;

        $query = "INSERT INTO activity_log (user_id, project_id, action, description, metadata)
                  VALUES (:user_id, NULL, :action, :description, :metadata)";

        $this->db->execute($query, [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'metadata' => $meta ? json_encode($meta) : null,
        ]);
    }

    /**
     * Get recent activity entries for a user's accessible projects
     * (For dashboard display - scoped by authorization)
     */
    public function getRecentActivity(int $limit = 20, ?array $authorizedProjectIds = null): array
    {
        if ($authorizedProjectIds === null) {
            $authService = new \App\Services\AuthorizationService();
            $authorizedProjectIds = $authService->getAccessibleProjectIds();
        }

        if (empty($authorizedProjectIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($authorizedProjectIds), '?'));
        $params = array_merge($authorizedProjectIds, ['lim' => $limit]);

        return $this->db->query(
            "SELECT al.*, u.name as user_name, p.project_name
             FROM activity_log al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN projects p ON p.id = al.project_id
             WHERE al.project_id IN ($placeholders)
               AND al.action NOT LIKE 'security.%'
             ORDER BY al.created_at DESC
             LIMIT :lim",
            $params
        ) ?: [];
    }

    /**
     * Get security events (admin only)
     */
    public function getSecurityEvents(int $limit = 50): array
    {
        return $this->db->query(
            "SELECT al.*, u.name as user_name
             FROM activity_log al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.action LIKE 'security.%'
             ORDER BY al.created_at DESC
             LIMIT :lim",
            ['lim' => $limit]
        ) ?: [];
    }

    /**
     * Get security events with filters and pagination (super admin only)
     */
    public function getSecurityEventsFiltered(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where = ["al.action LIKE 'security.%'"];
        $params = [];

        // Date range filter
        if (!empty($filters['date_from'])) {
            $where[] = "al.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "al.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        // Event type filter
        if (!empty($filters['event_type'])) {
            $where[] = "al.action = :event_type";
            $params['event_type'] = 'security.' . $filters['event_type'];
        }

        // Severity filter (inferred from action)
        if (!empty($filters['severity'])) {
            $severityMap = [
                'high' => ['security.auth_failed', 'security.csrf_failed', 'security.suspicious_activity', 'security.alert_fired'],
                'medium' => ['security.authz_denied'],
                'low' => [],
            ];
            $actions = $severityMap[$filters['severity']] ?? [];
            if (!empty($actions)) {
                $placeholders = implode(',', array_fill(0, count($actions), '?'));
                $where[] = "al.action IN ($placeholders)";
                $params = array_merge($params, $actions);
            }
        }

        // IP filter
        if (!empty($filters['ip'])) {
            $where[] = "JSON_EXTRACT(al.metadata, '$.ip') = :ip";
            $params['ip'] = $filters['ip'];
        }

        // User filter
        if (!empty($filters['user_id'])) {
            $where[] = "al.user_id = :user_id";
            $params['user_id'] = (int) $filters['user_id'];
        }

        // Request ID filter
        if (!empty($filters['request_id'])) {
            $where[] = "JSON_EXTRACT(al.metadata, '$.request_id') = :request_id";
            $params['request_id'] = $filters['request_id'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM activity_log al WHERE $whereClause";
        $countResult = $this->db->query($countQuery, $params);
        $total = $countResult ? (int) $countResult[0]['total'] : 0;

        // Get paginated results
        $query = "
            SELECT al.*, u.name as user_name,
                   JSON_EXTRACT(al.metadata, '$.ip') as ip,
                   JSON_EXTRACT(al.metadata, '$.endpoint') as endpoint,
                   JSON_EXTRACT(al.metadata, '$.method') as method,
                   JSON_EXTRACT(al.metadata, '$.request_id') as request_id,
                   JSON_EXTRACT(al.metadata, '$.user_agent') as user_agent
            FROM activity_log al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE $whereClause
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $params['limit'] = $perPage;
        $params['offset'] = $offset;

        $events = $this->db->query($query, $params) ?: [];

        // Parse metadata JSON for each event
        foreach ($events as &$event) {
            $meta = json_decode($event['metadata'] ?? '{}', true);
            $event['metadata_parsed'] = $meta;
        }

        return [
            'events' => $events,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Get security event statistics
     */
    public function getSecurityEventStats(int $hours = 24): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        // Total events
        $totalResult = $this->db->query(
            "SELECT COUNT(*) as cnt FROM activity_log 
             WHERE action LIKE 'security.%' AND created_at >= :cutoff",
            ['cutoff' => $cutoff]
        );
        $total = $totalResult ? (int) $totalResult[0]['cnt'] : 0;

        // By event type
        $byType = $this->db->query(
            "SELECT action, COUNT(*) as cnt FROM activity_log 
             WHERE action LIKE 'security.%' AND created_at >= :cutoff
             GROUP BY action ORDER BY cnt DESC",
            ['cutoff' => $cutoff]
        ) ?: [];

        // By severity (inferred)
        $severityMap = [
            'security.auth_failed' => 'high',
            'security.csrf_failed' => 'high',
            'security.suspicious_activity' => 'high',
            'security.alert_fired' => 'high',
            'security.authz_denied' => 'medium',
        ];
        $bySeverity = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($byType as $row) {
            $severity = $severityMap[$row['action']] ?? 'low';
            $bySeverity[$severity] += (int) $row['cnt'];
        }

        // Top IPs
        $topIps = $this->db->query(
            "SELECT JSON_EXTRACT(metadata, '$.ip') as ip, COUNT(*) as cnt
             FROM activity_log 
             WHERE action LIKE 'security.%' AND created_at >= :cutoff
             GROUP BY ip ORDER BY cnt DESC LIMIT 10",
            ['cutoff' => $cutoff]
        ) ?: [];

        // Timeline (hourly buckets)
        $timeline = $this->db->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hour, COUNT(*) as cnt
             FROM activity_log 
             WHERE action LIKE 'security.%' AND created_at >= :cutoff
             GROUP BY hour ORDER BY hour",
            ['cutoff' => $cutoff]
        ) ?: [];

        return [
            'total' => $total,
            'by_type' => $byType,
            'by_severity' => $bySeverity,
            'top_ips' => $topIps,
            'timeline' => $timeline,
            'period_hours' => $hours,
        ];
    }

    /**
     * Clean up old activity log entries
     * 
     * @param int $daysToKeep Retention period in days
     * @param int $batchSize Batch size for deletion to avoid long-running operations
     * @return int Number of deleted rows
     */
    public function cleanupOldLogs(int $daysToKeep = 90, int $batchSize = 1000): int
    {
        $totalDeleted = 0;
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        while (true) {
            $query = "DELETE FROM activity_log
                      WHERE created_at < :cutoff
                      LIMIT :batch";

            $deleted = $this->db->execute($query, [
                'cutoff' => $cutoff,
                'batch' => $batchSize,
            ]);

            // MySQL PDO doesn't return affected rows reliably for DELETE with LIMIT
            // Use a count query as proxy
            $countQuery = "SELECT COUNT(*) as cnt FROM activity_log WHERE created_at < :cutoff";
            $result = $this->db->query($countQuery, ['cutoff' => $cutoff]);
            $remaining = $result ? (int) $result[0]['cnt'] : 0;

            if ($remaining === 0) {
                break;
            }

            $totalDeleted += $batchSize;

            // Safety limit to prevent infinite loops
            if ($totalDeleted > 100000) {
                break;
            }
        }

        return $totalDeleted;
    }
}