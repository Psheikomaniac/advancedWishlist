<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Command;

use Psr\Log\LoggerInterface;

/**
 * Enhanced command bus with middleware pipeline support.
 * Implements the Chain of Responsibility pattern for cross-cutting concerns.
 */
class EnhancedCommandBus implements CommandBusInterface
{
    /**
     * @var CommandMiddleware[]
     */
    private array $middlewares = [];

    public function __construct(
        private readonly CommandHandlerRegistry $handlerRegistry,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Add middleware to the command pipeline.
     */
    public function addMiddleware(CommandMiddleware $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Dispatch command through middleware pipeline to handler.
     */
    public function dispatch(object $command): mixed
    {
        $commandClass = get_class($command);

        if (!$this->handlerRegistry->has($commandClass)) {
            $this->logger->error('No handler registered for command', [
                'command' => $commandClass,
                'registered_commands' => $this->handlerRegistry->getRegisteredCommands()
            ]);

            throw new \InvalidArgumentException(
                "No handler registered for command: {$commandClass}"
            );
        }

        // Build middleware pipeline
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn($next, $middleware) => fn($cmd) => $middleware->handle($cmd, $next),
            fn($cmd) => $this->executeCommand($cmd)
        );

        return $pipeline($command);
    }

    /**
     * Execute the command with its registered handler.
     */
    private function executeCommand(object $command): mixed
    {
        $commandClass = get_class($command);
        $handler = $this->handlerRegistry->get($commandClass);

        $this->logger->debug('Executing command with handler', [
            'command' => $commandClass,
            'handler' => get_class($handler)
        ]);

        return $handler->handle($command);
    }

    /**
     * Get middleware statistics.
     */
    public function getMiddlewareStatistics(): array
    {
        return [
            'total_middlewares' => count($this->middlewares),
            'middleware_chain' => array_map(
                fn($middleware) => get_class($middleware),
                $this->middlewares
            )
        ];
    }

    /**
     * Get handler registry statistics.
     */
    public function getHandlerStatistics(): array
    {
        return $this->handlerRegistry->getStatistics();
    }
}