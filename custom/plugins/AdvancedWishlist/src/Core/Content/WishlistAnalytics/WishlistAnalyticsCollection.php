<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\WishlistAnalytics;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(WishlistAnalyticsEntity $entity)
 * @method void                         set(string $key, WishlistAnalyticsEntity $entity)
 * @method WishlistAnalyticsEntity[]    getIterator()
 * @method WishlistAnalyticsEntity[]    getElements()
 * @method WishlistAnalyticsEntity|null get(string $key)
 * @method WishlistAnalyticsEntity|null first()
 * @method WishlistAnalyticsEntity|null last()
 */
class WishlistAnalyticsCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WishlistAnalyticsEntity::class;
    }
}
