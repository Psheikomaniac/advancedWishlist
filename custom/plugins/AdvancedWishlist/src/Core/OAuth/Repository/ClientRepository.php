<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\OAuth\Repository;

use AdvancedWishlist\Core\OAuth\Entity\ClientEntity;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class ClientRepository implements ClientRepositoryInterface
{
    /**
     * @var array
     */
    private array $clients;

    /**
     * ClientRepository constructor.
     */
    public function __construct()
    {
        // In a real application, these would be loaded from a database
        $this->clients = [
            'wishlist_client' => [
                'name' => 'Wishlist API Client',
                'redirect_uri' => 'https://example.com/callback',
                'is_confidential' => true,
                'secret' => password_hash('wishlist_secret', PASSWORD_BCRYPT),
            ],
        ];
    }

    /**
     * Get a client.
     *
     * @param string $clientIdentifier The client's identifier
     * @param string|null $grantType The grant type used
     * @param string|null $clientSecret The client's secret (if sent)
     * @param bool $mustValidateSecret If true the client must attempt to validate the secret if the client
     *                                 is confidential
     *
     * @return \League\OAuth2\Server\Entities\ClientEntityInterface|null
     */
    public function getClientEntity(
        $clientIdentifier,
        $grantType = null,
        $clientSecret = null,
        $mustValidateSecret = true
    ): ?\League\OAuth2\Server\Entities\ClientEntityInterface {
        // Check if client is registered
        if (!array_key_exists($clientIdentifier, $this->clients)) {
            return null;
        }

        $client = $this->clients[$clientIdentifier];

        // Check if client is confidential and validate secret if required
        if ($mustValidateSecret && $client['is_confidential'] && !$this->validateClient($clientIdentifier, $clientSecret)) {
            return null;
        }

        $clientEntity = new ClientEntity(
            $clientIdentifier,
            $client['name'],
            $client['redirect_uri'],
            $client['is_confidential']
        );

        return $clientEntity;
    }

    /**
     * Validate a client's secret.
     *
     * @param string $clientIdentifier The client's identifier
     * @param string|null $clientSecret The client's secret
     * @param string|null $grantType The grant type used
     *
     * @return bool
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType = null): bool
    {
        if (!array_key_exists($clientIdentifier, $this->clients)) {
            return false;
        }

        if (empty($clientSecret)) {
            return false;
        }

        return password_verify($clientSecret, $this->clients[$clientIdentifier]['secret']);
    }
}
