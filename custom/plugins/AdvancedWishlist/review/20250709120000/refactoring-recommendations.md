# Refactoring Recommendations

## Priority Refactoring Tasks

### 🔴 Critical (Week 1-2)

#### 1. **Complete Placeholder Implementations**
```php
// ❌ Current
public function validateCreateRequest(CreateWishlistRequest $request, Context $context): void
{
    // Placeholder for validation logic
}

// ✅ Refactored
public function validateCreateRequest(CreateWishlistRequest $request, Context $context): void
{
    $errors = [];
    
    if (empty($request->getName())) {
        $errors['name'] = 'Name is required';
    }
    
    if (strlen($request->getName()) < 3) {
        $errors['name'] = 'Name must be at least 3 characters';
    }
    
    if (!in_array($request->getType(), WishlistType::cases())) {
        $errors['type'] = 'Invalid wishlist type';
    }
    
    if ($this->wishlistExists($request->getName(), $request->getCustomerId())) {
        $errors['name'] = 'Wishlist with this name already exists';
    }
    
    if (!empty($errors)) {
        throw new ValidationException($errors);
    }
}
```

#### 2. **Fix Security Vulnerabilities**
```php
// ❌ Current - No authorization
public function deleteWishlist(string $wishlistId, Context $context): void
{
    $this->wishlistRepository->delete([['id' => $wishlistId]], $context);
}

// ✅ Refactored - With authorization
public function deleteWishlist(string $wishlistId, string $customerId, Context $context): void
{
    $wishlist = $this->loadWishlist($wishlistId, $context);
    
    if ($wishlist->getCustomerId() !== $customerId) {
        throw new AccessDeniedException('You cannot delete this wishlist');
    }
    
    if ($wishlist->isDefault() && $this->isLastWishlist($customerId, $context)) {
        throw new CannotDeleteDefaultWishlistException('Cannot delete the last wishlist');
    }
    
    $this->wishlistRepository->delete([['id' => $wishlistId]], $context);
    $this->eventDispatcher->dispatch(new WishlistDeletedEvent($wishlist, $context));
}
```

### 🟡 Important (Week 3-4)

#### 3. **Split Large Services**
```php
// ❌ Current - God Object
class WishlistService {
    // 20+ methods handling everything
}

// ✅ Refactored - Single Responsibility
// Core CRUD operations
class WishlistCrudService {
    public function create(CreateWishlistRequest $request): WishlistEntity;
    public function update(UpdateWishlistRequest $request): WishlistEntity;
    public function delete(string $id): void;
    public function find(string $id): ?WishlistEntity;
}

// Item management
class WishlistItemService {
    public function addItem(string $wishlistId, AddItemRequest $request): void;
    public function removeItem(string $wishlistId, string $itemId): void;
    public function updateItem(string $wishlistId, UpdateItemRequest $request): void;
}

// Sharing functionality
class WishlistShareService {
    public function share(ShareWishlistRequest $request): ShareToken;
    public function revoke(string $shareId): void;
    public function getShareInfo(string $token): ?WishlistShareEntity;
}

// Analytics
class WishlistAnalyticsService {
    public function trackView(string $wishlistId): void;
    public function trackConversion(string $wishlistId, Order $order): void;
    public function getStatistics(string $wishlistId): WishlistStatistics;
}
```

#### 4. **Implement Proper Value Objects**
```php
// ❌ Current - Primitive obsession
class WishlistEntity {
    private string $type; // 'private', 'public', 'shared'
}

// ✅ Refactored - Value Objects
namespace AdvancedWishlist\Domain\ValueObject;

final class WishlistType
{
    private const VALID_TYPES = ['private', 'public', 'shared'];
    
    private function __construct(
        private readonly string $value
    ) {
        if (!in_array($value, self::VALID_TYPES)) {
            throw new InvalidWishlistTypeException($value);
        }
    }
    
    public static function private(): self
    {
        return new self('private');
    }
    
    public static function public(): self
    {
        return new self('public');
    }
    
    public static function shared(): self
    {
        return new self('shared');
    }
    
    public static function fromString(string $value): self
    {
        return new self($value);
    }
    
    public function toString(): string
    {
        return $this->value;
    }
    
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
    
    public function isPublic(): bool
    {
        return $this->value === 'public';
    }
    
    public function allowsSharing(): bool
    {
        return in_array($this->value, ['public', 'shared']);
    }
}
```

### 🟢 Nice to Have (Week 5-6)

#### 5. **Extract Complex Logic to Domain Services**
```php
// ❌ Current - Logic in entity
class WishlistEntity {
    public function canBeSharedWith(string $userId): bool {
        // Complex logic here
    }
}

// ✅ Refactored - Domain Service
class WishlistSharingPolicy
{
    public function canShare(
        WishlistEntity $wishlist,
        UserEntity $sharer,
        UserEntity $recipient
    ): bool {
        // Owner can always share
        if ($wishlist->isOwnedBy($sharer)) {
            return true;
        }
        
        // Check if sharer has share permission
        if (!$wishlist->hasPermission($sharer, WishlistPermission::SHARE)) {
            return false;
        }
        
        // Check wishlist type allows sharing
        if (!$wishlist->getType()->allowsSharing()) {
            return false;
        }
        
        // Check recipient limits
        if ($this->hasReachedShareLimit($recipient)) {
            return false;
        }
        
        return true;
    }
}
```

#### 6. **Implement Builder Pattern for Complex Objects**
```php
// ✅ Wishlist Builder
class WishlistBuilder
{
    private array $data = [];
    
    public function withName(string $name): self
    {
        $this->data['name'] = $name;
        return $this;
    }
    
    public function withType(WishlistType $type): self
    {
        $this->data['type'] = $type;
        return $this;
    }
    
    public function withCustomer(string $customerId): self
    {
        $this->data['customerId'] = $customerId;
        return $this;
    }
    
    public function asDefault(): self
    {
        $this->data['isDefault'] = true;
        return $this;
    }
    
    public function withItems(array $items): self
    {
        $this->data['items'] = $items;
        return $this;
    }
    
    public function build(): WishlistEntity
    {
        $this->validate();
        
        $wishlist = new WishlistEntity();
        $wishlist->assign($this->data);
        
        return $wishlist;
    }
    
    private function validate(): void
    {
        $required = ['name', 'customerId', 'type'];
        
        foreach ($required as $field) {
            if (!isset($this->data[$field])) {
                throw new \InvalidArgumentException("Field {$field} is required");
            }
        }
    }
}

// Usage
$wishlist = (new WishlistBuilder())
    ->withName('Birthday Gifts')
    ->withType(WishlistType::private())
    ->withCustomer($customerId)
    ->asDefault()
    ->build();
```

## Code Organization Refactoring

### Current Structure Issues
```
src/
├── Core/           # Mixed concerns
├── Storefront/     # Presentation mixed with business logic
└── Migration/      # OK
```

### Recommended Structure
```
src/
├── Domain/                 # Core business logic
│   ├── Wishlist/
│   │   ├── Entity/
│   │   ├── ValueObject/
│   │   ├── Repository/
│   │   ├── Service/
│   │   ├── Event/
│   │   └── Exception/
│   ├── Guest/
│   │   └── ...
│   └── Shared/
│       ├── ValueObject/
│       └── Service/
├── Application/           # Use cases
│   ├── Command/
│   │   ├── CreateWishlist/
│   │   ├── ShareWishlist/
│   │   └── ...
│   ├── Query/
│   │   ├── GetWishlist/
│   │   ├── SearchWishlists/
│   │   └── ...
│   └── EventHandler/
├── Infrastructure/        # External dependencies
│   ├── Persistence/
│   │   ├── Doctrine/
│   │   └── Cache/
│   ├── Messaging/
│   └── Security/
└── Presentation/         # UI/API
    ├── Api/
    │   ├── Controller/
    │   └── Transformer/
    └── Storefront/
        ├── Controller/
        └── Template/
```

## Method-Level Refactoring

### Extract Method
```php
// ❌ Before
public function mergeGuestWishlistToCustomer(string $customerId, SalesChannelContext $context): void
{
    // 100+ lines of code doing multiple things
}

// ✅ After
public function mergeGuestWishlistToCustomer(string $customerId, SalesChannelContext $context): void
{
    $guestWishlist = $this->findGuestWishlist($context);
    if (!$guestWishlist) {
        return;
    }
    
    $customerWishlist = $this->getOrCreateCustomerWishlist($customerId, $context);
    $mergeResult = $this->mergeItems($guestWishlist, $customerWishlist);
    
    $this->recordMergeStatistics($mergeResult);
    $this->deleteGuestWishlist($guestWishlist);
    $this->dispatchMergeEvent($mergeResult);
}

private function findGuestWishlist(SalesChannelContext $context): ?GuestWishlistEntity
{
    // Extract guest ID and find wishlist
}

private function mergeItems(
    GuestWishlistEntity $source,
    WishlistEntity $target
): MergeResult {
    // Merge logic
}
```

## Testing After Refactoring

### Regression Test Suite
```php
class RefactoringRegressionTest extends TestCase
{
    /**
     * @dataProvider refactoredMethodsProvider
     */
    public function testRefactoredMethodBehavior(
        string $method,
        array $input,
        $expectedOutput
    ): void {
        $result = $this->service->$method(...$input);
        
        $this->assertEquals($expectedOutput, $result);
    }
}
```

## Refactoring Checklist

- [ ] Complete all placeholder implementations
- [ ] Add authorization to all endpoints
- [ ] Split large services into focused ones
- [ ] Extract value objects from primitives
- [ ] Implement domain services for complex logic
- [ ] Add builders for complex object creation
- [ ] Reorganize code structure by domain
- [ ] Extract long methods
- [ ] Add comprehensive tests
- [ ] Update documentation
- [ ] Run static analysis tools
- [ ] Performance test after changes