<?php declare(strict_types=1);

namespace AdvancedWishlist\Tests\Factory;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Factory for creating test wishlist item entities
 */
class WishlistItemFactory extends TestEntityFactory
{
    public function __construct(
        private EntityRepository $wishlistItemRepository
    ) {}

    /**
     * Create a wishlist item with the given data
     */
    public function createWishlistItem(
        array $data,
        Context $context
    ): string {
        $defaults = [
            'quantity' => 1,
            'note' => 'Test wishlist item note',
            'priority' => 0,
            'priceAlertActive' => false,
        ];

        $data = $this->mergeData($defaults, $data);

        return parent::create($this->wishlistItemRepository, $data, $context);
    }

    /**
     * Create a wishlist item with price alert
     */
    public function createWishlistItemWithPriceAlert(
        string $wishlistId,
        string $productId,
        string $productVersionId,
        float $priceAlertThreshold,
        Context $context
    ): string {
        return $this->createWishlistItem([
            'wishlistId' => $wishlistId,
            'productId' => $productId,
            'productVersionId' => $productVersionId,
            'priceAlertThreshold' => $priceAlertThreshold,
            'priceAlertActive' => true,
        ], $context);
    }

    /**
     * Get a wishlist item by ID
     */
    public function getWishlistItem(
        string $id,
        Context $context,
        array $associations = ['product', 'wishlist']
    ): ?WishlistItemEntity {
        return parent::get($this->wishlistItemRepository, $id, $context, $associations);
    }

    /**
     * Find wishlist items by wishlist ID
     */
    public function findByWishlist(
        string $wishlistId,
        Context $context,
        array $associations = ['product', 'wishlist']
    ): array {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('wishlistId', $wishlistId));

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        return $this->wishlistItemRepository->search($criteria, $context)->getElements();
    }

    /**
     * Find a wishlist item by product ID and wishlist ID
     */
    public function findByProductAndWishlist(
        string $productId,
        string $wishlistId,
        Context $context,
        array $associations = ['product', 'wishlist']
    ): ?WishlistItemEntity {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new EqualsFilter('wishlistId', $wishlistId));
        $criteria->setLimit(1);

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        return $this->wishlistItemRepository->search($criteria, $context)->first();
    }

    /**
     * Delete a wishlist item
     */
    public function deleteWishlistItem(
        string $id,
        Context $context
    ): void {
        parent::delete($this->wishlistItemRepository, $id, $context);
    }

    /**
     * Generate random wishlist item data
     */
    public function getRandomData(): array
    {
        return [
            'wishlistId' => Uuid::randomHex(),
            'productId' => Uuid::randomHex(),
            'productVersionId' => Uuid::randomHex(),
            'quantity' => random_int(1, 10),
            'note' => 'Random note ' . Uuid::randomHex(),
            'priority' => random_int(0, 5),
            'priceAlertThreshold' => random_int(50, 200) / 10,
            'priceAlertActive' => (bool) random_int(0, 1),
        ];
    }

    /**
     * Generate random wishlist item data for a specific wishlist and product
     */
    public function getRandomDataForWishlistAndProduct(
        string $wishlistId,
        string $productId,
        string $productVersionId
    ): array {
        $data = $this->getRandomData();
        $data['wishlistId'] = $wishlistId;
        $data['productId'] = $productId;
        $data['productVersionId'] = $productVersionId;
        return $data;
    }
}