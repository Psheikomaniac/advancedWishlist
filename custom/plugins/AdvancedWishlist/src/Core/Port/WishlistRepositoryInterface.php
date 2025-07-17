<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Port;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Port (interface) for wishlist repository operations
 * Part of the hexagonal architecture implementation.
 */
interface WishlistRepositoryInterface
{
    /**
     * Find a wishlist by ID.
     */
    public function find(string $id, Context $context): ?WishlistEntity;

    /**
     * Search for wishlists using criteria.
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult;

    /**
     * Create a new wishlist.
     */
    public function create(array $data, Context $context): void;

    /**
     * Update an existing wishlist.
     */
    public function update(array $data, Context $context): void;

    /**
     * Delete a wishlist.
     */
    public function delete(array $ids, Context $context): void;

    /**
     * Count wishlists for a customer.
     */
    public function countForCustomer(string $customerId, Context $context): int;

    /**
     * Find default wishlist for a customer.
     */
    public function findDefaultForCustomer(string $customerId, Context $context): ?WishlistEntity;

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commit a transaction.
     */
    public function commit(): void;

    /**
     * Rollback a transaction.
     */
    public function rollback(): void;
}
