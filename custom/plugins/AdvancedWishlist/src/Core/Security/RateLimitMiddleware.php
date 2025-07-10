<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Enterprise Rate Limiting Middleware
 * Automatically applies rate limiting to wishlist API endpoints
 */
class RateLimitMiddleware implements EventSubscriberInterface
{
    private const ENDPOINT_PATTERNS = [
        '/store-api/(v\d+/)?wishlist$' => 'wishlist_read',
        '/store-api/(v\d+/)?wishlist/.+$' => 'wishlist_read',
        '/store-api/(v\d+/)?wishlist/.+/items$' => 'wishlist_write',
        '/store-api/(v\d+/)?wishlist/.+/bulk' => 'wishlist_bulk',
        '/store-api/(v\d+/)?wishlist/.+/analytics' => 'analytics',
    ];
    
    public function __construct(
        private RateLimitService $rateLimitService,
        private SecurityMonitoringService $securityMonitoring
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        
        $request = $event->getRequest();
        $endpoint = $this->getEndpointType($request);
        
        if (!$endpoint) {
            return; // Not a wishlist API endpoint
        }
        
        // Check rate limit
        if (!$this->rateLimitService->isAllowed($endpoint, $request)) {
            $this->handleRateLimitExceeded($event, $request, $endpoint);
            return;
        }
        
        // Add rate limit headers to request for later use
        $headers = $this->rateLimitService->getRateLimitHeaders($endpoint, $request);
        $request->attributes->set('rate_limit_headers', $headers);
        $request->attributes->set('rate_limit_endpoint', $endpoint);
    }
    
    /**
     * Handle rate limit exceeded scenario
     */
    private function handleRateLimitExceeded(RequestEvent $event, Request $request, string $endpoint): void
    {
        // Log security event
        $this->securityMonitoring->logSecurityEvent('rate_limit_exceeded', [
            'endpoint' => $endpoint,
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
        ]);
        
        $headers = $this->rateLimitService->getRateLimitHeaders($endpoint, $request);
        
        $response = new JsonResponse([
            'errors' => [[
                'code' => 'RATE_LIMIT_EXCEEDED',
                'title' => 'Rate Limit Exceeded',
                'detail' => 'Too many requests. Please try again later.',
                'meta' => [
                    'limit' => $headers['X-RateLimit-Limit'] ?? null,
                    'remaining' => $headers['X-RateLimit-Remaining'] ?? 0,
                    'reset' => $headers['X-RateLimit-Reset'] ?? null,
                ]
            ]]
        ], Response::HTTP_TOO_MANY_REQUESTS);
        
        // Add rate limit headers
        foreach ($headers as $key => $value) {
            $response->headers->set($key, (string) $value);
        }
        
        $response->headers->set('Retry-After', (string) ($headers['X-RateLimit-Reset'] ?? time() + 3600));
        
        $event->setResponse($response);
    }
    
    /**
     * Determine endpoint type from request
     */
    private function getEndpointType(Request $request): ?string
    {
        $path = $request->getPathInfo();
        $method = $request->getMethod();
        
        foreach (self::ENDPOINT_PATTERNS as $pattern => $endpoint) {
            if (preg_match('#' . $pattern . '#', $path)) {
                // Adjust endpoint based on HTTP method for write operations
                if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'])) {
                    return match ($endpoint) {
                        'wishlist_read' => 'wishlist_write',
                        default => $endpoint
                    };
                }
                
                return $endpoint;
            }
        }
        
        return null;
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => ['onKernelRequest', 15], // Higher priority than normal
        ];
    }
}