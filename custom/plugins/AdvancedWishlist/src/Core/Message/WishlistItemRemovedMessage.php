<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message for handling wishlist item removal asynchronously.
 */
class WishlistItemRemovedMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $wishlistId,
        private readonly string $productId,
        private readonly string $customerId,
        private readonly Context $context,
        private readonly array $metadata = [],
        private readonly string $messageId = null
    ) {
        $this->messageId = $messageId ?? uniqid('wishlist_item_removed_', true);
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getId(): string
    {
        return $this->messageId;
    }

    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'wishlist_id' => $this->wishlistId,
            'product_id' => $this->productId,
            'customer_id' => $this->customerId,
            'metadata' => $this->metadata,
            'created_at' => time()
        ];
    }
}