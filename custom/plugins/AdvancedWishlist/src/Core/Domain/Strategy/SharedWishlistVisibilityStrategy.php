<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Strategy;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Domain\ValueObject\WishlistType;
use Shopware\Core\Framework\Context;

/**
 * Strategy for determining visibility of shared wishlists.
 */
class SharedWishlistVisibilityStrategy implements WishlistVisibilityStrategy
{
    public function canView(WishlistEntity $wishlist, Context $context, ?string $customerId = null): bool
    {
        // Shared wishlists can be viewed by their owner or users with whom it's shared
        if ($this->isOwner($wishlist, $customerId)) {
            return true;
        }

        return $this->isSharedWithUser($wishlist, $customerId);
    }

    public function canEdit(WishlistEntity $wishlist, Context $context, ?string $customerId = null): bool
    {
        // Shared wishlists can only be edited by their owner
        return $this->isOwner($wishlist, $customerId);
    }

    public function canShare(WishlistEntity $wishlist, Context $context, ?string $customerId = null): bool
    {
        // Shared wishlists can be shared by their owner or users with whom it's shared
        if ($this->isOwner($wishlist, $customerId)) {
            return true;
        }

        return $this->isSharedWithUser($wishlist, $customerId);
    }

    public function getSupportedType(): string
    {
        return WishlistType::SHARED;
    }

    /**
     * Check if the given customer is the owner of the wishlist.
     *
     * @param WishlistEntity $wishlist   The wishlist to check
     * @param string|null    $customerId The customer ID to check
     *
     * @return bool True if the customer is the owner, false otherwise
     */
    private function isOwner(WishlistEntity $wishlist, ?string $customerId): bool
    {
        if (null === $customerId) {
            return false;
        }

        return $wishlist->getCustomerId() === $customerId;
    }

    /**
     * Check if the wishlist is shared with the given user.
     *
     * @param WishlistEntity $wishlist   The wishlist to check
     * @param string|null    $customerId The customer ID to check
     *
     * @return bool True if the wishlist is shared with the user, false otherwise
     */
    private function isSharedWithUser(WishlistEntity $wishlist, ?string $customerId): bool
    {
        if (null === $customerId || null === $wishlist->getShareInfo()) {
            return false;
        }

        foreach ($wishlist->getShareInfo() as $share) {
            if ($share->getRecipientId() === $customerId) {
                return true;
            }
        }

        return false;
    }
}
