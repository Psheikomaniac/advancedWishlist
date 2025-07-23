<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Command;

/**
 * Interface for command middleware in the CQRS pipeline.
 * Enables cross-cutting concerns like validation, logging, and transactions.
 */
interface CommandMiddleware
{
    /**
     * Handle the command with middleware pipeline.
     * 
     * @param object $command The command to execute
     * @param callable $next The next middleware in the pipeline
     * @return mixed The command result
     */
    public function handle(object $command, callable $next): mixed;
}