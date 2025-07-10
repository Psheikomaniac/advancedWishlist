<?php declare(strict_types=1);

namespace AdvancedWishlist\Tests\Integration\Core\CQRS;

use AdvancedWishlist\Core\CQRS\Command\CommandBus;
use AdvancedWishlist\Core\CQRS\Command\CreateWishlistCommand;
use AdvancedWishlist\Core\CQRS\Query\GetWishlistQuery;
use AdvancedWishlist\Core\CQRS\Query\QueryBus;
use AdvancedWishlist\Core\Domain\ValueObject\WishlistType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the CQRS pattern
 * 
 * This test verifies that the Command and Query buses work correctly together
 * and that the CQRS pattern is properly integrated with the Shopware container.
 */
class CQRSIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CommandBus $commandBus;
    private QueryBus $queryBus;
    private Context $context;

    protected function setUp(): void
    {
        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->queryBus = $this->getContainer()->get(QueryBus::class);
        $this->context = Context::createDefaultContext();
    }

    public function testCreateAndGetWishlist(): void
    {
        // Create a wishlist using the CommandBus
        $customerId = '12345678901234567890123456789012';
        $command = new CreateWishlistCommand(
            name: 'Test Wishlist',
            customerId: $customerId,
            isPublic: true,
            description: 'This is a test wishlist',
            context: $this->context
        );

        $wishlistId = $this->commandBus->dispatch($command);
        
        // Verify that the wishlist ID is returned
        self::assertNotEmpty($wishlistId);
        
        // Retrieve the wishlist using the QueryBus
        $query = new GetWishlistQuery($wishlistId, $this->context);
        $wishlist = $this->queryBus->dispatch($query);
        
        // Verify that the wishlist is retrieved correctly
        self::assertNotNull($wishlist);
        self::assertEquals($wishlistId, $wishlist->getId());
        self::assertEquals('Test Wishlist', $wishlist->getName());
        self::assertEquals($customerId, $wishlist->getCustomerId());
        self::assertEquals('This is a test wishlist', $wishlist->getDescription());
        self::assertEquals(WishlistType::PUBLIC, $wishlist->getType());
    }

    public function testCreatePrivateWishlist(): void
    {
        // Create a private wishlist using the CommandBus
        $customerId = '12345678901234567890123456789012';
        $command = new CreateWishlistCommand(
            name: 'Private Wishlist',
            customerId: $customerId,
            isPublic: false,
            description: 'This is a private wishlist',
            context: $this->context
        );

        $wishlistId = $this->commandBus->dispatch($command);
        
        // Retrieve the wishlist using the QueryBus
        $query = new GetWishlistQuery($wishlistId, $this->context);
        $wishlist = $this->queryBus->dispatch($query);
        
        // Verify that the wishlist is private
        self::assertEquals(WishlistType::PRIVATE, $wishlist->getType());
    }

    public function testGetNonExistentWishlistThrowsException(): void
    {
        // Expect an exception when trying to get a non-existent wishlist
        $this->expectException(\AdvancedWishlist\Core\Exception\WishlistNotFoundException::class);
        
        // Try to retrieve a non-existent wishlist
        $query = new GetWishlistQuery('non-existent-id', $this->context);
        $this->queryBus->dispatch($query);
    }

    public function testCreateWishlistWithInvalidDataThrowsException(): void
    {
        // Create a command with invalid data (empty name)
        $command = new CreateWishlistCommand(
            name: '',
            customerId: '12345678901234567890123456789012',
            isPublic: true,
            description: 'This is a test wishlist',
            context: $this->context
        );

        // Expect an exception when trying to create a wishlist with invalid data
        $this->expectException(\InvalidArgumentException::class);
        
        // Try to create a wishlist with invalid data
        $this->commandBus->dispatch($command);
    }
}