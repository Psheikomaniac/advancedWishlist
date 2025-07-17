<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Query\Wishlist;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Query model for retrieving wishlists for a customer
 * Part of the CQRS implementation for wishlist operations.
 */
final readonly class GetWishlistsQuery
{
    /**
     * @param string              $customerId The customer ID
     * @param Criteria            $criteria   The search criteria
     * @param SalesChannelContext $context    The context
     */
    public function __construct(
        public string $customerId,
        public Criteria $criteria,
        public SalesChannelContext $context,
    ) {
    }
}
