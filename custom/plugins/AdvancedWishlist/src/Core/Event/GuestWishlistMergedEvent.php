<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Event;

use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class GuestWishlistMergedEvent extends Event
{
    public function __construct(
        private readonly string $guestWishlistId,
        private readonly string $customerWishlistId,
        private readonly int $mergedItemsCount,
        private readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    public function getGuestWishlistId(): string
    {
        return $this->guestWishlistId;
    }

    public function getCustomerWishlistId(): string
    {
        return $this->customerWishlistId;
    }

    public function getMergedItemsCount(): int
    {
        return $this->mergedItemsCount;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public static function getEventData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('guestWishlistId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('customerWishlistId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('mergedItemsCount', new ScalarValueType(ScalarValueType::TYPE_INT))
            ->add('salesChannelContext', new ScalarValueType(ScalarValueType::TYPE_OBJECT));
    }
}
