<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Asset;

use AdvancedWishlist\Core\Service\CdnService;
use Shopware\Core\Framework\Adapter\Asset\AssetPackageInterface;
use Psr\Log\LoggerInterface;

/**
 * Decorator for the Shopware asset package to use CDN for static assets
 * 
 * This class decorates the Shopware asset package to use the CDN for static assets
 * when CDN is enabled in the plugin configuration.
 */
class AssetUrlDecorator implements AssetPackageInterface
{
    /**
     * @param AssetPackageInterface $decorated The decorated asset package
     * @param CdnService $cdnService The CDN service
     * @param LoggerInterface $logger Logger for logging asset URL generation
     */
    public function __construct(
        private readonly AssetPackageInterface $decorated,
        private readonly CdnService $cdnService,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Get the URL for an asset
     * 
     * If CDN is enabled, the asset URL will be generated using the CDN URL.
     * Otherwise, the original asset URL will be returned.
     * 
     * @param string $assetPath The path to the asset
     * @return string The URL for the asset
     */
    public function getUrl(string $assetPath): string
    {
        // Get the original URL from the decorated asset package
        $originalUrl = $this->decorated->getUrl($assetPath);
        
        // If CDN is not enabled, return the original URL
        if (!$this->cdnService->isEnabled()) {
            return $originalUrl;
        }
        
        // Check if this is a static asset that should be served from CDN
        if (!$this->shouldUseCdn($assetPath)) {
            return $originalUrl;
        }
        
        // Generate the CDN URL for the asset
        $cdnUrl = $this->cdnService->getAssetUrl($assetPath);
        
        $this->logger->debug('Asset URL decorated with CDN', [
            'originalUrl' => $originalUrl,
            'cdnUrl' => $cdnUrl
        ]);
        
        return $cdnUrl;
    }
    
    /**
     * Get the version of the asset package
     * 
     * @return string The version of the asset package
     */
    public function getVersion(string $assetPath): string
    {
        return $this->decorated->getVersion($assetPath);
    }
    
    /**
     * Check if the asset should be served from CDN
     * 
     * Only static assets like CSS, JS, images, and fonts should be served from CDN.
     * 
     * @param string $assetPath The path to the asset
     * @return bool True if the asset should be served from CDN, false otherwise
     */
    private function shouldUseCdn(string $assetPath): bool
    {
        // Get the file extension
        $extension = pathinfo($assetPath, PATHINFO_EXTENSION);
        
        // List of static asset extensions that should be served from CDN
        $staticAssetExtensions = [
            'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp',
            'woff', 'woff2', 'ttf', 'eot', 'otf', 'ico'
        ];
        
        return in_array(strtolower($extension), $staticAssetExtensions, true);
    }
}