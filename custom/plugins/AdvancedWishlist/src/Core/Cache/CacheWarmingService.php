<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Cache;

use AdvancedWishlist\Core\Service\WishlistCrudService;
use AdvancedWishlist\Core\Performance\PerformanceMonitorService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Psr\Log\LoggerInterface;

/**
 * Automated cache warming service implementing the performance optimization strategy.
 * Pre-warms frequently accessed data to improve cache hit ratios and reduce database load.
 */
class CacheWarmingService
{
    private const BATCH_SIZE = 50;
    private const MAX_CUSTOMERS_PER_BATCH = 100;
    private const POPULAR_PRODUCTS_LIMIT = 200;
    private const RECENT_ACTIVITY_DAYS = 7;
    
    private array $warmingStrategies = [];
    private array $warmingStats = [];
    
    public function __construct(
        private readonly MultiLevelCacheService $cacheService,
        private readonly WishlistCrudService $wishlistCrudService,
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $wishlistRepository,
        private readonly PerformanceMonitorService $performanceMonitor,
        private readonly LoggerInterface $logger,
        private readonly array $config = []
    ) {
        $this->initializeWarmingStrategies();
    }

    /**
     * Execute full cache warming process.
     */
    public function warmCache(array $strategies = null): array
    {
        $startTime = microtime(true);
        $strategies = $strategies ?? array_keys($this->warmingStrategies);
        
        return $this->performanceMonitor->trackOperation('cache_warming_full', function() use ($strategies) {
            $results = [];
            
            foreach ($strategies as $strategy) {
                if (!isset($this->warmingStrategies[$strategy])) {
                    $this->logger->warning('Unknown cache warming strategy', ['strategy' => $strategy]);
                    continue;
                }
                
                try {
                    $strategyResult = $this->executeWarmingStrategy($strategy);
                    $results[$strategy] = $strategyResult;
                    
                    $this->logger->info('Cache warming strategy completed', [
                        'strategy' => $strategy,
                        'result' => $strategyResult
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Cache warming strategy failed', [
                        'strategy' => $strategy,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    $results[$strategy] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'items_warmed' => 0
                    ];
                }
            }
            
            $totalDuration = microtime(true) - $startTime;
            $totalItemsWarmed = array_sum(array_column($results, 'items_warmed'));
            
            $summary = [
                'strategies_executed' => count($strategies),
                'total_items_warmed' => $totalItemsWarmed,
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'average_items_per_second' => $totalDuration > 0 ? round($totalItemsWarmed / $totalDuration, 2) : 0,
                'strategy_results' => $results,
                'timestamp' => time()
            ];
            
            $this->warmingStats = $summary;
            
            return $summary;
        });
    }

    /**
     * Warm cache for specific customer's data.
     */
    public function warmCustomerCache(string $customerId, Context $context): array
    {
        return $this->performanceMonitor->trackOperation('cache_warming_customer', function() use ($customerId, $context) {
            $itemsWarmed = 0;
            
            try {
                // Warm customer's wishlists
                $wishlists = $this->wishlistCrudService->getWishlists(
                    $customerId,
                    new Criteria(),
                    $context
                );
                
                foreach ($wishlists['wishlists'] as $wishlist) {
                    $cacheKey = "customer_wishlist:{$customerId}:{$wishlist['id']}";
                    $this->cacheService->set($cacheKey, $wishlist, 300);
                    $itemsWarmed++;
                }
                
                // Warm customer's default wishlist
                $defaultWishlist = $this->wishlistCrudService->getOrCreateDefaultWishlist($customerId, $context);
                $defaultCacheKey = "customer_default_wishlist:{$customerId}";
                $this->cacheService->set($defaultCacheKey, $defaultWishlist, 600);
                $itemsWarmed++;
                
                // Warm customer profile data
                $customer = $this->customerRepository->search(
                    new Criteria([$customerId]),
                    $context
                )->first();
                
                if ($customer) {
                    $customerCacheKey = "customer_profile:{$customerId}";
                    $this->cacheService->set($customerCacheKey, $customer, 900);
                    $itemsWarmed++;
                }
                
                $this->logger->info('Customer cache warmed successfully', [
                    'customer_id' => $customerId,
                    'items_warmed' => $itemsWarmed
                ]);
                
                return [
                    'success' => true,
                    'customer_id' => $customerId,
                    'items_warmed' => $itemsWarmed
                ];
            } catch (\Exception $e) {
                $this->logger->error('Customer cache warming failed', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
                
                return [
                    'success' => false,
                    'customer_id' => $customerId,
                    'items_warmed' => $itemsWarmed,
                    'error' => $e->getMessage()
                ];
            }
        });
    }

    /**
     * Warm cache for popular products.
     */
    public function warmPopularProductsCache(Context $context): array
    {
        return $this->performanceMonitor->trackOperation('cache_warming_popular_products', function() use ($context) {
            $itemsWarmed = 0;
            
            try {
                // Get popular products based on wishlist frequency
                $popularProducts = $this->getPopularProducts($context);
                
                foreach ($popularProducts as $product) {
                    $productCacheKey = "product_data:{$product['id']}";
                    $this->cacheService->set($productCacheKey, $product, 1800); // 30 minutes
                    $itemsWarmed++;
                    
                    // Warm product pricing data
                    if (isset($product['prices']) && !empty($product['prices'])) {
                        $priceCacheKey = "product_prices:{$product['id']}";
                        $this->cacheService->set($priceCacheKey, $product['prices'], 900); // 15 minutes
                        $itemsWarmed++;
                    }
                }
                
                $this->logger->info('Popular products cache warmed', [
                    'products_count' => count($popularProducts),
                    'items_warmed' => $itemsWarmed
                ]);
                
                return [
                    'success' => true,
                    'products_count' => count($popularProducts),
                    'items_warmed' => $itemsWarmed
                ];
            } catch (\Exception $e) {
                $this->logger->error('Popular products cache warming failed', [
                    'error' => $e->getMessage()
                ]);
                
                return [
                    'success' => false,
                    'items_warmed' => $itemsWarmed,
                    'error' => $e->getMessage()
                ];
            }
        });
    }

    /**
     * Warm cache for recently active customers.
     */
    public function warmRecentActiveCustomersCache(Context $context): array
    {
        return $this->performanceMonitor->trackOperation('cache_warming_recent_customers', function() use ($context) {
            $itemsWarmed = 0;
            
            try {
                $recentActiveCustomers = $this->getRecentActiveCustomers($context);
                
                $batches = array_chunk($recentActiveCustomers, self::BATCH_SIZE);
                
                foreach ($batches as $batch) {
                    foreach ($batch as $customer) {
                        $result = $this->warmCustomerCache($customer['id'], $context);
                        $itemsWarmed += $result['items_warmed'];
                    }
                    
                    // Small delay between batches to avoid overwhelming the system
                    usleep(100000); // 100ms
                }
                
                $this->logger->info('Recent active customers cache warmed', [
                    'customers_count' => count($recentActiveCustomers),
                    'items_warmed' => $itemsWarmed
                ]);
                
                return [
                    'success' => true,
                    'customers_count' => count($recentActiveCustomers),
                    'items_warmed' => $itemsWarmed
                ];
            } catch (\Exception $e) {
                $this->logger->error('Recent active customers cache warming failed', [
                    'error' => $e->getMessage()
                ]);
                
                return [
                    'success' => false,
                    'items_warmed' => $itemsWarmed,
                    'error' => $e->getMessage()
                ];
            }
        });
    }

    /**
     * Warm cache for wishlist analytics data.
     */
    public function warmAnalyticsCache(Context $context): array
    {
        return $this->performanceMonitor->trackOperation('cache_warming_analytics', function() use ($context) {
            $itemsWarmed = 0;
            
            try {
                // Warm wishlist counts by type
                $wishlistStats = $this->getWishlistStatistics($context);
                $statsCacheKey = "wishlist_statistics";
                $this->cacheService->set($statsCacheKey, $wishlistStats, 3600); // 1 hour
                $itemsWarmed++;
                
                // Warm popular wishlist names
                $popularNames = $this->getPopularWishlistNames($context);
                $namesCacheKey = "popular_wishlist_names";
                $this->cacheService->set($namesCacheKey, $popularNames, 7200); // 2 hours
                $itemsWarmed++;
                
                // Warm daily activity metrics
                $dailyMetrics = $this->getDailyActivityMetrics($context);
                $metricsCacheKey = "daily_wishlist_metrics:" . date('Y-m-d');
                $this->cacheService->set($metricsCacheKey, $dailyMetrics, 1800); // 30 minutes
                $itemsWarmed++;
                
                $this->logger->info('Analytics cache warmed', [
                    'items_warmed' => $itemsWarmed
                ]);
                
                return [
                    'success' => true,
                    'items_warmed' => $itemsWarmed
                ];
            } catch (\Exception $e) {
                $this->logger->error('Analytics cache warming failed', [
                    'error' => $e->getMessage()
                ]);
                
                return [
                    'success' => false,
                    'items_warmed' => $itemsWarmed,
                    'error' => $e->getMessage()
                ];
            }
        });
    }

    /**
     * Get cache warming statistics.
     */
    public function getWarmingStats(): array
    {
        return $this->warmingStats;
    }

    /**
     * Schedule automatic cache warming.
     */
    public function scheduleAutoWarming(array $config = []): bool
    {
        try {
            $defaultConfig = [
                'interval_hours' => 6,
                'strategies' => ['recent_active_customers', 'popular_products', 'analytics'],
                'max_execution_time' => 3600, // 1 hour
                'batch_size' => self::BATCH_SIZE
            ];
            
            $config = array_merge($defaultConfig, $config);
            
            // In a real implementation, this would schedule the warming via cron or message queue
            $this->logger->info('Cache warming scheduled', [
                'config' => $config
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to schedule cache warming', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Initialize warming strategies.
     */
    private function initializeWarmingStrategies(): void
    {
        $this->warmingStrategies = [
            'recent_active_customers' => [
                'description' => 'Warm cache for customers active in the last 7 days',
                'priority' => 'high',
                'execution_method' => 'warmRecentActiveCustomersCache'
            ],
            'popular_products' => [
                'description' => 'Warm cache for most popular products in wishlists',
                'priority' => 'high',
                'execution_method' => 'warmPopularProductsCache'
            ],
            'analytics' => [
                'description' => 'Warm cache for analytics and statistics data',
                'priority' => 'medium',
                'execution_method' => 'warmAnalyticsCache'
            ],
            'system_defaults' => [
                'description' => 'Warm cache for system default data',
                'priority' => 'low',
                'execution_method' => 'warmSystemDefaultsCache'
            ]
        ];
    }

    /**
     * Execute a specific warming strategy.
     */
    private function executeWarmingStrategy(string $strategy): array
    {
        if (!isset($this->warmingStrategies[$strategy])) {
            throw new \InvalidArgumentException("Unknown warming strategy: {$strategy}");
        }
        
        $strategyConfig = $this->warmingStrategies[$strategy];
        $method = $strategyConfig['execution_method'];
        
        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException("Warming method does not exist: {$method}");
        }
        
        return $this->$method(Context::createDefaultContext());
    }

    /**
     * Get popular products based on wishlist frequency.
     */
    private function getPopularProducts(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('product')
                 ->addAssociation('product.prices')
                 ->addAssociation('product.cover')
                 ->setLimit(self::POPULAR_PRODUCTS_LIMIT);
        
        // In a real implementation, this would use a more sophisticated query
        // to find products with the highest wishlist counts
        $wishlistItems = $this->wishlistRepository->search($criteria, $context);
        
        $productFrequency = [];
        foreach ($wishlistItems as $item) {
            $productId = $item->getProductId();
            $productFrequency[$productId] = ($productFrequency[$productId] ?? 0) + 1;
        }
        
        // Sort by frequency and get top products
        arsort($productFrequency);
        $topProductIds = array_slice(array_keys($productFrequency), 0, self::POPULAR_PRODUCTS_LIMIT);
        
        // Get full product data
        $productCriteria = new Criteria($topProductIds);
        $productCriteria->addAssociation('prices')
                       ->addAssociation('cover')
                       ->addAssociation('categories');
        
        $products = $this->productRepository->search($productCriteria, $context);
        
        return $products->getElements();
    }

    /**
     * Get recently active customers.
     */
    private function getRecentActiveCustomers(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('updatedAt', [
            'gte' => (new \DateTime('-' . self::RECENT_ACTIVITY_DAYS . ' days'))->format('Y-m-d H:i:s')
        ]))
        ->addSorting(new FieldSorting('updatedAt', FieldSorting::DESCENDING))
        ->setLimit(self::MAX_CUSTOMERS_PER_BATCH);
        
        $result = $this->customerRepository->search($criteria, $context);
        
        return $result->getElements();
    }

    /**
     * Get wishlist statistics.
     */
    private function getWishlistStatistics(Context $context): array
    {
        // In a real implementation, this would use aggregation queries
        $criteria = new Criteria();
        $totalWishlists = $this->wishlistRepository->search($criteria, $context)->getTotal();
        
        $privateWishlists = $this->wishlistRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('type', 'private')),
            $context
        )->getTotal();
        
        $publicWishlists = $this->wishlistRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('type', 'public')),
            $context
        )->getTotal();
        
        return [
            'total_wishlists' => $totalWishlists,
            'private_wishlists' => $privateWishlists,
            'public_wishlists' => $publicWishlists,
            'shared_wishlists' => $totalWishlists - $privateWishlists - $publicWishlists,
            'generated_at' => time()
        ];
    }

    /**
     * Get popular wishlist names.
     */
    private function getPopularWishlistNames(Context $context): array
    {
        // This would typically use aggregation to find most common names
        // For now, return a simplified implementation
        $criteria = new Criteria();
        $criteria->setLimit(1000);
        
        $wishlists = $this->wishlistRepository->search($criteria, $context);
        
        $nameFrequency = [];
        foreach ($wishlists as $wishlist) {
            $name = $wishlist->getName();
            if ($name && $name !== 'My Wishlist') { // Exclude default names
                $nameFrequency[$name] = ($nameFrequency[$name] ?? 0) + 1;
            }
        }
        
        arsort($nameFrequency);
        
        return array_slice($nameFrequency, 0, 50, true);
    }

    /**
     * Get daily activity metrics.
     */
    private function getDailyActivityMetrics(Context $context): array
    {
        $today = new \DateTime();
        $todayStart = $today->format('Y-m-d 00:00:00');
        $todayEnd = $today->format('Y-m-d 23:59:59');
        
        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('createdAt', [
            'gte' => $todayStart,
            'lte' => $todayEnd
        ]));
        
        $newWishlists = $this->wishlistRepository->search($criteria, $context)->getTotal();
        
        return [
            'date' => $today->format('Y-m-d'),
            'new_wishlists' => $newWishlists,
            'generated_at' => time()
        ];
    }

    /**
     * Warm system defaults cache.
     */
    private function warmSystemDefaultsCache(Context $context): array
    {
        $itemsWarmed = 0;
        
        try {
            // Warm configuration data
            $systemConfig = [
                'max_items_per_wishlist' => 100,
                'default_wishlist_type' => 'private',
                'enable_sharing' => true,
                'cache_ttl_settings' => [
                    'wishlists' => 300,
                    'products' => 900,
                    'customers' => 600
                ]
            ];
            
            $configCacheKey = "system_configuration";
            $this->cacheService->set($configCacheKey, $systemConfig, 7200); // 2 hours
            $itemsWarmed++;
            
            $this->logger->info('System defaults cache warmed', [
                'items_warmed' => $itemsWarmed
            ]);
            
            return [
                'success' => true,
                'items_warmed' => $itemsWarmed
            ];
        } catch (\Exception $e) {
            $this->logger->error('System defaults cache warming failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'items_warmed' => $itemsWarmed,
                'error' => $e->getMessage()
            ];
        }
    }
}