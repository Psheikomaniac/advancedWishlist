<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Performance;

use AdvancedWishlist\Core\Content\Wishlist\WishlistCollection;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * Enterprise Lazy Objects Service for PHP 8.4
 * Implements lazy loading patterns for optimal performance with large datasets.
 */
class LazyObjectService
{
    public function __construct(
        private EntityRepository $wishlistRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Create a lazy-loaded wishlist that defers expensive operations until accessed.
     */
    public function createLazyWishlist(string $wishlistId, Context $context): WishlistEntity
    {
        $reflector = new \ReflectionClass(WishlistEntity::class);

        return $reflector->newLazyProxy(
            function (WishlistEntity $proxy) use ($wishlistId, $context): void {
                $this->logger->debug('Lazy wishlist initialization triggered', [
                    'wishlist_id' => $wishlistId,
                    'memory_before' => memory_get_usage(true),
                ]);

                $startTime = microtime(true);

                // Load wishlist with basic associations
                $criteria = new Criteria([$wishlistId]);
                $criteria->addAssociation('customer');
                $criteria->addAssociation('items.product');

                $wishlist = $this->wishlistRepository->search($criteria, $context)->first();

                if (!$wishlist) {
                    throw new \RuntimeException("Wishlist {$wishlistId} not found");
                }

                // Copy all properties to the proxy
                $this->copyPropertiesToProxy($wishlist, $proxy);

                $loadTime = microtime(true) - $startTime;
                $memoryAfter = memory_get_usage(true);

                $this->logger->debug('Lazy wishlist loaded', [
                    'wishlist_id' => $wishlistId,
                    'load_time' => round($loadTime * 1000, 2).'ms',
                    'memory_after' => $memoryAfter,
                    'items_count' => $wishlist->getItems()?->count() ?? 0,
                ]);
            }
        );
    }

    /**
     * Create a lazy-loaded wishlist ghost (more memory efficient).
     */
    public function createLazyWishlistGhost(string $wishlistId, Context $context): WishlistEntity
    {
        $reflector = new \ReflectionClass(WishlistEntity::class);

        return $reflector->newLazyGhost(
            function (WishlistEntity $ghost) use ($wishlistId, $context): void {
                $this->logger->debug('Lazy wishlist ghost initialization triggered', [
                    'wishlist_id' => $wishlistId,
                ]);

                $startTime = microtime(true);

                // Load minimal wishlist data first
                $criteria = new Criteria([$wishlistId]);
                $basicWishlist = $this->wishlistRepository->search($criteria, $context)->first();

                if (!$basicWishlist) {
                    throw new \RuntimeException("Wishlist {$wishlistId} not found");
                }

                // Initialize ghost with minimal constructor call
                $this->initializeGhost($ghost, $basicWishlist);

                $loadTime = microtime(true) - $startTime;

                $this->logger->debug('Lazy wishlist ghost initialized', [
                    'wishlist_id' => $wishlistId,
                    'load_time' => round($loadTime * 1000, 2).'ms',
                ]);
            }
        );
    }

    /**
     * Create lazy-loaded wishlist collection for customers.
     */
    public function createLazyCustomerWishlists(string $customerId, Context $context): WishlistCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(100); // Prevent memory issues

        $basicWishlists = $this->wishlistRepository->search($criteria, $context);
        $lazyWishlists = new WishlistCollection();

        foreach ($basicWishlists as $basicWishlist) {
            $lazyWishlist = $this->createLazyWishlistWithItems($basicWishlist->getId(), $context);
            $lazyWishlists->add($lazyWishlist);
        }

        $this->logger->info('Created lazy wishlist collection', [
            'customer_id' => $customerId,
            'wishlist_count' => $lazyWishlists->count(),
        ]);

        return $lazyWishlists;
    }

    /**
     * Create lazy wishlist that loads items only when accessed.
     */
    public function createLazyWishlistWithItems(string $wishlistId, Context $context): WishlistEntity
    {
        $reflector = new \ReflectionClass(WishlistEntity::class);

        return $reflector->newLazyGhost(
            function (WishlistEntity $ghost) use ($wishlistId, $context): void {
                // Load wishlist with items only when items are actually accessed
                $criteria = new Criteria([$wishlistId]);
                $criteria->addAssociation('items.product.cover');
                $criteria->addAssociation('items.product.prices');
                $criteria->addAssociation('shareInfo');

                $fullWishlist = $this->wishlistRepository->search($criteria, $context)->first();

                if (!$fullWishlist) {
                    throw new \RuntimeException("Wishlist {$wishlistId} not found");
                }

                $this->initializeGhost($ghost, $fullWishlist);

                $this->logger->debug('Lazy wishlist with items loaded', [
                    'wishlist_id' => $wishlistId,
                    'items_count' => $fullWishlist->getItems()?->count() ?? 0,
                ]);
            }
        );
    }

    /**
     * Create lazy-loaded analytics data.
     */
    public function createLazyAnalyticsData(string $customerId, Context $context): \Closure
    {
        return function () use ($customerId, $context) {
            $this->logger->debug('Loading lazy analytics data', ['customer_id' => $customerId]);

            $startTime = microtime(true);

            // Expensive analytics calculation
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customerId', $customerId));
            $criteria->addAssociation('items.product.prices');

            $wishlists = $this->wishlistRepository->search($criteria, $context);

            $analytics = [
                'total_wishlists' => $wishlists->count(),
                'total_items' => 0,
                'total_value' => 0.0,
                'avg_items_per_wishlist' => 0,
                'most_expensive_item' => 0.0,
                'categories' => [],
                'price_alerts' => 0,
                'shared_wishlists' => 0,
            ];

            foreach ($wishlists as $wishlist) {
                if ($wishlist->getItems()) {
                    $analytics['total_items'] += $wishlist->getItems()->count();

                    foreach ($wishlist->getItems() as $item) {
                        if ($item->getProduct() && $item->getProduct()->getPrice()) {
                            $price = $item->getProduct()->getPrice()->getGross();
                            $analytics['total_value'] += $price * $item->getQuantity();
                            $analytics['most_expensive_item'] = max($analytics['most_expensive_item'], $price);
                        }

                        if ($item->getPriceAlertActive()) {
                            ++$analytics['price_alerts'];
                        }
                    }
                }

                if ('shared' === $wishlist->getType()) {
                    ++$analytics['shared_wishlists'];
                }
            }

            if ($analytics['total_wishlists'] > 0) {
                $analytics['avg_items_per_wishlist'] = round($analytics['total_items'] / $analytics['total_wishlists'], 2);
            }

            $loadTime = microtime(true) - $startTime;

            $this->logger->info('Lazy analytics data loaded', [
                'customer_id' => $customerId,
                'load_time' => round($loadTime * 1000, 2).'ms',
                'total_wishlists' => $analytics['total_wishlists'],
                'total_items' => $analytics['total_items'],
            ]);

            return $analytics;
        };
    }

    /**
     * Create lazy-loaded product recommendations.
     */
    public function createLazyRecommendations(string $customerId, Context $context): \Closure
    {
        return function () use ($customerId) {
            $this->logger->debug('Loading lazy product recommendations', ['customer_id' => $customerId]);

            // This would typically involve complex ML algorithms or API calls
            // For now, we'll simulate an expensive operation

            $startTime = microtime(true);

            // Simulate expensive recommendation calculation
            usleep(100000); // 100ms delay to simulate API call

            $recommendations = [
                'similar_products' => [
                    ['id' => 'prod-1', 'name' => 'Similar Product 1', 'score' => 0.95],
                    ['id' => 'prod-2', 'name' => 'Similar Product 2', 'score' => 0.89],
                    ['id' => 'prod-3', 'name' => 'Similar Product 3', 'score' => 0.82],
                ],
                'trending_products' => [
                    ['id' => 'trend-1', 'name' => 'Trending Product 1', 'score' => 0.88],
                    ['id' => 'trend-2', 'name' => 'Trending Product 2', 'score' => 0.75],
                ],
                'price_drop_alerts' => [
                    ['id' => 'alert-1', 'name' => 'Product on Sale', 'discount' => 25],
                ],
            ];

            $loadTime = microtime(true) - $startTime;

            $this->logger->info('Lazy recommendations loaded', [
                'customer_id' => $customerId,
                'load_time' => round($loadTime * 1000, 2).'ms',
                'recommendations_count' => count($recommendations['similar_products']),
            ]);

            return $recommendations;
        };
    }

    /**
     * Copy properties from source to proxy object.
     */
    private function copyPropertiesToProxy(WishlistEntity $source, WishlistEntity $proxy): void
    {
        $sourceReflection = new \ReflectionObject($source);

        foreach ($sourceReflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);
            $value = $property->getValue($source);

            try {
                $property->setValue($proxy, $value);
            } catch (\Error $e) {
                // Skip properties that can't be set (readonly, etc.)
                $this->logger->debug('Could not copy property', [
                    'property' => $property->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Initialize ghost object with minimal data.
     */
    private function initializeGhost(WishlistEntity $ghost, WishlistEntity $source): void
    {
        // Copy essential properties for basic functionality
        $essentialProperties = ['id', 'customerId', 'name', 'type', 'isDefault', 'createdAt', 'updatedAt'];

        $sourceReflection = new \ReflectionObject($source);

        foreach ($essentialProperties as $propertyName) {
            try {
                $property = $sourceReflection->getProperty($propertyName);
                $property->setAccessible(true);
                $value = $property->getValue($source);

                $ghostProperty = new \ReflectionProperty($ghost, $propertyName);
                $ghostProperty->setAccessible(true);
                $ghostProperty->setValue($ghost, $value);
            } catch (\ReflectionException $e) {
                $this->logger->debug('Could not initialize ghost property', [
                    'property' => $propertyName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Performance metrics for lazy loading.
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'lazy_objects_created' => $this->getCreatedCount(),
            'memory_saved' => $this->calculateMemorySavings(),
            'average_load_time' => $this->getAverageLoadTime(),
        ];
    }

    private function getCreatedCount(): int
    {
        // Implementation would track created lazy objects
        return 0;
    }

    private function calculateMemorySavings(): string
    {
        // Implementation would calculate memory savings
        return '0 MB';
    }

    private function getAverageLoadTime(): string
    {
        // Implementation would track average load times
        return '0 ms';
    }
}
