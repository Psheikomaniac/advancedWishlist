<?php declare(strict_types=1);

namespace AdvancedWishlist\Tests;

use AdvancedWishlist\Core\Service\WishlistCacheService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class WishlistCacheServiceTest extends TestCase
{
    private WishlistCacheService $cacheService;
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        // Create a tag-aware cache adapter for testing
        $this->cache = new TagAwareAdapter(new ArrayAdapter());
        
        // Create a mock logger
        $this->logger = $this->createMock(LoggerInterface::class);
        
        // Create the cache service
        $this->cacheService = new WishlistCacheService($this->cache, $this->logger);
    }

    public function testMultiLevelCaching(): void
    {
        // Set a value in the cache
        $this->cacheService->set('test_key', 'test_value');
        
        // Get the value from the cache
        $value = $this->cacheService->get('test_key', function() {
            return 'fallback_value';
        });
        
        // Assert that the value is retrieved from the cache
        $this->assertEquals('test_value', $value);
    }

    public function testCacheTtlSettings(): void
    {
        // Set custom TTL values
        $this->cacheService->setCacheTtl(
            7200,           // 2 hours default
            3600,           // 1 hour for customer
            5400,           // 1.5 hours for wishlist
            1800            // 30 minutes for default wishlist
        );
        
        // Get cache statistics to verify TTL settings
        $stats = $this->cacheService->getCacheStatistics();
        
        // Assert that the TTL values are set correctly
        $this->assertEquals(7200, $stats['ttlSettings']['default']);
        $this->assertEquals(3600, $stats['ttlSettings']['customer']);
        $this->assertEquals(5400, $stats['ttlSettings']['wishlist']);
        $this->assertEquals(1800, $stats['ttlSettings']['defaultWishlist']);
    }

    public function testCacheStatistics(): void
    {
        // Initial statistics should show 0 hits and misses
        $initialStats = $this->cacheService->getCacheStatistics();
        $this->assertEquals(0, $initialStats['hits']);
        $this->assertEquals(0, $initialStats['misses']);
        
        // Cache miss - should increment misses
        $this->cacheService->get('missing_key', function() {
            return 'new_value';
        });
        
        // Cache hit - should increment hits
        $this->cacheService->get('missing_key', function() {
            return 'should_not_be_called';
        });
        
        // Get updated statistics
        $updatedStats = $this->cacheService->getCacheStatistics();
        
        // Assert that the statistics are updated correctly
        $this->assertEquals(1, $updatedStats['hits']);
        $this->assertEquals(1, $updatedStats['misses']);
        $this->assertEquals(2, $updatedStats['total']);
        $this->assertEquals('50%', $updatedStats['hitRate']);
    }

    public function testPerformanceMonitoring(): void
    {
        // Perform an operation that is monitored
        $this->cacheService->get('performance_test', function() {
            // Simulate some work
            usleep(10000); // 10ms
            return 'result';
        });
        
        // Get performance metrics
        $metrics = $this->cacheService->getPerformanceMetrics('cache_get_performance_test');
        
        // Assert that metrics are recorded
        $this->assertNotNull($metrics);
        $this->assertArrayHasKey('duration', $metrics);
        $this->assertArrayHasKey('memory', $metrics);
        $this->assertArrayHasKey('startTime', $metrics);
        $this->assertArrayHasKey('endTime', $metrics);
        
        // Duration should be positive
        $this->assertGreaterThan(0, $metrics['duration']);
    }

    public function testCacheInvalidation(): void
    {
        // Set up test data
        $wishlistId = 'test-wishlist-123';
        $this->cacheService->cacheWishlist($wishlistId, ['id' => $wishlistId, 'name' => 'Test Wishlist']);
        
        // Verify data is cached
        $cachedData = $this->cacheService->get("wishlist_{$wishlistId}", function() {
            return null;
        });
        $this->assertNotNull($cachedData);
        
        // Invalidate the cache
        $this->cacheService->invalidateWishlistCache($wishlistId);
        
        // Verify data is no longer cached
        $callbackCalled = false;
        $this->cacheService->get("wishlist_{$wishlistId}", function() use (&$callbackCalled) {
            $callbackCalled = true;
            return 'new data';
        });
        
        // Assert that the callback was called (cache miss)
        $this->assertTrue($callbackCalled);
    }

    public function testClearAllCache(): void
    {
        // Set up test data
        $this->cacheService->set('test_key1', 'test_value1');
        $this->cacheService->set('test_key2', 'test_value2');
        
        // Clear all cache
        $this->cacheService->clearAllCache();
        
        // Verify all data is cleared
        $callbackCalled = false;
        $this->cacheService->get('test_key1', function() use (&$callbackCalled) {
            $callbackCalled = true;
            return 'new value';
        });
        
        // Assert that the callback was called (cache miss)
        $this->assertTrue($callbackCalled);
        
        // Statistics should be reset
        $stats = $this->cacheService->getCacheStatistics();
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(1, $stats['misses']); // From the get() call above
    }
}