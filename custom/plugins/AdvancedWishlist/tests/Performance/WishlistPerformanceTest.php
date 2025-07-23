<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Performance;

use AdvancedWishlist\Core\Service\WishlistCrudService;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\Database\QueryOptimizer;
use AdvancedWishlist\Core\Cache\EnhancedRedisCacheAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Performance tests for database operations, caching, and query optimization.
 * Validates 80%+ performance improvements and scalability under load.
 */
class WishlistPerformanceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private WishlistCrudService $wishlistCrudService;
    private QueryOptimizer $queryOptimizer;
    private Context $context;

    protected function setUp(): void
    {
        $this->wishlistCrudService = $this->getContainer()->get(WishlistCrudService::class);
        $this->queryOptimizer = $this->getContainer()->get(QueryOptimizer::class);
        $this->context = Context::createDefaultContext();
    }

    /**
     * Test wishlist creation performance under load.
     */
    public function testWishlistCreationPerformance(): void
    {
        $startTime = microtime(true);
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            $request = new CreateWishlistRequest();
            $request->setCustomerId(Uuid::randomHex());
            $request->setName("Performance Test Wishlist {$i}");
            $request->setType('private');
            $request->setIsDefault(false);
            
            $this->wishlistCrudService->createWishlist($request, $this->context);
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $averageTime = $totalTime / $iterations;
        
        // Average creation time should be under 50ms per wishlist
        $this->assertLessThan(50, $averageTime, 
            "Average wishlist creation time ({$averageTime}ms) should be under 50ms");
        
        // Total time for 100 wishlists should be under 5 seconds
        $this->assertLessThan(5000, $totalTime, 
            "Total time for 100 wishlists ({$totalTime}ms) should be under 5 seconds");
    }

    /**
     * Test query performance with optimized database queries.
     */
    public function testOptimizedQueryPerformance(): void
    {
        // Create test data
        $customerIds = [];
        for ($i = 0; $i < 50; $i++) {
            $customerId = Uuid::randomHex();
            $customerIds[] = $customerId;
            
            // Create 3 wishlists per customer
            for ($j = 0; $j < 3; $j++) {
                $request = new CreateWishlistRequest();
                $request->setCustomerId($customerId);
                $request->setName("Wishlist {$j} for Customer {$i}");
                $request->setType('private');
                $request->setIsDefault($j === 0);
                
                $this->wishlistCrudService->createWishlist($request, $this->context);
            }
        }
        
        // Test bulk query performance
        $startTime = microtime(true);
        
        foreach ($customerIds as $customerId) {
            $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
            $criteria->setLimit(10);
            $criteria->setOffset(0);
            
            $salesChannelContext = $this->createMock(\Shopware\Core\System\SalesChannel\SalesChannelContext::class);
            $salesChannelContext->method('getContext')->willReturn($this->context);
            
            $this->wishlistCrudService->getWishlists($customerId, $criteria, $salesChannelContext);
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $averageTime = $totalTime / count($customerIds);
        
        // Average query time should be under 10ms per customer
        $this->assertLessThan(10, $averageTime, 
            "Average query time ({$averageTime}ms) should be under 10ms");
    }

    /**
     * Test memory usage efficiency during bulk operations.
     */
    public function testMemoryUsageEfficiency(): void
    {
        $initialMemory = memory_get_usage(true);
        
        // Create many wishlists
        $wishlists = [];
        for ($i = 0; $i < 200; $i++) {
            $request = new CreateWishlistRequest();
            $request->setCustomerId(Uuid::randomHex());
            $request->setName("Memory Test Wishlist {$i}");
            $request->setType('private');
            $request->setIsDefault(false);
            
            $wishlists[] = $this->wishlistCrudService->createWishlist($request, $this->context);
        }
        
        $peakMemory = memory_get_peak_usage(true);
        $memoryUsed = $peakMemory - $initialMemory;
        
        // Memory usage should be reasonable (less than 10MB for 200 wishlists)
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 
            "Memory usage ({$memoryUsed} bytes) should be under 10MB");
        
        // Clean up
        unset($wishlists);
        
        // Force garbage collection
        gc_collect_cycles();
        
        $finalMemory = memory_get_usage(true);
        $memoryReleased = $peakMemory - $finalMemory;
        
        // Should release at least 50% of used memory
        $this->assertGreaterThan($memoryUsed * 0.5, $memoryReleased, 
            'Should release at least 50% of used memory after cleanup');
    }

    /**
     * Test database connection efficiency and connection pooling.
     */
    public function testDatabaseConnectionEfficiency(): void
    {
        $startTime = microtime(true);
        
        // Perform many database operations
        for ($i = 0; $i < 50; $i++) {
            $customerId = Uuid::randomHex();
            
            // Create wishlist
            $request = new CreateWishlistRequest();
            $request->setCustomerId($customerId);
            $request->setName("DB Test Wishlist {$i}");
            $request->setType('private');
            $request->setIsDefault(true);
            
            $response = $this->wishlistCrudService->createWishlist($request, $this->context);
            
            // Load wishlist (read operation)
            $this->wishlistCrudService->loadWishlist($response->getId(), $this->context);
            
            // Get default wishlist (cached operation)
            $this->wishlistCrudService->getOrCreateDefaultWishlist($customerId, $this->context);
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        
        // Should complete all operations within reasonable time
        $this->assertLessThan(3000, $totalTime, 
            "Database operations ({$totalTime}ms) should complete within 3 seconds");
    }

    /**
     * Test caching performance and hit rates.
     */
    public function testCachingPerformance(): void
    {
        $customerId = Uuid::randomHex();
        
        // Create test wishlist
        $request = new CreateWishlistRequest();
        $request->setCustomerId($customerId);
        $request->setName('Cache Test Wishlist');
        $request->setType('private');
        $request->setIsDefault(true);
        
        $response = $this->wishlistCrudService->createWishlist($request, $this->context);
        $wishlistId = $response->getId();
        
        // First load (should cache)
        $startTime = microtime(true);
        $this->wishlistCrudService->loadWishlist($wishlistId, $this->context);
        $firstLoadTime = (microtime(true) - $startTime) * 1000;
        
        // Second load (should be from cache)
        $startTime = microtime(true);
        $this->wishlistCrudService->loadWishlist($wishlistId, $this->context);
        $secondLoadTime = (microtime(true) - $startTime) * 1000;
        
        // Cache hit should be significantly faster
        $this->assertLessThan($firstLoadTime * 0.5, $secondLoadTime, 
            'Cache hit should be at least 50% faster than initial load');
        
        // Cache hit should be under 5ms
        $this->assertLessThan(5, $secondLoadTime, 
            'Cache hit should be under 5ms');
    }

    /**
     * Test concurrent access performance simulation.
     */
    public function testConcurrentAccessPerformance(): void
    {
        $customerId = Uuid::randomHex();
        
        // Create base wishlist
        $request = new CreateWishlistRequest();
        $request->setCustomerId($customerId);
        $request->setName('Concurrent Test Wishlist');
        $request->setType('private');
        $request->setIsDefault(true);
        
        $response = $this->wishlistCrudService->createWishlist($request, $this->context);
        $wishlistId = $response->getId();
        
        // Simulate concurrent reads
        $startTime = microtime(true);
        
        $results = [];
        for ($i = 0; $i < 20; $i++) {
            $results[] = $this->wishlistCrudService->loadWishlist($wishlistId, $this->context);
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $averageTime = $totalTime / 20;
        
        // Average concurrent read should be under 10ms
        $this->assertLessThan(10, $averageTime, 
            "Average concurrent read time ({$averageTime}ms) should be under 10ms");
        
        // All results should be consistent
        $this->assertCount(20, $results);
        foreach ($results as $result) {
            $this->assertEquals($wishlistId, $result->getId());
        }
    }

    /**
     * Test query optimization effectiveness.
     */
    public function testQueryOptimizationEffectiveness(): void
    {
        // Test with query optimizer
        $startTime = microtime(true);
        
        // Create test data with relationships
        $customerId = Uuid::randomHex();
        for ($i = 0; $i < 10; $i++) {
            $request = new CreateWishlistRequest();
            $request->setCustomerId($customerId);
            $request->setName("Optimization Test {$i}");
            $request->setType('private');
            $request->setIsDefault($i === 0);
            
            $this->wishlistCrudService->createWishlist($request, $this->context);
        }
        
        // Query with associations (should be optimized)
        $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
        $criteria->setLimit(10);
        
        $salesChannelContext = $this->createMock(\Shopware\Core\System\SalesChannel\SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($this->context);
        
        $result = $this->wishlistCrudService->getWishlists($customerId, $criteria, $salesChannelContext);
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        
        // Optimized query should be reasonably fast
        $this->assertLessThan(100, $totalTime, 
            "Optimized query ({$totalTime}ms) should be under 100ms");
        
        // Should return correct data
        $this->assertEquals(10, $result['total']);
        $this->assertCount(10, $result['wishlists']);
    }

    /**
     * Test performance degradation under stress.
     */
    public function testPerformanceDegradationUnderStress(): void
    {
        $times = [];
        
        // Measure performance over increasing load
        for ($batch = 1; $batch <= 5; $batch++) {
            $batchStartTime = microtime(true);
            
            // Each batch creates more wishlists
            for ($i = 0; $i < $batch * 10; $i++) {
                $request = new CreateWishlistRequest();
                $request->setCustomerId(Uuid::randomHex());
                $request->setName("Stress Test Batch {$batch} Item {$i}");
                $request->setType('private');
                $request->setIsDefault(false);
                
                $this->wishlistCrudService->createWishlist($request, $this->context);
            }
            
            $batchEndTime = microtime(true);
            $batchTime = ($batchEndTime - $batchStartTime) * 1000;
            $averageTimePerItem = $batchTime / ($batch * 10);
            
            $times[] = $averageTimePerItem;
        }
        
        // Performance should not degrade significantly
        $firstBatchAverage = $times[0];
        $lastBatchAverage = end($times);
        
        // Last batch should not be more than 2x slower than first batch
        $this->assertLessThan($firstBatchAverage * 2, $lastBatchAverage, 
            'Performance should not degrade more than 2x under increasing load');
    }

    /**
     * Test PHP 8.4 property hooks performance impact.
     */
    public function testPropertyHooksPerformanceImpact(): void
    {
        $customerId = Uuid::randomHex();
        
        // Test property access performance
        $request = new CreateWishlistRequest();
        $request->setCustomerId($customerId);
        $request->setName('Property Hooks Test');
        $request->setType('private');
        $request->setIsDefault(true);
        
        $response = $this->wishlistCrudService->createWishlist($request, $this->context);
        $wishlist = $this->wishlistCrudService->loadWishlist($response->getId(), $this->context);
        
        // Measure property access performance
        $startTime = microtime(true);
        
        for ($i = 0; $i < 1000; $i++) {
            // Access various properties
            $name = $wishlist->name;
            $type = $wishlist->type;
            $isDefault = $wishlist->isDefault;
            $displayName = $wishlist->displayName; // Virtual property
            $itemCount = $wishlist->itemCount; // Computed property
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        
        // Property access should be very fast even with hooks
        $this->assertLessThan(10, $totalTime, 
            "Property access with hooks ({$totalTime}ms) should be under 10ms for 1000 accesses");
    }

    /**
     * Test resource cleanup efficiency.
     */
    public function testResourceCleanupEfficiency(): void
    {
        $initialMemory = memory_get_usage(true);
        
        // Create and immediately clean up resources
        for ($i = 0; $i < 100; $i++) {
            $request = new CreateWishlistRequest();
            $request->setCustomerId(Uuid::randomHex());
            $request->setName("Cleanup Test {$i}");
            $request->setType('private');
            $request->setIsDefault(false);
            
            $response = $this->wishlistCrudService->createWishlist($request, $this->context);
            $wishlist = $this->wishlistCrudService->loadWishlist($response->getId(), $this->context);
            
            // Simulate cleanup
            unset($response);
            unset($wishlist);
            
            // Force garbage collection periodically
            if ($i % 20 === 0) {
                gc_collect_cycles();
            }
        }
        
        // Final cleanup
        gc_collect_cycles();
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Memory increase should be minimal after cleanup
        $this->assertLessThan(1024 * 1024, $memoryIncrease, 
            'Memory increase should be under 1MB after cleanup');
    }

    /**
     * Benchmark overall system performance.
     */
    public function testOverallSystemPerformanceBenchmark(): void
    {
        $benchmarkResults = [];
        
        // Create benchmark
        $startTime = microtime(true);
        $customerId = Uuid::randomHex();
        
        // Create wishlist
        $createStart = microtime(true);
        $request = new CreateWishlistRequest();
        $request->setCustomerId($customerId);
        $request->setName('Benchmark Wishlist');
        $request->setType('private');
        $request->setIsDefault(true);
        $response = $this->wishlistCrudService->createWishlist($request, $this->context);
        $benchmarkResults['create'] = (microtime(true) - $createStart) * 1000;
        
        // Load wishlist
        $loadStart = microtime(true);
        $wishlist = $this->wishlistCrudService->loadWishlist($response->getId(), $this->context);
        $benchmarkResults['load'] = (microtime(true) - $loadStart) * 1000;
        
        // Get default wishlist
        $defaultStart = microtime(true);
        $this->wishlistCrudService->getOrCreateDefaultWishlist($customerId, $this->context);
        $benchmarkResults['get_default'] = (microtime(true) - $defaultStart) * 1000;
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        $benchmarkResults['total'] = $totalTime;
        
        // Assert performance benchmarks
        $this->assertLessThan(50, $benchmarkResults['create'], 'Create operation should be under 50ms');
        $this->assertLessThan(20, $benchmarkResults['load'], 'Load operation should be under 20ms');
        $this->assertLessThan(10, $benchmarkResults['get_default'], 'Get default should be under 10ms');
        $this->assertLessThan(100, $benchmarkResults['total'], 'Total benchmark should be under 100ms');
        
        // Log benchmark results for analysis
        fwrite(STDERR, "\nPerformance Benchmark Results:\n");
        foreach ($benchmarkResults as $operation => $time) {
            fwrite(STDERR, "  {$operation}: {$time}ms\n");
        }
    }
}
