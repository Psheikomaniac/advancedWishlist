<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\OAuth\Controller;

use AdvancedWishlist\Core\OAuth\Service\OAuth2Service;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class OAuth2Controller
{
    private OAuth2Service $oauth2Service;
    private PsrHttpFactory $psrHttpFactory;
    private HttpFoundationFactory $httpFoundationFactory;
    private LoggerInterface $logger;

    /**
     * OAuth2Controller constructor.
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
     * @Route("/api/oauth/token", name="api.oauth.token", methods={"POST"})
     */
    public function token(Request $request): Response
    {
        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse(new Response());

        try {
            $psr7Response = $this->oauth2Service->getAuthorizationServer()->respondToAccessTokenRequest(
                $psr7Request,
                $psr7Response
            );

            return $this->httpFoundationFactory->createResponse($psr7Response);
        } catch (OAuthServerException $exception) {
            $this->logger->error('OAuth2 token error: '.$exception->getMessage(), [
                'exception' => $exception,
            ]);

            return $this->httpFoundationFactory->createResponse(
                $exception->generateHttpResponse($psr7Response)
            );
        } catch (\Exception $exception) {
            $this->logger->error('OAuth2 token error: '.$exception->getMessage(), [
                'exception' => $exception,
            ]);

            return new Response('Server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @Route("/api/oauth/introspect", name="api.oauth.introspect", methods={"POST"})
     */
    public function introspect(Request $request): Response
    {
        $psr7Request = $this->psrHttpFactory->createRequest($request);

        try {
            $psr7Response = $this->psrHttpFactory->createResponse(new Response());
            $psr7Request = $this->oauth2Service->getResourceServer()->validateAuthenticatedRequest($psr7Request);

            $response = [
                'active' => true,
                'client_id' => $psr7Request->getAttribute('oauth_client_id'),
                'user_id' => $psr7Request->getAttribute('oauth_user_id'),
                'scopes' => $psr7Request->getAttribute('oauth_scopes'),
                'expires_at' => $psr7Request->getAttribute('oauth_access_token_id'),
            ];

            return new Response(json_encode($response), Response::HTTP_OK, [
                'Content-Type' => 'application/json',
            ]);
        } catch (OAuthServerException $exception) {
            $this->logger->error('OAuth2 introspect error: '.$exception->getMessage(), [
                'exception' => $exception,
            ]);

            return new Response(json_encode(['active' => false]), Response::HTTP_OK, [
                'Content-Type' => 'application/json',
            ]);
        } catch (\Exception $exception) {
            $this->logger->error('OAuth2 introspect error: '.$exception->getMessage(), [
                'exception' => $exception,
            ]);

            return new Response('Server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
