# AdvancedWishlist Developer Guide

This document provides comprehensive information for developers working with the AdvancedWishlist plugin. It covers the architecture, extension points, and includes code examples for common tasks.

## Architecture Overview

The AdvancedWishlist plugin follows a clean architecture approach with clear separation of concerns:

### Core Components

1. **Domain Layer**
   - Entities (Wishlist, WishlistItem, WishlistShare, etc.)
   - Value Objects
   - Domain Services

2. **Application Layer**
   - Services (WishlistService, ShareService, etc.)
   - DTOs (Data Transfer Objects)
   - Command and Query Handlers (CQRS pattern)

3. **Infrastructure Layer**
   - Repositories
   - External Services
   - Persistence

4. **Presentation Layer**
   - Controllers (API endpoints)
   - Storefront Integration
   - Administration Integration

### Directory Structure

```
AdvancedWishlist/
├── src/
│   ├── Core/
│   │   ├── Content/
│   │   │   ├── Wishlist/
│   │   │   │   ├── WishlistDefinition.php
│   │   │   │   ├── WishlistEntity.php
│   │   │   │   ├── Aggregate/
│   │   │   │   │   ├── WishlistItem/
│   │   │   │   │   │   ├── WishlistItemDefinition.php
│   │   │   │   │   │   └── WishlistItemEntity.php
│   │   │   │   │   └── WishlistShare/
│   │   │   │   │       ├── WishlistShareDefinition.php
│   │   │   │   │       └── WishlistShareEntity.php
│   │   │   ├── GuestWishlist/
│   │   │   │   ├── GuestWishlistDefinition.php
│   │   │   │   └── GuestWishlistEntity.php
│   │   │   └── WishlistShareView/
│   │   │       ├── WishlistShareViewDefinition.php
│   │   │       └── WishlistShareViewEntity.php
│   │   ├── DTO/
│   │   │   ├── Request/
│   │   │   │   ├── AbstractRequestDTO.php
│   │   │   │   ├── CreateWishlistRequest.php
│   │   │   │   └── UpdateWishlistRequest.php
│   │   │   └── Response/
│   │   │       ├── AbstractResponseDTO.php
│   │   │       └── WishlistResponse.php
│   │   ├── CQRS/
│   │   │   ├── Command/
│   │   │   │   ├── CommandBus.php
│   │   │   │   └── CreateWishlistCommandHandler.php
│   │   │   └── Query/
│   │   │       ├── QueryBus.php
│   │   │       └── GetWishlistQueryHandler.php
│   │   ├── Service/
│   │   │   ├── WishlistCrudService.php
│   │   │   ├── WishlistValidator.php
│   │   │   ├── WishlistLimitService.php
│   │   │   └── WishlistCacheService.php
│   │   ├── Factory/
│   │   │   └── WishlistFactory.php
│   │   ├── Domain/
│   │   │   ├── Strategy/
│   │   │   │   ├── PrivateWishlistVisibilityStrategy.php
│   │   │   │   ├── PublicWishlistVisibilityStrategy.php
│   │   │   │   └── SharedWishlistVisibilityStrategy.php
│   │   │   └── Service/
│   │   │       └── WishlistVisibilityService.php
│   │   ├── Builder/
│   │   │   └── WishlistBuilder.php
│   │   ├── OAuth/
│   │   │   ├── Controller/
│   │   │   ├── Entity/
│   │   │   ├── Repository/
│   │   │   ├── Service/
│   │   │   └── Middleware/
│   │   └── Security/
│   │       ├── SecurityMonitoringService.php
│   │       └── SecurityMonitoringSubscriber.php
│   ├── Service/
│   │   ├── EncryptionService.php
│   │   ├── ShareService.php
│   │   ├── AnalyticsService.php
│   │   └── NotificationService.php
│   ├── Storefront/
│   │   └── Controller/
│   │       └── WishlistController.php
│   ├── Administration/
│   │   └── Controller/
│   │       └── AnalyticsController.php
│   ├── Migration/
│   │   ├── Migration1700000000CreateWishlistTables.php
│   │   └── Migration1700000001AddPerformanceIndexes.php
│   └── Resources/
│       ├── config/
│       │   ├── services.xml
│       │   └── routes.yaml
│       ├── views/
│       └── app/
├── tests/
└── docs/
```

## Design Patterns

The AdvancedWishlist plugin implements several design patterns to ensure maintainable and extensible code:

### 1. Command Query Responsibility Segregation (CQRS)

The plugin separates read and write operations using the CQRS pattern:

- **Commands**: Used for state-changing operations (create, update, delete)
- **Queries**: Used for read operations that don't change state

Example of a Query:

```php
// Query
class GetWishlistsQuery
{
    public function __construct(
        public readonly string $customerId,
        public readonly Criteria $criteria,
        public readonly SalesChannelContext $context
    ) {}
}

// Query Handler
class GetWishlistsQueryHandler
{
    public function __construct(
        private EntityRepository $wishlistRepository
    ) {}
    
    public function __invoke(GetWishlistsQuery $query): array
    {
        $criteria = $query->criteria;
        $criteria->addFilter(new EqualsFilter('customerId', $query->customerId));
        
        $result = $this->wishlistRepository->search($criteria, $query->context->getContext());
        
        return [
            'total' => $result->getTotal(),
            'data' => $result->getElements()
        ];
    }
}
```

### 2. Factory Pattern

The plugin uses factories to create complex objects:

```php
class WishlistFactory
{
    public function createWishlist(CreateWishlistRequest $request, Context $context): WishlistEntity
    {
        $wishlist = new WishlistEntity();
        $wishlist->setId(Uuid::randomHex());
        $wishlist->setName($request->getName());
        $wishlist->setCustomerId($request->getCustomerId());
        $wishlist->setType($request->getType() ?? 'private');
        $wishlist->setCreatedAt(new \DateTime());
        
        return $wishlist;
    }
}
```

### 3. Strategy Pattern

The plugin uses the strategy pattern for handling different wishlist visibility types:

```php
interface WishlistVisibilityStrategyInterface
{
    public function canView(WishlistEntity $wishlist, string $userId): bool;
}

class PrivateWishlistVisibilityStrategy implements WishlistVisibilityStrategyInterface
{
    public function canView(WishlistEntity $wishlist, string $userId): bool
    {
        return $wishlist->getCustomerId() === $userId;
    }
}

class PublicWishlistVisibilityStrategy implements WishlistVisibilityStrategyInterface
{
    public function canView(WishlistEntity $wishlist, string $userId): bool
    {
        return true;
    }
}
```

### 4. Builder Pattern

The plugin uses the builder pattern for constructing complex objects:

```php
class WishlistBuilder
{
    private string $name;
    private string $customerId;
    private string $type = 'private';
    private array $items = [];
    
    public function withName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    
    public function withCustomerId(string $customerId): self
    {
        $this->customerId = $customerId;
        return $this;
    }
    
    public function withType(string $type): self
    {
        $this->type = $type;
        return $this;
    }
    
    public function withItems(array $items): self
    {
        $this->items = $items;
        return $this;
    }
    
    public function build(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => $this->name,
            'customerId' => $this->customerId,
            'type' => $this->type,
            'items' => $this->items
        ];
    }
}
```

## Extension Points

The AdvancedWishlist plugin provides several extension points for customization:

### 1. Events

The plugin dispatches events that you can subscribe to:

```php
// Event Subscriber Example
class WishlistEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'wishlist.created' => 'onWishlistCreated',
            'wishlist.updated' => 'onWishlistUpdated',
            'wishlist.deleted' => 'onWishlistDeleted',
            'wishlist.item.added' => 'onItemAdded',
            'wishlist.item.removed' => 'onItemRemoved',
            'wishlist.shared' => 'onWishlistShared'
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

### 2. Decorating Services

You can extend the functionality of services by decorating them:

```php
// Service Decorator Example
class CustomWishlistValidator extends WishlistValidator
{
    private WishlistValidator $innerValidator;
    
    public function __construct(WishlistValidator $innerValidator)
    {
        $this->innerValidator = $innerValidator;
    }
    
    public function validate(CreateWishlistRequest $request): void
    {
        // Call the inner validator first
        $this->innerValidator->validate($request);
        
        // Add custom validation logic
        if (strpos($request->getName(), 'forbidden') !== false) {
            throw new ValidationException('Wishlist name contains forbidden words');
        }
    }
}
```

In your `services.xml`:

```xml
<service id="YourPlugin\CustomWishlistValidator" decorates="AdvancedWishlist\Core\Service\WishlistValidator">
    <argument type="service" id="YourPlugin\CustomWishlistValidator.inner"/>
</service>
```

### 3. Custom Entities

You can extend the data model by creating custom entities that relate to wishlists:

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

## Common Tasks

### 1. Creating a Wishlist

```php
// Controller Example
public function createWishlist(Request $request, SalesChannelContext $context): Response
{
    $customerId = $context->getCustomer()->getId();
    
    $createRequest = new CreateWishlistRequest();
    $createRequest->setName($request->request->get('name'));
    $createRequest->setCustomerId($customerId);
    $createRequest->setType($request->request->get('type', 'private'));
    
    $wishlist = $this->wishlistCrudService->createWishlist($createRequest, $context->getContext());
    
    return new JsonResponse($wishlist);
}
```

### 2. Adding an Item to a Wishlist

```php
// Service Example
public function addItemToWishlist(string $wishlistId, string $productId, int $quantity, Context $context): WishlistItemEntity
{
    $wishlistItem = [
        'id' => Uuid::randomHex(),
        'wishlistId' => $wishlistId,
        'productId' => $productId,
        'quantity' => $quantity
    ];
    
    $this->wishlistItemRepository->create([$wishlistItem], $context);
    
    return $this->wishlistItemRepository->search(
        (new Criteria())->addFilter(new EqualsFilter('id', $wishlistItem['id'])),
        $context
    )->first();
}
```

### 3. Sharing a Wishlist

```php
// Service Example
public function shareWishlist(string $wishlistId, string $recipientEmail, Context $context): WishlistShareEntity
{
    // Validate wishlist exists and belongs to the user
    $wishlist = $this->wishlistRepository->search(
        (new Criteria())->addFilter(new EqualsFilter('id', $wishlistId)),
        $context
    )->first();
    
    if (!$wishlist) {
        throw new WishlistNotFoundException($wishlistId);
    }
    
    // Create share token
    $token = $this->encryptionService->generateToken();
    $encryptedToken = $this->encryptionService->encrypt($token);
    
    // Create share record
    $shareData = [
        'id' => Uuid::randomHex(),
        'wishlistId' => $wishlistId,
        'recipientEmail' => $recipientEmail,
        'token' => $encryptedToken,
        'createdAt' => new \DateTime()
    ];
    
    $this->wishlistShareRepository->create([$shareData], $context);
    
    // Dispatch event
    $this->eventDispatcher->dispatch(
        new WishlistSharedEvent($wishlist, $shareData['id'], $context)
    );
    
    return $this->wishlistShareRepository->search(
        (new Criteria())->addFilter(new EqualsFilter('id', $shareData['id'])),
        $context
    )->first();
}
```

## Security Considerations

When working with the AdvancedWishlist plugin, keep these security considerations in mind:

1. **Authorization**: Always check that the current user has permission to access or modify a wishlist
2. **CSRF Protection**: Use CSRF tokens for all state-changing operations
3. **Input Validation**: Validate all user input before processing
4. **Encryption**: Use the EncryptionService for sensitive data
5. **Rate Limiting**: Implement rate limiting for API endpoints to prevent abuse

Example of proper authorization check:

```php
public function updateWishlist(string $wishlistId, UpdateWishlistRequest $request, Context $context): WishlistEntity
{
    $wishlist = $this->wishlistRepository->search(
        (new Criteria())->addFilter(new EqualsFilter('id', $wishlistId)),
        $context
    )->first();
    
    if (!$wishlist) {
        throw new WishlistNotFoundException($wishlistId);
    }
    
    // Check if the current user is the owner of the wishlist
    $currentUserId = $context->getSource() instanceof AdminApiSource
        ? $context->getSource()->getUserId()
        : $context->getSource() instanceof SalesChannelApiSource && $context->getSource()->getCustomer()
            ? $context->getSource()->getCustomer()->getId()
            : null;
    
    if ($currentUserId !== $wishlist->getCustomerId()) {
        throw new AccessDeniedException('You do not have permission to update this wishlist');
    }
    
    // Proceed with update...
}
```

## Performance Optimization

To ensure optimal performance when working with wishlists:

1. **Use Pagination**: Always paginate results when fetching multiple wishlists or items
2. **Selective Loading**: Use the Criteria object to select only the fields you need
3. **Caching**: Use the WishlistCacheService for frequently accessed data
4. **Indexing**: Ensure proper database indexes are in place for frequently queried fields

Example of optimized query:

```php
public function getWishlistsForCustomer(string $customerId, int $page = 1, int $limit = 10, Context $context): EntitySearchResult
{
    $criteria = new Criteria();
    $criteria->addFilter(new EqualsFilter('customerId', $customerId));
    
    // Pagination
    $criteria->setLimit($limit);
    $criteria->setOffset(($page - 1) * $limit);
    
    // Selective loading - only load fields we need
    $criteria->addAssociation('items');
    
    // Sorting
    $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
    
    return $this->wishlistRepository->search($criteria, $context);
}
```

## Testing

The AdvancedWishlist plugin includes comprehensive tests. When extending the plugin, make sure to write tests for your custom functionality:

1. **Unit Tests**: Test individual components in isolation
2. **Integration Tests**: Test the interaction between components
3. **End-to-End Tests**: Test complete user flows

Example of a unit test:

```php
class WishlistValidatorTest extends TestCase
{
    private WishlistValidator $validator;
    
    protected function setUp(): void
    {
        $this->validator = new WishlistValidator();
    }
    
    public function testValidateWithValidRequest(): void
    {
        $request = new CreateWishlistRequest();
        $request->setName('Test Wishlist');
        $request->setCustomerId('customer123');
        
        // This should not throw an exception
        $this->validator->validate($request);
        
        // If we reach here, the test passes
        $this->assertTrue(true);
    }
    
    public function testValidateWithInvalidRequest(): void
    {
        $request = new CreateWishlistRequest();
        // Missing name
        $request->setCustomerId('customer123');
        
        $this->expectException(ValidationException::class);
        $this->validator->validate($request);
    }
}
```

## Troubleshooting

Common issues and their solutions:

1. **Missing Dependencies**: Ensure all required services are properly registered in `services.xml`
2. **Permission Issues**: Check that the current user has the necessary permissions
3. **Cache Issues**: Clear the cache after making changes to services or entities
4. **Database Issues**: Run the migrations to ensure the database schema is up to date

## Further Resources

- [API Documentation](./api-documentation.md)
- [User Documentation](./user-documentation.md)
- [Shopware Developer Documentation](https://developer.shopware.com/)
- [PHP 8.4 Documentation](https://www.php.net/releases/8.4/en.php)