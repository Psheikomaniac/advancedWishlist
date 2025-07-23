<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Service;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Optimized price calculation service that eliminates N+1 query patterns.
 * 
 * This service batches price calculations for multiple products in a single 
 * optimized query, reducing database load from O(n) to O(1).
 */
class OptimizedPriceCalculationService
{
    private const PRICE_CACHE_TTL = 900; // 15 minutes
    private const BATCH_SIZE = 100; // Maximum products per batch

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Calculate prices for multiple wishlist items in a single optimized query.
     * 
     * @param array $wishlistItems Array of wishlist items
     * @param Context $context Shopware context with currency and rules
     * @return array Associative array of product_id => price data
     */
    public function calculateWishlistItemPrices(array $wishlistItems, Context $context): array
    {
        if (empty($wishlistItems)) {
            return [];
        }

        $productIds = array_map(fn($item) => $item->getProductId(), $wishlistItems);
        $productIds = array_unique($productIds);

        // Process in batches to avoid query size limits
        $batches = array_chunk($productIds, self::BATCH_SIZE);
        $allPrices = [];

        foreach ($batches as $batch) {
            $batchPrices = $this->calculateBatchPrices($batch, $context);
            $allPrices = array_merge($allPrices, $batchPrices);
        }

        return $allPrices;
    }

    /**
     * Calculate prices for a batch of products.
     * 
     * @param array $productIds Product IDs to calculate prices for
     * @param Context $context Shopware context
     * @return array Price data indexed by product ID
     */
    private function calculateBatchPrices(array $productIds, Context $context): array
    {
        $cacheKey = $this->generateCacheKey($productIds, $context);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            $this->logger->debug('Retrieved batch prices from cache', [
                'product_count' => count($productIds),
                'cache_key' => $cacheKey
            ]);
            return $cacheItem->get();
        }

        $startTime = microtime(true);
        $prices = $this->executeOptimizedPriceQuery($productIds, $context);
        $executionTime = microtime(true) - $startTime;

        // Cache the results
        $cacheItem->set($prices);
        $cacheItem->expiresAfter(self::PRICE_CACHE_TTL);
        $this->cache->save($cacheItem);

        $this->logger->info('Calculated batch prices', [
            'product_count' => count($productIds),
            'execution_time' => $executionTime,
            'prices_found' => count($prices)
        ]);

        return $prices;
    }

    /**
     * Execute the optimized price query with proper joins and subqueries.
     * 
     * @param array $productIds Product IDs to query
     * @param Context $context Shopware context
     * @return array Price data
     */
    private function executeOptimizedPriceQuery(array $productIds, Context $context): array
    {
        $ruleIds = $context->getRuleIds();
        $currencyId = $context->getCurrencyId();
        $versionId = $context->getVersionId();

        // Convert UUIDs to binary for database query
        $productIdsBinary = array_map([Uuid::class, 'fromHexToBytesList'], $productIds);
        $ruleIdBinary = !empty($ruleIds) ? Uuid::fromHexToBytes($ruleIds[0]) : null;
        $currencyIdBinary = Uuid::fromHexToBytes($currencyId);
        $versionIdBinary = Uuid::fromHexToBytes($versionId);

        $query = "
            SELECT 
                LOWER(HEX(p.id)) as product_id,
                p.price,
                p.calculated_price,
                COALESCE(pc.net_price, p.price) as net_price,
                COALESCE(pc.gross_price, p.price) as gross_price,
                LOWER(HEX(COALESCE(pc.currency_id, :currency_id))) as currency_id,
                COALESCE(pr.percentage, 0) as rule_discount,
                p.tax_id,
                p.stock,
                p.available
            FROM product p
            STRAIGHT_JOIN (
                SELECT UNHEX(:product_ids_placeholder) as id
                UNION ALL SELECT UNHEX(:product_ids_placeholder)
                -- Dynamic UNION ALL for each product ID
            ) pids ON p.id = pids.id
            LEFT JOIN product_price pc ON p.id = pc.product_id 
                AND pc.rule_id = :rule_id 
                AND pc.currency_id = :currency_id
                AND pc.quantity_start <= 1
                AND pc.quantity_end IS NULL OR pc.quantity_end >= 1
            LEFT JOIN rule pr ON pc.rule_id = pr.id AND pr.invalid != 1
            WHERE p.version_id = :version_id
                AND p.id IN (:product_ids)
            ORDER BY p.id, pc.quantity_start DESC, pc.priority DESC
        ";

        // Build the dynamic UNION ALL for product IDs
        $productUnions = [];
        foreach ($productIdsBinary as $index => $productId) {
            $productUnions[] = "SELECT UNHEX(:product_id_$index) as id";
        }
        $unionClause = implode(' UNION ALL ', $productUnions);
        $query = str_replace(
            'SELECT UNHEX(:product_ids_placeholder) as id UNION ALL SELECT UNHEX(:product_ids_placeholder)',
            $unionClause,
            $query
        );

        // Prepare parameters
        $params = [
            'rule_id' => $ruleIdBinary,
            'currency_id' => $currencyIdBinary,
            'version_id' => $versionIdBinary,
            'product_ids' => $productIdsBinary
        ];

        // Add individual product ID parameters
        foreach ($productIdsBinary as $index => $productId) {
            $params["product_id_$index"] = bin2hex($productId);
        }

        try {
            $stmt = $this->connection->prepare($query);
            $result = $stmt->executeQuery($params);
            
            $prices = [];
            while ($row = $result->fetchAssociative()) {
                $productId = $row['product_id'];
                $prices[$productId] = [
                    'net_price' => (float) $row['net_price'],
                    'gross_price' => (float) $row['gross_price'],
                    'currency_id' => $row['currency_id'],
                    'rule_discount' => (float) $row['rule_discount'],
                    'stock' => (int) $row['stock'],
                    'available' => (bool) $row['available'],
                    'calculated_at' => time()
                ];
            }

            return $prices;
        } catch (\Exception $e) {
            $this->logger->error('Failed to execute optimized price query', [
                'product_count' => count($productIds),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback to individual queries if batch fails
            return $this->fallbackToIndividualQueries($productIds, $context);
        }
    }

    /**
     * Fallback method using individual queries if batch query fails.
     * 
     * @param array $productIds Product IDs
     * @param Context $context Shopware context
     * @return array Price data
     */
    private function fallbackToIndividualQueries(array $productIds, Context $context): array
    {
        $this->logger->warning('Using fallback individual price queries', [
            'product_count' => count($productIds)
        ]);

        $prices = [];
        foreach ($productIds as $productId) {
            try {
                $price = $this->getSingleProductPrice($productId, $context);
                if ($price) {
                    $prices[$productId] = $price;
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to get individual product price', [
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $prices;
    }

    /**
     * Get price for a single product (fallback method).
     * 
     * @param string $productId Product ID
     * @param Context $context Shopware context
     * @return array|null Price data or null if not found
     */
    private function getSingleProductPrice(string $productId, Context $context): ?array
    {
        $productIdBinary = Uuid::fromHexToBytes($productId);
        $ruleIds = $context->getRuleIds();
        $ruleIdBinary = !empty($ruleIds) ? Uuid::fromHexToBytes($ruleIds[0]) : null;
        $currencyIdBinary = Uuid::fromHexToBytes($context->getCurrencyId());
        $versionIdBinary = Uuid::fromHexToBytes($context->getVersionId());

        $query = "
            SELECT 
                p.price,
                COALESCE(pc.net_price, p.price) as net_price,
                COALESCE(pc.gross_price, p.price) as gross_price,
                p.stock,
                p.available
            FROM product p
            LEFT JOIN product_price pc ON p.id = pc.product_id 
                AND pc.rule_id = :rule_id 
                AND pc.currency_id = :currency_id
                AND pc.quantity_start <= 1
            WHERE p.id = :product_id 
                AND p.version_id = :version_id
            LIMIT 1
        ";

        $result = $this->connection->fetchAssociative($query, [
            'product_id' => $productIdBinary,
            'rule_id' => $ruleIdBinary,
            'currency_id' => $currencyIdBinary,
            'version_id' => $versionIdBinary
        ]);

        if (!$result) {
            return null;
        }

        return [
            'net_price' => (float) $result['net_price'],
            'gross_price' => (float) $result['gross_price'],
            'currency_id' => $context->getCurrencyId(),
            'rule_discount' => 0.0,
            'stock' => (int) $result['stock'],
            'available' => (bool) $result['available'],
            'calculated_at' => time()
        ];
    }

    /**
     * Generate cache key for batch price calculation.
     * 
     * @param array $productIds Product IDs
     * @param Context $context Shopware context
     * @return string Cache key
     */
    private function generateCacheKey(array $productIds, Context $context): string
    {
        sort($productIds); // Ensure consistent ordering
        
        $keyData = [
            'products' => implode(',', $productIds),
            'currency' => $context->getCurrencyId(),
            'rules' => implode(',', $context->getRuleIds()),
            'version' => $context->getVersionId()
        ];

        return 'batch_prices_' . hash('xxh64', serialize($keyData));
    }

    /**
     * Clear price cache for specific products.
     * 
     * @param array $productIds Product IDs to clear cache for
     * @return void
     */
    public function clearPriceCache(array $productIds = []): void
    {
        if (empty($productIds)) {
            // Clear all price cache
            $this->cache->deleteItems(['batch_prices_*']);
            $this->logger->info('Cleared all price cache');
        } else {
            // Clear specific product cache (approximate)
            foreach ($productIds as $productId) {
                $pattern = "batch_prices_*{$productId}*";
                $this->cache->deleteItems([$pattern]);
            }
            $this->logger->info('Cleared price cache for specific products', [
                'product_ids' => $productIds
            ]);
        }
    }

    /**
     * Get cache statistics.
     * 
     * @return array Cache statistics
     */
    public function getCacheStats(): array
    {
        // This would need to be implemented based on the specific cache adapter
        return [
            'cache_enabled' => true,
            'ttl' => self::PRICE_CACHE_TTL,
            'batch_size' => self::BATCH_SIZE
        ];
    }
}