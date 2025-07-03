# Price Monitoring Feature

## Overview

The Price Monitoring Feature monitors price changes for wishlist products and automatically notifies customers about price drops. It offers detailed price histories and analyses.

## User Stories

### As a customer, I want to...
1. **Set price alerts** for individual products
2. **Receive notifications** when the price falls below my threshold
3. **View price history** for my wishlist products
4. **Understand price trends** with visualizations
5. **Manage alerts** and adjust them

### As a shop owner, I want to...
1. **Increase conversion** through targeted price notifications
2. **Analyze customer behavior** regarding price sensitivity
3. **Automate** notifications
4. **Optimize performance** with many price alerts

## Technical Implementation

### Price Alert Service

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service\PriceMonitoring;

use AdvancedWishlist\Core\Entity\PriceAlert\PriceAlertEntity;
use AdvancedWishlist\Core\Event\PriceDropDetectedEvent;
use AdvancedWishlist\Core\Event\PriceAlertTriggeredEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class PriceAlertService
{
    private const BATCH_SIZE = 100;
    private const MIN_PRICE_CHANGE = 0.01;
    private const NOTIFICATION_COOLDOWN = 86400; // 24 hours
    
    public function __construct(
        private EntityRepository $priceAlertRepository,
        private EntityRepository $priceHistoryRepository,
        private EntityRepository $productRepository,
        private PriceCalculationService $priceCalculationService,
        private NotificationService $notificationService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private CacheInterface $cache
    ) {}
    
    /**
     * Create or update price alert for wishlist item
     */
    public function createPriceAlert(
        string $wishlistItemId,
        string $productId,
        string $customerId,
        float $targetPrice,
        array $options = [],
        Context $context
    ): PriceAlertEntity {
        // Validate target price
        $currentPrice = $this->getCurrentPrice($productId, $context);
        
        if ($targetPrice <= 0) {
            throw new \InvalidArgumentException('Target price must be greater than 0');
        }
        
        if ($targetPrice >= $currentPrice) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Target price (%.2f) must be lower than current price (%.2f)',
                    $targetPrice,
                    $currentPrice
                )
            );
        }
        
        // Check if alert already exists
        $existingAlert = $this->findAlertForItem($wishlistItemId, $context);
        
        if ($existingAlert) {
            // Update existing alert
            return $this->updatePriceAlert($existingAlert->getId(), [
                'targetPrice' => $targetPrice,
                'options' => array_merge($existingAlert->getOptions() ?? [], $options),
            ], $context);
        }
        
        // Create new alert
        $alertId = Uuid::randomHex();
        $alertData = [
            'id' => $alertId,
            'wishlistItemId' => $wishlistItemId,
            'productId' => $productId,
            'customerId' => $customerId,
            'targetPrice' => $targetPrice,
            'currentPrice' => $currentPrice,
            'priceDropPercentage' => (($currentPrice - $targetPrice) / $currentPrice) * 100,
            'active' => true,
            'options' => array_merge([
                'notifyOnAnyDrop' => false,
                'includeShipping' => false,
                'considerVariants' => true,
            ], $options),
            'createdAt' => new \DateTime(),
        ];
        
        $this->priceAlertRepository->create([$alertData], $context);
        
        // Record initial price
        $this->recordPriceHistory($productId, $currentPrice, $context);
        
        $this->logger->info('Price alert created', [
            'alertId' => $alertId,
            'productId' => $productId,
            'targetPrice' => $targetPrice,
            'currentPrice' => $currentPrice,
        ]);
        
        return $this->loadPriceAlert($alertId, $context);
    }
    
    /**
     * Check all active price alerts
     */
    public function checkAllAlerts(Context $context): array
    {
        $stats = [
            'total' => 0,
            'checked' => 0,
            'triggered' => 0,
            'errors' => 0,
            'startTime' => microtime(true),
        ];
        
        $offset = 0;
        
        do {
            // Get batch of active alerts
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('active', true));
            $criteria->addAssociation('product.prices');
            $criteria->addAssociation('wishlistItem.wishlist.customer');
            $criteria->setLimit(self::BATCH_SIZE);
            $criteria->setOffset($offset);
            
            $alerts = $this->priceAlertRepository->search($criteria, $context);
            $stats['total'] += $alerts->count();
            
            foreach ($alerts as $alert) {
                try {
                    if ($this->checkAlert($alert, $context)) {
                        $stats['triggered']++;
                    }
                    $stats['checked']++;
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $this->logger->error('Failed to check price alert', [
                        'alertId' => $alert->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            $offset += self::BATCH_SIZE;
            
        } while ($alerts->count() === self::BATCH_SIZE);
        
        $stats['duration'] = microtime(true) - $stats['startTime'];
        
        $this->logger->info('Price alert check completed', $stats);
        
        return $stats;
    }
    
    /**
     * Check single price alert
     */
    public function checkAlert(PriceAlertEntity $alert, Context $context): bool
    {
        // Get current price
        $currentPrice = $this->getCurrentPrice($alert->getProductId(), $context);
        
        // Check if price changed
        if (abs($currentPrice - $alert->getCurrentPrice()) < self::MIN_PRICE_CHANGE) {
            return false; // No significant change
        }
        
        // Update current price
        $this->priceAlertRepository->update([
            [
                'id' => $alert->getId(),
                'currentPrice' => $currentPrice,
                'lastCheckedAt' => new \DateTime(),
            ]
        ], $context);
        
        // Record price history
        $this->recordPriceHistory($alert->getProductId(), $currentPrice, $context);
        
        // Check if target reached
        if ($currentPrice <= $alert->getTargetPrice()) {
            return $this->triggerAlert($alert, $currentPrice, $context);
        }
        
        // Check if should notify on any drop
        if ($alert->getOptions()['notifyOnAnyDrop'] ?? false) {
            if ($currentPrice < $alert->getCurrentPrice()) {
                return $this->triggerPriceDropNotification($alert, $currentPrice, $context);
            }
        }
        
        return false;
    }
    
    /**
     * Trigger price alert notification
     */
    private function triggerAlert(
        PriceAlertEntity $alert,
        float $newPrice,
        Context $context
    ): bool {
        // Check cooldown
        if (!$this->canSendNotification($alert->getId())) {
            return false;
        }
        
        // Calculate savings
        $savings = $alert->getCurrentPrice() - $newPrice;
        $savingsPercentage = ($savings / $alert->getCurrentPrice()) * 100;
        
        // Load related data
        $wishlistItem = $alert->getWishlistItem();
        $customer = $wishlistItem->getWishlist()->getCustomer();
        $product = $alert->getProduct();
        
        // Send notification
        $this->notificationService->sendPriceAlert(
            $customer,
            $wishlistItem,
            $newPrice,
            $savings,
            $savingsPercentage,
            $context
        );
        
        // Update alert stats
        $this->priceAlertRepository->update([
            [
                'id' => $alert->getId(),
                'triggeredCount' => $alert->getTriggeredCount() + 1,
                'lastTriggeredAt' => new \DateTime(),
                'lowestPrice' => min($alert->getLowestPrice() ?? PHP_FLOAT_MAX, $newPrice),
            ]
        ], $context);
        
        // Record notification sent
        $this->recordNotificationSent($alert->getId());
        
        // Dispatch event
        $event = new PriceAlertTriggeredEvent($alert, $newPrice, $context);
        $this->eventDispatcher->dispatch($event);
        
        $this->logger->info('Price alert triggered', [
            'alertId' => $alert->getId(),
            'productId' => $alert->getProductId(),
            'targetPrice' => $alert->getTargetPrice(),
            'newPrice' => $newPrice,
            'savings' => $savings,
        ]);
        
        return true;
    }
    
    /**
     * Get price history for product
     */
    public function getPriceHistory(
        string $productId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        Context $context
    ): array {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new RangeFilter('recordedAt', [
            RangeFilter::GTE => $startDate->format('c'),
            RangeFilter::LTE => $endDate->format('c'),
        ]));
        $criteria->addSorting(new FieldSorting('recordedAt', 'ASC'));
        
        $history = $this->priceHistoryRepository->search($criteria, $context);
        
        return array_map(function ($entry) {
            return [
                'date' => $entry->getRecordedAt(),
                'price' => $entry->getPrice(),
                'currency' => $entry->getCurrencyId(),
                'source' => $entry->getSource(),
            ];
        }, $history->getElements());
    }
    
    /**
     * Get price statistics
     */
    public function getPriceStatistics(
        string $productId,
        int $days = 30,
        Context $context
    ): array {
        $startDate = (new \DateTime())->sub(new \DateInterval('P' . $days . 'D'));
        $endDate = new \DateTime();
        
        $history = $this->getPriceHistory($productId, $startDate, $endDate, $context);
        
        if (empty($history)) {
            return [
                'current' => 0,
                'average' => 0,
                'min' => 0,
                'max' => 0,
                'trend' => 'stable',
                'volatility' => 0,
                'priceDrops' => 0,
            ];
        }
        
        $prices = array_column($history, 'price');
        $current = end($prices);
        $min = min($prices);
        $max = max($prices);
        $average = array_sum($prices) / count($prices);
        
        // Calculate trend
        $firstHalf = array_slice($prices, 0, floor(count($prices) / 2));
        $secondHalf = array_slice($prices, floor(count($prices) / 2));
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        if ($secondAvg > $firstAvg * 1.02) {
            $trend = 'increasing';
        } elseif ($secondAvg < $firstAvg * 0.98) {
            $trend = 'decreasing';
        } else {
            $trend = 'stable';
        }
        
        // Calculate volatility
        $variance = 0;
        foreach ($prices as $price) {
            $variance += pow($price - $average, 2);
        }
        $variance /= count($prices);
        $volatility = sqrt($variance) / $average * 100;
        
        // Count price drops
        $priceDrops = 0;
        for ($i = 1; $i < count($prices); $i++) {
            if ($prices[$i] < $prices[$i - 1]) {
                $priceDrops++;
            }
        }
        
        return [
            'current' => round($current, 2),
            'average' => round($average, 2),
            'min' => round($min, 2),
            'max' => round($max, 2),
            'trend' => $trend,
            'volatility' => round($volatility, 2),
            'priceDrops' => $priceDrops,
            'daysSinceMin' => $this->daysSincePrice($history, $min),
            'daysSinceMax' => $this->daysSincePrice($history, $max),
        ];
    }
    
    /**
     * Predict future price based on historical data
     */
    public function predictPrice(
        string $productId,
        int $daysAhead,
        Context $context
    ): array {
        $history = $this->getPriceHistory(
            $productId,
            (new \DateTime())->sub(new \DateInterval('P90D')),
            new \DateTime(),
            $context
        );
        
        if (count($history) < 10) {
            return [
                'prediction' => null,
                'confidence' => 0,
                'method' => 'insufficient_data',
            ];
        }
        
        // Simple linear regression
        $x = range(0, count($history) - 1);
        $y = array_column($history, 'price');
        
        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        // Predict future price
        $futureX = count($history) + $daysAhead;
        $prediction = $slope * $futureX + $intercept;
        
        // Calculate confidence based on R-squared
        $yMean = $sumY / $n;
        $ssTotal = 0;
        $ssResidual = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $ssTotal += pow($y[$i] - $yMean, 2);
            $ssResidual += pow($y[$i] - ($slope * $x[$i] + $intercept), 2);
        }
        
        $rSquared = 1 - ($ssResidual / $ssTotal);
        $confidence = max(0, min(100, $rSquared * 100));
        
        return [
            'prediction' => round(max(0, $prediction), 2),
            'confidence' => round($confidence, 2),
            'method' => 'linear_regression',
            'trend' => $slope > 0 ? 'increasing' : 'decreasing',
            'dailyChange' => round($slope, 2),
        ];
    }
    
    /**
     * Helper: Get current price for product
     */
    private function getCurrentPrice(string $productId, Context $context): float
    {
        $product = $this->productRepository->search(
            new Criteria([$productId]),
            $context
        )->first();
        
        if (!$product) {
            throw new \RuntimeException('Product not found: ' . $productId);
        }
        
        return $this->priceCalculationService->calculatePrice(
            $product,
            $context
        )->getGross();
    }
    
    /**
     * Helper: Record price history
     */
    private function recordPriceHistory(
        string $productId,
        float $price,
        Context $context
    ): void {
        $data = [
            'id' => Uuid::randomHex(),
            'productId' => $productId,
            'price' => $price,
            'currencyId' => $context->getCurrencyId(),
            'source' => 'price_monitor',
            'recordedAt' => new \DateTime(),
        ];
        
        $this->priceHistoryRepository->create([$data], $context);
    }
    
    /**
     * Helper: Check notification cooldown
     */
    private function canSendNotification(string $alertId): bool
    {
        $cacheKey = sprintf('price_alert.notified.%s', $alertId);
        
        return !$this->cache->has($cacheKey);
    }
    
    /**
     * Helper: Record notification sent
     */
    private function recordNotificationSent(string $alertId): void
    {
        $cacheKey = sprintf('price_alert.notified.%s', $alertId);
        
        $this->cache->set($cacheKey, time(), self::NOTIFICATION_COOLDOWN);
    }
}
```

### Price History Tracking

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service\PriceMonitoring;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class PriceHistoryService
{
    private const COMPRESSION_THRESHOLD = 30; // days
    private const COMPRESSION_INTERVAL = 'P1D'; // 1 day
    
    public function __construct(
        private EntityRepository $priceHistoryRepository,
        private EntityRepository $priceHistoryCompressedRepository,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Record price change
     */
    public function recordPrice(
        string $productId,
        float $price,
        string $currencyId,
        string $source = 'system',
        Context $context
    ): void {
        // Check if price actually changed
        $lastPrice = $this->getLastPrice($productId, $context);
        
        if ($lastPrice && abs($lastPrice['price'] - $price) < 0.01) {
            return; // No significant change
        }
        
        $data = [
            'id' => Uuid::randomHex(),
            'productId' => $productId,
            'price' => $price,
            'currencyId' => $currencyId,
            'source' => $source,
            'recordedAt' => new \DateTime(),
            'metadata' => [
                'salesChannelId' => $context->getSource()->getSalesChannelId(),
            ],
        ];
        
        $this->priceHistoryRepository->create([$data], $context);
        
        $this->logger->debug('Price recorded', [
            'productId' => $productId,
            'price' => $price,
            'previousPrice' => $lastPrice['price'] ?? null,
        ]);
    }
    
    /**
     * Get price history with aggregation
     */
    public function getAggregatedHistory(
        string $productId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $interval = 'day',
        Context $context
    ): array {
        $history = $this->getRawHistory($productId, $startDate, $endDate, $context);
        
        if (empty($history)) {
            return [];
        }
        
        // Aggregate by interval
        $aggregated = [];
        $intervalFormat = $this->getIntervalFormat($interval);
        
        foreach ($history as $entry) {
            $key = $entry['recordedAt']->format($intervalFormat);
            
            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'date' => $key,
                    'prices' => [],
                    'min' => PHP_FLOAT_MAX,
                    'max' => 0,
                    'avg' => 0,
                    'open' => $entry['price'],
                    'close' => $entry['price'],
                ];
            }
            
            $aggregated[$key]['prices'][] = $entry['price'];
            $aggregated[$key]['min'] = min($aggregated[$key]['min'], $entry['price']);
            $aggregated[$key]['max'] = max($aggregated[$key]['max'], $entry['price']);
            $aggregated[$key]['close'] = $entry['price'];
        }
        
        // Calculate averages
        foreach ($aggregated as &$data) {
            $data['avg'] = array_sum($data['prices']) / count($data['prices']);
            unset($data['prices']); // Remove raw data
        }
        
        return array_values($aggregated);
    }
    
    /**
     * Compress old price history
     */
    public function compressHistory(Context $context): array
    {
        $stats = [
            'processed' => 0,
            'compressed' => 0,
            'deleted' => 0,
        ];
        
        $threshold = (new \DateTime())->sub(new \DateInterval('P' . self::COMPRESSION_THRESHOLD . 'D'));
        
        // Get products with old history
        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('recordedAt', [
            RangeFilter::LT => $threshold->format('c'),
        ]));
        $criteria->addAggregation(
            new TermsAggregation('products', 'productId')
        );
        
        $result = $this->priceHistoryRepository->aggregate($criteria, $context);
        $productIds = $result->get('products')->getKeys();
        
        foreach ($productIds as $productId) {
            $compressed = $this->compressProductHistory($productId, $threshold, $context);
            $stats['processed']++;
            $stats['compressed'] += $compressed['compressed'];
            $stats['deleted'] += $compressed['deleted'];
        }
        
        $this->logger->info('Price history compression completed', $stats);
        
        return $stats;
    }
    
    /**
     * Compress history for single product
     */
    private function compressProductHistory(
        string $productId,
        \DateTimeInterface $threshold,
        Context $context
    ): array {
        $stats = ['compressed' => 0, 'deleted' => 0];
        
        // Get old entries
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new RangeFilter('recordedAt', [
            RangeFilter::LT => $threshold->format('c'),
        ]));
        $criteria->addSorting(new FieldSorting('recordedAt', 'ASC'));
        
        $entries = $this->priceHistoryRepository->search($criteria, $context);
        
        if ($entries->count() === 0) {
            return $stats;
        }
        
        // Group by day
        $grouped = [];
        foreach ($entries as $entry) {
            $day = $entry->getRecordedAt()->format('Y-m-d');
            if (!isset($grouped[$day])) {
                $grouped[$day] = [];
            }
            $grouped[$day][] = $entry;
        }
        
        // Create compressed entries
        $compressedData = [];
        $deleteIds = [];
        
        foreach ($grouped as $day => $dayEntries) {
            $prices = array_map(fn($e) => $e->getPrice(), $dayEntries);
            
            $compressedData[] = [
                'id' => Uuid::randomHex(),
                'productId' => $productId,
                'date' => new \DateTime($day),
                'openPrice' => $dayEntries[0]->getPrice(),
                'closePrice' => end($dayEntries)->getPrice(),
                'minPrice' => min($prices),
                'maxPrice' => max($prices),
                'avgPrice' => array_sum($prices) / count($prices),
                'entryCount' => count($dayEntries),
                'currencyId' => $dayEntries[0]->getCurrencyId(),
            ];
            
            // Mark for deletion
            foreach ($dayEntries as $entry) {
                $deleteIds[] = ['id' => $entry->getId()];
            }
            
            $stats['compressed']++;
        }
        
        // Save compressed data
        if (!empty($compressedData)) {
            $this->priceHistoryCompressedRepository->create($compressedData, $context);
        }
        
        // Delete original entries
        if (!empty($deleteIds)) {
            $this->priceHistoryRepository->delete($deleteIds, $context);
            $stats['deleted'] = count($deleteIds);
        }
        
        return $stats;
    }
    
    /**
     * Get interval format for aggregation
     */
    private function getIntervalFormat(string $interval): string
    {
        return match($interval) {
            'hour' => 'Y-m-d H:00',
            'day' => 'Y-m-d',
            'week' => 'Y-W',
            'month' => 'Y-m',
            'year' => 'Y',
            default => 'Y-m-d',
        };
    }
}
```

### Frontend Components

```vue
<template>
  <div class="price-monitor">
    <div class="price-alert-setup">
      <h3>Set up price alert</h3>

      <div class="current-price">
        <span class="label">Current price:</span>
        <span class="price">{{ formatPrice(currentPrice) }}</span>
      </div>

      <div class="target-price">
        <label>Target price:</label>
        <div class="price-input">
          <input
              type="number"
              v-model.number="targetPrice"
              :max="currentPrice - 0.01"
              step="0.01"
              @input="calculateSavings"
          >
          <span class="currency">€</span>
        </div>

        <div class="savings-preview" v-if="targetPrice > 0">
          <span class="savings">
            Savings: {{ formatPrice(savings) }} 
            ({{ savingsPercentage.toFixed(1) }}%)
          </span>
        </div>
      </div>

      <div class="alert-options">
        <label>
          <input
              type="checkbox"
              v-model="options.notifyOnAnyDrop"
          >
          Notify on any price drop
        </label>

        <label>
          <input
              type="checkbox"
              v-model="options.includeShipping"
          >
          Include shipping costs
        </label>
      </div>

      <button
          @click="setPriceAlert"
          :disabled="!isValidPrice"
          class="btn-primary"
      >
        <i class="icon-bell"></i>
        Activate price alert
      </button>
    </div>

    <!-- Price History Chart -->
    <div class="price-history" v-if="showHistory">
      <h3>Price history</h3>

      <div class="time-range">
        <button
            v-for="range in timeRanges"
            :key="range.value"
            @click="selectedRange = range.value"
            :class="{ active: selectedRange === range.value }"
        >
          {{ range.label }}
        </button>
      </div>

      <div class="chart-container">
        <price-history-chart
            :data="priceHistory"
            :current-price="currentPrice"
            :target-price="targetPrice"
            @hover="onChartHover"
        />
      </div>

      <div class="price-stats">
        <div class="stat">
          <span class="label">Lowest price:</span>
          <span class="value">{{ formatPrice(stats.min) }}</span>
          <span class="date">{{ formatDate(stats.minDate) }}</span>
        </div>

        <div class="stat">
          <span class="label">Highest price:</span>
          <span class="value">{{ formatPrice(stats.max) }}</span>
          <span class="date">{{ formatDate(stats.maxDate) }}</span>
        </div>

        <div class="stat">
          <span class="label">Average:</span>
          <span class="value">{{ formatPrice(stats.avg) }}</span>
        </div>

        <div class="stat">
          <span class="label">Trend:</span>
          <span class="value trend" :class="stats.trend">
            <i :class="getTrendIcon(stats.trend)"></i>
            {{ getTrendLabel(stats.trend) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Active Alerts -->
    <div class="active-alerts" v-if="activeAlerts.length > 0">
      <h3>Active price alerts</h3>

      <div class="alert-list">
        <div
            v-for="alert in activeAlerts"
            :key="alert.id"
            class="alert-item"
        >
          <div class="alert-info">
            <h4>{{ alert.product.name }}</h4>
            <div class="prices">
              <span class="current">Current: {{ formatPrice(alert.currentPrice) }}</span>
              <span class="target">Target: {{ formatPrice(alert.targetPrice) }}</span>
            </div>
            <div class="progress">
              <div
                  class="progress-bar"
                  :style="{ width: alert.progressPercentage + '%' }"
              ></div>
            </div>
          </div>

          <div class="alert-actions">
            <button @click="editAlert(alert)" class="btn-edit">
              <i class="icon-edit"></i>
            </button>
            <button @click="deleteAlert(alert)" class="btn-delete">
              <i class="icon-trash"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Price Prediction -->
    <div class="price-prediction" v-if="prediction">
      <h3>Price forecast</h3>

      <div class="prediction-content">
        <div class="prediction-value">
          <span class="label">Expected price in {{ predictionDays }} days:</span>
          <span class="price">{{ formatPrice(prediction.price) }}</span>
          <span class="confidence">
            Confidence: {{ prediction.confidence }}%
          </span>
        </div>

        <div class="prediction-chart">
          <price-prediction-chart
              :historical="priceHistory"
              :prediction="prediction"
              :days="predictionDays"
          />
        </div>

        <p class="disclaimer">
          * Forecast based on historical data.
          No guarantee for actual price development.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
  import { ref, computed, watch, onMounted } from 'vue'
  import { usePriceMonitorStore } from '@/stores/priceMonitor'
  import { useNotification } from '@/composables/useNotification'
  import PriceHistoryChart from './PriceHistoryChart.vue'
  import PricePredictionChart from './PricePredictionChart.vue'

  const props = defineProps({
    productId: {
      type: String,
      required: true
    },
    wishlistItemId: {
      type: String,
      required: true
    },
    currentPrice: {
      type: Number,
      required: true
    }
  })

  const priceMonitorStore = usePriceMonitorStore()
  const notification = useNotification()

  const targetPrice = ref(0)
  const selectedRange = ref(30)
  const predictionDays = ref(7)
  const showHistory = ref(false)

  const options = ref({
    notifyOnAnyDrop: false,
    includeShipping: false,
    considerVariants: true
  })

  const timeRanges = [
    { label: '7 Days', value: 7 },
    { label: '30 Days', value: 30 },
    { label: '90 Days', value: 90 },
    { label: '1 Year', value: 365 }
  ]

  const savings = computed(() => {
    if (targetPrice.value > 0 && targetPrice.value < props.currentPrice) {
      return props.currentPrice - targetPrice.value
    }
    return 0
  })

  const savingsPercentage = computed(() => {
    if (savings.value > 0) {
      return (savings.value / props.currentPrice) * 100
    }
    return 0
  })

  const isValidPrice = computed(() => {
    return targetPrice.value > 0 && targetPrice.value < props.currentPrice
  })

  const priceHistory = computed(() =>
      priceMonitorStore.getPriceHistory(props.productId, selectedRange.value)
  )

  const stats = computed(() =>
      priceMonitorStore.getPriceStatistics(props.productId)
  )

  const activeAlerts = computed(() =>
      priceMonitorStore.getActiveAlerts(props.productId)
  )

  const prediction = computed(() =>
      priceMonitorStore.getPricePrediction(props.productId, predictionDays.value)
  )

  onMounted(async () => {
    await priceMonitorStore.loadPriceData(props.productId)
    showHistory.value = priceHistory.value.length > 0
  })

  async function setPriceAlert() {
    try {
      await priceMonitorStore.createPriceAlert({
        wishlistItemId: props.wishlistItemId,
        productId: props.productId,
        targetPrice: targetPrice.value,
        options: options.value
      })

      notification.success('Price alert has been activated!')
      targetPrice.value = 0
    } catch (error) {
      notification.error('Error activating price alert')
    }
  }

  async function deleteAlert(alert) {
    if (!confirm('Really delete price alert?')) {
      return
    }

    try {
      await priceMonitorStore.deletePriceAlert(alert.id)
      notification.success('Price alert deleted')
    } catch (error) {
      notification.error('Error deleting alert')
    }
  }

  function formatPrice(price) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(price)
  }

  function formatDate(date) {
    if (!date) return ''
    return new Intl.DateTimeFormat('en-US', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    }).format(new Date(date))
  }

  function getTrendIcon(trend) {
    return {
      'increasing': 'icon-trending-up',
      'decreasing': 'icon-trending-down',
      'stable': 'icon-minus'
    }[trend] || 'icon-minus'
  }

  function getTrendLabel(trend) {
    return {
      'increasing': 'Rising',
      'decreasing': 'Falling',
      'stable': 'Stable'
    }[trend] || 'Unknown'
  }
</script>

<style scoped>
  .price-monitor {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }

  .price-alert-setup {
    margin-bottom: 2rem;
  }

  .current-price {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    font-size: 1.25rem;
  }

  .current-price .price {
    font-weight: bold;
    color: var(--primary-color);
  }

  .target-price {
    margin-bottom: 1rem;
  }

  .price-input {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
  }

  .price-input input {
    width: 150px;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1.1rem;
  }

  .savings-preview {
    margin-top: 0.5rem;
    color: #28a745;
    font-weight: 500;
  }

  .alert-options {
    margin: 1.5rem 0;
  }

  .alert-options label {
    display: block;
    margin-bottom: 0.5rem;
  }

  .time-range {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
  }

  .time-range button {
    padding: 0.5rem 1rem;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
  }

  .time-range button.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
  }

  .chart-container {
    height: 300px;
    margin-bottom: 1.5rem;
  }

  .price-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
  }

  .stat {
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 4px;
  }

  .stat .label {
    display: block;
    font-size: 0.875rem;
    color: #666;
    margin-bottom: 0.25rem;
  }

  .stat .value {
    display: block;
    font-size: 1.25rem;
    font-weight: bold;
  }

  .stat .date {
    display: block;
    font-size: 0.75rem;
    color: #999;
    margin-top: 0.25rem;
  }

  .trend.increasing {
    color: #dc3545;
  }

  .trend.decreasing {
    color: #28a745;
  }

  .trend.stable {
    color: #6c757d;
  }

  .alert-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .alert-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
  }

  .progress {
    width: 200px;
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    margin-top: 0.5rem;
    overflow: hidden;
  }

  .progress-bar {
    height: 100%;
    background: var(--primary-color);
    transition: width 0.3s ease;
  }

  .prediction-content {
    background: #f0f8ff;
    padding: 1.5rem;
    border-radius: 8px;
  }

  .prediction-value {
    text-align: center;
    margin-bottom: 1.5rem;
  }

  .prediction-value .price {
    display: block;
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
    margin: 0.5rem 0;
  }

  .confidence {
    display: block;
    font-size: 0.875rem;
    color: #666;
  }

  .disclaimer {
    margin-top: 1rem;
    font-size: 0.75rem;
    color: #666;
    font-style: italic;
    text-align: center;
  }
</style>
```

## Database Schema

```sql
-- Price alerts table
CREATE TABLE `wishlist_price_alert` (
                                        `id` BINARY(16) NOT NULL,
                                        `wishlist_item_id` BINARY(16) NOT NULL,
                                        `product_id` BINARY(16) NOT NULL,
                                        `customer_id` BINARY(16) NOT NULL,
                                        `target_price` DECIMAL(10,2) NOT NULL,
                                        `current_price` DECIMAL(10,2) NOT NULL,
                                        `price_drop_percentage` DECIMAL(5,2),
                                        `active` TINYINT(1) DEFAULT 1,
                                        `options` JSON,
                                        `triggered_count` INT DEFAULT 0,
                                        `last_triggered_at` DATETIME(3),
                                        `last_checked_at` DATETIME(3),
                                        `lowest_price` DECIMAL(10,2),
                                        `created_at` DATETIME(3) NOT NULL,
                                        `updated_at` DATETIME(3),
                                        PRIMARY KEY (`id`),
                                        UNIQUE KEY `uniq.price_alert.item` (`wishlist_item_id`),
                                        KEY `idx.price_alert.product` (`product_id`, `active`),
                                        KEY `idx.price_alert.customer` (`customer_id`),
                                        KEY `idx.price_alert.check` (`active`, `last_checked_at`),
                                        CONSTRAINT `fk.price_alert.wishlist_item` FOREIGN KEY (`wishlist_item_id`)
                                            REFERENCES `wishlist_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Price history table
CREATE TABLE `product_price_history` (
                                         `id` BINARY(16) NOT NULL,
                                         `product_id` BINARY(16) NOT NULL,
                                         `price` DECIMAL(10,2) NOT NULL,
                                         `currency_id` BINARY(16) NOT NULL,
                                         `sales_channel_id` BINARY(16),
                                         `source` VARCHAR(50) DEFAULT 'system',
                                         `metadata` JSON,
                                         `recorded_at` DATETIME(3) NOT NULL,
                                         PRIMARY KEY (`id`),
                                         KEY `idx.price_history.product_date` (`product_id`, `recorded_at`),
                                         KEY `idx.price_history.date` (`recorded_at`),
                                         CONSTRAINT `fk.price_history.product` FOREIGN KEY (`product_id`)
                                             REFERENCES `product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (YEAR(recorded_at)) (
  PARTITION p2024 VALUES LESS THAN (2025),
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION p2026 VALUES LESS THAN (2027),
  PARTITION pfuture VALUES LESS THAN MAXVALUE
);

-- Compressed price history for long-term storage
CREATE TABLE `product_price_history_compressed` (
                                                    `id` BINARY(16) NOT NULL,
                                                    `product_id` BINARY(16) NOT NULL,
                                                    `date` DATE NOT NULL,
                                                    `open_price` DECIMAL(10,2) NOT NULL,
                                                    `close_price` DECIMAL(10,2) NOT NULL,
                                                    `min_price` DECIMAL(10,2) NOT NULL,
                                                    `max_price` DECIMAL(10,2) NOT NULL,
                                                    `avg_price` DECIMAL(10,2) NOT NULL,
                                                    `entry_count` INT NOT NULL,
                                                    `currency_id` BINARY(16) NOT NULL,
                                                    PRIMARY KEY (`id`),
                                                    UNIQUE KEY `uniq.price_compressed.product_date` (`product_id`, `date`),
                                                    KEY `idx.price_compressed.date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Event System

```php
// Price monitoring events
PriceAlertCreatedEvent
PriceAlertUpdatedEvent
PriceAlertDeletedEvent
PriceAlertTriggeredEvent
PriceDropDetectedEvent
PriceHistoryRecordedEvent
```

## Scheduled Tasks

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CheckPriceAlertsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'advanced_wishlist.check_price_alerts';
    }

    public static function getDefaultInterval(): int
    {
        return 3600; // 1 hour
    }
}

class CompressPriceHistoryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'advanced_wishlist.compress_price_history';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // Daily
    }
}
```

## Performance Considerations

### Batch Processing

```php
// Process alerts in batches to avoid memory issues
$batchProcessor = new BatchProcessor(100);

$batchProcessor->process($alerts, function($batch) {
    foreach ($batch as $alert) {
        $this->checkAlert($alert);
    }
});
```

### Caching Strategy

```php
// Cache current prices
$cacheKey = sprintf('product.price.%s', $productId);
$price = $this->cache->get($cacheKey, function() use ($productId) {
    return $this->calculateCurrentPrice($productId);
});
```

### Index Optimization

```sql
-- Optimize price check queries
CREATE INDEX `idx.price_alert.batch_check`
    ON `wishlist_price_alert` (`active`, `last_checked_at`, `product_id`)
    WHERE `active` = 1;

-- Optimize history queries
CREATE INDEX `idx.price_history.analysis`
    ON `product_price_history` (`product_id`, `recorded_at` DESC, `price`);
```