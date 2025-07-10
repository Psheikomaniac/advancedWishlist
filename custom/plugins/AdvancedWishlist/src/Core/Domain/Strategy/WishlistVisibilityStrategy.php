<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Strategy;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Framework\Context;

/**
 * Strategy interface for determining wishlist visibility
 */
interface WishlistVisibilityStrategy
{
    /**
     * Check if a user can view a wishlist
     * 
     * @param WishlistEntity $wishlist The wishlist to check
     * @param Context $context The context containing user information
     * @param string|null $customerId The customer ID, if available
     * @return bool True if the user can view the wishlist, false otherwise
     */
    public function canView(WishlistEntity $wishlist, Context $context, ?string $customerId = null): bool;
    
    /**
     * Check if a user can edit a wishlist
     * 
     * @param WishlistEntity $wishlist The wishlist to check
     * @param Context $context The context containing user information
     * @param string|null $customerId The customer ID, if available
     * @return bool True if the user can edit the wishlist, false otherwise
     */
    public function canEdit(WishlistEntity $wishlist, Context $context, ?string $customerId = null): bool;
    
    /**
     * Check if a user can share a wishlist
     * 
     * @param WishlistEntity $wishlist The wishlist to check
     * @param Context $context The context containing user information
     * @param string|null $customerId The customer ID, if available
     * @return bool True if the user can share the wishlist, false otherwise
     */
    public function canShare(WishlistEntity $wishlist, Context $context, ?string $customerId = null): bool;
    
    /**
     * Get the supported wishlist type for this strategy
     * 
     * @return string The wishlist type this strategy supports
     */
    public function getSupportedType(): string;
}