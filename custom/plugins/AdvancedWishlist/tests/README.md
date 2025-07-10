# AdvancedWishlist Plugin - Testing Documentation

This directory contains tests for the AdvancedWishlist plugin. The tests are organized into different categories and use various utilities and fixtures to simplify test creation.

## Test Structure

The tests are organized into the following directories:

- **Factory/**: Contains factory classes for creating test entities
- **Fixtures/**: Contains fixtures for loading test data
- **Functional/**: Contains functional tests that test functionality from a user perspective
- **Integration/**: Contains integration tests that test the interaction between components
- **Security/**: Contains security-related tests
- **Service/**: Contains service-related tests
- **Unit/**: Contains unit tests for individual classes and methods
- **E2E/**: Contains end-to-end tests for critical user journeys
- **Utilities/**: Contains utility classes and traits for testing

## Running Tests

To run all tests, use the following command from the plugin directory:

```bash
../../../vendor/bin/phpunit
```

To run a specific test class:

```bash
../../../vendor/bin/phpunit tests/Integration/WishlistRepositoryTest.php
```

To run a specific test method:

```bash
../../../vendor/bin/phpunit --filter testFindByCustomer tests/Integration/WishlistRepositoryTest.php
```

To run a specific test suite:

```bash
../../../vendor/bin/phpunit --testsuite Unit
../../../vendor/bin/phpunit --testsuite Integration
../../../vendor/bin/phpunit --testsuite Functional
```

To generate a code coverage report:

```bash
XDEBUG_MODE=coverage ../../../vendor/bin/phpunit --coverage-html coverage
```

### E2E Tests

To run E2E tests with Cypress:

```bash
# Install Cypress and dependencies
npm install

# Open Cypress Test Runner
npx cypress open --config-file custom/plugins/AdvancedWishlist/tests/E2E/cypress.config.js

# Run Cypress tests headlessly
npx cypress run --config-file custom/plugins/AdvancedWishlist/tests/E2E/cypress.config.js
```

## Test Utilities

### WishlistTestTrait

The `WishlistTestTrait` provides helper methods for creating and asserting wishlists in tests:

```php
use AdvancedWishlist\Tests\Utilities\WishlistTestTrait;

class MyTest extends TestCase
{
    use WishlistTestTrait;

    public function testSomething(): void
    {
        // Create a wishlist
        $wishlistId = $this->createWishlist([
            'name' => 'Test Wishlist',
            'customerId' => Uuid::randomHex(),
        ]);

        // Create a wishlist with items
        $wishlistWithItemsId = $this->createWishlistWithItems(3);

        // Get a wishlist
        $wishlist = $this->getWishlist($wishlistId);

        // Assert wishlist properties
        $this->assertWishlistHasItems($wishlist, 2);
        $this->assertWishlistContainsProduct($wishlist, $productId);
    }
}
```

## Test Fixtures

### WishlistFixtures

The `WishlistFixtures` class provides predefined test data for wishlists:

```php
use AdvancedWishlist\Tests\Fixtures\WishlistFixtures;

class MyTest extends TestCase
{
    private WishlistFixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = new WishlistFixtures(
            $this->getContainer()->get(WishlistFactory::class),
            $this->getContainer()->get(WishlistItemFactory::class)
        );

        // Load fixtures
        $objectManager = $this->createMock(\Doctrine\Persistence\ObjectManager::class);
        $this->fixtures->load($objectManager);
    }

    public function testSomething(): void
    {
        // Get a wishlist ID from the fixtures
        $wishlistId = $this->fixtures->getReference('private-wishlist');

        // Use the wishlist ID in your test
    }
}
```

## Test Factories

### WishlistFactory

The `WishlistFactory` provides methods for creating and retrieving wishlist entities:

```php
use AdvancedWishlist\Tests\Factory\WishlistFactory;

class MyTest extends TestCase
{
    private WishlistFactory $wishlistFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wishlistFactory = $this->getContainer()->get(WishlistFactory::class);
    }

    public function testSomething(): void
    {
        // Create a wishlist
        $wishlistId = $this->wishlistFactory->createWishlist([
            'name' => 'Test Wishlist',
            'customerId' => Uuid::randomHex(),
        ], Context::createDefaultContext());

        // Get a wishlist
        $wishlist = $this->wishlistFactory->getWishlist($wishlistId, Context::createDefaultContext());
    }
}
```

## Best Practices

1. **Use Test Factories**: Use the provided test factories to create test entities instead of directly using repositories.
2. **Isolate Tests**: Each test should be independent and not rely on the state from other tests.
3. **Mock External Dependencies**: Use PHPUnit's mocking capabilities to mock external dependencies.
4. **Test Edge Cases**: Include tests for edge cases and error conditions, not just the happy path.
5. **Security Focus**: Pay special attention to testing security-related functionality.

## CI/CD Pipeline

The tests are integrated with GitHub Actions for continuous integration. The CI pipeline runs all tests on each pull request and push to the main and develop branches.

The CI pipeline includes the following jobs:

1. **php-tests**: Runs PHP tests, code style checks, and static analysis
   - Sets up PHP 8.4 with necessary extensions
   - Validates composer.json and composer.lock
   - Installs dependencies
   - Checks code style with php-cs-fixer
   - Runs static analysis with PHPStan
   - Runs unit, integration, and functional tests
   - Generates a code coverage report
   - Uploads the coverage report to Codecov

2. **e2e-tests**: Runs E2E tests with Cypress
   - Sets up Node.js
   - Installs Cypress and dependencies
   - Starts Shopware in a Docker container
   - Runs Cypress tests
   - Uploads screenshots and videos as artifacts

See the `.github/workflows/ci.yml` file for the complete CI configuration.

## Code Coverage

The goal is to achieve at least 80% code coverage. The code coverage report can be generated using the command mentioned above and viewed in a web browser.

## Adding New Tests

When adding new features or fixing bugs, always add corresponding tests to ensure the functionality works as expected and to prevent regressions.

1. **Unit Tests**: Add unit tests for new classes and methods
2. **Integration Tests**: Add integration tests for interactions between components
3. **Functional Tests**: Add functional tests for new functionality from a user perspective
4. **E2E Tests**: Add E2E tests for new critical user journeys
