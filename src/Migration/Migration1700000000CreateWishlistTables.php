<?php declare(strict_types=1);

namespace AdvancedWishlist\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000000CreateWishlistTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000000;
    }

    public function update(Connection $connection): void
    {
        $this->createWishlistTable($connection);
        $this->createWishlistItemTable($connection);
        $this->createWishlistShareTable($connection);
        $this->createWishlistShareViewTable($connection);
        $this->createWishlistAnalyticsTable($connection);
        $this->createWishlistProductAnalyticsTable($connection);
        $this->createGuestWishlistTable($connection);
        $this->createGuestWishlistMergeLogTable($connection);
        $this->createWishlistNotificationQueueTable($connection);
        $this->createWishlistNotificationLogTable($connection);
        $this->createWishlistCacheTable($connection);
        $this->createViews($connection);
        $this->createProcedures($connection);
        $this->createEvents($connection);
        $this->createTriggers($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createWishlistTable(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `wishlist` (
            `id` BINARY(16) NOT NULL,
            `customer_id` BINARY(16) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `type` ENUM('private','public','shared') NOT NULL DEFAULT 'private',
            `is_default` TINYINT(1) NOT NULL DEFAULT 0,
            `sales_channel_id` BINARY(16),
            `language_id` BINARY(16),
            `item_count` INT NOT NULL DEFAULT 0,
            `total_value` DECIMAL(10,2) DEFAULT 0.00,
            `custom_fields` JSON,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3),
            PRIMARY KEY (`id`),
            KEY `idx.wishlist.customer` (`customer_id`),
            KEY `idx.wishlist.default` (`customer_id`, `is_default`),
            KEY `idx.wishlist.type` (`type`),
            KEY `idx.wishlist.created` (`created_at`),
            CONSTRAINT `fk.wishlist.customer_id` FOREIGN KEY (`customer_id`)
                REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.wishlist.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createWishlistItemTable(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `wishlist_item` (
            `id` BINARY(16) NOT NULL,
            `wishlist_id` BINARY(16) NOT NULL,
            `product_id` BINARY(16) NOT NULL,
            `product_version_id` BINARY(16) NOT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `note` VARCHAR(500),
            `priority` INT DEFAULT 0,
            `price_at_addition` DECIMAL(10,2),
            `price_alert_threshold` DECIMAL(10,2),
            `price_alert_active` TINYINT(1) DEFAULT 0,
            `custom_fields` JSON,
            `added_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq.wishlist_item.wishlist_product` (`wishlist_id`, `product_id`),
            KEY `idx.wishlist_item.product` (`product_id`),
            KEY `idx.wishlist_item.priority` (`wishlist_id`, `priority`),
            KEY `idx.wishlist_item.price_alert` (`price_alert_active`, `product_id`),
            CONSTRAINT `fk.wishlist_item.wishlist_id` FOREIGN KEY (`wishlist_id`)
                REFERENCES `wishlist` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.wishlist_item.product` FOREIGN KEY (`product_id`, `product_version_id`)
                REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        CREATE INDEX `idx.wishlist_item.price_monitoring`
        ON `wishlist_item` (`product_id`, `price_alert_threshold`)
        WHERE `price_alert_active` = 1;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createWishlistShareTable(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `wishlist_share` (
            `id` BINARY(16) NOT NULL,
            `wishlist_id` BINARY(16) NOT NULL,
            `token` VARCHAR(64) NOT NULL,
            `type` ENUM('link','email','social') NOT NULL DEFAULT 'link',
            `platform` VARCHAR(50),
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `password` VARCHAR(255),
            `expires_at` DATETIME(3),
            `settings` JSON COMMENT 'hidePrices, readOnly, allowGuestPurchase, etc.',
            `views` INT NOT NULL DEFAULT 0,
            `unique_views` INT NOT NULL DEFAULT 0,
            `conversions` INT NOT NULL DEFAULT 0,
            `last_viewed_at` DATETIME(3),
            `created_by` BINARY(16),
            `created_at` DATETIME(3) NOT NULL,
            `revoked_at` DATETIME(3),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq.wishlist_share.token` (`token`),
            KEY `idx.wishlist_share.wishlist` (`wishlist_id`),
            KEY `idx.wishlist_share.active` (`active`, `expires_at`),
            KEY `idx.wishlist_share.type` (`type`),
            CONSTRAINT `fk.wishlist_share.wishlist` FOREIGN KEY (`wishlist_id`)
                REFERENCES `wishlist` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createWishlistShareViewTable(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `wishlist_share_view` (
            `id` BINARY(16) NOT NULL,
            `share_id` BINARY(16) NOT NULL,
            `visitor_id` VARCHAR(64) NOT NULL,
            `customer_id` BINARY(16),
            `ip_address` VARCHAR(45),
            `user_agent` VARCHAR(500),
            `referrer` VARCHAR(500),
            `country_code` CHAR(2),
            `device_type` ENUM('desktop','mobile','tablet','other'),
            `purchased` TINYINT(1) DEFAULT 0,
            `purchase_value` DECIMAL(10,2),
            `viewed_at` DATETIME(3) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq.share_view.visitor` (`share_id`, `visitor_id`),
            KEY `idx.share_view.share` (`share_id`),
            KEY `idx.share_view.viewed` (`viewed_at`),
            KEY `idx.share_view.country` (`country_code`),
            CONSTRAINT `fk.share_view.share` FOREIGN KEY (`share_id`)
                REFERENCES `wishlist_share` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createWishlistAnalyticsTable(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `wishlist_analytics` (
            `id` BINARY(16) NOT NULL,
            `wishlist_id` BINARY(16) NOT NULL,
            `date` DATE NOT NULL,
            `views` INT NOT NULL DEFAULT 0,
            `shares` INT NOT NULL DEFAULT 0,
            `items_added` INT NOT NULL DEFAULT 0,
            `items_removed` INT NOT NULL DEFAULT 0,
            `conversions` INT NOT NULL DEFAULT 0,
            `conversion_value` DECIMAL(10,2) DEFAULT 0.00,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq.wishlist_analytics.date` (`wishlist_id`, `date`),
            KEY `idx.wishlist_analytics.date` (`date`),
            CONSTRAINT `fk.wishlist_analytics.wishlist` FOREIGN KEY (`wishlist_id`)
                REFERENCES `wishlist` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createWishlistProductAnalyticsTable(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `wishlist_product_analytics` (
            `id` BINARY(16) NOT NULL,
            `product_id` BINARY(16) NOT NULL,
            `period_start` DATE NOT NULL,
            `period_type` ENUM('day','week','month') NOT NULL DEFAULT 'day',
            `wishlist_count` INT NOT NULL DEFAULT 0,
            `unique_customers` INT NOT NULL DEFAULT 0,
            `conversion_count` INT NOT NULL DEFAULT 0,
            `conversion_rate` DECIMAL(5,2) DEFAULT 0.00,
            `avg_days_to_purchase` DECIMAL(5,1),
            `trend_direction` ENUM('up','down','stable','new') DEFAULT 'stable',
            `trend_percentage` DECIMAL(5,2),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq.product_analytics.period` (`product_id`, `period_start`, `period_type`),
            KEY `idx.product_analytics.count` (`wishlist_count` DESC),
            KEY `idx.product_analytics.trend` (`trend_direction`, `trend_percentage`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        PARTITION BY RANGE (YEAR(period_start)) (
            PARTITION p2024 VALUES LESS THAN (2025),
            PARTITION p2025 VALUES LESS THAN (2026),
            PARTITION p2026 VALUES LESS THAN (2027),
            PARTITION pfuture VALUES LESS THAN MAXVALUE
        );
        SQL;
        $connection->executeStatement($sql);
    }

    private function createGuestWishlistTable(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `guest_wishlist` (
            `id` BINARY(16) NOT NULL,
            `guest_id` VARCHAR(64) NOT NULL,
            `session_id` VARCHAR(128),
            `sales_channel_id` BINARY(16) NOT NULL,
            `language_id` BINARY(16) NOT NULL,
            `currency_id` BINARY(16) NOT NULL,
            `name` VARCHAR(255) DEFAULT 'Guest Wishlist',
            `items` JSON NOT NULL COMMENT 'Array of wishlist items',
            `item_count` INT GENERATED ALWAYS AS (JSON_LENGTH(`items`)) STORED,
            `expires_at` DATETIME(3) NOT NULL,
            `ip_address` VARCHAR(45),
            `user_agent` VARCHAR(500),
            `device_fingerprint` VARCHAR(128),
            `reminder_sent_at` DATETIME(3),
            `reminder_email` VARCHAR(255),
            `conversion_tracking` JSON,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3),
            PRIMARY KEY (`id`),
            KEY `idx.guest_wishlist.guest_id` (`guest_id`),
            KEY `idx.guest_wishlist.expires` (`expires_at`),
            KEY `idx.guest_wishlist.session` (`session_id`),
            KEY `idx.guest_wishlist.reminder` (`reminder_email`, `reminder_sent_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createGuestWishlistMergeLogTable(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `guest_wishlist_merge_log` (
            `id` BINARY(16) NOT NULL,
            `guest_wishlist_id` BINARY(16) NOT NULL,
            `customer_wishlist_id` BINARY(16) NOT NULL,
            `customer_id` BINARY(16) NOT NULL,
            `guest_id` VARCHAR(64) NOT NULL,
            `items_merged` INT NOT NULL DEFAULT 0,
            `items_skipped` INT NOT NULL DEFAULT 0,
            `items_failed` INT NOT NULL DEFAULT 0,
            `merge_strategy` ENUM('merge','replace','append') NOT NULL DEFAULT 'merge',
            `merge_data` JSON,
            `merged_at` DATETIME(3) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx.merge_log.customer` (`customer_id`),
            KEY `idx.merge_log.date` (`merged_at`),
            KEY `idx.merge_log.guest` (`guest_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createWishlistNotificationQueueTable(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `wishlist_notification_queue` (
            `id` BINARY(16) NOT NULL,
            `type` ENUM('price_drop','back_in_stock','reminder','share') NOT NULL,
            `recipient_id` BINARY(16) NOT NULL,
            `recipient_type` ENUM('customer','guest') NOT NULL DEFAULT 'customer',
            `wishlist_id` BINARY(16),
            `item_id` BINARY(16),
            `data` JSON NOT NULL,
            `priority` INT NOT NULL DEFAULT 0,
            `attempts` INT NOT NULL DEFAULT 0,
            `scheduled_at` DATETIME(3) NOT NULL,
            `sent_at` DATETIME(3),
            `failed_at` DATETIME(3),
            `error_message` TEXT,
            `created_at` DATETIME(3) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx.notification_queue.scheduled` (`scheduled_at`, `sent_at`),
            KEY `idx.notification_queue.type` (`type`, `priority`),
            KEY `idx.notification_queue.recipient` (`recipient_id`, `recipient_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createWishlistNotificationLogTable(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `wishlist_notification_log` (
            `id` BINARY(16) NOT NULL,
            `notification_id` BINARY(16) NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `channel` ENUM('email','push','sms') NOT NULL DEFAULT 'email',
            `recipient` VARCHAR(255) NOT NULL,
            `subject` VARCHAR(500),
            `status` ENUM('sent','delivered','opened','clicked','bounced','failed') NOT NULL,
            `metadata` JSON,
            `sent_at` DATETIME(3) NOT NULL,
            `delivered_at` DATETIME(3),
            `opened_at` DATETIME(3),
            `clicked_at` DATETIME(3),
            PRIMARY KEY (`id`),
            KEY `idx.notification_log.type` (`type`),
            KEY `idx.notification_log.status` (`status`),
            KEY `idx.notification_log.sent` (`sent_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createWishlistCacheTable(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `wishlist_cache` (
            `id` BINARY(16) NOT NULL,
            `cache_key` VARCHAR(255) NOT NULL,
            `cache_type` ENUM('top_products','user_stats','share_metrics') NOT NULL,
            `data` JSON NOT NULL,
            `expires_at` DATETIME(3) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq.wishlist_cache.key` (`cache_key`),
            KEY `idx.wishlist_cache.expires` (`expires_at`),
            KEY `idx.wishlist_cache.type` (`cache_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createViews(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE VIEW `v_wishlist_product_popularity` AS
        SELECT
          p.id AS product_id,
          p.product_number,
          pt.name AS product_name,
          COUNT(DISTINCT wi.wishlist_id) AS wishlist_count,
          COUNT(DISTINCT w.customer_id) AS unique_customers,
          AVG(wi.priority) AS avg_priority,
          MIN(wi.added_at) AS first_added,
          MAX(wi.added_at) AS last_added
        FROM product p
        INNER JOIN wishlist_item wi ON p.id = wi.product_id
        INNER JOIN wishlist w ON wi.wishlist_id = w.id
        LEFT JOIN product_translation pt ON p.id = pt.product_id
        WHERE w.type != 'private' OR w.customer_id = @current_customer_id
        GROUP BY p.id;
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        CREATE TABLE `mv_wishlist_conversion_stats` (
          `product_id` BINARY(16) NOT NULL,
          `period` DATE NOT NULL,
          `wishlisted_count` INT NOT NULL DEFAULT 0,
          `purchased_count` INT NOT NULL DEFAULT 0,
          `conversion_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
          `avg_days_to_purchase` DECIMAL(5,1),
          `revenue_generated` DECIMAL(10,2) DEFAULT 0.00,
          PRIMARY KEY (`product_id`, `period`),
          KEY `idx.conversion_stats.rate` (`conversion_rate` DESC)
        ) ENGINE=InnoDB;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createProcedures(Connection $connection): void
    {
        $sql = <<<SQL
        DELIMITER $
        CREATE PROCEDURE `refresh_wishlist_conversion_stats`()
        BEGIN
          TRUNCATE TABLE `mv_wishlist_conversion_stats`;

          INSERT INTO `mv_wishlist_conversion_stats`
          SELECT
            wi.product_id,
            DATE(o.order_date) AS period,
            COUNT(DISTINCT wi.wishlist_id) AS wishlisted_count,
            COUNT(DISTINCT o.id) AS purchased_count,
            (COUNT(DISTINCT o.id) / COUNT(DISTINCT wi.wishlist_id)) * 100 AS conversion_rate,
            AVG(DATEDIFF(o.order_date, wi.added_at)) AS avg_days_to_purchase,
            SUM(oi.total_price) AS revenue_generated
          FROM wishlist_item wi
          LEFT JOIN order_line_item oi ON wi.product_id = oi.product_id
          LEFT JOIN `order` o ON oi.order_id = o.id
            AND o.customer_id = (SELECT customer_id FROM wishlist WHERE id = wi.wishlist_id)
            AND o.order_date > wi.added_at
          GROUP BY wi.product_id, DATE(o.order_date);
        END$
        DELIMITER ;
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        DELIMITER $
        CREATE PROCEDURE `cleanup_old_data`()
        BEGIN
          -- Delete expired guest wishlists
          DELETE FROM `guest_wishlist`
          WHERE `expires_at` < DATE_SUB(NOW(), INTERVAL 90 DAY)
          LIMIT 1000;

          -- Archive old analytics data
          INSERT INTO `wishlist_analytics_archive`
          SELECT * FROM `wishlist_analytics`
          WHERE `date` < DATE_SUB(NOW(), INTERVAL 1 YEAR);

          DELETE FROM `wishlist_analytics`
          WHERE `date` < DATE_SUB(NOW(), INTERVAL 1 YEAR);

          -- Optimize tables
          OPTIMIZE TABLE `wishlist_share_view`;
          OPTIMIZE TABLE `guest_wishlist`;
        END$
        DELIMITER ;
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        DELIMITER $
        CREATE PROCEDURE `update_product_statistics`()
        BEGIN
          -- Update wishlist counts
        UPDATE `product` p
        SET p.`wishlist_count` = (
            SELECT COUNT(DISTINCT wi.`wishlist_id`)
            FROM `wishlist_item` wi
            WHERE wi.`product_id` = p.`id`
        );

        -- Refresh materialized views
        CALL refresh_wishlist_conversion_stats();
        END$
        DELIMITER ;
        SQL;
        $connection->executeStatement($sql);
    }

    private function createEvents(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE EVENT `cleanup_expired_guest_wishlists`
        ON SCHEDULE EVERY 1 DAY
        DO
          DELETE FROM `guest_wishlist`
          WHERE `expires_at` < NOW()
          LIMIT 1000;
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        CREATE EVENT `weekly_wishlist_cleanup`
        ON SCHEDULE EVERY 1 WEEK
        STARTS '2024-01-07 03:00:00'
        DO CALL cleanup_old_data();
        SQL;
        $connection->executeStatement($sql);
    }

    private function createTriggers(Connection $connection): void
    {
        $sql = <<<SQL
        DELIMITER $
        CREATE TRIGGER `wishlist_item_count_insert`
        AFTER INSERT ON `wishlist_item`
        FOR EACH ROW
        BEGIN
          UPDATE `wishlist`
          SET `item_count` = `item_count` + 1,
              `updated_at` = CURRENT_TIMESTAMP(3)
          WHERE `id` = NEW.`wishlist_id`;
        END$

        CREATE TRIGGER `wishlist_item_count_delete`
        AFTER DELETE ON `wishlist_item`
        FOR EACH ROW
        BEGIN
          UPDATE `wishlist`
          SET `item_count` = `item_count` - 1,
              `updated_at` = CURRENT_TIMESTAMP(3)
          WHERE `id` = OLD.`wishlist_id`;
        END$
        DELIMITER ;
        SQL;
        $connection->executeStatement($sql);
    }
}
