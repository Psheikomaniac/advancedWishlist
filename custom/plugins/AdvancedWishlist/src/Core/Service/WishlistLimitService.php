<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use AdvancedWishlist\Core\Exception\WishlistLimitExceededException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class WishlistLimitService
{
    private const int DEFAULT_MAX_WISHLISTS_PER_CUSTOMER = 10;
    private const int DEFAULT_MAX_ITEMS_PER_WISHLIST = 100;

    public function __construct(
        private EntityRepository $wishlistRepository,
        private EntityRepository $wishlistItemRepository,
        private LoggerInterface $logger,
        private int $maxWishlistsPerCustomer = self::DEFAULT_MAX_WISHLISTS_PER_CUSTOMER,
        private int $maxItemsPerWishlist = self::DEFAULT_MAX_ITEMS_PER_WISHLIST,
    ) {
    }

    /**
     * Check if customer has reached the maximum number of wishlists.
     */
    public function checkCustomerWishlistLimit(string $customerId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        $count = $this->wishlistRepository->search($criteria, $context)->getTotal();

        if ($count >= $this->maxWishlistsPerCustomer) {
            $this->logger->warning('Customer has reached wishlist limit', [
                'customerId' => $customerId,
                'limit' => $this->maxWishlistsPerCustomer,
                'current' => $count,
            ]);

            throw new WishlistLimitExceededException('Maximum number of wishlists reached', ['limit' => $this->maxWishlistsPerCustomer, 'current' => $count]);
        }
    }

    /**
     * Check if wishlist has reached the maximum number of items.
     */
    public function checkWishlistItemLimit(string $wishlistId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('wishlistId', $wishlistId));

        $count = $this->wishlistItemRepository->search($criteria, $context)->getTotal();

        if ($count >= $this->maxItemsPerWishlist) {
            $this->logger->warning('Wishlist has reached item limit', [
                'wishlistId' => $wishlistId,
                'limit' => $this->maxItemsPerWishlist,
                'current' => $count,
            ]);

            throw new WishlistLimitExceededException('Maximum number of items in wishlist reached', ['limit' => $this->maxItemsPerWishlist, 'current' => $count]);
        }
    }

    /**
     * Get remaining capacity for a wishlist.
     */
    public function getWishlistRemainingCapacity(string $wishlistId, Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('wishlistId', $wishlistId));

        $count = $this->wishlistItemRepository->search($criteria, $context)->getTotal();

        return max(0, $this->maxItemsPerWishlist - $count);
    }

    /**
     * Get remaining capacity for a customer.
     */
    public function getCustomerRemainingCapacity(string $customerId, Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        $count = $this->wishlistRepository->search($criteria, $context)->getTotal();

        return max(0, $this->maxWishlistsPerCustomer - $count);
    }

    /**
     * Get limits information.
     */
    public function getLimitsInfo(string $customerId, Context $context): array
    {
        $wishlistCriteria = new Criteria();
        $wishlistCriteria->addFilter(new EqualsFilter('customerId', $customerId));

        $wishlists = $this->wishlistRepository->search($wishlistCriteria, $context);
        $wishlistCount = $wishlists->getTotal();

        $itemCounts = [];
        $totalItems = 0;

        foreach ($wishlists as $wishlist) {
            $itemCriteria = new Criteria();
            $itemCriteria->addFilter(new EqualsFilter('wishlistId', $wishlist->getId()));

            $itemCount = $this->wishlistItemRepository->search($itemCriteria, $context)->getTotal();
            $totalItems += $itemCount;

            $itemCounts[$wishlist->getId()] = [
                'name' => $wishlist->getName(),
                'count' => $itemCount,
                'limit' => $this->maxItemsPerWishlist,
                'remaining' => $this->maxItemsPerWishlist - $itemCount,
            ];
        }

        return [
            'wishlists' => [
                'count' => $wishlistCount,
                'limit' => $this->maxWishlistsPerCustomer,
                'remaining' => $this->maxWishlistsPerCustomer - $wishlistCount,
            ],
            'items' => [
                'total' => $totalItems,
                'byWishlist' => $itemCounts,
            ],
        ];
    }

    /**
     * Set custom limits.
     */
    public function setLimits(int $maxWishlistsPerCustomer, int $maxItemsPerWishlist): void
    {
        $this->maxWishlistsPerCustomer = $maxWishlistsPerCustomer;
        $this->maxItemsPerWishlist = $maxItemsPerWishlist;

        $this->logger->info('Wishlist limits updated', [
            'maxWishlistsPerCustomer' => $maxWishlistsPerCustomer,
            'maxItemsPerWishlist' => $maxItemsPerWishlist,
        ]);
    }
}
