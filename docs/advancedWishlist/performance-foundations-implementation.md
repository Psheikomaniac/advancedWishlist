# Performance Foundations Implementation

## Overview
This document outlines the performance improvements required for the AdvancedWishlist plugin as part of Phase 1, Step 4 of the implementation roadmap. These improvements focus on database optimization, query efficiency, and caching to ensure the plugin performs well under load.

## Required Performance Improvements

### 1. Database Indexes
Add missing database indexes to improve query performance:

#### Wishlist Item Table
- Add index on `added_at` column to improve performance of queries that sort or filter by when items were added
- Add composite index on `wishlist_id` and `added_at` to optimize queries that filter by wishlist and sort by addition date
- Add index on `product_id` to speed up product existence checks

#### Wishlist Table
- Add index on `updated_at` to improve performance of queries that sort or filter by last update time
- Add index on `customer_id` to optimize customer-specific wishlist queries
- Add index on `name` for text search operations

#### Wishlist Share Table
- Add index on `token` for fast token-based lookups
- Add index on `created_at` for expiration checks

### 2. N+1 Query Optimizations
Fix N+1 query issues in the codebase:

#### isProductInWishlist Method
- Optimize the query to directly filter for the product instead of loading all wishlists and items
- Implement a single query approach that checks if a product exists in any of a customer's wishlists
- Add early return logic to avoid unnecessary processing

#### loadWishlist Method
- Add optional parameter to specify which associations to load
- Implement selective loading of associations based on actual needs
- Use Doctrine's partial object loading where appropriate

#### getCustomerWishlists Method
- Optimize to fetch only necessary data in a single query
- Implement pagination to avoid loading all wishlists at once
- Add sorting options that leverage database indexes

### 3. Caching Implementation
Implement caching for expensive operations:

#### WishlistCacheService Integration
- Implement cache for product existence checks
- Cache wishlist data with appropriate invalidation strategies
- Cache customer wishlist collections

#### Cache Keys and TTL
- Define standardized cache key generation for consistency
- Implement appropriate TTL (Time To Live) for different cache types
- Create cache tags for efficient invalidation

#### Cache Invalidation
- Implement automatic cache invalidation on wishlist updates
- Add cache clearing on product changes that affect wishlists
- Implement selective cache invalidation to avoid full cache clears

### 4. Lazy Loading
Implement lazy loading for related entities:

- Configure Doctrine to lazy load product details when loading wishlist items
- Implement lazy loading for customer data when loading wishlists
- Add lazy loading for media and other non-critical associations

## Implementation Details

### Database Migration
Create a new migration to add the required indexes:

```php
class Migration1700000001AddPerformanceIndexes extends MigrationStep
{
    public function update(Connection $connection): void
    {
        // Add indexes to wishlist_item table
        $connection->executeStatement('
            ALTER TABLE `wishlist_item` 
            ADD INDEX `added_at_idx` (`added_at`),
            ADD INDEX `wishlist_id_added_at_idx` (`wishlist_id`, `added_at`),
            ADD INDEX `product_id_idx` (`product_id`)
        ');

        // Add indexes to wishlist table
        $connection->executeStatement('
            ALTER TABLE `wishlist` 
            ADD INDEX `updated_at_idx` (`updated_at`),
            ADD INDEX `customer_id_idx` (`customer_id`),
            ADD INDEX `name_idx` (`name`)
        ');

        // Add indexes to wishlist_share table
        $connection->executeStatement('
            ALTER TABLE `wishlist_share` 
            ADD INDEX `token_idx` (`token`),
            ADD INDEX `created_at_idx` (`created_at`)
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive changes
    }
}
```

### Query Optimization Examples

#### Optimized isProductInWishlist Method
```php
public function isProductInWishlist(string $productId, string $customerId): bool
{
    // Check cache first
    $cacheKey = sprintf('product_in_wishlist_%s_%s', $customerId, $productId);
    
    return $this->wishlistCacheService->get($cacheKey, function() use ($productId, $customerId) {
        // Direct query to check if product exists in any wishlist
        $result = $this->connection->fetchOne('
            SELECT 1 FROM wishlist w
            JOIN wishlist_item wi ON w.id = wi.wishlist_id
            WHERE w.customer_id = :customerId
            AND wi.product_id = :productId
            LIMIT 1
        ', [
            'customerId' => $customerId,
            'productId' => $productId
        ]);
        
        return !empty($result);
    });
}
```

### Caching Implementation Examples

#### WishlistCacheService Methods
```php
// Cache a wishlist with its items
public function cacheWishlist(WishlistEntity $wishlist): void
{
    $cacheKey = sprintf('wishlist_%s', $wishlist->getId());
    $this->cache->set($cacheKey, $wishlist, 3600); // 1 hour TTL
    
    // Also cache in customer's wishlist collection
    $this->invalidateCustomerCache($wishlist->getCustomerId());
}

// Get cached wishlist or load from database
public function getCachedWishlist(string $wishlistId, callable $loader): WishlistEntity
{
    $cacheKey = sprintf('wishlist_%s', $wishlistId);
    
    return $this->cache->get($cacheKey, $loader);
}
```

## Testing Strategy

### Performance Benchmarks
- Create baseline performance measurements before implementing changes
- Measure query execution times before and after index additions
- Test caching effectiveness with various dataset sizes

### Load Testing
- Simulate multiple concurrent users accessing wishlist features
- Test with large wishlists (100+ items) to ensure performance
- Measure database load under various conditions

## Expected Benefits

- **Faster Page Loads**: Pages that display wishlists will load significantly faster
- **Reduced Database Load**: More efficient queries will reduce database server load
- **Better Scalability**: The application will handle more users and larger wishlists
- **Improved User Experience**: Users will experience less lag when interacting with wishlist features

## Next Steps

After implementing these performance foundations, we will proceed with setting up the testing foundation as outlined in Phase 1, Step 5 of the roadmap.