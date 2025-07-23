<?php

declare(strict_types=1);

namespace AdvancedWishlist\Storefront\Controller;

use AdvancedWishlist\Core\CQRS\Query\Wishlist\GetWishlistsQuery;
use AdvancedWishlist\Core\CQRS\Query\Wishlist\GetWishlistsQueryHandler;
use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\Service\WishlistCrudService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class WishlistController extends StorefrontController
{
    public function __construct(
        private WishlistCrudService $wishlistCrudService,
        private GetWishlistsQueryHandler $getWishlistsQueryHandler,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/store-api/wishlist', name: 'store-api.wishlist.list', methods: ['GET'])]
    public function list(Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__UNAUTHORIZED', 'title' => 'Unauthorized', 'detail' => 'Customer not logged in']]], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $criteria = new Criteria();

        // Add pagination from request with security validation
        $limit = $this->validateAndSanitizeInt($request->query->get('limit', '10'), 1, 100);
        $page = $this->validateAndSanitizeInt($request->query->get('page', '1'), 1, 1000);
        $offset = ($page - 1) * $limit;

        $criteria->setLimit($limit);
        $criteria->setOffset($offset);

        // Add field filtering from request with validation
        $fields = $request->query->get('fields');
        if ($fields && $this->validateFieldsParameter($fields)) {
            // Parse fields parameter (comma-separated list)
            $fieldArray = array_map('trim', explode(',', $fields));
            $allowedFields = ['id', 'name', 'description', 'type', 'isDefault', 'createdAt', 'updatedAt'];
            $fieldArray = array_intersect($fieldArray, $allowedFields);
            if (!empty($fieldArray)) {
                $criteria->setFields($fieldArray);
            }
        }

        // Add sorting from request with validation
        $sort = $request->query->get('sort');
        if ($sort && $this->validateSortParameter($sort)) {
            // Parse sort parameter (field:direction format)
            $sortParts = explode(':', $sort);
            $field = $sortParts[0];
            $direction = strtoupper($sortParts[1] ?? 'ASC');

            $allowedSortFields = ['id', 'name', 'createdAt', 'updatedAt'];
            if (in_array($field, $allowedSortFields) && in_array($direction, ['ASC', 'DESC'])) {
                $criteria->addSorting(new FieldSorting($field, $direction));
            } else {
                $criteria->addSorting(new FieldSorting('createdAt', 'DESC'));
            }
        } else {
            // Default sorting by creation date (newest first)
            $criteria->addSorting(new FieldSorting('createdAt', 'DESC'));
        }

        // Add filtering from request with validation
        $filter = $request->query->get('filter');
        if ($filter && $this->validateFilterParameter($filter)) {
            // Parse filter parameter (field:value format)
            $filterParts = explode(':', $filter, 2);
            if (2 === count($filterParts)) {
                $field = $filterParts[0];
                $value = $filterParts[1];

                $allowedFilterFields = ['type', 'isDefault'];
                if (in_array($field, $allowedFilterFields) && $this->validateFilterValue($field, $value)) {
                    $criteria->addFilter(new EqualsFilter($field, $value));
                }
            }
        }

        // Create and dispatch query using CQRS pattern
        $query = new GetWishlistsQuery(
            customerId: $customerId,
            criteria: $criteria,
            context: $context
        );

        $response = $this->getWishlistsQueryHandler->__invoke($query);

        return new JsonResponse($response);
    }

    #[Route('/store-api/wishlist/{id}', name: 'store-api.wishlist.detail', methods: ['GET'])]
    public function detail(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__UNAUTHORIZED', 'title' => 'Unauthorized', 'detail' => 'Customer not logged in']]], JsonResponse::HTTP_UNAUTHORIZED);
        }

        try {
            $wishlist = $this->wishlistCrudService->loadWishlist($id, $context->getContext());

            // Check if the wishlist belongs to the current customer or is public
            if ($wishlist->getCustomerId() !== $customerId && 'public' !== $wishlist->getType()) {
                // Check if the wishlist is shared with the customer
                $isShared = false;
                if ($wishlist->getShareInfo()) {
                    foreach ($wishlist->getShareInfo() as $share) {
                        if ($share->getRecipientId() === $customerId) {
                            $isShared = true;
                            break;
                        }
                    }
                }

                if (!$isShared) {
                    return new JsonResponse(['errors' => [['code' => 'WISHLIST__ACCESS_DENIED', 'title' => 'Access Denied', 'detail' => 'You do not have permission to view this wishlist']]], JsonResponse::HTTP_FORBIDDEN);
                }
            }

            return new JsonResponse($wishlist);
        } catch (\Exception $e) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__NOT_FOUND', 'title' => 'Not Found', 'detail' => 'Wishlist not found']]], JsonResponse::HTTP_NOT_FOUND);
        }
    }

    #[Route('/store-api/wishlist', name: 'store-api.wishlist.create', methods: ['POST'])]
    public function create(
        CreateWishlistRequest $createRequest,
        Request $request,
        SalesChannelContext $context,
    ): JsonResponse {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__UNAUTHORIZED', 'title' => 'Unauthorized', 'detail' => 'Customer not logged in']]], JsonResponse::HTTP_UNAUTHORIZED);
        }

        try {
            // Verify CSRF token for state-changing operation
            $token = $request->request->get('_csrf_token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('wishlist_create', $token))) {
                return new JsonResponse(['errors' => [['code' => 'WISHLIST__INVALID_CSRF_TOKEN', 'title' => 'Invalid CSRF Token', 'detail' => 'Invalid CSRF token provided']]], JsonResponse::HTTP_FORBIDDEN);
            }

            // Set the customer ID from the context
            $createRequest->setCustomerId($customerId);

            // Create the wishlist
            $wishlist = $this->wishlistCrudService->createWishlist($createRequest, $context->getContext());

            return new JsonResponse($wishlist, JsonResponse::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__CREATE_FAILED', 'title' => 'Create Failed', 'detail' => $e->getMessage()]]], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/store-api/wishlist/{id}', name: 'store-api.wishlist.update', methods: ['PATCH'])]
    public function update(
        string $id,
        UpdateWishlistRequest $updateRequest,
        Request $request,
        SalesChannelContext $context,
    ): JsonResponse {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__UNAUTHORIZED', 'title' => 'Unauthorized', 'detail' => 'Customer not logged in']]], JsonResponse::HTTP_UNAUTHORIZED);
        }

        try {
            // Verify CSRF token for state-changing operation
            $token = $request->request->get('_csrf_token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('wishlist_update', $token))) {
                return new JsonResponse(['errors' => [['code' => 'WISHLIST__INVALID_CSRF_TOKEN', 'title' => 'Invalid CSRF Token', 'detail' => 'Invalid CSRF token provided']]], JsonResponse::HTTP_FORBIDDEN);
            }

            // Check ownership before updating
            try {
                $wishlist = $this->wishlistCrudService->loadWishlist($id, $context->getContext());

                // Only the owner can update the wishlist
                if ($wishlist->getCustomerId() !== $customerId) {
                    return new JsonResponse(['errors' => [['code' => 'WISHLIST__ACCESS_DENIED', 'title' => 'Access Denied', 'detail' => 'You do not have permission to update this wishlist']]], JsonResponse::HTTP_FORBIDDEN);
                }

                // Set the wishlist ID
                $updateRequest->setWishlistId($id);

                // Update the wishlist
                $wishlist = $this->wishlistCrudService->updateWishlist($updateRequest, $context->getContext());

                return new JsonResponse($wishlist);
            } catch (\Exception $e) {
                return new JsonResponse(['errors' => [['code' => 'WISHLIST__NOT_FOUND', 'title' => 'Not Found', 'detail' => 'Wishlist not found']]], JsonResponse::HTTP_NOT_FOUND);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__UPDATE_FAILED', 'title' => 'Update Failed', 'detail' => $e->getMessage()]]], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/store-api/wishlist/{id}', name: 'store-api.wishlist.delete', methods: ['DELETE'])]
    public function delete(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__UNAUTHORIZED', 'title' => 'Unauthorized', 'detail' => 'Customer not logged in']]], JsonResponse::HTTP_UNAUTHORIZED);
        }

        try {
            // Verify CSRF token for state-changing operation
            $token = $request->request->get('_csrf_token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('wishlist_delete', $token))) {
                return new JsonResponse(['errors' => [['code' => 'WISHLIST__INVALID_CSRF_TOKEN', 'title' => 'Invalid CSRF Token', 'detail' => 'Invalid CSRF token provided']]], JsonResponse::HTTP_FORBIDDEN);
            }

            // Check ownership before deleting
            try {
                $wishlist = $this->wishlistCrudService->loadWishlist($id, $context->getContext());

                // Only the owner can delete the wishlist
                if ($wishlist->getCustomerId() !== $customerId) {
                    return new JsonResponse(['errors' => [['code' => 'WISHLIST__ACCESS_DENIED', 'title' => 'Access Denied', 'detail' => 'You do not have permission to delete this wishlist']]], JsonResponse::HTTP_FORBIDDEN);
                }

                // Validate transferTo parameter if provided
                $transferToWishlistId = $request->query->get('transferTo');
                if ($transferToWishlistId) {
                    try {
                        $transferToWishlist = $this->wishlistCrudService->loadWishlist($transferToWishlistId, $context->getContext());

                        // Ensure the target wishlist also belongs to the customer
                        if ($transferToWishlist->getCustomerId() !== $customerId) {
                            return new JsonResponse(['errors' => [['code' => 'WISHLIST__INVALID_TARGET', 'title' => 'Invalid Target', 'detail' => 'Target wishlist does not belong to you']]], JsonResponse::HTTP_FORBIDDEN);
                        }
                    } catch (\Exception $e) {
                        return new JsonResponse(['errors' => [['code' => 'WISHLIST__TARGET_NOT_FOUND', 'title' => 'Target Not Found', 'detail' => 'Target wishlist not found']]], JsonResponse::HTTP_NOT_FOUND);
                    }
                }

                $this->wishlistCrudService->deleteWishlist($id, $transferToWishlistId, $context->getContext());

                return new JsonResponse(['success' => true, 'message' => 'Wishlist deleted successfully']);
            } catch (\Exception $e) {
                return new JsonResponse(['errors' => [['code' => 'WISHLIST__NOT_FOUND', 'title' => 'Not Found', 'detail' => 'Wishlist not found']]], JsonResponse::HTTP_NOT_FOUND);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['errors' => [['code' => 'WISHLIST__DELETE_FAILED', 'title' => 'Delete Failed', 'detail' => $e->getMessage()]]], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate and sanitize integer input with bounds checking.
     */
    private function validateAndSanitizeInt(string $value, int $min = 1, int $max = PHP_INT_MAX): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $int || $int < $min || $int > $max) {
            return $min;
        }
        return $int;
    }

    /**
     * Validate fields parameter to prevent injection attacks.
     */
    private function validateFieldsParameter(string $fields): bool
    {
        // Only allow alphanumeric characters, commas, and underscores
        return 1 === preg_match('/^[a-zA-Z0-9,_]+$/', $fields);
    }

    /**
     * Validate sort parameter to prevent SQL injection.
     */
    private function validateSortParameter(string $sort): bool
    {
        // Only allow alphanumeric characters, colon, and underscore
        return 1 === preg_match('/^[a-zA-Z0-9_]+:(asc|desc|ASC|DESC)$/', $sort);
    }

    /**
     * Validate filter parameter to prevent injection attacks.
     */
    private function validateFilterParameter(string $filter): bool
    {
        // Basic validation for field:value format
        return 1 === preg_match('/^[a-zA-Z0-9_]+:[a-zA-Z0-9_]+$/', $filter);
    }

    /**
     * Validate filter values based on field type.
     */
    private function validateFilterValue(string $field, string $value): bool
    {
        switch ($field) {
            case 'type':
                return in_array($value, ['private', 'public', 'shared']);
            case 'isDefault':
                return in_array($value, ['0', '1', 'true', 'false']);
            default:
                return false;
        }
    }
}
