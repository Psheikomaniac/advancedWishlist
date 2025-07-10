# AdvancedWishlist Public API Reference

This document provides a comprehensive reference for all public APIs, service interfaces, events, and hooks available in the AdvancedWishlist plugin. This reference is intended for developers who want to integrate with or extend the plugin's functionality.

## Service Interfaces

The AdvancedWishlist plugin provides several service interfaces that can be used to interact with the plugin's functionality.

### WishlistCrudService

The `WishlistCrudService` is the main service for managing wishlists.

```php
namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
```

#### Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `createWishlist` | Creates a new wishlist | `CreateWishlistRequest $request, Context $context` | `array` |
| `updateWishlist` | Updates an existing wishlist | `UpdateWishlistRequest $request, Context $context` | `array` |
| `deleteWishlist` | Deletes a wishlist | `string $id, ?string $transferToWishlistId, Context $context` | `void` |
| `loadWishlist` | Loads a wishlist by ID | `string $id, Context $context` | `array` |
| `searchWishlists` | Searches for wishlists | `Criteria $criteria, Context $context` | `array` |
| `addItemToWishlist` | Adds an item to a wishlist | `string $wishlistId, string $productId, int $quantity, Context $context` | `array` |
| `removeItemFromWishlist` | Removes an item from a wishlist | `string $wishlistId, string $itemId, Context $context` | `void` |
| `updateItemQuantity` | Updates the quantity of an item | `string $wishlistId, string $itemId, int $quantity, Context $context` | `array` |

### ShareService

The `ShareService` handles sharing wishlists with other users.

```php
namespace AdvancedWishlist\Service;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareEntity;
use Shopware\Core\Framework\Context;
```

#### Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `createShare` | Creates a new share for a wishlist | `string $wishlistId, Context $context` | `WishlistShareEntity` |
| `getShareByToken` | Gets a share by its token | `string $token, Context $context` | `?WishlistShareEntity` |
| `updateShare` | Updates a share | `string $shareId, array $data, Context $context` | `void` |
| `deleteShare` | Deletes a share | `string $shareId, Context $context` | `void` |

### EncryptionService

The `EncryptionService` provides encryption and token generation functionality.

```php
namespace AdvancedWishlist\Service;

use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
```

#### Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `encrypt` | Encrypts data | `string $data` | `string` |
| `decrypt` | Decrypts data | `string $encryptedData` | `string` |
| `generateToken` | Generates a random token | none | `string` |
| `generateEncryptionKey` (static) | Generates a new encryption key | none | `string` |

### AnalyticsService

The `AnalyticsService` provides analytics data for wishlists.

```php
namespace AdvancedWishlist\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
```

#### Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `getAnalyticsSummary` | Gets a summary of analytics data | `Context $context` | `array` |
| `getWishlistAnalytics` | Gets analytics for a specific wishlist | `string $wishlistId, Context $context` | `array` |
| `getShareAnalytics` | Gets analytics for shares | `Criteria $criteria, Context $context` | `array` |
| `trackWishlistView` | Tracks a view of a wishlist | `string $wishlistId, ?string $customerId, Context $context` | `void` |
| `trackShareView` | Tracks a view of a shared wishlist | `string $shareId, Context $context` | `void` |
| `trackConversion` | Tracks a conversion (purchase from wishlist) | `string $wishlistId, string $productId, Context $context` | `void` |

### GuestWishlistService

The `GuestWishlistService` handles wishlists for guest users.

```php
namespace AdvancedWishlist\Core\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
```

#### Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `getGuestWishlist` | Gets the wishlist for a guest | `string $guestId, Context $context` | `array` |
| `createGuestWishlist` | Creates a wishlist for a guest | `string $guestId, Context $context` | `array` |
| `addItemToGuestWishlist` | Adds an item to a guest wishlist | `string $guestId, string $productId, int $quantity, Context $context` | `array` |
| `removeItemFromGuestWishlist` | Removes an item from a guest wishlist | `string $guestId, string $itemId, Context $context` | `void` |
| `mergeGuestWishlistWithCustomer` | Merges a guest wishlist with a customer wishlist | `string $guestId, string $customerId, Context $context` | `array` |

### OAuth2Service

The `OAuth2Service` handles OAuth2 authentication.

```php
namespace AdvancedWishlist\Core\OAuth\Service;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
```

#### Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `getAuthorizationServer` | Gets the OAuth2 authorization server | none | `AuthorizationServer` |
| `getResourceServer` | Gets the OAuth2 resource server | none | `ResourceServer` |

### SecurityMonitoringService

The `SecurityMonitoringService` provides security monitoring functionality.

```php
namespace AdvancedWishlist\Core\Security;
```

#### Methods

| Method | Description | Parameters | Return Type |
|--------|-------------|------------|-------------|
| `logSecurityEvent` | Logs a security event | `string $event, array $context = []` | `void` |
| `monitorRequest` | Monitors a request for suspicious patterns | none | `void` |
| `logFailedAuthentication` | Logs a failed authentication attempt | `string $username` | `void` |
| `logUnauthorizedAccess` | Logs an unauthorized access attempt | `string $resource, string $action` | `void` |
| `logSuspiciousApiRequest` | Logs a suspicious API request | `string $endpoint, array $params` | `void` |

## Events

The AdvancedWishlist plugin dispatches several events that you can subscribe to for extending functionality.

### Wishlist Events

```php
namespace AdvancedWishlist\Core\Event;
```

#### WishlistCreatedEvent

Dispatched when a new wishlist is created.

```php
class WishlistCreatedEvent extends WishlistEvent
{
    public function __construct(
        private array $wishlist,
        private Context $context
    ) {
        parent::__construct($context);
    }

    public function getWishlist(): array
    {
        return $this->wishlist;
    }
}
```

#### WishlistUpdatedEvent

Dispatched when a wishlist is updated.

```php
class WishlistUpdatedEvent extends WishlistEvent
{
    public function __construct(
        private array $wishlist,
        private array $changes,
        private Context $context
    ) {
        parent::__construct($context);
    }

    public function getWishlist(): array
    {
        return $this->wishlist;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }
}
```

#### WishlistDeletedEvent

Dispatched when a wishlist is deleted.

```php
class WishlistDeletedEvent extends WishlistEvent
{
    public function __construct(
        private string $wishlistId,
        private ?string $transferToWishlistId,
        private Context $context
    ) {
        parent::__construct($context);
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getTransferToWishlistId(): ?string
    {
        return $this->transferToWishlistId;
    }
}
```

### Wishlist Item Events

#### WishlistItemAddedEvent

Dispatched when an item is added to a wishlist.

```php
class WishlistItemAddedEvent extends WishlistEvent
{
    public function __construct(
        private string $wishlistId,
        private array $item,
        private Context $context
    ) {
        parent::__construct($context);
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getItem(): array
    {
        return $this->item;
    }
}
```

#### WishlistItemRemovedEvent

Dispatched when an item is removed from a wishlist.

```php
class WishlistItemRemovedEvent extends WishlistEvent
{
    public function __construct(
        private string $wishlistId,
        private string $itemId,
        private Context $context
    ) {
        parent::__construct($context);
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }
}
```

#### WishlistItemQuantityUpdatedEvent

Dispatched when the quantity of an item in a wishlist is updated.

```php
class WishlistItemQuantityUpdatedEvent extends WishlistEvent
{
    public function __construct(
        private string $wishlistId,
        private string $itemId,
        private int $quantity,
        private Context $context
    ) {
        parent::__construct($context);
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
```

### Share Events

#### WishlistSharedEvent

Dispatched when a wishlist is shared.

```php
class WishlistSharedEvent extends WishlistEvent
{
    public function __construct(
        private array $wishlist,
        private string $shareId,
        private Context $context
    ) {
        parent::__construct($context);
    }

    public function getWishlist(): array
    {
        return $this->wishlist;
    }

    public function getShareId(): string
    {
        return $this->shareId;
    }
}
```

#### WishlistShareViewedEvent

Dispatched when a shared wishlist is viewed.

```php
class WishlistShareViewedEvent extends WishlistEvent
{
    public function __construct(
        private string $shareId,
        private Context $context
    ) {
        parent::__construct($context);
    }

    public function getShareId(): string
    {
        return $this->shareId;
    }
}
```

### Guest Wishlist Events

#### GuestWishlistCreatedEvent

Dispatched when a guest wishlist is created.

```php
class GuestWishlistCreatedEvent extends WishlistEvent
{
    public function __construct(
        private string $guestId,
        private array $wishlist,
        private Context $context
    ) {
        parent::__construct($context);
    }

    public function getGuestId(): string
    {
        return $this->guestId;
    }

    public function getWishlist(): array
    {
        return $this->wishlist;
    }
}
```

#### GuestWishlistMergedEvent

Dispatched when a guest wishlist is merged with a customer wishlist.

```php
class GuestWishlistMergedEvent extends WishlistEvent
{
    public function __construct(
        private string $guestId,
        private string $customerId,
        private array $mergedWishlist,
        private Context $context
    ) {
        parent::__construct($context);
    }

    public function getGuestId(): string
    {
        return $this->guestId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getMergedWishlist(): array
    {
        return $this->mergedWishlist;
    }
}
```

## Hooks

The AdvancedWishlist plugin provides several hooks that allow you to modify its behavior.

### Service Decoration

You can decorate any of the services to modify their behavior:

```php
// services.xml
<service id="YourPlugin\CustomWishlistValidator" decorates="AdvancedWishlist\Core\Service\WishlistValidator">
    <argument type="service" id="YourPlugin\CustomWishlistValidator.inner"/>
</service>
```

### Event Subscribers

You can subscribe to events to extend functionality:

```php
// Event Subscriber Example
class WishlistEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            WishlistCreatedEvent::class => 'onWishlistCreated',
            WishlistUpdatedEvent::class => 'onWishlistUpdated',
            WishlistDeletedEvent::class => 'onWishlistDeleted',
            WishlistItemAddedEvent::class => 'onItemAdded',
            WishlistItemRemovedEvent::class => 'onItemRemoved',
            WishlistSharedEvent::class => 'onWishlistShared'
        ];
    }
    
    public function onWishlistCreated(WishlistCreatedEvent $event): void
    {
        $wishlist = $event->getWishlist();
        // Custom logic here
    }
    
    // Other event handlers...
}
```

### Custom Entities

You can create custom entities that relate to wishlists:

```php
// Custom Entity Definition Example
class WishlistTagDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'wishlist_tag';
    
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }
    
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new FkField('wishlist_id', 'wishlistId', WishlistDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('wishlist', 'wishlist_id', WishlistDefinition::class, 'id', false)
        ]);
    }
}
```

## Data Transfer Objects (DTOs)

The AdvancedWishlist plugin uses DTOs for request and response data.

### Request DTOs

#### CreateWishlistRequest

```php
namespace AdvancedWishlist\Core\DTO\Request;

class CreateWishlistRequest extends AbstractRequestDTO
{
    protected ?string $name = null;
    protected ?string $customerId = null;
    protected ?string $type = 'private';
    
    // Getters and setters...
}
```

#### UpdateWishlistRequest

```php
namespace AdvancedWishlist\Core\DTO\Request;

class UpdateWishlistRequest extends AbstractRequestDTO
{
    protected ?string $wishlistId = null;
    protected ?string $name = null;
    protected ?string $type = null;
    
    // Getters and setters...
}
```

### Response DTOs

#### WishlistResponse

```php
namespace AdvancedWishlist\Core\DTO\Response;

class WishlistResponse extends AbstractResponseDTO
{
    protected string $id;
    protected string $name;
    protected string $customerId;
    protected string $type;
    protected array $items = [];
    protected ?\DateTimeInterface $createdAt = null;
    protected ?\DateTimeInterface $updatedAt = null;
    
    // Getters and setters...
}
```

## Constants and Enums

### WishlistTypes

```php
namespace AdvancedWishlist\Core\Content\Wishlist;

class WishlistTypes
{
    public const PRIVATE = 'private';
    public const PUBLIC = 'public';
    public const SHARED = 'shared';
}
```

### WishlistPermissions

```php
namespace AdvancedWishlist\Core\Content\Wishlist;

class WishlistPermissions
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const SHARE = 'share';
}
```

## Integration Examples

### Using the WishlistCrudService

```php
// Inject the service
public function __construct(
    private WishlistCrudService $wishlistCrudService
) {}

// Create a wishlist
public function createWishlist(string $name, string $customerId, Context $context): array
{
    $request = new CreateWishlistRequest();
    $request->setName($name);
    $request->setCustomerId($customerId);
    $request->setType('private');
    
    return $this->wishlistCrudService->createWishlist($request, $context);
}

// Add an item to a wishlist
public function addItemToWishlist(string $wishlistId, string $productId, Context $context): array
{
    return $this->wishlistCrudService->addItemToWishlist($wishlistId, $productId, 1, $context);
}
```

### Subscribing to Events

```php
class MyWishlistSubscriber implements EventSubscriberInterface
{
    private NotificationService $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            WishlistSharedEvent::class => 'onWishlistShared'
        ];
    }
    
    public function onWishlistShared(WishlistSharedEvent $event): void
    {
        $wishlist = $event->getWishlist();
        $shareId = $event->getShareId();
        
        // Send notification when a wishlist is shared
        $this->notificationService->sendWishlistSharedNotification($wishlist, $shareId);
    }
}
```

### Using the EncryptionService

```php
// Inject the service
public function __construct(
    private EncryptionService $encryptionService
) {}

// Encrypt sensitive data
public function storeSecureNote(string $wishlistId, string $note, Context $context): void
{
    $encryptedNote = $this->encryptionService->encrypt($note);
    
    // Store the encrypted note
    $this->repository->update([
        [
            'id' => $wishlistId,
            'secureNote' => $encryptedNote
        ]
    ], $context);
}

// Decrypt sensitive data
public function getSecureNote(string $wishlistId, Context $context): string
{
    $wishlist = $this->repository->search(
        (new Criteria())->addFilter(new EqualsFilter('id', $wishlistId)),
        $context
    )->first();
    
    if (!$wishlist || !$wishlist->get('secureNote')) {
        return '';
    }
    
    return $this->encryptionService->decrypt($wishlist->get('secureNote'));
}
```

## Error Handling

The AdvancedWishlist plugin uses custom exceptions for error handling:

```php
namespace AdvancedWishlist\Core\Exception;

class WishlistNotFoundException extends \Exception
{
    public function __construct(string $wishlistId)
    {
        parent::__construct(sprintf('Wishlist with id "%s" not found', $wishlistId));
    }
}

class WishlistAccessDeniedException extends \Exception
{
    public function __construct(string $wishlistId, string $userId)
    {
        parent::__construct(sprintf('User "%s" does not have access to wishlist "%s"', $userId, $wishlistId));
    }
}

class WishlistValidationException extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
```

## Conclusion

This document provides a comprehensive reference for all public APIs, service interfaces, events, and hooks available in the AdvancedWishlist plugin. Use this reference to integrate with or extend the plugin's functionality.

For more detailed information, refer to the [Developer Guide](./developer-guide.md) and [API Documentation](./api-documentation.md).