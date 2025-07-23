<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message for handling analytics updates asynchronously.
 */
class AnalyticsUpdateMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $wishlistId,
        private readonly string $eventType,
        private readonly Context $context,
        private readonly array $metadata = [],
        private readonly string $messageId = null
    ) {
        $this->messageId = $messageId ?? uniqid('analytics_update_', true);
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
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
            'event_type' => $this->eventType,
            'metadata' => $this->metadata,
            'created_at' => time()
        ];
    }
}