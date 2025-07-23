<?php

declare(strict_types=1);

namespace AdvancedWishlist\Administration\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class AnalyticsController extends AbstractController
{
    private \AdvancedWishlist\Service\AnalyticsService $analyticsService;

    public function __construct(\AdvancedWishlist\Service\AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * @RouteScope(scopes={"administration"})
     */
    public function getAnalyticsSummary(Context $context): JsonResponse
    {
        try {
            $summary = $this->analyticsService->getAnalyticsSummary($context);
            
            return new JsonResponse([
                'totalWishlists' => $summary['totalWishlists'] ?? 0,
                'totalItems' => $summary['totalItems'] ?? 0,
                'totalShares' => $summary['totalShares'] ?? 0,
                'totalConversions' => $summary['totalConversions'] ?? 0,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to fetch analytics data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
