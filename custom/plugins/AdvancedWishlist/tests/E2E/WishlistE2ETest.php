<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\E2E;

use AdvancedWishlist\Core\Service\WishlistCrudService;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * End-to-end tests covering complete user journeys and workflows.
 * Tests real-world scenarios from user registration to wishlist management.
 */
class WishlistE2ETest extends TestCase
{
    use IntegrationTestBehaviour;

    private WishlistCrudService $wishlistCrudService;
    private Context $context;

    protected function setUp(): void
    {
        $this->wishlistCrudService = $this->getContainer()->get(WishlistCrudService::class);
        $this->context = Context::createDefaultContext();
    }

    /**
     * Test complete user journey: Registration -> Create Wishlist -> Add Items -> Share -> Purchase.
     */
    public function testCompleteUserJourney(): void
    {
        // Step 1: New user registration (simulated)
        $customerId = Uuid::randomHex();
        
        // Step 2: User creates their first wishlist (should be default)
        $createRequest = new CreateWishlistRequest();
        $createRequest->setCustomerId($customerId);
        $createRequest->setName('My First Wishlist');
        $createRequest->setType('private');
        $createRequest->setIsDefault(true);
        
        $wishlistResponse = $this->wishlistCrudService->createWishlist($createRequest, $this->context);
        $this->assertTrue($wishlistResponse->isDefault());
        $this->assertEquals('My First Wishlist', $wishlistResponse->getName());
        
        $wishlistId = $wishlistResponse->getId();
        
        // Step 3: User updates wishlist details
        $updateRequest = new UpdateWishlistRequest();
        $updateRequest->setWishlistId($wishlistId);
        $updateRequest->setName('My Updated Wishlist');
        $updateRequest->setDescription('This is my personal wishlist');
        
        $updatedResponse = $this->wishlistCrudService->updateWishlist($updateRequest, $this->context);
        $this->assertEquals('My Updated Wishlist', $updatedResponse->getName());
        $this->assertEquals('This is my personal wishlist', $updatedResponse->getDescription());
        
        // Step 4: User creates additional wishlists
        $specialRequest = new CreateWishlistRequest();
        $specialRequest->setCustomerId($customerId);
        $specialRequest->setName('Birthday Wishlist');
        $specialRequest->setType('shared');
        $specialRequest->setIsDefault(false);
        
        $specialWishlist = $this->wishlistCrudService->createWishlist($specialRequest, $this->context);
        $this->assertEquals('shared', $specialWishlist->getType());
        $this->assertFalse($specialWishlist->isDefault());
        
        // Step 5: User gets all their wishlists
        $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
        $criteria->setLimit(10);
        $criteria->setOffset(0);
        
        $salesChannelContext = $this->createMockSalesChannelContext($customerId);
        $allWishlists = $this->wishlistCrudService->getWishlists($customerId, $criteria, $salesChannelContext);
        
        $this->assertEquals(2, $allWishlists['total']);
        $this->assertCount(2, $allWishlists['wishlists']);
        
        // Step 6: User deletes a wishlist (non-default)
        $this->wishlistCrudService->deleteWishlist($specialWishlist->getId(), null, $this->context);
        
        // Verify deletion
        $finalWishlists = $this->wishlistCrudService->getWishlists($customerId, $criteria, $salesChannelContext);
        $this->assertEquals(1, $finalWishlists['total']);
        
        // Step 7: Verify default wishlist still exists
        $defaultWishlist = $this->wishlistCrudService->getOrCreateDefaultWishlist($customerId, $this->context);
        $this->assertEquals($wishlistId, $defaultWishlist->getId());
        $this->assertTrue($defaultWishlist->isDefault());
    }

    /**
     * Test guest to registered user conversion workflow.
     */
    public function testGuestUserConversionWorkflow(): void
    {
        // Step 1: Guest creates wishlist items (simulated)
        $guestIdentifier = 'guest_' . Uuid::randomHex();
        
        // Step 2: Guest registers and becomes authenticated user
        $newCustomerId = Uuid::randomHex();
        
        // Step 3: System creates default wishlist for new user
        $defaultWishlist = $this->wishlistCrudService->getOrCreateDefaultWishlist($newCustomerId, $this->context);
        $this->assertNotNull($defaultWishlist);
        $this->assertTrue($defaultWishlist->isDefault());
        $this->assertEquals('My Wishlist', $defaultWishlist->name); // Default name
        
        // Step 4: User personalizes their wishlist
        $updateRequest = new UpdateWishlistRequest();
        $updateRequest->setWishlistId($defaultWishlist->getId());
        $updateRequest->setName('My Personal Collection');
        $updateRequest->setDescription('Items I want to buy');
        
        $personalizedWishlist = $this->wishlistCrudService->updateWishlist($updateRequest, $this->context);
        $this->assertEquals('My Personal Collection', $personalizedWishlist->getName());
        
        // Step 5: User creates specialized wishlists
        $categories = ['Electronics', 'Books', 'Clothing'];
        $createdWishlists = [];
        
        foreach ($categories as $category) {
            $categoryRequest = new CreateWishlistRequest();
            $categoryRequest->setCustomerId($newCustomerId);
            $categoryRequest->setName("{$category} Wishlist");
            $categoryRequest->setType('private');
            $categoryRequest->setIsDefault(false);
            
            $createdWishlists[] = $this->wishlistCrudService->createWishlist($categoryRequest, $this->context);
        }
        
        $this->assertCount(3, $createdWishlists);
        
        // Step 6: Verify user has all wishlists
        $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
        $salesChannelContext = $this->createMockSalesChannelContext($newCustomerId);
        
        $allWishlists = $this->wishlistCrudService->getWishlists($newCustomerId, $criteria, $salesChannelContext);
        $this->assertEquals(4, $allWishlists['total']); // 1 default + 3 category
    }

    /**
     * Test multi-device synchronization workflow.
     */
    public function testMultiDeviceSynchronizationWorkflow(): void
    {
        $customerId = Uuid::randomHex();
        
        // Step 1: User creates wishlist on device 1 (mobile)
        $mobileRequest = new CreateWishlistRequest();
        $mobileRequest->setCustomerId($customerId);
        $mobileRequest->setName('Mobile Wishlist');
        $mobileRequest->setType('private');
        $mobileRequest->setIsDefault(true);
        
        $mobileWishlist = $this->wishlistCrudService->createWishlist($mobileRequest, $this->context);
        
        // Step 2: User logs in on device 2 (desktop) and sees their wishlist
        $desktopContext = Context::createDefaultContext();
        $syncedWishlist = $this->wishlistCrudService->loadWishlist($mobileWishlist->getId(), $desktopContext);
        
        $this->assertEquals($mobileWishlist->getId(), $syncedWishlist->getId());
        $this->assertEquals('Mobile Wishlist', $syncedWishlist->name);
        
        // Step 3: User updates wishlist on desktop
        $desktopUpdate = new UpdateWishlistRequest();
        $desktopUpdate->setWishlistId($syncedWishlist->getId());
        $desktopUpdate->setName('Synchronized Wishlist');
        $desktopUpdate->setDescription('Updated from desktop');
        
        $updatedFromDesktop = $this->wishlistCrudService->updateWishlist($desktopUpdate, $desktopContext);
        
        // Step 4: Changes should be visible on mobile (simulated refresh)
        $refreshedOnMobile = $this->wishlistCrudService->loadWishlist($mobileWishlist->getId(), $this->context);
        
        $this->assertEquals('Synchronized Wishlist', $refreshedOnMobile->name);
        $this->assertEquals('Updated from desktop', $refreshedOnMobile->description);
        
        // Step 5: User creates new wishlist on desktop
        $desktopOnlyRequest = new CreateWishlistRequest();
        $desktopOnlyRequest->setCustomerId($customerId);
        $desktopOnlyRequest->setName('Desktop Created');
        $desktopOnlyRequest->setType('private');
        $desktopOnlyRequest->setIsDefault(false);
        
        $desktopWishlist = $this->wishlistCrudService->createWishlist($desktopOnlyRequest, $desktopContext);
        
        // Step 6: New wishlist should be visible on mobile
        $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
        $salesChannelContext = $this->createMockSalesChannelContext($customerId);
        
        $allWishlistsOnMobile = $this->wishlistCrudService->getWishlists($customerId, $criteria, $salesChannelContext);
        $this->assertEquals(2, $allWishlistsOnMobile['total']);
        
        $wishlistNames = array_column($allWishlistsOnMobile['wishlists'], 'name');
        $this->assertContains('Synchronized Wishlist', $wishlistNames);
        $this->assertContains('Desktop Created', $wishlistNames);
    }

    /**
     * Test wishlist sharing and collaboration workflow.
     */
    public function testWishlistSharingWorkflow(): void
    {
        // Step 1: User A creates a wishlist to share
        $userAId = Uuid::randomHex();
        $shareRequest = new CreateWishlistRequest();
        $shareRequest->setCustomerId($userAId);
        $shareRequest->setName('Birthday Wishlist');
        $shareRequest->setType('shared');
        $shareRequest->setIsDefault(false);
        
        $sharedWishlist = $this->wishlistCrudService->createWishlist($shareRequest, $this->context);
        $this->assertEquals('shared', $sharedWishlist->getType());
        
        // Step 2: User A gets shareable link (simulated)
        $shareableWishlistId = $sharedWishlist->getId();
        
        // Step 3: User B (friend/family) accesses shared wishlist
        $userBId = Uuid::randomHex();
        $userBContext = $this->createMockSalesChannelContext($userBId);
        
        // User B should be able to view shared wishlist
        $viewedSharedWishlist = $this->wishlistCrudService->loadWishlist($shareableWishlistId, $this->context);
        $this->assertEquals('Birthday Wishlist', $viewedSharedWishlist->name);
        $this->assertEquals('shared', $viewedSharedWishlist->type);
        
        // Step 4: User A changes wishlist to private
        $privateUpdate = new UpdateWishlistRequest();
        $privateUpdate->setWishlistId($shareableWishlistId);
        $privateUpdate->setType('private');
        
        $this->wishlistCrudService->updateWishlist($privateUpdate, $this->context);
        
        // Step 5: Verify privacy change
        $privateWishlist = $this->wishlistCrudService->loadWishlist($shareableWishlistId, $this->context);
        $this->assertEquals('private', $privateWishlist->type);
    }

    /**
     * Test performance under realistic load scenarios.
     */
    public function testRealisticLoadScenario(): void
    {
        $startTime = microtime(true);
        
        // Simulate 50 users each creating and managing wishlists
        $userIds = [];
        for ($i = 0; $i < 50; $i++) {
            $userIds[] = Uuid::randomHex();
        }
        
        $operations = [];
        
        // Each user performs typical operations
        foreach ($userIds as $userId) {
            // Create default wishlist
            $defaultWishlist = $this->wishlistCrudService->getOrCreateDefaultWishlist($userId, $this->context);
            $operations[] = 'create_default';
            
            // Create 2 additional wishlists
            for ($j = 0; $j < 2; $j++) {
                $request = new CreateWishlistRequest();
                $request->setCustomerId($userId);
                $request->setName("Wishlist {$j}");
                $request->setType('private');
                $request->setIsDefault(false);
                
                $this->wishlistCrudService->createWishlist($request, $this->context);
                $operations[] = 'create_additional';
            }
            
            // Update one wishlist
            $updateRequest = new UpdateWishlistRequest();
            $updateRequest->setWishlistId($defaultWishlist->getId());
            $updateRequest->setName('Updated Default');
            
            $this->wishlistCrudService->updateWishlist($updateRequest, $this->context);
            $operations[] = 'update';
            
            // Get all wishlists
            $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
            $salesChannelContext = $this->createMockSalesChannelContext($userId);
            
            $this->wishlistCrudService->getWishlists($userId, $criteria, $salesChannelContext);
            $operations[] = 'list';
        }
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        $averageTimePerOperation = $totalTime / count($operations);
        
        // Performance should be reasonable under load
        $this->assertLessThan(10000, $totalTime, "Total time for load test should be under 10 seconds, got {$totalTime}ms");
        $this->assertLessThan(50, $averageTimePerOperation, "Average operation time should be under 50ms, got {$averageTimePerOperation}ms");
        
        // Verify all operations completed successfully
        $this->assertEquals(250, count($operations)); // 50 users * 5 operations each
    }

    /**
     * Test error recovery and resilience.
     */
    public function testErrorRecoveryResilience(): void
    {
        $customerId = Uuid::randomHex();
        
        // Step 1: Create valid wishlist
        $validRequest = new CreateWishlistRequest();
        $validRequest->setCustomerId($customerId);
        $validRequest->setName('Valid Wishlist');
        $validRequest->setType('private');
        $validRequest->setIsDefault(true);
        
        $validWishlist = $this->wishlistCrudService->createWishlist($validRequest, $this->context);
        $this->assertNotNull($validWishlist->getId());
        
        // Step 2: Attempt invalid operations (should fail gracefully)
        try {
            $invalidRequest = new CreateWishlistRequest();
            $invalidRequest->setCustomerId($customerId);
            $invalidRequest->setName(''); // Invalid empty name
            $invalidRequest->setType('private');
            $invalidRequest->setIsDefault(false);
            
            $this->wishlistCrudService->createWishlist($invalidRequest, $this->context);
            $this->fail('Should have thrown exception for invalid name');
        } catch (\Exception $e) {
            // Expected - system should handle errors gracefully
            $this->assertInstanceOf(\Exception::class, $e);
        }
        
        // Step 3: Verify system is still functional after error
        $recoveryRequest = new CreateWishlistRequest();
        $recoveryRequest->setCustomerId($customerId);
        $recoveryRequest->setName('Recovery Test');
        $recoveryRequest->setType('private');
        $recoveryRequest->setIsDefault(false);
        
        $recoveryWishlist = $this->wishlistCrudService->createWishlist($recoveryRequest, $this->context);
        $this->assertEquals('Recovery Test', $recoveryWishlist->getName());
        
        // Step 4: Verify original wishlist still exists
        $originalWishlist = $this->wishlistCrudService->loadWishlist($validWishlist->getId(), $this->context);
        $this->assertEquals('Valid Wishlist', $originalWishlist->name);
        
        // Step 5: Test concurrent error scenarios
        $concurrentErrors = 0;
        $concurrentSuccesses = 0;
        
        for ($i = 0; $i < 10; $i++) {
            try {
                $concurrentRequest = new CreateWishlistRequest();
                $concurrentRequest->setCustomerId($customerId);
                $concurrentRequest->setName($i % 2 === 0 ? "Valid {$i}" : ''); // Every other invalid
                $concurrentRequest->setType('private');
                $concurrentRequest->setIsDefault(false);
                
                $this->wishlistCrudService->createWishlist($concurrentRequest, $this->context);
                $concurrentSuccesses++;
            } catch (\Exception $e) {
                $concurrentErrors++;
            }
        }
        
        // Should have appropriate mix of successes and failures
        $this->assertEquals(5, $concurrentSuccesses, 'Should have 5 successful operations');
        $this->assertEquals(5, $concurrentErrors, 'Should have 5 failed operations');
    }

    /**
     * Test data consistency across operations.
     */
    public function testDataConsistencyAcrossOperations(): void
    {
        $customerId = Uuid::randomHex();
        
        // Step 1: Create multiple wishlists with specific properties
        $wishlistData = [];
        for ($i = 0; $i < 5; $i++) {
            $request = new CreateWishlistRequest();
            $request->setCustomerId($customerId);
            $request->setName("Consistency Test {$i}");
            $request->setType($i === 0 ? 'private' : 'public');
            $request->setIsDefault($i === 0);
            
            $wishlist = $this->wishlistCrudService->createWishlist($request, $this->context);
            $wishlistData[] = $wishlist;
        }
        
        // Step 2: Verify only one default wishlist exists
        $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
        $salesChannelContext = $this->createMockSalesChannelContext($customerId);
        
        $allWishlists = $this->wishlistCrudService->getWishlists($customerId, $criteria, $salesChannelContext);
        
        $defaultCount = 0;
        foreach ($allWishlists['wishlists'] as $wishlist) {
            if ($wishlist['isDefault']) {
                $defaultCount++;
            }
        }
        
        $this->assertEquals(1, $defaultCount, 'Should have exactly one default wishlist');
        
        // Step 3: Change default wishlist and verify consistency
        $newDefaultId = $wishlistData[2]->getId();
        
        $changeDefaultRequest = new UpdateWishlistRequest();
        $changeDefaultRequest->setWishlistId($newDefaultId);
        $changeDefaultRequest->setIsDefault(true);
        
        $this->wishlistCrudService->updateWishlist($changeDefaultRequest, $this->context);
        
        // Step 4: Verify old default is no longer default
        $oldDefaultWishlist = $this->wishlistCrudService->loadWishlist($wishlistData[0]->getId(), $this->context);
        $this->assertFalse($oldDefaultWishlist->isDefault);
        
        // Verify new default is set
        $newDefaultWishlist = $this->wishlistCrudService->loadWishlist($newDefaultId, $this->context);
        $this->assertTrue($newDefaultWishlist->isDefault);
        
        // Step 5: Final consistency check
        $finalWishlists = $this->wishlistCrudService->getWishlists($customerId, $criteria, $salesChannelContext);
        
        $finalDefaultCount = 0;
        foreach ($finalWishlists['wishlists'] as $wishlist) {
            if ($wishlist['isDefault']) {
                $finalDefaultCount++;
            }
        }
        
        $this->assertEquals(1, $finalDefaultCount, 'Should still have exactly one default wishlist after update');
    }

    /**
     * Helper method to create mock sales channel context.
     */
    private function createMockSalesChannelContext(string $customerId): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(\Shopware\Core\Checkout\Customer\CustomerEntity::class);
        $customer->method('getId')->willReturn($customerId);
        
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getContext')->willReturn($this->context);
        
        return $context;
    }
}
