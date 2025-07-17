<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Query;

use Shopware\Core\Framework\Context;

/**
 * Query to retrieve a wishlist by its ID.
 *
 * This is part of the CQRS pattern implementation for the Advanced Wishlist System.
 * Queries represent read operations that do not change the system state.
 */
class GetWishlistQuery implements QueryInterface
{
    /**
     * @param string  $wishlistId The ID of the wishlist to retrieve
     * @param Context $context    The Shopware context
     */
    public function __construct(
        public readonly string $wishlistId,
        private readonly Context $context,
    ) {
    }

    /**
     * Get the context for the query.
     *
     * @return Context The Shopware context
     */
    public function getContext(): Context
    {
        return $this->context;
    }
}
