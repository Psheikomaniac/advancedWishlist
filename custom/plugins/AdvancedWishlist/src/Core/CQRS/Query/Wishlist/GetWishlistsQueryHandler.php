<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Query\Wishlist;

use AdvancedWishlist\Core\Service\WishlistCacheService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Handler for the GetWishlistsQuery
 * Retrieves wishlists for a customer with pagination, sorting, and filtering.
 */
final readonly class GetWishlistsQueryHandler
{
    public function __construct(
        #[Autowire(service: 'wishlist.repository')]
        private EntityRepository $wishlistRepository,
        private WishlistCacheService $cacheService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Handle the query and return the response.
     */
    public function __invoke(GetWishlistsQuery $query): GetWishlistsQueryResponse
    {
        // Start performance monitoring
        $startTime = microtime(true);

        // Generate cache key based on customer ID and criteria
        $cacheKey = "customer_wishlists_{$query->customerId}_".$this->generateCriteriaHash($query->criteria);

        try {
            // Try to get from cache first
            return $this->cacheService->get($cacheKey, function () use ($query, $startTime) {
                // Add customer filter
                $query->criteria->addFilter(new EqualsFilter('customerId', $query->customerId));

                // Add default associations if no specific fields are requested
                if (empty($query->criteria->getFields())) {
                    $query->criteria->addAssociation('items.product.cover');
                }

                // Get wishlists from repository
                $searchStartTime = microtime(true);
                $result = $this->wishlistRepository->search($query->criteria, $query->context->getContext());
                $searchTime = microtime(true) - $searchStartTime;

                // Transform to array
                $transformStartTime = microtime(true);
                $wishlists = [];
                foreach ($result as $wishlist) {
                    // If specific fields are requested, only include those fields
                    if (!empty($query->criteria->getFields())) {
                        $wishlistData = [];
                        foreach ($query->criteria->getFields() as $field) {
                            // Handle nested fields like 'items.count'
                            if (false !== strpos($field, 'items.count')) {
                                $wishlistData['itemCount'] = $wishlist->getItems() ? $wishlist->getItems()->count() : 0;
                                continue;
                            }

                            // Handle standard fields
                            $getter = 'get'.ucfirst($field);
                            if (method_exists($wishlist, $getter)) {
                                $value = $wishlist->$getter();
                                // Format dates
                                if ($value instanceof \DateTimeInterface) {
                                    $value = $value->format(\DateTimeInterface::ATOM);
                                }
                                $wishlistData[$field] = $value;
                            }
                        }
                        $wishlists[] = $wishlistData;
                    } else {
                        // Default fields if no specific fields are requested
                        $wishlists[] = [
                            'id' => $wishlist->getId(),
                            'name' => $wishlist->getName(),
                            'description' => $wishlist->getDescription(),
                            'type' => $wishlist->getType(),
                            'isDefault' => $wishlist->isDefault(),
                            'itemCount' => $wishlist->getItems() ? $wishlist->getItems()->count() : 0,
                            'createdAt' => $wishlist->getCreatedAt()->format(\DateTimeInterface::ATOM),
                            'updatedAt' => $wishlist->getUpdatedAt() ? $wishlist->getUpdatedAt()->format(\DateTimeInterface::ATOM) : null,
                        ];
                    }
                }
                $transformTime = microtime(true) - $transformStartTime;

                // Calculate total execution time
                $totalTime = microtime(true) - $startTime;

                // Log performance metrics
                $this->logger->info('Wishlists retrieved', [
                    'customerId' => $query->customerId,
                    'count' => count($wishlists),
                    'performance' => [
                        'totalTimeMs' => round($totalTime * 1000, 2),
                        'searchTimeMs' => round($searchTime * 1000, 2),
                        'transformTimeMs' => round($transformTime * 1000, 2),
                    ],
                ]);

                // Prepare pagination information
                $page = (int) ($query->criteria->getOffset() / $query->criteria->getLimit() + 1);
                $pages = (int) ceil($result->getTotal() / $query->criteria->getLimit());

                return new GetWishlistsQueryResponse(
                    total: $result->getTotal(),
                    page: $page,
                    limit: $query->criteria->getLimit(),
                    pages: $pages,
                    wishlists: $wishlists
                );
            });
        } catch (\Exception $e) {
            // Log error
            $this->logger->error('Failed to retrieve wishlists', [
                'customerId' => $query->customerId,
                'error' => $e->getMessage(),
                'executionTimeMs' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            // Return error response
            return GetWishlistsQueryResponse::fromError(
                error: $e->getMessage(),
                limit: $query->criteria->getLimit()
            );
        }
    }

    /**
     * Generate a hash for a criteria object to use as part of a cache key.
     */
    private function generateCriteriaHash($criteria): string
    {
        $data = [
            'limit' => $criteria->getLimit(),
            'offset' => $criteria->getOffset(),
            'fields' => $criteria->getFields(),
            'filters' => [], // Add filter values if needed
            'sortings' => [], // Add sorting values if needed
        ];

        return md5(json_encode($data));
    }
}
