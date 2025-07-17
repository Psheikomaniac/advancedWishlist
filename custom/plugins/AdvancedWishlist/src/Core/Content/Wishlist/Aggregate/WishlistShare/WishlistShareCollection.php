<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(WishlistShareEntity $entity)
 * @method void                     set(string $key, WishlistShareEntity $entity)
 * @method WishlistShareEntity[]    getIterator()
 * @method WishlistShareEntity[]    getElements()
 * @method WishlistShareEntity|null get(string $key)
 * @method WishlistShareEntity|null first()
 * @method WishlistShareEntity|null last()
 */
class WishlistShareCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WishlistShareEntity::class;
    }
}
