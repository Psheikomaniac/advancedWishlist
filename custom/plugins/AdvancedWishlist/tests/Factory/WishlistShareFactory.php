<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Factory;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Factory for creating test wishlist share entities.
 */
class WishlistShareFactory extends TestEntityFactory
{
    public function __construct(
        private EntityRepository $wishlistShareRepository,
    ) {
    }

    /**
     * Create a wishlist share with the given data.
     */
    public function createWishlistShare(
        array $data,
        Context $context,
    ): string {
        $defaults = [
            'token' => bin2hex(random_bytes(32)),
            'type' => 'link',
            'active' => true,
            'views' => 0,
            'uniqueViews' => 0,
            'conversions' => 0,
        ];

        $data = $this->mergeData($defaults, $data);

        return parent::create($this->wishlistShareRepository, $data, $context);
    }

    /**
     * Create a wishlist share with expiration.
     */
    public function createWishlistShareWithExpiration(
        string $wishlistId,
        \DateTimeInterface $expiresAt,
        Context $context,
    ): string {
        return $this->createWishlistShare([
            'wishlistId' => $wishlistId,
            'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
        ], $context);
    }

    /**
     * Create a password-protected wishlist share.
     */
    public function createPasswordProtectedShare(
        string $wishlistId,
        string $password,
        Context $context,
    ): string {
        return $this->createWishlistShare([
            'wishlistId' => $wishlistId,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ], $context);
    }

    /**
     * Get a wishlist share by ID.
     */
    public function getWishlistShare(
        string $id,
        Context $context,
        array $associations = ['wishlist'],
    ): ?WishlistShareEntity {
        return parent::get($this->wishlistShareRepository, $id, $context, $associations);
    }

    /**
     * Find a wishlist share by token.
     */
    public function findByToken(
        string $token,
        Context $context,
        array $associations = ['wishlist'],
    ): ?WishlistShareEntity {
        return parent::findOneBy($this->wishlistShareRepository, 'token', $token, $context, $associations);
    }

    /**
     * Find wishlist shares by wishlist ID.
     */
    public function findByWishlist(
        string $wishlistId,
        Context $context,
        array $associations = ['wishlist'],
    ): array {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('wishlistId', $wishlistId));

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        return $this->wishlistShareRepository->search($criteria, $context)->getElements();
    }

    /**
     * Delete a wishlist share.
     */
    public function deleteWishlistShare(
        string $id,
        Context $context,
    ): void {
        parent::delete($this->wishlistShareRepository, $id, $context);
    }

    /**
     * Generate random wishlist share data.
     */
    public function getRandomData(): array
    {
        return [
            'wishlistId' => Uuid::randomHex(),
            'token' => bin2hex(random_bytes(32)),
            'type' => array_rand(['link' => 1, 'email' => 1, 'social' => 1]),
            'platform' => random_int(0, 1) ? array_rand(['facebook' => 1, 'twitter' => 1, 'whatsapp' => 1]) : null,
            'active' => (bool) random_int(0, 1),
            'password' => random_int(0, 1) ? password_hash('password'.Uuid::randomHex(), PASSWORD_DEFAULT) : null,
            'expiresAt' => random_int(0, 1) ? (new \DateTime())->modify('+'.random_int(1, 30).' days')->format('Y-m-d H:i:s') : null,
            'settings' => [
                'hidePrices' => (bool) random_int(0, 1),
                'readOnly' => (bool) random_int(0, 1),
                'allowGuestPurchase' => (bool) random_int(0, 1),
            ],
        ];
    }

    /**
     * Generate random wishlist share data for a specific wishlist.
     */
    public function getRandomDataForWishlist(string $wishlistId): array
    {
        $data = $this->getRandomData();
        $data['wishlistId'] = $wishlistId;

        return $data;
    }
}
