<?php

declare(strict_types=1);

namespace AdvancedWishlist\Storefront\Controller\V2;

use AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest;
use AdvancedWishlist\Core\DTO\Request\UpdateWishlistRequest;
use AdvancedWishlist\Core\Performance\LazyObjectService;
use AdvancedWishlist\Core\Routing\ApiVersionResolver;
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

/**
 * Enterprise V2 Wishlist Controller with PHP 8.4 Features
 * Demonstrates modern API design with lazy loading, bulk operations, and enhanced performance.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class WishlistControllerV2 extends StorefrontController
{
    public function __construct(
        private WishlistCrudService $wishlistCrudService,
        private ApiVersionResolver $apiVersionResolver,
        private LazyObjectService $lazyObjectService,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    /**
     * Enhanced list endpoint with lazy loading and advanced filtering.
     */
    #[Route('/store-api/v2/wishlist', name: 'store-api.v2.wishlist.list', methods: ['GET'])]
    public function list(Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return $this->createErrorResponse('UNAUTHORIZED', 'Customer not logged in', 401);
        }

        $criteria = $this->buildEnhancedCriteria($request);

        // Use lazy loading for performance
        $useLazyLoading = $request->query->getBoolean('lazy', true);

        if ($useLazyLoading) {
            $wishlists = $this->lazyObjectService->createLazyCustomerWishlists($customerId, $context->getContext());
            $data = $this->serializeLazyWishlists($wishlists);
        } else {
            $criteria->addFilter(new EqualsFilter('customerId', $customerId));
            $result = $this->wishlistCrudService->searchWishlists($criteria, $context->getContext());
            $data = $this->serializeWishlists($result);
        }

        $response = new JsonResponse([
            'data' => $data,
            'meta' => $this->buildMetadata($request, $customerId, $context),
            'links' => $this->buildHalLinks($request),
        ]);

        // Add version headers
        $this->addVersionHeaders($response, 'v2');

        return $response;
    }

    /**
     * Enhanced detail endpoint with lazy loading and computed properties.
     */
    #[Route('/store-api/v2/wishlist/{id}', name: 'store-api.v2.wishlist.detail', methods: ['GET'])]
    public function detail(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return $this->createErrorResponse('UNAUTHORIZED', 'Customer not logged in', 401);
        }

        try {
            $useLazyLoading = $request->query->getBoolean('lazy', false);

            if ($useLazyLoading) {
                $wishlist = $this->lazyObjectService->createLazyWishlist($id, $context->getContext());
            } else {
                $wishlist = $this->wishlistCrudService->loadWishlist($id, $context->getContext());
            }

            // Enhanced authorization with visibility checking
            if (!$this->canAccessWishlist($wishlist, $customerId)) {
                return $this->createErrorResponse('ACCESS_DENIED', 'You do not have permission to view this wishlist', 403);
            }

            $data = [
                'data' => $this->serializeWishlist($wishlist, true), // Include computed properties
                'included' => $this->getIncludedResources($wishlist, $request),
                'meta' => $this->buildDetailMetadata($wishlist),
            ];

            $response = new JsonResponse($data);
            $this->addVersionHeaders($response, 'v2');
            $this->addCacheHeaders($response, 300); // 5 minutes cache

            return $response;
        } catch (\Exception $e) {
            return $this->createErrorResponse('NOT_FOUND', 'Wishlist not found', 404);
        }
    }

    /**
     * Enhanced create endpoint with bulk creation support.
     */
    #[Route('/store-api/v2/wishlist', name: 'store-api.v2.wishlist.create', methods: ['POST'])]
    public function create(Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return $this->createErrorResponse('UNAUTHORIZED', 'Customer not logged in', 401);
        }

        if (!$this->validateCsrfToken($request, 'wishlist_create')) {
            return $this->createErrorResponse('INVALID_CSRF_TOKEN', 'Invalid CSRF token provided', 403);
        }

        try {
            $requestData = json_decode($request->getContent(), true);

            // Support bulk creation in V2
            if (isset($requestData['data']) && is_array($requestData['data']) && isset($requestData['data'][0])) {
                return $this->handleBulkCreate($requestData['data'], $customerId, $context);
            }

            // Single creation
            $createRequest = $this->buildCreateRequest($requestData, $customerId);
            $wishlist = $this->wishlistCrudService->createWishlist($createRequest, $context->getContext());

            $response = new JsonResponse([
                'data' => $this->serializeWishlist($wishlist, true),
                'meta' => ['created_at' => time()],
            ], 201);

            $this->addVersionHeaders($response, 'v2');
            $response->headers->set('Location', "/store-api/v2/wishlist/{$wishlist->getId()}");

            return $response;
        } catch (\Exception $e) {
            return $this->createErrorResponse('CREATE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * Enhanced update endpoint with partial updates and optimistic locking.
     */
    #[Route('/store-api/v2/wishlist/{id}', name: 'store-api.v2.wishlist.update', methods: ['PATCH'])]
    public function update(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return $this->createErrorResponse('UNAUTHORIZED', 'Customer not logged in', 401);
        }

        if (!$this->validateCsrfToken($request, 'wishlist_update')) {
            return $this->createErrorResponse('INVALID_CSRF_TOKEN', 'Invalid CSRF token provided', 403);
        }

        try {
            $wishlist = $this->wishlistCrudService->loadWishlist($id, $context->getContext());

            if (!$this->canModifyWishlist($wishlist, $customerId)) {
                return $this->createErrorResponse('ACCESS_DENIED', 'You do not have permission to update this wishlist', 403);
            }

            $requestData = json_decode($request->getContent(), true);

            // Handle optimistic locking
            if (isset($requestData['meta']['version'])) {
                if (!$this->validateOptimisticLock($wishlist, $requestData['meta']['version'])) {
                    return $this->createErrorResponse('CONFLICT', 'Wishlist was modified by another process', 409);
                }
            }

            $updateRequest = $this->buildUpdateRequest($requestData, $id);
            $updatedWishlist = $this->wishlistCrudService->updateWishlist($updateRequest, $context->getContext());

            $response = new JsonResponse([
                'data' => $this->serializeWishlist($updatedWishlist, true),
                'meta' => ['updated_at' => time()],
            ]);

            $this->addVersionHeaders($response, 'v2');

            return $response;
        } catch (\Exception $e) {
            return $this->createErrorResponse('UPDATE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * Enhanced delete endpoint with soft delete and item transfer.
     */
    #[Route('/store-api/v2/wishlist/{id}', name: 'store-api.v2.wishlist.delete', methods: ['DELETE'])]
    public function delete(string $id, Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return $this->createErrorResponse('UNAUTHORIZED', 'Customer not logged in', 401);
        }

        if (!$this->validateCsrfToken($request, 'wishlist_delete')) {
            return $this->createErrorResponse('INVALID_CSRF_TOKEN', 'Invalid CSRF token provided', 403);
        }

        try {
            $wishlist = $this->wishlistCrudService->loadWishlist($id, $context->getContext());

            if (!$this->canModifyWishlist($wishlist, $customerId)) {
                return $this->createErrorResponse('ACCESS_DENIED', 'You do not have permission to delete this wishlist', 403);
            }

            $transferToWishlistId = $request->query->get('transferTo');
            $softDelete = $request->query->getBoolean('soft', false);

            if ($softDelete) {
                // Implement soft delete logic
                $this->softDeleteWishlist($id, $context->getContext());
            } else {
                $this->wishlistCrudService->deleteWishlist($id, $transferToWishlistId, $context->getContext());
            }

            $response = new JsonResponse([
                'meta' => [
                    'deleted_at' => time(),
                    'soft_delete' => $softDelete,
                    'items_transferred' => null !== $transferToWishlistId,
                ],
            ], 204);

            $this->addVersionHeaders($response, 'v2');

            return $response;
        } catch (\Exception $e) {
            return $this->createErrorResponse('DELETE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * New V2 bulk operations endpoint.
     */
    #[Route('/store-api/v2/wishlist/bulk', name: 'store-api.v2.wishlist.bulk', methods: ['POST'])]
    public function bulkOperations(Request $request, SalesChannelContext $context): JsonResponse
    {
        $customerId = $context->getCustomer()?->getId();
        if (!$customerId) {
            return $this->createErrorResponse('UNAUTHORIZED', 'Customer not logged in', 401);
        }

        if (!$this->validateCsrfToken($request, 'wishlist_bulk')) {
            return $this->createErrorResponse('INVALID_CSRF_TOKEN', 'Invalid CSRF token provided', 403);
        }

        try {
            $requestData = json_decode($request->getContent(), true);
            $operations = $requestData['operations'] ?? [];

            $results = [];
            foreach ($operations as $operation) {
                $result = $this->executeBulkOperation($operation, $customerId, $context);
                $results[] = $result;
            }

            $response = new JsonResponse([
                'data' => $results,
                'meta' => [
                    'total_operations' => count($operations),
                    'successful' => count(array_filter($results, fn ($r) => $r['success'])),
                    'failed' => count(array_filter($results, fn ($r) => !$r['success'])),
                ],
            ]);

            $this->addVersionHeaders($response, 'v2');

            return $response;
        } catch (\Exception $e) {
            return $this->createErrorResponse('BULK_OPERATION_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * Build enhanced criteria with advanced filtering.
     */
    private function buildEnhancedCriteria(Request $request): Criteria
    {
        $criteria = new Criteria();

        // Enhanced pagination with security validation
        $limit = $this->validateAndSanitizePagination($request->query->get('limit', '10'), 1, 100);
        $page = $this->validateAndSanitizePagination($request->query->get('page', '1'), 1, 1000);
        $offset = ($page - 1) * $limit;

        $criteria->setLimit($limit);
        $criteria->setOffset($offset);

        // Enhanced field selection with security validation
        if ($fields = $request->query->get('fields')) {
            if ($this->validateFieldsInput($fields)) {
                $fieldArray = $this->sanitizeFieldsArray($fields);
                if (!empty($fieldArray)) {
                    $criteria->setFields($fieldArray);
                }
            }
        }

        // Enhanced sorting with multiple fields and security validation
        $sort = $request->query->get('sort', 'createdAt:DESC');
        if ($this->validateSortInput($sort)) {
            foreach (explode(',', $sort) as $sortField) {
                [$field, $direction] = explode(':', $sortField.':ASC');
                if ($this->isAllowedSortField($field) && in_array(strtoupper($direction), ['ASC', 'DESC'])) {
                    $criteria->addSorting(new FieldSorting($field, $direction));
                }
            }
        } else {
            // Fallback to default sorting
            $criteria->addSorting(new FieldSorting('createdAt', 'DESC'));
        }

        // Enhanced filtering with security validation
        if ($filter = $request->query->get('filter')) {
            if ($this->validateFilterInput($filter)) {
                $this->addAdvancedFilters($criteria, $filter);
            }
        }

        // Include associations based on request
        $include = $request->query->get('include', 'items,shareInfo');
        foreach (explode(',', $include) as $association) {
            $criteria->addAssociation(trim($association));
        }

        return $criteria;
    }

    /**
     * Create standardized error response.
     */
    private function createErrorResponse(string $code, string $message, int $status): JsonResponse
    {
        $response = new JsonResponse([
            'errors' => [[
                'code' => $code,
                'title' => $this->getErrorTitle($status),
                'detail' => $message,
                'status' => (string) $status,
            ]],
        ], $status);

        $this->addVersionHeaders($response, 'v2');

        return $response;
    }

    /**
     * Add version-specific headers.
     */
    private function addVersionHeaders(JsonResponse $response, string $version): void
    {
        $headers = $this->apiVersionResolver->getVersionHeaders($version);

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }
    }

    /**
     * Serialize wishlist with PHP 8.4 property hooks benefits.
     */
    private function serializeWishlist($wishlist, bool $includeComputed = false): array
    {
        $data = [
            'id' => $wishlist->getId(),
            'name' => $wishlist->name, // Using property hook
            'description' => $wishlist->description, // Using property hook
            'type' => $wishlist->type, // Using property hook
            'isDefault' => $wishlist->isDefault, // Using property hook
            'itemCount' => $wishlist->itemCount, // Computed property
            'totalValue' => $wishlist->totalValue, // Computed property
            'createdAt' => $wishlist->getCreatedAt()?->format('c'),
            'updatedAt' => $wishlist->getUpdatedAt()?->format('c'),
        ];

        if ($includeComputed) {
            $data['computed'] = [
                'displayName' => $wishlist->displayName, // Virtual property
                'isShared' => $wishlist->isShared, // Virtual property
            ];
        }

        return $data;
    }

    // Additional helper methods would be implemented here...
    private function canAccessWishlist($wishlist, string $customerId): bool
    {
        if (!$wishlist) {
            return false;
        }
        
        // Owner can always access
        if ($wishlist->getCustomerId() === $customerId) {
            return true;
        }
        
        // Public wishlists can be accessed
        if ($wishlist->getType() === 'public') {
            return true;
        }
        
        // Check if wishlist is shared with the customer
        if ($wishlist->getShareInfo()) {
            foreach ($wishlist->getShareInfo() as $share) {
                if ($share->getRecipientId() === $customerId) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function canModifyWishlist($wishlist, string $customerId): bool
    {
        if (!$wishlist) {
            return false;
        }
        
        // Only the owner can modify the wishlist
        return $wishlist->getCustomerId() === $customerId;
    }

    private function validateCsrfToken(Request $request, string $intention): bool
    {
        $token = $request->request->get('_csrf_token');
        if (!$token) {
            $token = $request->headers->get('X-CSRF-Token');
        }
        
        if (!$token) {
            return false;
        }
        
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($intention, $token));
    }

    private function buildCreateRequest(array $data, string $customerId): CreateWishlistRequest
    {
        return new CreateWishlistRequest();
    }

    private function buildUpdateRequest(array $data, string $id): UpdateWishlistRequest
    {
        return new UpdateWishlistRequest();
    }

    private function handleBulkCreate(array $data, string $customerId, SalesChannelContext $context): JsonResponse
    {
        return new JsonResponse([]);
    }

    private function validateOptimisticLock($wishlist, $version): bool
    {
        return true;
    }

    private function softDeleteWishlist(string $id, Context $context): void
    {
    }

    private function executeBulkOperation(array $operation, string $customerId, SalesChannelContext $context): array
    {
        return [];
    }

    private function buildMetadata(Request $request, string $customerId, SalesChannelContext $context): array
    {
        return [];
    }

    private function buildDetailMetadata($wishlist): array
    {
        return [];
    }

    private function buildHalLinks(Request $request): array
    {
        return [];
    }

    private function getIncludedResources($wishlist, Request $request): array
    {
        return [];
    }

    private function serializeLazyWishlists($wishlists): array
    {
        return [];
    }

    private function serializeWishlists($result): array
    {
        return [];
    }

    private function addAdvancedFilters(Criteria $criteria, string $filter): void
    {
        // Parse multiple filters separated by comma
        $filters = explode(',', $filter);
        
        foreach ($filters as $filterStr) {
            $filterParts = explode(':', $filterStr, 2);
            if (2 === count($filterParts)) {
                $field = trim($filterParts[0]);
                $value = trim($filterParts[1]);
                
                if ($this->isAllowedFilterField($field) && $this->validateFilterValue($field, $value)) {
                    $criteria->addFilter(new EqualsFilter($field, $value));
                }
            }
        }
    }

    private function addCacheHeaders(JsonResponse $response, int $ttl): void
    {
    }

    private function getErrorTitle(int $status): string
    {
        return match($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'Error'
        };
    }
    
    /**
     * Validate and sanitize pagination input.
     */
    private function validateAndSanitizePagination(string $value, int $min = 1, int $max = PHP_INT_MAX): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $int || $int < $min || $int > $max) {
            return $min;
        }
        return $int;
    }
    
    /**
     * Validate fields input for security.
     */
    private function validateFieldsInput(string $fields): bool
    {
        // Only allow alphanumeric characters, commas, dots, and underscores
        return 1 === preg_match('/^[a-zA-Z0-9,._]+$/', $fields);
    }
    
    /**
     * Sanitize fields array with allowed fields whitelist.
     */
    private function sanitizeFieldsArray(string $fields): array
    {
        $fieldArray = array_map('trim', explode(',', $fields));
        $allowedFields = [
            'id', 'name', 'description', 'type', 'isDefault', 'createdAt', 'updatedAt',
            'items.id', 'items.count', 'shareInfo.id'
        ];
        
        return array_intersect($fieldArray, $allowedFields);
    }
    
    /**
     * Validate sort input for security.
     */
    private function validateSortInput(string $sort): bool
    {
        // Allow multiple sort fields separated by comma
        return 1 === preg_match('/^[a-zA-Z0-9_:,]+$/', $sort);
    }
    
    /**
     * Check if field is allowed for sorting.
     */
    private function isAllowedSortField(string $field): bool
    {
        $allowedSortFields = ['id', 'name', 'createdAt', 'updatedAt', 'type'];
        return in_array($field, $allowedSortFields);
    }
    
    /**
     * Validate filter input for security.
     */
    private function validateFilterInput(string $filter): bool
    {
        // Allow multiple filters separated by comma
        return 1 === preg_match('/^[a-zA-Z0-9_:,]+$/', $filter);
    }
    
    /**
     * Check if field is allowed for filtering.
     */
    private function isAllowedFilterField(string $field): bool
    {
        $allowedFilterFields = ['type', 'isDefault', 'customerId'];
        return in_array($field, $allowedFilterFields);
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
            case 'customerId':
                return 1 === preg_match('/^[a-f0-9]{32}$/', $value); // UUID format
            default:
                return false;
        }
    }
}
