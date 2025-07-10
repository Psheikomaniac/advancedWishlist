<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\OAuth\Service;

use AdvancedWishlist\Core\OAuth\Repository\AccessTokenRepository;
use AdvancedWishlist\Core\OAuth\Repository\ClientRepository;
use AdvancedWishlist\Core\OAuth\Repository\RefreshTokenRepository;
use AdvancedWishlist\Core\OAuth\Repository\ScopeRepository;
use DateInterval;
use Defuse\Crypto\Key;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use Psr\Log\LoggerInterface;

class OAuth2Service
{
    private AuthorizationServer $authorizationServer;
    private ResourceServer $resourceServer;
    private LoggerInterface $logger;

    /**
     * OAuth2Service constructor.
     *
     * @param ClientRepository $clientRepository
     * @param AccessTokenRepository $accessTokenRepository
     * @param ScopeRepository $scopeRepository
     * @param RefreshTokenRepository $refreshTokenRepository
     * @param string $privateKeyPath
     * @param string $publicKeyPath
     * @param string $encryptionKey
     * @param LoggerInterface $logger
     */
    public function __construct(
        ClientRepository $clientRepository,
        AccessTokenRepository $accessTokenRepository,
        ScopeRepository $scopeRepository,
        RefreshTokenRepository $refreshTokenRepository,
        string $privateKeyPath,
        string $publicKeyPath,
        string $encryptionKey,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;

        // Set up the authorization server
        $this->authorizationServer = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            new CryptKey($privateKeyPath, null, false),
            Key::loadFromAsciiSafeString($encryptionKey)
        );

        // Enable the client credentials grant
        $this->authorizationServer->enableGrantType(
            new ClientCredentialsGrant(),
            new DateInterval('PT1H') // Access tokens will expire after 1 hour
        );

        // Enable the password grant
        $passwordGrant = new PasswordGrant(
            $refreshTokenRepository,
            $accessTokenRepository
        );
        $passwordGrant->setRefreshTokenTTL(new DateInterval('P1M')); // Refresh tokens will expire after 1 month
        $this->authorizationServer->enableGrantType(
            $passwordGrant,
            new DateInterval('PT1H') // Access tokens will expire after 1 hour
        );

        // Enable the refresh token grant
        $refreshTokenGrant = new RefreshTokenGrant($refreshTokenRepository);
        $refreshTokenGrant->setRefreshTokenTTL(new DateInterval('P1M')); // Refresh tokens will expire after 1 month
        $this->authorizationServer->enableGrantType(
            $refreshTokenGrant,
            new DateInterval('PT1H') // Access tokens will expire after 1 hour
        );

        // Set up the resource server
        $this->resourceServer = new ResourceServer(
            $accessTokenRepository,
            new CryptKey($publicKeyPath, null, false)
        );
    }

    /**
     * Get the authorization server.
     *
     * @return AuthorizationServer
     */
    public function getAuthorizationServer(): AuthorizationServer
    {
        return $this->authorizationServer;
    }

    /**
     * Get the resource server.
     *
     * @return ResourceServer
     */
    public function getResourceServer(): ResourceServer
    {
        return $this->resourceServer;
    }
}