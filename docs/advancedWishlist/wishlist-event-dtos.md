# Event DTOs - Advanced Wishlist System

## Überblick

Event DTOs dienen als Transportobjekte für Events im Shopware Event System. Sie kapseln alle relevanten Daten, die bei einem bestimmten Ereignis übertragen werden müssen, und ermöglichen eine typensichere Kommunikation zwischen verschiedenen System-Komponenten.

## Base Event DTO

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;

abstract class AbstractEventDTO implements ShopwareEvent
{
    protected Context $context;
    protected array $data = [];
    protected \DateTimeImmutable $timestamp;

    public function __construct(Context $context, array $data = [])
    {    
        $this->context = $context;
        $this->data = $data;
        $this->timestamp = new \DateTimeImmutable();
    }

    public function getContext(): Context
    {    
        return $this->context;
    }

    public function getData(): array
    {    
        return $this->data;
    }

    public function getTimestamp(): \DateTimeImmutable
    {    
        return $this->timestamp;
    }

    public function getName(): string
    {    
        return static::class;
    }
}
```

## Wishlist Event DTOs

### WishlistCreatedEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Framework\Context;

class WishlistCreatedEvent extends AbstractEventDTO
{
    private WishlistEntity $wishlist;
    private string $wishlistId;
    private string $customerId;

    public function __construct(
        WishlistEntity $wishlist,
        Context $context
    ) {
        parent::__construct($context);

        $this->wishlist = $wishlist;
        $this->wishlistId = $wishlist->getId();
        $this->customerId = $wishlist->getCustomerId();

        $this->data = [
            'wishlistId' => $this->wishlistId,
            'customerId' => $this->customerId,
            'name' => $wishlist->getName(),
            'type' => $wishlist->getType(),
        ];
    }

    public function getWishlist(): WishlistEntity
    {
        return $this->wishlist;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }
}
```

### WishlistUpdatedEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Framework\Context;

class WishlistUpdatedEvent extends AbstractEventDTO
{
    private WishlistEntity $wishlist;
    private string $wishlistId;
    private array $changedFields;

    public function __construct(
        WishlistEntity $wishlist,
        array $changedFields,
        Context $context
    ) {
        parent::__construct($context);

        $this->wishlist = $wishlist;
        $this->wishlistId = $wishlist->getId();
        $this->changedFields = $changedFields;

        $this->data = [
            'wishlistId' => $this->wishlistId,
            'customerId' => $wishlist->getCustomerId(),
            'changedFields' => $this->changedFields,
        ];
    }

    public function getWishlist(): WishlistEntity
    {
        return $this->wishlist;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getChangedFields(): array
    {
        return $this->changedFields;
    }
}
```

### WishlistDeletedEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use Shopware\Core\Framework\Context;

class WishlistDeletedEvent extends AbstractEventDTO
{
    private string $wishlistId;
    private string $customerId;
    private ?string $transferredToWishlistId;

    public function __construct(
        string $wishlistId,
        string $customerId,
        ?string $transferredToWishlistId,
        Context $context
    ) {
        parent::__construct($context);

        $this->wishlistId = $wishlistId;
        $this->customerId = $customerId;
        $this->transferredToWishlistId = $transferredToWishlistId;

        $this->data = [
            'wishlistId' => $this->wishlistId,
            'customerId' => $this->customerId,
            'transferredToWishlistId' => $this->transferredToWishlistId,
        ];
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getTransferredToWishlistId(): ?string
    {
        return $this->transferredToWishlistId;
    }
}
```

## Wishlist Item Event DTOs

### WishlistItemAddedEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemEntity;
use Shopware\Core\Framework\Context;

class WishlistItemAddedEvent extends AbstractEventDTO
{
    private WishlistItemEntity $item;
    private string $itemId;
    private string $wishlistId;
    private string $productId;

    public function __construct(
        WishlistItemEntity $item,
        Context $context
    ) {
        parent::__construct($context);

        $this->item = $item;
        $this->itemId = $item->getId();
        $this->wishlistId = $item->getWishlistId();
        $this->productId = $item->getProductId();

        $this->data = [
            'itemId' => $this->itemId,
            'wishlistId' => $this->wishlistId,
            'productId' => $this->productId,
            'quantity' => $item->getQuantity(),
        ];
    }

    public function getItem(): WishlistItemEntity
    {
        return $this->item;
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }
}
```

### WishlistItemUpdatedEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemEntity;
use Shopware\Core\Framework\Context;

class WishlistItemUpdatedEvent extends AbstractEventDTO
{
    private WishlistItemEntity $item;
    private string $itemId;
    private string $wishlistId;
    private array $changedFields;

    public function __construct(
        WishlistItemEntity $item,
        array $changedFields,
        Context $context
    ) {
        parent::__construct($context);

        $this->item = $item;
        $this->itemId = $item->getId();
        $this->wishlistId = $item->getWishlistId();
        $this->changedFields = $changedFields;

        $this->data = [
            'itemId' => $this->itemId,
            'wishlistId' => $this->wishlistId,
            'productId' => $item->getProductId(),
            'changedFields' => $this->changedFields,
        ];
    }

    public function getItem(): WishlistItemEntity
    {
        return $this->item;
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getChangedFields(): array
    {
        return $this->changedFields;
    }
}
```

### WishlistItemRemovedEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use Shopware\Core\Framework\Context;

class WishlistItemRemovedEvent extends AbstractEventDTO
{
    private string $itemId;
    private string $wishlistId;
    private string $productId;

    public function __construct(
        string $itemId,
        string $wishlistId,
        string $productId,
        Context $context
    ) {
        parent::__construct($context);

        $this->itemId = $itemId;
        $this->wishlistId = $wishlistId;
        $this->productId = $productId;

        $this->data = [
            'itemId' => $this->itemId,
            'wishlistId' => $this->wishlistId,
            'productId' => $this->productId,
        ];
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }
}
```

### WishlistItemMovedEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use Shopware\Core\Framework\Context;

class WishlistItemMovedEvent extends AbstractEventDTO
{
    private string $itemId;
    private string $sourceWishlistId;
    private string $targetWishlistId;
    private string $productId;
    private bool $copied;

    public function __construct(
        string $itemId,
        string $sourceWishlistId,
        string $targetWishlistId,
        string $productId,
        bool $copied,
        Context $context
    ) {
        parent::__construct($context);

        $this->itemId = $itemId;
        $this->sourceWishlistId = $sourceWishlistId;
        $this->targetWishlistId = $targetWishlistId;
        $this->productId = $productId;
        $this->copied = $copied;

        $this->data = [
            'itemId' => $this->itemId,
            'sourceWishlistId' => $this->sourceWishlistId,
            'targetWishlistId' => $this->targetWishlistId,
            'productId' => $this->productId,
            'copied' => $this->copied,
        ];
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getSourceWishlistId(): string
    {
        return $this->sourceWishlistId;
    }

    public function getTargetWishlistId(): string
    {
        return $this->targetWishlistId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function isCopied(): bool
    {
        return $this->copied;
    }
}
```

## Sharing Event DTOs

### WishlistSharedEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareEntity;
use Shopware\Core\Framework\Context;

class WishlistSharedEvent extends AbstractEventDTO
{
    private WishlistShareEntity $share;
    private string $shareId;
    private string $wishlistId;
    private string $shareMethod;
    private ?string $recipientEmail;

    public function __construct(
        WishlistShareEntity $share,
        Context $context
    ) {
        parent::__construct($context);

        $this->share = $share;
        $this->shareId = $share->getId();
        $this->wishlistId = $share->getWishlistId();
        $this->shareMethod = $share->getShareMethod();
        $this->recipientEmail = $share->getRecipientEmail();

        $this->data = [
            'shareId' => $this->shareId,
            'wishlistId' => $this->wishlistId,
            'shareMethod' => $this->shareMethod,
            'recipientEmail' => $this->recipientEmail,
            'shareToken' => $share->getShareToken(),
            'expiresAt' => $share->getExpiresAt()?->format('c'),
        ];
    }

    public function getShare(): WishlistShareEntity
    {
        return $this->share;
    }

    public function getShareId(): string
    {
        return $this->shareId;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getShareMethod(): string
    {
        return $this->shareMethod;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }
}
```

### WishlistShareAccessedEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use Shopware\Core\Framework\Context;

class WishlistShareAccessedEvent extends AbstractEventDTO
{
    private string $shareId;
    private string $wishlistId;
    private string $shareToken;
    private ?string $visitorId;
    private ?string $referrer;

    public function __construct(
        string $shareId,
        string $wishlistId,
        string $shareToken,
        ?string $visitorId,
        ?string $referrer,
        Context $context
    ) {
        parent::__construct($context);

        $this->shareId = $shareId;
        $this->wishlistId = $wishlistId;
        $this->shareToken = $shareToken;
        $this->visitorId = $visitorId;
        $this->referrer = $referrer;

        $this->data = [
            'shareId' => $this->shareId,
            'wishlistId' => $this->wishlistId,
            'shareToken' => $this->shareToken,
            'visitorId' => $this->visitorId,
            'referrer' => $this->referrer,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];
    }

    public function getShareId(): string
    {
        return $this->shareId;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getShareToken(): string
    {
        return $this->shareToken;
    }

    public function getVisitorId(): ?string
    {
        return $this->visitorId;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }
}
```

## Price Monitoring Event DTOs

### PriceAlertTriggeredEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use Shopware\Core\Framework\Context;

class PriceAlertTriggeredEvent extends AbstractEventDTO
{
    private string $itemId;
    private string $wishlistId;
    private string $customerId;
    private string $productId;
    private float $oldPrice;
    private float $newPrice;
    private float $threshold;
    private float $discountPercentage;

    public function __construct(
        string $itemId,
        string $wishlistId,
        string $customerId,
        string $productId,
        float $oldPrice,
        float $newPrice,
        float $threshold,
        Context $context
    ) {
        parent::__construct($context);

        $this->itemId = $itemId;
        $this->wishlistId = $wishlistId;
        $this->customerId = $customerId;
        $this->productId = $productId;
        $this->oldPrice = $oldPrice;
        $this->newPrice = $newPrice;
        $this->threshold = $threshold;
        $this->discountPercentage = ($oldPrice > 0) ? round((($oldPrice - $newPrice) / $oldPrice) * 100, 2) : 0;

        $this->data = [
            'itemId' => $this->itemId,
            'wishlistId' => $this->wishlistId,
            'customerId' => $this->customerId,
            'productId' => $this->productId,
            'oldPrice' => $this->oldPrice,
            'newPrice' => $this->newPrice,
            'threshold' => $this->threshold,
            'discountPercentage' => $this->discountPercentage,
        ];
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getOldPrice(): float
    {
        return $this->oldPrice;
    }

    public function getNewPrice(): float
    {
        return $this->newPrice;
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    public function getDiscountPercentage(): float
    {
        return $this->discountPercentage;
    }
}
```

## Analytics Event DTOs

### WishlistAnalyticsEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use Shopware\Core\Framework\Context;

class WishlistAnalyticsEvent extends AbstractEventDTO
{
    private string $eventType;
    private ?string $wishlistId;
    private ?string $customerId;
    private ?string $productId;
    private array $additionalData;

    public function __construct(
        string $eventType,
        ?string $wishlistId,
        ?string $customerId,
        ?string $productId,
        array $additionalData,
        Context $context
    ) {
        parent::__construct($context);

        $this->eventType = $eventType;
        $this->wishlistId = $wishlistId;
        $this->customerId = $customerId;
        $this->productId = $productId;
        $this->additionalData = $additionalData;

        $this->data = array_merge([
            'eventType' => $this->eventType,
            'wishlistId' => $this->wishlistId,
            'customerId' => $this->customerId,
            'productId' => $this->productId,
        ], $this->additionalData);
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getWishlistId(): ?string
    {
        return $this->wishlistId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }
}
```

### WishlistToCartEvent

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\DTO\Event;

use Shopware\Core\Framework\Context;

class WishlistToCartEvent extends AbstractEventDTO
{
    private string $wishlistId;
    private ?string $customerId;
    private array $productIds;
    private string $cartToken;
    private bool $removeFromWishlist;

    public function __construct(
        string $wishlistId,
        ?string $customerId,
        array $productIds,
        string $cartToken,
        bool $removeFromWishlist,
        Context $context
    ) {
        parent::__construct($context);

        $this->wishlistId = $wishlistId;
        $this->customerId = $customerId;
        $this->productIds = $productIds;
        $this->cartToken = $cartToken;
        $this->removeFromWishlist = $removeFromWishlist;

        $this->data = [
            'wishlistId' => $this->wishlistId,
            'customerId' => $this->customerId,
            'productIds' => $this->productIds,
            'productCount' => count($this->productIds),
            'cartToken' => $this->cartToken,
            'removeFromWishlist' => $this->removeFromWishlist,
        ];
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function getProductIds(): array
    {
        return $this->productIds;
    }

    public function getCartToken(): string
    {
        return $this->cartToken;
    }

    public function isRemoveFromWishlist(): bool
    {
        return $this->removeFromWishlist;
    }
}
```

## Event Subscriber Beispiel

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Subscriber;

use AdvancedWishlist\Core\DTO\Event\WishlistCreatedEvent;
use AdvancedWishlist\Core\DTO\Event\WishlistSharedEvent;
use AdvancedWishlist\Core\DTO\Event\WishlistToCartEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WishlistEventSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WishlistCreatedEvent::class => 'onWishlistCreated',
            WishlistSharedEvent::class => 'onWishlistShared',
            WishlistToCartEvent::class => 'onWishlistToCart',
        ];
    }

    public function onWishlistCreated(WishlistCreatedEvent $event): void
    {
        $this->logger->info('Wishlist created', [
            'wishlistId' => $event->getWishlistId(),
            'customerId' => $event->getCustomerId(),
        ]);

        // Weitere Verarbeitung...
    }

    public function onWishlistShared(WishlistSharedEvent $event): void
    {
        $this->logger->info('Wishlist shared', [
            'wishlistId' => $event->getWishlistId(),
            'shareMethod' => $event->getShareMethod(),
        ]);

        // Analytics tracking, E-Mail-Benachrichtigungen, etc.
    }

    public function onWishlistToCart(WishlistToCartEvent $event): void
    {
        $this->logger->info('Wishlist items added to cart', [
            'wishlistId' => $event->getWishlistId(),
            'productCount' => count($event->getProductIds()),
        ]);

        // Conversion tracking
    }
}
```

## Event-Versand Beispiel

```php
<?php declare(strict_types=1);  

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\DTO\Event\WishlistCreatedEvent;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class WishlistService
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function createWishlist(CreateWishlistRequest $request, Context $context): WishlistEntity
    {
        // Wishlist erstellen Logik...

        // Event erstellen und versenden
        $event = new WishlistCreatedEvent($wishlist, $context);
        $this->eventDispatcher->dispatch($event);

        return $wishlist;
    }
}
```
