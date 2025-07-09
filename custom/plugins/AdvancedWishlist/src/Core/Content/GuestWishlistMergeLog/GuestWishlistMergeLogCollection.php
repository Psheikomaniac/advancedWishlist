<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\GuestWishlistMergeLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                add(GuestWishlistMergeLogEntity $entity)
 * @method void                                set(string $key, GuestWishlistMergeLogEntity $entity)
 * @method GuestWishlistMergeLogEntity[]       getIterator()
 * @method GuestWishlistMergeLogEntity[]       getElements()
 * @method GuestWishlistMergeLogEntity|null    get(string $key)
 * @method GuestWishlistMergeLogEntity|null    first()
 * @method GuestWishlistMergeLogEntity|null    last()
 */
class GuestWishlistMergeLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return GuestWishlistMergeLogEntity::class;
    }
}
