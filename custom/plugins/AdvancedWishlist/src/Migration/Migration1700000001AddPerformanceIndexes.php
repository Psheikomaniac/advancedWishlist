<?php declare(strict_types=1);

namespace AdvancedWishlist\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000001AddPerformanceIndexes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000001;
    }

    public function update(Connection $connection): void
    {
        // Add index on added_at in wishlist_item table
        $connection->executeStatement('
            ALTER TABLE `wishlist_item`
            ADD INDEX `idx.wishlist_item.added_at` (`added_at`);
        ');

        // Add index on updated_at in wishlist table
        $connection->executeStatement('
            ALTER TABLE `wishlist`
            ADD INDEX `idx.wishlist.updated_at` (`updated_at`);
        ');

        // Add composite index on wishlist_id and added_at in wishlist_item table
        $connection->executeStatement('
            ALTER TABLE `wishlist_item`
            ADD INDEX `idx.wishlist_item.wishlist_added` (`wishlist_id`, `added_at`);
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive updates needed
    }
}