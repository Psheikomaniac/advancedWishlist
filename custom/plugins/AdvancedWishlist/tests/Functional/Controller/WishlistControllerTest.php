<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Functional\Controller;

use AdvancedWishlist\Storefront\Controller\WishlistController;
use AdvancedWishlist\Tests\Factory\WishlistFactory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the WishlistController.
 *
 * These tests verify that the controller endpoints work correctly from a user perspective.
 */
class WishlistControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private WishlistController $controller;
    private WishlistFactory $wishlistFactory;
    private Context $context;

    protected function setUp(): void
    {
        $this->controller = $this->getContainer()->get(WishlistController::class);
        $this->wishlistFactory = new WishlistFactory($this->getContainer()->get('wishlist.repository'));
        $this->context = Context::createDefaultContext();
    }

    public function testCreateWishlist(): void
    {
        // Create a request with wishlist data
        $request = new Request([], [
            'name' => 'Test Wishlist',
            'customerId' => Uuid::randomHex(),
            'isPublic' => true,
            'description' => 'This is a test wishlist',
        ]);

        // Call the controller method
        $response = $this->controller->create($request, $this->createSalesChannelContext());

        // Assert that the response is successful
        self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // Assert that the response contains the wishlist data
        $content = json_decode($response->getContent(), true);
        self::assertArrayHasKey('success', $content);
        self::assertTrue($content['success']);
        self::assertArrayHasKey('wishlist', $content);
        self::assertEquals('Test Wishlist', $content['wishlist']['name']);
    }

    public function testGetWishlist(): void
    {
        // Create a test wishlist
        $customerId = Uuid::randomHex();
        $wishlistId = $this->wishlistFactory->createWishlist([
            'customerId' => $customerId,
            'name' => 'Test Wishlist',
            'isPublic' => true,
        ], $this->context);

        // Create a request to get the wishlist
        $request = new Request();

        // Call the controller method
        $response = $this->controller->detail($wishlistId, $request, $this->createSalesChannelContextWithCustomer($customerId));

        // Assert that the response is successful
        self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // Assert that the response contains the wishlist data
        $content = json_decode($response->getContent(), true);
        self::assertArrayHasKey('success', $content);
        self::assertTrue($content['success']);
        self::assertArrayHasKey('wishlist', $content);
        self::assertEquals('Test Wishlist', $content['wishlist']['name']);
    }

    public function testUpdateWishlist(): void
    {
        // Create a test wishlist
        $customerId = Uuid::randomHex();
        $wishlistId = $this->wishlistFactory->createWishlist([
            'customerId' => $customerId,
            'name' => 'Test Wishlist',
            'isPublic' => true,
        ], $this->context);

        // Create a request to update the wishlist
        $request = new Request([], [
            'name' => 'Updated Wishlist',
            'isPublic' => false,
        ]);

        // Call the controller method
        $response = $this->controller->update($wishlistId, $request, $this->createSalesChannelContextWithCustomer($customerId));

        // Assert that the response is successful
        self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // Assert that the response contains the updated wishlist data
        $content = json_decode($response->getContent(), true);
        self::assertArrayHasKey('success', $content);
        self::assertTrue($content['success']);

        // Get the updated wishlist to verify the changes
        $updatedWishlist = $this->wishlistFactory->getWishlist($wishlistId, $this->context);
        self::assertEquals('Updated Wishlist', $updatedWishlist->getName());
        self::assertFalse($updatedWishlist->isPublic());
    }

    public function testDeleteWishlist(): void
    {
        // Create a test wishlist
        $customerId = Uuid::randomHex();
        $wishlistId = $this->wishlistFactory->createWishlist([
            'customerId' => $customerId,
            'name' => 'Test Wishlist',
            'isPublic' => true,
        ], $this->context);

        // Create a request to delete the wishlist
        $request = new Request();

        // Call the controller method
        $response = $this->controller->delete($wishlistId, $request, $this->createSalesChannelContextWithCustomer($customerId));

        // Assert that the response is successful
        self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // Assert that the response indicates success
        $content = json_decode($response->getContent(), true);
        self::assertArrayHasKey('success', $content);
        self::assertTrue($content['success']);

        // Verify that the wishlist has been deleted
        $deletedWishlist = $this->wishlistFactory->getWishlist($wishlistId, $this->context);
        self::assertNull($deletedWishlist);
    }

    public function testAddItemToWishlist(): void
    {
        // Create a test wishlist
        $customerId = Uuid::randomHex();
        $wishlistId = $this->wishlistFactory->createWishlist([
            'customerId' => $customerId,
            'name' => 'Test Wishlist',
            'isPublic' => true,
        ], $this->context);

        // Create a request to add an item to the wishlist
        $productId = Uuid::randomHex();
        $request = new Request([], [
            'productId' => $productId,
        ]);

        // Call the controller method
        $response = $this->controller->addItem($wishlistId, $request, $this->createSalesChannelContextWithCustomer($customerId));

        // Assert that the response is successful
        self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // Assert that the response indicates success
        $content = json_decode($response->getContent(), true);
        self::assertArrayHasKey('success', $content);
        self::assertTrue($content['success']);

        // Verify that the item has been added to the wishlist
        $wishlist = $this->wishlistFactory->getWishlist($wishlistId, $this->context);
        self::assertCount(1, $wishlist->getItems());
        self::assertEquals($productId, $wishlist->getItems()->first()->getProductId());
    }

    public function testRemoveItemFromWishlist(): void
    {
        // Create a test wishlist
        $customerId = Uuid::randomHex();
        $wishlistId = $this->wishlistFactory->createWishlist([
            'customerId' => $customerId,
            'name' => 'Test Wishlist',
            'isPublic' => true,
        ], $this->context);

        // Add an item to the wishlist
        $productId = Uuid::randomHex();
        $itemId = $this->getContainer()->get('wishlist_item.repository')->create([
            [
                'id' => Uuid::randomHex(),
                'wishlistId' => $wishlistId,
                'productId' => $productId,
                'productVersionId' => Uuid::randomHex(),
            ],
        ], $this->context)->getPrimaryKeys()['wishlist_item'][0];

        // Create a request to remove the item from the wishlist
        $request = new Request();

        // Call the controller method
        $response = $this->controller->removeItem($wishlistId, $itemId, $request, $this->createSalesChannelContextWithCustomer($customerId));

        // Assert that the response is successful
        self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // Assert that the response indicates success
        $content = json_decode($response->getContent(), true);
        self::assertArrayHasKey('success', $content);
        self::assertTrue($content['success']);

        // Verify that the item has been removed from the wishlist
        $wishlist = $this->wishlistFactory->getWishlist($wishlistId, $this->context);
        self::assertCount(0, $wishlist->getItems());
    }

    /**
     * Create a sales channel context with a customer.
     */
    private function createSalesChannelContextWithCustomer(string $customerId): SalesChannelContext
    {
        $salesChannelContext = $this->createSalesChannelContext();

        // Set the customer ID in the context
        $customer = new CustomerEntity();
        $customer->setId($customerId);
        $customer->setGroupId(Uuid::randomHex());

        $salesChannelContext->getContext()->assign(['customer' => $customer]);

        return $salesChannelContext;
    }

    /**
     * Create a sales channel context.
     */
    private function createSalesChannelContext(): SalesChannelContext
    {
        return $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            Uuid::randomHex()
        );
    }
}
