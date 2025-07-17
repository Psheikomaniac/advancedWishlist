<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Integration;

use AdvancedWishlist\Core\Service\WishlistMergeService;
use AdvancedWishlist\Tests\Factory\WishlistFactory;
use AdvancedWishlist\Tests\Factory\WishlistItemFactory;
use AdvancedWishlist\Tests\Utilities\WishlistTestTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class WishlistMergeTest extends TestCase
{
    use IntegrationTestBehaviour;
    use WishlistTestTrait;

    private WishlistMergeService $mergeService;
    private WishlistFactory $wishlistFactory;
    private WishlistItemFactory $wishlistItemFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mergeService = $this->getContainer()->get(WishlistMergeService::class);
        $this->wishlistFactory = $this->getContainer()->get(WishlistFactory::class);
        $this->wishlistItemFactory = $this->getContainer()->get(WishlistItemFactory::class);
    }

    public function testMergeGuestWishlistToCustomer(): void
    {
        // Arrange
        $context = Context::createDefaultContext();
        $customerId = Uuid::randomHex();

        // Create a customer wishlist with some items
        $customerWishlistId = $this->createWishlist([
            'customerId' => $customerId,
            'name' => 'Customer Wishlist',
        ], $context);

        $product1Id = Uuid::randomHex();
        $product2Id = Uuid::randomHex();

        $this->wishlistItemFactory->createWishlistItem([
            'wishlistId' => $customerWishlistId,
            'productId' => $product1Id,
            'productVersionId' => Uuid::randomHex(),
            'quantity' => 1,
        ], $context);

        // Create a guest wishlist with some items (including one duplicate)
        $guestWishlistId = $this->createGuestWishlist($context);

        $this->wishlistItemFactory->createWishlistItem([
            'wishlistId' => $guestWishlistId,
            'productId' => $product1Id, // Duplicate product
            'productVersionId' => Uuid::randomHex(),
            'quantity' => 2, // Different quantity
        ], $context);

        $this->wishlistItemFactory->createWishlistItem([
            'wishlistId' => $guestWishlistId,
            'productId' => $product2Id, // New product
            'productVersionId' => Uuid::randomHex(),
            'quantity' => 1,
        ], $context);

        $product3Id = Uuid::randomHex();
        $this->wishlistItemFactory->createWishlistItem([
            'wishlistId' => $guestWishlistId,
            'productId' => $product3Id, // New product
            'productVersionId' => Uuid::randomHex(),
            'quantity' => 3,
        ], $context);

        // Act
        $result = $this->mergeService->mergeGuestWishlistToCustomer(
            $guestWishlistId,
            $customerWishlistId,
            $customerId,
            $context
        );

        // Assert
        $this->assertNotNull($result, 'Merge result should not be null');

        // Get the updated customer wishlist
        $criteria = new Criteria([$customerWishlistId]);
        $criteria->addAssociation('items');

        $updatedWishlist = $this->wishlistFactory->getWishlist($customerWishlistId, $context);

        $this->assertNotNull($updatedWishlist, 'Updated wishlist should not be null');
        $this->assertWishlistHasItems($updatedWishlist, 3);

        // Check that all products are in the wishlist
        $this->assertWishlistContainsProduct($updatedWishlist, $product1Id);
        $this->assertWishlistContainsProduct($updatedWishlist, $product2Id);
        $this->assertWishlistContainsProduct($updatedWishlist, $product3Id);

        // Check that the duplicate product has the higher quantity
        $items = $updatedWishlist->getItems();
        foreach ($items as $item) {
            if ($item->getProductId() === $product1Id) {
                $this->assertEquals(2, $item->getQuantity(), 'Duplicate product should have the higher quantity');
            }
        }
    }

    private function createGuestWishlist(Context $context): string
    {
        // Create a guest wishlist
        $guestWishlistId = Uuid::randomHex();

        $this->wishlistFactory->createWishlist([
            'id' => $guestWishlistId,
            'token' => Uuid::randomHex(),
            'name' => 'Guest Wishlist',
        ], $context);

        return $guestWishlistId;
    }
}
