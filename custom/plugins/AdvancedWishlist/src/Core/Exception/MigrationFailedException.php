<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Exception;

/**
 * Exception thrown when a migration fails and cannot be completed safely
 */
class MigrationFailedException extends \RuntimeException
{
    private ?string $migrationId;
    private ?string $backupId;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $migrationId = null,
        ?string $backupId = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->migrationId = $migrationId;
        $this->backupId = $backupId;
    }

    public function getMigrationId(): ?string
    {
        return $this->migrationId;
    }

    public function getBackupId(): ?string
    {
        return $this->backupId;
    }
}