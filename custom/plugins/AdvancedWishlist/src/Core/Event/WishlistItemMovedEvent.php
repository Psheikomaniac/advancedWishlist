<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Event;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemEntity;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Symfony\Contracts\EventDispatcher\Event;

class WishlistItemMovedEvent extends Event
{
    public function __construct(
        private readonly WishlistEntity $sourceWishlist,
        private readonly WishlistEntity $targetWishlist,
        private readonly WishlistItemEntity $movedItem,
        private readonly bool $isCopy,
        private readonly Context $context,
    ) {
    }

    public function getSourceWishlist(): WishlistEntity
    {
        return $this->sourceWishlist;
    }

    public function getTargetWishlist(): WishlistEntity
    {
        return $this->targetWishlist;
    }

    public function getMovedItem(): WishlistItemEntity
    {
        return $this->movedItem;
    }

    public function isCopy(): bool
    {
        return $this->isCopy;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getEventData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('sourceWishlist', new EntityType(WishlistEntity::class))
            ->add('targetWishlist', new EntityType(WishlistEntity::class))
            ->add('movedItem', new EntityType(WishlistItemEntity::class))
            ->add('isCopy', new ScalarValueType(ScalarValueType::TYPE_BOOL))
            ->add('context', new ScalarValueType(ScalarValueType::TYPE_OBJECT));
    }
}
