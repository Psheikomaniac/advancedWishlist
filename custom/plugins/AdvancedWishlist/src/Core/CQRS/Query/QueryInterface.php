<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Query;

/**
 * Interface for all queries in the CQRS pattern.
 * 
 * Queries represent read operations that do not change the system state.
 * They are used to retrieve data from the system.
 */
interface QueryInterface
{
    /**
     * Get the context for the query.
     * 
     * @return mixed The context for the query
     */
    public function getContext(): mixed;
}