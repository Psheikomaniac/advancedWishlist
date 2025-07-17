<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Integration;

use AdvancedWishlist\Core\DTO\Request\AddItemRequest;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateItemRequest;
use AdvancedWishlist\Core\Exception\DuplicateWishlistItemException;
use AdvancedWishlist\Core\Exception\WishlistItemNotFoundException;
use AdvancedWishlist\Core\Service\WishlistCrudService;
use AdvancedWishlist\Core\Service\WishlistItemService;
use AdvancedWishlist\Tests\Factory\WishlistFactory;
use AdvancedWishlist\Tests\Factory\WishlistItemFactory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Integration tests for WishlistItemService.
 */
class WishlistItemServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private WishlistItemService $wishlistItemService;
    private WishlistCrudService $wishlistCrudService;
    private EntityRepository $wishlistRepository;
    private EntityRepository $productRepository;
    private WishlistFactory $wishlistFactory;
    private WishlistItemFactory $wishlistItemFactory;
    private Context $context;

    protected function setUp(): void
    {
        $this->wishlistItemService = $this->getContainer()->get(WishlistItemService::class);
        $this->wishlistCrudService = $this->getContainer()->get(WishlistCrudService::class);
        $this->wishlistRepository = $this->getContainer()->get('wishlist.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->wishlistFactory = new WishlistFactory($this->wishlistRepository);
        $this->wishlistItemFactory = new WishlistItemFactory($this->getContainer()->get('wishlist_item.repository'));
        $this->context = Context::createDefaultContext();
    }

    public function testAddItem(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $wishlistId = $this->createTestWishlist($customerId, 'Test Wishlist');
        $productId = $this->createTestProduct('Test Product');

        $request = new AddItemRequest();
        $request->setWishlistId($wishlistId);
        $request->setProductId($productId);
        $request->setQuantity(2);
        $request->setNote('Test note');

        // Act
        $response = $this->wishlistItemService->addItem($request, $this->context);

        // Assert
        $this->assertNotNull($response->getId());
        $this->assertEquals($wishlistId, $response->getWishlistId());
        $this->assertEquals($productId, $response->getProductId());
        $this->assertEquals(2, $response->getQuantity());
        $this->assertEquals('Test note', $response->getNote());

        // Verify item was added to the wishlist
        $wishlist = $this->wishlistCrudService->loadWishlist($wishlistId, $this->context);
        $this->assertCount(1, $wishlist->getItems());
        $this->assertEquals($productId, $wishlist->getItems()->first()->getProductId());
    }

    public function testAddDuplicateItemThrowsException(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $wishlistId = $this->createTestWishlist($customerId, 'Test Wishlist');
        $productId = $this->createTestProduct('Test Product');

        $request = new AddItemRequest();
        $request->setWishlistId($wishlistId);
        $request->setProductId($productId);
        $request->setQuantity(1);

        // Add the item first time
        $this->wishlistItemService->addItem($request, $this->context);

        // Act & Assert
        $this->expectException(DuplicateWishlistItemException::class);
        $this->wishlistItemService->addItem($request, $this->context);
    }

    public function testUpdateItem(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $wishlistId = $this->createTestWishlist($customerId, 'Test Wishlist');
        $productId = $this->createTestProduct('Test Product');

        // Add an item
        $addRequest = new AddItemRequest();
        $addRequest->setWishlistId($wishlistId);
        $addRequest->setProductId($productId);
        $addRequest->setQuantity(1);
        $addResponse = $this->wishlistItemService->addItem($addRequest, $this->context);
        $itemId = $addResponse->getId();

        // Create update request
        $updateRequest = new UpdateItemRequest();
        $updateRequest->setItemId($itemId);
        $updateRequest->setQuantity(3);
        $updateRequest->setNote('Updated note');
        $updateRequest->setPriority(5);

        // Act
        $response = $this->wishlistItemService->updateItem($updateRequest, $this->context);

        // Assert
        $this->assertEquals($itemId, $response->getId());
        $this->assertEquals(3, $response->getQuantity());
        $this->assertEquals('Updated note', $response->getNote());
        $this->assertEquals(5, $response->getPriority());
    }

    public function testRemoveItem(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $wishlistId = $this->createTestWishlist($customerId, 'Test Wishlist');
        $productId = $this->createTestProduct('Test Product');

        // Add an item
        $addRequest = new AddItemRequest();
        $addRequest->setWishlistId($wishlistId);
        $addRequest->setProductId($productId);
        $addResponse = $this->wishlistItemService->addItem($addRequest, $this->context);
        $itemId = $addResponse->getId();

        // Act
        $this->wishlistItemService->removeItem($wishlistId, $itemId, $this->context);

        // Assert
        $wishlist = $this->wishlistCrudService->loadWishlist($wishlistId, $this->context);
        $this->assertCount(0, $wishlist->getItems());

        // Verify item no longer exists
        $this->expectException(WishlistItemNotFoundException::class);
        $this->wishlistItemService->removeItem($wishlistId, $itemId, $this->context);
    }

    public function testMoveItem(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $sourceWishlistId = $this->createTestWishlist($customerId, 'Source Wishlist');
        $targetWishlistId = $this->createTestWishlist($customerId, 'Target Wishlist');
        $productId = $this->createTestProduct('Test Product');

        // Add an item to source wishlist
        $addRequest = new AddItemRequest();
        $addRequest->setWishlistId($sourceWishlistId);
        $addRequest->setProductId($productId);
        $addResponse = $this->wishlistItemService->addItem($addRequest, $this->context);
        $itemId = $addResponse->getId();

        // Act
        $response = $this->wishlistItemService->moveItem(
            $sourceWishlistId,
            $targetWishlistId,
            $itemId,
            false, // move, not copy
            $this->context
        );

        // Assert
        $this->assertEquals($targetWishlistId, $response->getWishlistId());
        $this->assertEquals($productId, $response->getProductId());

        // Verify item was moved
        $sourceWishlist = $this->wishlistCrudService->loadWishlist($sourceWishlistId, $this->context);
        $targetWishlist = $this->wishlistCrudService->loadWishlist($targetWishlistId, $this->context);
        $this->assertCount(0, $sourceWishlist->getItems());
        $this->assertCount(1, $targetWishlist->getItems());
    }

    public function testBulkAddItems(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $wishlistId = $this->createTestWishlist($customerId, 'Test Wishlist');
        $productId1 = $this->createTestProduct('Product 1');
        $productId2 = $this->createTestProduct('Product 2');
        $productId3 = $this->createTestProduct('Product 3');

        $items = [
            [
                'productId' => $productId1,
                'quantity' => 1,
            ],
            [
                'productId' => $productId2,
                'quantity' => 2,
                'note' => 'Note for product 2',
            ],
            [
                'productId' => $productId3,
                'quantity' => 3,
                'priority' => 5,
            ],
        ];

        // Act
        $result = $this->wishlistItemService->bulkAddItems($wishlistId, $items, $this->context);

        // Assert
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(3, $result['successful']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(3, $result['results']);

        // Verify items were added
        $wishlist = $this->wishlistCrudService->loadWishlist($wishlistId, $this->context);
        $this->assertCount(3, $wishlist->getItems());
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

    /**
     * Helper method to create a test product.
     */
    private function createTestProduct(string $name): string
    {
        $productId = Uuid::randomHex();
        $data = [
            'id' => $productId,
            'name' => $name,
            'productNumber' => 'TEST-'.$productId,
            'stock' => 10,
            'price' => [
                ['currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca', 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => '19%', 'taxRate' => 19],
        ];

        $this->productRepository->create([$data], $this->context);

        return $productId;
    }
}
