<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Multi-level caching service implementing the performance optimization strategy.
 * 
 * Cache Levels:
 * Level 1: APCu (In-Memory) - 50ms TTL - Ultra-fast local cache
 * Level 2: Redis (Distributed) - 5min TTL - Shared cache across instances  
 * Level 3: Database Query Cache - 15min TTL - Persistent cache
 * Level 4: CDN Edge Cache - 1hr TTL - Global edge caching
 */
class MultiLevelCacheService
{
    private const APCU_TTL = 50; // 50 seconds for Level 1
    private const REDIS_TTL = 300; // 5 minutes for Level 2
    private const QUERY_CACHE_TTL = 900; // 15 minutes for Level 3
    private const CDN_TTL = 3600; // 1 hour for Level 4
    
    private const CACHE_KEY_PREFIX = 'adv_wishlist:';
    
    private readonly ApcuAdapter $level1Cache;
    private readonly RedisAdapter $level2Cache;
    private readonly CacheItemPoolInterface $level3Cache;
    private readonly ArrayAdapter $fallbackCache;
    
    public function __construct(
        private readonly RedisAdapter $redisAdapter,
        private readonly CacheItemPoolInterface $queryCache,
        private readonly LoggerInterface $logger,
        private readonly bool $enableLevel1 = true,
        private readonly bool $enableLevel2 = true,
        private readonly bool $enableLevel3 = true,
    ) {
        $this->level1Cache = new ApcuAdapter('adv_wishlist_l1', self::APCU_TTL);
        $this->level2Cache = $redisAdapter;
        $this->level3Cache = $queryCache;
        $this->fallbackCache = new ArrayAdapter();
    }

    /**
     * Get cached data with multi-level cache hierarchy.
     * Checks each cache level in order and populates higher levels on cache hits.
     */
    public function get(string $key, callable $callback = null, int $ttl = null): mixed
    {
        $startTime = microtime(true);
        $cacheKey = $this->generateCacheKey($key);
        $result = null;
        $hitLevel = null;

        try {
            // Level 1: APCu (fastest)
            if ($this->enableLevel1 && $this->level1Cache->hasItem($cacheKey)) {
                $item = $this->level1Cache->getItem($cacheKey);
                if ($item->isHit()) {
                    $result = $item->get();
                    $hitLevel = 'L1_APCU';
                }
            }

            // Level 2: Redis (distributed)
            if ($result === null && $this->enableLevel2) {
                $item = $this->level2Cache->getItem($cacheKey);
                if ($item->isHit()) {
                    $result = $item->get();
                    $hitLevel = 'L2_REDIS';
                    
                    // Populate Level 1 for faster future access
                    if ($this->enableLevel1) {
                        $this->populateLevel1Cache($cacheKey, $result);
                    }
                }
            }

            // Level 3: Query Cache (persistent)
            if ($result === null && $this->enableLevel3) {
                $item = $this->level3Cache->getItem($cacheKey);
                if ($item->isHit()) {
                    $result = $item->get();
                    $hitLevel = 'L3_QUERY';
                    
                    // Populate higher levels
                    $this->populateHigherLevels($cacheKey, $result, 2);
                }
            }

            // Cache miss - execute callback if provided
            if ($result === null && $callback !== null) {
                $result = $callback();
                $hitLevel = 'MISS_CALLBACK';
                
                // Store in all cache levels
                $this->setMultiLevel($cacheKey, $result, $ttl ?? self::REDIS_TTL);
            }

            // Log cache performance
            $this->logCachePerformance($key, $hitLevel, microtime(true) - $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Multi-level cache error', [
                'key' => $key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback to callback if provided
            return $callback ? $callback() : null;
        }
    }

    /**
     * Set data in all cache levels.
     */
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $cacheKey = $this->generateCacheKey($key);
        $ttl = $ttl ?? self::REDIS_TTL;
        
        return $this->setMultiLevel($cacheKey, $value, $ttl);
    }

    /**
     * Delete from all cache levels.
     */
    public function delete(string $key): bool
    {
        $cacheKey = $this->generateCacheKey($key);
        $success = true;

        try {
            if ($this->enableLevel1) {
                $success &= $this->level1Cache->deleteItem($cacheKey);
            }
            
            if ($this->enableLevel2) {
                $success &= $this->level2Cache->deleteItem($cacheKey);
            }
            
            if ($this->enableLevel3) {
                $success &= $this->level3Cache->deleteItem($cacheKey);
            }

            $this->logger->debug('Cache key deleted from all levels', [
                'key' => $key,
                'success' => $success
            ]);

            return $success;
        } catch (\Exception $e) {
            $this->logger->error('Cache deletion error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Clear entire cache by tags or pattern.
     */
    public function clear(array $tags = null, string $pattern = null): bool
    {
        $startTime = microtime(true);
        $success = true;

        try {
            if ($tags !== null) {
                // Tag-based clearing
                foreach ($tags as $tag) {
                    $success &= $this->clearByTag($tag);
                }
            } elseif ($pattern !== null) {
                // Pattern-based clearing
                $success &= $this->clearByPattern($pattern);
            } else {
                // Clear all cache levels
                if ($this->enableLevel1) {
                    $success &= $this->level1Cache->clear();
                }
                
                if ($this->enableLevel2) {
                    $success &= $this->level2Cache->clear();
                }
                
                if ($this->enableLevel3) {
                    $success &= $this->level3Cache->clear();
                }
            }

            $this->logger->info('Cache cleared', [
                'tags' => $tags,
                'pattern' => $pattern,
                'success' => $success,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

            return $success;
        } catch (\Exception $e) {
            $this->logger->error('Cache clear error', [
                'tags' => $tags,
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get cache statistics for monitoring.
     */
    public function getStats(): array
    {
        $stats = [
            'enabled_levels' => [],
            'level1_apcu' => ['enabled' => $this->enableLevel1],
            'level2_redis' => ['enabled' => $this->enableLevel2],
            'level3_query' => ['enabled' => $this->enableLevel3],
            'configuration' => [
                'apcu_ttl' => self::APCU_TTL,
                'redis_ttl' => self::REDIS_TTL,
                'query_cache_ttl' => self::QUERY_CACHE_TTL,
                'cdn_ttl' => self::CDN_TTL,
            ]
        ];

        if ($this->enableLevel1) {
            $stats['enabled_levels'][] = 'L1_APCU';
        }
        
        if ($this->enableLevel2) {
            $stats['enabled_levels'][] = 'L2_REDIS';
        }
        
        if ($this->enableLevel3) {
            $stats['enabled_levels'][] = 'L3_QUERY';
        }

        return $stats;
    }

    /**
     * Warm cache with frequently accessed data.
     */
    public function warmCache(array $keys, callable $dataProvider): bool
    {
        $startTime = microtime(true);
        $warmedCount = 0;

        try {
            foreach ($keys as $key) {
                $data = $dataProvider($key);
                if ($data !== null) {
                    $this->set($key, $data);
                    $warmedCount++;
                }
            }

            $this->logger->info('Cache warmed successfully', [
                'keys_requested' => count($keys),
                'keys_warmed' => $warmedCount,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Cache warming failed', [
                'keys_count' => count($keys),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Batch get operation for multiple keys.
     */
    public function getMultiple(array $keys, callable $callback = null): array
    {
        $results = [];
        $missedKeys = [];

        // Try to get all keys from cache first
        foreach ($keys as $key) {
            $result = $this->get($key);
            if ($result !== null) {
                $results[$key] = $result;
            } else {
                $missedKeys[] = $key;
            }
        }

        // Handle cache misses with batch callback if provided
        if (!empty($missedKeys) && $callback !== null) {
            $batchResults = $callback($missedKeys);
            
            if (is_array($batchResults)) {
                foreach ($batchResults as $key => $value) {
                    $this->set($key, $value);
                    $results[$key] = $value;
                }
            }
        }

        return $results;
    }

    /**
     * Generate standardized cache key.
     */
    private function generateCacheKey(string $key): string
    {
        return self::CACHE_KEY_PREFIX . hash('xxh64', $key);
    }

    /**
     * Set data in multiple cache levels.
     */
    private function setMultiLevel(string $cacheKey, mixed $value, int $ttl): bool
    {
        $success = true;

        try {
            // Level 1: APCu (short TTL)
            if ($this->enableLevel1) {
                $item1 = $this->level1Cache->getItem($cacheKey);
                $item1->set($value);
                $item1->expiresAfter(min($ttl, self::APCU_TTL));
                $success &= $this->level1Cache->save($item1);
            }

            // Level 2: Redis (medium TTL)
            if ($this->enableLevel2) {
                $item2 = $this->level2Cache->getItem($cacheKey);
                $item2->set($value);
                $item2->expiresAfter($ttl);
                $success &= $this->level2Cache->save($item2);
            }

            // Level 3: Query Cache (long TTL)
            if ($this->enableLevel3) {
                $item3 = $this->level3Cache->getItem($cacheKey);
                $item3->set($value);
                $item3->expiresAfter(max($ttl, self::QUERY_CACHE_TTL));
                $success &= $this->level3Cache->save($item3);
            }

            return $success;
        } catch (\Exception $e) {
            $this->logger->error('Multi-level cache set error', [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Populate Level 1 cache.
     */
    private function populateLevel1Cache(string $cacheKey, mixed $value): void
    {
        try {
            $item = $this->level1Cache->getItem($cacheKey);
            $item->set($value);
            $item->expiresAfter(self::APCU_TTL);
            $this->level1Cache->save($item);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to populate L1 cache', [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Populate higher cache levels.
     */
    private function populateHigherLevels(string $cacheKey, mixed $value, int $startLevel): void
    {
        try {
            if ($startLevel <= 1 && $this->enableLevel1) {
                $this->populateLevel1Cache($cacheKey, $value);
            }

            if ($startLevel <= 2 && $this->enableLevel2) {
                $item = $this->level2Cache->getItem($cacheKey);
                $item->set($value);
                $item->expiresAfter(self::REDIS_TTL);
                $this->level2Cache->save($item);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to populate higher cache levels', [
                'key' => $cacheKey,
                'start_level' => $startLevel,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear cache by tag.
     */
    private function clearByTag(string $tag): bool
    {
        $success = true;

        try {
            // Redis supports tag-based invalidation
            if ($this->enableLevel2) {
                $success &= $this->level2Cache->invalidateTags([$tag]);
            }

            // For other levels, we'd need to implement tag tracking
            // For now, we clear the entire cache levels
            if ($this->enableLevel1) {
                $success &= $this->level1Cache->clear();
            }

            if ($this->enableLevel3) {
                $success &= $this->level3Cache->clear();
            }

            return $success;
        } catch (\Exception $e) {
            $this->logger->error('Tag-based cache clear error', [
                'tag' => $tag,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Clear cache by pattern.
     */
    private function clearByPattern(string $pattern): bool
    {
        // Pattern-based clearing would require implementing pattern matching
        // For now, fallback to full clear
        return $this->clear();
    }

    /**
     * Log cache performance metrics.
     */
    private function logCachePerformance(string $key, ?string $hitLevel, float $duration): void
    {
        $this->logger->debug('Cache operation completed', [
            'key' => $key,
            'hit_level' => $hitLevel ?? 'MISS',
            'duration_ms' => round($duration * 1000, 3),
            'is_hit' => $hitLevel !== null && $hitLevel !== 'MISS_CALLBACK'
        ]);
    }
}