<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Command\Middleware;

use AdvancedWishlist\Core\CQRS\Command\CommandMiddleware;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Transaction middleware for CQRS command pipeline.
 * Wraps command execution in database transactions for data consistency.
 */
class TransactionMiddleware implements CommandMiddleware
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(object $command, callable $next): mixed
    {
        $commandClass = get_class($command);
        $startTime = microtime(true);

        // Check if command should run in transaction
        if (!$this->shouldUseTransaction($command)) {
            return $next($command);
        }

        $this->logger->debug('Starting transaction for command', [
            'command' => $commandClass
        ]);

        $this->connection->beginTransaction();

        try {
            $result = $next($command);
            
            $this->connection->commit();
            
            $duration = microtime(true) - $startTime;
            $this->logger->info('Command transaction committed successfully', [
                'command' => $commandClass,
                'duration_ms' => round($duration * 1000, 2)
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            
            $duration = microtime(true) - $startTime;
            $this->logger->error('Command transaction rolled back', [
                'command' => $commandClass,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2)
            ]);

            throw $e;
        }
    }

    /**
     * Determine if command should run in a transaction.
     * By default, all commands that modify data should use transactions.
     */
    private function shouldUseTransaction(object $command): bool
    {
        // Commands that don't need transactions (query-like operations)
        $nonTransactionalCommands = [
            // Add command classes that don't need transactions
        ];

        return !in_array(get_class($command), $nonTransactionalCommands);
    }
}