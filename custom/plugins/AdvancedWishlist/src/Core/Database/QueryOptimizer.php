<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for optimizing database queries.
 */
class QueryOptimizer
{
    /**
     * @var array<string, array> Cache of table indexes
     */
    private array $tableIndexes = [];

    /**
     * @var array<string, array> Cache of table columns
     */
    private array $tableColumns = [];

    /**
     * @var array<string, bool> Cache of query optimization results
     */
    private array $optimizedQueries = [];

    /**
     * @var int Query cache TTL in seconds
     */
    private const QUERY_CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Optimize a query builder.
     *
     * @param QueryBuilder $queryBuilder The query builder to optimize
     * @param bool         $analyze      Whether to analyze the query for potential optimizations
     *
     * @return QueryBuilder The optimized query builder
     */
    public function optimizeQueryBuilder(QueryBuilder $queryBuilder, bool $analyze = true): QueryBuilder
    {
        // Get the SQL and parameters
        $sql = $queryBuilder->getSQL();
        $params = $queryBuilder->getParameters();

        // Generate a cache key for this query
        $cacheKey = 'query_'.md5($sql.serialize($params));

        // Check if we've already optimized this query
        if (isset($this->optimizedQueries[$cacheKey])) {
            return $queryBuilder;
        }

        // Try to get optimized query from cache
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $optimizationData = $cacheItem->get();

            // Apply cached optimizations if available
            if (isset($optimizationData['hints']) && !empty($optimizationData['hints'])) {
                foreach ($optimizationData['hints'] as $hint) {
                    $queryBuilder->addHint($hint);
                }
            }

            $this->optimizedQueries[$cacheKey] = true;

            return $queryBuilder;
        }

        // Extract tables from the query
        $tables = $this->extractTablesFromQuery($sql);

        // Apply optimizations
        $optimizations = [];

        // Add query hints
        $hints = $this->getQueryHints($sql, $tables);
        foreach ($hints as $hint) {
            $queryBuilder->addHint($hint);
            $optimizations[] = "Added hint: $hint";
        }

        // Analyze the query if requested
        if ($analyze) {
            $analysisResults = $this->analyzeQuery($sql, $params, $tables);

            if (!empty($analysisResults)) {
                $this->logger->info('Query optimization suggestions', [
                    'sql' => $sql,
                    'suggestions' => $analysisResults,
                ]);

                $optimizations = array_merge($optimizations, $analysisResults);
            }
        }

        // Cache the optimization results
        $cacheItem->set([
            'hints' => $hints,
            'optimizations' => $optimizations,
        ]);
        $cacheItem->expiresAfter(self::QUERY_CACHE_TTL);
        $this->cache->save($cacheItem);

        $this->optimizedQueries[$cacheKey] = true;

        return $queryBuilder;
    }

    /**
     * Analyze a query for potential optimizations.
     *
     * @param string $sql    The SQL query
     * @param array  $params The query parameters
     * @param array  $tables The tables involved in the query
     *
     * @return array Analysis results with optimization suggestions
     */
    public function analyzeQuery(string $sql, array $params = [], array $tables = []): array
    {
        $results = [];

        // If no tables were extracted, try to extract them
        if (empty($tables)) {
            $tables = $this->extractTablesFromQuery($sql);
        }

        // Check for missing WHERE clauses
        if (!preg_match('/\bWHERE\b/i', $sql) && count($tables) > 0) {
            $results[] = 'Query does not have a WHERE clause, which may result in full table scans';
        }

        // Check for missing indexes on WHERE clauses
        if (preg_match_all('/\bWHERE\b\s+([^=]+)=\s*\?/i', $sql, $matches)) {
            foreach ($matches[1] as $column) {
                $column = trim($column);
                $tableName = $this->getTableForColumn($column, $tables);

                if ($tableName && !$this->isColumnIndexed($tableName, $column)) {
                    $results[] = "Column '$column' in table '$tableName' used in WHERE clause is not indexed";
                }
            }
        }

        // Check for missing indexes on JOIN conditions
        if (preg_match_all('/\bJOIN\b\s+(\w+)\s+.*?\bON\b\s+([^=]+)=([^\\s]+)/i', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $joinTable = trim($match[1]);
                $leftSide = trim($match[2]);
                $rightSide = trim($match[3]);

                // Check left side
                $leftTable = $this->getTableForColumn($leftSide, $tables);
                if ($leftTable && !$this->isColumnIndexed($leftTable, $leftSide)) {
                    $results[] = "Column '$leftSide' in table '$leftTable' used in JOIN condition is not indexed";
                }

                // Check right side
                $rightTable = $this->getTableForColumn($rightSide, $tables);
                if ($rightTable && !$this->isColumnIndexed($rightTable, $rightSide)) {
                    $results[] = "Column '$rightSide' in table '$rightTable' used in JOIN condition is not indexed";
                }
            }
        }

        // Check for missing indexes on ORDER BY clauses
        if (preg_match_all('/\bORDER\s+BY\b\s+([^,)]+)/i', $sql, $matches)) {
            foreach ($matches[1] as $column) {
                $column = trim($column);
                // Remove ASC/DESC if present
                $column = preg_replace('/\s+(ASC|DESC)$/i', '', $column);

                $tableName = $this->getTableForColumn($column, $tables);

                if ($tableName && !$this->isColumnIndexed($tableName, $column)) {
                    $results[] = "Column '$column' in table '$tableName' used in ORDER BY clause is not indexed";
                }
            }
        }

        // Check for potential N+1 query patterns
        if (preg_match('/\bIN\s*\(\s*\?\s*\)/i', $sql)) {
            $results[] = 'Query contains IN (?) pattern which may indicate an N+1 query problem';
        }

        // Check for LIKE with leading wildcard
        if (preg_match('/\bLIKE\s+[\'"]%/i', $sql)) {
            $results[] = "Query contains LIKE with leading wildcard ('%...') which cannot use indexes efficiently";
        }

        // Check for SELECT * usage
        if (preg_match('/SELECT\s+\*/i', $sql)) {
            $results[] = 'Query uses SELECT * which may retrieve unnecessary columns';
        }

        return $results;
    }

    /**
     * Get query hints for optimization.
     *
     * @param string $sql    The SQL query
     * @param array  $tables The tables involved in the query
     *
     * @return array Query hints
     */
    private function getQueryHints(string $sql, array $tables): array
    {
        $hints = [];

        // Add SQL_NO_CACHE hint for write queries to ensure fresh data
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)/i', $sql)) {
            $hints[] = 'SQL_NO_CACHE';
        }

        // Add SQL_CALC_FOUND_ROWS for paginated queries
        if (preg_match('/\bLIMIT\b/i', $sql) && !preg_match('/\bSQL_CALC_FOUND_ROWS\b/i', $sql)) {
            $hints[] = 'SQL_CALC_FOUND_ROWS';
        }

        return $hints;
    }

    /**
     * Extract table names from a SQL query.
     *
     * @param string $sql The SQL query
     *
     * @return array Table names
     */
    private function extractTablesFromQuery(string $sql): array
    {
        $tables = [];

        // Extract tables from FROM clause
        if (preg_match('/\bFROM\b\s+([^\s,]+)/i', $sql, $matches)) {
            $tables[] = $this->cleanTableName($matches[1]);
        }

        // Extract tables from JOIN clauses
        if (preg_match_all('/\bJOIN\b\s+([^\s]+)/i', $sql, $matches)) {
            foreach ($matches[1] as $table) {
                $tables[] = $this->cleanTableName($table);
            }
        }

        return array_unique($tables);
    }

    /**
     * Clean a table name (remove backticks, aliases, etc.).
     *
     * @param string $tableName The table name to clean
     *
     * @return string The cleaned table name
     */
    private function cleanTableName(string $tableName): string
    {
        // Remove backticks
        $tableName = str_replace('`', '', $tableName);

        // Remove schema if present
        if (false !== strpos($tableName, '.')) {
            $parts = explode('.', $tableName);
            $tableName = end($parts);
        }

        // Remove alias if present
        if (preg_match('/^([^\s]+)\s+[^\s]+$/', $tableName, $matches)) {
            $tableName = $matches[1];
        }

        return $tableName;
    }

    /**
     * Get the table name for a column.
     *
     * @param string $column The column name
     * @param array  $tables Possible tables
     *
     * @return string|null The table name or null if not found
     */
    private function getTableForColumn(string $column, array $tables): ?string
    {
        // If column has table prefix, extract it
        if (false !== strpos($column, '.')) {
            $parts = explode('.', $column);
            $tableName = $this->cleanTableName($parts[0]);

            // Check if this table is in our list
            if (in_array($tableName, $tables)) {
                return $tableName;
            }
        }

        // Otherwise, check each table for this column
        foreach ($tables as $table) {
            $columns = $this->getTableColumns($table);
            $columnName = $this->cleanColumnName($column);

            if (in_array($columnName, $columns)) {
                return $table;
            }
        }

        return null;
    }

    /**
     * Clean a column name (remove backticks, table prefix, etc.).
     *
     * @param string $columnName The column name to clean
     *
     * @return string The cleaned column name
     */
    private function cleanColumnName(string $columnName): string
    {
        // Remove backticks
        $columnName = str_replace('`', '', $columnName);

        // Remove table prefix if present
        if (false !== strpos($columnName, '.')) {
            $parts = explode('.', $columnName);
            $columnName = end($parts);
        }

        return $columnName;
    }

    /**
     * Get columns for a table.
     *
     * @param string $tableName The table name
     *
     * @return array Column names
     */
    private function getTableColumns(string $tableName): array
    {
        if (isset($this->tableColumns[$tableName])) {
            return $this->tableColumns[$tableName];
        }

        try {
            $cacheKey = 'table_columns_'.$tableName;
            $cacheItem = $this->cache->getItem($cacheKey);

            if ($cacheItem->isHit()) {
                $columns = $cacheItem->get();
                $this->tableColumns[$tableName] = $columns;

                return $columns;
            }

            $schemaManager = $this->connection->createSchemaManager();
            $columns = [];

            if ($schemaManager->tablesExist([$tableName])) {
                $tableColumns = $schemaManager->listTableColumns($tableName);
                foreach ($tableColumns as $column) {
                    $columns[] = $column->getName();
                }
            }

            $this->tableColumns[$tableName] = $columns;

            $cacheItem->set($columns);
            $cacheItem->expiresAfter(self::QUERY_CACHE_TTL);
            $this->cache->save($cacheItem);

            return $columns;
        } catch (\Exception $e) {
            $this->logger->error('Error getting table columns', [
                'table' => $tableName,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if a column is indexed.
     *
     * @param string $tableName  The table name
     * @param string $columnName The column name
     *
     * @return bool True if the column is indexed
     */
    private function isColumnIndexed(string $tableName, string $columnName): bool
    {
        $columnName = $this->cleanColumnName($columnName);

        // Get indexes for this table
        $indexes = $this->getTableIndexes($tableName);

        // Check if column is in any index
        foreach ($indexes as $index) {
            if (in_array($columnName, $index['columns'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get indexes for a table.
     *
     * @param string $tableName The table name
     *
     * @return array Indexes
     */
    private function getTableIndexes(string $tableName): array
    {
        if (isset($this->tableIndexes[$tableName])) {
            return $this->tableIndexes[$tableName];
        }

        try {
            $cacheKey = 'table_indexes_'.$tableName;
            $cacheItem = $this->cache->getItem($cacheKey);

            if ($cacheItem->isHit()) {
                $indexes = $cacheItem->get();
                $this->tableIndexes[$tableName] = $indexes;

                return $indexes;
            }

            $schemaManager = $this->connection->createSchemaManager();
            $indexes = [];

            if ($schemaManager->tablesExist([$tableName])) {
                $tableIndexes = $schemaManager->listTableIndexes($tableName);

                foreach ($tableIndexes as $indexName => $index) {
                    $indexes[$indexName] = [
                        'name' => $indexName,
                        'columns' => $index->getColumns(),
                        'isPrimary' => $index->isPrimary(),
                        'isUnique' => $index->isUnique(),
                    ];
                }
            }

            $this->tableIndexes[$tableName] = $indexes;

            $cacheItem->set($indexes);
            $cacheItem->expiresAfter(self::QUERY_CACHE_TTL);
            $this->cache->save($cacheItem);

            return $indexes;
        } catch (\Exception $e) {
            $this->logger->error('Error getting table indexes', [
                'table' => $tableName,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Create a missing index.
     *
     * @param string $tableName The table name
     * @param array  $columns   The columns to index
     * @param bool   $isUnique  Whether the index should be unique
     *
     * @return bool True if the index was created
     */
    public function createIndex(string $tableName, array $columns, bool $isUnique = false): bool
    {
        try {
            // Generate index name
            $indexName = $tableName.'_'.implode('_', $columns).($isUnique ? '_uniq' : '').'_idx';

            // Check if index already exists
            $indexes = $this->getTableIndexes($tableName);
            foreach ($indexes as $index) {
                if (0 === count(array_diff($index['columns'], $columns)) && 0 === count(array_diff($columns, $index['columns']))) {
                    $this->logger->info('Index already exists', [
                        'table' => $tableName,
                        'columns' => $columns,
                        'index_name' => $index['name'],
                    ]);

                    return true;
                }
            }

            // Create the index
            $sql = sprintf(
                'CREATE %s INDEX `%s` ON `%s` (%s)',
                $isUnique ? 'UNIQUE' : '',
                $indexName,
                $tableName,
                implode(', ', array_map(function ($col) {
                    return '`'.$col.'`';
                }, $columns))
            );

            $this->connection->executeStatement($sql);

            // Clear cache for this table
            $this->cache->deleteItem('table_indexes_'.$tableName);
            unset($this->tableIndexes[$tableName]);

            $this->logger->info('Created index', [
                'table' => $tableName,
                'columns' => $columns,
                'index_name' => $indexName,
                'unique' => $isUnique,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error creating index', [
                'table' => $tableName,
                'columns' => $columns,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Analyze a table and create missing indexes.
     *
     * @param string $tableName The table name
     *
     * @return array Results of the analysis
     */
    public function analyzeTableAndCreateIndexes(string $tableName): array
    {
        $results = [
            'table' => $tableName,
            'indexes_created' => [],
            'errors' => [],
        ];

        try {
            // Get common query patterns for this table
            $commonQueries = $this->getCommonQueryPatternsForTable($tableName);

            foreach ($commonQueries as $pattern) {
                $columns = $pattern['columns'];

                // Skip if already indexed
                $alreadyIndexed = true;
                foreach ($columns as $column) {
                    if (!$this->isColumnIndexed($tableName, $column)) {
                        $alreadyIndexed = false;
                        break;
                    }
                }

                if ($alreadyIndexed) {
                    continue;
                }

                // Create the index
                $isUnique = 'unique' === $pattern['type'];
                if ($this->createIndex($tableName, $columns, $isUnique)) {
                    $results['indexes_created'][] = [
                        'columns' => $columns,
                        'type' => $pattern['type'],
                    ];
                }
            }

            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Error analyzing table', [
                'table' => $tableName,
                'error' => $e->getMessage(),
            ]);

            $results['errors'][] = $e->getMessage();

            return $results;
        }
    }

    /**
     * Get common query patterns for a table.
     *
     * @param string $tableName The table name
     *
     * @return array Common query patterns
     */
    private function getCommonQueryPatternsForTable(string $tableName): array
    {
        // These are common query patterns that would benefit from indexes
        $commonPatterns = [];

        // For wishlist tables
        if ('wishlist' === $tableName) {
            $commonPatterns[] = [
                'columns' => ['customer_id'],
                'type' => 'index',
            ];
            $commonPatterns[] = [
                'columns' => ['customer_id', 'name'],
                'type' => 'index',
            ];
            $commonPatterns[] = [
                'columns' => ['created_at'],
                'type' => 'index',
            ];
            $commonPatterns[] = [
                'columns' => ['type'],
                'type' => 'index',
            ];
        } elseif ('wishlist_item' === $tableName) {
            $commonPatterns[] = [
                'columns' => ['wishlist_id'],
                'type' => 'index',
            ];
            $commonPatterns[] = [
                'columns' => ['product_id'],
                'type' => 'index',
            ];
            $commonPatterns[] = [
                'columns' => ['wishlist_id', 'product_id'],
                'type' => 'unique',
            ];
            $commonPatterns[] = [
                'columns' => ['created_at'],
                'type' => 'index',
            ];
        } elseif ('wishlist_share' === $tableName) {
            $commonPatterns[] = [
                'columns' => ['wishlist_id'],
                'type' => 'index',
            ];
            $commonPatterns[] = [
                'columns' => ['token'],
                'type' => 'unique',
            ];
            $commonPatterns[] = [
                'columns' => ['recipient_id'],
                'type' => 'index',
            ];
            $commonPatterns[] = [
                'columns' => ['created_at'],
                'type' => 'index',
            ];
        } elseif ('guest_wishlist' === $tableName) {
            $commonPatterns[] = [
                'columns' => ['guest_id'],
                'type' => 'unique',
            ];
            $commonPatterns[] = [
                'columns' => ['created_at'],
                'type' => 'index',
            ];
        } elseif ('wishlist_analytics' === $tableName) {
            $commonPatterns[] = [
                'columns' => ['wishlist_id'],
                'type' => 'index',
            ];
            $commonPatterns[] = [
                'columns' => ['event_type'],
                'type' => 'index',
            ];
            $commonPatterns[] = [
                'columns' => ['created_at'],
                'type' => 'index',
            ];
        }

        return $commonPatterns;
    }
}
