<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Integration;

use AdvancedWishlist\Core\Content\Wishlist\Event\WishlistCreatedEvent;
use AdvancedWishlist\Core\Content\Wishlist\Event\WishlistDeletedEvent;
use AdvancedWishlist\Core\Content\Wishlist\Event\WishlistItemAddedEvent;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Service\WishlistService;
use AdvancedWishlist\Tests\Factory\WishlistFactory;
use AdvancedWishlist\Tests\Factory\WishlistItemFactory;
use AdvancedWishlist\Tests\Fixtures\WishlistFixtures;
use AdvancedWishlist\Tests\Utilities\WishlistTestTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class WishlistEventTest extends TestCase
{
    use IntegrationTestBehaviour;
    use WishlistTestTrait;

    private WishlistService $wishlistService;
    private EventDispatcherInterface $eventDispatcher;
    private WishlistFixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wishlistService = $this->getContainer()->get(WishlistService::class);
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');

        // Initialize fixtures
        $this->fixtures = new WishlistFixtures(
            $this->getContainer()->get(WishlistFactory::class),
            $this->getContainer()->get(WishlistItemFactory::class)
        );
    }

    public function testWishlistCreatedEventDispatched(): void
    {
        // Arrange
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(WishlistCreatedEvent::class))
            ->willReturnCallback(function ($event) {
                $this->assertInstanceOf(WishlistCreatedEvent::class, $event);
                $this->assertInstanceOf(WishlistEntity::class, $event->getWishlist());
                $this->assertEquals('Test Event Wishlist', $event->getWishlist()->getName());

                return $event;
            });

        // Replace the real event dispatcher with our mock
        $wishlistServiceWithMock = new WishlistService(
            $this->getContainer()->get('wishlist.repository'),
            $this->getContainer()->get('wishlist_item.repository'),
            $eventDispatcherMock
        );

        // Act
        $wishlistId = $wishlistServiceWithMock->createWishlist([
            'name' => 'Test Event Wishlist',
            'customerId' => Uuid::randomHex(),
        ], Context::createDefaultContext());

        // Assert
        $this->assertNotNull($wishlistId, 'Wishlist ID should not be null');
    }

    public function testWishlistItemAddedEventDispatched(): void
    {
        // Arrange
        $context = Context::createDefaultContext();
        $wishlistId = $this->createWishlist([], $context);
        $productId = Uuid::randomHex();

        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(WishlistItemAddedEvent::class))
            ->willReturnCallback(function ($event) use ($wishlistId, $productId) {
                $this->assertInstanceOf(WishlistItemAddedEvent::class, $event);
                $this->assertEquals($wishlistId, $event->getWishlistId());
                $this->assertEquals($productId, $event->getProductId());

                return $event;
            });

        // Replace the real event dispatcher with our mock
        $wishlistServiceWithMock = new WishlistService(
            $this->getContainer()->get('wishlist.repository'),
            $this->getContainer()->get('wishlist_item.repository'),
            $eventDispatcherMock
        );

        // Act
        $wishlistServiceWithMock->addProductToWishlist($wishlistId, $productId, $context);

        // Assert - Verification happens in the mock callback
    }

    public function testWishlistDeletedEventDispatched(): void
    {
        // Arrange
        $context = Context::createDefaultContext();

        // Load fixtures to create test data
        $objectManager = $this->createMock(\Doctrine\Persistence\ObjectManager::class);
        $this->fixtures->load($objectManager);

        // Get a wishlist ID from the fixtures
        $wishlistId = $this->fixtures->getReference('private-wishlist');

        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(WishlistDeletedEvent::class))
            ->willReturnCallback(function ($event) use ($wishlistId) {
                $this->assertInstanceOf(WishlistDeletedEvent::class, $event);
                $this->assertEquals($wishlistId, $event->getWishlistId());

                return $event;
            });

        // Replace the real event dispatcher with our mock
        $wishlistServiceWithMock = new WishlistService(
            $this->getContainer()->get('wishlist.repository'),
            $this->getContainer()->get('wishlist_item.repository'),
            $eventDispatcherMock
        );

        // Act
        $wishlistServiceWithMock->deleteWishlist($wishlistId, null, $context);

        // Assert - Verification happens in the mock callback
    }
}
