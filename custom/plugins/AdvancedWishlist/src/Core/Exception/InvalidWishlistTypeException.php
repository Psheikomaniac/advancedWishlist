<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Exception;

use AdvancedWishlist\Core\Domain\ValueObject\WishlistType;

/**
 * Exception thrown when an invalid wishlist type is provided.
 */
class InvalidWishlistTypeException extends WishlistException
{
    /**
     * @param string $type The invalid wishlist type
     */
    public function __construct(string $type)
    {
        parent::__construct(
            sprintf('Invalid wishlist type: "%s". Must be one of: %s',
                $type,
                implode(', ', WishlistType::getValidTypes())
            ),
            ['type' => $type, 'validTypes' => WishlistType::getValidTypes()]
        );
    }
}
