<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\WishlistShareView;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(WishlistShareViewEntity $entity)
 * @method void                         set(string $key, WishlistShareViewEntity $entity)
 * @method WishlistShareViewEntity[]    getIterator()
 * @method WishlistShareViewEntity[]    getElements()
 * @method WishlistShareViewEntity|null get(string $key)
 * @method WishlistShareViewEntity|null first()
 * @method WishlistShareViewEntity|null last()
 */
class WishlistShareViewCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WishlistShareViewEntity::class;
    }
}
