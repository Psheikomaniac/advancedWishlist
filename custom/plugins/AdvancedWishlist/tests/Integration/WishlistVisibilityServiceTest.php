<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Integration;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Domain\Service\WishlistVisibilityService;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\Service\WishlistCrudService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Integration tests for WishlistVisibilityService.
 */
class WishlistVisibilityServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private WishlistVisibilityService $visibilityService;
    private WishlistCrudService $wishlistCrudService;
    private Context $context;

    protected function setUp(): void
    {
        $this->visibilityService = $this->getContainer()->get(WishlistVisibilityService::class);
        $this->wishlistCrudService = $this->getContainer()->get(WishlistCrudService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testCanViewPrivateWishlist(): void
    {
        // Arrange
        $ownerId = Uuid::randomHex();
        $otherUserId = Uuid::randomHex();
        $wishlist = $this->createWishlist($ownerId, 'Private Wishlist', 'private');

        // Act & Assert
        // Owner can view
        $this->assertTrue($this->visibilityService->canView($wishlist, $this->context, $ownerId));

        // Other user cannot view
        $this->assertFalse($this->visibilityService->canView($wishlist, $this->context, $otherUserId));

        // Guest cannot view
        $this->assertFalse($this->visibilityService->canView($wishlist, $this->context, null));
    }

    public function testCanViewPublicWishlist(): void
    {
        // Arrange
        $ownerId = Uuid::randomHex();
        $otherUserId = Uuid::randomHex();
        $wishlist = $this->createWishlist($ownerId, 'Public Wishlist', 'public');

        // Act & Assert
        // Owner can view
        $this->assertTrue($this->visibilityService->canView($wishlist, $this->context, $ownerId));

        // Other user can view
        $this->assertTrue($this->visibilityService->canView($wishlist, $this->context, $otherUserId));

        // Guest can view
        $this->assertTrue($this->visibilityService->canView($wishlist, $this->context, null));
    }

    public function testCanViewSharedWishlist(): void
    {
        // Arrange
        $ownerId = Uuid::randomHex();
        $sharedUserId = Uuid::randomHex();
        $otherUserId = Uuid::randomHex();
        $wishlist = $this->createWishlist($ownerId, 'Shared Wishlist', 'shared');

        // Simulate sharing with sharedUserId
        // Note: In a real test, we would need to actually share the wishlist with the user
        // For now, we'll mock the behavior by directly testing the strategy

        // Act & Assert
        // Owner can view
        $this->assertTrue($this->visibilityService->canView($wishlist, $this->context, $ownerId));

        // Other user cannot view
        $this->assertFalse($this->visibilityService->canView($wishlist, $this->context, $otherUserId));

        // Guest cannot view
        $this->assertFalse($this->visibilityService->canView($wishlist, $this->context, null));
    }

    public function testCanEditWishlist(): void
    {
        // Arrange
        $ownerId = Uuid::randomHex();
        $otherUserId = Uuid::randomHex();

        // Test for each type
        $privateWishlist = $this->createWishlist($ownerId, 'Private Wishlist', 'private');
        $publicWishlist = $this->createWishlist($ownerId, 'Public Wishlist', 'public');
        $sharedWishlist = $this->createWishlist($ownerId, 'Shared Wishlist', 'shared');

        // Act & Assert
        // Owner can edit all types
        $this->assertTrue($this->visibilityService->canEdit($privateWishlist, $this->context, $ownerId));
        $this->assertTrue($this->visibilityService->canEdit($publicWishlist, $this->context, $ownerId));
        $this->assertTrue($this->visibilityService->canEdit($sharedWishlist, $this->context, $ownerId));

        // Other user cannot edit any type
        $this->assertFalse($this->visibilityService->canEdit($privateWishlist, $this->context, $otherUserId));
        $this->assertFalse($this->visibilityService->canEdit($publicWishlist, $this->context, $otherUserId));
        $this->assertFalse($this->visibilityService->canEdit($sharedWishlist, $this->context, $otherUserId));

        // Guest cannot edit any type
        $this->assertFalse($this->visibilityService->canEdit($privateWishlist, $this->context, null));
        $this->assertFalse($this->visibilityService->canEdit($publicWishlist, $this->context, null));
        $this->assertFalse($this->visibilityService->canEdit($sharedWishlist, $this->context, null));
    }

    public function testCanShareWishlist(): void
    {
        // Arrange
        $ownerId = Uuid::randomHex();
        $otherUserId = Uuid::randomHex();

        // Test for each type
        $privateWishlist = $this->createWishlist($ownerId, 'Private Wishlist', 'private');
        $publicWishlist = $this->createWishlist($ownerId, 'Public Wishlist', 'public');
        $sharedWishlist = $this->createWishlist($ownerId, 'Shared Wishlist', 'shared');

        // Act & Assert
        // Owner can share all types
        $this->assertTrue($this->visibilityService->canShare($privateWishlist, $this->context, $ownerId));
        $this->assertTrue($this->visibilityService->canShare($publicWishlist, $this->context, $ownerId));
        $this->assertTrue($this->visibilityService->canShare($sharedWishlist, $this->context, $ownerId));

        // Other user can share public wishlists
        $this->assertFalse($this->visibilityService->canShare($privateWishlist, $this->context, $otherUserId));
        $this->assertTrue($this->visibilityService->canShare($publicWishlist, $this->context, $otherUserId));
        $this->assertFalse($this->visibilityService->canShare($sharedWishlist, $this->context, $otherUserId));

        // Guest cannot share any type
        $this->assertFalse($this->visibilityService->canShare($privateWishlist, $this->context, null));
        $this->assertTrue($this->visibilityService->canShare($publicWishlist, $this->context, null));
        $this->assertFalse($this->visibilityService->canShare($sharedWishlist, $this->context, null));
    }

    public function testIsOwner(): void
    {
        // Arrange
        $ownerId = Uuid::randomHex();
        $otherUserId = Uuid::randomHex();
        $wishlist = $this->createWishlist($ownerId, 'Test Wishlist', 'private');

        // Act & Assert
        $this->assertTrue($this->visibilityService->isOwner($wishlist, $ownerId));
        $this->assertFalse($this->visibilityService->isOwner($wishlist, $otherUserId));
        $this->assertFalse($this->visibilityService->isOwner($wishlist, null));
    }

    public function testGetCustomerIdFromContext(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();

        // Create a mock SalesChannelContext with a customer
        $customer = new CustomerEntity();
        $customer->setId($customerId);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        // Act
        $result = $this->visibilityService->getCustomerIdFromContext($salesChannelContext);

        // Assert
        $this->assertEquals($customerId, $result);

        // Test with regular context (no customer)
        $this->assertNull($this->visibilityService->getCustomerIdFromContext($this->context));
    }

    /**
     * Helper method to create a wishlist with the specified type.
     */
    private function createWishlist(string $customerId, string $name, string $type): WishlistEntity
    {
        $request = new CreateWishlistRequest();
        $request->setCustomerId($customerId);
        $request->setName($name);
        $request->setType($type);
        $request->setIsDefault(false);

        $response = $this->wishlistCrudService->createWishlist($request, $this->context);

        return $this->wishlistCrudService->loadWishlist($response->getId(), $this->context);
    }
}
