# Testing Foundation Implementation

## Overview
This document outlines the testing foundation implemented as part of Phase 1, Step 5 of the AdvancedWishlist plugin roadmap. The focus was on setting up PHPUnit configuration, creating test factories, and writing critical unit tests for security-related functionality.

## Implemented Components

### 1. PHPUnit Configuration
The PHPUnit configuration has been set up in `phpunit.xml` with the following features:
- Bootstrap file configured to load the plugin in test mode
- Test suite defined to include all tests in the `tests` directory
- Code coverage configuration to include the `src` directory
- Environment variables set for testing

### 2. Test Database
The plugin uses Shopware's built-in testing infrastructure, which:
- Uses the same database for both development and testing
- Wraps each test in a transaction that is rolled back after the test completes
- Ensures tests don't affect each other by isolating database changes

### 3. Test Factories
A set of factory classes has been implemented to simplify the creation of test entities:

#### Base Factory
- `TestEntityFactory`: A base class that provides common functionality for all test factories, including methods for creating, retrieving, finding, and deleting entities.

#### Entity-Specific Factories
- `WishlistFactory`: For creating and managing wishlist entities in tests
- `WishlistItemFactory`: For creating and managing wishlist item entities in tests
- `WishlistShareFactory`: For creating and managing wishlist share entities in tests

Each factory provides methods for:
- Creating entities with default or custom data
- Finding entities by various criteria
- Generating random test data
- Deleting entities

### 4. Security-Related Unit Tests
Critical unit tests have been implemented for security-related functionality:

#### WishlistValidator Tests
- Tests for validating create and update requests
- Tests for validating ownership of wishlists
- Tests for checking view permissions based on wishlist type and ownership

#### WishlistController Security Tests
- Tests for authorization checks (guest users, unauthorized users)
- Tests for CSRF protection on state-changing operations
- Tests for access control based on wishlist type and ownership

#### Input Validation Tests
- Tests for validating required fields
- Tests for validating field length and values
- Tests for sanitizing user input to prevent XSS attacks
- Tests for removing dangerous fields that could be used for privilege escalation

## Usage Examples

### Using Test Factories

```php
// Example: Creating a test wishlist
public function testSomeFunctionality(): void
{
    $wishlistFactory = new WishlistFactory($this->getContainer()->get('wishlist.repository'));
    
    // Create a wishlist with default values
    $customerId = Uuid::randomHex();
    $wishlistId = $wishlistFactory->createWishlist([
        'customerId' => $customerId,
        'name' => 'Test Wishlist',
    ], Context::createDefaultContext());
    
    // Create a wishlist item
    $itemFactory = new WishlistItemFactory($this->getContainer()->get('wishlist_item.repository'));
    $itemId = $itemFactory->createWishlistItem([
        'wishlistId' => $wishlistId,
        'productId' => Uuid::randomHex(),
        'productVersionId' => Uuid::randomHex(),
    ], Context::createDefaultContext());
    
    // Get the wishlist with its items
    $wishlist = $wishlistFactory->getWishlist($wishlistId, Context::createDefaultContext());
    
    // Perform assertions
    $this->assertNotNull($wishlist);
    $this->assertEquals('Test Wishlist', $wishlist->getName());
}
```

### Writing Security Tests

```php
// Example: Testing authorization
public function testUnauthorizedAccessIsDenied(): void
{
    // Create a wishlist owned by one user
    $ownerId = Uuid::randomHex();
    $wishlistId = $this->wishlistFactory->createWishlist([
        'customerId' => $ownerId,
        'type' => 'private',
    ], Context::createDefaultContext());
    
    // Attempt to access it as another user
    $currentUserId = Uuid::randomHex();
    $salesChannelContext = $this->createSalesChannelContextWithCustomer($currentUserId);
    
    $response = $this->wishlistController->detail($wishlistId, new Request(), $salesChannelContext);
    
    // Verify access is denied
    $this->assertEquals(403, $response->getStatusCode());
}
```

## Running Tests

To run the tests, use the following command from the plugin directory:

```bash
../../../vendor/bin/phpunit
```

To run a specific test class:

```bash
../../../vendor/bin/phpunit tests/Security/WishlistValidatorTest.php
```

To generate a code coverage report:

```bash
XDEBUG_MODE=coverage ../../../vendor/bin/phpunit --coverage-html coverage
```

## Best Practices

1. **Use Test Factories**: Use the provided test factories to create test entities instead of directly using repositories.
2. **Isolate Tests**: Each test should be independent and not rely on the state from other tests.
3. **Mock External Dependencies**: Use PHPUnit's mocking capabilities to mock external dependencies.
4. **Test Edge Cases**: Include tests for edge cases and error conditions, not just the happy path.
5. **Security Focus**: Pay special attention to testing security-related functionality, including:
   - Authorization checks
   - Input validation and sanitization
   - CSRF protection
   - Access control

## Next Steps

1. **Expand Test Coverage**: Continue adding tests to increase overall test coverage.
2. **Integration Tests**: Add integration tests for complex workflows.
3. **E2E Tests**: Consider adding end-to-end tests for critical user journeys.
4. **CI/CD Integration**: Set up continuous integration to run tests automatically.

## Conclusion

The testing foundation implemented provides a solid base for ensuring the quality and security of the AdvancedWishlist plugin. By following the established patterns and best practices, developers can continue to add tests that verify the functionality and security of the plugin.