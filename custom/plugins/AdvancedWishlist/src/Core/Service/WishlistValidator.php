<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Framework\Context;

class WishlistValidator
{
    public function validateCreateRequest(CreateWishlistRequest $request, Context $context): void
    {
        // Placeholder for validation logic
    }

    public function validateOwnership(WishlistEntity $wishlist, Context $context): void
    {
        // Placeholder for ownership validation
    }

    public function validateUpdateRequest(UpdateWishlistRequest $request, WishlistEntity $wishlist, Context $context): void
    {
        // Placeholder for update request validation
    }
}
