# Testing Foundation Implementation

## Overview
This document outlines the testing foundation for the AdvancedWishlist plugin as part of Phase 1, Step 5 of the implementation roadmap. A robust testing infrastructure is essential for ensuring the quality, security, and reliability of the plugin.

## Required Testing Components

### 1. PHPUnit Configuration
Set up PHPUnit configuration to enable effective testing:

- **Bootstrap File**: Configure to load the plugin in test mode
- **Test Suite Definition**: Include all tests in the `tests` directory
- **Code Coverage Configuration**: Configure to include the `src` directory
- **Environment Variables**: Set up testing-specific environment variables

### 2. Test Database Setup
Leverage Shopware's built-in testing infrastructure:

- **Transaction-Based Testing**: Wrap each test in a transaction that is rolled back after completion
- **Isolation**: Ensure tests don't affect each other by isolating database changes
- **Test Data Management**: Implement strategies for creating and cleaning up test data

### 3. Test Factory Implementation
Create factory classes to simplify test entity creation:

#### Base Factory
- **TestEntityFactory**: A base class providing common functionality for all test factories:
  - Entity creation methods
  - Entity retrieval methods
  - Entity search methods
  - Entity deletion methods
  - Random data generation utilities

#### Entity-Specific Factories
- **WishlistFactory**: For creating and managing wishlist entities in tests
- **WishlistItemFactory**: For creating and managing wishlist item entities in tests
- **WishlistShareFactory**: For creating and managing wishlist share entities in tests

### 4. Security-Related Unit Tests
Implement critical unit tests for security-related functionality:

- **WishlistValidator Tests**:
  - Validation of create and update requests
  - Ownership validation
  - Permission checking based on wishlist type and ownership

- **WishlistController Security Tests**:
  - Authorization checks for different user types
  - CSRF protection verification
  - Access control based on wishlist type and ownership

- **Input Validation Tests**:
  - Required field validation
  - Field length and value validation
  - Input sanitization for XSS prevention
  - Dangerous field filtering

## Implementation Details

### PHPUnit Configuration Example
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
         bootstrap="tests/TestBootstrap.php"
         cacheResultFile=".phpunit.result.cache"
         executionOrder="depends,defects"
         forceCoversAnnotation="false"
         beStrictAboutCoversAnnotation="false"
         beStrictAboutOutputDuringTests="true"
         beStrictAboutTodoAnnotatedTests="true"
         beStrictAboutChangesToGlobalState="true"
         verbose="true">
    <testsuites>
        <testsuite name="AdvancedWishlist Tests">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
    <php>
        <ini name="error_reporting" value="-1"/>
        <server name="APP_ENV" value="test" force="true"/>
        <server name="KERNEL_CLASS" value="Shopware\Core\Kernel"/>
        <env name="APP_DEBUG" value="1"/>
        <env name="APP_SECRET" value="s$cretf0rt3st"/>
        <env name="SHELL_VERBOSITY" value="-1"/>
    </php>
</phpunit>
```

### Test Factory Implementation Example
```php
abstract class TestEntityFactory
{
    protected EntityRepositoryInterface $repository;

    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    protected function create(array $data, Context $context): string
    {
        $id = Uuid::randomHex();
        $data['id'] = $id;

        $this->repository->create([$data], $context);

        return $id;
    }

    protected function find(string $id, Context $context): ?Entity
    {
        $criteria = new Criteria([$id]);
        return $this->repository->search($criteria, $context)->first();
    }

    protected function delete(string $id, Context $context): void
    {
        $this->repository->delete([['id' => $id]], $context);
    }

    protected function generateRandomString(int $length = 10): string
    {
        return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)))), 1, $length);
    }
}
```

### Unit Test Example
```php
class WishlistValidatorTest extends TestCase
{
    private WishlistValidator $wishlistValidator;
    private WishlistFactory $wishlistFactory;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->wishlistValidator = $this->getContainer()->get(WishlistValidator::class);
        $this->wishlistFactory = new WishlistFactory($this->getContainer()->get('wishlist.repository'));
    }

    public function testValidateOwnershipReturnsTrueForOwner(): void
    {
        // Arrange
        $customerId = Uuid::randomHex();
        $wishlistId = $this->wishlistFactory->createWishlist([
            'customerId' => $customerId,
            'name' => 'Test Wishlist',
        ], Context::createDefaultContext());
        
        // Act
        $result = $this->wishlistValidator->validateOwnership($wishlistId, $customerId, Context::createDefaultContext());
        
        // Assert
        $this->assertTrue($result);
    }

    public function testValidateOwnershipReturnsFalseForNonOwner(): void
    {
        // Arrange
        $ownerId = Uuid::randomHex();
        $nonOwnerId = Uuid::randomHex();
        $wishlistId = $this->wishlistFactory->createWishlist([
            'customerId' => $ownerId,
            'name' => 'Test Wishlist',
        ], Context::createDefaultContext());
        
        // Act
        $result = $this->wishlistValidator->validateOwnership($wishlistId, $nonOwnerId, Context::createDefaultContext());
        
        // Assert
        $this->assertFalse($result);
    }
}
```

## Testing Best Practices

1. **Use Test Factories**: Use the provided test factories to create test entities instead of directly using repositories
2. **Isolate Tests**: Each test should be independent and not rely on the state from other tests
3. **Mock External Dependencies**: Use PHPUnit's mocking capabilities to mock external dependencies
4. **Test Edge Cases**: Include tests for edge cases and error conditions, not just the happy path
5. **Security Focus**: Pay special attention to testing security-related functionality
6. **Arrange-Act-Assert Pattern**: Structure tests using the AAA pattern for clarity
7. **Descriptive Test Names**: Use descriptive test method names that explain what is being tested
8. **Test Coverage**: Aim for high test coverage, especially for critical components

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

## Next Steps

1. **Expand Test Coverage**: Continue adding tests to increase overall test coverage
2. **Integration Tests**: Add integration tests for complex workflows
3. **E2E Tests**: Consider adding end-to-end tests for critical user journeys
4. **CI/CD Integration**: Set up continuous integration to run tests automatically
5. **Performance Tests**: Add tests to verify performance requirements are met
6. **Security Tests**: Expand security testing with dedicated penetration tests

## Conclusion

The testing foundation provides a solid base for ensuring the quality and security of the AdvancedWishlist plugin. By following the established patterns and best practices, developers can continue to add tests that verify the functionality and security of the plugin.