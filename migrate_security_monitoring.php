<?php
/**
 * Migration: Add security monitoring settings
 * 
 * Run this script to add security monitoring configuration to the database.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Modules\Settings\SettingsRepository;

$repo = new SettingsRepository();

// Security monitoring alert thresholds
$repo->create('security_alert_auth_failed_max_events', 'security', '10', 'number', 'Max failed auth events before alert (per window)');
$repo->create('security_alert_auth_failed_window_seconds', 'security', '300', 'number', 'Time window in seconds for auth failed threshold');
$repo->create('security_alert_auth_failed_cooldown_seconds', 'security', '900', 'number', 'Cooldown in seconds before another auth failed alert');

$repo->create('security_alert_csrf_failed_max_events', 'security', '5', 'number', 'Max CSRF failures before alert (per window)');
$repo->create('security_alert_csrf_failed_window_seconds', 'security', '300', 'number', 'Time window in seconds for CSRF failed threshold');
$repo->create('security_alert_csrf_failed_cooldown_seconds', 'security', '900', 'number', 'Cooldown in seconds before another CSRF failed alert');

$repo->create('security_alert_authz_denied_max_events', 'security', '20', 'number', 'Max authorization denied events before alert (per window)');
$repo->create('security_alert_authz_denied_window_seconds', 'security', '300', 'number', 'Time window in seconds for authz denied threshold');
$repo->create('security_alert_authz_denied_cooldown_seconds', 'security', '900', 'number', 'Cooldown in seconds before another authz denied alert');

$repo->create('security_alert_suspicious_activity_max_events', 'security', '5', 'number', 'Max suspicious activity events before alert (per window)');
$repo->create('security_alert_suspicious_activity_window_seconds', 'security', '300', 'number', 'Time window in seconds for suspicious activity threshold');
$repo->create('security_alert_suspicious_activity_cooldown_seconds', 'security', '1800', 'number', 'Cooldown in seconds before another suspicious activity alert');

// Notification settings
$repo->create('security_alert_email', 'security', '', 'text', 'Comma-separated email addresses for security alerts');
$repo->create('security_alert_webhook', 'security', '', 'text', 'Comma-separated webhook URLs for security alerts');

// SIEM integration
$repo->create('security_siem_webhook', 'security', '', 'text', 'External SIEM webhook URL (HTTPS only, no internal IPs)');

// Anomaly detection
$repo->create('security_anomaly_request_frequency_window', 'security', '60', 'number', 'Time window in seconds for request frequency anomaly detection');
$repo->create('security_anomaly_request_frequency_max', 'security', '100', 'number', 'Max requests per IP per window before anomaly alert');

echo "Security monitoring settings added successfully.\n";