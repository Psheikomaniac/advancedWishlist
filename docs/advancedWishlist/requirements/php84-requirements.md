# PHP 8.4 Requirements for Advanced Wishlist System

This document outlines the specific requirements and features for PHP 8.4 compatibility in the Advanced Wishlist System plugin for Shopware 6.

## PHP 8.4 Overview

PHP 8.4 is scheduled for release in November 2024 and will be supported until November 2027. As a modern Shopware 6 plugin, the Advanced Wishlist System should leverage the latest PHP features while maintaining compatibility with the minimum PHP version required by Shopware.

## Required PHP Version

- **Minimum PHP Version**: 8.4.0
- **Recommended PHP Version**: 8.4.x (latest stable release)
- **Development Environment**: Must use PHP 8.4 for development to ensure compatibility

## PHP 8.4 Features to Utilize

### Class Constant Type Declarations

Use type declarations for class constants:

```php
class WishlistService
{
    public const string DEFAULT_WISHLIST_NAME = 'My Wishlist';
    public const int MAX_WISHLISTS = 10;
    public const array ALLOWED_TYPES = ['private', 'public', 'shared'];
}
```

### Typed Class Constants in Interfaces

Define typed constants in interfaces:

```php
interface WishlistLimitInterface
{
    public const int DEFAULT_LIMIT = 10;
    public const int PREMIUM_LIMIT = 50;
}
```

### First-Class Callable Syntax

Use the new first-class callable syntax for cleaner code:

```php
// Before PHP 8.4
$getWishlistName = fn($wishlist) => $wishlist->getName();

// With PHP 8.4
$getWishlistName = $wishlist->getName(...);
```

### Parameter Type Declarations

Use union types, intersection types, and nullable types for parameters:

```php
public function processWishlist(
    WishlistEntity|WishlistDTO $wishlist,
    ?Context $context = null
): void {
    // Implementation
}
```

### Return Type Declarations

Use specific return types including union types:

```php
public function findWishlist(string $id): ?WishlistEntity
{
    // Implementation
}

public function getWishlistResponse(string $id): WishlistResponse|ErrorResponse
{
    // Implementation
}
```

### Constructor Property Promotion

Use constructor property promotion for cleaner class definitions:

```php
class WishlistService
{
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}
}
```

### Named Arguments

Use named arguments for better readability:

```php
$wishlistService->createWishlist(
    name: 'Birthday Wishlist',
    customerId: $customer->getId(),
    isPublic: true,
    expiresAt: new \DateTime('+30 days'),
);
```

### Readonly Properties

Use readonly properties for immutable data:

```php
class WishlistEntity
{
    public readonly string $id;
    public readonly string $customerId;
    public readonly \DateTimeInterface $createdAt;
    
    private string $name;
    private ?string $description;
    
    // Getters and setters for mutable properties
}
```

### Match Expression

Use match expressions instead of switch statements:

```php
$errorMessage = match ($errorCode) {
    'WISHLIST_NOT_FOUND' => 'The wishlist could not be found',
    'ITEM_NOT_FOUND' => 'The item could not be found',
    'PERMISSION_DENIED' => 'You do not have permission to access this wishlist',
    default => 'An unknown error occurred',
};
```

### Nullsafe Operator

Use the nullsafe operator for safer property access:

```php
$wishlistName = $customer?->getDefaultWishlist()?->getName() ?? 'No wishlist';
```

### Attributes (PHP 8.0+)

Use attributes for metadata and annotations:

```php
class WishlistController
{
    #[Route('/api/wishlist', methods: ['POST'])]
    #[Permission('create_wishlist')]
    public function createWishlist(Request $request): Response
    {
        // Implementation
    }
}
```

## PHP 8.4 Compatibility Checklist

Ensure the following compatibility checks are performed:

1. **Remove deprecated function calls**: Check for functions deprecated in PHP 8.4
2. **Update parameter order**: Ensure parameter order matches expected signatures
3. **Check for removed features**: Verify no usage of features removed in PHP 8.4
4. **Update error handling**: Adapt to changes in error handling behavior
5. **Test with strict types**: Ensure all files use `declare(strict_types=1);`

## Static Analysis

Use static analysis tools configured for PHP 8.4:

```bash
# Run PHPStan with level 8 (max)
vendor/bin/phpstan analyse src --level=8

# Run Psalm with errorLevel 1 (strictest)
vendor/bin/psalm --show-info=true
```

## Composer Configuration

Specify PHP 8.4 requirement in composer.json:

```json
{
    "name": "advanced-wishlist/shopware6",
    "description": "Advanced Wishlist System for Shopware 6",
    "type": "shopware-platform-plugin",
    "license": "proprietary",
    "require": {
        "php": ">=8.4.0",
        "shopware/core": "^6.5.0"
    },
    "require-dev": {
        "phpstan/phpstan": "^1.10",
        "phpunit/phpunit": "^10.0",
        "symfony/phpunit-bridge": "^6.0"
    },
    "config": {
        "platform": {
            "php": "8.4.0"
        }
    }
}
```

## CI/CD Configuration

Configure CI/CD pipelines to test with PHP 8.4:

```yaml
# Example GitHub Actions workflow
name: PHP 8.4 Compatibility

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  php84-compatibility:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP 8.4
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.4'
        extensions: mbstring, intl, pdo_mysql
        coverage: none
        
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
      
    - name: Run PHPStan
      run: vendor/bin/phpstan analyse src --level=8
      
    - name: Run tests
      run: vendor/bin/phpunit
```

## Performance Considerations

Take advantage of PHP 8.4 performance improvements:

1. **JIT Compilation**: Enable JIT in production for performance boost
2. **Optimized Opcache**: Configure opcache for production environments
3. **Reduced Memory Usage**: Take advantage of PHP 8.4's memory optimizations

## Migration Guide

When upgrading from PHP 8.3 to PHP 8.4:

1. Update all development environments to PHP 8.4
2. Run static analysis tools to identify compatibility issues
3. Update code to use new PHP 8.4 features
4. Run comprehensive tests to ensure functionality
5. Update CI/CD pipelines to use PHP 8.4
6. Update documentation to reflect PHP 8.4 requirements

## Learning Resources

- [PHP 8.4 Release Notes](https://www.php.net/releases/8.4/en.php)
- [PHP 8.4 Migration Guide](https://www.php.net/manual/en/migration84.php)
- [PHP 8.4 RFC List](https://wiki.php.net/rfc#php_84)
- [PHP 8.4 New Features](https://stitcher.io/blog/new-in-php-84)