<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Service;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Factory\WishlistVisibilityStrategyFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Service for determining wishlist visibility.
 */
class WishlistVisibilityService
{
    /**
     * @param WishlistVisibilityStrategyFactory $strategyFactory Factory for creating visibility strategies
     */
    public function __construct(
        private readonly WishlistVisibilityStrategyFactory $strategyFactory,
    ) {
    }

    /**
     * Check if a user can view a wishlist.
     *
     * @param WishlistEntity $wishlist   The wishlist to check
     * @param Context        $context    The context containing user information
     * @param string|null    $customerId The customer ID, if available
     *
     * @return bool True if the user can view the wishlist, false otherwise
     */
    public function canView(WishlistEntity $wishlist, Context $context, ?string $customerId = null): bool
    {
        $strategy = $this->strategyFactory->getStrategy($wishlist);

        return $strategy->canView($wishlist, $context, $customerId);
    }

    /**
     * Check if a user can edit a wishlist.
     *
     * @param WishlistEntity $wishlist   The wishlist to check
     * @param Context        $context    The context containing user information
     * @param string|null    $customerId The customer ID, if available
     *
     * @return bool True if the user can edit the wishlist, false otherwise
     */
    public function canEdit(WishlistEntity $wishlist, Context $context, ?string $customerId = null): bool
    {
        $strategy = $this->strategyFactory->getStrategy($wishlist);

        return $strategy->canEdit($wishlist, $context, $customerId);
    }

    /**
     * Check if a user can share a wishlist.
     *
     * @param WishlistEntity $wishlist   The wishlist to check
     * @param Context        $context    The context containing user information
     * @param string|null    $customerId The customer ID, if available
     *
     * @return bool True if the user can share the wishlist, false otherwise
     */
    public function canShare(WishlistEntity $wishlist, Context $context, ?string $customerId = null): bool
    {
        $strategy = $this->strategyFactory->getStrategy($wishlist);

        return $strategy->canShare($wishlist, $context, $customerId);
    }

    /**
     * Get the customer ID from the context.
     *
     * @param Context $context The context
     *
     * @return string|null The customer ID, or null if not available
     */
    public function getCustomerIdFromContext(Context $context): ?string
    {
        if ($context instanceof SalesChannelContext && $context->getCustomer()) {
            return $context->getCustomer()->getId();
        }

        return null;
    }

    /**
     * Check if the given customer is the owner of the wishlist.
     *
     * @param WishlistEntity $wishlist   The wishlist to check
     * @param string|null    $customerId The customer ID to check
     *
     * @return bool True if the customer is the owner, false otherwise
     */
    public function isOwner(WishlistEntity $wishlist, ?string $customerId): bool
    {
        if (null === $customerId) {
            return false;
        }

        return $wishlist->getCustomerId() === $customerId;
    }
}
