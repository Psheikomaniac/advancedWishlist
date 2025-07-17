<?php

declare(strict_types=1);

namespace AdvancedWishlist\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

class NotificationService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function sendPriceAlertNotification(string $productId, float $oldPrice, float $newPrice, Context $context): void
    {
        // TODO: Implement actual notification logic (e.g., email, in-app notification)
        $this->logger->info(
            sprintf(
                'Price alert for product %s: Price changed from %f to %f',
                $productId,
                $oldPrice,
                $newPrice
            )
        );
    }
}
