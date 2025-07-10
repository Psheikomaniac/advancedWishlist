<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Security;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Enterprise Security Headers Subscriber
 * Adds comprehensive security headers to API responses
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        
        $response = $event->getResponse();
        $request = $event->getRequest();
        
        // Only apply to wishlist API routes and admin routes
        if ($this->shouldApplySecurityHeaders($request)) {
            $this->addSecurityHeaders($response, $request);
            $this->addRateLimitHeaders($response, $request);
            $this->addCorsHeaders($response, $request);
        }
    }
    
    /**
     * Add comprehensive security headers
     */
    private function addSecurityHeaders($response, Request $request): void
    {
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // XSS Protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Content Security Policy for API endpoints
        if ($this->isApiRoute($request)) {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none';");
        }
        
        // Permissions Policy (Feature Policy)
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        // Prevent caching of sensitive API responses
        if ($this->isApiRoute($request)) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
        
        // Add security timestamp
        $response->headers->set('X-Security-Timestamp', (string) time());
    }
    
    /**
     * Add rate limit headers from request attributes
     */
    private function addRateLimitHeaders($response, Request $request): void
    {
        $rateLimitHeaders = $request->attributes->get('rate_limit_headers', []);
        
        foreach ($rateLimitHeaders as $key => $value) {
            $response->headers->set($key, (string) $value);
        }
        
        // Add rate limit endpoint info for debugging (in dev mode)
        if ($endpoint = $request->attributes->get('rate_limit_endpoint')) {
            $response->headers->set('X-RateLimit-Endpoint', $endpoint);
        }
    }
    
    /**
     * Add CORS headers for API endpoints
     */
    private function addCorsHeaders($response, Request $request): void
    {
        if (!$this->isApiRoute($request)) {
            return;
        }
        
        // Allow specific origins in production (configure based on your needs)
        $allowedOrigins = [
            'https://localhost:3000', // Development
            'https://admin.your-domain.com', // Admin panel
        ];
        
        $origin = $request->headers->get('Origin');
        
        if ($origin && in_array($origin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        } else {
            // For public API, allow all origins but be explicit about it
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }
        
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-Version');
        $response->headers->set('Access-Control-Expose-Headers', 'X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset');
        $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours
        
        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            $response->setStatusCode(204);
            $response->setContent('');
        }
    }
    
    /**
     * Check if security headers should be applied
     */
    private function shouldApplySecurityHeaders(Request $request): bool
    {
        $path = $request->getPathInfo();
        
        return str_starts_with($path, '/store-api/') || 
               str_starts_with($path, '/api/') ||
               str_contains($path, 'wishlist');
    }
    
    /**
     * Check if this is an API route
     */
    private function isApiRoute(Request $request): bool
    {
        $path = $request->getPathInfo();
        
        return str_starts_with($path, '/store-api/') || 
               str_starts_with($path, '/api/');
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.response' => ['onKernelResponse', -10], // Lower priority to run after other response listeners
        ];
    }
}