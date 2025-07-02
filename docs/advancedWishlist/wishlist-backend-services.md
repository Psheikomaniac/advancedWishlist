# Backend Services Documentation - Advanced Wishlist System

## Overview

Die Service Layer implementiert die gesamte Business Logic des Wishlist Systems. Services sind zustandslos, testbar und folgen dem Single Responsibility Principle.

## Core Services

### WishlistService

Der zentrale Service für alle Wishlist-Operationen.

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\DTO\Response\WishlistResponse;
use AdvancedWishlist\Core\Entity\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Event\WishlistCreatedEvent;
use AdvancedWishlist\Core\Exception\WishlistException;
use AdvancedWishlist\Core\Repository\WishlistRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\EventDispatcherInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

class WishlistService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const MAX_WISHLISTS_PER_CUSTOMER = 10;
    private const MAX_ITEMS_PER_WISHLIST = 100;
    
    public function __construct(
        private WishlistRepository $wishlistRepository,
        private WishlistValidator $validator,
        private WishlistLimitService $limitService,
        private WishlistCacheService $cacheService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache
    ) {}
    
    /**
     * Create a new wishlist with validation and limits
     */
    public function createWishlist(
        CreateWishlistRequest $request,
        Context $context
    ): WishlistResponse {
        // Start transaction
        $this->wishlistRepository->beginTransaction();
        
        try {
            // 1. Validate request
            $this->validator->validateCreateRequest($request, $context);
            
            // 2. Check customer limits
            $this->checkCustomerLimits($request->getCustomerId(), $context);
            
            // 3. Handle default wishlist
            if ($request->isDefault()) {
                $this->unsetExistingDefaultWishlist($request->getCustomerId(), $context);
            }
            
            // 4. Create wishlist
            $wishlistId = Uuid::randomHex();
            $wishlist = $this->createWishlistEntity($wishlistId, $request, $context);
            
            // 5. Dispatch event
            $event = new WishlistCreatedEvent($wishlist, $context);
            $this->eventDispatcher->dispatch($event);
            
            // 6. Clear cache
            $this->cacheService->invalidateCustomerCache($request->getCustomerId());
            
            // 7. Log creation
            $this->logger->info('Wishlist created', [
                'wishlistId' => $wishlistId,
                'customerId' => $request->getCustomerId(),
                'name' => $request->getName(),
                'type' => $request->getType(),
            ]);
            
            // Commit transaction
            $this->wishlistRepository->commit();
            
            return WishlistResponse::fromEntity($wishlist);
            
        } catch (\Exception $e) {
            // Rollback transaction
            $this->wishlistRepository->rollback();
            
            $this->logger->error('Failed to create wishlist', [
                'error' => $e->getMessage(),
                'request' => $request->toArray(),
            ]);
            
            throw new WishlistException(
                'Failed to create wishlist: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Update existing wishlist with optimistic locking
     */
    public function updateWishlist(
        UpdateWishlistRequest $request,
        Context $context
    ): WishlistResponse {
        // 1. Load wishlist with lock
        $wishlist = $this->wishlistRepository->findWithLock(
            $request->getWishlistId(),
            $context
        );
        
        if (!$wishlist) {
            throw new WishlistNotFoundException(
                'Wishlist not found',
                ['wishlistId' => $request->getWishlistId()]
            );
        }
        
        // 2. Validate ownership and permissions
        $this->validator->validateOwnership($wishlist, $context);
        $this->validator->validateUpdateRequest($request, $wishlist, $context);
        
        // 3. Check version for optimistic locking
        if ($request->getVersion() && $request->getVersion() !== $wishlist->getVersion()) {
            throw new OptimisticLockException(
                'Wishlist was modified by another process',
                ['currentVersion' => $wishlist->getVersion()]
            );
        }
        
        // 4. Apply updates
        $updateData = $this->prepareUpdateData($request, $wishlist);
        
        if (empty($updateData)) {
            return WishlistResponse::fromEntity($wishlist);
        }
        
        // 5. Update wishlist
        $this->wishlistRepository->update([$updateData], $context);
        
        // 6. Reload wishlist
        $updatedWishlist = $this->loadWishlist($request->getWishlistId(), $context);
        
        // 7. Clear cache
        $this->cacheService->invalidateWishlistCache($request->getWishlistId());
        
        // 8. Dispatch event
        $event = new WishlistUpdatedEvent($wishlist, $updatedWishlist, $context);
        $this->eventDispatcher->dispatch($event);
        
        return WishlistResponse::fromEntity($updatedWishlist);
    }
    
    /**
     * Delete wishlist with optional item transfer
     */
    public function deleteWishlist(
        string $wishlistId,
        ?string $transferToWishlistId,
        Context $context
    ): void {
        $wishlist = $this->loadWishlist($wishlistId, $context);
        $this->validator->validateOwnership($wishlist, $context);
        
        // Prevent deleting default wishlist if it's the only one
        if ($wishlist->isDefault() && $this->isOnlyWishlist($wishlist, $context)) {
            throw new CannotDeleteDefaultWishlistException(
                'Cannot delete the only wishlist'
            );
        }
        
        // Transfer items if requested
        if ($transferToWishlistId && $wishlist->getItems()->count() > 0) {
            $this->transferItems($wishlist, $transferToWishlistId, $context);
        }
        
        // Delete wishlist
        $this->wishlistRepository->delete([['id' => $wishlistId]], $context);
        
        // Clear cache
        $this->cacheService->invalidateCustomerCache($wishlist->getCustomerId());
        
        // Dispatch event
        $event = new WishlistDeletedEvent($wishlist, $context);
        $this->eventDispatcher->dispatch($event);
        
        $this->logger->info('Wishlist deleted', [
            'wishlistId' => $wishlistId,
            'itemsTransferred' => $transferToWishlistId !== null,
        ]);
    }
    
    /**
     * Get or create default wishlist for customer
     */
    public function getOrCreateDefaultWishlist(
        string $customerId,
        Context $context
    ): WishlistEntity {
        // Try cache first
        $cacheKey = sprintf('wishlist.default.%s', $customerId);
        $cached = $this->cache->getItem($cacheKey);
        
        if ($cached->isHit()) {
            return $cached->get();
        }
        
        // Find existing default wishlist
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addFilter(new EqualsFilter('isDefault', true));
        $criteria->setLimit(1);
        
        $result = $this->wishlistRepository->search($criteria, $context);
        
        if ($result->getTotal() > 0) {
            $wishlist = $result->first();
        } else {
            // Create default wishlist
            $request = new CreateWishlistRequest();
            $request->setCustomerId($customerId);
            $request->setName('Meine Wunschliste');
            $request->setType('private');
            $request->setIsDefault(true);
            
            $response = $this->createWishlist($request, $context);
            $wishlist = $this->loadWishlist($response->getId(), $context);
        }
        
        // Cache result
        $cached->set($wishlist);
        $cached->expiresAfter(self::CACHE_TTL);
        $this->cache->save($cached);
        
        return $wishlist;
    }
    
    /**
     * Check if product is in any customer wishlist
     */
    public function isProductInWishlist(
        string $customerId,
        string $productId,
        Context $context
    ): array {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addAssociation('items');
        
        $wishlists = $this->wishlistRepository->search($criteria, $context);
        
        $result = [
            'inWishlist' => false,
            'wishlists' => [],
        ];
        
        foreach ($wishlists as $wishlist) {
            $item = $wishlist->getItems()->filter(
                fn($item) => $item->getProductId() === $productId
            )->first();
            
            if ($item) {
                $result['inWishlist'] = true;
                $result['wishlists'][] = [
                    'id' => $wishlist->getId(),
                    'name' => $wishlist->getName(),
                    'itemId' => $item->getId(),
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Get wishlist statistics for customer
     */
    public function getCustomerStatistics(
        string $customerId,
        Context $context
    ): array {
        $stats = $this->wishlistRepository->getCustomerStatistics($customerId, $context);
        
        return [
            'totalWishlists' => $stats['wishlist_count'],
            'totalItems' => $stats['total_items'],
            'totalValue' => $stats['total_value'],
            'topCategories' => $this->getTopCategories($customerId, $context),
            'priceAlerts' => [
                'active' => $stats['active_price_alerts'],
                'triggered' => $stats['triggered_price_alerts'],
            ],
            'recentActivity' => $this->getRecentActivity($customerId, $context),
        ];
    }
    
    /**
     * Helper: Check customer limits
     */
    private function checkCustomerLimits(string $customerId, Context $context): void
    {
        $count = $this->wishlistRepository->countByCustomerId($customerId, $context);
        
        if ($count >= self::MAX_WISHLISTS_PER_CUSTOMER) {
            throw new WishlistLimitExceededException(
                'Maximum number of wishlists reached',
                [
                    'limit' => self::MAX_WISHLISTS_PER_CUSTOMER,
                    'current' => $count,
                ]
            );
        }
    }
    
    /**
     * Helper: Create wishlist entity
     */
    private function createWishlistEntity(
        string $wishlistId,
        CreateWishlistRequest $request,
        Context $context
    ): WishlistEntity {
        $data = [
            'id' => $wishlistId,
            'customerId' => $request->getCustomerId(),
            'name' => $request->getName(),
            'description' => $request->getDescription(),
            'type' => $request->getType(),
            'isDefault' => $request->isDefault(),
            'salesChannelId' => $context->getSource()->getSalesChannelId(),
            'languageId' => $context->getLanguageId(),
        ];
        
        $this->wishlistRepository->create([$data], $context);
        
        return $this->loadWishlist($wishlistId, $context);
    }
    
    /**
     * Helper: Load wishlist with associations
     */
    private function loadWishlist(string $wishlistId, Context $context): WishlistEntity
    {
        $criteria = new Criteria([$wishlistId]);
        $criteria->addAssociation('items.product.cover');
        $criteria->addAssociation('items.product.prices');
        $criteria->addAssociation('customer');
        $criteria->addAssociation('shareInfo');
        
        $wishlist = $this->wishlistRepository->search($criteria, $context)->first();
        
        if (!$wishlist) {
            throw new WishlistNotFoundException(
                'Wishlist not found',
                ['wishlistId' => $wishlistId]
            );
        }
        
        return $wishlist;
    }
}
```

### WishlistItemService

Service für die Verwaltung von Wishlist-Items.

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\AddItemRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateItemRequest;
use AdvancedWishlist\Core\DTO\Response\WishlistItemResponse;
use AdvancedWishlist\Core\Event\WishlistItemAddedEvent;
use AdvancedWishlist\Core\Exception\DuplicateWishlistItemException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class WishlistItemService
{
    private const BATCH_SIZE = 50;
    
    public function __construct(
        private EntityRepository $wishlistRepository,
        private EntityRepository $wishlistItemRepository,
        private EntityRepository $productRepository,
        private WishlistValidator $validator,
        private ProductAvailabilityService $availabilityService,
        private PriceAlertService $priceAlertService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Add item to wishlist with duplicate check
     */
    public function addItem(
        AddItemRequest $request,
        Context $context
    ): WishlistItemResponse {
        // 1. Load and validate wishlist
        $wishlist = $this->loadWishlist($request->getWishlistId(), $context);
        $this->validator->validateOwnership($wishlist, $context);
        
        // 2. Check item limit
        $this->checkItemLimit($wishlist);
        
        // 3. Validate product
        $product = $this->validateProduct($request->getProductId(), $context);
        
        // 4. Check for duplicates
        if ($this->isDuplicate($wishlist, $request->getProductId())) {
            throw new DuplicateWishlistItemException(
                'Product already in wishlist',
                [
                    'productId' => $request->getProductId(),
                    'wishlistId' => $request->getWishlistId(),
                ]
            );
        }
        
        // 5. Create item
        $itemId = Uuid::randomHex();
        $itemData = $this->prepareItemData($itemId, $request, $product, $context);
        
        $this->wishlistItemRepository->create([$itemData], $context);
        
        // 6. Setup price alert if requested
        if ($request->getPriceAlertThreshold()) {
            $this->priceAlertService->setupAlert(
                $itemId,
                $product,
                $request->getPriceAlertThreshold(),
                $context
            );
        }
        
        // 7. Dispatch event
        $event = new WishlistItemAddedEvent(
            $wishlist,
            $itemId,
            $product,
            $context
        );
        $this->eventDispatcher->dispatch($event);
        
        // 8. Log
        $this->logger->info('Item added to wishlist', [
            'wishlistId' => $request->getWishlistId(),
            'productId' => $request->getProductId(),
            'itemId' => $itemId,
        ]);
        
        // 9. Load and return
        $item = $this->loadWishlistItem($itemId, $context);
        return WishlistItemResponse::fromEntity($item);
    }
    
    /**
     * Update wishlist item
     */
    public function updateItem(
        UpdateItemRequest $request,
        Context $context
    ): WishlistItemResponse {
        // 1. Load item with wishlist
        $item = $this->loadWishlistItem($request->getItemId(), $context);
        $wishlist = $item->getWishlist();
        
        // 2. Validate ownership
        $this->validator->validateOwnership($wishlist, $context);
        
        // 3. Prepare update data
        $updateData = $this->prepareUpdateData($request, $item);
        
        if (empty($updateData)) {
            return WishlistItemResponse::fromEntity($item);
        }
        
        // 4. Update item
        $this->wishlistItemRepository->update([$updateData], $context);
        
        // 5. Update price alert if needed
        if (array_key_exists('priceAlertThreshold', $updateData)) {
            if ($updateData['priceAlertThreshold'] !== null) {
                $this->priceAlertService->updateAlert(
                    $item->getId(),
                    $item->getProduct(),
                    $updateData['priceAlertThreshold'],
                    $context
                );
            } else {
                $this->priceAlertService->removeAlert($item->getId(), $context);
            }
        }
        
        // 6. Reload and return
        $updatedItem = $this->loadWishlistItem($request->getItemId(), $context);
        return WishlistItemResponse::fromEntity($updatedItem);
    }
    
    /**
     * Remove item from wishlist
     */
    public function removeItem(
        string $wishlistId,
        string $itemId,
        Context $context
    ): void {
        // 1. Validate
        $wishlist = $this->loadWishlist($wishlistId, $context);
        $this->validator->validateOwnership($wishlist, $context);
        
        // 2. Check item belongs to wishlist
        $item = $wishlist->getItems()->get($itemId);
        if (!$item) {
            throw new WishlistItemNotFoundException(
                'Item not found in wishlist',
                ['itemId' => $itemId, 'wishlistId' => $wishlistId]
            );
        }
        
        // 3. Remove price alert if exists
        if ($item->isPriceAlertActive()) {
            $this->priceAlertService->removeAlert($itemId, $context);
        }
        
        // 4. Delete item
        $this->wishlistItemRepository->delete([['id' => $itemId]], $context);
        
        // 5. Dispatch event
        $event = new WishlistItemRemovedEvent($wishlist, $item, $context);
        $this->eventDispatcher->dispatch($event);
        
        $this->logger->info('Item removed from wishlist', [
            'wishlistId' => $wishlistId,
            'itemId' => $itemId,
            'productId' => $item->getProductId(),
        ]);
    }
    
    /**
     * Move item between wishlists
     */
    public function moveItem(
        string $sourceWishlistId,
        string $targetWishlistId,
        string $itemId,
        bool $copy,
        Context $context
    ): WishlistItemResponse {
        // 1. Validate both wishlists
        $sourceWishlist = $this->loadWishlist($sourceWishlistId, $context);
        $targetWishlist = $this->loadWishlist($targetWishlistId, $context);
        
        $this->validator->validateOwnership($sourceWishlist, $context);
        $this->validator->validateOwnership($targetWishlist, $context);
        
        // 2. Get item
        $item = $sourceWishlist->getItems()->get($itemId);
        if (!$item) {
            throw new WishlistItemNotFoundException('Item not found');
        }
        
        // 3. Check for duplicate in target
        if ($this->isDuplicate($targetWishlist, $item->getProductId())) {
            throw new DuplicateWishlistItemException(
                'Product already exists in target wishlist'
            );
        }
        
        // 4. Check target wishlist limit
        $this->checkItemLimit($targetWishlist);
        
        if ($copy) {
            // Copy item
            $newItemId = Uuid::randomHex();
            $copyData = [
                'id' => $newItemId,
                'wishlistId' => $targetWishlistId,
                'productId' => $item->getProductId(),
                'quantity' => $item->getQuantity(),
                'note' => $item->getNote(),
                'priority' => $this->getNextPriority($targetWishlist),
                'priceAlertThreshold' => $item->getPriceAlertThreshold(),
                'priceAlertActive' => $item->isPriceAlertActive(),
            ];
            
            $this->wishlistItemRepository->create([$copyData], $context);
            $movedItem = $this->loadWishlistItem($newItemId, $context);
        } else {
            // Move item
            $this->wishlistItemRepository->update([
                [
                    'id' => $itemId,
                    'wishlistId' => $targetWishlistId,
                    'priority' => $this->getNextPriority($targetWishlist),
                ]
            ], $context);
            $movedItem = $this->loadWishlistItem($itemId, $context);
        }
        
        // 5. Dispatch event
        $event = new WishlistItemMovedEvent(
            $sourceWishlist,
            $targetWishlist,
            $movedItem,
            $copy,
            $context
        );
        $this->eventDispatcher->dispatch($event);
        
        return WishlistItemResponse::fromEntity($movedItem);
    }
    
    /**
     * Bulk add items
     */
    public function bulkAddItems(
        string $wishlistId,
        array $items,
        Context $context
    ): BulkOperationResponse {
        $wishlist = $this->loadWishlist($wishlistId, $context);
        $this->validator->validateOwnership($wishlist, $context);
        
        $results = [];
        $successful = 0;
        $failed = 0;
        
        // Process in batches
        $batches = array_chunk($items, self::BATCH_SIZE);
        
        foreach ($batches as $batch) {
            $createData = [];
            
            foreach ($batch as $item) {
                try {
                    // Validate product
                    $product = $this->validateProduct($item['productId'], $context);
                    
                    // Check duplicate
                    if ($this->isDuplicate($wishlist, $item['productId'])) {
                        if ($item['skipDuplicates'] ?? true) {
                            $results[] = [
                                'success' => false,
                                'productId' => $item['productId'],
                                'error' => 'Duplicate product',
                            ];
                            $failed++;
                            continue;
                        }
                    }
                    
                    // Prepare data
                    $itemId = Uuid::randomHex();
                    $createData[] = $this->prepareItemData(
                        $itemId,
                        AddItemRequest::fromArray($item),
                        $product,
                        $context
                    );
                    
                    $results[] = [
                        'success' => true,
                        'itemId' => $itemId,
                        'productId' => $item['productId'],
                    ];
                    $successful++;
                    
                } catch (\Exception $e) {
                    $results[] = [
                        'success' => false,
                        'productId' => $item['productId'],
                        'error' => $e->getMessage(),
                    ];
                    $failed++;
                }
            }
            
            // Create items in batch
            if (!empty($createData)) {
                $this->wishlistItemRepository->create($createData, $context);
            }
        }
        
        return new BulkOperationResponse($results);
    }
    
    /**
     * Helper: Check if product already in wishlist
     */
    private function isDuplicate(
        WishlistEntity $wishlist,
        string $productId
    ): bool {
        return $wishlist->getItems()->filter(
            fn($item) => $item->getProductId() === $productId
        )->count() > 0;
    }
    
    /**
     * Helper: Check item limit
     */
    private function checkItemLimit(WishlistEntity $wishlist): void
    {
        if ($wishlist->getItems()->count() >= WishlistService::MAX_ITEMS_PER_WISHLIST) {
            throw new WishlistLimitExceededException(
                'Wishlist item limit reached',
                ['limit' => WishlistService::MAX_ITEMS_PER_WISHLIST]
            );
        }
    }
    
    /**
     * Helper: Get next priority number
     */
    private function getNextPriority(WishlistEntity $wishlist): int
    {
        $maxPriority = 0;
        
        foreach ($wishlist->getItems() as $item) {
            if ($item->getPriority() > $maxPriority) {
                $maxPriority = $item->getPriority();
            }
        }
        
        return $maxPriority + 1;
    }
}
```

### PriceMonitorService

Service für Preis-Überwachung und Benachrichtigungen.

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\Entity\WishlistItem\WishlistItemEntity;
use AdvancedWishlist\Core\Event\PriceDropDetectedEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class PriceMonitorService
{
    private const BATCH_SIZE = 100;
    private const CHECK_INTERVAL = 3600; // 1 hour
    
    public function __construct(
        private EntityRepository $wishlistItemRepository,
        private EntityRepository $productRepository,
        private NotificationService $notificationService,
        private PriceHistoryService $priceHistoryService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private CacheInterface $cache
    ) {}
    
    /**
     * Check all active price alerts
     */
    public function checkPriceAlerts(Context $context): array
    {
        $processed = 0;
        $triggered = 0;
        $offset = 0;
        
        do {
            // Get items with active price alerts
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('priceAlertActive', true));
            $criteria->addFilter(new RangeFilter('priceAlertThreshold', [
                RangeFilter::GT => 0
            ]));
            $criteria->addAssociation('product.prices');
            $criteria->addAssociation('wishlist.customer');
            $criteria->setLimit(self::BATCH_SIZE);
            $criteria->setOffset($offset);
            
            $items = $this->wishlistItemRepository->search($criteria, $context);
            
            foreach ($items as $item) {
                try {
                    if ($this->checkPriceDrop($item, $context)) {
                        $triggered++;
                    }
                    $processed++;
                } catch (\Exception $e) {
                    $this->logger->error('Failed to check price alert', [
                        'itemId' => $item->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            $offset += self::BATCH_SIZE;
            
        } while ($items->count() === self::BATCH_SIZE);
        
        $this->logger->info('Price alerts checked', [
            'processed' => $processed,
            'triggered' => $triggered,
        ]);
        
        return [
            'processed' => $processed,
            'triggered' => $triggered,
        ];
    }
    
    /**
     * Check single item for price drop
     */
    public function checkPriceDrop(
        WishlistItemEntity $item,
        Context $context
    ): bool {
        $product = $item->getProduct();
        if (!$product) {
            return false;
        }
        
        $currentPrice = $this->getCurrentPrice($product, $context);
        $threshold = $item->getPriceAlertThreshold();
        
        // Check if price dropped below threshold
        if ($currentPrice >= $threshold) {
            return false;
        }
        
        // Check if we already notified recently
        if ($this->wasRecentlyNotified($item->getId())) {
            return false;
        }
        
        // Calculate savings
        $savings = $threshold - $currentPrice;
        $savingsPercentage = ($savings / $threshold) * 100;
        
        // Send notification
        $this->notificationService->sendPriceAlert(
            $item->getWishlist()->getCustomer(),
            $item,
            $currentPrice,
            $savings,
            $savingsPercentage,
            $context
        );
        
        // Record notification
        $this->recordNotification($item->getId(), $currentPrice);
        
        // Track price history
        $this->priceHistoryService->recordPrice(
            $product->getId(),
            $currentPrice,
            $context
        );
        
        // Dispatch event
        $event = new PriceDropDetectedEvent(
            $item,
            $threshold,
            $currentPrice,
            $context
        );
        $this->eventDispatcher->dispatch($event);
        
        return true;
    }
    
    /**
     * Setup price alert for item
     */
    public function setupAlert(
        string $itemId,
        ProductEntity $product,
        float $threshold,
        Context $context
    ): void {
        // Validate threshold
        $currentPrice = $this->getCurrentPrice($product, $context);
        
        if ($threshold <= 0) {
            throw new \InvalidArgumentException('Threshold must be greater than 0');
        }
        
        if ($threshold <= $currentPrice) {
            throw new \InvalidArgumentException(
                'Threshold must be higher than current price'
            );
        }
        
        // Update item
        $this->wishlistItemRepository->update([
            [
                'id' => $itemId,
                'priceAlertThreshold' => $threshold,
                'priceAlertActive' => true,
                'priceAtAlert' => $currentPrice,
            ]
        ], $context);
        
        // Record initial price
        $this->priceHistoryService->recordPrice(
            $product->getId(),
            $currentPrice,
            $context
        );
        
        $this->logger->info('Price alert setup', [
            'itemId' => $itemId,
            'productId' => $product->getId(),
            'threshold' => $threshold,
            'currentPrice' => $currentPrice,
        ]);
    }
    
    /**
     * Get price statistics for product
     */
    public function getPriceStatistics(
        string $productId,
        \DateTimeInterface $since,
        Context $context
    ): array {
        $history = $this->priceHistoryService->getHistory(
            $productId,
            $since,
            new \DateTime(),
            $context
        );
        
        if (empty($history)) {
            return [
                'current' => 0,
                'min' => 0,
                'max' => 0,
                'average' => 0,
                'trend' => 'stable',
                'volatility' => 0,
            ];
        }
        
        $prices = array_column($history, 'price');
        $current = end($prices);
        $min = min($prices);
        $max = max($prices);
        $average = array_sum($prices) / count($prices);
        
        // Calculate trend
        $firstHalf = array_slice($prices, 0, floor(count($prices) / 2));
        $secondHalf = array_slice($prices, floor(count($prices) / 2));
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        $trend = 'stable';
        if ($secondAvg > $firstAvg * 1.05) {
            $trend = 'up';
        } elseif ($secondAvg < $firstAvg * 0.95) {
            $trend = 'down';
        }
        
        // Calculate volatility
        $variance = 0;
        foreach ($prices as $price) {
            $variance += pow($price - $average, 2);
        }
        $variance /= count($prices);
        $volatility = sqrt($variance) / $average * 100;
        
        return [
            'current' => $current,
            'min' => $min,
            'max' => $max,
            'average' => round($average, 2),
            'trend' => $trend,
            'volatility' => round($volatility, 2),
            'history' => $history,
        ];
    }
    
    /**
     * Helper: Get current price for product
     */
    private function getCurrentPrice(
        ProductEntity $product,
        Context $context
    ): float {
        $price = $product->getCheapestPrice();
        
        if (!$price) {
            throw new \RuntimeException(
                'No price found for product: ' . $product->getId()
            );
        }
        
        return $price->getGross();
    }
    
    /**
     * Helper: Check if recently notified
     */
    private function wasRecentlyNotified(string $itemId): bool
    {
        $cacheKey = sprintf('price_alert.notified.%s', $itemId);
        
        return $this->cache->has($cacheKey);
    }
    
    /**
     * Helper: Record notification sent
     */
    private function recordNotification(string $itemId, float $price): void
    {
        $cacheKey = sprintf('price_alert.notified.%s', $itemId);
        
        // Prevent duplicate notifications for 24 hours
        $this->cache->set($cacheKey, [
            'price' => $price,
            'timestamp' => time(),
        ], 86400);
    }
}
```

### NotificationService

Service für alle Arten von Benachrichtigungen.

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\Entity\WishlistItem\WishlistItemEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

class NotificationService
{
    private const QUEUE_BATCH_SIZE = 50;
    
    public function __construct(
        private MailService $mailService,
        private EntityRepository $notificationQueueRepository,
        private EntityRepository $notificationLogRepository,
        private TemplateRenderer $templateRenderer,
        private TranslatorInterface $translator,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Send price alert notification
     */
    public function sendPriceAlert(
        CustomerEntity $customer,
        WishlistItemEntity $item,
        float $currentPrice,
        float $savings,
        float $savingsPercentage,
        Context $context
    ): void {
        $data = [
            'customer' => $customer,
            'item' => $item,
            'product' => $item->getProduct(),
            'currentPrice' => $currentPrice,
            'previousPrice' => $item->getPriceAlertThreshold(),
            'savings' => $savings,
            'savingsPercentage' => $savingsPercentage,
            'wishlistUrl' => $this->generateWishlistUrl($item->getWishlistId()),
            'productUrl' => $this->generateProductUrl($item->getProductId()),
        ];
        
        $this->queueNotification(
            'price_drop',
            $customer->getId(),
            'customer',
            $data,
            $context
        );
    }
    
    /**
     * Send back in stock notification
     */
    public function sendBackInStockAlert(
        CustomerEntity $customer,
        WishlistItemEntity $item,
        int $availableStock,
        Context $context
    ): void {
        $data = [
            'customer' => $customer,
            'item' => $item,
            'product' => $item->getProduct(),
            'availableStock' => $availableStock,
            'wishlistUrl' => $this->generateWishlistUrl($item->getWishlistId()),
            'productUrl' => $this->generateProductUrl($item->getProductId()),
        ];
        
        $this->queueNotification(
            'back_in_stock',
            $customer->getId(),
            'customer',
            $data,
            $context
        );
    }
    
    /**
     * Send wishlist shared notification
     */
    public function sendShareNotification(
        string $recipientEmail,
        WishlistEntity $wishlist,
        string $shareUrl,
        ?string $message,
        Context $context
    ): void {
        $data = [
            'recipientEmail' => $recipientEmail,
            'wishlist' => $wishlist,
            'sharer' => $wishlist->getCustomer(),
            'shareUrl' => $shareUrl,
            'message' => $message,
            'itemCount' => $wishlist->getItemCount(),
        ];
        
        // Send immediately for share notifications
        $this->sendEmail(
            'wishlist_shared',
            $recipientEmail,
            $data,
            $context
        );
    }
    
    /**
     * Send guest wishlist reminder
     */
    public function sendGuestReminder(
        string $email,
        GuestWishlistEntity $guestWishlist,
        string $accessUrl,
        Context $context
    ): void {
        $data = [
            'email' => $email,
            'wishlist' => $guestWishlist,
            'accessUrl' => $accessUrl,
            'itemCount' => count($guestWishlist->getItems()),
            'expiresIn' => $this->calculateExpiryDays($guestWishlist->getExpiresAt()),
        ];
        
        $this->sendEmail(
            'guest_wishlist_reminder',
            $email,
            $data,
            $context
        );
    }
    
    /**
     * Process notification queue
     */
    public function processQueue(Context $context): array
    {
        $processed = 0;
        $failed = 0;
        
        // Get pending notifications
        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('scheduledAt', [
            RangeFilter::LTE => (new \DateTime())->format('c'),
        ]));
        $criteria->addFilter(new EqualsFilter('sentAt', null));
        $criteria->addFilter(new RangeFilter('attempts', [
            RangeFilter::LT => 3,
        ]));
        $criteria->setLimit(self::QUEUE_BATCH_SIZE);
        $criteria->addSorting(new FieldSorting('priority', 'DESC'));
        $criteria->addSorting(new FieldSorting('scheduledAt', 'ASC'));
        
        $notifications = $this->notificationQueueRepository->search($criteria, $context);
        
        foreach ($notifications as $notification) {
            try {
                $this->processNotification($notification, $context);
                $processed++;
            } catch (\Exception $e) {
                $this->handleFailedNotification($notification, $e, $context);
                $failed++;
            }
        }
        
        return [
            'processed' => $processed,
            'failed' => $failed,
        ];
    }
    
    /**
     * Queue notification for sending
     */
    private function queueNotification(
        string $type,
        string $recipientId,
        string $recipientType,
        array $data,
        Context $context,
        ?\DateTime $scheduledAt = null
    ): void {
        $queueData = [
            'id' => Uuid::randomHex(),
            'type' => $type,
            'recipientId' => $recipientId,
            'recipientType' => $recipientType,
            'data' => $data,
            'priority' => $this->getNotificationPriority($type),
            'attempts' => 0,
            'scheduledAt' => $scheduledAt ?? new \DateTime(),
        ];
        
        if (isset($data['item'])) {
            $queueData['wishlistId'] = $data['item']->getWishlistId();
            $queueData['itemId'] = $data['item']->getId();
        }
        
        $this->notificationQueueRepository->create([$queueData], $context);
    }
    
    /**
     * Process single notification
     */
    private function processNotification(
        NotificationQueueEntity $notification,
        Context $context
    ): void {
        // Increment attempts
        $this->notificationQueueRepository->update([
            [
                'id' => $notification->getId(),
                'attempts' => $notification->getAttempts() + 1,
            ]
        ], $context);
        
        // Get recipient
        $recipient = $this->getRecipient($notification, $context);
        
        if (!$recipient || !$recipient['email']) {
            throw new \RuntimeException('Recipient email not found');
        }
        
        // Send notification
        $this->sendEmail(
            $notification->getType(),
            $recipient['email'],
            $notification->getData(),
            $context
        );
        
        // Mark as sent
        $this->notificationQueueRepository->update([
            [
                'id' => $notification->getId(),
                'sentAt' => new \DateTime(),
            ]
        ], $context);
        
        // Log notification
        $this->logNotification(
            $notification,
            'sent',
            $recipient['email'],
            $context
        );
    }
    
    /**
     * Send email notification
     */
    private function sendEmail(
        string $type,
        string $recipientEmail,
        array $data,
        Context $context
    ): void {
        $template = $this->getEmailTemplate($type);
        
        $mailData = [
            'recipients' => [$recipientEmail],
            'senderName' => $this->getShopName($context),
            'subject' => $this->translator->trans(
                $template['subject'],
                $data,
                null,
                $context->getLanguageId()
            ),
            'contentHtml' => $this->templateRenderer->render(
                $template['html'],
                $data,
                $context
            ),
            'contentPlain' => $this->templateRenderer->render(
                $template['plain'],
                $data,
                $context
            ),
        ];
        
        $this->mailService->send($mailData, $context);
        
        $this->logger->info('Notification sent', [
            'type' => $type,
            'recipient' => $recipientEmail,
        ]);
    }
    
    /**
     * Get email template for notification type
     */
    private function getEmailTemplate(string $type): array
    {
        return match($type) {
            'price_drop' => [
                'subject' => 'wishlist.notification.price_drop.subject',
                'html' => '@AdvancedWishlist/email/price-drop.html.twig',
                'plain' => '@AdvancedWishlist/email/price-drop.txt.twig',
            ],
            'back_in_stock' => [
                'subject' => 'wishlist.notification.back_in_stock.subject',
                'html' => '@AdvancedWishlist/email/back-in-stock.html.twig',
                'plain' => '@AdvancedWishlist/email/back-in-stock.txt.twig',
            ],
            'wishlist_shared' => [
                'subject' => 'wishlist.notification.shared.subject',
                'html' => '@AdvancedWishlist/email/wishlist-shared.html.twig',
                'plain' => '@AdvancedWishlist/email/wishlist-shared.txt.twig',
            ],
            'guest_wishlist_reminder' => [
                'subject' => 'wishlist.notification.guest_reminder.subject',
                'html' => '@AdvancedWishlist/email/guest-reminder.html.twig',
                'plain' => '@AdvancedWishlist/email/guest-reminder.txt.twig',
            ],
            default => throw new \InvalidArgumentException('Unknown notification type: ' . $type),
        };
    }
    
    /**
     * Get notification priority
     */
    private function getNotificationPriority(string $type): int
    {
        return match($type) {
            'price_drop' => 10,
            'back_in_stock' => 10,
            'wishlist_shared' => 5,
            'guest_wishlist_reminder' => 3,
            default => 0,
        };
    }
}
```

## Support Services

### WishlistCacheService

Cache-Management für Wishlist-Daten.

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;

class WishlistCacheService
{
    private const CACHE_TAG_WISHLIST = 'wishlist';
    private const CACHE_TAG_CUSTOMER = 'wishlist_customer';
    private const CACHE_TAG_PRODUCT = 'wishlist_product';
    private const DEFAULT_TTL = 3600;
    
    public function __construct(
        private CacheItemPoolInterface $cache,
        private CacheInvalidator $cacheInvalidator,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Get cached wishlist data
     */
    public function get(string $key): mixed
    {
        $item = $this->cache->getItem($this->prefixKey($key));
        
        if ($item->isHit()) {
            $this->logger->debug('Cache hit', ['key' => $key]);
            return $item->get();
        }
        
        return null;
    }
    
    /**
     * Set cached wishlist data
     */
    public function set(string $key, mixed $data, ?int $ttl = null): void
    {
        $item = $this->cache->getItem($this->prefixKey($key));
        $item->set($data);
        $item->expiresAfter($ttl ?? self::DEFAULT_TTL);
        
        $this->cache->save($item);
        
        $this->logger->debug('Cache set', [
            'key' => $key,
            'ttl' => $ttl ?? self::DEFAULT_TTL,
        ]);
    }
    
    /**
     * Invalidate wishlist cache
     */
    public function invalidateWishlistCache(string $wishlistId): void
    {
        $tags = [
            self::CACHE_TAG_WISHLIST . '_' . $wishlistId,
            self::CACHE_TAG_WISHLIST,
        ];
        
        $this->cacheInvalidator->invalidate($tags);
        
        $this->logger->debug('Wishlist cache invalidated', [
            'wishlistId' => $wishlistId,
        ]);
    }
    
    /**
     * Invalidate customer cache
     */
    public function invalidateCustomerCache(string $customerId): void
    {
        $tags = [
            self::CACHE_TAG_CUSTOMER . '_' . $customerId,
            self::CACHE_TAG_CUSTOMER,
        ];
        
        $this->cacheInvalidator->invalidate($tags);
        
        $this->logger->debug('Customer cache invalidated', [
            'customerId' => $customerId,
        ]);
    }
    
    /**
     * Invalidate product cache
     */
    public function invalidateProductCache(string $productId): void
    {
        $tags = [
            self::CACHE_TAG_PRODUCT . '_' . $productId,
            self::CACHE_TAG_PRODUCT,
        ];
        
        $this->cacheInvalidator->invalidate($tags);
    }
    
    /**
     * Warm up cache
     */
    public function warmUp(array $wishlistIds, Context $context): void
    {
        foreach ($wishlistIds as $wishlistId) {
            $key = 'wishlist.' . $wishlistId;
            
            if (!$this->get($key)) {
                // Load and cache wishlist
                $wishlist = $this->wishlistRepository->find($wishlistId, $context);
                
                if ($wishlist) {
                    $this->set($key, $wishlist);
                }
            }
        }
    }
    
    /**
     * Clear all wishlist cache
     */
    public function clear(): void
    {
        $this->cacheInvalidator->invalidate([
            self::CACHE_TAG_WISHLIST,
            self::CACHE_TAG_CUSTOMER,
            self::CACHE_TAG_PRODUCT,
        ]);
        
        $this->logger->info('All wishlist cache cleared');
    }
    
    /**
     * Get cache statistics
     */
    public function getStatistics(): array
    {
        // Implementation depends on cache backend
        return [
            'hits' => 0,
            'misses' => 0,
            'hitRate' => 0.0,
            'memoryUsage' => 0,
        ];
    }
    
    /**
     * Helper: Prefix cache key
     */
    private function prefixKey(string $key): string
    {
        return 'advanced_wishlist.' . $key;
    }
}
```

### WishlistExportService

Export-Funktionalität für Wishlists.

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\Entity\Wishlist\WishlistEntity;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Shopware\Core\Framework\Context;

class WishlistExportService
{
    public function __construct(
        private WishlistService $wishlistService,
        private TranslatorInterface $translator,
        private MediaService $mediaService,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Export wishlist to various formats
     */
    public function export(
        string $wishlistId,
        string $format,
        Context $context
    ): ExportResult {
        $wishlist = $this->wishlistService->loadWishlist($wishlistId, $context);
        
        return match($format) {
            'xlsx' => $this->exportToExcel($wishlist, $context),
            'csv' => $this->exportToCsv($wishlist, $context),
            'pdf' => $this->exportToPdf($wishlist, $context),
            'json' => $this->exportToJson($wishlist, $context),
            default => throw new \InvalidArgumentException('Unsupported format: ' . $format),
        };
    }
    
    /**
     * Export to Excel
     */
    private function exportToExcel(
        WishlistEntity $wishlist,
        Context $context
    ): ExportResult {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headers = [
            'A1' => $this->trans('wishlist.export.product_number'),
            'B1' => $this->trans('wishlist.export.product_name'),
            'C1' => $this->trans('wishlist.export.quantity'),
            'D1' => $this->trans('wishlist.export.price'),
            'E1' => $this->trans('wishlist.export.total'),
            'F1' => $this->trans('wishlist.export.note'),
            'G1' => $this->trans('wishlist.export.added_date'),
        ];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        
        // Style headers
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');
        
        // Add data
        $row = 2;
        $totalValue = 0;
        
        foreach ($wishlist->getItems() as $item) {
            $product = $item->getProduct();
            $price = $product->getCheapestPrice()->getGross();
            $total = $price * $item->getQuantity();
            $totalValue += $total;
            
            $sheet->setCellValue('A' . $row, $product->getProductNumber());
            $sheet->setCellValue('B' . $row, $product->getTranslated()['name']);
            $sheet->setCellValue('C' . $row, $item->getQuantity());
            $sheet->setCellValue('D' . $row, $price);
            $sheet->setCellValue('E' . $row, $total);
            $sheet->setCellValue('F' . $row, $item->getNote() ?? '');
            $sheet->setCellValue('G' . $row, $item->getAddedAt()->format('Y-m-d'));
            
            $row++;
        }
        
        // Add summary
        $sheet->setCellValue('D' . ($row + 1), $this->trans('wishlist.export.total'));
        $sheet->setCellValue('E' . ($row + 1), $totalValue);
        $sheet->getStyle('D' . ($row + 1) . ':E' . ($row + 1))->getFont()->setBold(true);
        
        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Write to temporary file
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'wishlist_export_');
        $writer->save($tempFile);
        
        // Create media file
        $fileName = sprintf(
            'wishlist_%s_%s.xlsx',
            $wishlist->getName(),
            date('Y-m-d')
        );
        
        $mediaFile = $this->mediaService->saveFile(
            $tempFile,
            $fileName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'wishlist-export',
            $context
        );
        
        unlink($tempFile);
        
        return new ExportResult(
            $mediaFile->getId(),
            $mediaFile->getUrl(),
            $fileName,
            filesize($tempFile)
        );
    }
    
    /**
     * Export to CSV
     */
    private function exportToCsv(
        WishlistEntity $wishlist,
        Context $context
    ): ExportResult {
        $data = [];
        
        // Headers
        $data[] = [
            'Product Number',
            'Product Name',
            'Quantity',
            'Price',
            'Total',
            'Note',
            'Added Date',
        ];
        
        // Items
        foreach ($wishlist->getItems() as $item) {
            $product = $item->getProduct();
            $price = $product->getCheapestPrice()->getGross();
            
            $data[] = [
                $product->getProductNumber(),
                $product->getTranslated()['name'],
                $item->getQuantity(),
                $price,
                $price * $item->getQuantity(),
                $item->getNote() ?? '',
                $item->getAddedAt()->format('Y-m-d'),
            ];
        }
        
        // Create CSV
        $tempFile = tempnam(sys_get_temp_dir(), 'wishlist_csv_');
        $handle = fopen($tempFile, 'w');
        
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        
        // Save as media
        $fileName = sprintf(
            'wishlist_%s_%s.csv',
            $wishlist->getName(),
            date('Y-m-d')
        );
        
        $mediaFile = $this->mediaService->saveFile(
            $tempFile,
            $fileName,
            'text/csv',
            'wishlist-export',
            $context
        );
        
        unlink($tempFile);
        
        return new ExportResult(
            $mediaFile->getId(),
            $mediaFile->getUrl(),
            $fileName,
            filesize($tempFile)
        );
    }
    
    /**
     * Helper: Translate
     */
    private function trans(string $key): string
    {
        return $this->translator->trans($key);
    }
}
```