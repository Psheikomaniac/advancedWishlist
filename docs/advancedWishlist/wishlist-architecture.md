# System Architecture - Advanced Wishlist System

## Overview

Das Advanced Wishlist System folgt einer **Domain-Driven Design (DDD)** Architektur mit klarer Trennung von Concerns. Die Implementierung nutzt **Shopware 6's Plugin-System** und integriert sich nahtlos in die bestehende Architektur.

## Architecture Layers

```
┌─────────────────────────────────────────────────────────┐
│                   Presentation Layer                     │
│         (Vue.js Components, Twig Templates)              │
├─────────────────────────────────────────────────────────┤
│                    Application Layer                     │
│      (Controllers, API Endpoints, Event Handlers)        │
├─────────────────────────────────────────────────────────┤
│                     Domain Layer                         │
│        (Services, Business Logic, Validators)            │
├─────────────────────────────────────────────────────────┤
│                  Infrastructure Layer                    │
│      (Repositories, Database, Cache, External APIs)      │
└─────────────────────────────────────────────────────────┘
```

## Component Architecture

### Backend Components

```
src/
├── Core/
│   ├── Content/
│   │   ├── Wishlist/
│   │   │   ├── Aggregate/
│   │   │   │   ├── WishlistItem/
│   │   │   │   │   ├── WishlistItemDefinition.php
│   │   │   │   │   ├── WishlistItemEntity.php
│   │   │   │   │   └── WishlistItemCollection.php
│   │   │   │   └── WishlistShare/
│   │   │   │       ├── WishlistShareDefinition.php
│   │   │   │       ├── WishlistShareEntity.php
│   │   │   │       └── WishlistShareCollection.php
│   │   │   ├── WishlistDefinition.php
│   │   │   ├── WishlistEntity.php
│   │   │   ├── WishlistCollection.php
│   │   │   └── WishlistHydrator.php
│   │   └── Analytics/
│   │       ├── WishlistAnalyticsDefinition.php
│   │       └── WishlistAnalyticsEntity.php
│   ├── Service/
│   │   ├── WishlistService.php
│   │   ├── WishlistShareService.php
│   │   ├── WishlistPriceMonitorService.php
│   │   ├── WishlistNotificationService.php
│   │   └── WishlistAnalyticsService.php
│   ├── Api/
│   │   ├── Controller/
│   │   │   ├── WishlistController.php
│   │   │   ├── WishlistShareController.php
│   │   │   └── WishlistAnalyticsController.php
│   │   └── Route/
│   │       ├── AbstractWishlistRoute.php
│   │       └── WishlistRoute.php
│   ├── DTO/
│   │   ├── Request/
│   │   │   ├── CreateWishlistRequest.php
│   │   │   ├── UpdateWishlistRequest.php
│   │   │   ├── AddItemRequest.php
│   │   │   └── ShareWishlistRequest.php
│   │   ├── Response/
│   │   │   ├── WishlistResponse.php
│   │   │   ├── WishlistItemResponse.php
│   │   │   └── WishlistShareResponse.php
│   │   └── Transformer/
│   │       ├── WishlistTransformer.php
│   │       └── WishlistItemTransformer.php
│   └── Event/
│       ├── WishlistCreatedEvent.php
│       ├── WishlistItemAddedEvent.php
│       ├── WishlistSharedEvent.php
│       └── PriceDropDetectedEvent.php
├── Storefront/
│   ├── Controller/
│   │   ├── WishlistController.php
│   │   └── WishlistShareController.php
│   ├── Page/
│   │   ├── Wishlist/
│   │   │   ├── WishlistPageLoader.php
│   │   │   └── WishlistPage.php
│   │   └── Account/
│   │       └── WishlistOverviewPageLoader.php
│   ├── Pagelet/
│   │   └── Wishlist/
│   │       ├── WishlistWidgetPageletLoader.php
│   │       └── WishlistWidgetPagelet.php
│   └── Subscriber/
│       ├── WishlistSubscriber.php
│       └── ProductPageSubscriber.php
└── Administration/
    └── module/
        └── sw-wishlist/
            ├── page/
            ├── component/
            └── service/
```

## Service Layer Architecture

### WishlistService (Core Business Logic)

```php
namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Response\WishlistResponse;

class WishlistService
{
    private EntityRepository $wishlistRepository;
    private WishlistValidator $validator;
    private EventDispatcher $eventDispatcher;
    private WishlistTransformer $transformer;
    
    public function createWishlist(
        CreateWishlistRequest $request,
        Context $context
    ): WishlistResponse {
        // 1. Validate request
        $this->validator->validateCreateRequest($request);
        
        // 2. Business logic
        $data = [
            'customerId' => $request->getCustomerId(),
            'name' => $request->getName(),
            'type' => $request->getType(),
            'isDefault' => $request->isDefault(),
        ];
        
        // 3. Persist
        $this->wishlistRepository->create([$data], $context);
        
        // 4. Dispatch event
        $this->eventDispatcher->dispatch(
            new WishlistCreatedEvent($wishlist, $context)
        );
        
        // 5. Transform to response DTO
        return $this->transformer->transform($wishlist);
    }
}
```

## Data Flow

### Request Flow

```
Client Request
    ↓
API Controller (Validation via DTO)
    ↓
Service Layer (Business Logic)
    ↓
Repository Layer (Data Access)
    ↓
Database
    ↓
Response DTO
    ↓
Client Response
```

### Event Flow

```
User Action
    ↓
Domain Event Created
    ↓
Event Dispatcher
    ↓
Event Subscribers
    ├── Analytics Logger
    ├── Cache Invalidator
    ├── Notification Sender
    └── External Integrations
```

## Caching Strategy

### Cache Layers

1. **HTTP Cache** (Varnish/Cloudflare)
   - Public wishlist pages
   - Share links
   - Static assets

2. **Application Cache** (Redis)
   - User wishlists
   - Product availability
   - Price data

3. **Database Cache** (Query Cache)
   - Frequent queries
   - Aggregated data

### Cache Keys

```php
// User wishlist cache
$cacheKey = sprintf('wishlist.user.%s', $userId);

// Shared wishlist cache  
$cacheKey = sprintf('wishlist.share.%s', $shareToken);

// Analytics cache
$cacheKey = sprintf('wishlist.analytics.%s.%s', $metric, $period);
```

## Security Architecture

### Authentication & Authorization

```php
namespace AdvancedWishlist\Core\Security;

class WishlistPermissionValidator
{
    public function canView(WishlistEntity $wishlist, ?CustomerEntity $customer): bool
    {
        // Public wishlists
        if ($wishlist->getType() === WishlistType::PUBLIC) {
            return true;
        }
        
        // Own wishlists
        if ($customer && $wishlist->getCustomerId() === $customer->getId()) {
            return true;
        }
        
        // Shared wishlists
        if ($this->hasValidShareToken($wishlist)) {
            return true;
        }
        
        return false;
    }
}
```

### Data Protection

1. **Input Validation**: DTO-based validation
2. **SQL Injection**: Prepared statements via Doctrine
3. **XSS Prevention**: Output escaping in templates
4. **CSRF Protection**: Shopware's built-in CSRF tokens
5. **Rate Limiting**: API endpoint throttling

## Performance Optimizations

### Database Optimizations

```sql
-- Composite indexes for common queries
CREATE INDEX idx_wishlist_customer_type 
ON wishlist(customer_id, type, created_at);

CREATE INDEX idx_wishlist_item_product 
ON wishlist_item(wishlist_id, product_id);

-- Partial index for active price alerts
CREATE INDEX idx_price_alert_active 
ON wishlist_item(product_id, price_alert_threshold) 
WHERE price_alert_active = 1;
```

### Query Optimization

```php
// Optimized wishlist loading with relations
public function loadWishlistWithItems(string $wishlistId, Context $context): ?WishlistEntity
{
    $criteria = new Criteria([$wishlistId]);
    
    // Eager load associations
    $criteria->addAssociation('items.product.cover');
    $criteria->addAssociation('items.product.prices');
    
    // Add computed fields
    $criteria->addAggregation(
        new CountAggregation('item_count', 'items.id')
    );
    
    return $this->wishlistRepository->search($criteria, $context)->first();
}
```

## Scalability Considerations

### Horizontal Scaling

1. **Stateless Services**: No session dependencies
2. **Distributed Cache**: Redis Cluster
3. **Read Replicas**: For analytics queries
4. **Queue Workers**: Multiple consumer instances

### Vertical Scaling

1. **Connection Pooling**: Optimized DB connections
2. **Lazy Loading**: On-demand data fetching
3. **Batch Processing**: Bulk operations
4. **Async Operations**: Non-blocking I/O

## Integration Points

### Shopware Core Integration

```php
// Product subscriber for wishlist button
class ProductPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }
    
    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $context = $event->getSalesChannelContext();
        
        // Add wishlist data to product page
        $wishlistData = $this->wishlistService->getProductWishlistData(
            $page->getProduct()->getId(),
            $context->getCustomer()?->getId()
        );
        
        $page->addExtension('wishlist', $wishlistData);
    }
}
```

### External Service Integration

1. **Email Service**: Transactional emails via Shopware's mail service
2. **Analytics**: Google Analytics / Matomo integration
3. **Social Platforms**: OAuth for social sharing
4. **Payment**: No direct integration needed

## Monitoring & Logging

### Application Metrics

```php
// Prometheus metrics example
class WishlistMetrics
{
    private Counter $wishlistCreatedCounter;
    private Histogram $apiResponseTime;
    private Gauge $activeWishlists;
    
    public function recordWishlistCreated(): void
    {
        $this->wishlistCreatedCounter->inc([
            'type' => 'private'
        ]);
    }
}
```

### Structured Logging

```php
// Monolog implementation
$this->logger->info('Wishlist created', [
    'wishlist_id' => $wishlist->getId(),
    'customer_id' => $wishlist->getCustomerId(),
    'item_count' => count($wishlist->getItems()),
    'context' => [
        'channel' => $context->getSalesChannel()->getName(),
        'locale' => $context->getLanguageId(),
    ]
]);
```

## Error Handling

### Exception Hierarchy

```php
namespace AdvancedWishlist\Core\Exception;

// Base exception
class WishlistException extends ShopwareHttpException {}

// Specific exceptions
class WishlistNotFoundException extends WishlistException {}
class WishlistLimitExceededException extends WishlistException {}
class InvalidShareTokenException extends WishlistException {}
class DuplicateWishlistItemException extends WishlistException {}
```

### Error Response Format

```json
{
    "errors": [
        {
            "status": "404",
            "code": "WISHLIST__NOT_FOUND",
            "title": "Wishlist not found",
            "detail": "The wishlist with id 'xyz' could not be found",
            "meta": {
                "parameters": {
                    "wishlistId": "xyz"
                }
            }
        }
    ]
}
```

## Development Guidelines

### Code Organization

1. **Single Responsibility**: Each class has one clear purpose
2. **Dependency Injection**: All dependencies injected via constructor
3. **Interface Segregation**: Small, focused interfaces
4. **Open/Closed Principle**: Extensible via events and plugins

### Testing Strategy

1. **Unit Tests**: Service layer, DTOs, Validators
2. **Integration Tests**: API endpoints, Database operations
3. **E2E Tests**: Critical user journeys
4. **Performance Tests**: Load testing for scale

### Documentation Standards

1. **PHPDoc**: All public methods documented
2. **OpenAPI**: API endpoints documented
3. **ADRs**: Architecture Decision Records
4. **README**: Setup and configuration guide