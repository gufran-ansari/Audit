<?php
/**
 * Audit Engine Configuration
 * 
 * Central configuration for the audit orchestration layer.
 * Defines enabled modules, execution order, fetch settings, and
 * performance tuning. The ModuleRegistry is the source of truth for
 * module definitions; this file controls runtime behavior and can be
 * extended for future modules without code changes.
 */

return [
    /*
     * Default modules selected when none specified.
     * Uses module short keys (see ModuleRegistry).
     */
    'default_modules' => [
        'h1',
        'meta',
        'noindex',
        'faq',
        'lorem',
        'staging',
    ],

    /*
     * Execution order override (lower runs first).
     * If a module is omitted here, the order from ModuleRegistry is used.
     */
    'execution_order' => [
        'h1' => 10,
        'meta' => 20,
        'noindex' => 30,
        'faq' => 40,
        'lorem' => 50,
        'staging' => 60,
        'word' => 70,
    ],

    /*
     * Per-module enable/disable flags.
     * Allows disabling modules globally without removing them.
     */
    'modules' => [
        'h1' => ['enabled' => true],
        'meta' => ['enabled' => true],
        'noindex' => ['enabled' => true],
        'faq' => ['enabled' => true],
        'lorem' => ['enabled' => true],
        'staging' => ['enabled' => true],
        'word' => ['enabled' => true, 'requires_input' => true],
    ],

    /*
     * Page fetching configuration (shared across modules).
     */
    'fetch' => [
        'timeout' => 30,            // seconds
        'max_redirects' => 5,
        'user_agent' => 'AuditWithGufran/1.0 (SEO Audit Bot)',
        'verify_ssl' => false,
        'cache_enabled' => true,    // reuse a page within one execution
        'max_cache_entries' => 50,  // in-memory cache cap (shared hosting friendly)
    ],

    /*
     * Execution behavior and performance tuning.
     */
    'execution' => [
        'progress_update_interval' => 10,  // update run progress every N pages
        'max_pages_per_run' => 100000,     // safety cap
        'store_history' => true,           // record changes in page_audit_history
    ],

    /*
     * Background job processing.
     */
    'background' => [
        'enabled' => true,
        'batch_size' => 10,             // pages per batch (shared-hosting-friendly default)
        'max_execution_seconds' => 55,  // yield before PHP timeout
        'heartbeat_timeout' => 120,     // seconds before marking as interrupted
        'max_retries' => 3,             // per-page retry limit
        'resumable' => true,            // auto-resume from last_page_id
    ],

    /*
     * Database write optimization.
     */
    'writes' => [
        'skip_unchanged' => true,       // don't UPDATE if result hasn't changed
        'batch_insert' => false,        // reserved for future multi-row inserts
    ],

    /*
     * Processing safeguards.
     */
    'safeguards' => [
        'max_consecutive_errors' => 50,     // abort job after N consecutive page errors
        'stale_job_timeout' => 3600,        // seconds: mark very old 'processing' jobs as failed
        'duplicate_prevention' => true,     // prevent multiple running jobs per project
        'orphan_detection_interval' => 300, // seconds between orphan checks
    ],

    /*
     * Performance metrics collection.
     */
    'metrics' => [
        'enabled' => true,
        'track_execution_time' => true,
        'track_module_time' => true,
        'track_fetch_time' => true,
    ],

    /*
     * System settings (future settings UI will read from database;
     * these serve as defaults).
     */
    'settings_defaults' => [
        'batch_size' => 10,
        'retry_limit' => 3,
        'fetch_timeout' => 30,
        'max_redirects' => 5,
    ],
];
