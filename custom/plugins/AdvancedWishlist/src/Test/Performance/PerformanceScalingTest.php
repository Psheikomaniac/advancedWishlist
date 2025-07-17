<?php

declare(strict_types=1);

namespace AdvancedWishlist\Test\Performance;

use AdvancedWishlist\Core\Asset\AssetUrlDecorator;
use AdvancedWishlist\Core\Async\AsyncProcessor;
use AdvancedWishlist\Core\Database\QueryOptimizer;
use AdvancedWishlist\Core\Database\ReadReplicaConnectionDecorator;
use AdvancedWishlist\Core\Http\CacheHeaderSubscriber;
use AdvancedWishlist\Core\Service\CdnService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Test for the performance scaling features.
 *
 * This test verifies that the performance scaling features work as expected.
 */
class PerformanceScalingTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * Test that the CDN service correctly generates CDN URLs.
     */
    public function testCdnService(): void
    {
        $systemConfigService = $this->createMock(\Shopware\Core\System\SystemConfig\SystemConfigService::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        // Configure the system config service to return test values
        $systemConfigService->method('getBool')
            ->with('AdvancedWishlist.config.cdnEnabled')
            ->willReturn(true);

        $systemConfigService->method('getString')
            ->willReturnMap([
                ['AdvancedWishlist.config.cdnStrategy', 'custom'],
                ['AdvancedWishlist.config.cdnUrl', 'https://cdn.example.com'],
            ]);

        $cdnService = new CdnService($systemConfigService, $logger);

        // Test that the CDN service correctly generates CDN URLs
        $assetPath = '/bundles/advancedwishlist/css/style.css';
        $cdnUrl = $cdnService->getAssetUrl($assetPath);

        self::assertEquals('https://cdn.example.com/bundles/advancedwishlist/css/style.css', $cdnUrl);
    }

    /**
     * Test that the asset URL decorator correctly decorates asset URLs.
     */
    public function testAssetUrlDecorator(): void
    {
        $decorated = $this->createMock(\Shopware\Core\Framework\Adapter\Asset\AssetPackageInterface::class);
        $cdnService = $this->createMock(CdnService::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        // Configure the decorated asset package to return test values
        $decorated->method('getUrl')
            ->with('/bundles/advancedwishlist/css/style.css')
            ->willReturn('/bundles/advancedwishlist/css/style.css');

        // Configure the CDN service to return test values
        $cdnService->method('isEnabled')
            ->willReturn(true);

        $cdnService->method('getAssetUrl')
            ->with('/bundles/advancedwishlist/css/style.css')
            ->willReturn('https://cdn.example.com/bundles/advancedwishlist/css/style.css');

        $assetUrlDecorator = new AssetUrlDecorator($decorated, $cdnService, $logger);

        // Test that the asset URL decorator correctly decorates asset URLs
        $assetPath = '/bundles/advancedwishlist/css/style.css';
        $decoratedUrl = $assetUrlDecorator->getUrl($assetPath);

        self::assertEquals('https://cdn.example.com/bundles/advancedwishlist/css/style.css', $decoratedUrl);
    }

    /**
     * Test that the read replica connection decorator correctly routes queries.
     */
    public function testReadReplicaConnectionDecorator(): void
    {
        $masterConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $replicaConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $readReplicaConnectionDecorator = new ReadReplicaConnectionDecorator(
            $masterConnection,
            $replicaConnection,
            $logger
        );

        // Test that read operations are routed to the replica connection
        $readConnection = $readReplicaConnectionDecorator->getConnection(true);
        self::assertSame($replicaConnection, $readConnection);

        // Test that write operations are routed to the master connection
        $writeConnection = $readReplicaConnectionDecorator->getConnection(false);
        self::assertSame($masterConnection, $writeConnection);
    }

    /**
     * Test that the query optimizer correctly optimizes queries.
     */
    public function testQueryOptimizer(): void
    {
        $cache = $this->createMock(\Psr\Cache\CacheItemPoolInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $queryOptimizer = new QueryOptimizer($cache, $logger);

        // Test that the query optimizer correctly optimizes wishlist criteria
        $criteria = new Criteria();
        $optimizedCriteria = $queryOptimizer->optimizeCriteria($criteria, 'wishlist');

        self::assertEquals(100, $optimizedCriteria->getLimit());
        self::assertCount(1, $optimizedCriteria->getSorting());
    }

    /**
     * Test that the async processor correctly dispatches messages.
     */
    public function testAsyncProcessor(): void
    {
        $messageBus = $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $asyncProcessor = new AsyncProcessor($messageBus, $logger);

        // Create a test message
        $message = new \stdClass();

        // Configure the message bus to expect a dispatch call
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with($message, self::anything())
            ->willReturn(new \Symfony\Component\Messenger\Envelope($message));

        // Test that the async processor correctly dispatches messages
        $asyncProcessor->dispatch($message);
    }

    /**
     * Test that the cache header subscriber correctly adds cache headers.
     */
    public function testCacheHeaderSubscriber(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $cacheHeaderSubscriber = new CacheHeaderSubscriber($logger);

        // Create a test response
        $response = new Response();

        // Create a test request for a static asset
        $request = Request::create('/bundles/advancedwishlist/css/style.css');

        // Create a test response event
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        // Test that the cache header subscriber correctly adds cache headers
        $cacheHeaderSubscriber->onKernelResponse($event);

        self::assertTrue($response->headers->has('Cache-Control'));
        self::assertTrue($response->headers->has('ETag'));
        self::assertTrue($response->headers->has('Last-Modified'));
    }
}
