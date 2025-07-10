<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Event;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Symfony\Contracts\EventDispatcher\Event;

class WishlistItemAddedEvent extends Event
{
    public function __construct(
        private readonly WishlistEntity $wishlist,
        private readonly string $wishlistItemId,
        private readonly ProductEntity $product,
        private readonly Context $context
    ) {
    }

    public function getWishlist(): WishlistEntity
    {
        return $this->wishlist;
    }

    public function getWishlistItemId(): string
    {
        return $this->wishlistItemId;
    }

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getEventData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('wishlist', new EntityType(WishlistEntity::class))
            ->add('wishlistItemId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('product', new EntityType(ProductEntity::class))
            ->add('context', new ScalarValueType(ScalarValueType::TYPE_OBJECT));
    }
}
