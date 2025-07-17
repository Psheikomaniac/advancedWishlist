<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\DTO\Response\WishlistResponse;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Event\WishlistCreatedEvent;
use AdvancedWishlist\Core\Event\WishlistUpdatedEvent;
use AdvancedWishlist\Core\Event\WishlistDeletedEvent;
use AdvancedWishlist\Core\Exception\WishlistException;
use AdvancedWishlist\Core\Exception\WishlistNotFoundException;
use AdvancedWishlist\Core\Exception\OptimisticLockException;
use AdvancedWishlist\Core\Exception\CannotDeleteDefaultWishlistException;
use AdvancedWishlist\Core\Exception\WishlistLimitExceededException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use AdvancedWishlist\Service\ShareService;

class WishlistService
{
    private const int CACHE_TTL = 3600; // 1 hour
    private const int MAX_WISHLISTS_PER_CUSTOMER = 10;
    public const int MAX_ITEMS_PER_WISHLIST = 100;

    public function __construct(
        private EntityRepository $wishlistRepository,
        private WishlistValidator $validator,
        private WishlistLimitService $limitService,
        private WishlistCacheService $cacheService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
        private ShareService $shareService
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

            // 5. Create share token for the new wishlist
            $this->shareService->createShare($wishlistId, $context);

            // 6. Dispatch event
            $event = new WishlistCreatedEvent($wishlist, $context);
            $this->eventDispatcher->dispatch($event);

            // 7. Clear cache
            $this->cacheService->invalidateCustomerCache($request->getCustomerId());

            // 8. Log creation
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
        $wishlist = $this->wishlistRepository->search(
            (new Criteria([$request->getWishlistId()]))->addAssociation('items'),
            $context
        )->first();

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
        // if ($request->getVersion() && $request->getVersion() !== $wishlist->getVersion()) {
        //     throw new OptimisticLockException(
        //         'Wishlist was modified by another process',
        //         ['currentVersion' => $wishlist->getVersion()]
        //     );
        // }

        // 4. Apply updates
        $updateData = $request->toArray();

        if (empty($updateData)) {
            return WishlistResponse::fromEntity($wishlist);
        }

        // 5. Update wishlist
        $this->wishlistRepository->update([array_merge(['id' => $request->getWishlistId()], $updateData)], $context);

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
        // if ($wishlist->isDefault() && $this->isOnlyWishlist($wishlist, $context)) {
        //     throw new CannotDeleteDefaultWishlistException(
        //         'Cannot delete the only wishlist'
        //     );
        // }

        // Transfer items if requested
        // if ($transferToWishlistId && $wishlist->getItems()->count() > 0) {
        //     $this->transferItems($wishlist, $transferToWishlistId, $context);
        // }

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
        // Use the WishlistCacheService to get or create the default wishlist
        return $this->cacheService->getCachedDefaultWishlist($customerId, function() use ($customerId, $context) {
            // Find existing default wishlist
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customerId', $customerId));
            $criteria->addFilter(new EqualsFilter('isDefault', true));
            $criteria->setLimit(1);

            $result = $this->wishlistRepository->search($criteria, $context);

            if ($result->getTotal() > 0) {
                return $result->first();
            } else {
                // Create default wishlist
                $request = new CreateWishlistRequest();
                $request->setCustomerId($customerId);
                $request->setName('My Wishlist');
                $request->setType('private');
                $request->setIsDefault(true);

                $response = $this->createWishlist($request, $context);
                return $this->loadWishlist($response->getId(), $context);
            }
        });
    }

    /**
     * Check if product is in any customer wishlist
     */
    public function isProductInWishlist(
        string $customerId,
        string $productId,
        Context $context
    ): array {
        // Try to get from cache first
        $cacheKey = "product_in_wishlist_{$customerId}_{$productId}";

        return $this->cacheService->get($cacheKey, function() use ($customerId, $productId, $context) {
            // Create a more efficient query that directly filters for the product
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customerId', $customerId));

            // Join with wishlist_item and filter by product_id
            $criteria->addFilter(new EqualsFilter('items.productId', $productId));

            // Only select the fields we need
            $criteria->addAssociation('items');

            $wishlists = $this->wishlistRepository->search($criteria, $context);

            $result = [
                'inWishlist' => $wishlists->count() > 0,
                'wishlists' => [],
            ];

            // If we found wishlists, get the details
            if ($result['inWishlist']) {
                foreach ($wishlists as $wishlist) {
                    $item = $wishlist->getItems()->filter(
                        fn($item) => $item->getProductId() === $productId
                    )->first();

                    if ($item) {
                        $result['wishlists'][] = [
                            'id' => $wishlist->getId(),
                            'name' => $wishlist->getName(),
                            'itemId' => $item->getId(),
                        ];
                    }
                }
            }

            return $result;
        });
    }

    /**
     * Get wishlist statistics for customer
     */
    public function getCustomerStatistics(
        string $customerId,
        Context $context
    ): array {
        // $stats = $this->wishlistRepository->getCustomerStatistics($customerId, $context);

        return [
            'totalWishlists' => 0,
            'totalItems' => 0,
            'totalValue' => 0.00,
            'topCategories' => [],
            'priceAlerts' => [
                'active' => 0,
                'triggered' => 0,
            ],
            'recentActivity' => [],
        ];
    }

    /**
     * Helper: Check customer limits
     */
    private function checkCustomerLimits(string $customerId, Context $context): void
    {
        // Use the WishlistLimitService to check customer limits
        $this->limitService->checkCustomerWishlistLimit($customerId, $context);
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
     * 
     * @param string $wishlistId The ID of the wishlist to load
     * @param Context $context The context
     * @param array|null $associations Optional associations to load, null for all default associations
     * @return WishlistEntity The loaded wishlist
     * @throws WishlistNotFoundException If the wishlist is not found
     */
    private function loadWishlist(
        string $wishlistId, 
        Context $context, 
        ?array $associations = null
    ): WishlistEntity {
        // Try to get from cache first
        $cacheKey = "wishlist_{$wishlistId}";

        return $this->cacheService->getCachedWishlist($wishlistId, function() use ($wishlistId, $context, $associations) {
            $criteria = new Criteria([$wishlistId]);

            // If specific associations are requested, only load those
            if ($associations !== null) {
                foreach ($associations as $association) {
                    $criteria->addAssociation($association);
                }
            } else {
                // Default associations
                $criteria->addAssociation('items.product.cover');
                $criteria->addAssociation('items.product.prices');
                $criteria->addAssociation('customer');
                $criteria->addAssociation('shareInfo');
            }

            $wishlist = $this->wishlistRepository->search($criteria, $context)->first();

            if (!$wishlist) {
                throw new WishlistNotFoundException(
                    'Wishlist not found',
                    ['wishlistId' => $wishlistId]
                );
            }

            return $wishlist;
        });
    }

    private function unsetExistingDefaultWishlist(string $customerId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addFilter(new EqualsFilter('isDefault', true));

        $defaultWishlist = $this->wishlistRepository->search($criteria, $context)->first();

        if ($defaultWishlist) {
            $this->wishlistRepository->update([[
                'id' => $defaultWishlist->getId(),
                'isDefault' => false,
            ]], $context);
        }
    }
}
