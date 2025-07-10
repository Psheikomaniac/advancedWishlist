<?php declare(strict_types=1);

/**
 * Load testing script for AdvancedWishlist plugin
 * 
 * This script simulates various load scenarios to benchmark performance
 * 
 * Usage: php bin/load-test.php [scenario] [iterations] [concurrent]
 * 
 * Scenarios:
 *   - create-wishlists: Create multiple wishlists
 *   - add-items: Add items to wishlists
 *   - get-wishlists: Retrieve wishlists
 *   - share-wishlists: Share wishlists
 *   - all: Run all scenarios
 * 
 * Example: php bin/load-test.php create-wishlists 100 10
 */

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

// Bootstrap Shopware
$_SERVER['APP_ENV'] = 'dev';
$_SERVER['APP_DEBUG'] = '1';
require_once dirname(__DIR__, 4) . '/vendor/autoload.php';
$kernel = new \Shopware\Core\Framework\Kernel(
    $_SERVER['APP_ENV'],
    (bool) $_SERVER['APP_DEBUG']
);
$kernel->boot();

// Set up console I/O
$input = new ArgvInput();
$output = new ConsoleOutput();
$io = new SymfonyStyle($input, $output);

// Parse command line arguments
$scenario = $argv[1] ?? 'all';
$iterations = (int) ($argv[2] ?? 10);
$concurrent = (int) ($argv[3] ?? 1);

// Get services
$container = $kernel->getContainer();
$connection = $container->get(\Doctrine\DBAL\Connection::class);
$entityManager = $container->get('doctrine.orm.entity_manager');
$wishlistService = $container->get(\AdvancedWishlist\Core\Service\WishlistCrudService::class);
$shareService = $container->get(\AdvancedWishlist\Service\ShareService::class);
$logger = $container->get(\Psr\Log\LoggerInterface::class);
$performanceMonitoring = $container->get(\AdvancedWishlist\Core\Performance\PerformanceMonitoringService::class);

// Create a sales channel context for testing
$salesChannelId = $connection->fetchOne('SELECT id FROM sales_channel LIMIT 1');
$contextFactory = $container->get(\Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory::class);
$context = $contextFactory->create('', $salesChannelId);

// Create a customer for testing
$customerId = createTestCustomer($connection);

// Run the selected scenario
$io->title('AdvancedWishlist Load Testing');
$io->section("Running scenario: $scenario with $iterations iterations ($concurrent concurrent)");

$startTime = microtime(true);

switch ($scenario) {
    case 'create-wishlists':
        runCreateWishlistsScenario($wishlistService, $customerId, $context->getContext(), $iterations, $concurrent, $io);
        break;
    case 'add-items':
        runAddItemsScenario($wishlistService, $customerId, $context->getContext(), $iterations, $concurrent, $io);
        break;
    case 'get-wishlists':
        runGetWishlistsScenario($wishlistService, $customerId, $context->getContext(), $iterations, $concurrent, $io);
        break;
    case 'share-wishlists':
        runShareWishlistsScenario($wishlistService, $shareService, $customerId, $context->getContext(), $iterations, $concurrent, $io);
        break;
    case 'all':
        runCreateWishlistsScenario($wishlistService, $customerId, $context->getContext(), $iterations, $concurrent, $io);
        runAddItemsScenario($wishlistService, $customerId, $context->getContext(), $iterations, $concurrent, $io);
        runGetWishlistsScenario($wishlistService, $customerId, $context->getContext(), $iterations, $concurrent, $io);
        runShareWishlistsScenario($wishlistService, $shareService, $customerId, $context->getContext(), $iterations, $concurrent, $io);
        break;
    default:
        $io->error("Unknown scenario: $scenario");
        exit(1);
}

$totalTime = microtime(true) - $startTime;
$io->success("Load test completed in " . round($totalTime, 2) . " seconds");

// Get performance metrics
$metrics = $performanceMonitoring->getMetrics();
$io->section('Performance Metrics');
$io->table(
    ['Metric', 'Value'],
    formatMetricsForDisplay($metrics)
);

// Clean up test data
cleanupTestData($connection, $customerId);

/**
 * Create a test customer
 */
function createTestCustomer(\Doctrine\DBAL\Connection $connection): string
{
    // Check if test customer already exists
    $customerId = $connection->fetchOne("SELECT id FROM customer WHERE email = 'load-test@example.com'");
    
    if ($customerId) {
        return $customerId;
    }
    
    // Create a new test customer
    $customerId = \Shopware\Core\Framework\Uuid\Uuid::randomHex();
    $customerNumber = 'LOAD-TEST-' . rand(10000, 99999);
    
    $connection->executeStatement("
        INSERT INTO customer (
            id, 
            customer_number, 
            salutation_id, 
            first_name, 
            last_name, 
            email, 
            password, 
            active, 
            created_at
        ) VALUES (
            UNHEX(?),
            ?,
            (SELECT id FROM salutation LIMIT 1),
            'Load',
            'Test',
            'load-test@example.com',
            'not-a-real-password',
            1,
            NOW()
        )
    ", [$customerId, $customerNumber]);
    
    return $customerId;
}

/**
 * Clean up test data
 */
function cleanupTestData(\Doctrine\DBAL\Connection $connection, string $customerId): void
{
    // Delete test wishlists
    $connection->executeStatement("
        DELETE FROM wishlist WHERE customer_id = UNHEX(?)
    ", [$customerId]);
    
    // Delete test customer
    $connection->executeStatement("
        DELETE FROM customer WHERE id = UNHEX(?)
    ", [$customerId]);
}

/**
 * Run create wishlists scenario
 */
function runCreateWishlistsScenario(
    \AdvancedWishlist\Core\Service\WishlistCrudService $wishlistService,
    string $customerId,
    \Shopware\Core\Framework\Context $context,
    int $iterations,
    int $concurrent,
    SymfonyStyle $io
): void {
    $io->section('Create Wishlists Scenario');
    
    $progressBar = $io->createProgressBar($iterations);
    $progressBar->start();
    
    $results = [];
    $wishlistIds = [];
    
    // Create a pool of workers
    $pool = new \GuzzleHttp\Pool(
        new \GuzzleHttp\Client(),
        generateCreateWishlistRequests($wishlistService, $customerId, $context, $iterations, $progressBar, $results, $wishlistIds),
        [
            'concurrency' => $concurrent,
            'fulfilled' => function ($response, $index) use ($progressBar) {
                $progressBar->advance();
            },
            'rejected' => function ($reason, $index) use ($io, $progressBar) {
                $progressBar->advance();
                $io->error("Request $index failed: " . $reason->getMessage());
            },
        ]
    );
    
    // Execute the pool of requests
    $pool->promise()->wait();
    
    $progressBar->finish();
    $io->newLine(2);
    
    // Calculate statistics
    $totalTime = array_sum(array_column($results, 'time'));
    $avgTime = $totalTime / count($results);
    $minTime = min(array_column($results, 'time'));
    $maxTime = max(array_column($results, 'time'));
    
    $io->table(
        ['Metric', 'Value'],
        [
            ['Total Wishlists Created', count($results)],
            ['Total Time (s)', round($totalTime, 2)],
            ['Average Time (ms)', round($avgTime * 1000, 2)],
            ['Min Time (ms)', round($minTime * 1000, 2)],
            ['Max Time (ms)', round($maxTime * 1000, 2)],
            ['Requests/sec', round(count($results) / $totalTime, 2)],
        ]
    );
    
    return;
}

/**
 * Generate create wishlist requests
 */
function generateCreateWishlistRequests(
    \AdvancedWishlist\Core\Service\WishlistCrudService $wishlistService,
    string $customerId,
    \Shopware\Core\Framework\Context $context,
    int $iterations,
    \Symfony\Component\Console\Helper\ProgressBar $progressBar,
    array &$results,
    array &$wishlistIds
): \Generator {
    for ($i = 0; $i < $iterations; $i++) {
        yield function() use ($wishlistService, $customerId, $context, $i, &$results, &$wishlistIds) {
            $startTime = microtime(true);
            
            try {
                // Create a wishlist request
                $request = new \AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest();
                $request->setName('Load Test Wishlist ' . $i);
                $request->setCustomerId($customerId);
                $request->setType('private');
                
                // Create the wishlist
                $wishlist = $wishlistService->createWishlist($request, $context);
                
                $endTime = microtime(true);
                $executionTime = $endTime - $startTime;
                
                $results[] = [
                    'id' => $wishlist->getId(),
                    'time' => $executionTime,
                ];
                
                $wishlistIds[] = $wishlist->getId();
                
                return new \GuzzleHttp\Psr7\Response(200);
            } catch (\Exception $e) {
                throw new \Exception('Failed to create wishlist: ' . $e->getMessage());
            }
        };
    }
}

/**
 * Run add items scenario
 */
function runAddItemsScenario(
    \AdvancedWishlist\Core\Service\WishlistCrudService $wishlistService,
    string $customerId,
    \Shopware\Core\Framework\Context $context,
    int $iterations,
    int $concurrent,
    SymfonyStyle $io
): void {
    $io->section('Add Items Scenario');
    
    // First, create a wishlist to add items to
    $request = new \AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest();
    $request->setName('Load Test Items Wishlist');
    $request->setCustomerId($customerId);
    $request->setType('private');
    
    $wishlist = $wishlistService->createWishlist($request, $context);
    $wishlistId = $wishlist->getId();
    
    // Get some product IDs to add
    $connection = $wishlistService->getConnection();
    $productIds = $connection->fetchFirstColumn('SELECT id FROM product LIMIT 100');
    
    if (empty($productIds)) {
        $io->warning('No products found in the database. Skipping add items scenario.');
        return;
    }
    
    $progressBar = $io->createProgressBar($iterations);
    $progressBar->start();
    
    $results = [];
    
    // Create a pool of workers
    $pool = new \GuzzleHttp\Pool(
        new \GuzzleHttp\Client(),
        generateAddItemRequests($wishlistService, $wishlistId, $productIds, $context, $iterations, $progressBar, $results),
        [
            'concurrency' => $concurrent,
            'fulfilled' => function ($response, $index) use ($progressBar) {
                $progressBar->advance();
            },
            'rejected' => function ($reason, $index) use ($io, $progressBar) {
                $progressBar->advance();
                $io->error("Request $index failed: " . $reason->getMessage());
            },
        ]
    );
    
    // Execute the pool of requests
    $pool->promise()->wait();
    
    $progressBar->finish();
    $io->newLine(2);
    
    // Calculate statistics
    $totalTime = array_sum(array_column($results, 'time'));
    $avgTime = $totalTime / count($results);
    $minTime = min(array_column($results, 'time'));
    $maxTime = max(array_column($results, 'time'));
    
    $io->table(
        ['Metric', 'Value'],
        [
            ['Total Items Added', count($results)],
            ['Total Time (s)', round($totalTime, 2)],
            ['Average Time (ms)', round($avgTime * 1000, 2)],
            ['Min Time (ms)', round($minTime * 1000, 2)],
            ['Max Time (ms)', round($maxTime * 1000, 2)],
            ['Requests/sec', round(count($results) / $totalTime, 2)],
        ]
    );
    
    return;
}

/**
 * Generate add item requests
 */
function generateAddItemRequests(
    \AdvancedWishlist\Core\Service\WishlistCrudService $wishlistService,
    string $wishlistId,
    array $productIds,
    \Shopware\Core\Framework\Context $context,
    int $iterations,
    \Symfony\Component\Console\Helper\ProgressBar $progressBar,
    array &$results
): \Generator {
    for ($i = 0; $i < $iterations; $i++) {
        yield function() use ($wishlistService, $wishlistId, $productIds, $context, $i, &$results) {
            $startTime = microtime(true);
            
            try {
                // Get a random product ID
                $productId = $productIds[array_rand($productIds)];
                $quantity = rand(1, 5);
                
                // Add the item to the wishlist
                $item = $wishlistService->addItemToWishlist($wishlistId, $productId, $quantity, $context);
                
                $endTime = microtime(true);
                $executionTime = $endTime - $startTime;
                
                $results[] = [
                    'id' => $item['id'],
                    'time' => $executionTime,
                ];
                
                return new \GuzzleHttp\Psr7\Response(200);
            } catch (\Exception $e) {
                throw new \Exception('Failed to add item: ' . $e->getMessage());
            }
        };
    }
}

/**
 * Run get wishlists scenario
 */
function runGetWishlistsScenario(
    \AdvancedWishlist\Core\Service\WishlistCrudService $wishlistService,
    string $customerId,
    \Shopware\Core\Framework\Context $context,
    int $iterations,
    int $concurrent,
    SymfonyStyle $io
): void {
    $io->section('Get Wishlists Scenario');
    
    $progressBar = $io->createProgressBar($iterations);
    $progressBar->start();
    
    $results = [];
    
    // Create a pool of workers
    $pool = new \GuzzleHttp\Pool(
        new \GuzzleHttp\Client(),
        generateGetWishlistsRequests($wishlistService, $customerId, $context, $iterations, $progressBar, $results),
        [
            'concurrency' => $concurrent,
            'fulfilled' => function ($response, $index) use ($progressBar) {
                $progressBar->advance();
            },
            'rejected' => function ($reason, $index) use ($io, $progressBar) {
                $progressBar->advance();
                $io->error("Request $index failed: " . $reason->getMessage());
            },
        ]
    );
    
    // Execute the pool of requests
    $pool->promise()->wait();
    
    $progressBar->finish();
    $io->newLine(2);
    
    // Calculate statistics
    $totalTime = array_sum(array_column($results, 'time'));
    $avgTime = $totalTime / count($results);
    $minTime = min(array_column($results, 'time'));
    $maxTime = max(array_column($results, 'time'));
    
    $io->table(
        ['Metric', 'Value'],
        [
            ['Total Requests', count($results)],
            ['Total Time (s)', round($totalTime, 2)],
            ['Average Time (ms)', round($avgTime * 1000, 2)],
            ['Min Time (ms)', round($minTime * 1000, 2)],
            ['Max Time (ms)', round($maxTime * 1000, 2)],
            ['Requests/sec', round(count($results) / $totalTime, 2)],
        ]
    );
    
    return;
}

/**
 * Generate get wishlists requests
 */
function generateGetWishlistsRequests(
    \AdvancedWishlist\Core\Service\WishlistCrudService $wishlistService,
    string $customerId,
    \Shopware\Core\Framework\Context $context,
    int $iterations,
    \Symfony\Component\Console\Helper\ProgressBar $progressBar,
    array &$results
): \Generator {
    for ($i = 0; $i < $iterations; $i++) {
        yield function() use ($wishlistService, $customerId, $context, &$results) {
            $startTime = microtime(true);
            
            try {
                // Create criteria for the search
                $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
                $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('customerId', $customerId));
                
                // Get the wishlists
                $wishlists = $wishlistService->getWishlists($criteria, $context);
                
                $endTime = microtime(true);
                $executionTime = $endTime - $startTime;
                
                $results[] = [
                    'count' => count($wishlists),
                    'time' => $executionTime,
                ];
                
                return new \GuzzleHttp\Psr7\Response(200);
            } catch (\Exception $e) {
                throw new \Exception('Failed to get wishlists: ' . $e->getMessage());
            }
        };
    }
}

/**
 * Run share wishlists scenario
 */
function runShareWishlistsScenario(
    \AdvancedWishlist\Core\Service\WishlistCrudService $wishlistService,
    \AdvancedWishlist\Service\ShareService $shareService,
    string $customerId,
    \Shopware\Core\Framework\Context $context,
    int $iterations,
    int $concurrent,
    SymfonyStyle $io
): void {
    $io->section('Share Wishlists Scenario');
    
    // First, create a wishlist to share
    $request = new \AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest();
    $request->setName('Load Test Share Wishlist');
    $request->setCustomerId($customerId);
    $request->setType('private');
    
    $wishlist = $wishlistService->createWishlist($request, $context);
    $wishlistId = $wishlist->getId();
    
    $progressBar = $io->createProgressBar($iterations);
    $progressBar->start();
    
    $results = [];
    
    // Create a pool of workers
    $pool = new \GuzzleHttp\Pool(
        new \GuzzleHttp\Client(),
        generateShareWishlistRequests($shareService, $wishlistId, $context, $iterations, $progressBar, $results),
        [
            'concurrency' => $concurrent,
            'fulfilled' => function ($response, $index) use ($progressBar) {
                $progressBar->advance();
            },
            'rejected' => function ($reason, $index) use ($io, $progressBar) {
                $progressBar->advance();
                $io->error("Request $index failed: " . $reason->getMessage());
            },
        ]
    );
    
    // Execute the pool of requests
    $pool->promise()->wait();
    
    $progressBar->finish();
    $io->newLine(2);
    
    // Calculate statistics
    $totalTime = array_sum(array_column($results, 'time'));
    $avgTime = $totalTime / count($results);
    $minTime = min(array_column($results, 'time'));
    $maxTime = max(array_column($results, 'time'));
    
    $io->table(
        ['Metric', 'Value'],
        [
            ['Total Shares Created', count($results)],
            ['Total Time (s)', round($totalTime, 2)],
            ['Average Time (ms)', round($avgTime * 1000, 2)],
            ['Min Time (ms)', round($minTime * 1000, 2)],
            ['Max Time (ms)', round($maxTime * 1000, 2)],
            ['Requests/sec', round(count($results) / $totalTime, 2)],
        ]
    );
    
    return;
}

/**
 * Generate share wishlist requests
 */
function generateShareWishlistRequests(
    \AdvancedWishlist\Service\ShareService $shareService,
    string $wishlistId,
    \Shopware\Core\Framework\Context $context,
    int $iterations,
    \Symfony\Component\Console\Helper\ProgressBar $progressBar,
    array &$results
): \Generator {
    for ($i = 0; $i < $iterations; $i++) {
        yield function() use ($shareService, $wishlistId, $context, &$results) {
            $startTime = microtime(true);
            
            try {
                // Create a share
                $share = $shareService->createShare($wishlistId, $context);
                
                $endTime = microtime(true);
                $executionTime = $endTime - $startTime;
                
                $results[] = [
                    'id' => $share->getId(),
                    'time' => $executionTime,
                ];
                
                return new \GuzzleHttp\Psr7\Response(200);
            } catch (\Exception $e) {
                throw new \Exception('Failed to share wishlist: ' . $e->getMessage());
            }
        };
    }
}

/**
 * Format metrics for display
 */
function formatMetricsForDisplay(array $metrics): array
{
    $formattedMetrics = [];
    
    // Format counters
    if (isset($metrics['counters'])) {
        foreach ($metrics['counters'] as $key => $value) {
            $formattedMetrics[] = ["Counter: $key", $value];
        }
    }
    
    // Format averages
    if (isset($metrics['averages'])) {
        foreach ($metrics['averages'] as $key => $data) {
            $formattedMetrics[] = ["Avg Time: $key", round($data['avg'] * 1000, 2) . ' ms'];
            $formattedMetrics[] = ["Min Time: $key", round($data['min'] * 1000, 2) . ' ms'];
            $formattedMetrics[] = ["Max Time: $key", round($data['max'] * 1000, 2) . ' ms'];
        }
    }
    
    return $formattedMetrics;
}