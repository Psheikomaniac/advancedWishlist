# Analytics Feature Documentation

## Overview

Das Analytics Feature bietet umfassende Einblicke in das Wishlist-Verhalten der Kunden. Shop-Betreiber können Trends erkennen, beliebte Produkte identifizieren und Conversion-Raten optimieren.

## User Stories

### Als Shop-Betreiber möchte ich...
1. **Top-Produkte sehen** die am häufigsten auf Wunschlisten stehen
2. **Conversion-Raten** von Wishlist zu Kauf verstehen
3. **Trends erkennen** bei Produktpopularität
4. **Kundenverhalten** analysieren
5. **ROI messen** des Wishlist-Features

### Als Marketing-Manager möchte ich...
1. **Kampagnen optimieren** basierend auf Wishlist-Daten
2. **Zielgruppen segmentieren** nach Wishlist-Verhalten
3. **Saisonale Trends** identifizieren
4. **Preisstrategien** entwickeln

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
          <option value="">Alle Verkaufskanäle</option>
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
      <h3>Top Wishlist Produkte</h3>
      
      <div class="products-table">
        <table>
          <thead>
            <tr>
              <th>Rang</th>
              <th>Produkt</th>
              <th>Wishlist Count</th>
              <th>Conversion Rate</th>
              <th>Trend</th>
              <th>Aktionen</th>
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
                <span class="customers">({{ product.uniqueCustomers }} Kunden)</span>
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
        Alle Produkte anzeigen →
      </button>
    </div>
    
    <!-- Customer Segments -->
    <div class="segments-section">
      <h3>Kundensegmente</h3>
      
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
      <h3>Insights & Empfehlungen</h3>
      
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
  { label: 'Letzte 7 Tage', days: 7 },
  { label: 'Letzte 30 Tage', days: 30 },
  { label: 'Letzte 90 Tage', days: 90 },
  { label: 'Dieses Jahr', type: 'year' }
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
    notification.error('Fehler beim Laden der Analytics')
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
        title: 'Niedrige Conversion bei Top-Produkt',
        description: `"${topProduct.name}" ist sehr beliebt auf Wishlists, hat aber nur ${topProduct.conversionRate}% Conversion Rate.`,
        action: {
          label: 'Kampagne erstellen',
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
      title: 'Share-Feature wenig genutzt',
      description: 'Der virale Koeffizient ist niedrig. Überlegen Sie Anreize für das Teilen von Wishlists.',
      action: {
        label: 'Share-Kampagne planen',
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
      description: `Nur ${powerUsers.percentage}% sind Power User. Diese generieren ${powerUsers.avgRevenue}€ Durchschnittsumsatz.`,
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
    notification.success('Report wurde erstellt')
  } catch (error) {
    notification.error('Fehler beim Erstellen des Reports')
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
  return new Intl.NumberFormat('de-DE').format(value)
}
</script>

<style scoped>
.wishlist-analytics-dashboard {
  padding: 2rem;
  background: #f5f5f5;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.controls {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.charts-row {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1rem;
  margin-bottom: 2rem;
}

.chart-container {
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.chart-container h3 {
  margin-bottom: 1rem;
  color: #333;
}

.top-products-section {
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-bottom: 2rem;
}

.products-table {
  overflow-x: auto;
}

.products-table table {
  width: 100%;
  border-collapse: collapse;
}

.products-table th {
  text-align: left;
  padding: 0.75rem;
  border-bottom: 2px solid #e0e0e0;
  font-weight: 600;
  color: #666;
}

.products-table td {
  padding: 0.75rem;
  border-bottom: 1px solid #f0f0f0;
}

.product-info {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.product-info img {
  width: 40px;
  height: 40px;
  object-fit: cover;
  border-radius: 4px;
}

.sku {
  display: block;
  font-size: 0.875rem;
  color: #999;
}

.count .customers {
  display: block;
  font-size: 0.875rem;
  color: #666;
}

.trending-up {
  background: #f0fff4;
}

.segments-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}

.share-analytics {
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-bottom: 2rem;
}

.share-metrics {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.share-metrics .metric {
  text-align: center;
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 4px;
}

.share-metrics .label {
  display: block;
  font-size: 0.875rem;
  color: #666;
  margin-bottom: 0.5rem;
}

.share-metrics .value {
  display: block;
  font-size: 1.5rem;
  font-weight: bold;
  color: var(--primary-color);
}

.insights-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1rem;
}

.btn-export {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: var(--primary-color);
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-export:hover {
  background: var(--primary-dark);
}

.btn-icon {
  padding: 0.5rem;
  background: transparent;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
  margin-right: 0.25rem;
}

.btn-icon:hover {
  background: #f0f0f0;
}

.btn-link {
  display: inline-block;
  margin-top: 1rem;
  color: var(--primary-color);
  text-decoration: none;
  font-weight: 500;
}

.btn-link:hover {
  text-decoration: underline;
}
</style>
```

### Analytics Report Generator

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service\Analytics;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Shopware\Core\Framework\Context;

class AnalyticsReportGenerator
{
    public function __construct(
        private WishlistAnalyticsService $analyticsService,
        private TemplateRenderer $templateRenderer,
        private MediaService $mediaService,
        private TranslatorInterface $translator,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Generate executive summary report
     */
    public function generateExecutiveSummary(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $options,
        Context $context
    ): array {
        // Get all analytics data
        $data = $this->analyticsService->getDashboardData(
            $startDate,
            $endDate,
            $options['salesChannelId'] ?? null,
            $context
        );
        
        // Add additional analysis
        $data['insights'] = $this->generateInsights($data);
        $data['recommendations'] = $this->generateRecommendations($data);
        $data['forecast'] = $this->generateForecast($data);
        
        // Generate report based on format
        return match($options['format'] ?? 'pdf') {
            'pdf' => $this->generatePdfReport($data, 'executive_summary', $context),
            'excel' => $this->generateExcelReport($data, 'executive_summary', $context),
            'json' => $this->generateJsonReport($data, 'executive_summary', $context),
            default => throw new \InvalidArgumentException('Unsupported format'),
        };
    }
    
    /**
     * Generate PDF report
     */
    private function generatePdfReport(
        array $data,
        string $template,
        Context $context
    ): array {
        // Render HTML
        $html = $this->templateRenderer->render(
            '@AdvancedWishlist/reports/' . $template . '.html.twig',
            [
                'data' => $data,
                'generatedAt' => new \DateTime(),
                'shopName' => $this->getShopName($context),
            ]
        );
        
        // Generate PDF
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Save to media
        $fileName = sprintf(
            'wishlist_report_%s_%s.pdf',
            $template,
            date('Y-m-d')
        );
        
        $tempFile = tempnam(sys_get_temp_dir(), 'report_');
        file_put_contents($tempFile, $dompdf->output());
        
        $mediaFile = $this->mediaService->saveFile(
            $tempFile,
            $fileName,
            'application/pdf',
            'wishlist-reports',
            $context
        );
        
        unlink($tempFile);
        
        return [
            'url' => $mediaFile->getUrl(),
            'filename' => $fileName,
            'size' => $mediaFile->getFileSize(),
            'mediaId' => $mediaFile->getId(),
        ];
    }
    
    /**
     * Generate Excel report with charts
     */
    private function generateExcelReport(
        array $data,
        string $template,
        Context $context
    ): array {
        $spreadsheet = new Spreadsheet();
        
        // Overview Sheet
        $overviewSheet = $spreadsheet->getActiveSheet();
        $overviewSheet->setTitle('Overview');
        $this->fillOverviewSheet($overviewSheet, $data['overview']);
        
        // Top Products Sheet
        $productsSheet = $spreadsheet->createSheet();
        $productsSheet->setTitle('Top Products');
        $this->fillProductsSheet($productsSheet, $data['topProducts']);
        
        // Trends Sheet with Chart
        $trendsSheet = $spreadsheet->createSheet();
        $trendsSheet->setTitle('Trends');
        $this->fillTrendsSheet($trendsSheet, $data['trends']);
        $this->addTrendChart($trendsSheet, $data['trends']);
        
        // Customer Segments Sheet
        $segmentsSheet = $spreadsheet->createSheet();
        $segmentsSheet->setTitle('Customer Segments');
        $this->fillSegmentsSheet($segmentsSheet, $data['customerSegments']);
        
        // Save file
        $writer = new Xlsx($spreadsheet);
        $fileName = sprintf(
            'wishlist_analytics_%s_%s.xlsx',
            $template,
            date('Y-m-d')
        );
        
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
        $writer->save($tempFile);
        
        $mediaFile = $this->mediaService->saveFile(
            $tempFile,
            $fileName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'wishlist-reports',
            $context
        );
        
        unlink($tempFile);
        
        return [
            'url' => $mediaFile->getUrl(),
            'filename' => $fileName,
            'size' => $mediaFile->getFileSize(),
            'mediaId' => $mediaFile->getId(),
        ];
    }
    
    /**
     * Fill overview sheet
     */
    private function fillOverviewSheet($sheet, array $overview): void
    {
        $sheet->setCellValue('A1', 'Wishlist Analytics Overview');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        
        $metrics = [
            ['Total Wishlists', $overview['totalWishlists']],
            ['Unique Customers', $overview['uniqueCustomers']],
            ['Total Items', $overview['totalItems']],
            ['Avg Items per Wishlist', $overview['avgItemsPerWishlist']],
            ['Conversion Rate', $overview['conversionRate'] . '%'],
            ['Converted Revenue', $this->formatCurrency($overview['convertedRevenue'])],
            ['Avg Days to Convert', $overview['avgDaysToConvert']],
        ];
        
        $row = 3;
        foreach ($metrics as $metric) {
            $sheet->setCellValue('A' . $row, $metric[0]);
            $sheet->setCellValue('B' . $row, $metric[1]);
            $row++;
        }
        
        // Style
        $sheet->getStyle('A3:A' . ($row - 1))->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(20);
    }
    
    /**
     * Add trend chart
     */
    private function addTrendChart($sheet, array $trends): void
    {
        if (empty($trends)) {
            return;
        }
        
        // Prepare data
        $rowCount = count($trends) + 1;
        
        // Data series
        $dataSeriesLabels = [
            new DataSeriesValues('String', 'Trends!$B$1', null, 1), // Wishlists
            new DataSeriesValues('String', 'Trends!$C$1', null, 1), // Items
        ];
        
        $xAxisTickValues = [
            new DataSeriesValues('String', 'Trends!$A$2:$A$' . $rowCount, null, count($trends)),
        ];
        
        $dataSeriesValues = [
            new DataSeriesValues('Number', 'Trends!$B$2:$B$' . $rowCount, null, count($trends)),
            new DataSeriesValues('Number', 'Trends!$C$2:$C$' . $rowCount, null, count($trends)),
        ];
        
        // Build the dataseries
        $series = new DataSeries(
            DataSeries::TYPE_LINECHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );
        
        // Create the chart
        $plotArea = new PlotArea(null, [$series]);
        $title = new Title('Wishlist Trends');
        $chart = new Chart(
            'trendChart',
            $title,
            null,
            $plotArea,
            true,
            0,
            null,
            null
        );
        
        // Set the position and size
        $chart->setTopLeftPosition('E2');
        $chart->setBottomRightPosition('M20');
        
        // Add the chart to the worksheet
        $sheet->addChart($chart);
    }
    
    /**
     * Generate insights
     */
    private function generateInsights(array $data): array
    {
        $insights = [];
        
        // Conversion insight
        if ($data['overview']['conversionRate'] < 20) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Low Conversion Rate',
                'description' => sprintf(
                    'The wishlist to purchase conversion rate is %.1f%%, which is below the industry average of 20-25%%.',
                    $data['overview']['conversionRate']
                ),
                'recommendation' => 'Consider implementing price drop notifications and abandoned wishlist campaigns.',
            ];
        }
        
        // Top product insight
        if (!empty($data['topProducts'])) {
            $topProduct = $data['topProducts'][0];
            if ($topProduct['wishlistCount'] > 100 && $topProduct['conversionRate'] < 15) {
                $insights[] = [
                    'type' => 'opportunity',
                    'title' => 'High Interest, Low Conversion Product',
                    'description' => sprintf(
                        '"%s" has been added to %d wishlists but has only a %.1f%% conversion rate.',
                        $topProduct['name'],
                        $topProduct['wishlistCount'],
                        $topProduct['conversionRate']
                    ),
                    'recommendation' => 'This product is a prime candidate for targeted marketing campaigns or pricing adjustments.',
                ];
            }
        }
        
        // Viral coefficient insight
        if ($data['shareAnalytics']['viralCoefficient'] < 0.5) {
            $insights[] = [
                'type' => 'improvement',
                'title' => 'Low Social Sharing',
                'description' => sprintf(
                    'The viral coefficient is %.2f, indicating low social sharing activity.',
                    $data['shareAnalytics']['viralCoefficient']
                ),
                'recommendation' => 'Implement sharing incentives or make the share feature more prominent.',
            ];
        }
        
        return $insights;
    }
    
    /**
     * Generate recommendations
     */
    private function generateRecommendations(array $data): array
    {
        $recommendations = [];
        
        // Based on customer segments
        $powerUsers = $data['customerSegments']['power'] ?? null;
        if ($powerUsers && $powerUsers['percentage'] < 10) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Grow Power User Base',
                'description' => 'Only ' . $powerUsers['percentage'] . '% of customers are power users, but they generate ' . $powerUsers['avgRevenue'] . ' in average revenue.',
                'actions' => [
                    'Create a VIP wishlist program with exclusive benefits',
                    'Offer early access to new products for active wishlist users',
                    'Implement gamification elements to encourage wishlist creation',
                ],
            ];
        }
        
        // Based on trends
        $recentTrend = end($data['trends']);
        $oldestTrend = reset($data['trends']);
        
        if ($recentTrend && $oldestTrend) {
            $growthRate = (($recentTrend['wishlists'] - $oldestTrend['wishlists']) / $oldestTrend['wishlists']) * 100;
            
            if ($growthRate < 10) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'title' => 'Accelerate Wishlist Adoption',
                    'description' => sprintf('Wishlist creation has grown only %.1f%% over the analyzed period.', $growthRate),
                    'actions' => [
                        'Add wishlist CTA to product pages',
                        'Create email campaigns highlighting the wishlist feature',
                        'Offer a discount for first wishlist creation',
                    ],
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Generate forecast
     */
    private function generateForecast(array $data): array
    {
        // Simple linear projection based on trends
        $trends = $data['trends'];
        if (count($trends) < 3) {
            return [];
        }
        
        // Calculate growth rate
        $firstPeriod = array_slice($trends, 0, 3);
        $lastPeriod = array_slice($trends, -3);
        
        $firstAvg = array_sum(array_column($firstPeriod, 'wishlists')) / 3;
        $lastAvg = array_sum(array_column($lastPeriod, 'wishlists')) / 3;
        
        $periods = count($trends);
        $growthRate = pow($lastAvg / $firstAvg, 1 / $periods);
        
        // Project next 3 months
        $forecast = [];
        $lastValue = end($trends)['wishlists'];
        
        for ($i = 1; $i <= 3; $i++) {
            $projectedValue = round($lastValue * pow($growthRate, $i));
            $forecast[] = [
                'period' => '+' . $i . ' month',
                'wishlists' => $projectedValue,
                'confidence' => max(50, 90 - ($i * 10)), // Decrease confidence over time
            ];
        }
        
        return $forecast;
    }
}
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