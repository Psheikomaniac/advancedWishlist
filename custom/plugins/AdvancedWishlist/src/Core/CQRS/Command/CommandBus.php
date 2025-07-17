<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Command;

use Psr\Container\ContainerInterface;

/**
 * Command bus for executing commands in the CQRS pattern.
 *
 * The command bus is responsible for finding the appropriate handler for a given command
 * and executing it. It acts as a mediator between the command and its handler.
 */
class CommandBus
{
    /**
     * @param ContainerInterface $container Service container for resolving handlers
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Dispatch a command to its handler.
     *
     * @template T
     *
     * @param object $command The command to dispatch
     *
     * @return mixed The result of handling the command, if any
     *
     * @throws \InvalidArgumentException If no handler is found for the command
     */
    public function dispatch(object $command): mixed
    {
        $handlerClass = $this->getHandlerClass($command);

        if (!$this->container->has($handlerClass)) {
            throw new \InvalidArgumentException(sprintf('No handler found for command %s', get_class($command)));
        }

        $handler = $this->container->get($handlerClass);

        if (!$handler instanceof CommandHandlerInterface) {
            throw new \InvalidArgumentException(sprintf('Handler %s must implement %s', get_class($handler), CommandHandlerInterface::class));
        }

        return $handler->handle($command);
    }

    /**
     * Get the handler class for a command.
     *
     * @param object $command The command
     *
     * @return string The fully qualified class name of the handler
     */
    private function getHandlerClass(object $command): string
    {
        $commandClass = get_class($command);
        $handlerClass = $commandClass.'Handler';

        return $handlerClass;
    }
}
