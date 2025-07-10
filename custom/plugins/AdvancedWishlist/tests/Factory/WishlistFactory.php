<?php declare(strict_types=1);

namespace AdvancedWishlist\Tests\Factory;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Factory for creating test wishlist entities
 */
class WishlistFactory extends TestEntityFactory
{
    public function __construct(
        private EntityRepository $wishlistRepository
    ) {}

    /**
     * Create a wishlist with the given data
     */
    public function createWishlist(
        array $data,
        Context $context
    ): string {
        $defaults = [
            'name' => 'Test Wishlist ' . Uuid::randomHex(),
            'description' => 'Test wishlist description',
            'type' => 'private',
            'isDefault' => false,
            'salesChannelId' => $context->getSource()->getSalesChannelId(),
            'languageId' => $context->getLanguageId(),
        ];

        $data = $this->mergeData($defaults, $data);

        return parent::create($this->wishlistRepository, $data, $context);
    }

    /**
     * Create a default wishlist for a customer
     */
    public function createDefaultWishlist(
        string $customerId,
        Context $context
    ): string {
        return $this->createWishlist([
            'customerId' => $customerId,
            'name' => 'Default Wishlist',
            'isDefault' => true,
        ], $context);
    }

    /**
     * Get a wishlist by ID
     */
    public function getWishlist(
        string $id,
        Context $context,
        array $associations = ['items', 'customer', 'shareInfo']
    ): ?WishlistEntity {
        return parent::get($this->wishlistRepository, $id, $context, $associations);
    }

    /**
     * Find a wishlist by customer ID
     */
    public function findByCustomer(
        string $customerId,
        Context $context,
        array $associations = ['items', 'customer', 'shareInfo']
    ): ?WishlistEntity {
        return parent::findOneBy($this->wishlistRepository, 'customerId', $customerId, $context, $associations);
    }

    /**
     * Find the default wishlist for a customer
     */
    public function findDefaultWishlist(
        string $customerId,
        Context $context,
        array $associations = ['items', 'customer', 'shareInfo']
    ): ?WishlistEntity {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addFilter(new EqualsFilter('isDefault', true));
        $criteria->setLimit(1);

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        return $this->wishlistRepository->search($criteria, $context)->first();
    }

    /**
     * Delete a wishlist
     */
    public function deleteWishlist(
        string $id,
        Context $context
    ): void {
        parent::delete($this->wishlistRepository, $id, $context);
    }

    /**
     * Generate random wishlist data
     */
    public function getRandomData(): array
    {
        return [
            'customerId' => Uuid::randomHex(),
            'name' => 'Random Wishlist ' . Uuid::randomHex(),
            'description' => 'Random wishlist description ' . Uuid::randomHex(),
            'type' => array_rand(['private' => 1, 'public' => 1, 'shared' => 1]),
            'isDefault' => false,
        ];
    }

    /**
     * Generate random wishlist data for a specific customer
     */
    public function getRandomDataForCustomer(string $customerId): array
    {
        $data = $this->getRandomData();
        $data['customerId'] = $customerId;
        return $data;
    }
}
