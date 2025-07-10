<?php declare(strict_types=1);

namespace AdvancedWishlist\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class AnalyticsService
{
    private EntityRepository $wishlistRepository;
    private EntityRepository $wishlistShareViewRepository;
    private EntityRepository $wishlistAnalyticsRepository;

    public function __construct(
        EntityRepository $wishlistRepository,
        EntityRepository $wishlistShareViewRepository,
        EntityRepository $wishlistAnalyticsRepository
    )
    {
        $this->wishlistRepository = $wishlistRepository;
        $this->wishlistShareViewRepository = $wishlistShareViewRepository;
        $this->wishlistAnalyticsRepository = $wishlistAnalyticsRepository;
    }

    /**
     * Track wishlist creation for analytics
     * This method is called asynchronously via Symfony Messenger
     */
    public function trackWishlistCreation(string $wishlistId, string $customerId, Context $context): void
    {
        // Create an analytics entry for the new wishlist
        $this->wishlistAnalyticsRepository->create([
            [
                'id' => \Shopware\Core\Framework\Uuid\Uuid::randomHex(),
                'wishlistId' => $wishlistId,
                'customerId' => $customerId,
                'eventType' => 'creation',
                'eventDate' => (new \DateTime())->format(\DateTimeInterface::ATOM),
                'data' => json_encode([
                    'source' => 'api',
                    'timestamp' => time(),
                ]),
            ]
        ], $context);
    }

    public function getSummary(Context $context): array
    {
        $totalWishlists = $this->wishlistRepository->search(new Criteria(), $context)->getTotal();
        $totalShares = $this->wishlistShareViewRepository->search(new Criteria(), $context)->getTotal();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('conversions', 1)); // Assuming a conversion is marked by conversions = 1
        $totalConversions = $this->wishlistAnalyticsRepository->search($criteria, $context)->getTotal();

        // This is a placeholder. A proper implementation would sum item counts from wishlists.
        $totalItems = 0;

        return [
            'totalWishlists' => $totalWishlists,
            'totalItems' => $totalItems,
            'totalShares' => $totalShares,
            'totalConversions' => $totalConversions,
        ];
    }
}
