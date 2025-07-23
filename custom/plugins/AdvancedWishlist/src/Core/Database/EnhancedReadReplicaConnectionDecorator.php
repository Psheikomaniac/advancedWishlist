<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Psr\Log\LoggerInterface;

/**
 * Enhanced read replica connection decorator with intelligent query routing.
 * 
 * This decorator automatically routes read operations to replica databases
 * while ensuring write operations and replication-sensitive reads use the master.
 */
class EnhancedReadReplicaConnectionDecorator
{
    private Connection $masterConnection;
    private Connection $replicaConnection;  
    private LoggerInterface $logger;
    private bool $useReplica = true;
    private array $writeOperations = [
        'insert', 'update', 'delete', 'replace', 'alter', 'create', 'drop', 
        'truncate', 'rename', 'call', 'do', 'handler', 'load', 'optimize',
        'repair', 'revoke', 'grant', 'flush', 'kill', 'reset', 'stop', 'start'
    ];
    private array $replicationSensitiveOperations = [
        'select ... for update', 'select ... lock in share mode', 'lock tables', 
        'unlock tables', 'get_lock', 'release_lock', 'last_insert_id'
    ];
    private int $queryCount = 0;
    private int $replicaQueries = 0;
    private int $masterQueries = 0;

    public function __construct(
        Connection $masterConnection,
        Connection $replicaConnection,
        LoggerInterface $logger
    ) {
        $this->masterConnection = $masterConnection;
        $this->replicaConnection = $replicaConnection;
        $this->logger = $logger;
    }

    /**
     * Execute a query with intelligent connection routing.
     */
    public function executeQuery(string $sql, array $params = [], array $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        $this->queryCount++;
        $isReadOperation = $this->isReadOperation($sql);
        
        // Force master for replication-sensitive reads
        if ($isReadOperation && $this->isReplicationSensitive($sql)) {
            $this->logger->debug('Using master for replication-sensitive read', [
                'sql' => substr($sql, 0, 100) . (strlen($sql) > 100 ? '...' : ''),
                'reason' => 'replication_sensitive'
            ]);
            $this->masterQueries++;
            return $this->masterConnection->executeQuery($sql, $params, $types, $qcp);
        }
        
        // Use replica for read operations
        if ($isReadOperation && $this->useReplica) {
            try {
                $startTime = microtime(true);
                $result = $this->replicaConnection->executeQuery($sql, $params, $types, $qcp);
                $executionTime = microtime(true) - $startTime;
                
                $this->replicaQueries++;
                
                if ($executionTime > 0.1) { // Log slow queries
                    $this->logger->warning('Slow replica query detected', [
                        'sql' => substr($sql, 0, 200),
                        'execution_time' => $executionTime,
                        'params_count' => count($params)
                    ]);
                }
                
                return $result;
            } catch (\Exception $e) {
                $this->logger->warning('Replica query failed, falling back to master', [
                    'sql' => substr($sql, 0, 100) . '...',
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode()
                ]);
                $this->masterQueries++;
                return $this->masterConnection->executeQuery($sql, $params, $types, $qcp);
            }
        }
        
        // Use master for write operations
        $this->masterQueries++;
        $startTime = microtime(true);
        $result = $this->masterConnection->executeQuery($sql, $params, $types, $qcp);
        $executionTime = microtime(true) - $startTime;
        
        if ($executionTime > 0.2) { // Log slow write queries
            $this->logger->warning('Slow master query detected', [
                'sql' => substr($sql, 0, 200),
                'execution_time' => $executionTime,
                'is_write' => !$isReadOperation
            ]);
        }
        
        return $result;
    }

    /**
     * Execute a statement with intelligent connection routing.
     */
    public function executeStatement(string $sql, array $params = [], array $types = []): int
    {
        $this->queryCount++;
        $isReadOperation = $this->isReadOperation($sql);
        
        // Statements are typically write operations, always use master
        if (!$isReadOperation) {
            $this->masterQueries++;
            return $this->masterConnection->executeStatement($sql, $params, $types);
        }
        
        // Handle read statements (rare but possible)
        if ($this->useReplica && !$this->isReplicationSensitive($sql)) {
            try {
                $this->replicaQueries++;
                return $this->replicaConnection->executeStatement($sql, $params, $types);
            } catch (\Exception $e) {
                $this->logger->warning('Replica statement failed, falling back to master', [
                    'sql' => substr($sql, 0, 100) . '...',
                    'error' => $e->getMessage()
                ]);
                $this->masterQueries++;
                return $this->masterConnection->executeStatement($sql, $params, $types);
            }
        }
        
        $this->masterQueries++;
        return $this->masterConnection->executeStatement($sql, $params, $types);
    }

    /**
     * Determine if a SQL query is a read operation.
     */
    private function isReadOperation(string $sql): bool
    {
        $sql = strtolower(trim($sql));
        
        // Check for read operations
        if (str_starts_with($sql, 'select') || 
            str_starts_with($sql, 'show') || 
            str_starts_with($sql, 'describe') ||
            str_starts_with($sql, 'desc') ||
            str_starts_with($sql, 'explain') ||
            str_starts_with($sql, 'help')) {
            return true;
        }
        
        // Check for write operations
        foreach ($this->writeOperations as $operation) {
            if (str_starts_with($sql, $operation)) {
                return false;
            }
        }
        
        // Default to read if unclear
        return true;
    }

    /**
     * Check if a query is replication-sensitive and must use master.
     */
    private function isReplicationSensitive(string $sql): bool
    {
        $sql = strtolower($sql);
        
        foreach ($this->replicationSensitiveOperations as $operation) {
            if (str_contains($sql, $operation)) {
                return true;
            }
        }
        
        // Check for transactions
        if (str_contains($sql, 'start transaction') ||
            str_contains($sql, 'begin') ||
            str_contains($sql, 'commit') ||
            str_contains($sql, 'rollback')) {
            return true;
        }
        
        // Check for session variables
        if (str_contains($sql, '@@') || str_contains($sql, 'session')) {
            return true;
        }
        
        return false;
    }

    /**
     * Get the appropriate connection for manual usage.
     */
    public function getConnection(bool $forRead = true): Connection
    {
        if ($forRead && $this->useReplica) {
            return $this->replicaConnection;
        }
        return $this->masterConnection;
    }

    /**
     * Disable replica usage for the current request.
     */
    public function disableReplica(): void
    {
        $this->useReplica = false;
        $this->logger->info('Read replica disabled for this request');
    }

    /**
     * Enable replica usage.
     */
    public function enableReplica(): void
    {
        $this->useReplica = true;
        $this->logger->info('Read replica enabled');
    }

    /**
     * Check if replica is currently enabled.
     */
    public function isReplicaEnabled(): bool
    {
        return $this->useReplica;
    }

    /**
     * Test replica connection availability.
     */
    public function testReplicaConnection(): bool
    {
        try {
            $this->replicaConnection->executeQuery('SELECT 1');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Replica connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Test master connection availability.
     */
    public function testMasterConnection(): bool
    {
        try {
            $this->masterConnection->executeQuery('SELECT 1');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Master connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get connection statistics.
     */
    public function getStatistics(): array
    {
        $replicaPercentage = $this->queryCount > 0 
            ? round(($this->replicaQueries / $this->queryCount) * 100, 2)
            : 0;

        return [
            'total_queries' => $this->queryCount,
            'master_queries' => $this->masterQueries,
            'replica_queries' => $this->replicaQueries,
            'replica_usage_percentage' => $replicaPercentage,
            'replica_enabled' => $this->useReplica,
            'master_available' => $this->testMasterConnection(),
            'replica_available' => $this->testReplicaConnection()
        ];
    }

    /**
     * Reset statistics counters.
     */
    public function resetStatistics(): void
    {
        $this->queryCount = 0;
        $this->masterQueries = 0;
        $this->replicaQueries = 0;
        $this->logger->debug('Connection statistics reset');
    }

    /**
     * Check replication lag (if supported by the database).
     */
    public function getReplicationLag(): ?float
    {
        try {
            // This is MySQL-specific - would need adaptation for other databases
            $masterResult = $this->masterConnection->fetchAssociative('SHOW MASTER STATUS');
            $replicaResult = $this->replicaConnection->fetchAssociative('SHOW SLAVE STATUS');

            if ($masterResult && $replicaResult) {
                $masterPosition = $masterResult['Position'] ?? 0;
                $replicaPosition = $replicaResult['Exec_Master_Log_Pos'] ?? 0;
                
                // This is a simplified calculation - actual lag calculation is more complex
                $lag = abs($masterPosition - $replicaPosition);
                
                if ($lag > 1000000) { // Log significant lag
                    $this->logger->warning('High replication lag detected', [
                        'lag_bytes' => $lag,
                        'master_position' => $masterPosition,
                        'replica_position' => $replicaPosition
                    ]);
                }
                
                return $lag;
            }
        } catch (\Exception $e) {
            $this->logger->debug('Could not determine replication lag', [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Force next query to use master regardless of operation type.
     */
    public function forceNextQueryToMaster(): void
    {
        $previousState = $this->useReplica;
        $this->useReplica = false;
        
        // Register a one-time callback to restore the previous state
        register_shutdown_function(function() use ($previousState) {
            $this->useReplica = $previousState;
        });
    }

    /**
     * Delegate method calls to the master connection for compatibility.
     */
    public function __call(string $method, array $arguments)
    {
        return $this->masterConnection->$method(...$arguments);
    }
}