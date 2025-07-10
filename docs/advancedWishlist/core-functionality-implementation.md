# Core Functionality Implementation

## Overview
This document outlines the plan for implementing the core functionality of the AdvancedWishlist plugin as part of Phase 1, Step 3 of the roadmap. The focus is on completing missing service implementations, implementing proper error handling, and fixing type declarations.

## Required Service Implementations

### 1. WishlistValidator
A service responsible for validating wishlist operations:

- **validateCreateRequest**: Validate create wishlist requests
  - Check required fields (name)
  - Validate name length
  - Validate wishlist type
  - Verify customer ID

- **validateUpdateRequest**: Validate update wishlist requests
  - Check wishlist ID
  - Validate name length
  - Validate wishlist type

- **validateOwnership**: Validate that a wishlist belongs to a customer
  - Check customer ID against wishlist owner
  - Handle guest wishlists appropriately

- **canViewWishlist**: Check if a user can view a wishlist
  - Check ownership
  - Check wishlist type (public/private)
  - Check sharing permissions

### 2. WishlistLimitService
A service responsible for managing and enforcing limits on wishlists and wishlist items:

- **checkCustomerWishlistLimit**: Check if a customer has reached their wishlist limit
  - Get customer's current wishlist count
  - Compare against system or customer-specific limit

- **checkWishlistItemLimit**: Check if a wishlist has reached its item limit
  - Get wishlist's current item count
  - Compare against system or wishlist-specific limit

- **getWishlistRemainingCapacity**: Calculate remaining capacity for a wishlist
  - Get maximum allowed items
  - Get current item count
  - Return difference

- **getCustomerRemainingCapacity**: Calculate remaining capacity for a customer
  - Get maximum allowed wishlists
  - Get current wishlist count
  - Return difference

- **getLimitsInfo**: Get detailed information about limits
  - Wishlist limits
  - Item limits
  - Usage statistics

- **setLimits**: Set custom limits for wishlists and items
  - Customer-specific limits
  - Wishlist-specific limits

### 3. WishlistCacheService
A service responsible for caching wishlist data:

- **get**: Get an item from the cache
  - Support for callback if item not found

- **set**: Set an item in the cache
  - Support for TTL (Time To Live)

- **delete**: Delete an item from the cache

- **invalidateWishlistCache**: Invalidate cache for a specific wishlist
  - Clear all related cache keys

- **invalidateCustomerCache**: Invalidate cache for a specific customer
  - Clear all related cache keys

- **cacheWishlist**: Cache wishlist data
  - Store complete wishlist entity
  - Store related items

- **cacheCustomerWishlists**: Cache a customer's wishlists
  - Store list of wishlist IDs
  - Store basic wishlist data

- **cacheDefaultWishlist**: Cache the default wishlist for a customer

- **getCachedWishlist**: Get a cached wishlist
  - Support for callback if not found

- **getCachedCustomerWishlists**: Get cached customer wishlists
  - Support for callback if not found

- **getCachedDefaultWishlist**: Get the cached default wishlist
  - Support for callback if not found

- **clearAllCache**: Clear all wishlist cache

- **warmUpCustomerCache**: Warm up the cache for a customer
  - Pre-cache frequently accessed data

## Error Handling Implementation

1. **Specific Exception Classes**:
   - Create custom exception classes for different error scenarios
   - WishlistNotFoundException
   - WishlistLimitExceededException
   - WishlistValidationException
   - WishlistPermissionDeniedException

2. **Contextual Error Messages**:
   - Provide detailed error messages with context
   - Include relevant IDs and parameters in error messages
   - Support for translation keys

3. **Logging**:
   - Implement proper logging with context
   - Log different severity levels appropriately
   - Include request information in logs

4. **Transaction Management**:
   - Use database transactions for operations that modify multiple records
   - Implement proper rollback on errors
   - Ensure data consistency

## Type Declarations

Update all services to use proper PHP 8.3+ type declarations:

1. **Parameter Types**:
   - Add explicit type declarations for all method parameters
   - Use union types where appropriate
   - Use nullable types where appropriate

2. **Return Types**:
   - Add explicit return type declarations for all methods
   - Use union types where appropriate
   - Use never type for methods that always throw exceptions

3. **Property Types**:
   - Add explicit type declarations for all properties
   - Use readonly properties where appropriate
   - Use typed properties with default values

4. **PHP 8.3+ Features**:
   - Use typed class constants
   - Use #[\Override] attribute for overridden methods
   - Use readonly properties with constructor promotion

## Integration with Existing Services

1. **WishlistService**:
   - Integrate WishlistValidator for request validation
   - Use WishlistLimitService for limit checking
   - Implement WishlistCacheService for performance optimization

2. **GuestWishlistService**:
   - Integrate with WishlistValidator
   - Use WishlistLimitService for guest limits
   - Implement proper caching with WishlistCacheService

## Testing Strategy

1. **Unit Tests**:
   - Create unit tests for each service
   - Test each method with various inputs
   - Test error cases and edge conditions

2. **Integration Tests**:
   - Test integration between services
   - Test database operations
   - Test caching behavior

## Benefits

- Complete core functionality for the plugin
- Improved data validation and error handling
- Better performance through caching
- Type-safe code with modern PHP features
- Clear separation of concerns between services

## Next Steps

After implementing the core functionality, we will proceed with performance optimizations as outlined in Phase 1, Step 4 of the roadmap.