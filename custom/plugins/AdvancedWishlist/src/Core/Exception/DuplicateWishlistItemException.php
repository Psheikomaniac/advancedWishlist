<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Exception;

class DuplicateWishlistItemException extends WishlistException
{
    public function __construct(string $message, array $parameters = [])
    {
        parent::__construct($message, $parameters);
    }

    public function getErrorCode(): string
    {
        return 'WISHLIST_ITEM__DUPLICATE_PRODUCT';
    }
}
