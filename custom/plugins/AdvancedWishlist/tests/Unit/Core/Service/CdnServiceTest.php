<?php declare(strict_types=1);

namespace AdvancedWishlist\Tests\Unit\Core\Service;

use AdvancedWishlist\Core\Service\CdnService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Unit tests for the CdnService class
 */
class CdnServiceTest extends TestCase
{
    private SystemConfigService $configService;
    private LoggerInterface $logger;
    private CdnService $cdnService;

    protected function setUp(): void
    {
        $this->configService = $this->createMock(SystemConfigService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cdnService = new CdnService($this->configService, $this->logger);
    }

    public function testIsEnabledReturnsTrueWhenEnabled(): void
    {
        // Configure the config service to return true for cdnEnabled
        $this->configService->method('getBool')
            ->with('AdvancedWishlist.config.cdnEnabled')
            ->willReturn(true);

        // Check if CDN is enabled
        $isEnabled = $this->cdnService->isEnabled();

        // Assert that CDN is enabled
        $this->assertTrue($isEnabled);
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        // Configure the config service to return false for cdnEnabled
        $this->configService->method('getBool')
            ->with('AdvancedWishlist.config.cdnEnabled')
            ->willReturn(false);

        // Check if CDN is enabled
        $isEnabled = $this->cdnService->isEnabled();

        // Assert that CDN is disabled
        $this->assertFalse($isEnabled);
    }

    public function testGetStrategyReturnsConfiguredStrategy(): void
    {
        // Configure the config service to return a strategy
        $this->configService->method('getString')
            ->with('AdvancedWishlist.config.cdnStrategy')
            ->willReturn('cloudfront');

        // Get the strategy
        $strategy = $this->cdnService->getStrategy();

        // Assert that the strategy is returned
        $this->assertEquals('cloudfront', $strategy);
    }

    public function testGetStrategyReturnsNoneWhenNotConfigured(): void
    {
        // Configure the config service to return an empty string
        $this->configService->method('getString')
            ->with('AdvancedWishlist.config.cdnStrategy')
            ->willReturn('');

        // Get the strategy
        $strategy = $this->cdnService->getStrategy();

        // Assert that 'none' is returned
        $this->assertEquals('none', $strategy);
    }

    public function testGetCdnUrlReturnsConfiguredUrl(): void
    {
        // Configure the config service to return a URL
        $this->configService->method('getString')
            ->with('AdvancedWishlist.config.cdnUrl')
            ->willReturn('https://cdn.example.com');

        // Get the CDN URL
        $cdnUrl = $this->cdnService->getCdnUrl();

        // Assert that the URL is returned
        $this->assertEquals('https://cdn.example.com', $cdnUrl);
    }

    public function testGetAssetUrlReturnsOriginalPathWhenCdnDisabled(): void
    {
        // Configure the config service to return false for cdnEnabled
        $this->configService->method('getBool')
            ->with('AdvancedWishlist.config.cdnEnabled')
            ->willReturn(false);

        // Get the asset URL
        $assetUrl = $this->cdnService->getAssetUrl('/path/to/asset.jpg');

        // Assert that the original path is returned
        $this->assertEquals('/path/to/asset.jpg', $assetUrl);
    }

    public function testGetAssetUrlReturnsCdnUrlWhenCdnEnabled(): void
    {
        // Configure the config service
        $this->configService->method('getBool')
            ->with('AdvancedWishlist.config.cdnEnabled')
            ->willReturn(true);

        $this->configService->method('getString')
            ->willReturnMap([
                ['AdvancedWishlist.config.cdnUrl', 'https://cdn.example.com'],
            ]);

        // Get the asset URL
        $assetUrl = $this->cdnService->getAssetUrl('/path/to/asset.jpg');

        // Assert that the CDN URL is returned
        $this->assertEquals('https://cdn.example.com/path/to/asset.jpg', $assetUrl);
    }

    public function testGetAssetUrlHandlesLeadingSlashInAssetPath(): void
    {
        // Configure the config service
        $this->configService->method('getBool')
            ->with('AdvancedWishlist.config.cdnEnabled')
            ->willReturn(true);

        $this->configService->method('getString')
            ->willReturnMap([
                ['AdvancedWishlist.config.cdnUrl', 'https://cdn.example.com'],
            ]);

        // Get the asset URL with a leading slash
        $assetUrl = $this->cdnService->getAssetUrl('/path/to/asset.jpg');

        // Assert that the leading slash is handled correctly
        $this->assertEquals('https://cdn.example.com/path/to/asset.jpg', $assetUrl);
    }

    public function testGetAssetUrlHandlesTrailingSlashInCdnUrl(): void
    {
        // Configure the config service
        $this->configService->method('getBool')
            ->with('AdvancedWishlist.config.cdnEnabled')
            ->willReturn(true);

        $this->configService->method('getString')
            ->willReturnMap([
                ['AdvancedWishlist.config.cdnUrl', 'https://cdn.example.com/'],
            ]);

        // Get the asset URL
        $assetUrl = $this->cdnService->getAssetUrl('path/to/asset.jpg');

        // Assert that the trailing slash is handled correctly
        $this->assertEquals('https://cdn.example.com/path/to/asset.jpg', $assetUrl);
    }

    public function testGetAssetUrlReturnsOriginalPathWhenCdnUrlEmpty(): void
    {
        // Configure the config service
        $this->configService->method('getBool')
            ->with('AdvancedWishlist.config.cdnEnabled')
            ->willReturn(true);

        $this->configService->method('getString')
            ->willReturnMap([
                ['AdvancedWishlist.config.cdnUrl', ''],
            ]);

        // Expect a warning to be logged
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('CDN is enabled but no CDN URL is configured');

        // Get the asset URL
        $assetUrl = $this->cdnService->getAssetUrl('/path/to/asset.jpg');

        // Assert that the original path is returned
        $this->assertEquals('/path/to/asset.jpg', $assetUrl);
    }
}