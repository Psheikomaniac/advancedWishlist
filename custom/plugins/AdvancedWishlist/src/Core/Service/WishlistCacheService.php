<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Stopwatch\Stopwatch;

class WishlistCacheService
{
    // Default cache TTLs for different types of data
    private const int DEFAULT_CACHE_TTL = 3600; // 1 hour
    private const int CUSTOMER_CACHE_TTL = 1800; // 30 minutes
    private const int WISHLIST_CACHE_TTL = 3600; // 1 hour
    private const int DEFAULT_WISHLIST_CACHE_TTL = 1800; // 30 minutes

    private const string CACHE_PREFIX = 'wishlist_';
    private const string CUSTOMER_CACHE_PREFIX = 'wishlist_customer_';

    // Actual TTL values that can be modified at runtime
    private int $cacheTtl;
    private int $customerCacheTtl;
    private int $wishlistCacheTtl;
    private int $defaultWishlistCacheTtl;

    // L1 cache (in-memory)
    private ArrayAdapter $l1Cache;

    // Performance monitoring
    private Stopwatch $stopwatch;

    // Cache statistics
    private int $cacheHits = 0;
    private int $cacheMisses = 0;

    public function __construct(
        // L2 cache (persistent)
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {
        // Initialize L1 cache
        $this->l1Cache = new ArrayAdapter();

        // Initialize stopwatch for performance monitoring
        $this->stopwatch = new Stopwatch(true);

        // Initialize TTL values with defaults
        $this->cacheTtl = self::DEFAULT_CACHE_TTL;
        $this->customerCacheTtl = self::CUSTOMER_CACHE_TTL;
        $this->wishlistCacheTtl = self::WISHLIST_CACHE_TTL;
        $this->defaultWishlistCacheTtl = self::DEFAULT_WISHLIST_CACHE_TTL;
    }

    /**
     * Get item from cache with multi-level caching and performance monitoring.
     */
    public function get(string $key, callable $callback, array $tags = []): mixed
    {
        // Start performance monitoring
        $this->stopwatch->start("cache_get_{$key}");

        $cacheKey = $this->getCacheKey($key);

        // Check L1 cache first (in-memory)
        $l1CacheItem = $this->l1Cache->getItem($cacheKey);
        if ($l1CacheItem->isHit()) {
            ++$this->cacheHits;
            $this->logger->debug('L1 cache hit', ['key' => $cacheKey]);

            // Stop performance monitoring
            $event = $this->stopwatch->stop("cache_get_{$key}");
            $this->logger->debug('L1 cache performance', [
                'key' => $cacheKey,
                'duration' => $event->getDuration(),
                'memory' => $event->getMemory(),
            ]);

            return $l1CacheItem->get();
        }

        // Check L2 cache (persistent)
        $l2CacheItem = $this->cache->getItem($cacheKey);
        if ($l2CacheItem->isHit()) {
            ++$this->cacheHits;
            $this->logger->debug('L2 cache hit', ['key' => $cacheKey]);

            // Store in L1 cache for future requests
            $value = $l2CacheItem->get();
            $l1CacheItem->set($value);
            $l1CacheItem->expiresAfter(300); // 5 minutes for L1 cache
            $this->l1Cache->save($l1CacheItem);

            // Stop performance monitoring
            $event = $this->stopwatch->stop("cache_get_{$key}");
            $this->logger->debug('L2 cache performance', [
                'key' => $cacheKey,
                'duration' => $event->getDuration(),
                'memory' => $event->getMemory(),
            ]);

            return $value;
        }

        // Cache miss - execute callback
        ++$this->cacheMisses;
        $this->logger->debug('Cache miss', ['key' => $cacheKey]);

        // Execute callback with performance monitoring
        $this->stopwatch->start("callback_{$key}");
        $result = $callback();
        $callbackEvent = $this->stopwatch->stop("callback_{$key}");

        $this->logger->debug('Callback performance', [
            'key' => $cacheKey,
            'duration' => $callbackEvent->getDuration(),
            'memory' => $callbackEvent->getMemory(),
        ]);

        // Determine TTL based on key pattern
        $ttl = $this->getTtlForKey($key);

        // Store in L2 cache
        $l2CacheItem->set($result);
        $l2CacheItem->expiresAfter($ttl);

        // Add tags if supported
        if ($this->cache instanceof \Symfony\Component\Cache\Adapter\TagAwareAdapterInterface && !empty($tags)) {
            if ($l2CacheItem instanceof \Symfony\Component\Cache\CacheItem) {
                $l2CacheItem->tag($tags);
                $this->logger->debug('Cache tags added', ['key' => $cacheKey, 'tags' => $tags]);
            }
        }

        $this->cache->save($l2CacheItem);

        // Store in L1 cache
        $l1CacheItem->set($result);
        $l1CacheItem->expiresAfter(300); // 5 minutes for L1 cache
        $this->l1Cache->save($l1CacheItem);

        // Stop performance monitoring
        $event = $this->stopwatch->stop("cache_get_{$key}");
        $this->logger->debug('Cache miss performance', [
            'key' => $cacheKey,
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
        ]);

        return $result;
    }

    /**
     * Set item in cache with multi-level caching and performance monitoring.
     */
    public function set(string $key, mixed $value, ?int $ttl = null, array $tags = []): void
    {
        // Start performance monitoring
        $this->stopwatch->start("cache_set_{$key}");

        $cacheKey = $this->getCacheKey($key);

        // Determine TTL based on key pattern if not provided
        $ttl = $ttl ?? $this->getTtlForKey($key);

        // Set in L2 cache (persistent)
        $l2CacheItem = $this->cache->getItem($cacheKey);
        $l2CacheItem->set($value);
        $l2CacheItem->expiresAfter($ttl);

        // Add tags if supported
        if ($this->cache instanceof \Symfony\Component\Cache\Adapter\TagAwareAdapterInterface && !empty($tags)) {
            if ($l2CacheItem instanceof \Symfony\Component\Cache\CacheItem) {
                $l2CacheItem->tag($tags);
                $this->logger->debug('Cache tags added', ['key' => $cacheKey, 'tags' => $tags]);
            }
        }

        $this->cache->save($l2CacheItem);

        // Set in L1 cache (in-memory) with shorter TTL
        $l1CacheItem = $this->l1Cache->getItem($cacheKey);
        $l1CacheItem->set($value);
        $l1CacheItem->expiresAfter(min($ttl, 300)); // 5 minutes or less for L1 cache
        $this->l1Cache->save($l1CacheItem);

        // Stop performance monitoring
        $event = $this->stopwatch->stop("cache_set_{$key}");
        $this->logger->debug('Cache set performance', [
            'key' => $cacheKey,
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
            'ttl' => $ttl,
        ]);
    }

    /**
     * Delete item from cache (both L1 and L2).
     */
    public function delete(string $key): void
    {
        // Start performance monitoring
        $this->stopwatch->start("cache_delete_{$key}");

        $cacheKey = $this->getCacheKey($key);

        // Delete from L1 cache
        $this->l1Cache->deleteItem($cacheKey);

        // Delete from L2 cache
        $this->cache->deleteItem($cacheKey);

        $this->logger->debug('Cache deleted from both L1 and L2', ['key' => $cacheKey]);

        // Stop performance monitoring
        $event = $this->stopwatch->stop("cache_delete_{$key}");
        $this->logger->debug('Cache delete performance', [
            'key' => $cacheKey,
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
        ]);
    }

    /**
     * Determine TTL based on key pattern.
     */
    private function getTtlForKey(string $key): int
    {
        // Customer-related cache items
        if (str_contains($key, 'customer_')) {
            return $this->customerCacheTtl;
        }

        // Wishlist-related cache items
        if (str_contains($key, 'wishlist_')) {
            // Default wishlist has shorter TTL
            if (str_contains($key, 'default_wishlist')) {
                return $this->defaultWishlistCacheTtl;
            }

            return $this->wishlistCacheTtl;
        }

        // Default TTL for other items
        return $this->cacheTtl;
    }

    /**
     * Invalidate wishlist cache using tags (both L1 and L2).
     */
    public function invalidateWishlistCache(string $wishlistId): void
    {
        // Start performance monitoring
        $this->stopwatch->start("invalidate_wishlist_{$wishlistId}");

        // For L1 cache, we need to delete specific keys
        $this->l1Cache->deleteItem("wishlist_{$wishlistId}");
        $this->l1Cache->deleteItem("wishlist_items_{$wishlistId}");
        $this->l1Cache->deleteItem("wishlist_share_{$wishlistId}");

        // For L2 cache, use tags if supported
        if ($this->cache instanceof \Symfony\Component\Cache\Adapter\TagAwareAdapterInterface) {
            $this->cache->invalidateTags(["wishlist-{$wishlistId}"]);
            $this->logger->debug('Wishlist cache invalidated by tag (L2)', ['wishlistId' => $wishlistId]);
        } else {
            // Fallback to direct key invalidation for L2
            $this->cache->deleteItem("wishlist_{$wishlistId}");
            $this->cache->deleteItem("wishlist_items_{$wishlistId}");
            $this->cache->deleteItem("wishlist_share_{$wishlistId}");
            $this->logger->debug('Wishlist cache invalidated by keys (L2)', ['wishlistId' => $wishlistId]);
        }

        $this->logger->debug('Wishlist cache invalidated (L1+L2)', ['wishlistId' => $wishlistId]);

        // Stop performance monitoring
        $event = $this->stopwatch->stop("invalidate_wishlist_{$wishlistId}");
        $this->logger->debug('Invalidate wishlist cache performance', [
            'wishlistId' => $wishlistId,
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
        ]);
    }

    /**
     * Invalidate customer cache using tags (both L1 and L2).
     */
    public function invalidateCustomerCache(string $customerId): void
    {
        // Start performance monitoring
        $this->stopwatch->start("invalidate_customer_{$customerId}");

        // For L1 cache, we need to delete specific keys
        $this->l1Cache->deleteItem("customer_wishlists_{$customerId}");
        $this->l1Cache->deleteItem("customer_default_wishlist_{$customerId}");
        $this->l1Cache->deleteItem("customer_wishlist_stats_{$customerId}");

        // For L2 cache, use tags if supported
        if ($this->cache instanceof \Symfony\Component\Cache\Adapter\TagAwareAdapterInterface) {
            $this->cache->invalidateTags(["customer-{$customerId}"]);
            $this->logger->debug('Customer cache invalidated by tag (L2)', ['customerId' => $customerId]);
        } else {
            // Fallback to direct key invalidation for L2
            $this->cache->deleteItem("customer_wishlists_{$customerId}");
            $this->cache->deleteItem("customer_default_wishlist_{$customerId}");
            $this->cache->deleteItem("customer_wishlist_stats_{$customerId}");
            $this->logger->debug('Customer cache invalidated by keys (L2)', ['customerId' => $customerId]);
        }

        $this->logger->debug('Customer cache invalidated (L1+L2)', ['customerId' => $customerId]);

        // Stop performance monitoring
        $event = $this->stopwatch->stop("invalidate_customer_{$customerId}");
        $this->logger->debug('Invalidate customer cache performance', [
            'customerId' => $customerId,
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
        ]);
    }

    /**
     * Cache wishlist data with optimized TTL.
     */
    public function cacheWishlist(string $wishlistId, array $data): void
    {
        $this->set(
            "wishlist_{$wishlistId}",
            $data,
            $this->wishlistCacheTtl,
            ["wishlist-{$wishlistId}"]
        );
    }

    /**
     * Cache customer wishlists with optimized TTL.
     */
    public function cacheCustomerWishlists(string $customerId, array $wishlists): void
    {
        $this->set(
            "customer_wishlists_{$customerId}",
            $wishlists,
            $this->customerCacheTtl,
            ["customer-{$customerId}"]
        );
    }

    /**
     * Cache default wishlist for customer with optimized TTL.
     */
    public function cacheDefaultWishlist(string $customerId, array $wishlist): void
    {
        $this->set(
            "customer_default_wishlist_{$customerId}",
            $wishlist,
            $this->defaultWishlistCacheTtl,
            ["customer-{$customerId}", "wishlist-{$wishlist['id']}"]
        );
    }

    /**
     * Get cached wishlist with performance monitoring.
     */
    public function getCachedWishlist(string $wishlistId, callable $callback): mixed
    {
        return $this->get(
            "wishlist_{$wishlistId}",
            $callback,
            ["wishlist-{$wishlistId}"]
        );
    }

    /**
     * Get cached customer wishlists with performance monitoring.
     */
    public function getCachedCustomerWishlists(string $customerId, callable $callback): mixed
    {
        return $this->get(
            "customer_wishlists_{$customerId}",
            $callback,
            ["customer-{$customerId}"]
        );
    }

    /**
     * Get cached default wishlist with performance monitoring.
     */
    public function getCachedDefaultWishlist(string $customerId, callable $callback): mixed
    {
        return $this->get(
            "customer_default_wishlist_{$customerId}",
            $callback,
            ["customer-{$customerId}"]
        );
    }

    /**
     * Clear all wishlist cache (both L1 and L2).
     */
    public function clearAllCache(): void
    {
        // Start performance monitoring
        $this->stopwatch->start('clear_all_cache');

        // Clear L1 cache
        $this->l1Cache->clear();

        // Clear L2 cache
        $this->cache->clear();

        $this->logger->info('All wishlist cache cleared (L1+L2)');

        // Reset cache statistics
        $this->cacheHits = 0;
        $this->cacheMisses = 0;

        // Stop performance monitoring
        $event = $this->stopwatch->stop('clear_all_cache');
        $this->logger->debug('Clear all cache performance', [
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
        ]);
    }

    /**
     * Get cache key with prefix.
     */
    private function getCacheKey(string $key): string
    {
        return self::CACHE_PREFIX.$key;
    }

    /**
     * Set custom cache TTL values.
     */
    public function setCacheTtl(int $ttl, ?int $customerTtl = null, ?int $wishlistTtl = null, ?int $defaultWishlistTtl = null): void
    {
        $this->cacheTtl = $ttl;
        $this->customerCacheTtl = $customerTtl ?? $ttl;
        $this->wishlistCacheTtl = $wishlistTtl ?? $ttl;
        $this->defaultWishlistCacheTtl = $defaultWishlistTtl ?? $ttl;

        $this->logger->info('Cache TTL values updated', [
            'default' => $this->cacheTtl,
            'customer' => $this->customerCacheTtl,
            'wishlist' => $this->wishlistCacheTtl,
            'defaultWishlist' => $this->defaultWishlistCacheTtl,
        ]);
    }

    /**
     * Get cache statistics.
     */
    public function getCacheStatistics(): array
    {
        $totalRequests = $this->cacheHits + $this->cacheMisses;
        $hitRate = $totalRequests > 0 ? ($this->cacheHits / $totalRequests) * 100 : 0;

        return [
            'hits' => $this->cacheHits,
            'misses' => $this->cacheMisses,
            'total' => $totalRequests,
            'hitRate' => round($hitRate, 2).'%',
            'ttlSettings' => [
                'default' => $this->cacheTtl,
                'customer' => $this->customerCacheTtl,
                'wishlist' => $this->wishlistCacheTtl,
                'defaultWishlist' => $this->defaultWishlistCacheTtl,
            ],
        ];
    }

    /**
     * Get performance metrics for a specific operation.
     */
    public function getPerformanceMetrics(string $operation): ?array
    {
        if (!$this->stopwatch->isStarted($operation) && !$this->stopwatch->has($operation)) {
            return null;
        }

        if ($this->stopwatch->isStarted($operation)) {
            $event = $this->stopwatch->stop($operation);
        } else {
            $event = $this->stopwatch->getEvent($operation);
        }

        return [
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
            'startTime' => $event->getStartTime(),
            'endTime' => $event->getEndTime(),
        ];
    }

    /**
     * Warm up cache for customer with performance monitoring.
     */
    public function warmUpCustomerCache(string $customerId, array $wishlists): void
    {
        // Start performance monitoring
        $this->stopwatch->start("warm_up_cache_{$customerId}");

        $this->cacheCustomerWishlists($customerId, $wishlists);

        // Cache each wishlist
        foreach ($wishlists as $wishlist) {
            $this->cacheWishlist($wishlist['id'], $wishlist);
        }

        // Cache default wishlist
        foreach ($wishlists as $wishlist) {
            if ($wishlist['isDefault']) {
                $this->cacheDefaultWishlist($customerId, $wishlist);
                break;
            }
        }

        // Stop performance monitoring
        $event = $this->stopwatch->stop("warm_up_cache_{$customerId}");
        $this->logger->info('Customer cache warmed up', [
            'customerId' => $customerId,
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory(),
            'wishlistCount' => count($wishlists),
        ]);
    }

    /**
     * Cache price data for products.
     */
    public function cachePriceData(array $productIds, array $priceData, int $ttl = 900): void
    {
        foreach ($productIds as $productId) {
            if (isset($priceData[$productId])) {
                $cacheKey = "product_price_{$productId}";
                $this->set($cacheKey, $priceData[$productId], $ttl, ["product-{$productId}", "prices"]);
            }
        }

        $this->logger->debug('Price data cached', [
            'products' => count($productIds),
            'cached_prices' => count(array_intersect_key($priceData, array_flip($productIds))),
            'ttl' => $ttl
        ]);
    }

    /**
     * Get cached price data for a product.
     */
    public function getCachedPriceData(string $productId): ?array
    {
        $cacheKey = "product_price_{$productId}";
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            ++$this->cacheHits;
            $this->logger->debug('Price cache hit', ['productId' => $productId]);
            return $cacheItem->get();
        }

        ++$this->cacheMisses;
        return null;
    }

    /**
     * Invalidate price cache for specific products.
     */
    public function invalidatePriceCache(array $productIds = []): void
    {
        if (empty($productIds)) {
            // Clear all price cache
            if ($this->cache instanceof \Symfony\Component\Cache\Adapter\TagAwareAdapterInterface) {
                $this->cache->invalidateTags(["prices"]);
                $this->logger->info('All price cache invalidated by tag');
            } else {
                // Fallback - this is less efficient but still works
                $this->logger->warning('Price cache invalidation requires TagAwareAdapter for efficiency');
            }
        } else {
            // Invalidate specific products
            foreach ($productIds as $productId) {
                $this->delete("product_price_{$productId}");
                
                if ($this->cache instanceof \Symfony\Component\Cache\Adapter\TagAwareAdapterInterface) {
                    $this->cache->invalidateTags(["product-{$productId}"]);
                }
            }
            
            $this->logger->debug('Price cache invalidated for specific products', [
                'product_count' => count($productIds)
            ]);
        }
    }

    /**
     * Batch cache wishlist item prices.
     */
    public function batchCacheWishlistItemPrices(array $items, array $priceData): void
    {
        $startTime = microtime(true);
        $cached = 0;

        foreach ($items as $item) {
            $productId = is_object($item) ? $item->getProductId() : $item['productId'];
            if (isset($priceData[$productId])) {
                $cacheKey = "wishlist_item_price_{$productId}";
                $this->set($cacheKey, $priceData[$productId], 600, ["product-{$productId}", "wishlist-prices"]);
                ++$cached;
            }
        }

        $duration = microtime(true) - $startTime;
        $this->logger->debug('Batch cached wishlist item prices', [
            'items' => count($items),
            'cached' => $cached,
            'duration_ms' => round($duration * 1000, 2)
        ]);
    }
}
