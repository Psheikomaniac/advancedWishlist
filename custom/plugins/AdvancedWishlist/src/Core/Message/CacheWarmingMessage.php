<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message for handling cache warming asynchronously.
 */
class CacheWarmingMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $strategy,
        private readonly Context $context,
        private readonly ?string $customerId = null,
        private readonly array $options = [],
        private readonly string $messageId = null
    ) {
        $this->messageId = $messageId ?? uniqid('cache_warming_', true);
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getId(): string
    {
        return $this->messageId;
    }

    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'strategy' => $this->strategy,
            'customer_id' => $this->customerId,
            'options' => $this->options,
            'created_at' => time()
        ];
    }
}