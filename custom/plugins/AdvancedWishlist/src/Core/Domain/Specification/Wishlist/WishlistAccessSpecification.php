<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Specification\Wishlist;

use AdvancedWishlist\Core\Domain\Specification\SpecificationInterface;
use AdvancedWishlist\Core\Domain\ValueObject\CustomerId;

/**
 * Factory for creating complex wishlist access specifications.
 * Demonstrates composition of specifications for business rules.
 */
class WishlistAccessSpecification
{
    /**
     * Create specification for checking if a customer can view a wishlist.
     * A customer can view a wishlist if:
     * - They own it, OR
     * - It's public, OR  
     * - It's shared with them
     */
    public static function canView(CustomerId $customerId): SpecificationInterface
    {
        return (new WishlistBelongsToCustomerSpecification($customerId))
            ->or(new WishlistIsPublicSpecification())
            ->or(new WishlistIsSharedWithCustomerSpecification($customerId));
    }

    /**
     * Create specification for checking if a customer can modify a wishlist.
     * A customer can modify a wishlist only if they own it.
     */
    public static function canModify(CustomerId $customerId): SpecificationInterface
    {
        return new WishlistBelongsToCustomerSpecification($customerId);
    }

    /**
     * Create specification for checking if a customer can delete a wishlist.
     * Same as modify - only the owner can delete.
     */
    public static function canDelete(CustomerId $customerId): SpecificationInterface
    {
        return new WishlistBelongsToCustomerSpecification($customerId);
    }

    /**
     * Create specification for checking if a customer can share a wishlist.
     * A customer can share a wishlist if they own it AND it's not already public.
     */
    public static function canShare(CustomerId $customerId): SpecificationInterface
    {
        return (new WishlistBelongsToCustomerSpecification($customerId))
            ->and((new WishlistIsPublicSpecification())->not());
    }

    /**
     * Create specification for checking if a wishlist is accessible by anyone.
     * This includes public wishlists or wishlists that have been shared.
     */
    public static function isAccessibleByAnyone(): SpecificationInterface
    {
        return new WishlistIsPublicSpecification();
        // Note: We could extend this to check if any shares exist, but for now public is sufficient
    }

    /**
     * Create specification for checking if a wishlist is private and unshared.
     * Useful for privacy audits or determining completely private wishlists.
     */
    public static function isCompletelyPrivate(): SpecificationInterface
    {
        return (new WishlistIsPublicSpecification())->not();
        // Note: This could be extended to also check for no shares
    }
}