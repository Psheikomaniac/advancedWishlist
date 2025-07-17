<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Query;

/**
 * Interface for all query handlers in the CQRS pattern.
 *
 * Query handlers are responsible for processing queries and retrieving
 * the requested data from the system.
 */
interface QueryHandlerInterface
{
    /**
     * Handle a query.
     *
     * @template T
     *
     * @param QueryInterface $query The query to handle
     *
     * @return mixed The result of handling the query
     */
    public function handle(QueryInterface $query): mixed;
}
