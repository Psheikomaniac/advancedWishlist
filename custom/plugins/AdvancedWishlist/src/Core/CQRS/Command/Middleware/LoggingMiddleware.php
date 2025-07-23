<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Command\Middleware;

use AdvancedWishlist\Core\CQRS\Command\CommandMiddleware;
use Psr\Log\LoggerInterface;

/**
 * Logging middleware for CQRS command pipeline.
 * Provides comprehensive logging of command execution with performance metrics.
 */
class LoggingMiddleware implements CommandMiddleware
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $logCommandData = false
    ) {
    }

    public function handle(object $command, callable $next): mixed
    {
        $commandClass = get_class($command);
        $commandId = uniqid('cmd_');
        $startTime = microtime(true);
        $memoryStart = memory_get_usage(true);

        $this->logger->info('Command execution started', [
            'command_id' => $commandId,
            'command' => $commandClass,
            'command_data' => $this->logCommandData ? $this->serializeCommand($command) : null,
            'memory_start_mb' => round($memoryStart / 1024 / 1024, 2)
        ]);

        try {
            $result = $next($command);

            $duration = microtime(true) - $startTime;
            $memoryEnd = memory_get_usage(true);
            $memoryUsed = $memoryEnd - $memoryStart;

            $this->logger->info('Command execution completed successfully', [
                'command_id' => $commandId,
                'command' => $commandClass,
                'duration_ms' => round($duration * 1000, 2),
                'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'result_type' => is_object($result) ? get_class($result) : gettype($result)
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            $memoryEnd = memory_get_usage(true);
            $memoryUsed = $memoryEnd - $memoryStart;

            $this->logger->error('Command execution failed', [
                'command_id' => $commandId,
                'command' => $commandClass,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Serialize command for logging (with sensitive data filtering).
     */
    private function serializeCommand(object $command): array
    {
        try {
            $reflection = new \ReflectionClass($command);
            $data = [];

            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($command);

                // Filter sensitive data
                if ($this->isSensitiveProperty($property->getName())) {
                    $data[$property->getName()] = '[FILTERED]';
                } else {
                    $data[$property->getName()] = $this->sanitizeValue($value);
                }
            }

            return $data;
        } catch (\Throwable $e) {
            return ['serialization_error' => $e->getMessage()];
        }
    }

    /**
     * Check if property contains sensitive data.
     */
    private function isSensitiveProperty(string $propertyName): bool
    {
        $sensitiveProperties = [
            'password',
            'token',
            'secret',
            'key',
            'credential',
            'authorization'
        ];

        $lowerProperty = strtolower($propertyName);
        
        foreach ($sensitiveProperties as $sensitive) {
            if (str_contains($lowerProperty, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize value for logging.
     */
    private function sanitizeValue(mixed $value): mixed
    {
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return get_class($value);
        }

        if (is_array($value)) {
            return array_map([$this, 'sanitizeValue'], $value);
        }

        if (is_string($value) && strlen($value) > 1000) {
            return substr($value, 0, 1000) . '... [TRUNCATED]';
        }

        return $value;
    }
}