<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\GuestWishlist;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(GuestWishlistEntity $entity)
 * @method void                     set(string $key, GuestWishlistEntity $entity)
 * @method GuestWishlistEntity[]    getIterator()
 * @method GuestWishlistEntity[]    getElements()
 * @method GuestWishlistEntity|null get(string $key)
 * @method GuestWishlistEntity|null first()
 * @method GuestWishlistEntity|null last()
 */
class GuestWishlistCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return GuestWishlistEntity::class;
    }
}
