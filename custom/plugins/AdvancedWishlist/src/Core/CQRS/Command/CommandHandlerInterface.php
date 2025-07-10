<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Command;

/**
 * Interface for all command handlers in the CQRS pattern.
 * 
 * Command handlers are responsible for processing commands and performing
 * the actual operations that change the system state.
 */
interface CommandHandlerInterface
{
    /**
     * Handle a command.
     * 
     * @template T
     * @param object $command The command to handle
     * @return mixed The result of handling the command, if any
     */
    public function handle(object $command): mixed;
}