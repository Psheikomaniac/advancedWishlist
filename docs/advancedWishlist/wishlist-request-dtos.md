# Request DTOs - Advanced Wishlist System

## Overview

Request DTOs (Data Transfer Objects) define the structure of incoming data and provide automatic validation. Each API endpoint has its own Request DTO for type safety and clear contracts.

## Base Request DTO

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\Constraints as Assert;

abstract class AbstractRequestDTO extends Struct
{
    /**
     * Create DTO from request data
     */
    public static function fromArray(array $data): self
    {
        $dto = new static();
        $dto->assign($data);
        return $dto;
    }
    
    /**
     * Validate the DTO
     */
    abstract public function validate(): array;
}
```

## Wishlist Management DTOs

### CreateWishlistRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateWishlistRequest extends AbstractRequestDTO
{
    #[Assert\NotBlank(message: 'wishlist.name.not_blank')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'wishlist.name.too_short',
        maxMessage: 'wishlist.name.too_long'
    )]
    private string $name;
    
    #[Assert\Type('string')]
    #[Assert\Length(max: 1000)]
    private ?string $description = null;
    
    #[Assert\Choice(choices: ['private', 'public', 'shared'])]
    private string $type = 'private';
    
    #[Assert\Type('bool')]
    private bool $isDefault = false;
    
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $customerId;
    
    #[Assert\Uuid]
    private ?string $salesChannelId = null;
    
    // Getters and Setters
    public function getName(): string
    {
        return $this->name;
    }
    
    public function setName(string $name): void
    {
        $this->name = $name;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function setType(string $type): void
    {
        $this->type = $type;
    }
    
    public function isDefault(): bool
    {
        return $this->isDefault;
    }
    
    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }
    
    public function getCustomerId(): string
    {
        return $this->customerId;
    }
    
    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }
    
    public function validate(): array
    {
        // Custom validation logic
        $errors = [];
        
        // Check if customer already has default wishlist
        if ($this->isDefault) {
            // This would be checked in service layer
            // Just example of custom validation
        }
        
        return $errors;
    }
}
```

### UpdateWishlistRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateWishlistRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;
    
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'wishlist.name.too_short',
        maxMessage: 'wishlist.name.too_long'
    )]
    private ?string $name = null;
    
    #[Assert\Type('string')]
    #[Assert\Length(max: 1000)]
    private ?string $description = null;
    
    #[Assert\Choice(choices: ['private', 'public', 'shared'])]
    private ?string $type = null;
    
    #[Assert\Type('bool')]
    private ?bool $isDefault = null;
    
    #[Assert\Type('array')]
    private array $customFields = [];
    
    // Getters for all fields...
    
    public function hasChanges(): bool
    {
        return $this->name !== null 
            || $this->description !== null
            || $this->type !== null
            || $this->isDefault !== null
            || !empty($this->customFields);
    }
    
    public function toArray(): array
    {
        $data = [];
        
        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        
        if ($this->type !== null) {
            $data['type'] = $this->type;
        }
        
        if ($this->isDefault !== null) {
            $data['isDefault'] = $this->isDefault;
        }
        
        if (!empty($this->customFields)) {
            $data['customFields'] = $this->customFields;
        }
        
        return $data;
    }
}
```

### DeleteWishlistRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class DeleteWishlistRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;
    
    #[Assert\Type('bool')]
    private bool $force = false;
    
    #[Assert\Uuid]
    private ?string $transferToWishlistId = null;
    
    // When deleting, optionally transfer items to another wishlist
    public function shouldTransferItems(): bool
    {
        return $this->transferToWishlistId !== null;
    }
}
```

## Wishlist Item DTOs

### AddItemRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class AddItemRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;
    
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $productId;
    
    #[Assert\Positive]
    private int $quantity = 1;
    
    #[Assert\Type('string')]
    #[Assert\Length(max: 500)]
    private ?string $note = null;
    
    #[Assert\Type('int')]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $priority = null;
    
    #[Assert\Type('float')]
    #[Assert\Positive]
    private ?float $priceAlertThreshold = null;
    
    #[Assert\Type('array')]
    private array $productOptions = [];
    
    // For configurable products
    #[Assert\Type('array')]
    private array $lineItemData = [];
    
    public function validate(): array
    {
        $errors = [];
        
        // Validate product options structure
        foreach ($this->productOptions as $optionId => $optionValue) {
            if (!is_string($optionId) || !is_string($optionValue)) {
                $errors[] = 'Invalid product option format';
            }
        }
        
        return $errors;
    }
}
```

### UpdateItemRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateItemRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;
    
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $itemId;
    
    #[Assert\Positive]
    private ?int $quantity = null;
    
    #[Assert\Type('string')]
    #[Assert\Length(max: 500)]
    private ?string $note = null;
    
    #[Assert\Type('int')]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $priority = null;
    
    #[Assert\Type('float')]
    #[Assert\Positive]
    private ?float $priceAlertThreshold = null;
    
    #[Assert\Type('bool')]
    private ?bool $priceAlertActive = null;
}
```

### MoveItemRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class MoveItemRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $sourceWishlistId;
    
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $targetWishlistId;
    
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $itemId;
    
    #[Assert\Type('bool')]
    private bool $copy = false; // false = move, true = copy
}
```

## Sharing DTOs

### ShareWishlistRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ShareWishlistRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;
    
    #[Assert\Choice(choices: ['link', 'email', 'social'])]
    private string $shareMethod = 'link';
    
    #[Assert\Type('array')]
    private array $shareSettings = [];
    
    // Email sharing specific
    #[Assert\Email]
    private ?string $recipientEmail = null;
    
    #[Assert\Type('string')]
    #[Assert\Length(max: 1000)]
    private ?string $message = null;
    
    // Link sharing specific
    #[Assert\DateTime]
    private ?\DateTimeInterface $expiresAt = null;
    
    #[Assert\Type('string')]
    #[Assert\Length(min: 4, max: 50)]
    private ?string $password = null;
    
    #[Assert\Type('bool')]
    private bool $allowGuestPurchase = false;
    
    // Social sharing specific
    #[Assert\Choice(choices: ['facebook', 'twitter', 'whatsapp', 'pinterest'])]
    private ?string $platform = null;
    
    public function getShareSettings(): ShareSettings
    {
        return new ShareSettings([
            'expiresAt' => $this->expiresAt,
            'password' => $this->password,
            'allowGuestPurchase' => $this->allowGuestPurchase,
            'readOnly' => $this->shareSettings['readOnly'] ?? true,
            'hideQuantity' => $this->shareSettings['hideQuantity'] ?? false,
            'hidePrices' => $this->shareSettings['hidePrices'] ?? false,
        ]);
    }
}
```

### UpdateShareRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateShareRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $shareId;
    
    #[Assert\DateTime]
    private ?\DateTimeInterface $expiresAt = null;
    
    #[Assert\Type('string')]
    private ?string $password = null;
    
    #[Assert\Type('bool')]
    private ?bool $active = null;
    
    #[Assert\Type('array')]
    private array $settings = [];
}
```

## Analytics DTOs

### AnalyticsQueryRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class AnalyticsQueryRequest extends AbstractRequestDTO
{
    #[Assert\Choice(choices: [
        'top_products',
        'conversion_rate',
        'share_statistics',
        'user_activity',
        'abandoned_wishlists'
    ])]
    private string $metric;
    
    #[Assert\DateTime]
    #[Assert\NotBlank]
    private \DateTimeInterface $startDate;
    
    #[Assert\DateTime]
    #[Assert\NotBlank]
    private \DateTimeInterface $endDate;
    
    #[Assert\Choice(choices: ['hour', 'day', 'week', 'month'])]
    private string $groupBy = 'day';
    
    #[Assert\Type('array')]
    private array $filters = [];
    
    #[Assert\Type('int')]
    #[Assert\Range(min: 1, max: 1000)]
    private int $limit = 100;
    
    #[Assert\Type('int')]
    #[Assert\Range(min: 0)]
    private int $offset = 0;
    
    public function validate(): array
    {
        $errors = [];
        
        if ($this->startDate > $this->endDate) {
            $errors[] = 'Start date must be before end date';
        }
        
        $maxRange = new \DateInterval('P1Y');
        if ($this->startDate->diff($this->endDate) > $maxRange) {
            $errors[] = 'Date range cannot exceed 1 year';
        }
        
        return $errors;
    }
}
```

## Bulk Operation DTOs

### BulkAddItemsRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class BulkAddItemsRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;
    
    #[Assert\Type('array')]
    #[Assert\Count(min: 1, max: 100)]
    #[Assert\All([
        new Assert\Collection([
            'productId' => [
                new Assert\Uuid(),
                new Assert\NotBlank()
            ],
            'quantity' => [
                new Assert\Type('int'),
                new Assert\Positive()
            ],
            'note' => [
                new Assert\Type('string'),
                new Assert\Length(max: 500)
            ]
        ])
    ])]
    private array $items;
    
    #[Assert\Type('bool')]
    private bool $skipDuplicates = true;
    
    #[Assert\Type('bool')]
    private bool $mergeQuantities = false;
}
```

### BulkDeleteItemsRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class BulkDeleteItemsRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;
    
    #[Assert\Type('array')]
    #[Assert\Count(min: 1, max: 100)]
    #[Assert\All([
        new Assert\Uuid()
    ])]
    private array $itemIds;
}
```

## Guest Wishlist DTOs

### CreateGuestWishlistRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateGuestWishlistRequest extends AbstractRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private string $sessionId;
    
    #[Assert\Email]
    private ?string $guestEmail = null;
    
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    private array $items = [];
    
    #[Assert\Type('int')]
    private int $ttl = 2592000; // 30 days in seconds
}
```

### MergeGuestWishlistRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class MergeGuestWishlistRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $guestWishlistId;
    
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $customerWishlistId;
    
    #[Assert\Choice(choices: ['merge', 'replace', 'skip'])]
    private string $conflictResolution = 'merge';
}
```

## Notification DTOs

### NotificationPreferencesRequest

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class NotificationPreferencesRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $customerId;
    
    #[Assert\Type('bool')]
    private bool $priceDropNotifications = true;
    
    #[Assert\Type('bool')]
    private bool $backInStockNotifications = true;
    
    #[Assert\Type('bool')]
    private bool $shareNotifications = true;
    
    #[Assert\Type('bool')]
    private bool $reminderNotifications = false;
    
    #[Assert\Choice(choices: ['immediate', 'daily', 'weekly'])]
    private string $notificationFrequency = 'immediate';
    
    #[Assert\Choice(choices: ['email', 'push', 'both'])]
    private string $notificationChannel = 'email';
}
```

## Validation Helper

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO;

use Symfony\Component\Validator\Validator\ValidatorInterface;

class DTOValidator
{
    public function __construct(
        private ValidatorInterface $validator
    ) {}
    
    public function validate(AbstractRequestDTO $dto): array
    {
        $violations = $this->validator->validate($dto);
        
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }
        
        // Add custom validation errors
        $customErrors = $dto->validate();
        
        return array_merge($errors, $customErrors);
    }
    
    public function validateOrThrow(AbstractRequestDTO $dto): void
    {
        $errors = $this->validate($dto);
        
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
```

## Usage Example in Controller

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Api\Controller;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class WishlistController
{
    public function create(Request $request, Context $context): JsonResponse
    {
        // Create DTO from request
        $createRequest = CreateWishlistRequest::fromArray(
            $request->request->all()
        );
        
        // Add context data
        $createRequest->setCustomerId($context->getCustomer()->getId());
        $createRequest->setSalesChannelId($context->getSalesChannelId());
        
        // Validate
        $this->dtoValidator->validateOrThrow($createRequest);
        
        // Process
        $response = $this->wishlistService->createWishlist($createRequest, $context);
        
        return new JsonResponse($response);
    }
}
```