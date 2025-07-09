<?php declare(strict_types=1);

namespace AdvancedWishlist\Storefront\Controller;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\Service\WishlistService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class WishlistController extends StorefrontController
{
    public function __construct(
        private WishlistService $wishlistService
    ) {}

    /**
     * @Route("/store-api/wishlist", name="store-api.wishlist.list", methods={'GET'})
     */
    public function list(Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__UNAUTHORIZED', 'title' => 'Unauthorized', 'detail' => 'Customer not logged in']]], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $criteria = new Criteria();
        // Add pagination, sorting, and filtering from request

        $wishlists = $this->wishlistService->getWishlists($customerId, $criteria, $context);

        return new JsonResponse($wishlists);
    }

    /**
     * @Route("/store-api/wishlist/{id}", name="store-api.wishlist.detail", methods={'GET'})
     */
    public function detail(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__UNAUTHORIZED', 'title' => 'Unauthorized', 'detail' => 'Customer not logged in']]], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $wishlist = $this->wishlistService->loadWishlist($id, $context);

        return new JsonResponse($wishlist);
    }

    /**
     * @Route("/store-api/wishlist", name="store-api.wishlist.create", methods={'POST'})
     */
    public function create(Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__UNAUTHORIZED', 'title' => 'Unauthorized', 'detail' => 'Customer not logged in']]], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $createRequest = new CreateWishlistRequest();
        $createRequest->assign($request->request->all());
        $createRequest->setCustomerId($customerId);

        $wishlist = $this->wishlistService->createWishlist($createRequest, $context->getContext());

        return new JsonResponse($wishlist, JsonResponse::HTTP_CREATED);
    }

    /**
     * @Route("/store-api/wishlist/{id}", name="store-api.wishlist.update", methods={'PATCH'})
     */
    public function update(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__UNAUTHORIZED', 'title' => 'Unauthorized', 'detail' => 'Customer not logged in']]], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $updateRequest = new UpdateWishlistRequest();
        $updateRequest->assign($request->request->all());
        $updateRequest->setWishlistId($id);

        $wishlist = $this->wishlistService->updateWishlist($updateRequest, $context->getContext());

        return new JsonResponse($wishlist);
    }

    /**
     * @Route("/store-api/wishlist/{id}", name="store-api.wishlist.delete", methods={'DELETE'})
     */
    public function delete(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__UNAUTHORIZED', 'title' => 'Unauthorized', 'detail' => 'Customer not logged in']]], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $transferToWishlistId = $request->query->get('transferTo');

        $this->wishlistService->deleteWishlist($id, $transferToWishlistId, $context->getContext());

        return new JsonResponse(['success' => true, 'message' => 'Wishlist deleted successfully']);
    }
}
