<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * API Version Resolver for Enterprise Wishlist Plugin
 * Supports multiple API version strategies for backward compatibility.
 */
class ApiVersionResolver
{
    private const DEFAULT_VERSION = 'v1';
    private const SUPPORTED_VERSIONS = ['v1', 'v2'];

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Resolve API version from request using multiple strategies.
     */
    public function resolveVersion(?Request $request = null): string
    {
        $request ??= $this->requestStack->getCurrentRequest();

        if (!$request) {
            return self::DEFAULT_VERSION;
        }

        // Strategy 1: Header-based versioning (preferred for REST APIs)
        $version = $this->getVersionFromHeader($request);
        if ($version && $this->isVersionSupported($version)) {
            return $version;
        }

        // Strategy 2: URL path versioning
        $version = $this->getVersionFromPath($request);
        if ($version && $this->isVersionSupported($version)) {
            return $version;
        }

        // Strategy 3: Query parameter versioning
        $version = $this->getVersionFromQuery($request);
        if ($version && $this->isVersionSupported($version)) {
            return $version;
        }

        // Strategy 4: Content negotiation
        $version = $this->getVersionFromAcceptHeader($request);
        if ($version && $this->isVersionSupported($version)) {
            return $version;
        }

        return self::DEFAULT_VERSION;
    }

    /**
     * Get version from API-Version header.
     */
    private function getVersionFromHeader(Request $request): ?string
    {
        $header = $request->headers->get('API-Version') ?? $request->headers->get('X-API-Version');

        if ($header && preg_match('/^v?(\d+)$/', $header, $matches)) {
            return 'v'.$matches[1];
        }

        return $header;
    }

    /**
     * Get version from URL path (/store-api/v2/wishlist).
     */
    private function getVersionFromPath(Request $request): ?string
    {
        $path = $request->getPathInfo();

        if (preg_match('#/store-api/(v\d+)/#', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get version from query parameter.
     */
    private function getVersionFromQuery(Request $request): ?string
    {
        $version = $request->query->get('version') ?? $request->query->get('api_version');

        if ($version && preg_match('/^v?(\d+)$/', $version, $matches)) {
            return 'v'.$matches[1];
        }

        return $version;
    }

    /**
     * Get version from Accept header content negotiation.
     */
    private function getVersionFromAcceptHeader(Request $request): ?string
    {
        $accept = $request->headers->get('Accept', '');

        // Look for application/vnd.wishlist.v2+json pattern
        if (preg_match('/application\/vnd\.wishlist\.(v\d+)\+json/', $accept, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if version is supported.
     */
    private function isVersionSupported(string $version): bool
    {
        return in_array($version, self::SUPPORTED_VERSIONS, true);
    }

    /**
     * Get all supported versions.
     */
    public function getSupportedVersions(): array
    {
        return self::SUPPORTED_VERSIONS;
    }

    /**
     * Get latest version.
     */
    public function getLatestVersion(): string
    {
        return end(self::SUPPORTED_VERSIONS);
    }

    /**
     * Check if version is deprecated.
     */
    public function isVersionDeprecated(string $version): bool
    {
        // V1 is considered legacy but still supported
        return match ($version) {
            'v1' => true,
            default => false,
        };
    }

    /**
     * Get version-specific features.
     */
    public function getVersionFeatures(string $version): array
    {
        return match ($version) {
            'v1' => [
                'basic_crud' => true,
                'lazy_loading' => false,
                'bulk_operations' => false,
                'advanced_analytics' => false,
                'real_time_updates' => false,
            ],
            'v2' => [
                'basic_crud' => true,
                'lazy_loading' => true,
                'bulk_operations' => true,
                'advanced_analytics' => true,
                'real_time_updates' => true,
                'property_hooks' => true,
                'asymmetric_visibility' => true,
                'enhanced_caching' => true,
            ],
            default => [],
        };
    }

    /**
     * Get deprecation notice for version.
     */
    public function getDeprecationNotice(string $version): ?array
    {
        return match ($version) {
            'v1' => [
                'message' => 'API v1 is deprecated and will be removed in version 3.0',
                'sunset_date' => '2026-01-01',
                'migration_guide' => '/docs/api/v1-to-v2-migration',
                'support_until' => '2025-12-31',
            ],
            default => null,
        };
    }

    /**
     * Generate version-aware route name.
     */
    public function getVersionedRouteName(string $baseRouteName, ?string $version = null): string
    {
        $version ??= $this->resolveVersion();

        if (self::DEFAULT_VERSION === $version) {
            return $baseRouteName;
        }

        return "{$baseRouteName}.{$version}";
    }

    /**
     * Generate appropriate response headers for version.
     */
    public function getVersionHeaders(string $version): array
    {
        $headers = [
            'API-Version' => $version,
            'API-Supported-Versions' => implode(', ', self::SUPPORTED_VERSIONS),
        ];

        if ($deprecation = $this->getDeprecationNotice($version)) {
            $headers['Deprecation'] = $deprecation['sunset_date'];
            $headers['Sunset'] = $deprecation['sunset_date'];
            $headers['Link'] = '<'.$deprecation['migration_guide'].'>; rel="help"; title="Migration Guide"';
        }

        return $headers;
    }

    /**
     * Validate version compatibility.
     */
    public function validateVersionCompatibility(string $requestedVersion, array $requiredFeatures = []): bool
    {
        if (!$this->isVersionSupported($requestedVersion)) {
            return false;
        }

        $versionFeatures = $this->getVersionFeatures($requestedVersion);

        foreach ($requiredFeatures as $feature) {
            if (!($versionFeatures[$feature] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get minimum version for required features.
     */
    public function getMinimumVersionForFeatures(array $requiredFeatures): ?string
    {
        foreach (self::SUPPORTED_VERSIONS as $version) {
            if ($this->validateVersionCompatibility($version, $requiredFeatures)) {
                return $version;
            }
        }

        return null;
    }
}
