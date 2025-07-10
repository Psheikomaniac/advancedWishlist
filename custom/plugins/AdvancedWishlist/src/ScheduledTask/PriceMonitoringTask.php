<?php declare(strict_types=1);

namespace AdvancedWishlist\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class PriceMonitoringTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'advanced_wishlist.price_monitoring_task';
    }

    public static function getDefaultInterval(): int
    {
        return 3600; // Every hour
    }
}
