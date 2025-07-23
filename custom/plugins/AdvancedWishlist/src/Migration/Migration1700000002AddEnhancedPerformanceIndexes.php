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
        $connection->executeStatement('
            ALTER TABLE `wishlist` 
            ADD INDEX IF NOT EXISTS `idx_wishlist_customer_type_created` (`customer_id`, `type`, `created_at`),
            ADD INDEX IF NOT EXISTS `idx_wishlist_updated_performance` (`updated_at` DESC, `customer_id`),
            ADD INDEX IF NOT EXISTS `idx_wishlist_default_lookup` (`customer_id`, `is_default`, `type`)
        ');

        // Wishlist item table optimizations
        $connection->executeStatement('
            ALTER TABLE `wishlist_item`
            ADD INDEX IF NOT EXISTS `idx_wishlist_item_product_lookup` (`product_id`, `wishlist_id`, `added_at`),
            ADD INDEX IF NOT EXISTS `idx_wishlist_item_priority_sort` (`wishlist_id`, `priority` DESC, `added_at` DESC),
            ADD INDEX IF NOT EXISTS `idx_wishlist_item_price_monitoring` (`price_alert_active`, `product_id`, `price_alert_threshold`)
        ');

        // Wishlist share table optimizations
        $connection->executeStatement('
            ALTER TABLE `wishlist_share`
            ADD INDEX IF NOT EXISTS `idx_wishlist_share_performance` (`active`, `expires_at`, `wishlist_id`),
            ADD INDEX IF NOT EXISTS `idx_wishlist_share_analytics` (`type`, `created_at`, `views` DESC)
        ');

        // Guest wishlist table optimizations
        $connection->executeStatement('
            ALTER TABLE `guest_wishlist`
            ADD INDEX IF NOT EXISTS `idx_guest_wishlist_session_lookup` (`session_id`, `expires_at`),
            ADD INDEX IF NOT EXISTS `idx_guest_wishlist_cleanup` (`expires_at`, `created_at`)
        ');

        // Analytics table optimizations
        $connection->executeStatement('
            ALTER TABLE `wishlist_analytics`
            ADD INDEX IF NOT EXISTS `idx_wishlist_analytics_reporting` (`date` DESC, `wishlist_id`, `views` DESC),
            ADD INDEX IF NOT EXISTS `idx_wishlist_analytics_aggregation` (`wishlist_id`, `date`, `conversions`)
        ');
    }

    private function addJsonIndexes(Connection $connection): void
    {
        // Check MySQL version for JSON index support
        $version = $connection->fetchOne('SELECT VERSION()');
        if (version_compare($version, '5.7.0', '>=')) {
            // Guest wishlist JSON optimization
            $connection->executeStatement('
                ALTER TABLE `guest_wishlist`
                ADD INDEX IF NOT EXISTS `idx_guest_wishlist_item_count` ((CAST(JSON_LENGTH(`items`) AS UNSIGNED)))
            ');

            // Analytics JSON optimization for merge log
            $connection->executeStatement('
                ALTER TABLE `guest_wishlist_merge_log`
                ADD INDEX IF NOT EXISTS `idx_merge_log_strategy` ((CAST(JSON_EXTRACT(`merge_data`, "$.strategy") AS CHAR(50))))
            ');

            // Notification data optimization
            $connection->executeStatement('
                ALTER TABLE `wishlist_notification_queue`
                ADD INDEX IF NOT EXISTS `idx_notification_priority_type` ((CAST(JSON_EXTRACT(`data`, "$.priority") AS UNSIGNED)), `type`, `scheduled_at`)
            ');
        }
    }

    private function addCoveringIndexes(Connection $connection): void
    {
        // MySQL 8.0+ covering indexes
        $version = $connection->fetchOne('SELECT VERSION()');
        if (version_compare($version, '8.0.0', '>=')) {
            // Covering index for wishlist listing
            $connection->executeStatement('
                ALTER TABLE `wishlist`
                ADD INDEX IF NOT EXISTS `idx_wishlist_listing_cover` (`customer_id`, `type`, `is_default`, `created_at` DESC)
                INCLUDE (`id`, `name`, `item_count`, `total_value`)
            ');

            // Covering index for item browsing
            $connection->executeStatement('
                ALTER TABLE `wishlist_item` 
                ADD INDEX IF NOT EXISTS `idx_wishlist_item_browse_cover` (`wishlist_id`, `priority` DESC, `added_at` DESC)
                INCLUDE (`id`, `product_id`, `quantity`, `note`, `price_at_addition`)
            ');
        }
    }

    private function optimizePartitions(Connection $connection): void
    {
        // Add partition pruning for analytics tables
        $connection->executeStatement('
            ALTER TABLE `wishlist_analytics`
            ADD COLUMN IF NOT EXISTS `partition_key` INT GENERATED ALWAYS AS (YEAR(`date`) * 100 + MONTH(`date`)) STORED,
            ADD INDEX IF NOT EXISTS `idx_partition_pruning` (`partition_key`, `product_id`)
        ');

        // Add partition pruning for product analytics
        $connection->executeStatement('
            ALTER TABLE `wishlist_product_analytics`
            ADD COLUMN IF NOT EXISTS `partition_key` INT GENERATED ALWAYS AS (YEAR(`period_start`) * 100 + MONTH(`period_start`)) STORED,
            ADD INDEX IF NOT EXISTS `idx_product_partition_pruning` (`partition_key`, `product_id`)
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive updates needed
    }
}