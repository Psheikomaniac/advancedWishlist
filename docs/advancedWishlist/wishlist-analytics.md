# Analytics Feature Documentation

## Overview

The Analytics Feature provides comprehensive insights into customer wishlist behavior. Shop owners can identify trends, recognize popular products, and optimize conversion rates.

## User Stories

### As a shop owner, I want to...
1. **See top products** that are most frequently on wishlists
2. **Understand conversion rates** from wishlist to purchase
3. **Identify trends** in product popularity
4. **Analyze customer behavior**
5. **Measure ROI** of the wishlist feature

### As a marketing manager, I want to...
1. **Optimize campaigns** based on wishlist data
2. **Segment target audiences** by wishlist behavior
3. **Identify seasonal trends**
4. **Develop pricing strategies**

## Technical Implementation

### Analytics Service

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service\Analytics;

use AdvancedWishlist\Core\Entity\Analytics\WishlistAnalyticsEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Psr\Log\LoggerInterface;

class WishlistAnalyticsService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const TOP_PRODUCTS_LIMIT = 100;
    
    public function __construct(
        private EntityRepository $wishlistRepository,
        private EntityRepository $wishlistItemRepository,
        private EntityRepository $analyticsRepository,
        private EntityRepository $productRepository,
        private EntityRepository $orderRepository,
        private ConversionTrackingService $conversionService,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Get comprehensive analytics dashboard data
     */
    public function getDashboardData(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $salesChannelId,
        Context $context
    ): array {
        $cacheKey = $this->getCacheKey('dashboard', $startDate, $endDate, $salesChannelId);
        
        return $this->cache->get($cacheKey, function() use ($startDate, $endDate, $salesChannelId, $context) {
            return [
                'overview' => $this->getOverviewMetrics($startDate, $endDate, $salesChannelId, $context),
                'topProducts' => $this->getTopWishlistProducts($startDate, $endDate, $salesChannelId, $context),
                'conversionFunnel' => $this->getConversionFunnel($startDate, $endDate, $salesChannelId, $context),
                'trends' => $this->getTrendData($startDate, $endDate, $salesChannelId, $context),
                'customerSegments' => $this->getCustomerSegmentation($startDate, $endDate, $salesChannelId, $context),
                'shareAnalytics' => $this->getShareAnalytics($startDate, $endDate, $salesChannelId, $context),
            ];
        }, self::CACHE_TTL);
    }
    
    /**
     * Get overview metrics
     */
    public function getOverviewMetrics(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $salesChannelId,
        Context $context
    ): array {
        $criteria = $this->getBaseCriteria($startDate, $endDate, $salesChannelId);
        
        // Add aggregations
        $criteria->addAggregation(new CountAggregation('total_wishlists', 'id'));
        $criteria->addAggregation(new CountAggregation('unique_customers', 'customerId'));
        $criteria->addAggregation(new SumAggregation('total_items', 'itemCount'));
        $criteria->addAggregation(new AvgAggregation('avg_items_per_wishlist', 'itemCount'));
        
        $result = $this->wishlistRepository->aggregate($criteria, $context);
        
        // Get conversion metrics
        $conversionData = $this->conversionService->getConversionMetrics(
            $startDate,
            $endDate,
            $salesChannelId,
            $context
        );
        
        return [
            'totalWishlists' => $result->get('total_wishlists')->getCount(),
            'uniqueCustomers' => $result->get('unique_customers')->getCount(),
            'totalItems' => (int) $result->get('total_items')->getSum(),
            'avgItemsPerWishlist' => round($result->get('avg_items_per_wishlist')->getAvg(), 2),
            'conversionRate' => $conversionData['rate'],
            'convertedRevenue' => $conversionData['revenue'],
            'avgDaysToConvert' => $conversionData['avgDays'],
        ];
    }
    
    /**
     * Get top wishlist products with trends
     */
    public function getTopWishlistProducts(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $salesChannelId,
        Context $context,
        int $limit = 50
    ): array {
        // Current period
        $currentProducts = $this->getProductRanking($startDate, $endDate, $salesChannelId, $context, $limit);
        
        // Previous period for comparison
        $periodLength = $endDate->diff($startDate);
        $previousStart = clone $startDate;
        $previousEnd = clone $endDate;
        $previousStart->sub($periodLength);
        $previousEnd->sub($periodLength);
        
        $previousProducts = $this->getProductRanking($previousStart, $previousEnd, $salesChannelId, $context, $limit * 2);
        
        // Calculate trends
        $products = [];
        foreach ($currentProducts as $rank => $product) {
            $previousRank = array_search($product['productId'], array_column($previousProducts, 'productId'));
            
            $trend = 'new';
            $trendValue = 0;
            
            if ($previousRank !== false) {
                if ($previousRank > $rank) {
                    $trend = 'up';
                    $trendValue = $previousRank - $rank;
                } elseif ($previousRank < $rank) {
                    $trend = 'down';
                    $trendValue = $rank - $previousRank;
                } else {
                    $trend = 'stable';
                }
            }
            
            $products[] = array_merge($product, [
                'rank' => $rank + 1,
                'trend' => $trend,
                'trendValue' => $trendValue,
                'conversionRate' => $this->calculateProductConversionRate($product['productId'], $startDate, $endDate, $context),
            ]);
        }
        
        return $products;
    }
    
    /**
     * Get conversion funnel data
     */
    public function getConversionFunnel(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $salesChannelId,
        Context $context
    ): array {
        $funnel = [
            'viewed' => $this->getProductViewCount($startDate, $endDate, $salesChannelId, $context),
            'wishlisted' => $this->getWishlistedCount($startDate, $endDate, $salesChannelId, $context),
            'addedToCart' => $this->getAddedToCartCount($startDate, $endDate, $salesChannelId, $context),
            'purchased' => $this->getPurchasedCount($startDate, $endDate, $salesChannelId, $context),
        ];
        
        // Calculate conversion rates between steps
        $funnel['viewToWishlistRate'] = $funnel['viewed'] > 0 
            ? round(($funnel['wishlisted'] / $funnel['viewed']) * 100, 2) 
            : 0;
            
        $funnel['wishlistToCartRate'] = $funnel['wishlisted'] > 0 
            ? round(($funnel['addedToCart'] / $funnel['wishlisted']) * 100, 2) 
            : 0;
            
        $funnel['cartToPurchaseRate'] = $funnel['addedToCart'] > 0 
            ? round(($funnel['purchased'] / $funnel['addedToCart']) * 100, 2) 
            : 0;
            
        $funnel['overallConversionRate'] = $funnel['wishlisted'] > 0 
            ? round(($funnel['purchased'] / $funnel['wishlisted']) * 100, 2) 
            : 0;
        
        return $funnel;
    }
    
    /**
     * Get trend data over time
     */
    public function getTrendData(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $salesChannelId,
        Context $context
    ): array {
        $criteria = $this->getBaseCriteria($startDate, $endDate, $salesChannelId);
        
        // Determine appropriate interval
        $days = $endDate->diff($startDate)->days;
        $interval = match(true) {
            $days <= 7 => DateHistogramAggregation::PER_DAY,
            $days <= 31 => DateHistogramAggregation::PER_DAY,
            $days <= 90 => DateHistogramAggregation::PER_WEEK,
            default => DateHistogramAggregation::PER_MONTH,
        };
        
        // Add date histogram aggregation
        $criteria->addAggregation(
            new DateHistogramAggregation(
                'wishlists_over_time',
                'createdAt',
                $interval,
                null,
                new CountAggregation('count', 'id')
            )
        );
        
        $result = $this->wishlistRepository->aggregate($criteria, $context);
        
        $trend = [];
        foreach ($result->get('wishlists_over_time')->getBuckets() as $bucket) {
            $trend[] = [
                'date' => $bucket->getKey(),
                'wishlists' => $bucket->getCount(),
                'items' => $this->getItemCountForDate($bucket->getKey(), $salesChannelId, $context),
                'conversions' => $this->getConversionCountForDate($bucket->getKey(), $salesChannelId, $context),
            ];
        }
        
        return $trend;
    }
    
    /**
     * Get customer segmentation data
     */
    public function getCustomerSegmentation(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $salesChannelId,
        Context $context
    ): array {
        // Segment by wishlist count
        $segments = [
            'casual' => ['min' => 0, 'max' => 5, 'count' => 0, 'revenue' => 0],
            'engaged' => ['min' => 6, 'max' => 20, 'count' => 0, 'revenue' => 0],
            'power' => ['min' => 21, 'max' => PHP_INT_MAX, 'count' => 0, 'revenue' => 0],
        ];
        
        $criteria = new Criteria();
        $criteria->addAggregation(
            new TermsAggregation(
                'customers_by_wishlist_count',
                'customerId',
                null,
                null,
                new CountAggregation('wishlist_count', 'id')
            )
        );
        
        if ($salesChannelId) {
            $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        }
        
        $result = $this->wishlistRepository->aggregate($criteria, $context);
        
        foreach ($result->get('customers_by_wishlist_count')->getBuckets() as $bucket) {
            $customerId = $bucket->getKey();
            $wishlistCount = $bucket->getCount();
            
            // Determine segment
            foreach ($segments as $segmentName => &$segment) {
                if ($wishlistCount >= $segment['min'] && $wishlistCount <= $segment['max']) {
                    $segment['count']++;
                    $segment['revenue'] += $this->getCustomerRevenue($customerId, $startDate, $endDate, $context);
                    break;
                }
            }
        }
        
        // Calculate percentages and average revenue
        $totalCustomers = array_sum(array_column($segments, 'count'));
        
        foreach ($segments as &$segment) {
            $segment['percentage'] = $totalCustomers > 0 
                ? round(($segment['count'] / $totalCustomers) * 100, 2) 
                : 0;
            $segment['avgRevenue'] = $segment['count'] > 0 
                ? round($segment['revenue'] / $segment['count'], 2) 
                : 0;
        }
        
        return $segments;
    }
    
    /**
     * Get share analytics
     */
    public function getShareAnalytics(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $salesChannelId,
        Context $context
    ): array {
        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::GTE => $startDate->format('c'),
            RangeFilter::LTE => $endDate->format('c'),
        ]));
        
        if ($salesChannelId) {
            $criteria->addFilter(new EqualsFilter('wishlist.salesChannelId', $salesChannelId));
        }
        
        // Aggregations
        $criteria->addAggregation(new CountAggregation('total_shares', 'id'));
        $criteria->addAggregation(new TermsAggregation('shares_by_type', 'type'));
        $criteria->addAggregation(new SumAggregation('total_views', 'views'));
        $criteria->addAggregation(new SumAggregation('total_conversions', 'conversions'));
        $criteria->addAggregation(new AvgAggregation('avg_views_per_share', 'views'));
        
        $result = $this->shareRepository->aggregate($criteria, $context);
        
        $sharesByType = [];
        foreach ($result->get('shares_by_type')->getBuckets() as $bucket) {
            $sharesByType[$bucket->getKey()] = $bucket->getCount();
        }
        
        return [
            'totalShares' => $result->get('total_shares')->getCount(),
            'sharesByType' => $sharesByType,
            'totalViews' => (int) $result->get('total_views')->getSum(),
            'totalConversions' => (int) $result->get('total_conversions')->getSum(),
            'avgViewsPerShare' => round($result->get('avg_views_per_share')->getAvg(), 2),
            'conversionRate' => $result->get('total_views')->getSum() > 0
                ? round(($result->get('total_conversions')->getSum() / $result->get('total_views')->getSum()) * 100, 2)
                : 0,
            'viralCoefficient' => $this->calculateViralCoefficient($startDate, $endDate, $salesChannelId, $context),
        ];
    }
    
    /**
     * Record analytics event
     */
    public function recordEvent(
        string $eventType,
        array $data,
        Context $context
    ): void {
        $eventData = [
            'id' => Uuid::randomHex(),
            'eventType' => $eventType,
            'eventData' => $data,
            'salesChannelId' => $context->getSource()?->getSalesChannelId(),
            'createdAt' => new \DateTime(),
        ];
        
        // Process event based on type
        switch ($eventType) {
            case 'wishlist_created':
                $this->recordWishlistCreated($data, $context);
                break;
                
            case 'item_added':
                $this->recordItemAdded($data, $context);
                break;
                
            case 'item_converted':
                $this->recordConversion($data, $context);
                break;
                
            case 'wishlist_shared':
                $this->recordShare($data, $context);
                break;
        }
        
        // Store raw event for future analysis
        $this->eventRepository->create([$eventData], $context);
    }
    
    /**
     * Generate analytics report
     */
    public function generateReport(
        string $type,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $options,
        Context $context
    ): array {
        return match($type) {
            'executive_summary' => $this->generateExecutiveSummary($startDate, $endDate, $options, $context),
            'product_performance' => $this->generateProductPerformanceReport($startDate, $endDate, $options, $context),
            'customer_insights' => $this->generateCustomerInsightsReport($startDate, $endDate, $options, $context),
            'conversion_analysis' => $this->generateConversionAnalysisReport($startDate, $endDate, $options, $context),
            default => throw new \InvalidArgumentException('Unknown report type: ' . $type),
        };
    }
    
    /**
     * Calculate product conversion rate
     */
    private function calculateProductConversionRate(
        string $productId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        Context $context
    ): float {
        // Get wishlist count
        $wishlistCriteria = new Criteria();
        $wishlistCriteria->addFilter(new EqualsFilter('productId', $productId));
        $wishlistCriteria->addFilter(new RangeFilter('addedAt', [
            RangeFilter::GTE => $startDate->format('c'),
            RangeFilter::LTE => $endDate->format('c'),
        ]));
        
        $wishlistCount = $this->wishlistItemRepository->search($wishlistCriteria, $context)->getTotal();
        
        if ($wishlistCount === 0) {
            return 0;
        }
        
        // Get conversion count
        $conversionCount = $this->conversionService->getProductConversionCount(
            $productId,
            $startDate,
            $endDate,
            $context
        );
        
        return round(($conversionCount / $wishlistCount) * 100, 2);
    }
    
    /**
     * Helper: Get base criteria with date and channel filters
     */
    private function getBaseCriteria(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $salesChannelId
    ): Criteria {
        $criteria = new Criteria();
        
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::GTE => $startDate->format('c'),
            RangeFilter::LTE => $endDate->format('c'),
        ]));
        
        if ($salesChannelId) {
            $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        }
        
        return $criteria;
    }
    
    /**
     * Helper: Generate cache key
     */
    private function getCacheKey(
        string $type,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $salesChannelId
    ): string {
        return sprintf(
            'wishlist.analytics.%s.%s.%s.%s',
            $type,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $salesChannelId ?? 'all'
        );
    }
}
```

### Analytics Dashboard Component

```vue
<template>
  <div class="wishlist-analytics-dashboard">
    <div class="dashboard-header">
      <h1>Wishlist Analytics</h1>
      
      <div class="controls">
        <date-range-picker 
          v-model="dateRange"
          :presets="datePresets"
          @change="loadData"
        />
        
        <select v-model="selectedChannel" @change="loadData">
          <option value="">All Sales Channels</option>
          <option 
            v-for="channel in salesChannels" 
            :key="channel.id"
            :value="channel.id"
          >
            {{ channel.name }}
          </option>
        </select>
        
        <button @click="exportReport" class="btn-export">
          <i class="icon-download"></i>
          Export
        </button>
      </div>
    </div>
    
    <!-- Overview Metrics -->
    <div class="metrics-grid">
      <metric-card
        v-for="metric in overviewMetrics"
        :key="metric.key"
        :title="metric.title"
        :value="metric.value"
        :change="metric.change"
        :format="metric.format"
        :icon="metric.icon"
      />
    </div>
    
    <!-- Charts Row -->
    <div class="charts-row">
      <!-- Trend Chart -->
      <div class="chart-container">
        <h3>Wishlist Trend</h3>
        <trend-chart
          :data="trendData"
          :metrics="['wishlists', 'items', 'conversions']"
          :height="300"
        />
      </div>
      
      <!-- Conversion Funnel -->
      <div class="chart-container">
        <h3>Conversion Funnel</h3>
        <funnel-chart
          :data="conversionFunnel"
          :height="300"
        />
      </div>
    </div>
    
    <!-- Top Products -->
    <div class="top-products-section">
      <h3>Top Wishlist Products</h3>
      
      <div class="products-table">
        <table>
          <thead>
            <tr>
              <th>Rank</th>
              <th>Product</th>
              <th>Wishlist Count</th>
              <th>Conversion Rate</th>
              <th>Trend</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="product in topProducts" 
              :key="product.productId"
              :class="{ 'trending-up': product.trend === 'up' }"
            >
              <td class="rank">
                {{ product.rank }}
                <trend-indicator :trend="product.trend" :value="product.trendValue" />
              </td>
              <td class="product">
                <div class="product-info">
                  <img :src="product.image" :alt="product.name">
                  <div>
                    <strong>{{ product.name }}</strong>
                    <span class="sku">{{ product.productNumber }}</span>
                  </div>
                </div>
              </td>
              <td class="count">
                {{ product.wishlistCount }}
                <span class="customers">({{ product.uniqueCustomers }} Customers)</span>
              </td>
              <td class="conversion">
                <conversion-badge :rate="product.conversionRate" />
              </td>
              <td class="trend">
                <mini-trend-chart :data="product.trendHistory" />
              </td>
              <td class="actions">
                <button @click="viewProductDetails(product)" class="btn-icon">
                  <i class="icon-chart"></i>
                </button>
                <button @click="createCampaign(product)" class="btn-icon">
                  <i class="icon-megaphone"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <button @click="showAllProducts" class="btn-link">
        Show All Products →
      </button>
    </div>
    
    <!-- Customer Segments -->
    <div class="segments-section">
      <h3>Customer Segments</h3>
      
      <div class="segments-grid">
        <segment-card
          v-for="(segment, key) in customerSegments"
          :key="key"
          :name="key"
          :data="segment"
          @click="viewSegmentDetails(key)"
        />
      </div>
    </div>
    
    <!-- Share Analytics -->
    <div class="share-analytics">
      <h3>Share Performance</h3>
      
      <div class="share-metrics">
        <div class="metric">
          <span class="label">Total Shares</span>
          <span class="value">{{ formatNumber(shareAnalytics.totalShares) }}</span>
        </div>
        <div class="metric">
          <span class="label">Views</span>
          <span class="value">{{ formatNumber(shareAnalytics.totalViews) }}</span>
        </div>
        <div class="metric">
          <span class="label">Conversions</span>
          <span class="value">{{ formatNumber(shareAnalytics.totalConversions) }}</span>
        </div>
        <div class="metric">
          <span class="label">Viral Coefficient</span>
          <span class="value">{{ shareAnalytics.viralCoefficient }}</span>
        </div>
      </div>
      
      <share-type-chart
        :data="shareAnalytics.sharesByType"
        :height="200"
      />
    </div>
    
    <!-- Insights & Recommendations -->
    <div class="insights-section">
      <h3>Insights & Recommendations</h3>
      
      <div class="insights-grid">
        <insight-card
          v-for="insight in insights"
          :key="insight.id"
          :type="insight.type"
          :title="insight.title"
          :description="insight.description"
          :action="insight.action"
          @action="handleInsightAction(insight)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAnalyticsStore } from '@/stores/analytics'
import { useNotification } from '@/composables/useNotification'
import DateRangePicker from '@/components/DateRangePicker.vue'
import MetricCard from '@/components/analytics/MetricCard.vue'
import TrendChart from '@/components/analytics/TrendChart.vue'
import FunnelChart from '@/components/analytics/FunnelChart.vue'
import TrendIndicator from '@/components/analytics/TrendIndicator.vue'
import ConversionBadge from '@/components/analytics/ConversionBadge.vue'
import MiniTrendChart from '@/components/analytics/MiniTrendChart.vue'
import SegmentCard from '@/components/analytics/SegmentCard.vue'
import ShareTypeChart from '@/components/analytics/ShareTypeChart.vue'
import InsightCard from '@/components/analytics/InsightCard.vue'

const analyticsStore = useAnalyticsStore()
const notification = useNotification()

const dateRange = ref({
  start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000),
  end: new Date()
})

const selectedChannel = ref('')
const loading = ref(false)

const datePresets = [
  { label: 'Last 7 Days', days: 7 },
  { label: 'Last 30 Days', days: 30 },
  { label: 'Last 90 Days', days: 90 },
  { label: 'This Year', type: 'year' }
]

const dashboardData = computed(() => analyticsStore.dashboardData)
const overviewMetrics = computed(() => formatOverviewMetrics(dashboardData.value?.overview))
const topProducts = computed(() => dashboardData.value?.topProducts || [])
const conversionFunnel = computed(() => dashboardData.value?.conversionFunnel || {})
const trendData = computed(() => dashboardData.value?.trends || [])
const customerSegments = computed(() => dashboardData.value?.customerSegments || {})
const shareAnalytics = computed(() => dashboardData.value?.shareAnalytics || {})
const insights = computed(() => generateInsights())

onMounted(async () => {
  await loadData()
})

async function loadData() {
  loading.value = true
  try {
    await analyticsStore.loadDashboardData({
      startDate: dateRange.value.start,
      endDate: dateRange.value.end,
      salesChannelId: selectedChannel.value
    })
  } catch (error) {
    notification.error('Error loading analytics')
  } finally {
    loading.value = false
  }
}

function formatOverviewMetrics(overview) {
  if (!overview) return []
  
  return [
    {
      key: 'wishlists',
      title: 'Wishlists',
      value: overview.totalWishlists,
      change: calculateChange('wishlists'),
      format: 'number',
      icon: 'icon-heart'
    },
    {
      key: 'customers',
      title: 'Unique Customers',
      value: overview.uniqueCustomers,
      change: calculateChange('customers'),
      format: 'number',
      icon: 'icon-users'
    },
    {
      key: 'items',
      title: 'Total Items',
      value: overview.totalItems,
      change: calculateChange('items'),
      format: 'number',
      icon: 'icon-package'
    },
    {
      key: 'avgItems',
      title: 'Ø Items/Wishlist',
      value: overview.avgItemsPerWishlist,
      change: calculateChange('avgItems'),
      format: 'decimal',
      icon: 'icon-trending-up'
    },
    {
      key: 'conversion',
      title: 'Conversion Rate',
      value: overview.conversionRate,
      change: calculateChange('conversion'),
      format: 'percentage',
      icon: 'icon-shopping-cart'
    },
    {
      key: 'revenue',
      title: 'Converted Revenue',
      value: overview.convertedRevenue,
      change: calculateChange('revenue'),
      format: 'currency',
      icon: 'icon-dollar-sign'
    }
  ]
}

function calculateChange(metric) {
  // This would compare with previous period
  // For demo purposes, returning random values
  const change = Math.random() * 40 - 20
  return {
    value: change,
    percentage: Math.abs(change),
    direction: change > 0 ? 'up' : 'down'
  }
}

function generateInsights() {
  const insights = []
  
  // Top product insight
  if (topProducts.value.length > 0) {
    const topProduct = topProducts.value[0]
    if (topProduct.conversionRate < 20) {
      insights.push({
        id: 'low-conversion-top',
        type: 'warning',
        title: 'Low Conversion for Top Product',
        description: `"${topProduct.name}" is very popular on wishlists but has only ${topProduct.conversionRate}% conversion rate.`,
        action: {
          label: 'Create Campaign',
          handler: () => createCampaign(topProduct)
        }
      })
    }
  }
  
  // Viral coefficient insight
  if (shareAnalytics.value.viralCoefficient < 0.5) {
    insights.push({
      id: 'low-viral',
      type: 'info',
      title: 'Share Feature Underutilized',
      description: 'The viral coefficient is low. Consider incentives for sharing wishlists.',
      action: {
        label: 'Plan Share Campaign',
        handler: () => planShareCampaign()
      }
    })
  }
  
  // Segment opportunity
  const powerUsers = customerSegments.value.power
  if (powerUsers && powerUsers.percentage < 10) {
    insights.push({
      id: 'grow-power-users',
      type: 'success',
      title: 'Power User Potential',
      description: `Only ${powerUsers.percentage}% are power users. They generate €${powerUsers.avgRevenue} average revenue.`,
      action: {
        label: 'Loyalty Program',
        handler: () => createLoyaltyProgram()
      }
    })
  }
  
  return insights
}

async function exportReport() {
  try {
    const report = await analyticsStore.generateReport({
      type: 'executive_summary',
      startDate: dateRange.value.start,
      endDate: dateRange.value.end,
      salesChannelId: selectedChannel.value,
      format: 'pdf'
    })
    
    // Download report
    downloadFile(report.url, report.filename)
    notification.success('Report was created')
  } catch (error) {
    notification.error('Error creating report')
  }
}

function viewProductDetails(product) {
  // Navigate to detailed product analytics
  router.push({
    name: 'analytics-product',
    params: { productId: product.productId }
  })
}

function createCampaign(product) {
  // Open campaign creation with pre-filled data
  router.push({
    name: 'marketing-campaign-create',
    query: {
      type: 'wishlist',
      productId: product.productId,
      targetAudience: 'wishlist_users'
    }
  })
}

function formatNumber(value) {
  return new Intl.NumberFormat('en-US').format(value)
}
</script>
```

## Analytics Events and Tracking

```javascript
// Analytics tracking mixin
export const analyticsTracking = {
    methods: {
        trackWishlistEvent(eventType, data = {}) {
            // Internal analytics
            this.$store.dispatch('analytics/trackEvent', {
                eventType,
                data: {
                    ...data,
                    timestamp: new Date().toISOString(),
                    sessionId: this.getSessionId(),
                    userId: this.$store.state.auth.user?.id
                }
            })

            // Google Analytics
            if (window.gtag) {
                window.gtag('event', eventType, {
                    event_category: 'wishlist',
                    ...data
                })
            }

            // Facebook Pixel
            if (window.fbq) {
                window.fbq('trackCustom', 'WishlistEvent', {
                    eventType,
                    ...data
                })
            }
        },

        trackProductView(productId) {
            this.trackWishlistEvent('product_viewed', { productId })
        },

        trackWishlistCreated(wishlistId, itemCount = 0) {
            this.trackWishlistEvent('wishlist_created', {
                wishlistId,
                itemCount
            })
        },

        trackItemAdded(wishlistId, productId, value) {
            this.trackWishlistEvent('item_added', {
                wishlistId,
                productId,
                value,
                currency: 'EUR'
            })
        },

        trackItemRemoved(wishlistId, productId) {
            this.trackWishlistEvent('item_removed', {
                wishlistId,
                productId
            })
        },

        trackWishlistShared(wishlistId, method, itemCount) {
            this.trackWishlistEvent('wishlist_shared', {
                wishlistId,
                method,
                itemCount
            })
        },

        trackConversion(wishlistId, productId, value) {
            this.trackWishlistEvent('wishlist_conversion', {
                wishlistId,
                productId,
                value,
                currency: 'EUR'
            })

            // Enhanced e-commerce tracking
            if (window.gtag) {
                window.gtag('event', 'purchase', {
                    transaction_id: this.getOrderId(),
                    value: value,
                    currency: 'EUR',
                    items: [{
                        item_id: productId,
                        item_category: 'wishlist',
                        price: value,
                        quantity: 1
                    }]
                })
            }
        }
    }
}
```

## Database Schema for Analytics

```sql
-- Analytics event log
CREATE TABLE `wishlist_analytics_event` (
                                            `id` BINARY(16) NOT NULL,
                                            `event_type` VARCHAR(50) NOT NULL,
                                            `event_data` JSON NOT NULL,
                                            `customer_id` BINARY(16),
                                            `session_id` VARCHAR(128),
                                            `sales_channel_id` BINARY(16),
                                            `created_at` DATETIME(3) NOT NULL,
                                            PRIMARY KEY (`id`),
                                            KEY `idx.analytics_event.type` (`event_type`),
                                            KEY `idx.analytics_event.customer` (`customer_id`),
                                            KEY `idx.analytics_event.created` (`created_at`),
                                            KEY `idx.analytics_event.channel` (`sales_channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (YEAR(created_at)) (
  PARTITION p2024 VALUES LESS THAN (2025),
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION pfuture VALUES LESS THAN MAXVALUE
);

-- Pre-aggregated analytics data
CREATE TABLE `wishlist_analytics_daily` (
                                            `id` BINARY(16) NOT NULL,
                                            `date` DATE NOT NULL,
                                            `sales_channel_id` BINARY(16),
                                            `metric_type` VARCHAR(50) NOT NULL,
                                            `metric_value` DECIMAL(10,2) NOT NULL,
                                            `dimensions` JSON,
                                            PRIMARY KEY (`id`),
                                            UNIQUE KEY `uniq.analytics_daily` (`date`, `sales_channel_id`, `metric_type`),
                                            KEY `idx.analytics_daily.date` (`date`),
                                            KEY `idx.analytics_daily.type` (`metric_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product performance metrics
CREATE TABLE `wishlist_product_performance` (
                                                `id` BINARY(16) NOT NULL,
                                                `product_id` BINARY(16) NOT NULL,
                                                `period_start` DATE NOT NULL,
                                                `period_end` DATE NOT NULL,
                                                `wishlist_additions` INT DEFAULT 0,
                                                `wishlist_removals` INT DEFAULT 0,
                                                `unique_customers` INT DEFAULT 0,
                                                `conversions` INT DEFAULT 0,
                                                `conversion_value` DECIMAL(10,2) DEFAULT 0.00,
                                                `avg_days_to_convert` DECIMAL(5,1),
                                                `share_count` INT DEFAULT 0,
                                                PRIMARY KEY (`id`),
                                                UNIQUE KEY `uniq.product_performance` (`product_id`, `period_start`, `period_end`),
                                                KEY `idx.product_performance.product` (`product_id`),
                                                KEY `idx.product_performance.period` (`period_start`, `period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Performance Considerations

### Data Aggregation Strategy

```php
// Scheduled task for pre-aggregation
class AggregateAnalyticsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'wishlist.aggregate_analytics';
    }
    
    public static function getDefaultInterval(): int
    {
        return 3600; // Hourly
    }
}

// Aggregation service
class AnalyticsAggregationService
{
    public function aggregateDaily(\DateTime $date, Context $context): void
    {
        // Aggregate event data into daily metrics
        $this->aggregateWishlistMetrics($date, $context);
        $this->aggregateProductMetrics($date, $context);
        $this->aggregateCustomerMetrics($date, $context);
        $this->aggregateConversionMetrics($date, $context);
        
        // Clean up old event data
        $this->cleanupOldEvents($date, $context);
    }
}
```