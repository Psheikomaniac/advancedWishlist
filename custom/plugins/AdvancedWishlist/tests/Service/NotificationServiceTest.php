<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Service;

use AdvancedWishlist\ScheduledTask\PriceMonitoringTaskHandler;
use AdvancedWishlist\Service\NotificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class NotificationServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ?NotificationService $notificationService;
    private ?EntityRepository $productRepository;
    private ?LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->notificationService = new NotificationService($this->logger);
    }

    public function testSendPriceAlertNotification(): void
    {
        $productId = 'test-product-id';
        $oldPrice = 100.00;
        $newPrice = 90.00;

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains(sprintf(
                    'Price alert for product %s: Price changed from %f to %f',
                    $productId,
                    $oldPrice,
                    $newPrice
                ))
            );

        $this->notificationService->sendPriceAlertNotification($productId, $oldPrice, $newPrice, Context::createDefaultContext());
    }

    public function testPriceMonitoringTaskHandler(): void
    {
        $scheduledTaskRepository = $this->getContainer()->get('scheduled_task.repository');
        $mockProductRepository = $this->createMock(EntityRepository::class);

        $handler = new PriceMonitoringTaskHandler(
            $scheduledTaskRepository,
            $this->notificationService,
            $mockProductRepository,
            $this->logger
        );

        // Mock the product repository search method to return an empty result
        // so that the run method doesn't try to fetch real products
        $mockProductRepository->method('search')
            ->willReturn(new EntitySearchResult('product', 0, new EntityCollection([]), null, new Criteria(), Context::createDefaultContext()));

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Price alert for product YOUR_PRODUCT_ID: Price changed from 100.000000 to 90.000000')
            );

        $handler->run();
    }
}
