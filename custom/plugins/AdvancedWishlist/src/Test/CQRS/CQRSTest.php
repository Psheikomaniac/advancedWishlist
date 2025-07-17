<?php

declare(strict_types=1);

namespace AdvancedWishlist\Test\CQRS;

use AdvancedWishlist\Core\CQRS\Command\CommandBus;
use AdvancedWishlist\Core\CQRS\Command\CreateWishlistCommand;
use AdvancedWishlist\Core\CQRS\Query\GetWishlistQuery;
use AdvancedWishlist\Core\CQRS\Query\QueryBus;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * Test for the CQRS pattern implementation.
 *
 * This test verifies that the Command and Query buses work correctly
 * and that the CQRS pattern is properly implemented.
 */
class CQRSTest extends TestCase
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
    }
}
