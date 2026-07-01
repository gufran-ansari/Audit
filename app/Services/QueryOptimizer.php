<?php

namespace App\Services;

use Database\Database;

/**
 * Query Optimizer
 * 
 * Provides optimized query patterns for common operations:
 * - Cursor-based pagination (faster than OFFSET for large tables)
 * - Cached counts
 * - Batch operations
 * - Deferred loading
 */
class QueryOptimizer
{
    private static $db;

    private static function db(): Database
    {
        if (!self::$db) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }

    /**
     * Cursor-based pagination (faster than OFFSET for large datasets).
     * 
     * @param string $table Table name
     * @param array $conditions WHERE conditions as [column => value]
     * @param int $lastId Last seen ID (cursor)
     * @param int $limit Page size
     * @param string $direction ASC or DESC
     * @return array
     */
    public static function cursorPaginate(string $table, array $conditions, int $lastId, int $limit = 25, string $direction = 'ASC'): array
    {
        $params = [];
        $where = '1=1';

        foreach ($conditions as $col => $val) {
            $paramKey = str_replace('.', '_', $col);
            $where .= " AND {$col} = :{$paramKey}";
            $params[$paramKey] = $val;
        }

        $operator = $direction === 'ASC' ? '>' : '<';
        if ($lastId > 0) {
            $where .= " AND id {$operator} :cursor_id";
            $params['cursor_id'] = $lastId;
        }

        $query = "SELECT * FROM {$table} WHERE {$where} ORDER BY id {$direction} LIMIT :lim";
        $params['lim'] = $limit;

        return self::db()->query($query, $params) ?: [];
    }

    /**
     * Get count with caching.
     * Uses CacheService to avoid repeated COUNT(*) on large tables.
     */
    public static function cachedCount(string $cacheKey, string $query, array $params = [], int $ttl = 60): int
    {
        $cache = CacheService::getInstance();

        return $cache->remember($cacheKey, $ttl, function () use ($query, $params) {
            $result = self::db()->query($query, $params);
            return $result ? (int) $result[0]['cnt'] : 0;
        });
    }

    /**
     * Batch insert with chunking (avoids huge single queries).
     * 
     * @param string $table Table name
     * @param array $columns Column names
     * @param array $rows Array of value arrays
     * @param int $chunkSize Rows per INSERT
     * @return int Total rows inserted
     */
    public static function batchInsert(string $table, array $columns, array $rows, int $chunkSize = 50): int
    {
        if (empty($rows)) return 0;

        $total = 0;
        $chunks = array_chunk($rows, $chunkSize);
        $colList = implode(', ', $columns);
        $placeholderRow = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        foreach ($chunks as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), $placeholderRow));
            $sql = "INSERT INTO {$table} ({$colList}) VALUES {$placeholders}";

            $params = [];
            foreach ($chunk as $row) {
                foreach ($row as $val) {
                    $params[] = $val;
                }
            }

            self::db()->insert($sql, $params);
            $total += count($chunk);
        }

        return $total;
    }

    /**
     * Batch update with CASE statements (single query for multiple rows).
     */
    public static function batchUpdate(string $table, string $idColumn, string $updateColumn, array $updates): int
    {
        if (empty($updates)) return 0;

        $ids = array_keys($updates);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $cases = '';
        $params = [];
        foreach ($updates as $id => $value) {
            $cases .= " WHEN ? THEN ?";
            $params[] = $id;
            $params[] = $value;
        }

        $sql = "UPDATE {$table} SET {$updateColumn} = CASE {$idColumn}{$cases} END WHERE {$idColumn} IN ({$placeholders})";
        $params = array_merge($params, $ids);

        return self::db()->execute($sql, $params);
    }

    /**
     * Efficient EXISTS check (faster than COUNT for checking existence).
     */
    public static function exists(string $table, array $conditions): bool
    {
        $params = [];
        $where = '1=1';

        foreach ($conditions as $col => $val) {
            $paramKey = str_replace('.', '_', $col);
            $where .= " AND {$col} = :{$paramKey}";
            $params[$paramKey] = $val;
        }

        $result = self::db()->query(
            "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1",
            $params
        );

        return !empty($result);
    }

    /**
     * Invalidate cached counts for a project.
     */
    public static function invalidateProjectCache(int $projectId): void
    {
        $cache = CacheService::getInstance();
        $cache->forget("project_stats_{$projectId}");
        $cache->forget("project_page_count_{$projectId}");
        $cache->forget("project_audit_count_{$projectId}");
        $cache->forget("dashboard_stats");
    }
}
