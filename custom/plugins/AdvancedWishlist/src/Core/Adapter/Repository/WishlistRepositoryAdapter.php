<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Adapter\Repository;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Port\WishlistRepositoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adapter for wishlist repository operations
 * Implements the WishlistRepositoryInterface using Shopware's EntityRepository
 * Part of the hexagonal architecture implementation
 */
class WishlistRepositoryAdapter implements WishlistRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'wishlist.repository')]
        private readonly EntityRepository $repository
    ) {}

    /**
     * Find a wishlist by ID
     */
    public function find(string $id, Context $context): ?WishlistEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('items.product.cover');
        $criteria->addAssociation('items.product.prices');
        $criteria->addAssociation('customer');
        $criteria->addAssociation('shareInfo');
        
        return $this->repository->search($criteria, $context)->first();
    }
    
    /**
     * Search for wishlists using criteria
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->repository->search($criteria, $context);
    }
    
    /**
     * Create a new wishlist
     */
    public function create(array $data, Context $context): void
    {
        $this->repository->create([$data], $context);
    }
    
    /**
     * Update an existing wishlist
     */
    public function update(array $data, Context $context): void
    {
        $this->repository->update($data, $context);
    }
    
    /**
     * Delete a wishlist
     */
    public function delete(array $ids, Context $context): void
    {
        $this->repository->delete($ids, $context);
    }
    
    /**
     * Count wishlists for a customer
     */
    public function countForCustomer(string $customerId, Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        
        return $this->repository->search($criteria, $context)->getTotal();
    }
    
    /**
     * Find default wishlist for a customer
     */
    public function findDefaultForCustomer(string $customerId, Context $context): ?WishlistEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addFilter(new EqualsFilter('isDefault', true));
        $criteria->setLimit(1);
        
        return $this->repository->search($criteria, $context)->first();
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction(): void
    {
        $this->repository->beginTransaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit(): void
    {
        $this->repository->commit();
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback(): void
    {
        $this->repository->rollback();
    }
}