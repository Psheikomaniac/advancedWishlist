<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Command;

use AdvancedWishlist\Core\Exception\CommandHandlerNotFoundException;

/**
 * Registry for command handlers in the CQRS architecture.
 * Eliminates service locator pattern and provides explicit handler registration.
 */
class CommandHandlerRegistry
{
    /**
     * @var array<string, CommandHandlerInterface>
     */
    private array $handlers = [];

    /**
     * Register a command handler for a specific command class.
     */
    public function register(string $commandClass, CommandHandlerInterface $handler): void
    {
        if (isset($this->handlers[$commandClass])) {
            throw new \InvalidArgumentException(
                "Handler for command '{$commandClass}' is already registered"
            );
        }

        $this->handlers[$commandClass] = $handler;
    }

    /**
     * Get the handler for a specific command class.
     */
    public function get(string $commandClass): CommandHandlerInterface
    {
        if (!isset($this->handlers[$commandClass])) {
            throw new CommandHandlerNotFoundException($commandClass);
        }

        return $this->handlers[$commandClass];
    }

    /**
     * Check if a handler is registered for a command class.
     */
    public function has(string $commandClass): bool
    {
        return isset($this->handlers[$commandClass]);
    }

    /**
     * Get all registered command classes.
     */
    public function getRegisteredCommands(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Get statistics about registered handlers.
     */
    public function getStatistics(): array
    {
        return [
            'total_handlers' => count($this->handlers),
            'registered_commands' => $this->getRegisteredCommands(),
            'handler_types' => array_map(
                fn($handler) => get_class($handler),
                $this->handlers
            )
        ];
    }
}