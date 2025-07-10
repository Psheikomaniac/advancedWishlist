<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\AddItemRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateItemRequest;
use AdvancedWishlist\Core\DTO\Response\WishlistItemResponse;
use AdvancedWishlist\Core\Event\WishlistItemAddedEvent;
use AdvancedWishlist\Core\Event\WishlistItemRemovedEvent;
use AdvancedWishlist\Core\Event\WishlistItemMovedEvent;
use AdvancedWishlist\Core\Exception\DuplicateWishlistItemException;
use AdvancedWishlist\Core\Exception\WishlistItemNotFoundException;
use AdvancedWishlist\Core\Exception\WishlistLimitExceededException;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class WishlistItemService
{
    private const int BATCH_SIZE = 50;

    public function __construct(
        private EntityRepository $wishlistRepository,
        private EntityRepository $wishlistItemRepository,
        private EntityRepository $productRepository,
        private WishlistValidator $validator,
        // private ProductAvailabilityService $availabilityService,
        // private PriceAlertService $priceAlertService,
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
        // if ($request->getPriceAlertThreshold()) {
        //     $this->priceAlertService->setupAlert(
        //         $itemId,
        //         $product,
        //         $request->getPriceAlertThreshold(),
        //         $context
        //     );
        // }

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
        // if (array_key_exists('priceAlertThreshold', $updateData)) {
        //     if ($updateData['priceAlertThreshold'] !== null) {
        //         $this->priceAlertService->updateAlert(
        //             $item->getId(),
        //             $item->getProduct(),
        //             $updateData['priceAlertThreshold'],
        //             $context
        //         );
        //     } else {
        //         $this->priceAlertService->removeAlert($item->getId(), $context);
        //     }
        // }

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
        // if ($item->isPriceAlertActive()) {
        //     $this->priceAlertService->removeAlert($itemId, $context);
        // }

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
    ): array {
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

        return [
            'total' => count($items),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ];
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

    private function loadWishlist(string $wishlistId, Context $context): WishlistEntity
    {
        $criteria = new Criteria([$wishlistId]);
        $criteria->addAssociation('items');
        $wishlist = $this->wishlistRepository->search($criteria, $context)->first();

        if (!$wishlist) {
            throw new WishlistNotFoundException(
                'Wishlist not found',
                ['wishlistId' => $wishlistId]
            );
        }

        return $wishlist;
    }

    private function validateProduct(string $productId, Context $context): ProductEntity
    {
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        if (!$product) {
            throw new WishlistItemNotFoundException(
                'Product not found',
                ['productId' => $productId]
            );
        }

        return $product;
    }

    private function prepareItemData(string $itemId, AddItemRequest $request, ProductEntity $product, Context $context): array
    {
        return [
            'id' => $itemId,
            'wishlistId' => $request->getWishlistId(),
            'productId' => $request->getProductId(),
            'productVersionId' => $product->getVersionId(),
            'quantity' => $request->getQuantity(),
            'note' => $request->getNote(),
            'priority' => $request->getPriority() ?? 0,
            'priceAtAddition' => $product->getCheapestPrice()?->getGross(),
            'priceAlertThreshold' => $request->getPriceAlertThreshold(),
            'priceAlertActive' => $request->getPriceAlertThreshold() !== null,
            'customFields' => $request->getCustomFields(),
        ];
    }

    private function loadWishlistItem(string $itemId, Context $context): WishlistItemEntity
    {
        $criteria = new Criteria([$itemId]);
        $criteria->addAssociation('wishlist');
        $criteria->addAssociation('product');

        $item = $this->wishlistItemRepository->search($criteria, $context)->first();

        if (!$item) {
            throw new WishlistItemNotFoundException(
                'Wishlist item not found',
                ['itemId' => $itemId]
            );
        }

        return $item;
    }

    private function prepareUpdateData(UpdateItemRequest $request, WishlistItemEntity $item): array
    {
        $updateData = [
            'id' => $request->getItemId(),
        ];

        if ($request->getQuantity() !== null) {
            $updateData['quantity'] = $request->getQuantity();
        }
        if ($request->getNote() !== null) {
            $updateData['note'] = $request->getNote();
        }
        if ($request->getPriority() !== null) {
            $updateData['priority'] = $request->getPriority();
        }
        if ($request->getPriceAlertThreshold() !== null) {
            $updateData['priceAlertThreshold'] = $request->getPriceAlertThreshold();
            $updateData['priceAlertActive'] = $request->getPriceAlertThreshold() !== null;
        }
        if ($request->getPriceAlertActive() !== null) {
            $updateData['priceAlertActive'] = $request->getPriceAlertActive();
        }

        return $updateData;
    }
}
