<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Security;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\Exception\WishlistException;
use AdvancedWishlist\Core\Service\WishlistValidator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\User\UserEntity;

class WishlistValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private WishlistValidator $wishlistValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wishlistValidator = new WishlistValidator();
    }

    public function testValidateCreateRequestWithValidData(): void
    {
        $request = new CreateWishlistRequest();
        $request->setName('Test Wishlist');
        $request->setType('private');
        $request->setCustomerId(Uuid::randomHex());

        // This should not throw an exception
        $this->wishlistValidator->validateCreateRequest($request, Context::createDefaultContext());

        // Assert that no exception was thrown
        $this->assertTrue(true);
    }

    public function testValidateCreateRequestWithEmptyName(): void
    {
        $request = new CreateWishlistRequest();
        $request->setName('');
        $request->setType('private');
        $request->setCustomerId(Uuid::randomHex());

        $this->expectException(WishlistException::class);
        $this->expectExceptionMessage('Wishlist name is required');

        $this->wishlistValidator->validateCreateRequest($request, Context::createDefaultContext());
    }

    public function testValidateCreateRequestWithNameTooLong(): void
    {
        $request = new CreateWishlistRequest();
        $request->setName(str_repeat('a', 256)); // 256 characters, exceeding the 255 limit
        $request->setType('private');
        $request->setCustomerId(Uuid::randomHex());

        $this->expectException(WishlistException::class);
        $this->expectExceptionMessage('Wishlist name cannot exceed 255 characters');

        $this->wishlistValidator->validateCreateRequest($request, Context::createDefaultContext());
    }

    public function testValidateCreateRequestWithInvalidType(): void
    {
        $request = new CreateWishlistRequest();
        $request->setName('Test Wishlist');
        $request->setType('invalid_type');
        $request->setCustomerId(Uuid::randomHex());

        $this->expectException(WishlistException::class);
        $this->expectExceptionMessage('Invalid wishlist type');

        $this->wishlistValidator->validateCreateRequest($request, Context::createDefaultContext());
    }

    public function testValidateCreateRequestWithEmptyCustomerId(): void
    {
        $request = new CreateWishlistRequest();
        $request->setName('Test Wishlist');
        $request->setType('private');
        $request->setCustomerId('');

        $this->expectException(WishlistException::class);
        $this->expectExceptionMessage('Customer ID is required');

        $this->wishlistValidator->validateCreateRequest($request, Context::createDefaultContext());
    }

    public function testValidateUpdateRequestWithValidData(): void
    {
        $request = new UpdateWishlistRequest();
        $request->setWishlistId(Uuid::randomHex());
        $request->setName('Updated Wishlist');
        $request->setType('public');

        $wishlist = new WishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setCustomerId(Uuid::randomHex());

        // This should not throw an exception
        $this->wishlistValidator->validateUpdateRequest($request, $wishlist, Context::createDefaultContext());

        // Assert that no exception was thrown
        $this->assertTrue(true);
    }

    public function testValidateUpdateRequestWithEmptyWishlistId(): void
    {
        $request = new UpdateWishlistRequest();
        $request->setWishlistId('');
        $request->setName('Updated Wishlist');

        $wishlist = new WishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setCustomerId(Uuid::randomHex());

        $this->expectException(WishlistException::class);
        $this->expectExceptionMessage('Wishlist ID is required');

        $this->wishlistValidator->validateUpdateRequest($request, $wishlist, Context::createDefaultContext());
    }

    public function testValidateUpdateRequestWithNameTooLong(): void
    {
        $request = new UpdateWishlistRequest();
        $request->setWishlistId(Uuid::randomHex());
        $request->setName(str_repeat('a', 256)); // 256 characters, exceeding the 255 limit

        $wishlist = new WishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setCustomerId(Uuid::randomHex());

        $this->expectException(WishlistException::class);
        $this->expectExceptionMessage('Wishlist name cannot exceed 255 characters');

        $this->wishlistValidator->validateUpdateRequest($request, $wishlist, Context::createDefaultContext());
    }

    public function testValidateUpdateRequestWithInvalidType(): void
    {
        $request = new UpdateWishlistRequest();
        $request->setWishlistId(Uuid::randomHex());
        $request->setType('invalid_type');

        $wishlist = new WishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setCustomerId(Uuid::randomHex());

        $this->expectException(WishlistException::class);
        $this->expectExceptionMessage('Invalid wishlist type');

        $this->wishlistValidator->validateUpdateRequest($request, $wishlist, Context::createDefaultContext());
    }

    public function testValidateOwnershipWithCorrectOwner(): void
    {
        $customerId = Uuid::randomHex();

        $wishlist = new WishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setCustomerId($customerId);

        $context = $this->createMock(Context::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getCustomer')->willReturn($this->createCustomerEntity($customerId));

        // This should not throw an exception
        $this->wishlistValidator->validateOwnership($wishlist, $salesChannelContext);

        // Assert that no exception was thrown
        $this->assertTrue(true);
    }

    public function testValidateOwnershipWithIncorrectOwner(): void
    {
        $wishlist = new WishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setCustomerId(Uuid::randomHex());

        $context = $this->createMock(Context::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getCustomer')->willReturn($this->createCustomerEntity(Uuid::randomHex()));

        $this->expectException(WishlistException::class);
        $this->expectExceptionMessage('You do not have permission to access this wishlist');

        $this->wishlistValidator->validateOwnership($wishlist, $salesChannelContext);
    }

    public function testCanViewWishlistAsOwner(): void
    {
        $customerId = Uuid::randomHex();

        $wishlist = new WishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setCustomerId($customerId);
        $wishlist->setType('private');

        $context = $this->createMock(Context::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getCustomer')->willReturn($this->createCustomerEntity($customerId));

        $result = $this->wishlistValidator->canViewWishlist($wishlist, $salesChannelContext);

        $this->assertTrue($result);
    }

    public function testCanViewWishlistAsPublic(): void
    {
        $wishlist = new WishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setCustomerId(Uuid::randomHex());
        $wishlist->setType('public');

        $context = $this->createMock(Context::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getCustomer')->willReturn($this->createCustomerEntity(Uuid::randomHex()));

        $result = $this->wishlistValidator->canViewWishlist($wishlist, $salesChannelContext);

        $this->assertTrue($result);
    }

    public function testCannotViewPrivateWishlistAsNonOwner(): void
    {
        $wishlist = new WishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setCustomerId(Uuid::randomHex());
        $wishlist->setType('private');

        $context = $this->createMock(Context::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getCustomer')->willReturn($this->createCustomerEntity(Uuid::randomHex()));

        $result = $this->wishlistValidator->canViewWishlist($wishlist, $salesChannelContext);

        $this->assertFalse($result);
    }

    private function createCustomerEntity(string $customerId): UserEntity
    {
        $customer = new UserEntity();
        $customer->setId($customerId);

        return $customer;
    }
}
