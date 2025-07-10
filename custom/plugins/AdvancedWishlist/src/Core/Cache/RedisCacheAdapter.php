<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * Redis cache adapter for the AdvancedWishlist plugin
 */
class RedisCacheAdapter implements CacheItemPoolInterface
{
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;

    /**
     * @param string $redisUrl Redis connection URL (e.g., redis://localhost:6379)
     * @param string $namespace Cache namespace to avoid collisions
     * @param int $defaultLifetime Default cache lifetime in seconds
     * @param LoggerInterface $logger Logger for cache operations
     */
    public function __construct(
        string $redisUrl,
        string $namespace = 'wishlist_cache',
        int $defaultLifetime = 3600,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        
        try {
            $redis = RedisAdapter::createConnection($redisUrl);
            $redisAdapter = new RedisAdapter($redis, $namespace, $defaultLifetime);
            
            // Use TagAwareAdapter to support cache invalidation by tags
            $this->cache = new TagAwareAdapter($redisAdapter);
            
            $this->logger->info('Redis cache adapter initialized', [
                'namespace' => $namespace,
                'defaultLifetime' => $defaultLifetime
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize Redis cache adapter', [
                'error' => $e->getMessage(),
                'redisUrl' => $redisUrl
            ]);
            
            // Fallback to array cache if Redis is not available
            $this->cache = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
            $this->logger->warning('Using in-memory array cache as fallback');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(string $key): CacheItemInterface
    {
        return $this->cache->getItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = []): iterable
    {
        return $this->cache->getItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        return $this->cache->hasItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->logger->info('Clearing entire cache');
        return $this->cache->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key): bool
    {
        $this->logger->debug('Deleting cache item', ['key' => $key]);
        return $this->cache->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys): bool
    {
        $this->logger->debug('Deleting multiple cache items', ['keys' => $keys]);
        return $this->cache->deleteItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item): bool
    {
        return $this->cache->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->cache->saveDeferred($item);
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        return $this->cache->commit();
    }
    
    /**
     * Invalidate cache items by tag
     * 
     * @param string $tag The tag to invalidate
     * @return bool True if successful
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
     * Invalidate cache items by multiple tags
     * 
     * @param array $tags The tags to invalidate
     * @return bool True if successful
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
}