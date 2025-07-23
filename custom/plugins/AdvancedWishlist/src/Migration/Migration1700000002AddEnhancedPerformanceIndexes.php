<?php

declare(strict_types=1);

namespace AdvancedWishlist\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000002AddEnhancedPerformanceIndexes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000002;
    }

    public function update(Connection $connection): void
    {
        // Primary performance indexes
        $this->addPrimaryIndexes($connection);
        
        // JSON functional indexes
        $this->addJsonIndexes($connection);
        
        // Covering indexes
        $this->addCoveringIndexes($connection);
        
        // Partition optimization
        $this->optimizePartitions($connection);
    }

    private function addPrimaryIndexes(Connection $connection): void
    {
        // Wishlist table optimizations
        $this->addIndexIfNotExists($connection, 'wishlist', 'idx_wishlist_customer_type_created', '(`customer_id`, `type`, `created_at`)');
        $this->addIndexIfNotExists($connection, 'wishlist', 'idx_wishlist_updated_performance', '(`updated_at` DESC, `customer_id`)');
        $this->addIndexIfNotExists($connection, 'wishlist', 'idx_wishlist_default_lookup', '(`customer_id`, `is_default`, `type`)');

        // Wishlist item table optimizations
        $this->addIndexIfNotExists($connection, 'wishlist_item', 'idx_wishlist_item_product_lookup', '(`product_id`, `wishlist_id`, `added_at`)');
        $this->addIndexIfNotExists($connection, 'wishlist_item', 'idx_wishlist_item_priority_sort', '(`wishlist_id`, `priority` DESC, `added_at` DESC)');
        $this->addIndexIfNotExists($connection, 'wishlist_item', 'idx_wishlist_item_price_monitoring', '(`price_alert_active`, `product_id`, `price_alert_threshold`)');

        // Wishlist share table optimizations
        $this->addIndexIfNotExists($connection, 'wishlist_share', 'idx_wishlist_share_performance', '(`active`, `expires_at`, `wishlist_id`)');
        $this->addIndexIfNotExists($connection, 'wishlist_share', 'idx_wishlist_share_analytics', '(`type`, `created_at`, `views` DESC)');

        // Guest wishlist table optimizations
        $this->addIndexIfNotExists($connection, 'guest_wishlist', 'idx_guest_wishlist_session_lookup', '(`session_id`, `expires_at`)');
        $this->addIndexIfNotExists($connection, 'guest_wishlist', 'idx_guest_wishlist_cleanup', '(`expires_at`, `created_at`)');

        // Analytics table optimizations
        $this->addIndexIfNotExists($connection, 'wishlist_analytics', 'idx_wishlist_analytics_reporting', '(`date` DESC, `wishlist_id`, `views` DESC)');
        $this->addIndexIfNotExists($connection, 'wishlist_analytics', 'idx_wishlist_analytics_aggregation', '(`wishlist_id`, `date`, `conversions`)');
    }

    private function addJsonIndexes(Connection $connection): void
    {
        // Check MySQL version for JSON index support
        $version = $connection->fetchOne('SELECT VERSION()');
        if (version_compare($version, '5.7.0', '>=')) {
            // For JSON indexes, we need to check if they exist differently
            try {
                $connection->executeStatement('
                    ALTER TABLE `guest_wishlist`
                    ADD INDEX `idx_guest_wishlist_item_count` ((CAST(JSON_LENGTH(`items`) AS UNSIGNED)))
                ');
            } catch (\Exception $e) {
                // Index might already exist, ignore error
            }

            try {
                $connection->executeStatement('
                    ALTER TABLE `guest_wishlist_merge_log`
                    ADD INDEX `idx_merge_log_strategy` ((CAST(JSON_EXTRACT(`merge_data`, "$.strategy") AS CHAR(50))))
                ');
            } catch (\Exception $e) {
                // Index might already exist, ignore error
            }

            try {
                $connection->executeStatement('
                    ALTER TABLE `wishlist_notification_queue`
                    ADD INDEX `idx_notification_priority_type` ((CAST(JSON_EXTRACT(`data`, "$.priority") AS UNSIGNED)), `type`, `scheduled_at`)
                ');
            } catch (\Exception $e) {
                // Index might already exist, ignore error
            }
        }
    }

    private function addCoveringIndexes(Connection $connection): void
    {
        // MySQL 8.0+ covering indexes (skip for now as not all MySQL versions support this)
        $version = $connection->fetchOne('SELECT VERSION()');
        if (version_compare($version, '8.0.13', '>=')) {
            try {
                $connection->executeStatement('
                    ALTER TABLE `wishlist`
                    ADD INDEX `idx_wishlist_listing_cover` (`customer_id`, `type`, `is_default`, `created_at` DESC)
                ');
            } catch (\Exception $e) {
                // Index might already exist or not supported, ignore error
            }

            try {
                $connection->executeStatement('
                    ALTER TABLE `wishlist_item` 
                    ADD INDEX `idx_wishlist_item_browse_cover` (`wishlist_id`, `priority` DESC, `added_at` DESC)
                ');
            } catch (\Exception $e) {
                // Index might already exist or not supported, ignore error
            }
        }
    }

    private function optimizePartitions(Connection $connection): void
    {
        // Add partition pruning for analytics tables (skip generated columns for now)
        try {
            $this->addIndexIfNotExists($connection, 'wishlist_analytics', 'idx_analytics_date_pruning', '(`date`, `wishlist_id`)');
        } catch (\Exception $e) {
            // Skip if table doesn't exist yet
        }

        try {
            $this->addIndexIfNotExists($connection, 'wishlist_product_analytics', 'idx_product_analytics_pruning', '(`period_start`, `product_id`)');
        } catch (\Exception $e) {
            // Skip if table doesn't exist yet
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive updates needed
    }

    /**
     * Helper method to add index only if it doesn't exist
     */
    private function addIndexIfNotExists(Connection $connection, string $table, string $indexName, string $columns): void
    {
        try {
            // Check if index exists
            $result = $connection->fetchOne(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
                [$table, $indexName]
            );

            if ($result == 0) {
                $sql = "ALTER TABLE `{$table}` ADD INDEX `{$indexName}` {$columns}";
                $connection->executeStatement($sql);
            }
        } catch (\Exception $e) {
            // If index creation fails, log but don't stop migration
            error_log("Warning: Could not create index {$indexName} on table {$table}: " . $e->getMessage());
        }
    }
}