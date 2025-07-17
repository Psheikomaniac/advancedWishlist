<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Message\Handler;

use AdvancedWishlist\Core\Message\WishlistCreatedMessage;
use AdvancedWishlist\Core\Service\WishlistCacheService;
use AdvancedWishlist\Service\AnalyticsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for WishlistCreatedMessage
 * Processes wishlist creation asynchronously.
 */
#[AsMessageHandler]
final class WishlistCreatedHandler
{
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly WishlistCacheService $cacheService,
        private readonly AnalyticsService $analyticsService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(WishlistCreatedMessage $message): void
    {
        $context = Context::createDefaultContext();

        try {
            // Warm up cache for the new wishlist
            $this->cacheService->invalidateCustomerCache($message->customerId);

            // Track analytics
            $this->analyticsService->trackWishlistCreation(
                $message->wishlistId,
                $message->customerId,
                $context
            );

            $this->logger->info('Processed wishlist creation asynchronously', [
                'wishlistId' => $message->wishlistId,
                'customerId' => $message->customerId,
                'createdAt' => $message->createdAt->format('c'),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to process wishlist creation', [
                'wishlistId' => $message->wishlistId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
