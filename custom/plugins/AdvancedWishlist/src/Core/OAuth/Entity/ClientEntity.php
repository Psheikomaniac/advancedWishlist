<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\OAuth\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ClientEntity implements ClientEntityInterface
{
    use EntityTrait, ClientTrait;

    /**
     * ClientEntity constructor.
     *
     * @param string $identifier
     * @param string $name
     * @param string|array $redirectUri
     * @param bool $isConfidential
     */
    public function __construct(
        string $identifier,
        string $name,
        $redirectUri,
        bool $isConfidential = false
    ) {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->redirectUri = $redirectUri;
        $this->isConfidential = $isConfidential;
    }
}
