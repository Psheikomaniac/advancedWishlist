<?php declare(strict_types=1);

namespace AdvancedWishlist\ScheduledTask;

use AdvancedWishlist\Service\NotificationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

#[AsMessageHandler(handles: PriceMonitoringTask::class)]
class PriceMonitoringTaskHandler extends ScheduledTaskHandler
{
    private NotificationService $notificationService;
    private EntityRepository $productRepository;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        NotificationService $notificationService,
        EntityRepository $productRepository,
        LoggerInterface $logger
    )
    {
        parent::__construct($scheduledTaskRepository, $logger);
        $this->notificationService = $notificationService;
        $this->productRepository = $productRepository;
    }

    public function run(): void
    {
        // TODO: Implement logic to fetch products with price monitoring enabled
        // TODO: Compare current price with a stored price and trigger notification

        $context = Context::createDefaultContext();

        // Example: Simulate a price change for a product
        $productId = 'YOUR_PRODUCT_ID'; // Replace with a real product ID for testing
        $oldPrice = 100.00;
        $newPrice = 90.00;

        $this->notificationService->sendPriceAlertNotification($productId, $oldPrice, $newPrice, $context);
    }
}
