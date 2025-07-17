<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\OAuth\Entity;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

class ScopeEntity implements ScopeEntityInterface
{
    use EntityTrait;
    use ScopeTrait;

    /**
     * ScopeEntity constructor.
     */
    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Serialize the scope.
     */
    public function jsonSerialize(): string
    {
        return $this->getIdentifier();
    }
}
