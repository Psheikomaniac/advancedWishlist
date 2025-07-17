<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Integration;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Content\Wishlist\WishlistType;
use AdvancedWishlist\Tests\Utilities\WishlistTestTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class WishlistRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use WishlistTestTrait;

    private EntityRepository $wishlistRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wishlistRepository = $this->getContainer()->get('wishlist.repository');
    }

    public function testFindByCustomer(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        // Create test wishlists for the customer
        $this->createWishlist([
            'customerId' => $customerId,
            'name' => 'First Wishlist',
            'type' => WishlistType::PRIVATE,
        ], $context);

        $this->createWishlist([
            'customerId' => $customerId,
            'name' => 'Second Wishlist',
            'type' => WishlistType::PUBLIC,
        ], $context);

        // Create a wishlist for another customer
        $this->createWishlist([
            'customerId' => Uuid::randomHex(),
            'name' => 'Another Customer Wishlist',
        ], $context);

        // Act
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        $result = $this->wishlistRepository->search($criteria, $context);

        // Assert
        $this->assertEquals(2, $result->getTotal(), 'Expected to find 2 wishlists for the customer');

        $wishlists = $result->getEntities();
        $wishlistNames = array_map(function (WishlistEntity $wishlist) {
            return $wishlist->getName();
        }, iterator_to_array($wishlists));

        $this->assertContains('First Wishlist', $wishlistNames, 'First wishlist not found');
        $this->assertContains('Second Wishlist', $wishlistNames, 'Second wishlist not found');
    }

    public function testFindByType(): void
    {
        // Arrange
        $context = Context::createDefaultContext();

        // Create test wishlists with different types
        $this->createWishlist([
            'name' => 'Private Wishlist 1',
            'type' => WishlistType::PRIVATE,
        ], $context);

        $this->createWishlist([
            'name' => 'Private Wishlist 2',
            'type' => WishlistType::PRIVATE,
        ], $context);

        $this->createWishlist([
            'name' => 'Public Wishlist',
            'type' => WishlistType::PUBLIC,
        ], $context);

        $this->createWishlist([
            'name' => 'Shared Wishlist',
            'type' => WishlistType::SHARED,
        ], $context);

        // Act
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('type', WishlistType::PRIVATE));

        $result = $this->wishlistRepository->search($criteria, $context);

        // Assert
        $this->assertGreaterThanOrEqual(2, $result->getTotal(), 'Expected to find at least 2 private wishlists');

        foreach ($result->getEntities() as $wishlist) {
            $this->assertEquals(
                WishlistType::PRIVATE,
                $wishlist->getType(),
                'Expected only private wishlists'
            );
        }
    }

    public function testFindWithItems(): void
    {
        // Arrange
        $context = Context::createDefaultContext();

        // Create a wishlist with items
        $wishlistId = $this->createWishlistWithItems(3, [
            'name' => 'Wishlist With Items',
        ], $context);

        // Act
        $criteria = new Criteria([$wishlistId]);
        $criteria->addAssociation('items');

        $result = $this->wishlistRepository->search($criteria, $context);

        // Assert
        $this->assertEquals(1, $result->getTotal(), 'Expected to find 1 wishlist');

        /** @var WishlistEntity $wishlist */
        $wishlist = $result->first();

        $this->assertNotNull($wishlist, 'Wishlist not found');
        $this->assertEquals('Wishlist With Items', $wishlist->getName());

        $this->assertWishlistHasItems($wishlist, 3);
    }
}
