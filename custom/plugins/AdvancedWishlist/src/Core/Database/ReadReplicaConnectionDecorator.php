<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Database;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Psr\Log\LoggerInterface;

/**
 * Connection factory for database read replicas.
 *
 * This class provides a factory for creating database connections that route read queries
 * to a read replica and write queries to the master database.
 */
class ReadReplicaConnectionDecorator
{
    /**
     * @var Connection The master connection for write operations
     */
    private Connection $masterConnection;

    /**
     * @var Connection The replica connection for read operations
     */
    private Connection $replicaConnection;

    /**
     * @var LoggerInterface Logger for logging connection usage
     */
    private LoggerInterface $logger;

    /**
     * @var bool Whether to use the replica for read operations
     */
    private bool $useReplica = true;

    /**
     * @param Connection      $masterConnection  The master connection for write operations
     * @param Connection      $replicaConnection The replica connection for read operations
     * @param LoggerInterface $logger            Logger for logging connection usage
     */
    public function __construct(
        Connection $masterConnection,
        Connection $replicaConnection,
        LoggerInterface $logger,
    ) {
        $this->masterConnection = $masterConnection;
        $this->replicaConnection = $replicaConnection;
        $this->logger = $logger;
    }

    /**
     * Enable or disable the use of the replica.
     */
    public function setUseReplica(bool $useReplica): void
    {
        $this->useReplica = $useReplica;
    }

    /**
     * Get the appropriate connection for the given operation.
     *
     * @param bool $isReadOperation Whether the operation is a read operation
     *
     * @return Connection The appropriate connection
     */
    public function getConnection(bool $isReadOperation = true): Connection
    {
        if ($isReadOperation && $this->useReplica) {
            $this->logger->debug('Using read replica connection');

            return $this->replicaConnection;
        }

        $this->logger->debug('Using master connection');

        return $this->masterConnection;
    }

    /**
     * Execute a query and return the result.
     *
     * @param string $sql    The SQL query
     * @param array  $params The query parameters
     * @param array  $types  The parameter types
     *
     * @return Result The query result
     */
    public function executeQuery(string $sql, array $params = [], array $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        // Determine if this is a read operation based on the SQL
        $isReadOperation = $this->isReadOperation($sql);

        return $this->getConnection($isReadOperation)->executeQuery($sql, $params, $types, $qcp);
    }

    /**
     * Execute a statement and return the number of affected rows.
     *
     * @param string $sql    The SQL statement
     * @param array  $params The statement parameters
     * @param array  $types  The parameter types
     *
     * @return int The number of affected rows
     */
    public function executeStatement(string $sql, array $params = [], array $types = []): int
    {
        // Write operations always go to the master
        return $this->getConnection(false)->executeStatement($sql, $params, $types);
    }

    /**
     * Determine if the given SQL is a read operation.
     *
     * @param string $sql The SQL to check
     *
     * @return bool Whether the SQL is a read operation
     */
    private function isReadOperation(string $sql): bool
    {
        // Normalize the SQL by removing extra whitespace and converting to lowercase
        $normalizedSql = strtolower(trim($sql));

        // Check if the SQL starts with a read operation keyword
        $readOperations = ['select', 'show', 'describe', 'explain'];

        foreach ($readOperations as $operation) {
            if (str_starts_with($normalizedSql, $operation)) {
                return true;
            }
        }

        return false;
    }
}
