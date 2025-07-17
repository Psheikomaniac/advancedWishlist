<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Command;

use Shopware\Core\Framework\Context;

/**
 * Command to create a new wishlist.
 *
 * This is part of the CQRS pattern implementation for the Advanced Wishlist System.
 * Commands represent intentions to change the system state.
 */
class CreateWishlistCommand
{
    /**
     * @param string      $name        The name of the wishlist
     * @param string      $customerId  The ID of the customer who owns the wishlist
     * @param bool        $isPublic    Whether the wishlist is public or private
     * @param string|null $description Optional description of the wishlist
     * @param Context     $context     The Shopware context
     */
    public function __construct(
        public readonly string $name,
        public readonly string $customerId,
        public readonly bool $isPublic = false,
        public readonly ?string $description = null,
        public readonly Context $context,
    ) {
    }
}
