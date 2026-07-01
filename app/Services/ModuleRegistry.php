<?php

namespace App\Services;

use App\Modules\H1Audit\H1AuditProcessor;
use App\Modules\H1Audit\H1AuditRepository;
use App\Modules\MetaAudit\MetaAuditProcessor;
use App\Modules\MetaAudit\MetaAuditRepository;
use App\Modules\NoindexAudit\NoindexAuditProcessor;
use App\Modules\NoindexAudit\NoindexAuditRepository;
use App\Modules\FaqSchemaAudit\FaqSchemaAuditProcessor;
use App\Modules\FaqSchemaAudit\FaqSchemaAuditRepository;
use App\Modules\LoremAudit\LoremAuditProcessor;
use App\Modules\LoremAudit\LoremAuditRepository;
use App\Modules\StagingAudit\StagingAuditProcessor;
use App\Modules\StagingAudit\StagingAuditRepository;
use App\Modules\WordOccurrenceAudit\WordOccurrenceProcessor;
use App\Modules\WordOccurrenceAudit\WordOccurrenceRepository;

/**
 * Module Registry
 * 
 * Central registry of all audit modules. Provides a single place to
 * register, configure, and resolve audit modules. New modules should
 * be added here once and will automatically be available to the
 * orchestration layer.
 */
class ModuleRegistry
{
    /**
     * Registry definition for all modules.
     * 
     * Each entry contains:
     *  - key:          internal short key (used by UI/frontend)
     *  - module_name:  database module_name (stored in page_audit_results)
     *  - label:        human-readable label
     *  - icon:         bootstrap icon class
     *  - order:        execution order (lower runs first)
     *  - enabled:      whether the module is active
     *  - requires_input: whether the module needs extra input (e.g. keyword)
     *  - processor:    processor class
     *  - repository:   repository class
     *
     * @var array<string, array>
     */
    private static array $modules = [
        'h1' => [
            'key' => 'h1',
            'module_name' => 'h1_audit',
            'label' => 'H1 Audit',
            'icon' => 'bi-tag',
            'order' => 10,
            'enabled' => true,
            'requires_input' => false,
            'processor' => H1AuditProcessor::class,
            'repository' => H1AuditRepository::class,
        ],
        'meta' => [
            'key' => 'meta',
            'module_name' => 'meta_audit',
            'label' => 'Meta Audit',
            'icon' => 'bi-card-text',
            'order' => 20,
            'enabled' => true,
            'requires_input' => false,
            'processor' => MetaAuditProcessor::class,
            'repository' => MetaAuditRepository::class,
        ],
        'noindex' => [
            'key' => 'noindex',
            'module_name' => 'noindex_audit',
            'label' => 'Noindex Audit',
            'icon' => 'bi-eye-slash',
            'order' => 30,
            'enabled' => true,
            'requires_input' => false,
            'processor' => NoindexAuditProcessor::class,
            'repository' => NoindexAuditRepository::class,
        ],
        'faq' => [
            'key' => 'faq',
            'module_name' => 'faq_schema_audit',
            'label' => 'FAQ Schema Audit',
            'icon' => 'bi-question-circle',
            'order' => 40,
            'enabled' => true,
            'requires_input' => false,
            'processor' => FaqSchemaAuditProcessor::class,
            'repository' => FaqSchemaAuditRepository::class,
        ],
        'lorem' => [
            'key' => 'lorem',
            'module_name' => 'lorem_audit',
            'label' => 'Lorem Ipsum Audit',
            'icon' => 'bi-file-text',
            'order' => 50,
            'enabled' => true,
            'requires_input' => false,
            'processor' => LoremAuditProcessor::class,
            'repository' => LoremAuditRepository::class,
        ],
        'staging' => [
            'key' => 'staging',
            'module_name' => 'staging_audit',
            'label' => 'Staging URL Audit',
            'icon' => 'bi-hdd-network',
            'order' => 60,
            'enabled' => true,
            'requires_input' => false,
            'processor' => StagingAuditProcessor::class,
            'repository' => StagingAuditRepository::class,
        ],
        'word' => [
            'key' => 'word',
            'module_name' => 'word_occurrence_audit',
            'label' => 'Word Occurrence Audit',
            'icon' => 'bi-search',
            'order' => 70,
            'enabled' => true,
            'requires_input' => true,
            'processor' => WordOccurrenceProcessor::class,
            'repository' => WordOccurrenceRepository::class,
        ],
    ];

    /**
     * Get all registered modules, sorted by execution order.
     */
    public static function all(): array
    {
        $modules = self::$modules;
        uasort($modules, fn($a, $b) => $a['order'] <=> $b['order']);
        return $modules;
    }

    /**
     * Get only enabled modules, sorted by execution order.
     */
    public static function enabled(): array
    {
        return array_filter(self::all(), fn($m) => $m['enabled'] === true);
    }

    /**
     * Get a module definition by its short key.
     */
    public static function getByKey(string $key): ?array
    {
        return self::$modules[$key] ?? null;
    }

    /**
     * Get a module definition by its database module_name.
     */
    public static function getByModuleName(string $moduleName): ?array
    {
        foreach (self::$modules as $module) {
            if ($module['module_name'] === $moduleName) {
                return $module;
            }
        }
        return null;
    }

    /**
     * Resolve a list of selected keys to ordered module definitions.
     * Invalid or disabled keys are skipped.
     *
     * @param array $keys List of module keys (e.g. ['h1', 'meta'])
     * @return array Ordered module definitions
     */
    public static function resolve(array $keys): array
    {
        $resolved = [];

        foreach ($keys as $key) {
            $module = self::getByKey($key);
            if ($module && $module['enabled']) {
                $resolved[$key] = $module;
            }
        }

        // Sort by execution order
        uasort($resolved, fn($a, $b) => $a['order'] <=> $b['order']);

        return $resolved;
    }

    /**
     * Get all valid module keys.
     */
    public static function keys(): array
    {
        return array_keys(self::$modules);
    }

    /**
     * Get all database module names.
     */
    public static function moduleNames(): array
    {
        return array_map(fn($m) => $m['module_name'], self::$modules);
    }

    /**
     * Check if a module key exists.
     */
    public static function exists(string $key): bool
    {
        return isset(self::$modules[$key]);
    }

    /**
     * Instantiate a processor for a module key.
     */
    public static function makeProcessor(string $key): ?object
    {
        $module = self::getByKey($key);
        if (!$module) {
            return null;
        }
        $class = $module['processor'];
        return new $class();
    }

    /**
     * Instantiate a repository for a module key.
     */
    public static function makeRepository(string $key): ?object
    {
        $module = self::getByKey($key);
        if (!$module) {
            return null;
        }
        $class = $module['repository'];
        return new $class();
    }
}
