<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Service for handling CDN integration.
 *
 * This service provides functionality for integrating with CDNs (Content Delivery Networks)
 * to improve the delivery of static assets.
 */
class CdnService
{
    private const string CONFIG_PREFIX = 'AdvancedWishlist.config.';

    /**
     * @param SystemConfigService $configService Service for accessing system configuration
     * @param LoggerInterface     $logger        Logger for logging CDN-related events
     */
    public function __construct(
        private readonly SystemConfigService $configService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Check if CDN is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->configService->getBool(self::CONFIG_PREFIX.'cdnEnabled');
    }

    /**
     * Get the CDN strategy.
     *
     * @return string The CDN strategy (none, cloudfront, cloudflare, custom)
     */
    public function getStrategy(): string
    {
        return $this->configService->getString(self::CONFIG_PREFIX.'cdnStrategy') ?: 'none';
    }

    /**
     * Get the CDN URL.
     *
     * @return string The CDN URL
     */
    public function getCdnUrl(): string
    {
        return $this->configService->getString(self::CONFIG_PREFIX.'cdnUrl') ?: '';
    }

    /**
     * Get the CDN API key.
     *
     * @return string The CDN API key
     */
    public function getApiKey(): string
    {
        return $this->configService->getString(self::CONFIG_PREFIX.'cdnApiKey') ?: '';
    }

    /**
     * Get the CDN secret key.
     *
     * @return string The CDN secret key
     */
    public function getSecretKey(): string
    {
        return $this->configService->getString(self::CONFIG_PREFIX.'cdnSecretKey') ?: '';
    }

    /**
     * Get the CDN URL for a given asset path.
     *
     * @param string $assetPath The path to the asset
     *
     * @return string The CDN URL for the asset
     */
    public function getAssetUrl(string $assetPath): string
    {
        if (!$this->isEnabled()) {
            return $assetPath;
        }

        $cdnUrl = $this->getCdnUrl();
        if (empty($cdnUrl)) {
            $this->logger->warning('CDN is enabled but no CDN URL is configured');

            return $assetPath;
        }

        // Ensure the CDN URL ends with a slash
        if (!str_ends_with($cdnUrl, '/')) {
            $cdnUrl .= '/';
        }

        // Remove leading slash from asset path if present
        if (str_starts_with($assetPath, '/')) {
            $assetPath = substr($assetPath, 1);
        }

        // Combine CDN URL and asset path
        $url = $cdnUrl.$assetPath;

        $this->logger->debug('CDN URL generated', [
            'originalPath' => $assetPath,
            'cdnUrl' => $url,
        ]);

        return $url;
    }

    /**
     * Purge an asset from the CDN cache.
     *
     * @param string $assetPath The path to the asset to purge
     *
     * @return bool True if the purge was successful, false otherwise
     */
    public function purgeAsset(string $assetPath): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $strategy = $this->getStrategy();

        try {
            switch ($strategy) {
                case 'cloudfront':
                    return $this->purgeCloudFront($assetPath);
                case 'cloudflare':
                    return $this->purgeCloudflare($assetPath);
                case 'custom':
                    return $this->purgeCustom($assetPath);
                default:
                    $this->logger->warning('Unknown CDN strategy', ['strategy' => $strategy]);

                    return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error purging asset from CDN', [
                'assetPath' => $assetPath,
                'strategy' => $strategy,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Purge an asset from CloudFront.
     *
     * @param string $assetPath The path to the asset to purge
     *
     * @return bool True if the purge was successful, false otherwise
     */
    private function purgeCloudFront(string $assetPath): bool
    {
        // Implementation would use AWS SDK to create invalidation
        // This is a placeholder for the actual implementation
        $this->logger->info('CloudFront purge requested', ['assetPath' => $assetPath]);

        return true;
    }

    /**
     * Purge an asset from Cloudflare.
     *
     * @param string $assetPath The path to the asset to purge
     *
     * @return bool True if the purge was successful, false otherwise
     */
    private function purgeCloudflare(string $assetPath): bool
    {
        // Implementation would use Cloudflare API to purge cache
        // This is a placeholder for the actual implementation
        $this->logger->info('Cloudflare purge requested', ['assetPath' => $assetPath]);

        return true;
    }

    /**
     * Purge an asset from a custom CDN.
     *
     * @param string $assetPath The path to the asset to purge
     *
     * @return bool True if the purge was successful, false otherwise
     */
    private function purgeCustom(string $assetPath): bool
    {
        // Implementation would use custom CDN API to purge cache
        // This is a placeholder for the actual implementation
        $this->logger->info('Custom CDN purge requested', ['assetPath' => $assetPath]);

        return true;
    }
}
