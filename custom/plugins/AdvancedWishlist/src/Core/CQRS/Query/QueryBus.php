<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Query;

use Psr\Container\ContainerInterface;

/**
 * Query bus for executing queries in the CQRS pattern.
 *
 * The query bus is responsible for finding the appropriate handler for a given query
 * and executing it. It acts as a mediator between the query and its handler.
 */
class QueryBus
{
    /**
     * @param ContainerInterface $container Service container for resolving handlers
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Dispatch a query to its handler.
     *
     * @template T
     *
     * @param QueryInterface $query The query to dispatch
     *
     * @return mixed The result of handling the query
     *
     * @throws \InvalidArgumentException If no handler is found for the query
     */
    public function dispatch(QueryInterface $query): mixed
    {
        $handlerClass = $this->getHandlerClass($query);

        if (!$this->container->has($handlerClass)) {
            throw new \InvalidArgumentException(sprintf('No handler found for query %s', get_class($query)));
        }

        $handler = $this->container->get($handlerClass);

        if (!$handler instanceof QueryHandlerInterface) {
            throw new \InvalidArgumentException(sprintf('Handler %s must implement %s', get_class($handler), QueryHandlerInterface::class));
        }

        return $handler->handle($query);
    }

    /**
     * Get the handler class for a query.
     *
     * @param QueryInterface $query The query
     *
     * @return string The fully qualified class name of the handler
     */
    private function getHandlerClass(QueryInterface $query): string
    {
        $queryClass = get_class($query);
        $handlerClass = $queryClass.'Handler';

        return $handlerClass;
    }
}
