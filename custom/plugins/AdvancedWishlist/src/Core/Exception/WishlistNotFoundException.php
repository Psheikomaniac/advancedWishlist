<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Exception;

class WishlistNotFoundException extends WishlistException
{
    public function __construct(string $message, array $parameters = [])
    {
        parent::__construct($message, $parameters);
    }

    public function getErrorCode(): string
    {
        return 'WISHLIST__NOT_FOUND';
    }
}
