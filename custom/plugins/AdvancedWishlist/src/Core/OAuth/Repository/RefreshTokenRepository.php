<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\OAuth\Repository;

use AdvancedWishlist\Core\OAuth\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    /**
     * @var RefreshTokenEntityInterface[]
     */
    private array $refreshTokens = [];

    /**
     * Create a new refresh token.
     *
     * @return RefreshTokenEntityInterface
     */
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    /**
     * Persists a new refresh token to permanent storage.
     *
     * @param RefreshTokenEntityInterface $refreshTokenEntity
     *
     * @throws UniqueTokenIdentifierConstraintViolationException
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        // Check if token ID is unique
        if (isset($this->refreshTokens[$refreshTokenEntity->getIdentifier()])) {
            throw new UniqueTokenIdentifierConstraintViolationException();
        }
        
        // In a real application, you would persist the token to a database
        $this->refreshTokens[$refreshTokenEntity->getIdentifier()] = $refreshTokenEntity;
    }

    /**
     * Revoke a refresh token.
     *
     * @param string $tokenId
     */
    public function revokeRefreshToken($tokenId): void
    {
        // In a real application, you would mark the token as revoked in the database
        unset($this->refreshTokens[$tokenId]);
    }

    /**
     * Check if the refresh token has been revoked.
     *
     * @param string $tokenId
     *
     * @return bool Return true if this token has been revoked
     */
    public function isRefreshTokenRevoked($tokenId): bool
    {
        // In a real application, you would check if the token is marked as revoked in the database
        return !isset($this->refreshTokens[$tokenId]);
    }
}