<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Security;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Enterprise-grade rate limiting service for API protection
 * Implements multiple rate limiting strategies for different endpoints.
 */
class RateLimitService
{
    private array $limiters = [];

    public function __construct(
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        private RequestStack $requestStack,
    ) {
        $this->initializeLimiters();
    }

    /**
     * Check if request is allowed based on rate limits.
     */
    public function isAllowed(string $endpoint, ?Request $request = null): bool
    {
        $request ??= $this->requestStack->getCurrentRequest();

        if (!$request) {
            return true; // Allow if no request context
        }

        $limiter = $this->getLimiter($endpoint);
        $clientKey = $this->getClientKey($request, $endpoint);

        $limit = $limiter->consume($clientKey);

        if (!$limit->isAccepted()) {
            $this->logRateLimitExceeded($endpoint, $clientKey, $request);

            return false;
        }

        // Log successful rate limit check for analytics
        $this->logRateLimitSuccess($endpoint, $clientKey, $limit->getRemainingTokens());

        return true;
    }

    /**
     * Get remaining tokens for a client.
     */
    public function getRemainingTokens(string $endpoint, ?Request $request = null): int
    {
        $request ??= $this->requestStack->getCurrentRequest();

        if (!$request) {
            return 100; // Default high value if no request context
        }

        $limiter = $this->getLimiter($endpoint);
        $clientKey = $this->getClientKey($request, $endpoint);

        return $limiter->consume($clientKey, 0)->getRemainingTokens();
    }

    /**
     * Get rate limit headers for response.
     */
    public function getRateLimitHeaders(string $endpoint, ?Request $request = null): array
    {
        $request ??= $this->requestStack->getCurrentRequest();

        if (!$request) {
            return [];
        }

        $limiter = $this->getLimiter($endpoint);
        $clientKey = $this->getClientKey($request, $endpoint);
        $limit = $limiter->consume($clientKey, 0);

        return [
            'X-RateLimit-Limit' => $this->getLimitConfig($endpoint)['limit'],
            'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
            'X-RateLimit-Reset' => $limit->getRetryAfter()?->format('U') ?? time() + 3600,
        ];
    }

    /**
     * Initialize rate limiters for different endpoints.
     */
    private function initializeLimiters(): void
    {
        $storage = new CacheStorage($this->cache);

        $configs = [
            'wishlist_read' => [
                'policy' => 'sliding_window',
                'limit' => 200,
                'interval' => '1 hour',
            ],
            'wishlist_write' => [
                'policy' => 'sliding_window',
                'limit' => 50,
                'interval' => '1 hour',
            ],
            'wishlist_bulk' => [
                'policy' => 'sliding_window',
                'limit' => 10,
                'interval' => '1 hour',
            ],
            'analytics' => [
                'policy' => 'sliding_window',
                'limit' => 100,
                'interval' => '1 hour',
            ],
            'auth' => [
                'policy' => 'sliding_window',
                'limit' => 20,
                'interval' => '15 minutes',
            ],
        ];

        foreach ($configs as $endpoint => $config) {
            $this->limiters[$endpoint] = new RateLimiterFactory([
                'id' => "wishlist_api_{$endpoint}",
                'policy' => $config['policy'],
                'limit' => $config['limit'],
                'interval' => $config['interval'],
            ], $storage);
        }
    }

    /**
     * Get appropriate limiter for endpoint.
     */
    private function getLimiter(string $endpoint): RateLimiterFactory
    {
        return $this->limiters[$endpoint] ?? $this->limiters['wishlist_read'];
    }

    /**
     * Generate unique client key for rate limiting.
     */
    private function getClientKey(Request $request, string $endpoint): string
    {
        $factors = [
            $request->getClientIp(),
            $request->headers->get('User-Agent', 'unknown'),
            $endpoint,
        ];

        // Add customer ID if available for more precise limiting
        if ($customerId = $request->attributes->get('customerId')) {
            $factors[] = $customerId;
        }

        return hash('sha256', implode(':', $factors));
    }

    /**
     * Get limit configuration for endpoint.
     */
    private function getLimitConfig(string $endpoint): array
    {
        $configs = [
            'wishlist_read' => ['limit' => 200, 'interval' => '1 hour'],
            'wishlist_write' => ['limit' => 50, 'interval' => '1 hour'],
            'wishlist_bulk' => ['limit' => 10, 'interval' => '1 hour'],
            'analytics' => ['limit' => 100, 'interval' => '1 hour'],
            'auth' => ['limit' => 20, 'interval' => '15 minutes'],
        ];

        return $configs[$endpoint] ?? $configs['wishlist_read'];
    }

    /**
     * Log rate limit exceeded event.
     */
    private function logRateLimitExceeded(string $endpoint, string $clientKey, Request $request): void
    {
        $this->logger->warning('Rate limit exceeded', [
            'endpoint' => $endpoint,
            'client_key' => substr($clientKey, 0, 8).'...',
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'timestamp' => time(),
        ]);
    }

    /**
     * Log successful rate limit check.
     */
    private function logRateLimitSuccess(string $endpoint, string $clientKey, int $remaining): void
    {
        $this->logger->debug('Rate limit check passed', [
            'endpoint' => $endpoint,
            'client_key' => substr($clientKey, 0, 8).'...',
            'remaining_tokens' => $remaining,
            'timestamp' => time(),
        ]);
    }
}
