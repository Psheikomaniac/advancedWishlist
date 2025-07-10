# Missing Requirements & PHP 8.4 Implementation Analysis

## Overview
This document identifies all implementation areas that didn't receive "excellent" ratings and provides a comprehensive analysis of PHP 8.4 compatibility and feature implementation opportunities.

## ❌ Implementation Areas Requiring Improvement

### 1. **Rate Limiting Implementation**
**Current Status**: Not Implemented
**Priority**: High (Production Requirement)
**Impact**: Security vulnerability in production environments

#### Required Implementation:
```php
// src/Core/Security/RateLimitService.php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Security;

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Cache\CacheItemPoolInterface;

class RateLimitService
{
    private RateLimiterFactory $limiterFactory;
    
    public function __construct(CacheItemPoolInterface $cache)
    {
        $storage = new CacheStorage($cache);
        $this->limiterFactory = new RateLimiterFactory([
            'id' => 'wishlist_api',
            'policy' => 'sliding_window',
            'limit' => 100,
            'interval' => '1 hour',
        ], $storage);
    }
    
    public function isAllowed(Request $request): bool
    {
        $limiter = $this->limiterFactory->create($this->getClientKey($request));
        return $limiter->consume(1)->isAccepted();
    }
    
    private function getClientKey(Request $request): string
    {
        return md5($request->getClientIp() . ':' . $request->headers->get('User-Agent', ''));
    }
}

// src/Core/Security/RateLimitMiddleware.php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RateLimitMiddleware implements EventSubscriberInterface
{
    public function __construct(
        private RateLimitService $rateLimitService
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Only apply to API routes
        if (!str_starts_with($request->getPathInfo(), '/store-api/wishlist')) {
            return;
        }
        
        if (!$this->rateLimitService->isAllowed($request)) {
            $response = new JsonResponse([
                'errors' => [[
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'title' => 'Rate Limit Exceeded',
                    'detail' => 'Too many requests. Please try again later.'
                ]]
            ], Response::HTTP_TOO_MANY_REQUESTS);
            
            $response->headers->set('Retry-After', '3600');
            $event->setResponse($response);
        }
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => ['onKernelRequest', 10],
        ];
    }
}
```

#### Service Configuration:
```xml
<!-- src/Resources/config/services.xml -->
<service id="AdvancedWishlist\Core\Security\RateLimitService">
    <argument type="service" id="cache.default"/>
</service>

<service id="AdvancedWishlist\Core\Security\RateLimitMiddleware">
    <argument type="service" id="AdvancedWishlist\Core\Security\RateLimitService"/>
    <tag name="kernel.event_subscriber"/>
</service>
```

### 2. **Security Headers Implementation**
**Current Status**: Not Implemented
**Priority**: Medium (Security Enhancement)
**Impact**: Missing security headers for production deployment

#### Required Implementation:
```php
// src/Core/Security/SecurityHeadersSubscriber.php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Security;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        
        $response = $event->getResponse();
        $request = $event->getRequest();
        
        // Only apply to API routes
        if (str_starts_with($request->getPathInfo(), '/store-api/wishlist')) {
            // Security headers for API responses
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('X-XSS-Protection', '1; mode=block');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->headers->set('Content-Security-Policy', "default-src 'self'");
            
            // CORS headers if needed
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            
            // Cache control for API responses
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.response' => ['onKernelResponse', -10],
        ];
    }
}
```

### 3. **API Versioning Strategy**
**Current Status**: Not Implemented
**Priority**: Medium (Future-Proofing)
**Impact**: API evolution and backward compatibility

#### Required Implementation:
```php
// src/Core/Routing/ApiVersionResolver.php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Routing;

use Symfony\Component\HttpFoundation\Request;

class ApiVersionResolver
{
    private const DEFAULT_VERSION = 'v1';
    private const SUPPORTED_VERSIONS = ['v1', 'v2'];
    
    public function resolveVersion(Request $request): string
    {
        // Check header first
        $version = $request->headers->get('API-Version');
        if ($version && $this->isVersionSupported($version)) {
            return $version;
        }
        
        // Check query parameter
        $version = $request->query->get('version');
        if ($version && $this->isVersionSupported($version)) {
            return $version;
        }
        
        // Extract from URL path
        $path = $request->getPathInfo();
        if (preg_match('#/store-api/(v\d+)/#', $path, $matches)) {
            $version = $matches[1];
            if ($this->isVersionSupported($version)) {
                return $version;
            }
        }
        
        return self::DEFAULT_VERSION;
    }
    
    private function isVersionSupported(string $version): bool
    {
        return in_array($version, self::SUPPORTED_VERSIONS, true);
    }
}

// Update controllers with versioning
#[Route('/store-api/v1/wishlist', name: 'store-api.v1.wishlist.list', methods: ['GET'])]
public function listV1(Request $request, SalesChannelContext $context): JsonResponse
{
    // V1 implementation
}

#[Route('/store-api/v2/wishlist', name: 'store-api.v2.wishlist.list', methods: ['GET'])]
public function listV2(Request $request, SalesChannelContext $context): JsonResponse
{
    // V2 implementation with enhanced features
}
```

### 4. **Enhanced Documentation**
**Current Status**: Basic Documentation
**Priority**: Medium (Developer Experience)
**Impact**: Developer onboarding and API adoption

#### Required Implementation:
```php
// src/Resources/config/api-docs/openapi.yaml
openapi: 3.0.0
info:
  title: Advanced Wishlist API
  version: 1.0.0
  description: Comprehensive wishlist management API for Shopware 6
  
paths:
  /store-api/v1/wishlist:
    get:
      summary: List customer wishlists
      parameters:
        - name: limit
          in: query
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 10
        - name: page
          in: query
          schema:
            type: integer
            minimum: 1
            default: 1
      responses:
        '200':
          description: Successful response
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/WishlistCollection'
        '401':
          $ref: '#/components/responses/Unauthorized'
        '429':
          $ref: '#/components/responses/RateLimited'

components:
  schemas:
    WishlistEntity:
      type: object
      properties:
        id:
          type: string
          format: uuid
        name:
          type: string
          maxLength: 255
        type:
          type: string
          enum: [private, public, shared]
        isDefault:
          type: boolean
        createdAt:
          type: string
          format: date-time
        
  responses:
    Unauthorized:
      description: Authentication required
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
    
    RateLimited:
      description: Rate limit exceeded
      headers:
        Retry-After:
          description: Seconds to wait before retrying
          schema:
            type: integer
```

## 🚀 PHP 8.4 Compatibility & Feature Implementation

### PHP 8.4 System Requirements for Shopware 6

**Current Shopware 6 Status**:
- **Shopware 6.5.8+**: Officially supports PHP 8.3
- **Shopware 6.6+**: Enhanced PHP 8.3 support
- **Shopware 6.7 (RC)**: Preparing for PHP 8.4 compatibility
- **PHP 8.4 Support**: Expected in Shopware 6.7 stable release (Q1 2025)

**Recommended PHP Version for Production**: PHP 8.3.14+ (Latest stable with Shopware compatibility)
**Future-Ready**: PHP 8.4+ (Expected compatibility Q1 2025)

### 1. **Property Hooks Implementation**

**Current Implementation Status**: Not Implemented
**Benefit**: Eliminates boilerplate getter/setter methods

#### Upgrade Opportunities:

```php
// Current implementation (PHP 8.3)
class WishlistEntity
{
    private string $name;
    private string $type;
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function setName(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Name cannot be empty');
        }
        if (mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Name too long');
        }
        $this->name = trim($name);
    }
    
    public function setType(string $type): void
    {
        if (!in_array($type, ['private', 'public', 'shared'], true)) {
            throw new InvalidArgumentException('Invalid type');
        }
        $this->type = $type;
    }
}

// PHP 8.4 Property Hooks Implementation
class WishlistEntity
{
    public string $name {
        get => $this->name;
        set {
            if (empty(trim($value))) {
                throw new InvalidArgumentException('Name cannot be empty');
            }
            if (mb_strlen($value) > 255) {
                throw new InvalidArgumentException('Name too long');
            }
            $this->name = trim($value);
        }
    }
    
    public string $type {
        get => $this->type;
        set {
            if (!in_array($value, ['private', 'public', 'shared'], true)) {
                throw new InvalidArgumentException('Invalid type');
            }
            $this->type = $value;
        }
    }
    
    // Virtual property using hooks
    public string $displayName {
        get => ucfirst($this->name) . ' (' . $this->type . ')';
    }
    
    // Computed property with caching
    private ?float $_totalValue = null;
    public float $totalValue {
        get {
            if ($this->_totalValue === null) {
                $this->_totalValue = $this->calculateTotalValue();
            }
            return $this->_totalValue;
        }
    }
    
    private function calculateTotalValue(): float
    {
        return array_sum(array_map(
            fn($item) => $item->getProduct()->getPrice(),
            $this->items->getElements()
        ));
    }
}
```

#### Benefits of Property Hooks:
- **Reduced Boilerplate**: Eliminates 50+ lines of getter/setter code
- **Validation Integration**: Built-in validation without separate methods
- **Virtual Properties**: Computed properties without storage overhead
- **Performance**: Direct property access with custom logic

### 2. **Asymmetric Visibility Implementation**

**Current Implementation Status**: Not Implemented
**Benefit**: Fine-grained access control for properties

#### Upgrade Opportunities:

```php
// Current implementation (PHP 8.3)
class WishlistEntity
{
    private string $id;
    private DateTime $createdAt;
    private DateTime $updatedAt;
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
    
    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
    
    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}

// PHP 8.4 Asymmetric Visibility Implementation
class WishlistEntity
{
    // Public read, private write
    public private(set) string $id;
    public private(set) DateTime $createdAt;
    
    // Public read, protected write (can be updated by service layer)
    public protected(set) DateTime $updatedAt;
    
    // Public read, internal write only
    public private(set) int $itemCount;
    public private(set) float $totalValue;
    
    // Fully public for user-modifiable properties
    public string $name;
    public string $description;
    public string $type;
    
    public function __construct(string $id, string $name, string $type)
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
        $this->itemCount = 0;
        $this->totalValue = 0.0;
    }
    
    // Internal method to update computed properties
    private function updateComputedProperties(): void
    {
        $this->updatedAt = new DateTime();
        $this->itemCount = $this->items->count();
        $this->totalValue = $this->calculateTotalValue();
    }
}

// Service layer can update protected properties
class WishlistService
{
    public function updateWishlist(WishlistEntity $wishlist, array $data): void
    {
        if (isset($data['name'])) {
            $wishlist->name = $data['name']; // Public write access
        }
        
        // This is allowed - protected(set) property
        $wishlist->updatedAt = new DateTime();
        
        // This would cause compile error - private(set) property
        // $wishlist->id = 'new-id'; // COMPILE ERROR
    }
}
```

#### Benefits of Asymmetric Visibility:
- **Data Integrity**: Prevents accidental modification of critical properties
- **API Clarity**: Clear intention of which properties can be modified
- **Security**: Reduces risk of unauthorized data modification
- **Encapsulation**: Better object-oriented design

### 3. **Lazy Objects Implementation**

**Current Implementation Status**: Not Implemented
**Benefit**: Deferred loading for performance optimization

#### Upgrade Opportunities:

```php
// Current implementation (PHP 8.3)
class WishlistService
{
    public function getWishlistWithItems(string $wishlistId, Context $context): WishlistEntity
    {
        // Always loads all data immediately
        $criteria = new Criteria([$wishlistId]);
        $criteria->addAssociation('items.product.cover');
        $criteria->addAssociation('items.product.prices');
        $criteria->addAssociation('customer');
        
        return $this->wishlistRepository->search($criteria, $context)->first();
    }
}

// PHP 8.4 Lazy Objects Implementation
class WishlistService
{
    public function getWishlistLazy(string $wishlistId, Context $context): WishlistEntity
    {
        $reflector = new ReflectionClass(WishlistEntity::class);
        
        // Create lazy proxy that defers loading until accessed
        return $reflector->newLazyProxy(
            function (WishlistEntity $proxy) use ($wishlistId, $context): void {
                // This initializer is called only when properties are accessed
                $criteria = new Criteria([$wishlistId]);
                $criteria->addAssociation('items.product.cover');
                $criteria->addAssociation('items.product.prices');
                
                $realWishlist = $this->wishlistRepository->search($criteria, $context)->first();
                
                if (!$realWishlist) {
                    throw new WishlistNotFoundException("Wishlist {$wishlistId} not found");
                }
                
                // Copy properties to proxy
                foreach (get_object_vars($realWishlist) as $property => $value) {
                    $proxy->$property = $value;
                }
            }
        );
    }
    
    public function getWishlistGhost(string $wishlistId, Context $context): WishlistEntity
    {
        $reflector = new ReflectionClass(WishlistEntity::class);
        
        // Create lazy ghost - more memory efficient
        return $reflector->newLazyGhost(
            function (WishlistEntity $ghost) use ($wishlistId, $context): void {
                // Load basic properties first
                $basicCriteria = new Criteria([$wishlistId]);
                $basicWishlist = $this->wishlistRepository->search($basicCriteria, $context)->first();
                
                if (!$basicWishlist) {
                    throw new WishlistNotFoundException("Wishlist {$wishlistId} not found");
                }
                
                // Initialize ghost with basic data
                $ghost->__construct(
                    $basicWishlist->getId(),
                    $basicWishlist->getName(),
                    $basicWishlist->getType()
                );
            }
        );
    }
    
    // Lazy loading for expensive operations
    public function getCustomerWishlistsLazy(string $customerId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(100); // Prevent memory issues
        
        $wishlists = $this->wishlistRepository->search($criteria, $context);
        $lazyWishlists = [];
        
        foreach ($wishlists as $wishlist) {
            $lazyWishlists[] = $this->createLazyWishlistWithItems($wishlist->getId(), $context);
        }
        
        return $lazyWishlists;
    }
    
    private function createLazyWishlistWithItems(string $wishlistId, Context $context): WishlistEntity
    {
        $reflector = new ReflectionClass(WishlistEntity::class);
        
        return $reflector->newLazyGhost(
            function (WishlistEntity $ghost) use ($wishlistId, $context): void {
                // Only load items when actually accessed
                $criteria = new Criteria([$wishlistId]);
                $criteria->addAssociation('items.product.cover');
                
                $fullWishlist = $this->wishlistRepository->search($criteria, $context)->first();
                $ghost->__construct($fullWishlist->getId(), $fullWishlist->getName(), $fullWishlist->getType());
            }
        );
    }
}

// Cache integration with lazy objects
class WishlistCacheService
{
    public function getCachedLazyWishlist(string $wishlistId, Context $context): WishlistEntity
    {
        return $this->get(
            "lazy_wishlist_{$wishlistId}",
            fn() => $this->wishlistService->getWishlistLazy($wishlistId, $context),
            ["wishlist-{$wishlistId}"]
        );
    }
}
```

#### Benefits of Lazy Objects:
- **Memory Efficiency**: Only loads data when accessed
- **Performance**: Faster initial object creation
- **Scalability**: Better handling of large datasets
- **Flexibility**: Can choose loading strategy per use case

### 4. **New Syntax Features Implementation**

#### Method Chaining Without Parentheses:

```php
// Current implementation (PHP 8.3)
$wishlistName = (new ReflectionClass($wishlist))->getShortName();
$repositoryName = (new ReflectionClass($this->wishlistRepository))->getName();

// PHP 8.4 Implementation
$wishlistName = new ReflectionClass($wishlist)->getShortName();
$repositoryName = new ReflectionClass($this->wishlistRepository)->getName();

// Complex chaining examples
$result = new WishlistFactory()
    ->setType('private')
    ->setName('My Wishlist')
    ->setCustomerId($customerId)
    ->build();

// Array method chaining
$wishlistIds = new ArrayCollection($wishlists)
    ->map(fn($w) => $w->getId())
    ->filter(fn($id) => !empty($id))
    ->toArray();
```

### 5. **Enhanced Type System**

```php
// PHP 8.4 Enhanced type usage
class WishlistValidator
{
    // More precise union types
    public function validateInput(string|int|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        
        return is_string($input) ? trim($input) : (string) $input;
    }
    
    // Intersection types for complex dependencies
    public function processCache(
        CacheItemPoolInterface&TagAwareAdapterInterface $cache
    ): void {
        // Can use both interfaces' methods safely
        $item = $cache->getItem('key');
        $cache->invalidateTags(['tag1', 'tag2']);
    }
}
```

## 📊 Implementation Priority Matrix

| Feature | Priority | Effort | Impact | Timeline |
|---------|----------|--------|---------|----------|
| Rate Limiting | 🔴 High | Medium | High | 1-2 days |
| Security Headers | 🟡 Medium | Low | Medium | 0.5 days |
| PHP 8.4 Property Hooks | 🟢 Low | High | High | 1-2 weeks |
| PHP 8.4 Asymmetric Visibility | 🟢 Low | Medium | Medium | 3-5 days |
| PHP 8.4 Lazy Objects | 🟢 Low | High | High | 1-2 weeks |
| API Versioning | 🟡 Medium | Medium | Medium | 2-3 days |
| Enhanced Documentation | 🟡 Medium | Medium | Low | 1 week |

## 🎯 Recommended Implementation Roadmap

### Phase 1: Critical Security (Week 1)
1. **Rate Limiting Implementation** - 2 days
2. **Security Headers** - 0.5 days
3. **Enhanced Input Validation** - 1 day
4. **Production Monitoring Setup** - 1.5 days

### Phase 2: PHP 8.4 Compatibility (Week 2-4)
1. **Shopware 6.7+ Upgrade** - 3 days
2. **Property Hooks Migration** - 1 week
3. **Asymmetric Visibility Implementation** - 3 days
4. **Lazy Objects Integration** - 1 week

### Phase 3: API Enhancement (Week 5-6)
1. **API Versioning Strategy** - 3 days
2. **Enhanced Documentation** - 1 week
3. **Performance Optimization** - 3 days

## 🔧 Development Environment Setup for PHP 8.4

### Docker Configuration:
```dockerfile
# Dockerfile for PHP 8.4 development
FROM php:8.4-fpm

# Install required extensions
RUN docker-php-ext-install pdo pdo_mysql opcache intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable PHP 8.4 specific features
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini
RUN echo "opcache.jit=1255" >> /usr/local/etc/php/conf.d/opcache.ini
RUN echo "opcache.jit_buffer_size=100M" >> /usr/local/etc/php/conf.d/opcache.ini
```

### Composer Configuration:
```json
{
    "require": {
        "php": "^8.4",
        "shopware/core": "~6.7.0",
        "symfony/framework-bundle": "~7.2"
    },
    "config": {
        "platform": {
            "php": "8.4"
        }
    }
}
```

## 📋 Final Implementation Checklist

### Before PHP 8.4 Migration:
- [ ] Complete rate limiting implementation
- [ ] Add security headers
- [ ] Upgrade to Shopware 6.7+
- [ ] Comprehensive testing on PHP 8.3
- [ ] Performance benchmarking
- [ ] Documentation updates

### PHP 8.4 Migration:
- [ ] Update composer.json requirements
- [ ] Implement property hooks for entities
- [ ] Add asymmetric visibility to critical properties
- [ ] Integrate lazy objects for performance
- [ ] Update syntax to use new features
- [ ] Comprehensive testing on PHP 8.4
- [ ] Performance comparison benchmarks

### Post-Migration:
- [ ] Production deployment
- [ ] Monitoring setup
- [ ] Performance validation
- [ ] User feedback collection
- [ ] Documentation updates
- [ ] Team training on new features

This comprehensive analysis provides a clear roadmap for bringing the AdvancedWishlist plugin to the latest PHP 8.4 standards while addressing all missing implementation requirements.