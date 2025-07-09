<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(WishlistEntity $entity)
 * @method void              set(string $key, WishlistEntity $entity)
 * @method WishlistEntity[]    getIterator()
 * @method WishlistEntity[]    getElements()
 * @method WishlistEntity|null get(string $key)
 * @method WishlistEntity|null first()
 * @method WishlistEntity|null last()
 */
class WishlistCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WishlistEntity::class;
    }
}
