# Guidelines for Using Enums in PHP 8.4

This document describes the best practices and implementation guidelines for using Enumerations (Enums) in PHP 8.4 in the Advanced Wishlist System.

## Enum Basics

PHP has supported enumerations since version 8.1, providing a type-safe way to define a group of named constants. With PHP 8.4, enums should be extensively used to make code more structured and robust.

## Enum Types

There are two main types of enums in PHP:

### Pure Enums

```php
<?php

declare(strict_types=1);

namespace AdvancedWishlist\Enum;

enum WishlistVisibility
{
    case PRIVATE;
    case PUBLIC;
    case SHARED;
}
```

### Backed Enums

```php
<?php

declare(strict_types=1);

namespace AdvancedWishlist\Enum;

enum WishlistType: string
{
    case PRIVATE = 'private';
    case PUBLIC = 'public';
    case SHARED = 'shared';
}
```

## Naming Conventions

1. **Enum Names**:
   - Use **PascalCase** for enum names (e.g., `WishlistType`).
   - Use meaningful singular names (e.g., `OrderStatus` instead of `OrderStatuses`).

2. **Enum Cases**:
   - Use **UPPERCASE** for all enum cases, as they are conceptually similar to constants.
   - Use underscores for multi-word names (e.g., `PENDING_APPROVAL`).

## When to Use Backed Enums?

Use Backed Enums (with values) when:

- Values need to be stored in the database
- Values need to be serialized in JSON/XML
- Values come from external sources (e.g., API requests)
- Conversion between enums and primitive types is required

Use Pure Enums (without values) when:

- Enums are only used internally in the code
- No serialization or deserialization is necessary
- Semantics are more important than the concrete value

## Extending Enums with Methods

Enums can contain methods to extend their functionality:

```php
<?php

declare(strict_types=1);

namespace AdvancedWishlist\Enum;

enum WishlistStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::PENDING_APPROVAL => 'Waiting for Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::ARCHIVED => 'Archived',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::ACTIVE], true);
    }

    /**
     * @return array<self>
     */
    public static function getEditableStatuses(): array
    {
        return [self::DRAFT, self::ACTIVE];
    }
}
```

## Type Hints with Enums

Use enum type hints for parameters and return values:

```php
public function updateWishlistStatus(WishlistEntity $wishlist, WishlistStatus $newStatus): void
{
    // Implementation
}

public function getPermissionsForRole(WishlistRole $role): array
{
    // Implementation
}
```

## Integration with Doctrine

For using enums with Doctrine, special converters must be implemented:

```php
<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Doctrine;

use AdvancedWishlist\Enum\WishlistType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class WishlistTypeType extends Type
{
    public const NAME = 'wishlist_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getVarcharTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof WishlistType ? $value->value : null;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?WishlistType
    {
        return $value !== null ? WishlistType::tryFrom($value) : null;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
```

## Usage in Entities

Example for integrating enums in entities:

```php
<?php

declare(strict_types=1);

namespace AdvancedWishlist\Entity;

use AdvancedWishlist\Enum\WishlistStatus;
use AdvancedWishlist\Enum\WishlistType;

class WishlistEntity
{
    private string $id;
    private string $customerId;
    private string $name;
    private WishlistType $type;
    private WishlistStatus $status;

    // Getters and Setters

    public function getType(): WishlistType
    {
        return $this->type;
    }

    public function setType(WishlistType $type): void
    {
        $this->type = $type;
    }

    public function getStatus(): WishlistStatus
    {
        return $this->status;
    }

    public function setStatus(WishlistStatus $status): void
    {
        $this->status = $status;
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }
}
```

## Usage in DTOs

```php
<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\DTO;

use AdvancedWishlist\Enum\WishlistStatus;
use AdvancedWishlist\Enum\WishlistType;
use Symfony\Component\Validator\Constraints as Assert;

class WishlistDTO
{
    #[Assert\NotBlank]
    private string $name;

    #[Assert\NotNull]
    private WishlistType $type;

    #[Assert\NotNull]
    private WishlistStatus $status;

    // Getters and Setters

    public function getType(): WishlistType
    {
        return $this->type;
    }

    public function setType(WishlistType $type): void
    {
        $this->type = $type;
    }

    public function getStatus(): WishlistStatus
    {
        return $this->status;
    }

    public function setStatus(WishlistStatus $status): void
    {
        $this->status = $status;
    }
}
```

## Error Handling with Enums

Use `tryFrom()` for safe conversion from strings/ints to enums:

```php
public function updateWishlistType(string $wishlistId, string $typeString): void
{
    $type = WishlistType::tryFrom($typeString);

    if ($type === null) {
        throw new InvalidArgumentException(sprintf(
            'Invalid wishlist type "%s". Allowed values: %s',
            $typeString,
            implode(', ', array_column(WishlistType::cases(), 'value'))
        ));
    }

    $wishlist = $this->wishlistRepository->find($wishlistId);
    $wishlist->setType($type);
    $this->wishlistRepository->save($wishlist);
}
```

## Serialization of Enums

Example method for JSON serialization:

```php
/**
 * @return array<string, mixed>
 */
public function serializeWishlist(WishlistEntity $wishlist): array
{
    return [
        'id' => $wishlist->getId(),
        'name' => $wishlist->getName(),
        'type' => $wishlist->getType()->value,
        'status' => $wishlist->getStatus()->value,
        'statusLabel' => $wishlist->getStatus()->getLabel(),
        'isEditable' => $wishlist->isEditable(),
    ];
}
```

## Usage in Templates (Twig)

```twig
{# Status-dependent display #}
{% if wishlist.status == constant('AdvancedWishlist\\Enum\\WishlistStatus::ACTIVE') %}
    <span class="badge badge-success">{{ wishlist.status.getLabel() }}</span>
{% elseif wishlist.status == constant('AdvancedWishlist\\Enum\\WishlistStatus::PENDING_APPROVAL') %}
    <span class="badge badge-warning">{{ wishlist.status.getLabel() }}</span>
{% else %}
    <span class="badge badge-secondary">{{ wishlist.status.getLabel() }}</span>
{% endif %}

{# Permission check #}
{% if wishlist.status.isEditable() %}
    <button class="btn btn-primary">Edit</button>
{% endif %}
```

## Examples for Commonly Used Enums

Here are some examples of enums that should be used in the Advanced Wishlist System:

### WishlistType

```php
enum WishlistType: string
{
    case PRIVATE = 'private';
    case PUBLIC = 'public';
    case SHARED = 'shared';
}
```

### WishlistStatus

```php
enum WishlistStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ARCHIVED = 'archived';
}
```

### WishlistRole

```php
enum WishlistRole: string
{
    case OWNER = 'owner';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';
}
```

### WishlistPermission

```php
enum WishlistPermission: string
{
    case ADD_PRODUCT = 'add_product';
    case REMOVE_PRODUCT = 'remove_product';
    case MANAGE_MEMBERS = 'manage_members';
    case TRIGGER_ORDER = 'trigger_order';
    case ADD_COMMENT = 'add_comment';
    case EXPORT_WISHLIST = 'export_wishlist';
}
```

### NotificationType

```php
enum NotificationType: string
{
    case PRICE_DROP = 'price_drop';
    case BACK_IN_STOCK = 'back_in_stock';
    case WISHLIST_SHARED = 'wishlist_shared';
    case APPROVAL_REQUESTED = 'approval_requested';
    case APPROVAL_GRANTED = 'approval_granted';
    case APPROVAL_REJECTED = 'approval_rejected';
}
```

## Performance Considerations

- Enums are objects and have a slight overhead compared to primitive types
- With large data volumes, serialization/deserialization of enums can affect performance
- In performance-critical paths, using caching for enum values can be beneficial

## Migration Strategies

When migrating from string constants to enums:

1. Identify all places where string constants are used
2. Create corresponding backed enums with the same values
3. Update entities and services to use enums
4. Add type hints and update tests
5. Update Doctrine mappings with enum converters

## Conclusion

The consistent use of enums in PHP 8.4 improves the code quality of the Advanced Wishlist System through:

- Increased type safety
- Improved readability and self-documentation
- Centralized definition of states and behavior
- Prevention of errors due to invalid states

Enums should be a central component of the Advanced Wishlist System implementation and should be used in all relevant areas.
