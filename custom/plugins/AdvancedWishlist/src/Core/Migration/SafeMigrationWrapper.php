<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Migration;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Migration\MigrationStep;
use AdvancedWishlist\Core\Exception\MigrationFailedException;

/**
 * Safe Migration Wrapper for AdvancedWishlist
 * 
 * Implements comprehensive migration safety framework as specified in 
 * deployment-strategy-implementation.md PRD
 * 
 * Features:
 * - Pre/post migration validation
 * - Automatic backup creation
 * - Transaction-based execution
 * - Rollback on failure
 * - Data integrity checks
 */
abstract class SafeMigrationWrapper extends MigrationStep
{
    protected LoggerInterface $logger;
    protected MigrationValidator $validator;
    protected MigrationBackupService $backupService;

    public function __construct()
    {
        // These will be injected via DI in real implementation
        $this->logger = new \Psr\Log\NullLogger();
        $this->validator = new MigrationValidator();
        $this->backupService = new MigrationBackupService();
    }

    final public function update(Connection $connection): void
    {
        $migrationId = $this->getMigrationId();
        $backupId = null;

        $this->logger->info("Starting safe migration: {$migrationId}");

        try {
            // 1. Pre-migration validation
            $this->logger->debug("Running pre-migration validation");
            $this->validator->validatePreconditions($connection, $migrationId);

            // 2. Create backup point
            $this->logger->debug("Creating backup point");
            $backupId = $this->backupService->createBackupPoint($connection, $migrationId);

            // 3. Execute migration in transaction
            $this->logger->debug("Executing migration in transaction");
            $connection->beginTransaction();
            
            try {
                $this->executeSafeMigration($connection);
                $connection->commit();
                $this->logger->info("Migration committed successfully");
            } catch (\Exception $e) {
                $connection->rollBack();
                $this->logger->error("Migration failed, transaction rolled back", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            // 4. Post-migration validation
            $this->logger->debug("Running post-migration validation");
            $this->validator->validatePostConditions($connection, $migrationId);

            // 5. Mark backup as successful (can be cleaned up later)
            $this->backupService->markBackupSuccessful($backupId);

            $this->logger->info("Safe migration completed successfully: {$migrationId}");

        } catch (\Exception $e) {
            $this->logger->error("Migration failed: {$migrationId}", [
                'error' => $e->getMessage(),
                'backup_id' => $backupId
            ]);

            // Attempt to restore from backup if one was created
            if ($backupId) {
                try {
                    $this->logger->warning("Attempting to restore from backup: {$backupId}");
                    $this->backupService->restoreFromBackup($connection, $backupId);
                    $this->logger->info("Successfully restored from backup");
                } catch (\Exception $restoreException) {
                    $this->logger->critical("Failed to restore from backup", [
                        'backup_id' => $backupId,
                        'restore_error' => $restoreException->getMessage(),
                        'original_error' => $e->getMessage()
                    ]);
                }
            }

            throw new MigrationFailedException(
                "Migration {$migrationId} failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Override this method to implement the actual migration logic
     */
    abstract protected function executeSafeMigration(Connection $connection): void;

    /**
     * Get unique migration identifier
     */
    protected function getMigrationId(): string
    {
        return static::class . '_' . $this->getCreationTimestamp();
    }

    /**
     * Implement updateDestructive if needed
     */
    public function updateDestructive(Connection $connection): void
    {
        // Most migrations should not be destructive
        // Override only if absolutely necessary
    }
}

/**
 * Migration Validator for pre/post condition checks
 */
class MigrationValidator
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = new \Psr\Log\NullLogger();
    }

    public function validatePreconditions(Connection $connection, string $migrationId): void
    {
        $this->logger->debug("Validating preconditions for migration: {$migrationId}");

        // Check database locks
        $this->checkForActiveQueries($connection);
        
        // Validate existing data integrity
        $this->validateExistingData($connection);
        
        // Check disk space
        $this->validateDiskSpace($connection);
        
        // Verify backup system availability
        $this->validateBackupSystemAvailability();

        $this->logger->debug("All preconditions validated successfully");
    }

    public function validatePostConditions(Connection $connection, string $migrationId): void
    {
        $this->logger->debug("Validating post-conditions for migration: {$migrationId}");

        // Verify schema changes applied correctly
        $this->validateSchemaIntegrity($connection);
        
        // Test critical queries still work
        $this->validateCriticalQueries($connection);
        
        // Check performance impact
        $this->measureQueryPerformance($connection);

        // Validate wishlist-specific functionality
        $this->validateWishlistFunctionality($connection);

        $this->logger->debug("All post-conditions validated successfully");
    }

    private function checkForActiveQueries(Connection $connection): void
    {
        try {
            $activeQueries = $connection->executeQuery(
                "SELECT COUNT(*) as active_count FROM INFORMATION_SCHEMA.PROCESSLIST 
                 WHERE COMMAND != 'Sleep' AND ID != CONNECTION_ID() AND TIME > 5"
            )->fetchOne();

            if ($activeQueries > 10) {
                throw new \RuntimeException("Too many active queries: {$activeQueries}");
            }

            $this->logger->debug("Active queries check passed", ['active_count' => $activeQueries]);
        } catch (\Exception $e) {
            $this->logger->warning("Could not check active queries", ['error' => $e->getMessage()]);
        }
    }

    private function validateExistingData(Connection $connection): void
    {
        try {
            // Check if wishlist tables exist and have valid data
            $tables = ['wishlist', 'wishlist_item', 'wishlist_share'];
            
            foreach ($tables as $table) {
                if ($this->tableExists($connection, $table)) {
                    $count = $connection->executeQuery("SELECT COUNT(*) FROM {$table}")->fetchOne();
                    $this->logger->debug("Table {$table} has {$count} records");
                    
                    // Basic integrity check - no NULL required fields
                    $nullCheck = $connection->executeQuery(
                        "SELECT COUNT(*) FROM {$table} WHERE id IS NULL"
                    )->fetchOne();
                    
                    if ($nullCheck > 0) {
                        throw new \RuntimeException("Found NULL IDs in table {$table}");
                    }
                }
            }

            $this->logger->debug("Existing data validation passed");
        } catch (\Exception $e) {
            throw new \RuntimeException("Data validation failed: {$e->getMessage()}");
        }
    }

    private function validateDiskSpace(Connection $connection): void
    {
        $freeSpace = disk_free_space('/var/lib/mysql');
        $requiredSpace = 1024 * 1024 * 1024; // 1GB minimum

        if ($freeSpace < $requiredSpace) {
            throw new \RuntimeException("Insufficient disk space for migration");
        }

        $this->logger->debug("Disk space validation passed", [
            'free_space_mb' => round($freeSpace / (1024 * 1024), 2)
        ]);
    }

    private function validateBackupSystemAvailability(): void
    {
        // Check if mysqldump is available
        if (!shell_exec('which mysqldump')) {
            throw new \RuntimeException("mysqldump not available for backup creation");
        }

        // Check backup directory is writable
        $backupDir = '/var/backups/migrations';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        if (!is_writable($backupDir)) {
            throw new \RuntimeException("Backup directory not writable: {$backupDir}");
        }

        $this->logger->debug("Backup system availability validated");
    }

    private function validateSchemaIntegrity(Connection $connection): void
    {
        try {
            // Verify table structures are as expected
            $result = $connection->executeQuery("SHOW TABLES LIKE 'wishlist%'")->fetchAllAssociative();
            
            $expectedTables = ['wishlist', 'wishlist_item', 'wishlist_share', 'wishlist_analytics'];
            $foundTables = array_column($result, 'Tables_in_' . $connection->getDatabase() . ' (wishlist%)');
            
            foreach ($expectedTables as $table) {
                if (!in_array($table, $foundTables)) {
                    $this->logger->warning("Expected table not found: {$table}");
                }
            }

            $this->logger->debug("Schema integrity validation completed");
        } catch (\Exception $e) {
            throw new \RuntimeException("Schema validation failed: {$e->getMessage()}");
        }
    }

    private function validateCriticalQueries(Connection $connection): void
    {
        $criticalQueries = [
            'SELECT COUNT(*) FROM wishlist' => 'Wishlist count query',
            'SELECT * FROM wishlist LIMIT 1' => 'Wishlist select query',
            'SELECT COUNT(*) FROM wishlist_item' => 'Wishlist item count query',
        ];

        foreach ($criticalQueries as $query => $description) {
            try {
                $start = microtime(true);
                $connection->executeQuery($query);
                $duration = (microtime(true) - $start) * 1000;
                
                if ($duration > 1000) { // 1 second threshold
                    $this->logger->warning("Slow critical query", [
                        'query' => $description,
                        'duration_ms' => $duration
                    ]);
                }
                
                $this->logger->debug("Critical query validated", [
                    'query' => $description,
                    'duration_ms' => round($duration, 2)
                ]);
            } catch (\Exception $e) {
                throw new \RuntimeException("Critical query failed: {$description} - {$e->getMessage()}");
            }
        }
    }

    private function measureQueryPerformance(Connection $connection): void
    {
        try {
            $start = microtime(true);
            
            // Test complex query performance
            $connection->executeQuery(
                "SELECT w.id, w.name, COUNT(wi.id) as item_count 
                 FROM wishlist w 
                 LEFT JOIN wishlist_item wi ON w.id = wi.wishlist_id 
                 GROUP BY w.id 
                 LIMIT 10"
            );
            
            $duration = (microtime(true) - $start) * 1000;
            
            if ($duration > 500) {
                $this->logger->warning("Performance degradation detected", [
                    'duration_ms' => $duration
                ]);
            }

            $this->logger->debug("Query performance measured", [
                'complex_query_duration_ms' => round($duration, 2)
            ]);
        } catch (\Exception $e) {
            $this->logger->warning("Performance measurement failed", ['error' => $e->getMessage()]);
        }
    }

    private function validateWishlistFunctionality(Connection $connection): void
    {
        try {
            // Verify wishlist-specific business logic still works
            $result = $connection->executeQuery(
                "SELECT w.*, COUNT(wi.id) as item_count 
                 FROM wishlist w 
                 LEFT JOIN wishlist_item wi ON w.id = wi.wishlist_id 
                 GROUP BY w.id 
                 HAVING item_count >= 0
                 LIMIT 5"
            )->fetchAllAssociative();

            $this->logger->debug("Wishlist functionality validation passed", [
                'sample_wishlists' => count($result)
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException("Wishlist functionality validation failed: {$e->getMessage()}");
        }
    }

    private function tableExists(Connection $connection, string $tableName): bool
    {
        try {
            $result = $connection->executeQuery(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$tableName]
            )->fetchOne();

            return $result > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}

/**
 * Migration Backup Service for creating and managing backups
 */
class MigrationBackupService
{
    private LoggerInterface $logger;
    private string $backupDir;

    public function __construct()
    {
        $this->logger = new \Psr\Log\NullLogger();
        $this->backupDir = '/var/backups/migrations';
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    public function createBackupPoint(Connection $connection, string $migrationId): string
    {
        $backupId = $migrationId . '_' . date('Y-m-d_H-i-s') . '_' . uniqid();
        $backupFile = $this->backupDir . '/' . $backupId . '.sql';

        $this->logger->info("Creating backup point", [
            'migration_id' => $migrationId,
            'backup_id' => $backupId,
            'backup_file' => $backupFile
        ]);

        try {
            // Create database backup using mysqldump
            $database = $connection->getDatabase();
            $host = $connection->getHost();
            $username = $connection->getUsername();
            $password = $connection->getPassword();

            $command = sprintf(
                'mysqldump --single-transaction --routines --triggers --host=%s --user=%s --password=%s %s > %s',
                escapeshellarg($host),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($backupFile)
            );

            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException("Backup creation failed with return code: {$returnCode}");
            }

            // Verify backup file was created and has content
            if (!file_exists($backupFile) || filesize($backupFile) < 1024) {
                throw new \RuntimeException("Backup file is empty or not created");
            }

            // Compress backup to save space
            $compressedFile = $backupFile . '.gz';
            exec("gzip {$backupFile}", $output, $returnCode);

            if ($returnCode === 0 && file_exists($compressedFile)) {
                $backupFile = $compressedFile;
            }

            $this->logger->info("Backup created successfully", [
                'backup_id' => $backupId,
                'backup_file' => $backupFile,
                'size_bytes' => filesize($backupFile)
            ]);

            return $backupId;

        } catch (\Exception $e) {
            $this->logger->error("Backup creation failed", [
                'migration_id' => $migrationId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Failed to create backup: {$e->getMessage()}");
        }
    }

    public function restoreFromBackup(Connection $connection, string $backupId): void
    {
        $backupFile = $this->backupDir . '/' . $backupId . '.sql';
        $compressedFile = $backupFile . '.gz';

        // Check for compressed version first
        if (file_exists($compressedFile)) {
            $this->logger->debug("Decompressing backup file");
            exec("gunzip {$compressedFile}");
        }

        if (!file_exists($backupFile)) {
            throw new \RuntimeException("Backup file not found: {$backupFile}");
        }

        $this->logger->warning("Restoring from backup", [
            'backup_id' => $backupId,
            'backup_file' => $backupFile
        ]);

        try {
            $database = $connection->getDatabase();
            $host = $connection->getHost();
            $username = $connection->getUsername();
            $password = $connection->getPassword();

            $command = sprintf(
                'mysql --host=%s --user=%s --password=%s %s < %s',
                escapeshellarg($host),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($backupFile)
            );

            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException("Backup restore failed with return code: {$returnCode}");
            }

            $this->logger->info("Backup restored successfully", ['backup_id' => $backupId]);

        } catch (\Exception $e) {
            $this->logger->critical("Backup restore failed", [
                'backup_id' => $backupId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Failed to restore backup: {$e->getMessage()}");
        }
    }

    public function markBackupSuccessful(string $backupId): void
    {
        $successFile = $this->backupDir . '/' . $backupId . '.success';
        file_put_contents($successFile, date('Y-m-d H:i:s') . " - Migration completed successfully\n");
        
        $this->logger->debug("Backup marked as successful", ['backup_id' => $backupId]);
    }

    public function cleanupOldBackups(int $retentionDays = 30): void
    {
        $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
        $files = glob($this->backupDir . '/*');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $this->logger->debug("Cleaned up old backup file", ['file' => basename($file)]);
            }
        }
    }
}