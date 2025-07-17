<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\OAuth\Repository;

use AdvancedWishlist\Core\OAuth\Entity\ScopeEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

class ScopeRepository implements ScopeRepositoryInterface
{
    private array $scopes = [
        'wishlist:read' => 'Read wishlists',
        'wishlist:write' => 'Create and update wishlists',
        'wishlist:delete' => 'Delete wishlists',
        'wishlist:share' => 'Share wishlists',
    ];

    /**
     * Return information about a scope.
     *
     * @param string $identifier The scope identifier
     */
    public function getScopeEntityByIdentifier($identifier): ?ScopeEntityInterface
    {
        if (!array_key_exists($identifier, $this->scopes)) {
            return null;
        }

        return new ScopeEntity($identifier);
    }

    /**
     * Given a client, grant type and user identifier, return the scopes the user can use.
     *
     * @param ScopeEntityInterface[] $scopes
     * @param string                 $grantType
     * @param string|null            $userIdentifier
     * @param string|null            $authCodeId
     *
     * @return ScopeEntityInterface[]
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null,
        $authCodeId = null,
    ): array {
        // For simplicity, we'll allow all requested scopes
        // In a real application, you would check if the client and user are allowed to use the requested scopes
        return $scopes;
    }
}
