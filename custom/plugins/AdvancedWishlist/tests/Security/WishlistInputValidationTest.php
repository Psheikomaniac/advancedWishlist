<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Security;

use AdvancedWishlist\Core\Service\WishlistService;
use AdvancedWishlist\Storefront\Controller\WishlistController;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class WishlistInputValidationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private WishlistController $wishlistController;
    private WishlistService $wishlistService;
    private CsrfTokenManagerInterface $csrfTokenManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wishlistService = $this->createMock(WishlistService::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->wishlistController = new WishlistController($this->wishlistService, $this->csrfTokenManager);

        // Always make CSRF token valid for these tests
        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(true);
    }

    public function testCreateValidatesEmptyName(): void
    {
        $request = new Request([], [
            '_csrf_token' => 'valid_token',
            'name' => '',
            'type' => 'private',
        ]);

        $salesChannelContext = $this->createSalesChannelContextWithCustomer(Uuid::randomHex());

        $response = $this->wishlistController->create($request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__INVALID_NAME', $response->getContent());
    }

    public function testCreateValidatesNameLength(): void
    {
        $request = new Request([], [
            '_csrf_token' => 'valid_token',
            'name' => str_repeat('a', 256), // 256 characters, exceeding the 255 limit
            'type' => 'private',
        ]);

        $salesChannelContext = $this->createSalesChannelContextWithCustomer(Uuid::randomHex());

        $response = $this->wishlistController->create($request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__NAME_TOO_LONG', $response->getContent());
    }

    public function testCreateValidatesWishlistType(): void
    {
        $request = new Request([], [
            '_csrf_token' => 'valid_token',
            'name' => 'Test Wishlist',
            'type' => 'invalid_type',
        ]);

        $salesChannelContext = $this->createSalesChannelContextWithCustomer(Uuid::randomHex());

        $response = $this->wishlistController->create($request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__INVALID_TYPE', $response->getContent());
    }

    public function testCreateSanitizesNameAndDescription(): void
    {
        $customerId = Uuid::randomHex();
        $unsafeInput = '<script>alert("XSS")</script>Test Wishlist';
        $sanitizedOutput = '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;Test Wishlist';

        $request = new Request([], [
            '_csrf_token' => 'valid_token',
            'name' => $unsafeInput,
            'description' => $unsafeInput,
            'type' => 'private',
        ]);

        $salesChannelContext = $this->createSalesChannelContextWithCustomer($customerId);

        // Mock the createWishlist method to verify sanitized input
        $this->wishlistService->expects($this->once())
            ->method('createWishlist')
            ->with(
                $this->callback(function ($createRequest) use ($sanitizedOutput, $customerId) {
                    return $createRequest->getName() === $sanitizedOutput
                           && $createRequest->getDescription() === $sanitizedOutput
                           && $createRequest->getCustomerId() === $customerId;
                }),
                $this->anything()
            )
            ->willReturn(['id' => Uuid::randomHex()]);

        $this->wishlistController->create($request, $salesChannelContext);
    }

    public function testCreateRemovesDangerousFields(): void
    {
        $customerId = Uuid::randomHex();
        $hackerCustomerId = Uuid::randomHex();

        $request = new Request([], [
            '_csrf_token' => 'valid_token',
            'name' => 'Test Wishlist',
            'type' => 'private',
            'customerId' => $hackerCustomerId, // Attempt to set a different customer ID
            'id' => Uuid::randomHex(), // Attempt to specify the ID
            'salesChannelId' => Uuid::randomHex(), // Attempt to specify the sales channel
        ]);

        $salesChannelContext = $this->createSalesChannelContextWithCustomer($customerId);

        // Mock the createWishlist method to verify sanitized input
        $this->wishlistService->expects($this->once())
            ->method('createWishlist')
            ->with(
                $this->callback(function ($createRequest) use ($customerId) {
                    // Verify that the customer ID is set to the authenticated user's ID, not the one from the request
                    return $createRequest->getCustomerId() === $customerId;
                }),
                $this->anything()
            )
            ->willReturn(['id' => Uuid::randomHex()]);

        $this->wishlistController->create($request, $salesChannelContext);
    }

    public function testUpdateSanitizesInput(): void
    {
        $wishlistId = Uuid::randomHex();
        $customerId = Uuid::randomHex();
        $unsafeInput = '<script>alert("XSS")</script>Updated Wishlist';
        $sanitizedOutput = '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;Updated Wishlist';

        $request = new Request([], [
            '_csrf_token' => 'valid_token',
            'name' => $unsafeInput,
            'description' => $unsafeInput,
            'type' => 'private',
            'customerId' => Uuid::randomHex(), // Attempt to change the owner
        ]);

        $salesChannelContext = $this->createSalesChannelContextWithCustomer($customerId);

        // Mock loadWishlist to return a wishlist owned by the current user
        $wishlist = $this->createMock(\AdvancedWishlist\Core\Content\Wishlist\WishlistEntity::class);
        $wishlist->method('getCustomerId')->willReturn($customerId);
        $wishlist->method('getId')->willReturn($wishlistId);

        $this->wishlistService->method('loadWishlist')
            ->willReturn($wishlist);

        // Mock the updateWishlist method to verify sanitized input
        $this->wishlistService->expects($this->once())
            ->method('updateWishlist')
            ->with(
                $this->callback(function ($updateRequest) use ($sanitizedOutput, $wishlistId) {
                    // Verify that the name and description are sanitized and customerId is not included
                    return $updateRequest->getName() === $sanitizedOutput
                           && $updateRequest->getDescription() === $sanitizedOutput
                           && $updateRequest->getWishlistId() === $wishlistId
                           && !property_exists($updateRequest, 'customerId');
                }),
                $this->anything()
            )
            ->willReturn(['id' => $wishlistId]);

        $this->wishlistController->update($wishlistId, $request, $salesChannelContext);
    }

    private function createSalesChannelContextWithCustomer(string $customerId): SalesChannelContext
    {
        $context = Context::createDefaultContext();
        $customer = new UserEntity();
        $customer->setId($customerId);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        return $salesChannelContext;
    }
}
