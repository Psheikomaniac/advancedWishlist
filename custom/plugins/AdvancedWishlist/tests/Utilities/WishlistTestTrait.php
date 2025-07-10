<?php declare(strict_types=1);

namespace AdvancedWishlist\Tests\Utilities;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Content\Wishlist\WishlistType;
use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemEntity;
use AdvancedWishlist\Tests\Factory\WishlistFactory;
use AdvancedWishlist\Tests\Factory\WishlistItemFactory;

trait WishlistTestTrait
{
    protected function createWishlist(array $data = [], Context $context = null): string
    {
        $context = $context ?? Context::createDefaultContext();

        $defaults = [
            'name' => 'Test Wishlist',
            'customerId' => Uuid::randomHex(),
            'type' => WishlistType::PRIVATE,
        ];

        $data = array_merge($defaults, $data);

        $wishlistFactory = $this->getContainer()->get(WishlistFactory::class);
        return $wishlistFactory->createWishlist($data, $context);
    }

    protected function createWishlistWithItems(int $itemCount = 2, array $wishlistData = [], Context $context = null): string
    {
        $context = $context ?? Context::createDefaultContext();

        $wishlistId = $this->createWishlist($wishlistData, $context);

        $wishlistItemFactory = $this->getContainer()->get(WishlistItemFactory::class);

        for ($i = 0; $i < $itemCount; $i++) {
            $wishlistItemFactory->createWishlistItem([
                'wishlistId' => $wishlistId,
                'productId' => Uuid::randomHex(),
                'productVersionId' => Uuid::randomHex(),
                'quantity' => rand(1, 5),
            ], $context);
        }

        return $wishlistId;
    }

    protected function getWishlist(string $wishlistId, Context $context = null): ?WishlistEntity
    {
        $context = $context ?? Context::createDefaultContext();

        $wishlistFactory = $this->getContainer()->get(WishlistFactory::class);
        return $wishlistFactory->getWishlist($wishlistId, $context);
    }

    protected function assertWishlistEquals(
        WishlistEntity $expected,
        WishlistEntity $actual,
        array $fields = ['name', 'type', 'customerId']
    ): void {
        foreach ($fields as $field) {
            $getter = 'get' . ucfirst($field);
            $this->assertEquals(
                $expected->$getter(),
                $actual->$getter(),
                "Field '$field' does not match expected value"
            );
        }
    }

    protected function assertWishlistHasItems(WishlistEntity $wishlist, int $expectedCount = null): void
    {
        $this->assertNotNull($wishlist->getItems(), 'Wishlist items collection is null');

        if ($expectedCount !== null) {
            $this->assertCount(
                $expectedCount,
                $wishlist->getItems(),
                sprintf('Expected wishlist to have %d items, but found %d', $expectedCount, count($wishlist->getItems()))
            );
        }
    }

    protected function assertWishlistContainsProduct(WishlistEntity $wishlist, string $productId): void
    {
        $this->assertNotNull($wishlist->getItems(), 'Wishlist items collection is null');

        $found = false;
        foreach ($wishlist->getItems() as $item) {
            if ($item->getProductId() === $productId) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, sprintf('Product with ID %s not found in wishlist', $productId));
    }

    protected function createSalesChannelContextWithCustomer(string $customerId): SalesChannelContext
    {
        // This is a simplified version - in a real implementation, you would use
        // Shopware's SalesChannelContextFactory to create a proper context

        $context = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();
        $customer->setId($customerId);

        $context->method('getCustomer')->willReturn($customer);

        return $context;
    }
}
