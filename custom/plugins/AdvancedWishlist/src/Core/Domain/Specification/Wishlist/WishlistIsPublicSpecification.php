<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Specification\Wishlist;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Domain\Specification\AbstractSpecification;

/**
 * Specification to check if a wishlist is public.
 */
class WishlistIsPublicSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(object $candidate): bool
    {
        if (!$candidate instanceof WishlistEntity) {
            return false;
        }

        return $candidate->getType() === 'public';
    }
}