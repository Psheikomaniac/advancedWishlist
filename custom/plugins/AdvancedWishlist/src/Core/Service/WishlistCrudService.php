<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\Builder\WishlistBuilder;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\DTO\Response\WishlistResponse;
use AdvancedWishlist\Core\Event\WishlistCreatedEvent;
use AdvancedWishlist\Core\Event\WishlistDeletedEvent;
use AdvancedWishlist\Core\Event\WishlistUpdatedEvent;
use AdvancedWishlist\Core\Exception\CannotDeleteDefaultWishlistException;
use AdvancedWishlist\Core\Exception\OptimisticLockException;
use AdvancedWishlist\Core\Exception\WishlistException;
use AdvancedWishlist\Core\Exception\WishlistNotFoundException;
use AdvancedWishlist\Core\Message\WishlistCreatedMessage;
use AdvancedWishlist\Service\ShareService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Service for basic CRUD operations on wishlists.
 */
class WishlistCrudService
{
    private const int MAX_ITEMS_PER_WISHLIST = 100;

    /**
     * @param EntityRepository         $wishlistRepository Repository for wishlist entities
     * @param WishlistValidator        $validator          Service for validating wishlist operations
     * @param WishlistLimitService     $limitService       Service for checking wishlist limits
     * @param WishlistCacheService     $cacheService       Service for caching wishlist data
     * @param EventDispatcherInterface $eventDispatcher    Event dispatcher for wishlist events
     * @param MessageBusInterface      $messageBus         Message bus for async operations
     * @param LoggerInterface          $logger             Logger for wishlist operations
     * @param ShareService             $shareService       Service for managing wishlist sharing
     */
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly WishlistValidator $validator,
        private readonly WishlistLimitService $limitService,
        private readonly WishlistCacheService $cacheService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly ShareService $shareService,
        private readonly WishlistBuilder $wishlistBuilder,
    ) {
    }

    /**
     * Create a new wishlist with validation and limits.
     */
    public function createWishlist(
        CreateWishlistRequest $request,
        Context $context,
    ): WishlistResponse {
        // Start performance monitoring
        $startTime = microtime(true);

        // Start transaction
        $this->wishlistRepository->beginTransaction();

        try {
            // 1. Validate request
            $validationStartTime = microtime(true);
            $this->validator->validateCreateRequest($request, $context);
            $validationTime = microtime(true) - $validationStartTime;

            // 2. Check customer limits
            $limitsStartTime = microtime(true);
            $this->limitService->checkCustomerWishlistLimit($request->getCustomerId(), $context);
            $limitsTime = microtime(true) - $limitsStartTime;

            // 3. Handle default wishlist
            $defaultHandlingStartTime = microtime(true);
            if ($request->isDefault()) {
                $this->unsetExistingDefaultWishlist($request->getCustomerId(), $context);
            }
            $defaultHandlingTime = microtime(true) - $defaultHandlingStartTime;

            // 4. Create wishlist
            $creationStartTime = microtime(true);
            $wishlistId = Uuid::randomHex();
            $wishlist = $this->createWishlistEntity($wishlistId, $request, $context);
            $creationTime = microtime(true) - $creationStartTime;

            // 5. Create share token for the new wishlist
            $shareStartTime = microtime(true);
            $this->shareService->createShare($wishlistId, $context);
            $shareTime = microtime(true) - $shareStartTime;

            // 6. Dispatch event and async message
            $eventStartTime = microtime(true);

            // Dispatch synchronous event
            $event = new WishlistCreatedEvent($wishlist, $context);
            $this->eventDispatcher->dispatch($event);

            // Dispatch asynchronous message for background processing
            $message = new WishlistCreatedMessage(
                $wishlist->getId(),
                $wishlist->getCustomerId(),
                $wishlist->getCreatedAt()
            );
            $this->messageBus->dispatch($message);

            $eventTime = microtime(true) - $eventStartTime;

            // 7. Clear cache
            $cacheStartTime = microtime(true);
            $this->cacheService->invalidateCustomerCache($request->getCustomerId());
            $cacheTime = microtime(true) - $cacheStartTime;

            // Commit transaction
            $this->wishlistRepository->commit();

            // Calculate total execution time
            $totalTime = microtime(true) - $startTime;

            // 8. Log creation with performance metrics
            $this->logger->info('Wishlist created', [
                'wishlistId' => $wishlistId,
                'customerId' => $request->getCustomerId(),
                'name' => $request->getName(),
                'type' => $request->getType(),
                'performance' => [
                    'totalTimeMs' => round($totalTime * 1000, 2),
                    'validationTimeMs' => round($validationTime * 1000, 2),
                    'limitsTimeMs' => round($limitsTime * 1000, 2),
                    'defaultHandlingTimeMs' => round($defaultHandlingTime * 1000, 2),
                    'creationTimeMs' => round($creationTime * 1000, 2),
                    'shareTimeMs' => round($shareTime * 1000, 2),
                    'eventTimeMs' => round($eventTime * 1000, 2),
                    'cacheTimeMs' => round($cacheTime * 1000, 2),
                ],
            ]);

            return WishlistResponse::fromEntity($wishlist);
        } catch (\Exception $e) {
            // Rollback transaction
            $this->wishlistRepository->rollback();

            // Calculate total execution time for failed operation
            $totalTime = microtime(true) - $startTime;

            $this->logger->error('Failed to create wishlist', [
                'error' => $e->getMessage(),
                'request' => $request->toArray(),
                'executionTimeMs' => round($totalTime * 1000, 2),
            ]);

            throw new WishlistException('Failed to create wishlist: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Update existing wishlist with optimistic locking.
     */
    public function updateWishlist(
        UpdateWishlistRequest $request,
        Context $context,
    ): WishlistResponse {
        // Start performance monitoring
        $startTime = microtime(true);

        try {
            // 1. Load wishlist with lock
            $loadStartTime = microtime(true);
            $wishlist = $this->wishlistRepository->search(
                (new Criteria([$request->getWishlistId()]))->addAssociation('items'),
                $context
            )->first();
            $loadTime = microtime(true) - $loadStartTime;

            if (!$wishlist) {
                throw new WishlistNotFoundException('Wishlist not found', ['wishlistId' => $request->getWishlistId()]);
            }

            // 2. Validate ownership and permissions
            $validationStartTime = microtime(true);
            $this->validator->validateOwnership($wishlist, $context);
            $this->validator->validateUpdateRequest($request, $wishlist, $context);
            $validationTime = microtime(true) - $validationStartTime;

            // 3. Check version for optimistic locking
            $lockingStartTime = microtime(true);
            if ($request->getVersion() && $request->getVersion() !== $wishlist->getVersion()) {
                throw new OptimisticLockException('Wishlist was modified by another process', ['currentVersion' => $wishlist->getVersion()]);
            }
            $lockingTime = microtime(true) - $lockingStartTime;

            // 4. Apply updates
            $updateData = $request->toArray();

            if (empty($updateData)) {
                return WishlistResponse::fromEntity($wishlist);
            }

            // 5. Update wishlist
            $updateStartTime = microtime(true);
            $this->wishlistRepository->update([array_merge(['id' => $request->getWishlistId()], $updateData)], $context);
            $updateTime = microtime(true) - $updateStartTime;

            // 6. Reload wishlist
            $reloadStartTime = microtime(true);
            $updatedWishlist = $this->loadWishlist($request->getWishlistId(), $context);
            $reloadTime = microtime(true) - $reloadStartTime;

            // 7. Clear cache
            $cacheStartTime = microtime(true);
            $this->cacheService->invalidateWishlistCache($request->getWishlistId());
            $cacheTime = microtime(true) - $cacheStartTime;

            // 8. Dispatch event
            $eventStartTime = microtime(true);
            $event = new WishlistUpdatedEvent($wishlist, $updatedWishlist, $context);
            $this->eventDispatcher->dispatch($event);
            $eventTime = microtime(true) - $eventStartTime;

            // Calculate total execution time
            $totalTime = microtime(true) - $startTime;

            // Log update with performance metrics
            $this->logger->info('Wishlist updated', [
                'wishlistId' => $request->getWishlistId(),
                'performance' => [
                    'totalTimeMs' => round($totalTime * 1000, 2),
                    'loadTimeMs' => round($loadTime * 1000, 2),
                    'validationTimeMs' => round($validationTime * 1000, 2),
                    'lockingTimeMs' => round($lockingTime * 1000, 2),
                    'updateTimeMs' => round($updateTime * 1000, 2),
                    'reloadTimeMs' => round($reloadTime * 1000, 2),
                    'cacheTimeMs' => round($cacheTime * 1000, 2),
                    'eventTimeMs' => round($eventTime * 1000, 2),
                ],
            ]);

            return WishlistResponse::fromEntity($updatedWishlist);
        } catch (\Exception $e) {
            // Calculate total execution time for failed operation
            $totalTime = microtime(true) - $startTime;

            $this->logger->error('Failed to update wishlist', [
                'wishlistId' => $request->getWishlistId(),
                'error' => $e->getMessage(),
                'executionTimeMs' => round($totalTime * 1000, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Delete wishlist with optional item transfer.
     */
    public function deleteWishlist(
        string $wishlistId,
        ?string $transferToWishlistId,
        Context $context,
    ): void {
        // Start performance monitoring
        $startTime = microtime(true);

        try {
            // 1. Load wishlist and validate ownership
            $loadStartTime = microtime(true);
            $wishlist = $this->loadWishlist($wishlistId, $context);
            $loadTime = microtime(true) - $loadStartTime;

            $validationStartTime = microtime(true);
            $this->validator->validateOwnership($wishlist, $context);
            $validationTime = microtime(true) - $validationStartTime;

            // 2. Check if this is the only wishlist
            $checkStartTime = microtime(true);
            $isOnlyWishlist = $wishlist->isDefault() && $this->isOnlyWishlist($wishlist->getCustomerId(), $context);
            $checkTime = microtime(true) - $checkStartTime;

            // Prevent deleting default wishlist if it's the only one
            if ($isOnlyWishlist) {
                throw new CannotDeleteDefaultWishlistException('Cannot delete the only wishlist');
            }

            // 3. Transfer items if requested
            $transferTime = 0;
            $itemsTransferred = 0;
            if ($transferToWishlistId && $wishlist->getItems()->count() > 0) {
                $transferStartTime = microtime(true);
                $this->transferItems($wishlist, $transferToWishlistId, $context);
                $transferTime = microtime(true) - $transferStartTime;
                $itemsTransferred = $wishlist->getItems()->count();
            }

            // 4. Delete wishlist
            $deleteStartTime = microtime(true);
            $this->wishlistRepository->delete([['id' => $wishlistId]], $context);
            $deleteTime = microtime(true) - $deleteStartTime;

            // 5. Clear cache
            $cacheStartTime = microtime(true);
            $this->cacheService->invalidateCustomerCache($wishlist->getCustomerId());
            $cacheTime = microtime(true) - $cacheStartTime;

            // 6. Dispatch event
            $eventStartTime = microtime(true);
            $event = new WishlistDeletedEvent($wishlist, $context);
            $this->eventDispatcher->dispatch($event);
            $eventTime = microtime(true) - $eventStartTime;

            // Calculate total execution time
            $totalTime = microtime(true) - $startTime;

            // 7. Log deletion with performance metrics
            $this->logger->info('Wishlist deleted', [
                'wishlistId' => $wishlistId,
                'customerId' => $wishlist->getCustomerId(),
                'itemsTransferred' => null !== $transferToWishlistId,
                'itemCount' => $itemsTransferred,
                'targetWishlistId' => $transferToWishlistId,
                'performance' => [
                    'totalTimeMs' => round($totalTime * 1000, 2),
                    'loadTimeMs' => round($loadTime * 1000, 2),
                    'validationTimeMs' => round($validationTime * 1000, 2),
                    'checkTimeMs' => round($checkTime * 1000, 2),
                    'transferTimeMs' => round($transferTime * 1000, 2),
                    'deleteTimeMs' => round($deleteTime * 1000, 2),
                    'cacheTimeMs' => round($cacheTime * 1000, 2),
                    'eventTimeMs' => round($eventTime * 1000, 2),
                ],
            ]);
        } catch (\Exception $e) {
            // Calculate total execution time for failed operation
            $totalTime = microtime(true) - $startTime;

            $this->logger->error('Failed to delete wishlist', [
                'wishlistId' => $wishlistId,
                'transferToWishlistId' => $transferToWishlistId,
                'error' => $e->getMessage(),
                'executionTimeMs' => round($totalTime * 1000, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Get or create default wishlist for customer.
     */
    public function getOrCreateDefaultWishlist(
        string $customerId,
        Context $context,
    ): WishlistEntity {
        // Use the WishlistCacheService to get or create the default wishlist
        return $this->cacheService->getCachedDefaultWishlist($customerId, function () use ($customerId, $context) {
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
     * Helper: Create wishlist entity using the builder pattern.
     */
    private function createWishlistEntity(
        string $wishlistId,
        CreateWishlistRequest $request,
        Context $context,
    ): WishlistEntity {
        // Use the builder to create the wishlist entity
        return $this->wishlistBuilder
            ->withId($wishlistId)
            ->withCustomerId($request->getCustomerId())
            ->withName($request->getName())
            ->withDescription($request->getDescription())
            ->withType($request->getType())
            ->withIsDefault($request->isDefault())
            ->withSalesChannelId($context->getSource()->getSalesChannelId())
            ->withLanguageId($context->getLanguageId())
            ->build($context);
    }

    /**
     * Helper: Load wishlist with associations.
     *
     * @param string     $wishlistId   The ID of the wishlist to load
     * @param Context    $context      The context
     * @param array|null $associations Optional associations to load, null for all default associations
     *
     * @return WishlistEntity The loaded wishlist
     *
     * @throws WishlistNotFoundException If the wishlist is not found
     */
    public function loadWishlist(
        string $wishlistId,
        Context $context,
        ?array $associations = null,
    ): WishlistEntity {
        // Try to get from cache first
        $cacheKey = "wishlist_{$wishlistId}";

        return $this->cacheService->getCachedWishlist($wishlistId, function () use ($wishlistId, $context, $associations) {
            $criteria = new Criteria([$wishlistId]);

            // If specific associations are requested, only load those
            if (null !== $associations) {
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
                throw new WishlistNotFoundException('Wishlist not found', ['wishlistId' => $wishlistId]);
            }

            return $wishlist;
        });
    }

    /**
     * Helper: Check if this is the only wishlist for the customer.
     */
    private function isOnlyWishlist(string $customerId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(2);

        return $this->wishlistRepository->search($criteria, $context)->getTotal() <= 1;
    }

    /**
     * Helper: Transfer items from one wishlist to another.
     */
    private function transferItems(WishlistEntity $sourceWishlist, string $targetWishlistId, Context $context): void
    {
        // Load target wishlist
        $targetWishlist = $this->loadWishlist($targetWishlistId, $context);

        // Check if target wishlist belongs to the same customer
        if ($sourceWishlist->getCustomerId() !== $targetWishlist->getCustomerId()) {
            throw new \InvalidArgumentException('Cannot transfer items to a wishlist owned by another customer');
        }

        // Get source items
        $sourceItems = $sourceWishlist->getItems();
        if (0 === $sourceItems->count()) {
            return; // Nothing to transfer
        }

        // Check target wishlist capacity
        $availableCapacity = self::MAX_ITEMS_PER_WISHLIST - $targetWishlist->getItems()->count();
        if ($availableCapacity < $sourceItems->count()) {
            $this->logger->warning('Not all items could be transferred due to wishlist capacity limit', [
                'sourceWishlistId' => $sourceWishlist->getId(),
                'targetWishlistId' => $targetWishlistId,
                'itemsToTransfer' => $sourceItems->count(),
                'availableCapacity' => $availableCapacity,
            ]);
        }

        // Prepare items to transfer (respecting capacity limit)
        $itemsToTransfer = [];
        $transferCount = 0;

        foreach ($sourceItems as $item) {
            // Check if we've reached capacity
            if ($transferCount >= $availableCapacity) {
                break;
            }

            // Check if item already exists in target wishlist
            $isDuplicate = $targetWishlist->getItems()->filter(
                fn ($targetItem) => $targetItem->getProductId() === $item->getProductId()
            )->count() > 0;

            if (!$isDuplicate) {
                $itemsToTransfer[] = [
                    'id' => $item->getId(),
                    'wishlistId' => $targetWishlistId,
                ];
                ++$transferCount;
            }
        }

        // Update items (transfer to new wishlist)
        if (!empty($itemsToTransfer)) {
            $this->wishlistRepository->update($itemsToTransfer, $context);

            $this->logger->info('Items transferred between wishlists', [
                'sourceWishlistId' => $sourceWishlist->getId(),
                'targetWishlistId' => $targetWishlistId,
                'transferredItems' => count($itemsToTransfer),
                'skippedItems' => $sourceItems->count() - count($itemsToTransfer),
            ]);
        }
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

    /**
     * Get all wishlists for a customer.
     *
     * @param string              $customerId The customer ID
     * @param Criteria            $criteria   The search criteria
     * @param SalesChannelContext $context    The context
     *
     * @return array The wishlists with pagination information
     */
    public function getWishlists(
        string $customerId,
        Criteria $criteria,
        SalesChannelContext $context,
    ): array {
        // Start performance monitoring
        $startTime = microtime(true);

        // Generate cache key based on customer ID and criteria
        $cacheKey = "customer_wishlists_{$customerId}_".$this->generateCriteriaHash($criteria);

        try {
            // Try to get from cache first
            return $this->cacheService->get($cacheKey, function () use ($customerId, $criteria, $context, $startTime) {
                // Add customer filter
                $criteria->addFilter(new EqualsFilter('customerId', $customerId));

                // Add default associations if no specific fields are requested
                if (empty($criteria->getFields())) {
                    $criteria->addAssociation('items.product.cover');
                }

                // Get wishlists from repository
                $searchStartTime = microtime(true);
                $result = $this->wishlistRepository->search($criteria, $context->getContext());
                $searchTime = microtime(true) - $searchStartTime;

                // Transform to array
                $transformStartTime = microtime(true);
                $wishlists = [];
                foreach ($result as $wishlist) {
                    // If specific fields are requested, only include those fields
                    if (!empty($criteria->getFields())) {
                        $wishlistData = [];
                        foreach ($criteria->getFields() as $field) {
                            // Handle nested fields like 'items.count'
                            if (false !== strpos($field, 'items.count')) {
                                $wishlistData['itemCount'] = $wishlist->getItems() ? $wishlist->getItems()->count() : 0;
                                continue;
                            }

                            // Handle standard fields
                            $getter = 'get'.ucfirst($field);
                            if (method_exists($wishlist, $getter)) {
                                $value = $wishlist->$getter();
                                // Format dates
                                if ($value instanceof \DateTimeInterface) {
                                    $value = $value->format(\DateTimeInterface::ATOM);
                                }
                                $wishlistData[$field] = $value;
                            }
                        }
                        $wishlists[] = $wishlistData;
                    } else {
                        // Default fields if no specific fields are requested
                        $wishlists[] = [
                            'id' => $wishlist->getId(),
                            'name' => $wishlist->getName(),
                            'description' => $wishlist->getDescription(),
                            'type' => $wishlist->getType(),
                            'isDefault' => $wishlist->isDefault(),
                            'itemCount' => $wishlist->getItems() ? $wishlist->getItems()->count() : 0,
                            'createdAt' => $wishlist->getCreatedAt()->format(\DateTimeInterface::ATOM),
                            'updatedAt' => $wishlist->getUpdatedAt() ? $wishlist->getUpdatedAt()->format(\DateTimeInterface::ATOM) : null,
                        ];
                    }
                }
                $transformTime = microtime(true) - $transformStartTime;

                // Calculate total execution time
                $totalTime = microtime(true) - $startTime;

                // Log performance metrics
                $this->logger->info('Wishlists retrieved', [
                    'customerId' => $customerId,
                    'count' => count($wishlists),
                    'performance' => [
                        'totalTimeMs' => round($totalTime * 1000, 2),
                        'searchTimeMs' => round($searchTime * 1000, 2),
                        'transformTimeMs' => round($transformTime * 1000, 2),
                    ],
                ]);

                // Prepare pagination information
                $page = $criteria->getOffset() / $criteria->getLimit() + 1;
                $pages = ceil($result->getTotal() / $criteria->getLimit());

                return [
                    'total' => $result->getTotal(),
                    'page' => $page,
                    'limit' => $criteria->getLimit(),
                    'pages' => $pages,
                    'wishlists' => $wishlists,
                ];
            });
        } catch (\Exception $e) {
            // Log error
            $this->logger->error('Failed to retrieve wishlists', [
                'customerId' => $customerId,
                'error' => $e->getMessage(),
                'executionTimeMs' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            // Return empty result on error
            return [
                'total' => 0,
                'page' => 1,
                'limit' => $criteria->getLimit(),
                'pages' => 0,
                'wishlists' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate a hash for a criteria object to use as part of a cache key.
     *
     * @param Criteria $criteria The criteria object
     *
     * @return string The hash
     */
    private function generateCriteriaHash(Criteria $criteria): string
    {
        $data = [
            'limit' => $criteria->getLimit(),
            'offset' => $criteria->getOffset(),
            'fields' => $criteria->getFields(),
            'filters' => [], // Add filter values if needed
            'sortings' => [], // Add sorting values if needed
        ];

        return md5(json_encode($data));
    }
}
