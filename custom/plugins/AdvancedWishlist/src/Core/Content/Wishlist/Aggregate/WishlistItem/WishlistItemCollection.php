<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(WishlistItemEntity $entity)
 * @method void                  set(string $key, WishlistItemEntity $entity)
 * @method WishlistItemEntity[]    getIterator()
 * @method WishlistItemEntity[]    getElements()
 * @method WishlistItemEntity|null get(string $key)
 * @method WishlistItemEntity|null first()
 * @method WishlistItemEntity|null last()
 */
class WishlistItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WishlistItemEntity::class;
    }
}
