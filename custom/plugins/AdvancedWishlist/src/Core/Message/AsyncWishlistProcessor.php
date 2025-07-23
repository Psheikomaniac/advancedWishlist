<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Message;

use AdvancedWishlist\Core\Cache\MultiLevelCacheService;
use AdvancedWishlist\Core\Cache\CacheWarmingService;
use AdvancedWishlist\Core\Performance\PerformanceMonitorService;
use AdvancedWishlist\Core\Service\OptimizedPriceCalculationService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Asynchronous processing system for wishlist operations.
 * Implements background job processing to improve response times and handle expensive operations.
 */
class AsyncWishlistProcessor implements MessageHandlerInterface
{
    public function __construct(
        private readonly MultiLevelCacheService $cacheService,
        private readonly CacheWarmingService $cacheWarmingService,
        private readonly OptimizedPriceCalculationService $priceCalculationService,
        private readonly PerformanceMonitorService $performanceMonitor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Handle wishlist item added message.
     */
    public function handleWishlistItemAdded(WishlistItemAddedMessage $message): void
    {
        $this->performanceMonitor->trackOperation('async_wishlist_item_added', function() use ($message) {
            try {
                // Invalidate related caches
                $this->invalidateWishlistCaches($message->getWishlistId(), $message->getCustomerId());
                
                // Pre-calculate prices for the new item
                $this->preCalculateItemPrices([$message->getProductId()], $message->getContext());
                
                // Warm related product data
                $this->warmRelatedProductData($message->getProductId(), $message->getContext());
                
                // Update analytics
                $this->updateWishlistAnalytics($message->getWishlistId(), 'item_added', $message->getContext());
                
                $this->logger->info('Wishlist item added processed asynchronously', [
                    'wishlist_id' => $message->getWishlistId(),
                    'product_id' => $message->getProductId(),
                    'customer_id' => $message->getCustomerId()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to process wishlist item added', [
                    'message' => $message->toArray(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                throw $e; // Re-throw to trigger message retry
            }
        });
    }

    /**
     * Handle wishlist item removed message.
     */
    public function handleWishlistItemRemoved(WishlistItemRemovedMessage $message): void
    {
        $this->performanceMonitor->trackOperation('async_wishlist_item_removed', function() use ($message) {
            try {
                // Invalidate related caches
                $this->invalidateWishlistCaches($message->getWishlistId(), $message->getCustomerId());
                
                // Clear cached prices for removed item
                $this->clearItemPriceCache($message->getProductId(), $message->getContext());
                
                // Update analytics
                $this->updateWishlistAnalytics($message->getWishlistId(), 'item_removed', $message->getContext());
                
                $this->logger->info('Wishlist item removed processed asynchronously', [
                    'wishlist_id' => $message->getWishlistId(),
                    'product_id' => $message->getProductId(),
                    'customer_id' => $message->getCustomerId()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to process wishlist item removed', [
                    'message' => $message->toArray(),
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Handle batch price calculation message.
     */
    public function handleBatchPriceCalculation(BatchPriceCalculationMessage $message): void
    {
        $this->performanceMonitor->trackOperation('async_batch_price_calculation', function() use ($message) {
            try {
                $productIds = $message->getProductIds();
                $context = $message->getContext();
                
                // Calculate prices in batches to avoid memory issues
                $batches = array_chunk($productIds, 50);
                $allPrices = [];
                
                foreach ($batches as $batch) {
                    $batchPrices = $this->priceCalculationService->calculateWishlistItemPrices(
                        array_map(fn($id) => (object)['getProductId' => fn() => $id], $batch),
                        $context
                    );
                    
                    $allPrices = array_merge($allPrices, $batchPrices);
                }
                
                // Cache the calculated prices
                foreach ($allPrices as $productId => $priceData) {
                    $cacheKey = "product_prices:{$productId}:{$context->getCurrencyId()}";
                    $this->cacheService->set($cacheKey, $priceData, 900); // 15 minutes
                }
                
                $this->logger->info('Batch price calculation completed', [
                    'product_count' => count($productIds),
                    'prices_calculated' => count($allPrices),
                    'cache_key_pattern' => "product_prices:*:{$context->getCurrencyId()}"
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to process batch price calculation', [
                    'product_count' => count($message->getProductIds()),
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Handle cache warming message.
     */
    public function handleCacheWarming(CacheWarmingMessage $message): void
    {
        $this->performanceMonitor->trackOperation('async_cache_warming', function() use ($message) {
            try {
                $strategy = $message->getStrategy();
                $context = $message->getContext();
                
                switch ($strategy) {
                    case 'customer':
                        $result = $this->cacheWarmingService->warmCustomerCache(
                            $message->getCustomerId(), 
                            $context
                        );
                        break;
                        
                    case 'popular_products':
                        $result = $this->cacheWarmingService->warmPopularProductsCache($context);
                        break;
                        
                    case 'analytics':
                        $result = $this->cacheWarmingService->warmAnalyticsCache($context);
                        break;
                        
                    case 'full':
                        $result = $this->cacheWarmingService->warmCache();
                        break;
                        
                    default:
                        throw new \InvalidArgumentException("Unknown cache warming strategy: {$strategy}");
                }
                
                $this->logger->info('Cache warming completed asynchronously', [
                    'strategy' => $strategy,
                    'result' => $result
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to process cache warming', [
                    'strategy' => $message->getStrategy(),
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Handle analytics update message.
     */
    public function handleAnalyticsUpdate(AnalyticsUpdateMessage $message): void
    {
        $this->performanceMonitor->trackOperation('async_analytics_update', function() use ($message) {
            try {
                $this->updateWishlistAnalytics(
                    $message->getWishlistId(),
                    $message->getEventType(),
                    $message->getContext(),
                    $message->getMetadata()
                );
                
                $this->logger->info('Analytics updated asynchronously', [
                    'wishlist_id' => $message->getWishlistId(),
                    'event_type' => $message->getEventType(),
                    'metadata' => $message->getMetadata()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to process analytics update', [
                    'message' => $message->toArray(),
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Handle notification sending message.
     */
    public function handleNotificationSending(NotificationMessage $message): void
    {
        $this->performanceMonitor->trackOperation('async_notification_sending', function() use ($message) {
            try {
                // In a real implementation, this would integrate with email/push notification services
                $this->sendNotification(
                    $message->getRecipientId(),
                    $message->getType(),
                    $message->getSubject(),
                    $message->getBody(),
                    $message->getMetadata()
                );
                
                $this->logger->info('Notification sent asynchronously', [
                    'recipient_id' => $message->getRecipientId(),
                    'type' => $message->getType(),
                    'subject' => $message->getSubject()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send notification', [
                    'message' => $message->toArray(),
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Main message handler dispatcher.
     */
    public function __invoke(AsyncMessageInterface $message): void
    {
        $messageType = get_class($message);
        
        $this->logger->debug('Processing async message', [
            'message_type' => $messageType,
            'message_id' => method_exists($message, 'getId') ? $message->getId() : 'unknown'
        ]);
        
        try {
            match ($messageType) {
                WishlistItemAddedMessage::class => $this->handleWishlistItemAdded($message),
                WishlistItemRemovedMessage::class => $this->handleWishlistItemRemoved($message),
                BatchPriceCalculationMessage::class => $this->handleBatchPriceCalculation($message),
                CacheWarmingMessage::class => $this->handleCacheWarming($message),
                AnalyticsUpdateMessage::class => $this->handleAnalyticsUpdate($message),
                NotificationMessage::class => $this->handleNotificationSending($message),
                default => throw new \InvalidArgumentException("Unsupported message type: {$messageType}")
            };
        } catch (\Exception $e) {
            $this->logger->error('Async message processing failed', [
                'message_type' => $messageType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Invalidate wishlist-related caches.
     */
    private function invalidateWishlistCaches(string $wishlistId, string $customerId): void
    {
        $cacheKeys = [
            "wishlist:{$wishlistId}",
            "customer_wishlists:{$customerId}",
            "customer_default_wishlist:{$customerId}",
            "wishlist_statistics",
            "daily_wishlist_metrics:" . date('Y-m-d')
        ];
        
        foreach ($cacheKeys as $key) {
            $this->cacheService->delete($key);
        }
        
        $this->logger->debug('Wishlist caches invalidated', [
            'wishlist_id' => $wishlistId,
            'customer_id' => $customerId,
            'cache_keys' => $cacheKeys
        ]);
    }

    /**
     * Pre-calculate prices for items.
     */
    private function preCalculateItemPrices(array $productIds, Context $context): void
    {
        // Create mock wishlist items for price calculation
        $mockItems = array_map(function($productId) {
            return new class($productId) {
                public function __construct(private string $productId) {}
                public function getProductId(): string { return $this->productId; }
            };
        }, $productIds);
        
        $prices = $this->priceCalculationService->calculateWishlistItemPrices($mockItems, $context);
        
        // Cache the calculated prices
        foreach ($prices as $productId => $priceData) {
            $cacheKey = "product_prices:{$productId}:{$context->getCurrencyId()}";
            $this->cacheService->set($cacheKey, $priceData, 900); // 15 minutes
        }
        
        $this->logger->debug('Item prices pre-calculated', [
            'product_ids' => $productIds,
            'prices_count' => count($prices)
        ]);
    }

    /**
     * Warm related product data.
     */
    private function warmRelatedProductData(string $productId, Context $context): void
    {
        // In a real implementation, this would load related products, categories, etc.
        $relatedDataKey = "product_related_data:{$productId}";
        $relatedData = [
            'product_id' => $productId,
            'loaded_at' => time(),
            'related_products' => [], // Would be populated with actual data
            'categories' => [], // Would be populated with actual data
            'cross_sells' => [] // Would be populated with actual data
        ];
        
        $this->cacheService->set($relatedDataKey, $relatedData, 1800); // 30 minutes
        
        $this->logger->debug('Related product data warmed', [
            'product_id' => $productId,
            'cache_key' => $relatedDataKey
        ]);
    }

    /**
     * Clear item price cache.
     */
    private function clearItemPriceCache(string $productId, Context $context): void
    {
        $cacheKey = "product_prices:{$productId}:{$context->getCurrencyId()}";
        $this->cacheService->delete($cacheKey);
        
        $this->logger->debug('Item price cache cleared', [
            'product_id' => $productId,
            'cache_key' => $cacheKey
        ]);
    }

    /**
     * Update wishlist analytics.
     */
    private function updateWishlistAnalytics(string $wishlistId, string $eventType, Context $context, array $metadata = []): void
    {
        $analyticsData = [
            'wishlist_id' => $wishlistId,
            'event_type' => $eventType,
            'timestamp' => time(),
            'date' => date('Y-m-d'),
            'hour' => date('H'),
            'metadata' => $metadata
        ];
        
        // Store analytics data (in a real implementation, this would go to a dedicated analytics service)
        $analyticsKey = "analytics:wishlist:{$wishlistId}:" . date('Y-m-d-H');
        $existingData = $this->cacheService->get($analyticsKey, fn() => []);
        $existingData[] = $analyticsData;
        
        $this->cacheService->set($analyticsKey, $existingData, 3600); // 1 hour
        
        // Update daily statistics
        $dailyStatsKey = "daily_wishlist_stats:" . date('Y-m-d');
        $dailyStats = $this->cacheService->get($dailyStatsKey, fn() => [
            'total_events' => 0,
            'events_by_type' => []
        ]);
        
        $dailyStats['total_events']++;
        $dailyStats['events_by_type'][$eventType] = ($dailyStats['events_by_type'][$eventType] ?? 0) + 1;
        
        $this->cacheService->set($dailyStatsKey, $dailyStats, 86400); // 24 hours
        
        $this->logger->debug('Wishlist analytics updated', [
            'wishlist_id' => $wishlistId,
            'event_type' => $eventType,
            'analytics_key' => $analyticsKey
        ]);
    }

    /**
     * Send notification (placeholder implementation).
     */
    private function sendNotification(string $recipientId, string $type, string $subject, string $body, array $metadata = []): void
    {
        // In a real implementation, this would integrate with email/push notification services
        $this->logger->info('Notification prepared for sending', [
            'recipient_id' => $recipientId,
            'type' => $type,
            'subject' => $subject,
            'metadata' => $metadata
        ]);
        
        // Placeholder for actual notification sending logic
        // This would typically integrate with services like:
        // - Symfony Mailer for email notifications
        // - Firebase Cloud Messaging for push notifications
        // - SMS services for text notifications
        // - Webhook services for third-party integrations
    }
}