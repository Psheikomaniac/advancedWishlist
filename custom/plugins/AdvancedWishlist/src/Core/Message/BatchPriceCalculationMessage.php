<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message for handling batch price calculations asynchronously.
 */
class BatchPriceCalculationMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly array $productIds,
        private readonly Context $context,
        private readonly array $options = [],
        private readonly string $messageId = null
    ) {
        $this->messageId = $messageId ?? uniqid('batch_price_calc_', true);
    }

    public function getProductIds(): array
    {
        return $this->productIds;
    }

    public function getContext(): Context
    {
        return $this->context;
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
            'product_ids' => $this->productIds,
            'product_count' => count($this->productIds),
            'options' => $this->options,
            'created_at' => time()
        ];
    }
}