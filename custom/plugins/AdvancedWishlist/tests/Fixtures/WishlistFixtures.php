<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Fixtures;

use AdvancedWishlist\Core\Content\Wishlist\WishlistType;
use AdvancedWishlist\Tests\Factory\WishlistFactory;
use AdvancedWishlist\Tests\Factory\WishlistItemFactory;
use Doctrine\Common\DataFixtures\Fixture;
use Doctrine\Persistence\ObjectManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class WishlistFixtures extends Fixture
{
    private WishlistFactory $wishlistFactory;
    private WishlistItemFactory $wishlistItemFactory;

    public function __construct(
        WishlistFactory $wishlistFactory,
        WishlistItemFactory $wishlistItemFactory,
    ) {
        $this->wishlistFactory = $wishlistFactory;
        $this->wishlistItemFactory = $wishlistItemFactory;
    }

    public function load(ObjectManager $manager): void
    {
        $context = Context::createDefaultContext();

        // Create test wishlists
        $this->createPrivateWishlist($context);
        $this->createPublicWishlist($context);
        $this->createSharedWishlist($context);
        $this->createWishlistWithItems($context);
    }

    private function createPrivateWishlist(Context $context): void
    {
        $customerId = Uuid::randomHex();
        $wishlistId = $this->wishlistFactory->createWishlist([
            'name' => 'Private Test Wishlist',
            'customerId' => $customerId,
            'type' => WishlistType::PRIVATE,
        ], $context);

        $this->addReference('private-wishlist', $wishlistId);
        $this->addReference('private-wishlist-customer', $customerId);
    }

    private function createPublicWishlist(Context $context): void
    {
        $customerId = Uuid::randomHex();
        $wishlistId = $this->wishlistFactory->createWishlist([
            'name' => 'Public Test Wishlist',
            'customerId' => $customerId,
            'type' => WishlistType::PUBLIC,
        ], $context);

        $this->addReference('public-wishlist', $wishlistId);
        $this->addReference('public-wishlist-customer', $customerId);
    }

    private function createSharedWishlist(Context $context): void
    {
        $customerId = Uuid::randomHex();
        $wishlistId = $this->wishlistFactory->createWishlist([
            'name' => 'Shared Test Wishlist',
            'customerId' => $customerId,
            'type' => WishlistType::SHARED,
        ], $context);

        $this->addReference('shared-wishlist', $wishlistId);
        $this->addReference('shared-wishlist-customer', $customerId);
    }

    private function createWishlistWithItems(Context $context): void
    {
        $customerId = Uuid::randomHex();
        $wishlistId = $this->wishlistFactory->createWishlist([
            'name' => 'Wishlist With Items',
            'customerId' => $customerId,
            'type' => WishlistType::PRIVATE,
        ], $context);

        // Add items to the wishlist
        $this->wishlistItemFactory->createWishlistItem([
            'wishlistId' => $wishlistId,
            'productId' => Uuid::randomHex(),
            'productVersionId' => Uuid::randomHex(),
            'quantity' => 1,
        ], $context);

        $this->wishlistItemFactory->createWishlistItem([
            'wishlistId' => $wishlistId,
            'productId' => Uuid::randomHex(),
            'productVersionId' => Uuid::randomHex(),
            'quantity' => 2,
        ], $context);

        $this->addReference('wishlist-with-items', $wishlistId);
        $this->addReference('wishlist-with-items-customer', $customerId);
    }
}
