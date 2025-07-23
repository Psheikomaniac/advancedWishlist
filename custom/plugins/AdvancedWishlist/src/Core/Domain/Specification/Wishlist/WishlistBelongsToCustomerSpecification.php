<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Specification\Wishlist;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Domain\Specification\AbstractSpecification;
use AdvancedWishlist\Core\Domain\ValueObject\CustomerId;

/**
 * Specification to check if a wishlist belongs to a specific customer.
 */
class WishlistBelongsToCustomerSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly CustomerId $customerId
    ) {
    }

    public function isSatisfiedBy(object $candidate): bool
    {
        if (!$candidate instanceof WishlistEntity) {
            return false;
        }

        $wishlistCustomerId = CustomerId::fromString($candidate->getCustomerId());
        return $this->customerId->equals($wishlistCustomerId);
    }
}