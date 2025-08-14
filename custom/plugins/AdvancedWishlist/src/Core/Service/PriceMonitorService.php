<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\Entity\WishlistItem\WishlistItemEntity;
use AdvancedWishlist\Core\Event\PriceDropDetectedEvent;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PriceMonitorService
{
    private const int BATCH_SIZE = 100;
    private const int CHECK_INTERVAL = 3600; // 1 hour

    public function __construct(
        private EntityRepository $wishlistItemRepository,
        private EntityRepository $productRepository,
        // private NotificationService $notificationService,
        // private PriceHistoryService $priceHistoryService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
        private OptimizedPriceCalculationService $priceCalculationService,
    ) {
    }

    /**
     * Check all active price alerts with optimized batch processing.
     */
    public function checkPriceAlerts(Context $context): array
    {
        $processed = 0;
        $triggered = 0;
        $offset = 0;

        do {
            // Get items with active price alerts (optimized query)
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('priceAlertActive', true));
            $criteria->addFilter(new RangeFilter('priceAlertThreshold', [
                RangeFilter::GT => 0,
            ]));
            // Eager load all needed associations in one query
            $criteria->addAssociation('product');
            $criteria->addAssociation('wishlist.customer');
            $criteria->setLimit(self::BATCH_SIZE);
            $criteria->setOffset($offset);

            $items = $this->wishlistItemRepository->search($criteria, $context);
            $itemsArray = $items->getElements();

            if (empty($itemsArray)) {
                break;
            }

            // Batch calculate prices for all items at once
            $batchStartTime = microtime(true);
            $pricesData = $this->priceCalculationService->calculateWishlistItemPrices($itemsArray, $context);
            $batchTime = microtime(true) - $batchStartTime;

            $this->logger->debug('Batch price calculation completed', [
                'items_count' => count($itemsArray),
                'execution_time_ms' => round($batchTime * 1000, 2),
                'prices_calculated' => count($pricesData)
            ]);

            // Process each item with pre-calculated prices
            foreach ($itemsArray as $item) {
                try {
                    $productId = $item->getProductId();
                    $priceData = $pricesData[$productId] ?? null;
                    
                    if ($this->checkPriceDropOptimized($item, $priceData, $context)) {
                        ++$triggered;
                    }
                    ++$processed;
                } catch (\Exception $e) {
                    $this->logger->error('Failed to check price alert', [
                        'itemId' => $item->getId(),
                        'productId' => $item->getProductId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $offset += self::BATCH_SIZE;
        } while (self::BATCH_SIZE === $items->count());

        $this->logger->info('Price alerts checked', [
            'processed' => $processed,
            'triggered' => $triggered,
        ]);

        return [
            'processed' => $processed,
            'triggered' => $triggered,
        ];
    }

    /**
     * Check single item for price drop (legacy method - maintained for compatibility).
     */
    public function checkPriceDrop(
        WishlistItemEntity $item,
        Context $context,
    ): bool {
        $product = $item->getProduct();
        if (!$product) {
            return false;
        }

        $currentPrice = $this->getCurrentPrice($product, $context);
        $threshold = $item->getPriceAlertThreshold();

        // Check if price dropped below threshold
        if ($currentPrice >= $threshold) {
            return false;
        }

        // Check if we already notified recently
        if ($this->wasRecentlyNotified($item->getId())) {
            return false;
        }

        return $this->processPriceDropNotification($item, $currentPrice, $threshold, $context);
    }

    /**
     * Optimized version using pre-calculated price data.
     */
    private function checkPriceDropOptimized(
        WishlistItemEntity $item,
        ?array $priceData,
        Context $context,
    ): bool {
        if (!$priceData || !isset($priceData['gross_price'])) {
            $this->logger->warning('No price data available for product', [
                'itemId' => $item->getId(),
                'productId' => $item->getProductId()
            ]);
            return false;
        }

        $currentPrice = $priceData['gross_price'];
        $threshold = $item->getPriceAlertThreshold();

        // Check if price dropped below threshold
        if ($currentPrice >= $threshold) {
            return false;
        }

        // Check if we already notified recently
        if ($this->wasRecentlyNotified($item->getId())) {
            return false;
        }

        return $this->processPriceDropNotification($item, $currentPrice, $threshold, $context);
    }

    /**
     * Process price drop notification (shared logic).
     */
    private function processPriceDropNotification(
        WishlistItemEntity $item,
        float $currentPrice,
        float $threshold,
        Context $context
    ): bool {
        // Calculate savings
        $savings = $threshold - $currentPrice;
        $savingsPercentage = ($savings / $threshold) * 100;

        // Send notification
        // $this->notificationService->sendPriceAlert(
        //     $item->getWishlist()->getCustomer(),
        //     $item,
        //     $currentPrice,
        //     $savings,
        //     $savingsPercentage,
        //     $context
        // );

        // Record notification
        $this->recordNotification($item->getId(), $currentPrice);

        // Track price history
        // $this->priceHistoryService->recordPrice(
        //     $item->getProduct()->getId(),
        //     $currentPrice,
        //     $context
        // );

        // Dispatch event
        $event = new PriceDropDetectedEvent($item, $threshold, $currentPrice, $context);
        $this->eventDispatcher->dispatch($event);

        $this->logger->info('Price drop detected', [
            'itemId' => $item->getId(),
            'productId' => $item->getProductId(),
            'threshold' => $threshold,
            'currentPrice' => $currentPrice,
            'savings' => $savings,
            'savingsPercentage' => round($savingsPercentage, 2)
        ]);

        return true;
    }

    /**
     * Setup price alert for item.
     */
    public function setupAlert(
        string $itemId,
        ProductEntity $product,
        float $threshold,
        Context $context,
    ): void {
        // Validate threshold
        $currentPrice = $this->getCurrentPrice($product, $context);

        if ($threshold <= 0) {
            throw new \InvalidArgumentException('Threshold must be greater than 0');
        }

        if ($threshold <= $currentPrice) {
            throw new \InvalidArgumentException('Threshold must be higher than current price');
        }

        // Update item
        $this->wishlistItemRepository->update([
            [
                'id' => $itemId,
                'priceAlertThreshold' => $threshold,
                'priceAlertActive' => true,
                'priceAtAlert' => $currentPrice,
            ],
        ], $context);

        // Record initial price
        // $this->priceHistoryService->recordPrice(
        //     $product->getId(),
        //     $currentPrice,
        //     $context
        // );

        $this->logger->info('Price alert setup', [
            'itemId' => $itemId,
            'productId' => $product->getId(),
            'threshold' => $threshold,
            'currentPrice' => $currentPrice,
        ]);
    }

    /**
     * Get price statistics for product.
     */
    public function getPriceStatistics(
        string $productId,
        \DateTimeInterface $since,
        Context $context,
    ): array {
        // $history = $this->priceHistoryService->getHistory(
        //     $productId,
        //     $since,
        //     new \DateTime(),
        //     $context
        // );

        // if (empty($history)) {
        return [
            'current' => 0,
            'min' => 0,
            'max' => 0,
            'average' => 0,
            'trend' => 'stable',
            'volatility' => 0,
        ];
        // }

        // $prices = array_column($history, 'price');
        // $current = array_last($prices);
        // $min = min($prices);
        // $max = max($prices);
        // $average = array_sum($prices) / count($prices);

        // // Calculate trend
        // $firstHalf = array_slice($prices, 0, floor(count($prices) / 2));
        // $secondHalf = array_slice($prices, floor(count($prices) / 2));

        // $firstAvg = array_sum($firstHalf) / count($firstHalf);
        // $secondAvg = array_sum($secondHalf) / count($secondHalf);

        // $trend = 'stable';
        // if ($secondAvg > $firstAvg * 1.05) {
        //     $trend = 'up';
        // } elseif ($secondAvg < $firstAvg * 0.95) {
        //     $trend = 'down';
        // }

        // // Calculate volatility
        // $variance = 0;
        // foreach ($prices as $price) {
        //     $variance += pow($price - $average, 2);
        // }
        // $variance /= count($prices);
        // $volatility = sqrt($variance) / $average * 100;

        // return [
        //     'current' => $current,
        //     'min' => $min,
        //     'max' => $max,
        //     'average' => round($average, 2),
        //     'trend' => $trend,
        //     'volatility' => round($volatility, 2),
        //     'history' => $history,
        // ];
    }

    /**
     * Helper: Get current price for product.
     */
    private function getCurrentPrice(
        ProductEntity $product,
        Context $context,
    ): float {
        $price = $product->getCheapestPrice();

        if (!$price) {
            throw new \RuntimeException('No price found for product: '.$product->getId());
        }

        return $price->getGross();
    }

    /**
     * Helper: Check if recently notified.
     */
    private function wasRecentlyNotified(string $itemId): bool
    {
        $cacheKey = sprintf('price_alert.notified.%s', $itemId);

        return $this->cache->has($cacheKey);
    }

    /**
     * Helper: Record notification sent.
     */
    private function recordNotification(string $itemId, float $price): void
    {
        $cacheKey = sprintf('price_alert.notified.%s', $itemId);

        // Prevent duplicate notifications for 24 hours
        $this->cache->set($cacheKey, [
            'price' => $price,
            'timestamp' => time(),
        ], 86400);
    }

    /**
     * Batch setup price alerts for multiple items.
     */
    public function batchSetupAlerts(
        array $alertData,
        Context $context,
    ): array {
        if (empty($alertData)) {
            return ['successful' => 0, 'failed' => 0, 'results' => []];
        }

        // Extract product IDs for batch price calculation
        $productIds = array_column($alertData, 'productId');
        $mockItems = array_map(fn($id) => new class($id) {
            public function __construct(private string $productId) {}
            public function getProductId(): string { return $this->productId; }
        }, $productIds);

        // Batch calculate prices
        $pricesData = $this->priceCalculationService->calculateWishlistItemPrices($mockItems, $context);

        $successful = 0;
        $failed = 0;
        $results = [];
        $updateData = [];

        foreach ($alertData as $data) {
            $itemId = $data['itemId'];
            $productId = $data['productId'];
            $threshold = $data['threshold'];

            try {
                if ($threshold <= 0) {
                    throw new \InvalidArgumentException('Threshold must be greater than 0');
                }

                $currentPrice = $pricesData[$productId]['gross_price'] ?? null;
                if (!$currentPrice) {
                    throw new \RuntimeException('Could not determine current price');
                }

                if ($threshold <= $currentPrice) {
                    throw new \InvalidArgumentException(
                        sprintf('Threshold (%.2f) must be higher than current price (%.2f)', $threshold, $currentPrice)
                    );
                }

                $updateData[] = [
                    'id' => $itemId,
                    'priceAlertThreshold' => $threshold,
                    'priceAlertActive' => true,
                    'priceAtAlert' => $currentPrice,
                ];

                $results[] = [
                    'success' => true,
                    'itemId' => $itemId,
                    'productId' => $productId,
                    'threshold' => $threshold,
                    'currentPrice' => $currentPrice
                ];

                ++$successful;
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'itemId' => $itemId,
                    'productId' => $productId,
                    'error' => $e->getMessage()
                ];
                ++$failed;
            }
        }

        // Batch update all successful items
        if (!empty($updateData)) {
            $this->wishlistItemRepository->update($updateData, $context);
        }

        $this->logger->info('Batch price alert setup completed', [
            'total' => count($alertData),
            'successful' => $successful,
            'failed' => $failed
        ]);

        return [
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ];
    }
}
