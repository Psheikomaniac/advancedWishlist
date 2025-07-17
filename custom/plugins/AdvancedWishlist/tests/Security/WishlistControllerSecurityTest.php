<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Security;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
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

class WishlistControllerSecurityTest extends TestCase
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
    }

    public function testListReturnsUnauthorizedForGuestUser(): void
    {
        $request = new Request();
        $salesChannelContext = $this->createSalesChannelContextWithoutCustomer();

        $response = $this->wishlistController->list($request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__UNAUTHORIZED', $response->getContent());
    }

    public function testDetailReturnsUnauthorizedForGuestUser(): void
    {
        $request = new Request();
        $salesChannelContext = $this->createSalesChannelContextWithoutCustomer();

        $response = $this->wishlistController->detail(Uuid::randomHex(), $request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__UNAUTHORIZED', $response->getContent());
    }

    public function testDetailReturnsForbiddenForUnauthorizedUser(): void
    {
        $wishlistId = Uuid::randomHex();
        $ownerId = Uuid::randomHex();
        $currentUserId = Uuid::randomHex();

        $wishlist = new WishlistEntity();
        $wishlist->setId($wishlistId);
        $wishlist->setCustomerId($ownerId);
        $wishlist->setType('private');

        $this->wishlistService->method('loadWishlist')
            ->willReturn($wishlist);

        $request = new Request();
        $salesChannelContext = $this->createSalesChannelContextWithCustomer($currentUserId);

        $response = $this->wishlistController->detail($wishlistId, $request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__ACCESS_DENIED', $response->getContent());
    }

    public function testDetailAllowsAccessToPublicWishlist(): void
    {
        $wishlistId = Uuid::randomHex();
        $ownerId = Uuid::randomHex();
        $currentUserId = Uuid::randomHex();

        $wishlist = new WishlistEntity();
        $wishlist->setId($wishlistId);
        $wishlist->setCustomerId($ownerId);
        $wishlist->setType('public');

        $this->wishlistService->method('loadWishlist')
            ->willReturn($wishlist);

        $request = new Request();
        $salesChannelContext = $this->createSalesChannelContextWithCustomer($currentUserId);

        $response = $this->wishlistController->detail($wishlistId, $request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCreateReturnsUnauthorizedForGuestUser(): void
    {
        $request = new Request();
        $salesChannelContext = $this->createSalesChannelContextWithoutCustomer();

        $response = $this->wishlistController->create($request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__UNAUTHORIZED', $response->getContent());
    }

    public function testCreateRequiresCsrfToken(): void
    {
        $request = new Request([], ['name' => 'Test Wishlist']);
        $salesChannelContext = $this->createSalesChannelContextWithCustomer(Uuid::randomHex());

        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(false);

        $response = $this->wishlistController->create($request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__INVALID_CSRF_TOKEN', $response->getContent());
    }

    public function testUpdateReturnsUnauthorizedForGuestUser(): void
    {
        $request = new Request();
        $salesChannelContext = $this->createSalesChannelContextWithoutCustomer();

        $response = $this->wishlistController->update(Uuid::randomHex(), $request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__UNAUTHORIZED', $response->getContent());
    }

    public function testUpdateRequiresCsrfToken(): void
    {
        $wishlistId = Uuid::randomHex();
        $request = new Request([], ['name' => 'Updated Wishlist']);
        $salesChannelContext = $this->createSalesChannelContextWithCustomer(Uuid::randomHex());

        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(false);

        $response = $this->wishlistController->update($wishlistId, $request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__INVALID_CSRF_TOKEN', $response->getContent());
    }

    public function testUpdateReturnsForbiddenForUnauthorizedUser(): void
    {
        $wishlistId = Uuid::randomHex();
        $ownerId = Uuid::randomHex();
        $currentUserId = Uuid::randomHex();

        $wishlist = new WishlistEntity();
        $wishlist->setId($wishlistId);
        $wishlist->setCustomerId($ownerId);

        $this->wishlistService->method('loadWishlist')
            ->willReturn($wishlist);

        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(true);

        $request = new Request([], ['name' => 'Updated Wishlist']);
        $salesChannelContext = $this->createSalesChannelContextWithCustomer($currentUserId);

        $response = $this->wishlistController->update($wishlistId, $request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__ACCESS_DENIED', $response->getContent());
    }

    public function testDeleteReturnsUnauthorizedForGuestUser(): void
    {
        $request = new Request();
        $salesChannelContext = $this->createSalesChannelContextWithoutCustomer();

        $response = $this->wishlistController->delete(Uuid::randomHex(), $request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__UNAUTHORIZED', $response->getContent());
    }

    public function testDeleteRequiresCsrfToken(): void
    {
        $wishlistId = Uuid::randomHex();
        $request = new Request();
        $salesChannelContext = $this->createSalesChannelContextWithCustomer(Uuid::randomHex());

        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(false);

        $response = $this->wishlistController->delete($wishlistId, $request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__INVALID_CSRF_TOKEN', $response->getContent());
    }

    public function testDeleteReturnsForbiddenForUnauthorizedUser(): void
    {
        $wishlistId = Uuid::randomHex();
        $ownerId = Uuid::randomHex();
        $currentUserId = Uuid::randomHex();

        $wishlist = new WishlistEntity();
        $wishlist->setId($wishlistId);
        $wishlist->setCustomerId($ownerId);

        $this->wishlistService->method('loadWishlist')
            ->willReturn($wishlist);

        $this->csrfTokenManager->method('isTokenValid')
            ->willReturn(true);

        $request = new Request();
        $salesChannelContext = $this->createSalesChannelContextWithCustomer($currentUserId);

        $response = $this->wishlistController->delete($wishlistId, $request, $salesChannelContext);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('WISHLIST__ACCESS_DENIED', $response->getContent());
    }

    private function createSalesChannelContextWithoutCustomer(): SalesChannelContext
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getCustomer')->willReturn(null);

        return $salesChannelContext;
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
