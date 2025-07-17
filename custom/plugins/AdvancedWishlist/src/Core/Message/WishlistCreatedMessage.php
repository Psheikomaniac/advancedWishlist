<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Message;

/**
 * Message dispatched when a wishlist is created
 * Used for async processing with Symfony Messenger.
 */
final readonly class WishlistCreatedMessage
{
    public function __construct(
        public string $wishlistId,
        public string $customerId,
        public \DateTimeInterface $createdAt = new \DateTime(),
    ) {
    }
}
