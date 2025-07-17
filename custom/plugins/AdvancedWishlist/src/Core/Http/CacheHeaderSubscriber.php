<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for adding cache headers to responses.
 *
 * This class adds appropriate cache headers to responses to improve performance
 * by enabling browser and proxy caching.
 */
class CacheHeaderSubscriber implements EventSubscriberInterface
{
    /**
     * Default cache TTL in seconds (1 hour).
     */
    private const int DEFAULT_CACHE_TTL = 3600;

    /**
     * Cache TTL for static assets in seconds (1 day).
     */
    private const int STATIC_ASSET_CACHE_TTL = 86400;

    /**
     * @param LoggerInterface $logger Logger for logging cache header configuration
     */
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get the subscribed events.
     *
     * @return array<string, string|array{0: string, 1: int}> The subscribed events
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    /**
     * Handle the kernel response event.
     *
     * @param ResponseEvent $event The response event
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // Skip if the response already has cache headers
        if ($response->headers->hasCacheControlDirective('max-age') || $response->headers->has('Expires')) {
            return;
        }

        // Skip if the response is an error
        if ($response->getStatusCode() >= 400) {
            return;
        }

        // Skip if the request is a POST, PUT, DELETE, or PATCH request
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return;
        }

        // Skip if the response contains a Set-Cookie header
        if ($response->headers->has('Set-Cookie')) {
            return;
        }

        // Skip if the request has a session cookie
        if ($request->cookies->has('session-') || $request->cookies->has('PHPSESSID')) {
            return;
        }

        // Get the path to determine if this is a static asset
        $path = $request->getPathInfo();

        // Check if this is a static asset
        $isStaticAsset = $this->isStaticAsset($path);

        // Set cache headers based on the type of resource
        if ($isStaticAsset) {
            $this->setCacheHeadersForStaticAsset($response);
        } else {
            $this->setCacheHeadersForDynamicContent($response);
        }

        $this->logger->debug('Cache headers added to response', [
            'path' => $path,
            'isStaticAsset' => $isStaticAsset,
            'cacheControl' => $response->headers->get('Cache-Control'),
        ]);
    }

    /**
     * Check if the given path is a static asset.
     *
     * @param string $path The path to check
     *
     * @return bool Whether the path is a static asset
     */
    private function isStaticAsset(string $path): bool
    {
        // Get the file extension
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // List of static asset extensions
        $staticAssetExtensions = [
            'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp',
            'woff', 'woff2', 'ttf', 'eot', 'otf', 'ico',
        ];

        return in_array(strtolower($extension), $staticAssetExtensions, true);
    }

    /**
     * Set cache headers for static assets.
     *
     * @param Response $response The response to modify
     */
    private function setCacheHeadersForStaticAsset(Response $response): void
    {
        $response->setMaxAge(self::STATIC_ASSET_CACHE_TTL);
        $response->setSharedMaxAge(self::STATIC_ASSET_CACHE_TTL);
        $response->headers->addCacheControlDirective('must-revalidate', false);
        $response->headers->addCacheControlDirective('proxy-revalidate', false);
        $response->headers->addCacheControlDirective('public', true);
        $response->headers->addCacheControlDirective('private', false);

        // Add ETag header
        if (!$response->headers->has('ETag')) {
            $response->setEtag(md5($response->getContent()));
        }

        // Add Last-Modified header
        if (!$response->headers->has('Last-Modified')) {
            $response->setLastModified(new \DateTime());
        }
    }

    /**
     * Set cache headers for dynamic content.
     *
     * @param Response $response The response to modify
     */
    private function setCacheHeadersForDynamicContent(Response $response): void
    {
        $response->setMaxAge(self::DEFAULT_CACHE_TTL);
        $response->setSharedMaxAge(self::DEFAULT_CACHE_TTL);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective('proxy-revalidate', true);
        $response->headers->addCacheControlDirective('public', true);
        $response->headers->addCacheControlDirective('private', false);

        // Add ETag header
        if (!$response->headers->has('ETag')) {
            $response->setEtag(md5($response->getContent()));
        }

        // Add Last-Modified header
        if (!$response->headers->has('Last-Modified')) {
            $response->setLastModified(new \DateTime());
        }

        // Add Vary header
        $response->setVary(['Accept', 'Accept-Encoding']);
    }
}
