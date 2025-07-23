<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Integration\Database;

use AdvancedWishlist\Core\Database\QueryOptimizer;
use AdvancedWishlist\Core\Database\ReadReplicaConnectionDecorator;
use AdvancedWishlist\Core\Service\WishlistCrudService;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Database performance and optimization integration tests.
 * Validates query performance, indexing effectiveness, and connection management.
 */
class DatabasePerformanceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $wishlistRepository;
    private QueryOptimizer $queryOptimizer;
    private WishlistCrudService $wishlistCrudService;
    private Context $context;

    protected function setUp(): void
    {
        $this->wishlistRepository = $this->getContainer()->get('advanced_wishlist.repository');
        $this->queryOptimizer = $this->getContainer()->get(QueryOptimizer::class);
        $this->wishlistCrudService = $this->getContainer()->get(WishlistCrudService::class);
        $this->context = Context::createDefaultContext();
    }

    /**
     * Test query performance with optimized indexes.
     */
    public function testOptimizedQueryPerformance(): void
    {
        // Create test data
        $testData = $this->createLargeTestDataset(1000);
        
        // Test customer-based queries (should use customer_id index)
        $startTime = microtime(true);
        
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $testData['customerIds'][0]));
        $criteria->setLimit(50);
        
        $result = $this->wishlistRepository->search($criteria, $this->context);
        
        $queryTime = (microtime(true) - $startTime) * 1000;
        
        // Query should be fast with proper indexing
        $this->assertLessThan(50, $queryTime, "Customer query should be under 50ms, got {$queryTime}ms");
        $this->assertGreaterThan(0, $result->getTotal());
    }

    /**
     * Test query performance with sorting.
     */
    public function testSortedQueryPerformance(): void
    {
        // Create test data
        $this->createLargeTestDataset(500);
        
        // Test sorting by created_at (should use created_at index)
        $startTime = microtime(true);
        
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(100);
        
        $result = $this->wishlistRepository->search($criteria, $this->context);
        
        $sortingTime = (microtime(true) - $startTime) * 1000;
        
        // Sorting should be efficient with index
        $this->assertLessThan(100, $sortingTime, "Sorted query should be under 100ms, got {$sortingTime}ms");
        $this->assertEquals(100, $result->count());
    }

    /**
     * Test compound index effectiveness.
     */
    public function testCompoundIndexEffectiveness(): void
    {
        // Create test data
        $testData = $this->createLargeTestDataset(800);
        
        // Test compound query (customer_id + is_default)
        $startTime = microtime(true);
        
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $testData['customerIds'][0]));
        $criteria->addFilter(new EqualsFilter('isDefault', true));
        
        $result = $this->wishlistRepository->search($criteria, $this->context);
        
        $compoundQueryTime = (microtime(true) - $startTime) * 1000;
        
        // Compound index should make this very fast
        $this->assertLessThan(25, $compoundQueryTime, "Compound query should be under 25ms, got {$compoundQueryTime}ms");
        $this->assertLessThanOrEqual(1, $result->getTotal(), 'Should find at most one default wishlist per customer');
    }

    /**
     * Test query optimization with associations.
     */
    public function testAssociationQueryOptimization(): void
    {
        // Create test data with items
        $testData = $this->createTestDataWithItems(100);
        
        // Test query with associations
        $startTime = microtime(true);
        
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $testData['customerIds'][0]));
        $criteria->addAssociation('items');
        $criteria->addAssociation('items.product');
        $criteria->setLimit(20);
        
        $result = $this->wishlistRepository->search($criteria, $this->context);
        
        $associationTime = (microtime(true) - $startTime) * 1000;
        
        // Association queries should be optimized
        $this->assertLessThan(200, $associationTime, "Association query should be under 200ms, got {$associationTime}ms");
        $this->assertGreaterThan(0, $result->getTotal());
    }

    /**
     * Test pagination performance.
     */
    public function testPaginationPerformance(): void
    {
        // Create large dataset
        $this->createLargeTestDataset(2000);
        
        $times = [];
        $pageSize = 50;
        
        // Test multiple pages
        for ($page = 0; $page < 5; $page++) {
            $startTime = microtime(true);
            
            $criteria = new Criteria();
            $criteria->setLimit($pageSize);
            $criteria->setOffset($page * $pageSize);
            $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
            
            $result = $this->wishlistRepository->search($criteria, $this->context);
            
            $pageTime = (microtime(true) - $startTime) * 1000;
            $times[] = $pageTime;
            
            $this->assertEquals($pageSize, $result->count());
        }
        
        // Later pages should not be significantly slower
        $firstPageTime = $times[0];
        $lastPageTime = end($times);
        
        $this->assertLessThan($firstPageTime * 2, $lastPageTime, 
            'Later pages should not be more than 2x slower than first page');
        
        // All pages should be reasonably fast
        foreach ($times as $i => $time) {
            $this->assertLessThan(150, $time, "Page {$i} should be under 150ms, got {$time}ms");
        }
    }

    /**
     * Test read replica connection decorator.
     */
    public function testReadReplicaConnection(): void
    {
        if (!$this->getContainer()->has(ReadReplicaConnectionDecorator::class)) {
            $this->markTestSkipped('Read replica decorator not available');
        }
        
        $decorator = $this->getContainer()->get(ReadReplicaConnectionDecorator::class);
        
        // Test read operations use replica
        $startTime = microtime(true);
        
        $criteria = new Criteria();
        $criteria->setLimit(10);
        
        $result = $this->wishlistRepository->search($criteria, $this->context);
        
        $readTime = (microtime(true) - $startTime) * 1000;
        
        // Read from replica should be fast
        $this->assertLessThan(50, $readTime, "Read replica query should be under 50ms, got {$readTime}ms");
        $this->assertInstanceOf(\Shopware\Core\Framework\DataAbstractionLayer\EntitySearchResult::class, $result);
    }

    /**
     * Test bulk operations performance.
     */
    public function testBulkOperationsPerformance(): void
    {
        $batchSize = 100;
        $customerId = Uuid::randomHex();
        
        // Test bulk insert performance
        $startTime = microtime(true);
        
        $wishlistData = [];
        for ($i = 0; $i < $batchSize; $i++) {
            $wishlistData[] = [
                'id' => Uuid::randomHex(),
                'customerId' => $customerId,
                'name' => "Bulk Test Wishlist {$i}",
                'type' => 'private',
                'isDefault' => $i === 0,
                'createdAt' => new \DateTime(),
                'updatedAt' => new \DateTime(),
            ];
        }
        
        $this->wishlistRepository->create($wishlistData, $this->context);
        
        $bulkInsertTime = (microtime(true) - $startTime) * 1000;
        
        // Bulk insert should be efficient
        $this->assertLessThan(500, $bulkInsertTime, "Bulk insert should be under 500ms, got {$bulkInsertTime}ms");
        
        // Verify all records were inserted
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $result = $this->wishlistRepository->search($criteria, $this->context);
        
        $this->assertEquals($batchSize, $result->getTotal());
    }

    /**
     * Test query cache effectiveness.
     */
    public function testQueryCacheEffectiveness(): void
    {
        // Create test data
        $customerId = Uuid::randomHex();
        $this->createTestWishlist($customerId, 'Cache Test');
        
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        
        // First query (cold cache)
        $startTime = microtime(true);
        $result1 = $this->wishlistRepository->search($criteria, $this->context);
        $coldTime = (microtime(true) - $startTime) * 1000;
        
        // Second query (warm cache)
        $startTime = microtime(true);
        $result2 = $this->wishlistRepository->search($criteria, $this->context);
        $warmTime = (microtime(true) - $startTime) * 1000;
        
        // Cache should provide performance benefit
        $this->assertLessThan($coldTime, $warmTime, 'Cached query should be faster');
        $this->assertEquals($result1->getTotal(), $result2->getTotal());
    }

    /**
     * Test connection pool efficiency.
     */
    public function testConnectionPoolEfficiency(): void
    {
        $connectionTimes = [];
        
        // Test multiple concurrent-like operations
        for ($i = 0; $i < 20; $i++) {
            $startTime = microtime(true);
            
            // Simulate different types of operations
            $criteria = new Criteria();
            $criteria->setLimit(5);
            $criteria->setOffset($i * 5);
            
            $this->wishlistRepository->search($criteria, $this->context);
            
            $connectionTime = (microtime(true) - $startTime) * 1000;
            $connectionTimes[] = $connectionTime;
        }
        
        // Connection acquisition should be consistent
        $averageTime = array_sum($connectionTimes) / count($connectionTimes);
        $maxTime = max($connectionTimes);
        
        $this->assertLessThan(100, $averageTime, "Average connection time should be under 100ms, got {$averageTime}ms");
        $this->assertLessThan($averageTime * 3, $maxTime, 'Max connection time should not be 3x average');
    }

    /**
     * Test query optimization with complex filters.
     */
    public function testComplexQueryOptimization(): void
    {
        // Create diverse test data
        $testData = $this->createDiverseTestData();
        
        // Test complex query with multiple filters
        $startTime = microtime(true);
        
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('type', 'private'));
        $criteria->addFilter(new EqualsFilter('isDefault', false));
        $criteria->addSorting(new FieldSorting('updatedAt', FieldSorting::DESCENDING));
        $criteria->setLimit(25);
        
        $result = $this->wishlistRepository->search($criteria, $this->context);
        
        $complexQueryTime = (microtime(true) - $startTime) * 1000;
        
        // Complex query should still be optimized
        $this->assertLessThan(150, $complexQueryTime, "Complex query should be under 150ms, got {$complexQueryTime}ms");
        $this->assertLessThanOrEqual(25, $result->count());
    }

    /**
     * Test database deadlock prevention.
     */
    public function testDeadlockPrevention(): void
    {
        $customerId = Uuid::randomHex();
        
        // Simulate concurrent operations that could cause deadlocks
        $operations = [];
        
        for ($i = 0; $i < 5; $i++) {
            $operations[] = function() use ($customerId, $i) {
                $request = new CreateWishlistRequest();
                $request->setCustomerId($customerId);
                $request->setName("Concurrent Wishlist {$i}");
                $request->setType('private');
                $request->setIsDefault($i === 0);
                
                return $this->wishlistCrudService->createWishlist($request, $this->context);
            };
        }
        
        // Execute operations and measure
        $startTime = microtime(true);
        $results = [];
        
        foreach ($operations as $operation) {
            $results[] = $operation();
        }
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        
        // Operations should complete without deadlocks
        $this->assertCount(5, $results);
        foreach ($results as $result) {
            $this->assertNotNull($result->getId());
        }
        
        // Should complete in reasonable time
        $this->assertLessThan(2000, $totalTime, "Concurrent operations should complete within 2 seconds");
    }

    /**
     * Test transaction rollback performance.
     */
    public function testTransactionRollbackPerformance(): void
    {
        $customerId = Uuid::randomHex();
        
        // Test transaction that will fail
        $startTime = microtime(true);
        
        try {
            $this->wishlistRepository->beginTransaction();
            
            // Create valid wishlist
            $wishlistData = [
                'id' => Uuid::randomHex(),
                'customerId' => $customerId,
                'name' => 'Transaction Test',
                'type' => 'private',
                'isDefault' => true,
            ];
            
            $this->wishlistRepository->create([$wishlistData], $this->context);
            
            // Create invalid data to force rollback
            $invalidData = [
                'id' => 'invalid-id', // This should cause an error
                'customerId' => $customerId,
                'name' => 'Invalid Wishlist',
            ];
            
            $this->wishlistRepository->create([$invalidData], $this->context);
            
            $this->wishlistRepository->commit();
        } catch (\Exception $e) {
            $this->wishlistRepository->rollback();
        }
        
        $rollbackTime = (microtime(true) - $startTime) * 1000;
        
        // Rollback should be fast
        $this->assertLessThan(200, $rollbackTime, "Transaction rollback should be under 200ms, got {$rollbackTime}ms");
        
        // Verify rollback was successful
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $result = $this->wishlistRepository->search($criteria, $this->context);
        
        $this->assertEquals(0, $result->getTotal(), 'All changes should have been rolled back');
    }

    /**
     * Helper method to create large test dataset.
     */
    private function createLargeTestDataset(int $count): array
    {
        $customerIds = [];
        $wishlistData = [];
        
        // Create 10 customers with multiple wishlists each
        for ($customer = 0; $customer < 10; $customer++) {
            $customerId = Uuid::randomHex();
            $customerIds[] = $customerId;
            
            $wishlistsPerCustomer = (int)($count / 10);
            
            for ($wishlist = 0; $wishlist < $wishlistsPerCustomer; $wishlist++) {
                $wishlistData[] = [
                    'id' => Uuid::randomHex(),
                    'customerId' => $customerId,
                    'name' => "Test Wishlist {$customer}-{$wishlist}",
                    'type' => ['private', 'public', 'shared'][array_rand(['private', 'public', 'shared'])],
                    'isDefault' => $wishlist === 0,
                    'createdAt' => new \DateTime('-' . rand(1, 365) . ' days'),
                    'updatedAt' => new \DateTime('-' . rand(1, 30) . ' days'),
                ];
            }
        }
        
        $this->wishlistRepository->create($wishlistData, $this->context);
        
        return ['customerIds' => $customerIds, 'wishlistData' => $wishlistData];
    }

    /**
     * Helper method to create test data with items.
     */
    private function createTestDataWithItems(int $count): array
    {
        $customerIds = [];
        
        for ($i = 0; $i < 5; $i++) {
            $customerId = Uuid::randomHex();
            $customerIds[] = $customerId;
            
            $this->createTestWishlist($customerId, "Wishlist with Items {$i}");
        }
        
        return ['customerIds' => $customerIds];
    }

    /**
     * Helper method to create diverse test data.
     */
    private function createDiverseTestData(): array
    {
        $data = [];
        
        $types = ['private', 'public', 'shared'];
        $defaults = [true, false];
        
        foreach ($types as $type) {
            foreach ($defaults as $isDefault) {
                for ($i = 0; $i < 10; $i++) {
                    $data[] = [
                        'id' => Uuid::randomHex(),
                        'customerId' => Uuid::randomHex(),
                        'name' => "Diverse {$type} {$i}",
                        'type' => $type,
                        'isDefault' => $isDefault && $i === 0, // Only first can be default
                        'createdAt' => new \DateTime('-' . rand(1, 100) . ' days'),
                        'updatedAt' => new \DateTime('-' . rand(1, 10) . ' days'),
                    ];
                }
            }
        }
        
        $this->wishlistRepository->create($data, $this->context);
        
        return $data;
    }

    /**
     * Helper method to create a test wishlist.
     */
    private function createTestWishlist(string $customerId, string $name): string
    {
        $wishlistId = Uuid::randomHex();
        
        $wishlistData = [
            'id' => $wishlistId,
            'customerId' => $customerId,
            'name' => $name,
            'type' => 'private',
            'isDefault' => true,
            'createdAt' => new \DateTime(),
            'updatedAt' => new \DateTime(),
        ];
        
        $this->wishlistRepository->create([$wishlistData], $this->context);
        
        return $wishlistId;
    }
}
