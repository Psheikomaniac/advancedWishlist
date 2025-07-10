<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Cache;

use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Cache configuration for the AdvancedWishlist plugin
 * Uses attribute-based configuration for Symfony 7 compatibility
 */
class CacheConfiguration
{
    /**
     * Create a Redis cache adapter for production
     */
    #[AsTaggedItem('cache.adapter', 100)]
    #[When(env: 'prod')]
    public function createProductionCacheAdapter(
        #[Autowire('%env(REDIS_URL)%')] string $redisUrl,
        #[Autowire('%env(int:CACHE_TTL)%')] int $cacheTtl = 3600,
        LoggerInterface $logger = null
    ): CacheItemPoolInterface {
        $redis = RedisAdapter::createConnection($redisUrl);

        $redisAdapter = new RedisAdapter(
            $redis,
            'wishlist_cache',
            $cacheTtl
        );

        // Make the adapter tag-aware for better cache invalidation
        return new TagAwareAdapter($redisAdapter);
    }

    /**
     * Create a memory cache adapter for development
     */
    #[AsTaggedItem('cache.adapter', 50)]
    #[When(env: 'dev')]
    public function createDevelopmentCacheAdapter(
        LoggerInterface $logger = null
    ): CacheItemPoolInterface {
        // Use in-memory cache for development
        $arrayAdapter = new ArrayAdapter(
            defaultLifetime: 60,
            storeSerialized: true
        );

        // Make the adapter tag-aware for better cache invalidation
        return new TagAwareAdapter($arrayAdapter);
    }

    /**
     * Create the wishlist cache service
     */
    #[AsTaggedItem('cache.pool', 100)]
    public function createWishlistCache(
        #[TaggedIterator('cache.adapter', defaultPriorityMethod: 'getPriority')] 
        iterable $adapters,
        LoggerInterface $logger
    ): CacheItemPoolInterface {
        // Get the highest priority adapter
        $adapter = null;
        foreach ($adapters as $candidate) {
            $adapter = $candidate;
            break;
        }

        if (!$adapter) {
            // Fallback to array adapter if no adapter is available
            $adapter = new ArrayAdapter();
            $logger->warning('No cache adapter found, using in-memory cache');
        }

        return $adapter;
    }
}
