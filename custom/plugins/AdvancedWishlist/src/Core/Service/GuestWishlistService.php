<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\DTO\Request\AddItemRequest;
use AdvancedWishlist\Core\Content\GuestWishlist\GuestWishlistEntity;
use AdvancedWishlist\Core\Event\GuestWishlistCreatedEvent;
use AdvancedWishlist\Core\Event\GuestWishlistMergedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use AdvancedWishlist\Core\Exception\GuestWishlistLimitException;

class GuestWishlistService
{
    private const string COOKIE_NAME = 'guest_wishlist_id';
    private const int DEFAULT_TTL = 2592000; // 30 days
    private const int MAX_ITEMS_GUEST = 50;
    private const int CLEANUP_BATCH_SIZE = 100;

    public function __construct(
        private EntityRepository $guestWishlistRepository,
        private EntityRepository $wishlistRepository,
        private WishlistService $wishlistService,
        private GuestIdentifierService $identifierService,
        private RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private int $guestWishlistTtl = self::DEFAULT_TTL
    ) {}

    /**
     * Get or create guest wishlist
     */
    public function getOrCreateGuestWishlist(
        SalesChannelContext $context
    ): GuestWishlistEntity {
        // 1. Check if user is logged in
        if ($context->getCustomer()) {
            throw new \LogicException('Guest wishlist not available for logged in users');
        }

        // 2. Get guest identifier
        $guestId = $this->identifierService->getOrCreateGuestId($context);

        // 3. Try to load existing guest wishlist
        $existingWishlist = $this->findGuestWishlistByIdentifier($guestId, $context);

        if ($existingWishlist) {
            // Extend TTL
            $this->extendGuestWishlistTtl($existingWishlist, $context);
            return $existingWishlist;
        }

        // 4. Create new guest wishlist
        return $this->createGuestWishlist($guestId, $context);
    }

    /**
     * Create new guest wishlist
     */
    private function createGuestWishlist(
        string $guestId,
        SalesChannelContext $context
    ): GuestWishlistEntity {
        $wishlistId = Uuid::randomHex();

        $data = [
            'id' => $wishlistId,
            'guestId' => $guestId,
            'sessionId' => $context->getToken(),
            'salesChannelId' => $context->getSalesChannelId(),
            'languageId' => $context->getLanguageId(),
            'currencyId' => $context->getCurrencyId(),
            'name' => 'My Wishlist',
            'items' => [],
            'expiresAt' => $this->calculateExpiryDate(),
            'ipAddress' => $this->getIpAddress(),
            'userAgent' => $this->getUserAgent(),
        ];

        $this->guestWishlistRepository->create([$data], $context->getContext());

        // Set cookie
        // $this->setGuestWishlistCookie($wishlistId);

        // Dispatch event
        $event = new GuestWishlistCreatedEvent($wishlistId, $guestId, $context);
        $this->eventDispatcher->dispatch($event);

        $this->logger->info('Guest wishlist created', [
            'wishlistId' => $wishlistId,
            'guestId' => $guestId,
        ]);

        return $this->loadGuestWishlist($wishlistId, $context->getContext());
    }

    /**
     * Add item to guest wishlist
     */
    public function addItemToGuestWishlist(
        string $productId,
        array $options,
        SalesChannelContext $context
    ): void {
        $guestWishlist = $this->getOrCreateGuestWishlist($context);

        // Check item limit
        if (count($guestWishlist->getItems()) >= self::MAX_ITEMS_GUEST) {
            throw new GuestWishlistLimitException(
                'Maximum number of items reached for guest wishlist',
                ['limit' => self::MAX_ITEMS_GUEST]
            );
        }

        // Check for duplicate
        $existingItem = $this->findItemInGuestWishlist($guestWishlist, $productId);
        if ($existingItem) {
            // Update quantity instead
            $this->updateGuestWishlistItem(
                $guestWishlist->getId(),
                $existingItem['id'],
                ['quantity' => $existingItem['quantity'] + 1],
                $context->getContext()
            );
            return;
        }

        // Add new item
        $itemId = Uuid::randomHex();
        $items = $guestWishlist->getItems();
        $items[] = [
            'id' => $itemId,
            'productId' => $productId,
            'quantity' => $options['quantity'] ?? 1,
            'note' => $options['note'] ?? null,
            'addedAt' => new \DateTime(),
            // 'productSnapshot' => $this->createProductSnapshot($productId, $context),
        ];

        $this->guestWishlistRepository->update([
            [
                'id' => $guestWishlist->getId(),
                'items' => $items,
                'updatedAt' => new \DateTime(),
            ]
        ], $context->getContext());

        // Track analytics
        // $this->trackGuestActivity('item_added', [
        //     'productId' => $productId,
        //     'wishlistId' => $guestWishlist->getId(),
        // ]);
    }

    /**
     * Remove item from guest wishlist
     */
    public function removeItemFromGuestWishlist(
        string $itemId,
        SalesChannelContext $context
    ): void {
        $guestWishlist = $this->getOrCreateGuestWishlist($context);

        $items = array_filter(
            $guestWishlist->getItems(),
            fn($item) => $item['id'] !== $itemId
        );

        $this->guestWishlistRepository->update([
            [
                'id' => $guestWishlist->getId(),
                'items' => array_values($items),
                'updatedAt' => new \DateTime(),
            ]
        ], $context->getContext());
    }

    /**
     * Merge guest wishlist with customer wishlist after login/registration
     */
    public function mergeGuestWishlistToCustomer(
        string $customerId,
        SalesChannelContext $context
    ): void {
        // 1. Get guest wishlist
        $guestId = $this->identifierService->getGuestIdFromCookie();
        if (!$guestId) {
            return; // No guest wishlist to merge
        }

        $guestWishlist = $this->findGuestWishlistByIdentifier($guestId, $context);
        if (!$guestWishlist || empty($guestWishlist->getItems())) {
            return; // Nothing to merge
        }

        // 2. Get or create customer's default wishlist
        $customerWishlist = $this->wishlistService->getOrCreateDefaultWishlist(
            $customerId,
            $context->getContext()
        );

        // 3. Merge items
        $mergedCount = 0;
        $skippedCount = 0;

        foreach ($guestWishlist->getItems() as $guestItem) {
            try {
                // Check if product already in customer wishlist
                // if ($this->wishlistService->hasProduct($customerWishlist->getId(), $guestItem['productId'])) {
                //     $skippedCount++;
                //     continue;
                // }

                // Add to customer wishlist
                $this->wishlistService->addItem(
                    new AddItemRequest([
                        'wishlistId' => $customerWishlist->getId(),
                        'productId' => $guestItem['productId'],
                        'quantity' => $guestItem['quantity'],
                        'note' => $guestItem['note'],
                    ]),
                    $context->getContext()
                );

                $mergedCount++;
            } catch (\Exception $e) {
                $this->logger->warning('Failed to merge guest wishlist item', [
                    'productId' => $guestItem['productId'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 4. Store merge record for analytics
        // $this->recordMergeAction($guestWishlist, $customerWishlist, [
        //     'mergedItems' => $mergedCount,
        //     'skippedItems' => $skippedCount,
        // ]);

        // 5. Delete guest wishlist
        $this->deleteGuestWishlist($guestWishlist->getId(), $context->getContext());

        // 6. Clear guest cookie
        $this->clearGuestWishlistCookie();

        // 7. Dispatch event
        $event = new GuestWishlistMergedEvent(
            $guestWishlist->getId(),
            $customerWishlist->getId(),
            $mergedCount,
            $context
        );
        $this->eventDispatcher->dispatch($event);

        $this->logger->info('Guest wishlist merged', [
            'guestWishlistId' => $guestWishlist->getId(),
            'customerWishlistId' => $customerWishlist->getId(),
            'mergedItems' => $mergedCount,
            'skippedItems' => $skippedCount,
        ]);
    }

    /**
     * Send reminder email to guest
     */
    public function sendGuestReminder(
        string $guestWishlistId,
        string $email,
        Context $context
    ): void {
        $guestWishlist = $this->loadGuestWishlist($guestWishlistId, $context);

        if (!$guestWishlist || empty($guestWishlist->getItems())) {
            throw new \InvalidArgumentException('Guest wishlist not found or empty');
        }

        // Generate secure link for guest to access wishlist
        // $token = $this->generateGuestAccessToken($guestWishlistId);
        // $accessUrl = $this->generateGuestAccessUrl($token);

        // Send email
        // $this->emailService->sendGuestWishlistReminder(
        //     $email,
        //     $guestWishlist,
        //     $accessUrl,
        //     $context
        // );

        // Record email sent
        $this->guestWishlistRepository->update([
            [
                'id' => $guestWishlistId,
                'reminderSentAt' => new \DateTime(),
                'reminderEmail' => $email,
            ]
        ], $context);
    }

    /**
     * Clean up expired guest wishlists
     */
    public function cleanupExpiredGuestWishlists(Context $context): int
    {
        $deletedCount = 0;
        $offset = 0;

        do {
            $criteria = new Criteria();
            $criteria->addFilter(new RangeFilter('expiresAt', [
                RangeFilter::LTE => (new \DateTime())->format('c'),
            ]));
            $criteria->setLimit(self::CLEANUP_BATCH_SIZE);
            $criteria->setOffset($offset);

            $expiredWishlists = $this->guestWishlistRepository->search($criteria, $context);

            if ($expiredWishlists->count() === 0) {
                break;
            }

            $ids = array_map(
                fn($wishlist) => ['id' => $wishlist->getId()],
                $expiredWishlists->getElements()
            );

            $this->guestWishlistRepository->delete($ids, $context);
            $deletedCount += count($ids);

            $offset += self::CLEANUP_BATCH_SIZE;

        } while ($expiredWishlists->count() === self::CLEANUP_BATCH_SIZE);

        $this->logger->info('Cleaned up expired guest wishlists', [
            'count' => $deletedCount,
        ]);

        return $deletedCount;
    }

    /**
     * Helper: Set guest wishlist cookie
     */
    private function setGuestWishlistCookie(string $wishlistId): void
    {
        $response = $this->requestStack->getCurrentRequest()->attributes->get('_response');

        if (!$response) {
            return;
        }

        $cookie = Cookie::create(self::COOKIE_NAME)
            ->withValue($wishlistId)
            ->withExpires(time() + $this->guestWishlistTtl)
            ->withPath('/')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite('Lax');

        $response->headers->setCookie($cookie);
    }

    /**
     * Helper: Calculate expiry date
     */
    private function calculateExpiryDate(): \DateTime
    {
        return (new \DateTime())->add(
            new \DateInterval('PT' . $this->guestWishlistTtl . 'S')
        );
    }

    /**
     * Helper: Create product snapshot for offline viewing
     */
    private function createProductSnapshot(
        string $productId,
        SalesChannelContext $context
    ): array {
        // $product = $this->productRepository->search(
        //     new Criteria([$productId]),
        //     $context
        // )->first();

        // if (!$product) {
        //     return [];
        // }

        return [
            // 'name' => $product->getTranslated()['name'],
            // 'productNumber' => $product->getProductNumber(),
            // 'price' => $product->getCheapestPrice()->getGross(),
            // 'image' => $product->getCover()?->getMedia()?->getUrl(),
            // 'manufacturer' => $product->getManufacturer()?->getTranslated()['name'],
        ];
    }

    private function findGuestWishlistByIdentifier(string $guestId, SalesChannelContext $context): ?GuestWishlistEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('guestId', $guestId));
        $criteria->setLimit(1);

        return $this->guestWishlistRepository->search($criteria, $context->getContext())->first();
    }

    private function extendGuestWishlistTtl(GuestWishlistEntity $wishlist, SalesChannelContext $context): void
    {
        $this->guestWishlistRepository->update([[
            'id' => $wishlist->getId(),
            'expiresAt' => $this->calculateExpiryDate(),
        ]], $context->getContext());
    }

    private function getIpAddress(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getClientIp();
    }

    private function getUserAgent(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->headers->get('User-Agent');
    }

    private function loadGuestWishlist(string $wishlistId, Context $context): GuestWishlistEntity
    {
        $criteria = new Criteria([$wishlistId]);
        $wishlist = $this->guestWishlistRepository->search($criteria, $context)->first();

        if (!$wishlist) {
            throw new GuestWishlistLimitException('Guest wishlist not found');
        }

        return $wishlist;
    }

    private function findItemInGuestWishlist(GuestWishlistEntity $guestWishlist, string $productId): ?array
    {
        foreach ($guestWishlist->getItems() as $item) {
            if ($item['productId'] === $productId) {
                return $item;
            }
        }

        return null;
    }

    private function updateGuestWishlistItem(string $wishlistId, string $itemId, array $data, Context $context): void
    {
        $guestWishlist = $this->loadGuestWishlist($wishlistId, $context);
        $items = $guestWishlist->getItems();

        foreach ($items as $key => $item) {
            if ($item['id'] === $itemId) {
                $items[$key] = array_merge($item, $data);
                break;
            }
        }

        $this->guestWishlistRepository->update([[
            'id' => $wishlistId,
            'items' => $items,
            'updatedAt' => new \DateTime(),
        ]], $context);
    }

    private function deleteGuestWishlist(string $wishlistId, Context $context): void
    {
        $this->guestWishlistRepository->delete([['id' => $wishlistId]], $context);
    }

    private function clearGuestWishlistCookie(): void
    {
        $response = $this->requestStack->getCurrentRequest()?->attributes->get('_response');

        if (!$response) {
            return;
        }

        $response->headers->clearCookie(self::COOKIE_NAME, '/');
    }
}
