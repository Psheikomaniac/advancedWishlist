<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Event;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Symfony\Contracts\EventDispatcher\Event;

class WishlistUpdatedEvent extends Event
{
    public function __construct(
        private readonly WishlistEntity $originalWishlist,
        private readonly WishlistEntity $updatedWishlist,
        private readonly Context $context
    ) {
    }

    public function getOriginalWishlist(): WishlistEntity
    {
        return $this->originalWishlist;
    }

    public function getUpdatedWishlist(): WishlistEntity
    {
        return $this->updatedWishlist;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getEventData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('originalWishlist', new EntityType(WishlistEntity::class))
            ->add('updatedWishlist', new EntityType(WishlistEntity::class))
            ->add('context', new ScalarValueType(ScalarValueType::TYPE_OBJECT));
    }
}
