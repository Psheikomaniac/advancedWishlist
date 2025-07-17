<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Event;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Symfony\Contracts\EventDispatcher\Event;

class PriceDropDetectedEvent extends Event
{
    public function __construct(
        private readonly WishlistItemEntity $wishlistItem,
        private readonly float $oldPrice,
        private readonly float $newPrice,
        private readonly Context $context,
    ) {
    }

    public function getWishlistItem(): WishlistItemEntity
    {
        return $this->wishlistItem;
    }

    public function getOldPrice(): float
    {
        return $this->oldPrice;
    }

    public function getNewPrice(): float
    {
        return $this->newPrice;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getEventData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('wishlistItem', new EntityType(WishlistItemEntity::class))
            ->add('oldPrice', new ScalarValueType(ScalarValueType::TYPE_FLOAT))
            ->add('newPrice', new ScalarValueType(ScalarValueType::TYPE_FLOAT))
            ->add('context', new ScalarValueType(ScalarValueType::TYPE_OBJECT));
    }
}
