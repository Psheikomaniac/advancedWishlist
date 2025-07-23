<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\OAuth\Controller;

use AdvancedWishlist\Core\OAuth\Service\OAuth2Service;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Enhanced OAuth2 Controller with comprehensive flows and CSRF protection
 * @Route(defaults={"_routeScope"={"api"}})
 */
class OAuth2Controller
{
    public function __construct(
        private readonly OAuth2Service $oauth2Service,
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly HttpFoundationFactory $httpFoundationFactory,
        private readonly LoggerInterface $logger,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    /**
     * Enhanced token endpoint with comprehensive OAuth2 flows and CSRF protection
     * Supports authorization_code, client_credentials, refresh_token flows
     * 
     * @Route("/api/oauth/token", name="api.oauth.token", methods={"POST"})
     */
    public function token(Request $request): Response
    {
        // CSRF protection for state-changing operations
        $grantType = $request->request->get('grant_type');
        if (in_array($grantType, ['authorization_code', 'refresh_token']) && !$this->validateCsrfToken($request, 'oauth_token')) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'error_description' => 'Invalid CSRF token provided'
            ], Response::HTTP_FORBIDDEN);
        }

        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse(new Response());

        try {
            // Enhanced token processing with flow-specific validation
            $this->validateTokenRequest($request, $grantType);
            
            $psr7Response = $this->oauth2Service->getAuthorizationServer()->respondToAccessTokenRequest(
                $psr7Request,
                $psr7Response
            );

            // Add security headers
            $response = $this->httpFoundationFactory->createResponse($psr7Response);
            $this->addSecurityHeaders($response);
            
            // Log successful token generation
            $this->logger->info('OAuth2 token generated successfully', [
                'grant_type' => $grantType,
                'client_id' => $request->request->get('client_id'),
                'ip' => $request->getClientIp(),
            ]);

            return $response;
        } catch (OAuthServerException $exception) {
            $this->logger->error('OAuth2 token error: ' . $exception->getMessage(), [
                'exception' => $exception,
                'grant_type' => $grantType,
                'client_id' => $request->request->get('client_id'),
                'ip' => $request->getClientIp(),
            ]);

            return $this->httpFoundationFactory->createResponse(
                $exception->generateHttpResponse($psr7Response)
            );
        } catch (\Exception $exception) {
            $this->logger->error('OAuth2 token error: ' . $exception->getMessage(), [
                'exception' => $exception,
                'grant_type' => $grantType,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse([
                'error' => 'server_error',
                'error_description' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Authorization endpoint for OAuth2 authorization code flow
     * 
     * @Route("/api/oauth/authorize", name="api.oauth.authorize", methods={"GET", "POST"})
     */
    public function authorize(Request $request): Response
    {
        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse(new Response());

        try {
            // Validate authorization request
            $authRequest = $this->oauth2Service->getAuthorizationServer()->validateAuthorizationRequest($psr7Request);
            
            if ($request->getMethod() === 'POST') {
                // CSRF protection for authorization approval
                if (!$this->validateCsrfToken($request, 'oauth_authorize')) {
                    return new JsonResponse([
                        'error' => 'invalid_request',
                        'error_description' => 'Invalid CSRF token provided'
                    ], Response::HTTP_FORBIDDEN);
                }

                // Handle user approval/denial
                $approved = $request->request->getBoolean('approved', false);
                $authRequest->setAuthorizationApproved($approved);
                
                if ($approved) {
                    // Set user entity for approved requests
                    $userId = $request->request->get('user_id');
                    if ($userId) {
                        $userEntity = $this->oauth2Service->createUserEntity($userId);
                        $authRequest->setUser($userEntity);
                    }
                }

                $psr7Response = $this->oauth2Service->getAuthorizationServer()->completeAuthorizationRequest(
                    $authRequest,
                    $psr7Response
                );

                return $this->httpFoundationFactory->createResponse($psr7Response);
            }

            // GET request - show authorization form
            return $this->renderAuthorizationForm($authRequest, $request);
        } catch (OAuthServerException $exception) {
            $this->logger->error('OAuth2 authorization error: ' . $exception->getMessage(), [
                'exception' => $exception,
                'client_id' => $request->query->get('client_id'),
            ]);

            return $this->httpFoundationFactory->createResponse(
                $exception->generateHttpResponse($psr7Response)
            );
        } catch (\Exception $exception) {
            $this->logger->error('OAuth2 authorization error: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            return new Response('Server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Enhanced introspect endpoint with comprehensive token validation
     * 
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
                'token_type' => 'Bearer',
                'exp' => $psr7Request->getAttribute('oauth_access_token_expiry_date_time')?->getTimestamp(),
                'iat' => time(),
                'sub' => $psr7Request->getAttribute('oauth_user_id'),
                'aud' => $psr7Request->getAttribute('oauth_client_id'),
            ];

            return new JsonResponse($response);
        } catch (OAuthServerException $exception) {
            $this->logger->error('OAuth2 introspect error: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            return new JsonResponse(['active' => false]);
        } catch (\Exception $exception) {
            $this->logger->error('OAuth2 introspect error: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            return new JsonResponse(['active' => false]);
        }
    }

    /**
     * Token revocation endpoint
     * 
     * @Route("/api/oauth/revoke", name="api.oauth.revoke", methods={"POST"})
     */
    public function revoke(Request $request): Response
    {
        // CSRF protection for revocation
        if (!$this->validateCsrfToken($request, 'oauth_revoke')) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'error_description' => 'Invalid CSRF token provided'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $token = $request->request->get('token');
            $tokenTypeHint = $request->request->get('token_type_hint', 'access_token');

            if (!$token) {
                return new JsonResponse([
                    'error' => 'invalid_request',
                    'error_description' => 'Token parameter is required'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Revoke token using OAuth2 service
            $success = $this->oauth2Service->revokeToken($token, $tokenTypeHint);

            if ($success) {
                $this->logger->info('OAuth2 token revoked successfully', [
                    'token_hint' => $tokenTypeHint,
                    'ip' => $request->getClientIp(),
                ]);

                return new Response('', Response::HTTP_OK);
            }

            return new JsonResponse([
                'error' => 'invalid_token',
                'error_description' => 'Token not found or already revoked'
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $exception) {
            $this->logger->error('OAuth2 revoke error: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            return new Response('Server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * JSON Web Key Set endpoint for JWT verification
     * 
     * @Route("/api/oauth/jwks", name="api.oauth.jwks", methods={"GET"})
     */
    public function jwks(): JsonResponse
    {
        try {
            $jwks = $this->oauth2Service->getPublicKeys();
            
            return new JsonResponse([
                'keys' => $jwks
            ]);
        } catch (\Exception $exception) {
            $this->logger->error('OAuth2 JWKS error: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            return new JsonResponse([
                'error' => 'server_error',
                'error_description' => 'Unable to retrieve public keys'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate CSRF token for OAuth2 operations
     */
    private function validateCsrfToken(Request $request, string $intention): bool
    {
        $token = $request->request->get('_csrf_token') 
            ?? $request->headers->get('X-CSRF-Token')
            ?? $request->headers->get('X-Requested-With');

        if (!$token) {
            return false;
        }

        return $this->csrfTokenManager->isTokenValid(new CsrfToken($intention, $token));
    }

    /**
     * Validate token request parameters
     */
    private function validateTokenRequest(Request $request, ?string $grantType): void
    {
        if (!$grantType) {
            throw new \InvalidArgumentException('Grant type is required');
        }

        $validGrantTypes = ['authorization_code', 'client_credentials', 'refresh_token', 'password'];
        if (!in_array($grantType, $validGrantTypes)) {
            throw new \InvalidArgumentException('Invalid grant type');
        }

        // Flow-specific validation
        switch ($grantType) {
            case 'authorization_code':
                if (!$request->request->get('code') || !$request->request->get('redirect_uri')) {
                    throw new \InvalidArgumentException('Code and redirect_uri are required for authorization code flow');
                }
                break;
            case 'client_credentials':
                if (!$request->request->get('client_id') || !$request->request->get('client_secret')) {
                    throw new \InvalidArgumentException('Client credentials are required');
                }
                break;
            case 'refresh_token':
                if (!$request->request->get('refresh_token')) {
                    throw new \InvalidArgumentException('Refresh token is required');
                }
                break;
        }
    }

    /**
     * Add security headers to response
     */
    private function addSecurityHeaders(Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
    }

    /**
     * Render authorization form for OAuth2 consent
     */
    private function renderAuthorizationForm($authRequest, Request $request): Response
    {
        // For API-only implementation, return JSON response
        // In a full implementation, this would render an HTML form
        return new JsonResponse([
            'client_id' => $authRequest->getClient()->getIdentifier(),
            'client_name' => $authRequest->getClient()->getName(),
            'scopes' => array_map(fn($scope) => $scope->getIdentifier(), $authRequest->getScopes()),
            'redirect_uri' => $authRequest->getRedirectUri(),
            'state' => $request->query->get('state'),
            'csrf_token' => $this->csrfTokenManager->getToken('oauth_authorize')->getValue(),
        ]);
    }
}
