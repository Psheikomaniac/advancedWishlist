<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Query;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use AdvancedWishlist\Core\Exception\WishlistNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * Handler for the GetWishlistQuery.
 * 
 * This class is responsible for processing the GetWishlistQuery and retrieving
 * the requested wishlist from the repository.
 */
class GetWishlistQueryHandler implements QueryHandlerInterface
{
    /**
     * @param EntityRepository $wishlistRepository Repository for wishlist entities
     */
    public function __construct(
        private readonly EntityRepository $wishlistRepository
    ) {
    }

    /**
     * Handle the GetWishlistQuery by retrieving the requested wishlist.
     * 
     * @param QueryInterface $query The query to handle
     * @return WishlistEntity The retrieved wishlist
     * @throws WishlistNotFoundException If the wishlist is not found
     * @throws \InvalidArgumentException If the query is not a GetWishlistQuery
     */
    public function handle(QueryInterface $query): WishlistEntity
    {
        if (!$query instanceof GetWishlistQuery) {
            throw new \InvalidArgumentException(sprintf(
                'Expected query of type %s, got %s',
                GetWishlistQuery::class,
                get_class($query)
            ));
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $query->wishlistId));
        $criteria->addAssociation('items');
        $criteria->addAssociation('shares');

        $wishlist = $this->wishlistRepository->search($criteria, $query->getContext())->first();

        if (!$wishlist instanceof WishlistEntity) {
            throw new WishlistNotFoundException($query->wishlistId);
        }

        return $wishlist;
    }
}