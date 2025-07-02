# Development Guidelines for Advanced Wishlist System

This document outlines the coding standards, architecture principles, and best practices for developing the Advanced Wishlist System plugin for Shopware 6.

## Coding Standards

### PHP Coding Standards

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style
- Use PHP 8.4 features appropriately (see [PHP 8.4 Requirements](./php84-requirements.md))
- Use strict typing with `declare(strict_types=1);` in all files
- Use type hints for parameters and return types
- Use nullable types (`?string`) instead of default `null` values where appropriate
- Use constructor property promotion for simple class properties
- Use readonly properties for immutable values

Example:

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Response\WishlistResponse;

class WishlistService
{
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly LoggerInterface $logger
    ) {}
    
    public function createWishlist(
        CreateWishlistRequest $request,
        Context $context
    ): WishlistResponse {
        // Implementation
    }
}
```

### JavaScript/TypeScript Standards

- Use TypeScript for all new JavaScript code
- Follow the [Airbnb JavaScript Style Guide](https://github.com/airbnb/javascript)
- Use ESLint with the Shopware preset
- Use Vue.js 3 Composition API
- Use Pinia for state management
- Use async/await for asynchronous operations

Example:

```typescript
// wishlist-store.ts
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { Wishlist, WishlistItem } from '@/types';
import { wishlistApi } from '@/api';

export const useWishlistStore = defineStore('wishlist', () => {
  const wishlists = ref<Wishlist[]>([]);
  const isLoading = ref(false);
  const error = ref<string | null>(null);
  
  const defaultWishlist = computed(() => 
    wishlists.value.find(w => w.isDefault)
  );
  
  const totalItems = computed(() => 
    wishlists.value.reduce((sum, w) => sum + w.items.length, 0)
  );
  
  async function loadWishlists() {
    try {
      isLoading.value = true;
      const response = await wishlistApi.getWishlists();
      wishlists.value = response.data;
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Unknown error';
    } finally {
      isLoading.value = false;
    }
  }
  
  async function addItem(wishlistId: string, productId: string) {
    const response = await wishlistApi.addItem(wishlistId, { productId });
    
    // Update local state
    const wishlist = wishlists.value.find(w => w.id === wishlistId);
    if (wishlist) {
      wishlist.items.push(response.data);
    }
    
    return response.data;
  }
  
  return {
    wishlists,
    isLoading,
    error,
    defaultWishlist,
    totalItems,
    loadWishlists,
    addItem
  };
});
```

### CSS/SCSS Standards

- Use SCSS for styling
- Follow BEM (Block Element Modifier) methodology
- Use Shopware's design system variables for consistency
- Avoid !important declarations
- Use responsive design principles

## Architecture Principles

### Clean Architecture

The plugin follows Clean Architecture principles with clear separation of concerns:

1. **Domain Layer**: Core business logic and entities
2. **Application Layer**: Use cases and services
3. **Infrastructure Layer**: Repositories, external services
4. **Presentation Layer**: Controllers, API endpoints, UI components

### Dependency Injection

- Use Shopware's DI container
- Define services in `services.xml`
- Inject dependencies via constructor
- Use interfaces for service contracts

Example `services.xml`:

```xml
<?xml version="1.0" ?>
<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="AdvancedWishlist\Core\Service\WishlistService">
            <argument type="service" id="wishlist.repository"/>
            <argument type="service" id="logger"/>
        </service>
    </services>
</container>
```

### DTO Pattern

- Use DTOs for all API requests and responses
- Separate Request and Response DTOs
- Implement validation in DTOs
- Use immutable DTOs where possible

### Repository Pattern

- Use Shopware's DAL (Data Abstraction Layer) for database operations
- Extend repositories only when necessary
- Use criteria objects for filtering and sorting
- Optimize queries for performance

## Best Practices

### Performance Optimization

- Use caching for expensive operations
- Optimize database queries (indexes, joins)
- Lazy load related entities
- Use pagination for large collections
- Minimize DOM manipulations in frontend code
- Use asynchronous operations where appropriate

### Security

- Validate all user input
- Use parameterized queries
- Implement proper access control
- Follow OWASP security guidelines
- Use CSRF protection for forms
- Sanitize output to prevent XSS
- Use secure cookies with appropriate flags

### Testing

- Write unit tests for all business logic
- Write integration tests for repositories and services
- Write end-to-end tests for critical user flows
- Use PHPUnit for PHP tests
- Use Jest and Vue Test Utils for JavaScript tests
- Aim for at least 80% code coverage

Example test:

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Test\Core\Service;

use AdvancedWishlist\Core\Service\WishlistService;
use PHPUnit\Framework\TestCase;

class WishlistServiceTest extends TestCase
{
    private WishlistService $service;
    
    protected function setUp(): void
    {
        // Setup test dependencies
        $this->service = new WishlistService(/* ... */);
    }
    
    public function testCreateWishlist(): void
    {
        // Arrange
        $request = new CreateWishlistRequest();
        $request->setName('Test Wishlist');
        
        // Act
        $response = $this->service->createWishlist($request, $this->createMock(Context::class));
        
        // Assert
        self::assertNotNull($response->getId());
        self::assertEquals('Test Wishlist', $response->getName());
    }
}
```

### Documentation

- Document all classes, methods, and properties with PHPDoc
- Document complex algorithms and business rules
- Keep documentation up-to-date with code changes
- Document API endpoints with OpenAPI/Swagger
- Include examples in documentation

### Logging

- Use appropriate log levels (debug, info, warning, error)
- Include context in log messages
- Don't log sensitive information
- Use structured logging
- Implement proper log rotation

## Development Environment

### Local Setup

1. Use Shopware's official development template
2. Use Docker for consistent environments
3. Configure Xdebug for debugging
4. Use development mode for Shopware

### Required Tools

- PHP 8.4+
- Composer 2.0+
- Node.js 18+
- npm 8+
- Docker and Docker Compose
- Git
- PHPUnit
- PHPStan
- PHP_CodeSniffer
- ESLint
- Prettier

### IDE Configuration

- Use PHPStorm or VSCode with appropriate plugins
- Configure code style to match project standards
- Set up file watchers for automatic linting
- Configure Xdebug integration

## Continuous Integration

- Run automated tests on each pull request
- Check code style and static analysis
- Generate code coverage reports
- Perform security scans
- Build and test the plugin in a clean environment

## Deployment

- Use semantic versioning for releases
- Generate a changelog for each release
- Create a release checklist
- Test in staging environment before production
- Implement rollback procedures

## Monitoring and Maintenance

- Implement health checks
- Monitor performance metrics
- Set up error tracking
- Plan for regular updates and maintenance
- Document known issues and workarounds

## Learning Resources

- [Shopware Developer Documentation](https://developer.shopware.com/)
- [PHP 8.4 Documentation](https://www.php.net/releases/8.4/en.php)
- [Vue.js 3 Documentation](https://vuejs.org/guide/introduction.html)
- [Clean Architecture by Robert C. Martin](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)