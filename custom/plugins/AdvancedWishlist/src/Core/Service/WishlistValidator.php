<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\Exception\WishlistException;
use AdvancedWishlist\Core\Exception\WishlistNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class WishlistValidator
{
    /**
     * Validates a create wishlist request
     */
    public function validateCreateRequest(
        CreateWishlistRequest $request,
        Context $context
    ): void {
        // Validate required fields
        if (empty($request->getName())) {
            throw new WishlistException(
                'Wishlist name is required',
                ['field' => 'name']
            );
        }

        // Validate name length
        if (mb_strlen($request->getName()) > 255) {
            throw new WishlistException(
                'Wishlist name cannot exceed 255 characters',
                ['field' => 'name', 'maxLength' => 255]
            );
        }

        // Validate type
        if (!in_array($request->getType(), ['private', 'public', 'shared'], true)) {
            throw new WishlistException(
                'Invalid wishlist type. Must be one of: private, public, shared',
                ['field' => 'type', 'allowedValues' => ['private', 'public', 'shared']]
            );
        }

        // Validate customer ID
        if (empty($request->getCustomerId())) {
            throw new WishlistException(
                'Customer ID is required',
                ['field' => 'customerId']
            );
        }
    }

    /**
     * Validates an update wishlist request
     */
    public function validateUpdateRequest(
        UpdateWishlistRequest $request,
        WishlistEntity $wishlist,
        Context $context
    ): void {
        // Validate wishlist ID
        if (empty($request->getWishlistId())) {
            throw new WishlistException(
                'Wishlist ID is required',
                ['field' => 'wishlistId']
            );
        }

        // Validate name length if provided
        $name = $request->getName();
        if ($name !== null && mb_strlen($name) > 255) {
            throw new WishlistException(
                'Wishlist name cannot exceed 255 characters',
                ['field' => 'name', 'maxLength' => 255]
            );
        }

        // Validate type if provided
        $type = $request->getType();
        if ($type !== null && !in_array($type, ['private', 'public', 'shared'], true)) {
            throw new WishlistException(
                'Invalid wishlist type. Must be one of: private, public, shared',
                ['field' => 'type', 'allowedValues' => ['private', 'public', 'shared']]
            );
        }
    }

    /**
     * Validates ownership of a wishlist
     */
    public function validateOwnership(
        WishlistEntity $wishlist,
        Context $context
    ): void {
        // Get customer ID from context
        $customerId = $this->getCustomerIdFromContext($context);
        
        // Check if the wishlist belongs to the customer
        if ($wishlist->getCustomerId() !== $customerId) {
            throw new WishlistException(
                'You do not have permission to access this wishlist',
                ['wishlistId' => $wishlist->getId()]
            );
        }
    }

    /**
     * Validates if a user can view a wishlist
     */
    public function canViewWishlist(
        WishlistEntity $wishlist,
        Context $context
    ): bool {
        // Get customer ID from context
        $customerId = $this->getCustomerIdFromContext($context);
        
        // Owner can always view
        if ($wishlist->getCustomerId() === $customerId) {
            return true;
        }
        
        // Public wishlists can be viewed by anyone
        if ($wishlist->getType() === 'public') {
            return true;
        }
        
        // Check if the wishlist is shared with the customer
        if ($wishlist->getType() === 'shared' && $wishlist->getShareInfo()) {
            foreach ($wishlist->getShareInfo() as $share) {
                if ($share->getRecipientId() === $customerId) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Helper method to get customer ID from context
     */
    private function getCustomerIdFromContext(Context $context): ?string
    {
        if ($context instanceof SalesChannelContext && $context->getCustomer()) {
            return $context->getCustomer()->getId();
        }
        
        return null;
    }
}