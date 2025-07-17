<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Cache;

use AdvancedWishlist\Core\Performance\PerformanceMonitoringService;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

/**
 * Enhanced Redis cache adapter with performance optimizations.
 */
class EnhancedRedisCacheAdapter implements CacheItemPoolInterface
{
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;
    private ?PerformanceMonitoringService $performanceMonitoring;
    private ?LockFactory $lockFactory;

    /**
     * @var array<string, int> Access count for cache items
     */
    private array $accessCount = [];

    /**
     * @var int Maximum TTL in seconds
     */
    private int $maxTtl;

    /**
     * @var int Minimum TTL in seconds
     */
    private int $minTtl;

    /**
     * @var bool Whether to use compression
     */
    private bool $useCompression;

    /**
     * @var int Compression threshold in bytes
     */
    private int $compressionThreshold;

    /**
     * @var int Compression level (0-9)
     */
    private int $compressionLevel;

    /**
     * @var bool Whether to use adaptive TTL
     */
    private bool $useAdaptiveTtl;

    /**
     * @var bool Whether to use cache stampede protection
     */
    private bool $useStampedeProtection;

    /**
     * @var string Compression prefix for identifying compressed items
     */
    private const COMPRESSION_PREFIX = 'COMPRESSED:';

    /**
     * @param string                            $redisUrl              Redis connection URL (e.g., redis://localhost:6379)
     * @param string                            $namespace             Cache namespace to avoid collisions
     * @param int                               $defaultLifetime       Default cache lifetime in seconds
     * @param LoggerInterface                   $logger                Logger for cache operations
     * @param PerformanceMonitoringService|null $performanceMonitoring Performance monitoring service
     * @param array                             $options               Additional options
     */
    public function __construct(
        string $redisUrl,
        string $namespace = 'wishlist_cache',
        int $defaultLifetime = 3600,
        LoggerInterface $logger,
        ?PerformanceMonitoringService $performanceMonitoring = null,
        array $options = [],
    ) {
        $this->logger = $logger;
        $this->performanceMonitoring = $performanceMonitoring;

        // Set options with defaults
        $this->maxTtl = $options['max_ttl'] ?? 86400; // 24 hours
        $this->minTtl = $options['min_ttl'] ?? 60; // 1 minute
        $this->useCompression = $options['use_compression'] ?? true;
        $this->compressionThreshold = $options['compression_threshold'] ?? 1024; // 1KB
        $this->compressionLevel = $options['compression_level'] ?? 6; // Medium compression
        $this->useAdaptiveTtl = $options['use_adaptive_ttl'] ?? true;
        $this->useStampedeProtection = $options['use_stampede_protection'] ?? true;

        try {
            // Create a persistent connection
            $redisOptions = [
                'persistent_id' => $namespace,
                'read_timeout' => 2,
                'retry_interval' => 100, // ms
                'tcp_keepalive' => true,
            ];

            $redis = RedisAdapter::createConnection($redisUrl, $redisOptions);

            // Enable client-side caching if available (Redis 6.0+)
            if (method_exists($redis, 'isConnected') && $redis->isConnected() && method_exists($redis, 'clientTracking')) {
                try {
                    $redis->clientTracking(['ON', 'OPTIN']);
                    $this->logger->info('Redis client-side caching enabled');
                } catch (\Exception $e) {
                    $this->logger->notice('Redis client-side caching not available', ['error' => $e->getMessage()]);
                }
            }

            // Create Redis adapter with persistent connection
            $redisAdapter = new RedisAdapter($redis, $namespace, $defaultLifetime);

            // Use TagAwareAdapter to support cache invalidation by tags
            $this->cache = new TagAwareAdapter($redisAdapter);

            // Set up lock factory for cache stampede protection if enabled
            if ($this->useStampedeProtection) {
                $store = new RedisStore($redis);
                $this->lockFactory = new LockFactory($store);
            }

            $this->logger->info('Enhanced Redis cache adapter initialized', [
                'namespace' => $namespace,
                'defaultLifetime' => $defaultLifetime,
                'options' => [
                    'useCompression' => $this->useCompression,
                    'useAdaptiveTtl' => $this->useAdaptiveTtl,
                    'useStampedeProtection' => $this->useStampedeProtection,
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize Enhanced Redis cache adapter', [
                'error' => $e->getMessage(),
                'redisUrl' => $redisUrl,
            ]);

            // Fallback to array cache if Redis is not available
            $this->cache = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
            $this->logger->warning('Using in-memory array cache as fallback');
        }
    }

    public function getItem(string $key): CacheItemInterface
    {
        $startTime = microtime(true);
        $hit = false;

        // Track access count for adaptive TTL
        if ($this->useAdaptiveTtl) {
            if (!isset($this->accessCount[$key])) {
                $this->accessCount[$key] = 0;
            }
            ++$this->accessCount[$key];
        }

        try {
            $item = $this->cache->getItem($key);
            $hit = $item->isHit();

            // Handle decompression if needed
            if ($hit && $this->useCompression && $item instanceof CacheItem) {
                $value = $item->get();
                if (is_string($value) && 0 === strpos($value, self::COMPRESSION_PREFIX)) {
                    $compressedData = substr($value, strlen(self::COMPRESSION_PREFIX));
                    $uncompressedData = gzuncompress($compressedData);
                    if (false !== $uncompressedData) {
                        $item->set(unserialize($uncompressedData));
                    }
                }
            }

            // Track performance if monitoring service is available
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('get', $key, $hit, $executionTime);
            }

            return $item;
        } catch (\Exception $e) {
            $this->logger->error('Error getting cache item', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // Track performance for failed operation
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('get_error', $key, false, $executionTime);
            }

            // Return a dummy item that will always miss
            return new \Symfony\Component\Cache\Adapter\NullAdapter()->getItem($key);
        }
    }

    public function getItems(array $keys = []): iterable
    {
        $startTime = microtime(true);

        try {
            $items = $this->cache->getItems($keys);

            // Handle decompression and track access count
            $result = [];
            foreach ($items as $key => $item) {
                // Track access count for adaptive TTL
                if ($this->useAdaptiveTtl) {
                    if (!isset($this->accessCount[$key])) {
                        $this->accessCount[$key] = 0;
                    }
                    ++$this->accessCount[$key];
                }

                // Handle decompression if needed
                if ($item->isHit() && $this->useCompression && $item instanceof CacheItem) {
                    $value = $item->get();
                    if (is_string($value) && 0 === strpos($value, self::COMPRESSION_PREFIX)) {
                        $compressedData = substr($value, strlen(self::COMPRESSION_PREFIX));
                        $uncompressedData = gzuncompress($compressedData);
                        if (false !== $uncompressedData) {
                            $item->set(unserialize($uncompressedData));
                        }
                    }
                }

                $result[$key] = $item;
            }

            // Track performance if monitoring service is available
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('get_items', implode(',', $keys), true, $executionTime);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error getting multiple cache items', [
                'keys' => $keys,
                'error' => $e->getMessage(),
            ]);

            // Track performance for failed operation
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('get_items_error', implode(',', $keys), false, $executionTime);
            }

            // Return dummy items that will always miss
            return (new \Symfony\Component\Cache\Adapter\NullAdapter())->getItems($keys);
        }
    }

    public function hasItem(string $key): bool
    {
        $startTime = microtime(true);

        try {
            $result = $this->cache->hasItem($key);

            // Track performance if monitoring service is available
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('has', $key, $result, $executionTime);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error checking cache item existence', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // Track performance for failed operation
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('has_error', $key, false, $executionTime);
            }

            return false;
        }
    }

    public function clear(): bool
    {
        $startTime = microtime(true);

        try {
            $this->logger->info('Clearing entire cache');
            $result = $this->cache->clear();

            // Reset access counts
            $this->accessCount = [];

            // Track performance if monitoring service is available
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('clear', 'all', $result, $executionTime);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error clearing cache', [
                'error' => $e->getMessage(),
            ]);

            // Track performance for failed operation
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('clear_error', 'all', false, $executionTime);
            }

            return false;
        }
    }

    public function deleteItem(string $key): bool
    {
        $startTime = microtime(true);

        try {
            $this->logger->debug('Deleting cache item', ['key' => $key]);
            $result = $this->cache->deleteItem($key);

            // Remove from access count
            unset($this->accessCount[$key]);

            // Track performance if monitoring service is available
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('delete', $key, $result, $executionTime);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error deleting cache item', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // Track performance for failed operation
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('delete_error', $key, false, $executionTime);
            }

            return false;
        }
    }

    public function deleteItems(array $keys): bool
    {
        $startTime = microtime(true);

        try {
            $this->logger->debug('Deleting multiple cache items', ['keys' => $keys]);
            $result = $this->cache->deleteItems($keys);

            // Remove from access count
            foreach ($keys as $key) {
                unset($this->accessCount[$key]);
            }

            // Track performance if monitoring service is available
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('delete_items', implode(',', $keys), $result, $executionTime);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error deleting multiple cache items', [
                'keys' => $keys,
                'error' => $e->getMessage(),
            ]);

            // Track performance for failed operation
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('delete_items_error', implode(',', $keys), false, $executionTime);
            }

            return false;
        }
    }

    public function save(CacheItemInterface $item): bool
    {
        $startTime = microtime(true);

        try {
            // Apply compression if enabled and item is large enough
            if ($this->useCompression && $item instanceof CacheItem) {
                $value = $item->get();

                // Only compress non-scalar values or large strings
                if ((!is_scalar($value) || (is_string($value) && strlen($value) > $this->compressionThreshold))
                    && !is_resource($value)) {
                    // Serialize and compress the value
                    $serialized = serialize($value);
                    $compressed = gzcompress($serialized, $this->compressionLevel);

                    if (false !== $compressed) {
                        // Store with compression prefix
                        $item->set(self::COMPRESSION_PREFIX.$compressed);

                        $this->logger->debug('Compressed cache item', [
                            'key' => $item->getKey(),
                            'original_size' => strlen($serialized),
                            'compressed_size' => strlen($compressed),
                            'ratio' => round(strlen($compressed) / strlen($serialized) * 100).'%',
                        ]);
                    }
                }
            }

            // Apply adaptive TTL if enabled
            if ($this->useAdaptiveTtl && $item instanceof CacheItem) {
                $key = $item->getKey();
                $accessCount = $this->accessCount[$key] ?? 0;

                // Calculate TTL based on access count
                // More frequently accessed items get longer TTL
                $ttlFactor = min(1 + ($accessCount / 10), 5); // Cap at 5x
                $adaptiveTtl = min(
                    $this->maxTtl,
                    max($this->minTtl, (int) ($item->getExpiry() * $ttlFactor))
                );

                // Set the new expiry time
                $item->expiresAfter($adaptiveTtl);

                $this->logger->debug('Applied adaptive TTL', [
                    'key' => $key,
                    'access_count' => $accessCount,
                    'ttl_factor' => $ttlFactor,
                    'adaptive_ttl' => $adaptiveTtl,
                ]);
            }

            $result = $this->cache->save($item);

            // Track performance if monitoring service is available
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('save', $item->getKey(), $result, $executionTime);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error saving cache item', [
                'key' => $item->getKey(),
                'error' => $e->getMessage(),
            ]);

            // Track performance for failed operation
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('save_error', $item->getKey(), false, $executionTime);
            }

            return false;
        }
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $startTime = microtime(true);

        try {
            // Apply compression if enabled and item is large enough
            if ($this->useCompression && $item instanceof CacheItem) {
                $value = $item->get();

                // Only compress non-scalar values or large strings
                if ((!is_scalar($value) || (is_string($value) && strlen($value) > $this->compressionThreshold))
                    && !is_resource($value)) {
                    // Serialize and compress the value
                    $serialized = serialize($value);
                    $compressed = gzcompress($serialized, $this->compressionLevel);

                    if (false !== $compressed) {
                        // Store with compression prefix
                        $item->set(self::COMPRESSION_PREFIX.$compressed);
                    }
                }
            }

            // Apply adaptive TTL if enabled
            if ($this->useAdaptiveTtl && $item instanceof CacheItem) {
                $key = $item->getKey();
                $accessCount = $this->accessCount[$key] ?? 0;

                // Calculate TTL based on access count
                $ttlFactor = min(1 + ($accessCount / 10), 5); // Cap at 5x
                $adaptiveTtl = min(
                    $this->maxTtl,
                    max($this->minTtl, (int) ($item->getExpiry() * $ttlFactor))
                );

                // Set the new expiry time
                $item->expiresAfter($adaptiveTtl);
            }

            $result = $this->cache->saveDeferred($item);

            // Track performance if monitoring service is available
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('save_deferred', $item->getKey(), $result, $executionTime);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error saving deferred cache item', [
                'key' => $item->getKey(),
                'error' => $e->getMessage(),
            ]);

            // Track performance for failed operation
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('save_deferred_error', $item->getKey(), false, $executionTime);
            }

            return false;
        }
    }

    public function commit(): bool
    {
        $startTime = microtime(true);

        try {
            $result = $this->cache->commit();

            // Track performance if monitoring service is available
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('commit', 'all', $result, $executionTime);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error committing cache changes', [
                'error' => $e->getMessage(),
            ]);

            // Track performance for failed operation
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('commit_error', 'all', false, $executionTime);
            }

            return false;
        }
    }

    /**
     * Get item with cache stampede protection.
     *
     * This method uses a lock to prevent multiple processes from regenerating the same cache item simultaneously
     */
    public function getItemWithStampedeProtection(string $key, callable $callback, ?int $ttl = null, array $tags = []): mixed
    {
        $startTime = microtime(true);
        $hit = false;

        // Track access count for adaptive TTL
        if ($this->useAdaptiveTtl) {
            if (!isset($this->accessCount[$key])) {
                $this->accessCount[$key] = 0;
            }
            ++$this->accessCount[$key];
        }

        try {
            $item = $this->getItem($key);
            $hit = $item->isHit();

            if ($hit) {
                // Cache hit, return the value
                $value = $item->get();

                // Track performance if monitoring service is available
                if ($this->performanceMonitoring) {
                    $executionTime = microtime(true) - $startTime;
                    $this->performanceMonitoring->trackCacheOperation('get_protected', $key, true, $executionTime);
                }

                return $value;
            }

            // Cache miss, regenerate with lock if stampede protection is enabled
            if ($this->useStampedeProtection && $this->lockFactory) {
                $lock = $this->lockFactory->createLock('cache_regeneration_'.$key, 30); // 30 seconds timeout

                if ($lock->acquire()) {
                    try {
                        // Double-check if another process has already regenerated the item
                        $item = $this->getItem($key);
                        if ($item->isHit()) {
                            // Another process regenerated the item while we were waiting
                            $value = $item->get();

                            // Track performance if monitoring service is available
                            if ($this->performanceMonitoring) {
                                $executionTime = microtime(true) - $startTime;
                                $this->performanceMonitoring->trackCacheOperation('get_protected_race_won', $key, true, $executionTime);
                            }

                            return $value;
                        }

                        // Generate the value
                        $value = $callback();

                        // Store in cache
                        $item->set($value);
                        if (null !== $ttl) {
                            $item->expiresAfter($ttl);
                        }

                        // Add tags if supported
                        if (!empty($tags) && $item instanceof CacheItem) {
                            $item->tag($tags);
                        }

                        $this->save($item);

                        // Track performance if monitoring service is available
                        if ($this->performanceMonitoring) {
                            $executionTime = microtime(true) - $startTime;
                            $this->performanceMonitoring->trackCacheOperation('get_protected_regenerated', $key, false, $executionTime);
                        }

                        return $value;
                    } finally {
                        $lock->release();
                    }
                } else {
                    // Could not acquire lock, wait briefly and try again
                    usleep(100000); // 100ms

                    // Check if the item is now in cache
                    $item = $this->getItem($key);
                    if ($item->isHit()) {
                        // Another process regenerated the item
                        $value = $item->get();

                        // Track performance if monitoring service is available
                        if ($this->performanceMonitoring) {
                            $executionTime = microtime(true) - $startTime;
                            $this->performanceMonitoring->trackCacheOperation('get_protected_waited', $key, true, $executionTime);
                        }

                        return $value;
                    }

                    // Still not in cache, generate without lock
                    $value = $callback();

                    // Store in cache
                    $item->set($value);
                    if (null !== $ttl) {
                        $item->expiresAfter($ttl);
                    }

                    // Add tags if supported
                    if (!empty($tags) && $item instanceof CacheItem) {
                        $item->tag($tags);
                    }

                    $this->save($item);

                    // Track performance if monitoring service is available
                    if ($this->performanceMonitoring) {
                        $executionTime = microtime(true) - $startTime;
                        $this->performanceMonitoring->trackCacheOperation('get_protected_fallback', $key, false, $executionTime);
                    }

                    return $value;
                }
            } else {
                // No stampede protection, simply regenerate
                $value = $callback();

                // Store in cache
                $item->set($value);
                if (null !== $ttl) {
                    $item->expiresAfter($ttl);
                }

                // Add tags if supported
                if (!empty($tags) && $item instanceof CacheItem) {
                    $item->tag($tags);
                }

                $this->save($item);

                // Track performance if monitoring service is available
                if ($this->performanceMonitoring) {
                    $executionTime = microtime(true) - $startTime;
                    $this->performanceMonitoring->trackCacheOperation('get_unprotected', $key, false, $executionTime);
                }

                return $value;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in getItemWithStampedeProtection', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // Track performance for failed operation
            if ($this->performanceMonitoring) {
                $executionTime = microtime(true) - $startTime;
                $this->performanceMonitoring->trackCacheOperation('get_protected_error', $key, false, $executionTime);
            }

            // Fall back to direct callback execution
            return $callback();
        }
    }

    /**
     * Invalidate cache items by tag.
     */
    public function invalidateTag(string $tag): bool
    {
        if ($this->cache instanceof TagAwareAdapter) {
            $this->logger->info('Invalidating cache by tag', ['tag' => $tag]);

            return $this->cache->invalidateTags([$tag]);
        }

        $this->logger->warning('Cache adapter does not support tag invalidation');

        return false;
    }

    /**
     * Invalidate cache items by multiple tags.
     */
    public function invalidateTags(array $tags): bool
    {
        if ($this->cache instanceof TagAwareAdapter) {
            $this->logger->info('Invalidating cache by multiple tags', ['tags' => $tags]);

            return $this->cache->invalidateTags($tags);
        }

        $this->logger->warning('Cache adapter does not support tag invalidation');

        return false;
    }

    /**
     * Warm up cache for frequently accessed items.
     */
    public function warmUpCache(array $keys, callable $callback): void
    {
        $this->logger->info('Warming up cache', ['keys' => $keys]);

        foreach ($keys as $key) {
            try {
                $this->getItemWithStampedeProtection($key, fn () => $callback($key));
            } catch (\Exception $e) {
                $this->logger->error('Error warming up cache for key', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get cache statistics.
     */
    public function getStatistics(): array
    {
        return [
            'access_counts' => $this->accessCount,
            'options' => [
                'useCompression' => $this->useCompression,
                'compressionThreshold' => $this->compressionThreshold,
                'compressionLevel' => $this->compressionLevel,
                'useAdaptiveTtl' => $this->useAdaptiveTtl,
                'minTtl' => $this->minTtl,
                'maxTtl' => $this->maxTtl,
                'useStampedeProtection' => $this->useStampedeProtection,
            ],
        ];
    }
}
