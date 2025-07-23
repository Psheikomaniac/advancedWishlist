<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Performance;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Database performance monitoring service.
 * 
 * Monitors query execution times, identifies slow queries, tracks index usage,
 * and provides performance recommendations.
 */
class DatabasePerformanceMonitor
{
    private Connection $connection;
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;
    private array $slowQueries = [];
    private array $queryStats = [];
    private float $slowQueryThreshold = 0.1; // 100ms
    private int $maxSlowQueries = 100; // Keep only the latest 100 slow queries

    public function __construct(
        Connection $connection,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        float $slowQueryThreshold = 0.1
    ) {
        $this->connection = $connection;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->slowQueryThreshold = $slowQueryThreshold;
    }

    /**
     * Monitor a query execution and log performance metrics.
     */
    public function monitorQuery(string $sql, array $params, float $executionTime, ?array $explainData = null): void
    {
        $queryHash = $this->generateQueryHash($sql);
        
        // Update query statistics
        if (!isset($this->queryStats[$queryHash])) {
            $this->queryStats[$queryHash] = [
                'sql' => $this->normalizeQuery($sql),
                'count' => 0,
                'total_time' => 0.0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0.0,
                'avg_time' => 0.0,
                'first_seen' => time(),
                'last_seen' => time()
            ];
        }

        $stats = &$this->queryStats[$queryHash];
        $stats['count']++;
        $stats['total_time'] += $executionTime;
        $stats['min_time'] = min($stats['min_time'], $executionTime);
        $stats['max_time'] = max($stats['max_time'], $executionTime);
        $stats['avg_time'] = $stats['total_time'] / $stats['count'];
        $stats['last_seen'] = time();

        // Track slow queries
        if ($executionTime > $this->slowQueryThreshold) {
            $this->addSlowQuery($sql, $params, $executionTime, $explainData);
        }

        // Log extremely slow queries immediately
        if ($executionTime > 1.0) { // 1 second
            $this->logger->critical('Extremely slow query detected', [
                'sql' => substr($sql, 0, 500),
                'execution_time' => $executionTime,
                'params_count' => count($params),
                'explain' => $explainData
            ]);
        }
    }

    /**
     * Add a slow query to the monitoring list.
     */
    private function addSlowQuery(string $sql, array $params, float $executionTime, ?array $explainData): void
    {
        $slowQuery = [
            'sql' => $sql,
            'normalized_sql' => $this->normalizeQuery($sql),
            'params' => $this->sanitizeParams($params),
            'execution_time' => $executionTime,
            'timestamp' => time(),
            'explain' => $explainData,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
            'recommendations' => $this->generateQueryRecommendations($sql, $explainData)
        ];

        $this->slowQueries[] = $slowQuery;

        // Keep only the latest slow queries
        if (count($this->slowQueries) > $this->maxSlowQueries) {
            array_shift($this->slowQueries);
        }

        $this->logger->warning('Slow query detected', [
            'sql' => substr($sql, 0, 200),
            'execution_time' => $executionTime,
            'threshold' => $this->slowQueryThreshold,
            'recommendations' => $slowQuery['recommendations']
        ]);
    }

    /**
     * Generate a comprehensive performance report.
     */
    public function getPerformanceReport(): array
    {
        return [
            'summary' => $this->getPerformanceSummary(),
            'slow_queries' => $this->getSlowQueriesSummary(),
            'query_patterns' => $this->getQueryPatterns(),
            'database_stats' => $this->getDatabaseStats(),
            'index_usage' => $this->getIndexUsageStats(),
            'table_stats' => $this->getTableStats(),
            'recommendations' => $this->generateGlobalRecommendations(),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get performance summary statistics.
     */
    private function getPerformanceSummary(): array
    {
        $totalQueries = array_sum(array_column($this->queryStats, 'count'));
        $totalTime = array_sum(array_column($this->queryStats, 'total_time'));
        $avgTime = $totalQueries > 0 ? $totalTime / $totalQueries : 0;

        return [
            'total_queries' => $totalQueries,
            'unique_queries' => count($this->queryStats),
            'total_execution_time' => round($totalTime, 4),
            'average_execution_time' => round($avgTime, 4),
            'slow_queries_count' => count($this->slowQueries),
            'slow_query_threshold' => $this->slowQueryThreshold,
            'slowest_query_time' => count($this->slowQueries) > 0 ? max(array_column($this->slowQueries, 'execution_time')) : 0
        ];
    }

    /**
     * Get slow queries summary.
     */
    private function getSlowQueriesSummary(): array
    {
        $summary = [];
        
        foreach ($this->slowQueries as $query) {
            $normalized = $query['normalized_sql'];
            if (!isset($summary[$normalized])) {
                $summary[$normalized] = [
                    'query' => $normalized,
                    'count' => 0,
                    'total_time' => 0.0,
                    'max_time' => 0.0,
                    'recommendations' => []
                ];
            }
            
            $summary[$normalized]['count']++;
            $summary[$normalized]['total_time'] += $query['execution_time'];
            $summary[$normalized]['max_time'] = max($summary[$normalized]['max_time'], $query['execution_time']);
            $summary[$normalized]['recommendations'] = array_merge(
                $summary[$normalized]['recommendations'],
                $query['recommendations']
            );
        }

        // Sort by total time descending
        uasort($summary, function($a, $b) {
            return $b['total_time'] <=> $a['total_time'];
        });

        return array_values($summary);
    }

    /**
     * Analyze query patterns.
     */
    private function getQueryPatterns(): array
    {
        $patterns = [
            'select_without_where' => 0,
            'select_with_like_leading_wildcard' => 0,
            'select_star' => 0,
            'missing_limit' => 0,
            'complex_joins' => 0,
            'subqueries' => 0
        ];

        foreach ($this->queryStats as $stat) {
            $sql = strtolower($stat['sql']);
            
            if (str_starts_with($sql, 'select')) {
                if (!str_contains($sql, ' where ')) {
                    $patterns['select_without_where']++;
                }
                
                if (str_contains($sql, ' like \'%')) {
                    $patterns['select_with_like_leading_wildcard']++;
                }
                
                if (str_contains($sql, 'select *')) {
                    $patterns['select_star']++;
                }
                
                if (str_contains($sql, ' order by ') && !str_contains($sql, ' limit ')) {
                    $patterns['missing_limit']++;
                }
                
                if (substr_count($sql, ' join ') > 2) {
                    $patterns['complex_joins']++;
                }
                
                if (str_contains($sql, '(select')) {
                    $patterns['subqueries']++;
                }
            }
        }

        return $patterns;
    }

    /**
     * Get database statistics.
     */
    private function getDatabaseStats(): array 
    {
        try {
            $stats = $this->connection->fetchAllAssociative("
                SELECT 
                    TABLE_NAME,
                    TABLE_ROWS,
                    DATA_LENGTH,
                    INDEX_LENGTH,
                    (DATA_LENGTH + INDEX_LENGTH) as TOTAL_SIZE,
                    DATA_FREE,
                    TABLE_COLLATION
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME LIKE '%wishlist%'
                ORDER BY TOTAL_SIZE DESC
            ");
            
            return $stats ?: [];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get database stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get index usage statistics.
     */
    private function getIndexUsageStats(): array
    {
        try {
            $indexStats = $this->connection->fetchAllAssociative("
                SELECT 
                    s.TABLE_NAME,
                    s.INDEX_NAME,
                    s.SEQ_IN_INDEX,
                    s.COLUMN_NAME,
                    s.CARDINALITY,
                    s.SUB_PART,
                    s.NULLABLE,
                    CASE WHEN s.INDEX_NAME = 'PRIMARY' THEN 'PRIMARY'
                         WHEN s.NON_UNIQUE = 0 THEN 'UNIQUE'
                         ELSE 'INDEX'
                    END as INDEX_TYPE
                FROM information_schema.STATISTICS s
                WHERE s.TABLE_SCHEMA = DATABASE()
                    AND s.TABLE_NAME LIKE '%wishlist%'
                ORDER BY s.TABLE_NAME, s.INDEX_NAME, s.SEQ_IN_INDEX
            ");
            
            return $indexStats ?: [];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get index stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get table statistics.
     */
    private function getTableStats(): array
    {
        try {
            // Get table status
            $tableStats = $this->connection->fetchAllAssociative("
                SHOW TABLE STATUS LIKE '%wishlist%'
            ");

            $result = [];
            foreach ($tableStats as $table) {
                $result[] = [
                    'name' => $table['Name'],
                    'engine' => $table['Engine'],
                    'rows' => $table['Rows'],
                    'avg_row_length' => $table['Avg_row_length'],
                    'data_length' => $table['Data_length'],
                    'index_length' => $table['Index_length'],
                    'data_free' => $table['Data_free'],
                    'auto_increment' => $table['Auto_increment'],
                    'collation' => $table['Collation']
                ];
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get table stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Generate global performance recommendations.
     */
    private function generateGlobalRecommendations(): array
    {
        $recommendations = [];
        
        // Analyze slow query patterns
        $patterns = $this->getQueryPatterns();
        
        if ($patterns['select_without_where'] > 0) {
            $recommendations[] = [
                'type' => 'missing_where_clauses',
                'priority' => 'high',
                'message' => "Found {$patterns['select_without_where']} queries without WHERE clauses",
                'suggestion' => 'Add appropriate WHERE clauses to avoid full table scans'
            ];
        }
        
        if ($patterns['select_with_like_leading_wildcard'] > 0) {
            $recommendations[] = [
                'type' => 'like_leading_wildcard',
                'priority' => 'medium',
                'message' => "Found {$patterns['select_with_like_leading_wildcard']} queries with leading wildcards",
                'suggestion' => 'Consider full-text search or alternative patterns for better performance'
            ];
        }
        
        if ($patterns['select_star'] > 0) {
            $recommendations[] = [
                'type' => 'select_star',
                'priority' => 'medium',
                'message' => "Found {$patterns['select_star']} queries using SELECT *",
                'suggestion' => 'Specify only required columns to reduce data transfer'
            ];
        }

        if ($patterns['missing_limit'] > 0) {
            $recommendations[] = [
                'type' => 'missing_limit',
                'priority' => 'high',
                'message' => "Found {$patterns['missing_limit']} ORDER BY queries without LIMIT",
                'suggestion' => 'Add LIMIT clauses to prevent sorting large result sets'
            ];
        }

        // Analyze query execution times
        $avgTime = $this->getPerformanceSummary()['average_execution_time'];
        if ($avgTime > 0.05) { // 50ms
            $recommendations[] = [
                'type' => 'high_average_execution_time',
                'priority' => 'high',
                'message' => "Average query execution time is {$avgTime}s",
                'suggestion' => 'Review slow queries and consider adding indexes'
            ];
        }

        // Check for missing indexes based on slow queries
        $missingIndexes = $this->analyzeMissingIndexes();
        foreach ($missingIndexes as $index) {
            $recommendations[] = [
                'type' => 'missing_index',
                'priority' => 'high',
                'message' => "Missing index on {$index['table']}.{$index['column']}",
                'suggestion' => "CREATE INDEX idx_{$index['table']}_{$index['column']} ON {$index['table']} ({$index['column']})"
            ];
        }

        return $recommendations;
    }

    /**
     * Analyze missing indexes based on slow queries.
     */
    private function analyzeMissingIndexes(): array
    {
        $missingIndexes = [];
        
        foreach ($this->slowQueries as $query) {
            if (isset($query['explain']) && is_array($query['explain'])) {
                foreach ($query['explain'] as $row) {
                    if (isset($row['key']) && $row['key'] === null && isset($row['table'])) {
                        // This indicates a table scan - might need an index
                        $missingIndexes[] = [
                            'table' => $row['table'],
                            'column' => 'unknown', // Would need more sophisticated parsing
                            'type' => 'table_scan',
                            'rows' => $row['rows'] ?? 0
                        ];
                    }
                }
            }
        }

        return array_unique($missingIndexes, SORT_REGULAR);
    }

    /**
     * Generate recommendations for a specific query.
     */
    private function generateQueryRecommendations(string $sql, ?array $explainData): array
    {
        $recommendations = [];
        $sqlLower = strtolower($sql);
        
        // Check for common anti-patterns
        if (str_starts_with($sqlLower, 'select') && !str_contains($sqlLower, ' where ')) {
            $recommendations[] = 'Add WHERE clause to avoid full table scan';
        }
        
        if (str_contains($sqlLower, ' like \'%')) {
            $recommendations[] = 'LIKE with leading wildcard cannot use index efficiently';
        }
        
        if (str_contains($sqlLower, 'select *')) {
            $recommendations[] = 'Avoid SELECT * - specify only needed columns';
        }
        
        if (str_contains($sqlLower, ' order by ') && !str_contains($sqlLower, ' limit ')) {
            $recommendations[] = 'Add LIMIT to ORDER BY queries';
        }

        // Analyze EXPLAIN data if available
        if ($explainData) {
            foreach ($explainData as $row) {
                if (isset($row['type']) && $row['type'] === 'ALL') {
                    $recommendations[] = 'Query performs full table scan - consider adding index';
                }
                
                if (isset($row['Extra']) && str_contains($row['Extra'], 'Using filesort')) {
                    $recommendations[] = 'Query requires filesort - consider adding index for ORDER BY';
                }
                
                if (isset($row['Extra']) && str_contains($row['Extra'], 'Using temporary')) {
                    $recommendations[] = 'Query uses temporary table - optimize GROUP BY/ORDER BY';
                }
            }
        }
        
        return $recommendations;
    }

    /**
     * Normalize SQL query for pattern matching.
     */
    private function normalizeQuery(string $sql): string
    {
        // Replace parameter placeholders with generic placeholders
        $normalized = preg_replace('/\?/', '?', $sql);
        $normalized = preg_replace('/:\w+/', ':param', $normalized);
        
        // Remove extra whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        return trim($normalized);
    }

    /**
     * Generate a hash for query identification.
     */
    private function generateQueryHash(string $sql): string
    {
        return hash('xxh64', $this->normalizeQuery($sql));
    }

    /**
     * Sanitize parameters for logging.
     */
    private function sanitizeParams(array $params): array
    {
        $sanitized = [];
        foreach ($params as $key => $value) {
            if (is_string($value) && strlen($value) > 100) {
                $sanitized[$key] = substr($value, 0, 100) . '...';
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Clear monitoring data.
     */
    public function clearData(): void
    {
        $this->slowQueries = [];
        $this->queryStats = [];
        $this->logger->info('Performance monitoring data cleared');
    }

    /**
     * Set slow query threshold.
     */
    public function setSlowQueryThreshold(float $threshold): void
    {
        $this->slowQueryThreshold = $threshold;
        $this->logger->info('Slow query threshold updated', ['threshold' => $threshold]);
    }

    /**
     * Get current slow query threshold.
     */
    public function getSlowQueryThreshold(): float
    {
        return $this->slowQueryThreshold;
    }

    /**
     * Export performance data to cache for persistence.
     */
    public function exportToCache(): void
    {
        $data = [
            'slow_queries' => $this->slowQueries,
            'query_stats' => $this->queryStats,
            'exported_at' => time()
        ];

        $cacheItem = $this->cache->getItem('database_performance_data');
        $cacheItem->set($data);
        $cacheItem->expiresAfter(86400); // 24 hours
        $this->cache->save($cacheItem);

        $this->logger->info('Performance data exported to cache');
    }

    /**
     * Import performance data from cache.
     */
    public function importFromCache(): bool
    {
        $cacheItem = $this->cache->getItem('database_performance_data');
        
        if ($cacheItem->isHit()) {
            $data = $cacheItem->get();
            $this->slowQueries = $data['slow_queries'] ?? [];
            $this->queryStats = $data['query_stats'] ?? [];
            
            $this->logger->info('Performance data imported from cache', [
                'slow_queries' => count($this->slowQueries),
                'query_stats' => count($this->queryStats)
            ]);
            
            return true;
        }

        return false;
    }
}