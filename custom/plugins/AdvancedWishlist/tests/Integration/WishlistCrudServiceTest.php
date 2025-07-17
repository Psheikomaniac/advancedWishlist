<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Integration;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\DTO\Response\WishlistResponse;
use AdvancedWishlist\Core\Exception\WishlistNotFoundException;
use AdvancedWishlist\Core\Service\WishlistCrudService;
use AdvancedWishlist\Tests\Factory\WishlistFactory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Integration tests for WishlistCrudService.
 */
class WishlistCrudServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private WishlistCrudService $wishlistCrudService;
    private EntityRepository $wishlistRepository;
    private WishlistFactory $wishlistFactory;
    private Context $context;

    protected function setUp(): void
    {
        $this->wishlistCrudService = $this->getContainer()->get(WishlistCrudService::class);
        $this->wishlistRepository = $this->getContainer()->get('wishlist.repository');
        $this->wishlistFactory = new WishlistFactory($this->wishlistRepository);
        $this->context = Context::createDefaultContext();
    }

    public function testCreateWishlist(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $request = new CreateWishlistRequest();
        $request->setCustomerId($customerId);
        $request->setName('Test Wishlist');
        $request->setType('private');
        $request->setIsDefault(true);

        // Act
        $response = $this->wishlistCrudService->createWishlist($request, $this->context);

        // Assert
        $this->assertInstanceOf(WishlistResponse::class, $response);
        $this->assertEquals('Test Wishlist', $response->getName());
        $this->assertEquals('private', $response->getType());
        $this->assertTrue($response->isDefault());

        // Verify wishlist was created in database
        $wishlist = $this->wishlistCrudService->loadWishlist($response->getId(), $this->context);
        $this->assertEquals($customerId, $wishlist->getCustomerId());
        $this->assertEquals('Test Wishlist', $wishlist->getName());
    }

    public function testUpdateWishlist(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $wishlistId = $this->createTestWishlist($customerId, 'Original Name');

        $request = new UpdateWishlistRequest();
        $request->setWishlistId($wishlistId);
        $request->setName('Updated Name');
        $request->setDescription('Updated Description');

        // Act
        $response = $this->wishlistCrudService->updateWishlist($request, $this->context);

        // Assert
        $this->assertEquals('Updated Name', $response->getName());
        $this->assertEquals('Updated Description', $response->getDescription());

        // Verify wishlist was updated in database
        $wishlist = $this->wishlistCrudService->loadWishlist($wishlistId, $this->context);
        $this->assertEquals('Updated Name', $wishlist->getName());
        $this->assertEquals('Updated Description', $wishlist->getDescription());
    }

    public function testDeleteWishlist(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $wishlistId = $this->createTestWishlist($customerId, 'Wishlist to Delete');

        // Create a second wishlist to avoid the "cannot delete the only wishlist" error
        $this->createTestWishlist($customerId, 'Second Wishlist');

        // Act
        $this->wishlistCrudService->deleteWishlist($wishlistId, null, $this->context);

        // Assert
        $this->expectException(WishlistNotFoundException::class);
        $this->wishlistCrudService->loadWishlist($wishlistId, $this->context);
    }

    public function testGetOrCreateDefaultWishlist(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();

        // Act
        $wishlist = $this->wishlistCrudService->getOrCreateDefaultWishlist($customerId, $this->context);

        // Assert
        $this->assertNotNull($wishlist);
        $this->assertEquals($customerId, $wishlist->getCustomerId());
        $this->assertTrue($wishlist->isDefault());

        // Act again - should return the same wishlist
        $wishlist2 = $this->wishlistCrudService->getOrCreateDefaultWishlist($customerId, $this->context);

        // Assert
        $this->assertEquals($wishlist->getId(), $wishlist2->getId());
    }

    public function testGetWishlists(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $this->createTestWishlist($customerId, 'Wishlist 1');
        $this->createTestWishlist($customerId, 'Wishlist 2');
        $this->createTestWishlist($customerId, 'Wishlist 3');

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setOffset(0);

        // Create a mock SalesChannelContext
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($this->context);

        // Act
        $result = $this->wishlistCrudService->getWishlists($customerId, $criteria, $salesChannelContext);

        // Assert
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('wishlists', $result);
        $this->assertEquals(3, $result['total']);
        $this->assertCount(3, $result['wishlists']);
    }

    /**
     * Helper method to create a test wishlist.
     */
    private function createTestWishlist(string $customerId, string $name): string
    {
        $request = new CreateWishlistRequest();
        $request->setCustomerId($customerId);
        $request->setName($name);
        $request->setType('private');
        $request->setIsDefault(false);

        $response = $this->wishlistCrudService->createWishlist($request, $this->context);

        return $response->getId();
    }
}
