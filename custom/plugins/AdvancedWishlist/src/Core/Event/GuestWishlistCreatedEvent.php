<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class GuestWishlistCreatedEvent extends Event
{
    public function __construct(
        private readonly string $wishlistId,
        private readonly string $guestId,
        private readonly SalesChannelContext $salesChannelContext
    ) {
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getGuestId(): string
    {
        return $this->guestId;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public static function getEventData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('wishlistId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('guestId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('salesChannelContext', new ScalarValueType(ScalarValueType::TYPE_OBJECT));
    }
}
