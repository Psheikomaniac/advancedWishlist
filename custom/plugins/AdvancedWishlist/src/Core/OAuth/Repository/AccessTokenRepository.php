<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\OAuth\Repository;

use AdvancedWishlist\Core\OAuth\Entity\AccessTokenEntity;
use DateTimeImmutable;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    /**
     * @var AccessTokenEntityInterface[]
     */
    private array $tokens = [];

    /**
     * Create a new access token
     *
     * @param ClientEntityInterface $clientEntity
     * @param ScopeEntityInterface[] $scopes
     * @param string|null $userIdentifier
     *
     * @return AccessTokenEntityInterface
     */
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        $userIdentifier = null
    ): AccessTokenEntityInterface
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        
        if ($userIdentifier !== null) {
            $accessToken->setUserIdentifier($userIdentifier);
        }
        
        return $accessToken;
    }

    /**
     * Persists a new access token to permanent storage.
     *
     * @param AccessTokenEntityInterface $accessTokenEntity
     *
     * @throws UniqueTokenIdentifierConstraintViolationException
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        // Check if token ID is unique
        if (isset($this->tokens[$accessTokenEntity->getIdentifier()])) {
            throw new UniqueTokenIdentifierConstraintViolationException();
        }
        
        // In a real application, you would persist the token to a database
        $this->tokens[$accessTokenEntity->getIdentifier()] = $accessTokenEntity;
    }

    /**
     * Revoke an access token.
     *
     * @param string $tokenId
     */
    public function revokeAccessToken($tokenId): void
    {
        // In a real application, you would mark the token as revoked in the database
        unset($this->tokens[$tokenId]);
    }

    /**
     * Check if the access token has been revoked.
     *
     * @param string $tokenId
     *
     * @return bool Return true if this token has been revoked
     */
    public function isAccessTokenRevoked($tokenId): bool
    {
        // In a real application, you would check if the token is marked as revoked in the database
        return !isset($this->tokens[$tokenId]);
    }
}