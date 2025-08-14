<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Unit\Core\DTO;

use AdvancedWishlist\Core\DTO\DTOValidator;
use AdvancedWishlist\Core\DTO\Request\AddItemRequest;
use AdvancedWishlist\Core\DTO\Request\BulkAddItemsRequest;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\NotificationPreferencesRequest;
use AdvancedWishlist\Core\DTO\Request\ShareWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DTOValidatorTest extends TestCase
{
    private DTOValidator $dtoValidator;
    private ValidatorInterface $symfonyValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->symfonyValidator = $this->createMock(ValidatorInterface::class);
        $this->dtoValidator = new DTOValidator($this->symfonyValidator);
    }

    /**
     * @test
     */
    public function it_validates_dto_without_errors(): void
    {
        $dto = new CreateWishlistRequest();
        $dto->setName('Test Wishlist');
        $dto->setType('private');
        $dto->setCustomerId('550e8400-e29b-41d4-a716-446655440000');

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function it_returns_symfony_validation_errors(): void
    {
        $dto = new CreateWishlistRequest();
        $dto->setName('');
        $dto->setCustomerId('invalid-uuid');

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation('Name cannot be blank', null, [], null, 'name', ''));
        $violations->add(new ConstraintViolation('Invalid UUID', null, [], null, 'customerId', 'invalid-uuid'));

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn($violations);

        $errors = $this->dtoValidator->validate($dto);

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('customerId', $errors);
        $this->assertEquals('Name cannot be blank', $errors['name']);
        $this->assertEquals('Invalid UUID', $errors['customerId']);
    }

    /**
     * @test
     */
    public function it_includes_custom_validation_errors(): void
    {
        $dto = new CreateWishlistRequest();
        $dto->setName('Test<script>');
        $dto->setType('private');
        $dto->setCustomerId('550e8400-e29b-41d4-a716-446655440000');

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertArrayHasKey('name', $errors);
        $this->assertEquals('Wishlist name contains invalid characters', $errors['name']);
    }

    /**
     * @test
     */
    public function it_throws_validation_exception_when_errors_exist(): void
    {
        $dto = new CreateWishlistRequest();
        $dto->setName('');
        $dto->setCustomerId('invalid-uuid');

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation('Name cannot be blank', null, [], null, 'name', ''));

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn($violations);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->dtoValidator->validateOrThrow($dto);
    }

    /**
     * @test
     */
    public function it_validates_add_item_request_successfully(): void
    {
        $dto = new AddItemRequest();
        $dto->setWishlistId('550e8400-e29b-41d4-a716-446655440000');
        $dto->setProductId('550e8400-e29b-41d4-a716-446655440001');
        $dto->setQuantity(5);
        $dto->setPriority(3);

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function it_validates_add_item_request_with_custom_errors(): void
    {
        $dto = new AddItemRequest();
        $dto->setWishlistId('550e8400-e29b-41d4-a716-446655440000');
        $dto->setProductId('550e8400-e29b-41d4-a716-446655440001');
        $dto->setQuantity(1001); // Exceeds maximum
        $dto->setPriority(6); // Invalid priority

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertArrayHasKey('quantity', $errors);
        $this->assertArrayHasKey('priority', $errors);
        $this->assertEquals('Quantity cannot exceed 1000 items', $errors['quantity']);
        $this->assertEquals('Priority must be between 1 and 5', $errors['priority']);
    }

    /**
     * @test
     */
    public function it_validates_bulk_add_items_request(): void
    {
        $dto = new BulkAddItemsRequest();
        $dto->setWishlistId('550e8400-e29b-41d4-a716-446655440000');
        $dto->setItems([
            [
                'productId' => '550e8400-e29b-41d4-a716-446655440001',
                'quantity' => 2
            ],
            [
                'productId' => '550e8400-e29b-41d4-a716-446655440002',
                'quantity' => 3
            ]
        ]);

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function it_validates_bulk_add_items_request_with_duplicate_products(): void
    {
        $dto = new BulkAddItemsRequest();
        $dto->setWishlistId('550e8400-e29b-41d4-a716-446655440000');
        $dto->setItems([
            [
                'productId' => '550e8400-e29b-41d4-a716-446655440001',
                'quantity' => 2
            ],
            [
                'productId' => '550e8400-e29b-41d4-a716-446655440001', // Duplicate
                'quantity' => 3
            ]
        ]);

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertArrayHasKey('items', $errors);
        $this->assertEquals('Duplicate product IDs found in the request', $errors['items']);
    }

    /**
     * @test
     */
    public function it_validates_share_wishlist_request_email(): void
    {
        $dto = new ShareWishlistRequest();
        $dto->setWishlistId('550e8400-e29b-41d4-a716-446655440000');
        $dto->setShareMethod('email');
        $dto->setRecipientEmail('test@example.com');
        $dto->setMessage('Check out my wishlist!');

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function it_validates_share_wishlist_request_email_without_recipient(): void
    {
        $dto = new ShareWishlistRequest();
        $dto->setWishlistId('550e8400-e29b-41d4-a716-446655440000');
        $dto->setShareMethod('email');
        // Missing recipient email

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertArrayHasKey('recipientEmail', $errors);
        $this->assertEquals('Recipient email is required for email sharing', $errors['recipientEmail']);
    }

    /**
     * @test
     */
    public function it_validates_share_wishlist_request_social(): void
    {
        $dto = new ShareWishlistRequest();
        $dto->setWishlistId('550e8400-e29b-41d4-a716-446655440000');
        $dto->setShareMethod('social');
        $dto->setPlatform('facebook');

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function it_validates_notification_preferences_request(): void
    {
        $dto = new NotificationPreferencesRequest();
        $dto->setCustomerId('550e8400-e29b-41d4-a716-446655440000');
        $dto->setPriceDropNotifications(true);
        $dto->setBackInStockNotifications(true);
        $dto->setNotificationFrequency('daily');
        $dto->setNotificationChannel('email');

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function it_validates_notification_preferences_with_invalid_combination(): void
    {
        $dto = new NotificationPreferencesRequest();
        $dto->setCustomerId('550e8400-e29b-41d4-a716-446655440000');
        $dto->setPriceDropNotifications(false);
        $dto->setBackInStockNotifications(false);
        $dto->setShareNotifications(false);
        $dto->setReminderNotifications(true);
        $dto->setNotificationFrequency('immediate'); // Invalid for reminder notifications

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertArrayHasKey('notificationFrequency', $errors);
        $this->assertEquals('Reminder notifications cannot use immediate frequency', $errors['notificationFrequency']);
    }

    /**
     * @test
     */
    public function it_validates_update_wishlist_request_with_no_changes(): void
    {
        $dto = new UpdateWishlistRequest();
        $dto->setWishlistId('550e8400-e29b-41d4-a716-446655440000');
        // No changes provided

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertArrayHasKey('general', $errors);
        $this->assertEquals('At least one field must be provided for update', $errors['general']);
    }

    /**
     * @test
     */
    public function it_validates_update_wishlist_request_successfully(): void
    {
        $dto = new UpdateWishlistRequest();
        $dto->setWishlistId('550e8400-e29b-41d4-a716-446655440000');
        $dto->setName('Updated Wishlist Name');
        $dto->setDescription('Updated description');

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $errors = $this->dtoValidator->validate($dto);

        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function validation_exception_contains_all_errors(): void
    {
        $dto = new CreateWishlistRequest();
        $dto->setName('Test<script>'); // Invalid characters
        $dto->setType('invalid'); // Invalid type
        $dto->setCustomerId('550e8400-e29b-41d4-a716-446655440000');

        $this->symfonyValidator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        try {
            $this->dtoValidator->validateOrThrow($dto);
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $errors = $e->getValidationErrors();
            
            $this->assertArrayHasKey('name', $errors);
            $this->assertArrayHasKey('type', $errors);
            $this->assertEquals('Wishlist name contains invalid characters', $errors['name']);
            $this->assertEquals('Invalid wishlist type. Must be one of: private, public, shared', $errors['type']);
        }
    }
}