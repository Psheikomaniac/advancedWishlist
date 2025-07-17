<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Premium;

use Psr\Log\LoggerInterface;

/**
 * Feature Gate Service for Premium Feature Control
 * Controls access to premium features based on licensing.
 */
class FeatureGateService
{
    public function __construct(
        private LicenseService $licenseService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Check if a specific feature is enabled.
     */
    public function isFeatureEnabled(string $feature): bool
    {
        $availableFeatures = $this->licenseService->getAvailableFeatures();

        $isEnabled = $availableFeatures[$feature] ?? false;

        if (!$isEnabled) {
            $this->logger->info('Feature access denied', [
                'feature' => $feature,
                'license_valid' => $this->licenseService->isLicenseValid(),
            ]);
        }

        return $isEnabled;
    }

    /**
     * Require a feature to be enabled or throw exception.
     */
    public function requireFeature(string $feature): void
    {
        if (!$this->isFeatureEnabled($feature)) {
            throw new PremiumFeatureRequiredException("Feature '{$feature}' requires a premium license");
        }
    }

    /**
     * Get upgrade URL for premium features.
     */
    public function getUpgradeUrl(): string
    {
        return 'https://your-website.com/upgrade-to-premium';
    }
}
