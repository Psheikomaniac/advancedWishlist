<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\OAuth\Middleware;

use AdvancedWishlist\Core\OAuth\Service\OAuth2Service;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class OAuth2Middleware
{
    private OAuth2Service $oauth2Service;
    private PsrHttpFactory $psrHttpFactory;
    private HttpFoundationFactory $httpFoundationFactory;
    private LoggerInterface $logger;
    private array $excludedRoutes = [
        'api.oauth.token',
        'api.oauth.introspect',
    ];

    /**
     * OAuth2Middleware constructor.
     */
    public function __construct(
        OAuth2Service $oauth2Service,
        PsrHttpFactory $psrHttpFactory,
        HttpFoundationFactory $httpFoundationFactory,
        LoggerInterface $logger,
    ) {
        $this->oauth2Service = $oauth2Service;
        $this->psrHttpFactory = $psrHttpFactory;
        $this->httpFoundationFactory = $httpFoundationFactory;
        $this->logger = $logger;
    }

    /**
     * Handle the request event.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only process API routes
        if (!$this->isApiRoute($request)) {
            return;
        }

        // Skip excluded routes
        if ($this->isExcludedRoute($request)) {
            return;
        }

        try {
            $psr7Request = $this->psrHttpFactory->createRequest($request);
            $psr7Request = $this->oauth2Service->getResourceServer()->validateAuthenticatedRequest($psr7Request);

            // Add OAuth2 attributes to the request
            $request->attributes->set('oauth_client_id', $psr7Request->getAttribute('oauth_client_id'));
            $request->attributes->set('oauth_user_id', $psr7Request->getAttribute('oauth_user_id'));
            $request->attributes->set('oauth_scopes', $psr7Request->getAttribute('oauth_scopes'));
        } catch (OAuthServerException $exception) {
            $this->logger->error('OAuth2 authentication error: '.$exception->getMessage(), [
                'exception' => $exception,
            ]);

            $psr7Response = $this->psrHttpFactory->createResponse(new Response());
            $response = $this->httpFoundationFactory->createResponse(
                $exception->generateHttpResponse($psr7Response)
            );

            $event->setResponse($response);
        } catch (\Exception $exception) {
            $this->logger->error('OAuth2 authentication error: '.$exception->getMessage(), [
                'exception' => $exception,
            ]);

            $response = new JsonResponse(['error' => 'Server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
            $event->setResponse($response);
        }
    }

    /**
     * Check if the request is for an API route.
     */
    private function isApiRoute(Request $request): bool
    {
        return 0 === strpos($request->getPathInfo(), '/api/');
    }

    /**
     * Check if the route is excluded from OAuth2 authentication.
     */
    private function isExcludedRoute(Request $request): bool
    {
        $routeName = $request->attributes->get('_route');

        return in_array($routeName, $this->excludedRoutes);
    }
}
