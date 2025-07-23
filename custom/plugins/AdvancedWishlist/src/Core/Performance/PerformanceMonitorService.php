<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Performance;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * Performance monitoring service implementing comprehensive metrics collection.
 * Tracks response times, memory usage, database queries, and cache performance.
 */
class PerformanceMonitorService
{
    private array $metrics = [];
    private array $operationStack = [];
    private array $alertThresholds = [];
    
    private const METRIC_TTL = 3600; // 1 hour
    private const SLOW_QUERY_THRESHOLD = 100; // 100ms
    private const HIGH_MEMORY_THRESHOLD = 67108864; // 64MB
    private const MAX_QUERIES_THRESHOLD = 50;
    
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RedisAdapter $cacheAdapter,
        private readonly array $config = []
    ) {
        $this->initializeAlertThresholds();
    }

    /**
     * Track a performance operation with automatic timing and resource monitoring.
     */
    public function trackOperation(string $operation, callable $callback, array $context = []): mixed
    {
        $operationId = uniqid('op_');
        $startTime = microtime(true);
        $memoryStart = memory_get_usage(true);
        $queryCountStart = $this->getDatabaseQueryCount();

        // Push operation onto stack for nested tracking
        $this->operationStack[] = [
            'id' => $operationId,
            'operation' => $operation,
            'start_time' => $startTime,
            'start_memory' => $memoryStart,
            'start_queries' => $queryCountStart,
            'context' => $context
        ];

        try {
            $result = $callback();
            
            $this->recordOperationSuccess($operationId, $operation, $startTime, $memoryStart, $queryCountStart, $context);
            
            return $result;
        } catch (\Exception $e) {
            $this->recordOperationError($operationId, $operation, $e, $startTime, $memoryStart, $queryCountStart, $context);
            
            throw $e;
        } finally {
            array_pop($this->operationStack);
        }
    }

    /**
     * Start manual performance tracking.
     */
    public function startTracking(string $operation, array $context = []): string
    {
        $operationId = uniqid('manual_');
        
        $this->operationStack[] = [
            'id' => $operationId,
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_queries' => $this->getDatabaseQueryCount(),
            'context' => $context,
            'manual' => true
        ];

        return $operationId;
    }

    /**
     * Stop manual performance tracking.
     */
    public function stopTracking(string $operationId, bool $success = true, ?\Exception $exception = null): array
    {
        $operation = null;
        foreach ($this->operationStack as $index => $op) {
            if ($op['id'] === $operationId) {
                $operation = $op;
                unset($this->operationStack[$index]);
                break;
            }
        }

        if (!$operation) {
            $this->logger->warning('Attempted to stop tracking unknown operation', ['operation_id' => $operationId]);
            return [];
        }

        if ($success) {
            return $this->recordOperationSuccess(
                $operationId,
                $operation['operation'],
                $operation['start_time'],
                $operation['start_memory'],
                $operation['start_queries'],
                $operation['context']
            );
        } else {
            return $this->recordOperationError(
                $operationId,
                $operation['operation'],
                $exception,
                $operation['start_time'],
                $operation['start_memory'],
                $operation['start_queries'],
                $operation['context']
            );
        }
    }

    /**
     * Record database query performance.
     */
    public function recordDatabaseQuery(string $sql, float $duration, array $params = []): void
    {
        $queryHash = hash('xxh64', $sql);
        $isSlowQuery = $duration > (self::SLOW_QUERY_THRESHOLD / 1000);

        $queryMetric = [
            'sql_hash' => $queryHash,
            'duration_ms' => round($duration * 1000, 3),
            'slow_query' => $isSlowQuery,
            'parameter_count' => count($params),
            'timestamp' => microtime(true)
        ];

        $this->metrics['database_queries'][] = $queryMetric;

        // Log slow queries for investigation
        if ($isSlowQuery) {
            $this->logger->warning('Slow database query detected', [
                'sql' => $sql,
                'duration_ms' => $queryMetric['duration_ms'],
                'params' => $params
            ]);

            $this->checkSlowQueryAlert($queryMetric['duration_ms']);
        }

        $this->storeMetric('query_performance', $queryMetric);
    }

    /**
     * Record cache performance metrics.
     */
    public function recordCacheOperation(string $operation, string $key, bool $hit, float $duration, ?string $level = null): void
    {
        $cacheMetric = [
            'operation' => $operation,
            'key_hash' => hash('xxh64', $key),
            'hit' => $hit,
            'cache_level' => $level,
            'duration_ms' => round($duration * 1000, 3),
            'timestamp' => microtime(true)
        ];

        $this->metrics['cache_operations'][] = $cacheMetric;

        $this->storeMetric('cache_performance', $cacheMetric);

        // Update hit ratio statistics
        $this->updateCacheHitRatio($hit);
    }

    /**
     * Record memory usage snapshot.
     */
    public function recordMemoryUsage(string $operation, int $memoryUsage, int $peakMemory = null): void
    {
        $memoryMetric = [
            'operation' => $operation,
            'memory_usage_bytes' => $memoryUsage,
            'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'peak_memory_bytes' => $peakMemory ?? memory_get_peak_usage(true),
            'peak_memory_mb' => round(($peakMemory ?? memory_get_peak_usage(true)) / 1024 / 1024, 2),
            'timestamp' => microtime(true)
        ];

        $this->metrics['memory_usage'][] = $memoryMetric;

        // Check for high memory usage
        if ($memoryUsage > self::HIGH_MEMORY_THRESHOLD) {
            $this->logger->warning('High memory usage detected', $memoryMetric);
            $this->checkHighMemoryAlert($memoryUsage);
        }

        $this->storeMetric('memory_performance', $memoryMetric);
    }

    /**
     * Get current performance statistics.
     */
    public function getPerformanceStats(string $timeframe = '1h'): array
    {
        $startTime = $this->getTimeframeStart($timeframe);
        
        return [
            'timeframe' => $timeframe,
            'start_time' => $startTime,
            'current_time' => microtime(true),
            'operations' => $this->getOperationStats($startTime),
            'database' => $this->getDatabaseStats($startTime),
            'cache' => $this->getCacheStats($startTime),
            'memory' => $this->getMemoryStats($startTime),
            'alerts' => $this->getRecentAlerts($startTime)
        ];
    }

    /**
     * Generate performance report.
     */
    public function generatePerformanceReport(string $format = 'array'): array|string
    {
        $stats = $this->getPerformanceStats('24h');
        
        $report = [
            'executive_summary' => $this->generateExecutiveSummary($stats),
            'detailed_metrics' => $stats,
            'recommendations' => $this->generateRecommendations($stats),
            'trends' => $this->analyzeTrends($stats),
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        return $format === 'json' ? json_encode($report, JSON_PRETTY_PRINT) : $report;
    }

    /**
     * Set custom alert thresholds.
     */
    public function setAlertThresholds(array $thresholds): void
    {
        $this->alertThresholds = array_merge($this->alertThresholds, $thresholds);
        
        $this->logger->info('Performance alert thresholds updated', [
            'thresholds' => $this->alertThresholds
        ]);
    }

    /**
     * Record successful operation completion.
     */
    private function recordOperationSuccess(
        string $operationId,
        string $operation,
        float $startTime,
        int $memoryStart,
        int $queryCountStart,
        array $context
    ): array {
        $endTime = microtime(true);
        $memoryEnd = memory_get_usage(true);
        $queryCountEnd = $this->getDatabaseQueryCount();
        
        $metrics = [
            'operation_id' => $operationId,
            'operation' => $operation,
            'success' => true,
            'duration_ms' => round(($endTime - $startTime) * 1000, 3),
            'memory_used_bytes' => $memoryEnd - $memoryStart,
            'memory_used_mb' => round(($memoryEnd - $memoryStart) / 1024 / 1024, 2),
            'database_queries' => $queryCountEnd - $queryCountStart,
            'context' => $context,
            'timestamp' => $endTime
        ];

        $this->metrics['operations'][] = $metrics;

        // Check for performance alerts
        $this->checkPerformanceAlerts($metrics);

        $this->logger->info('Operation completed successfully', $metrics);

        $this->storeMetric('operation_performance', $metrics);

        return $metrics;
    }

    /**
     * Record operation error.
     */
    private function recordOperationError(
        string $operationId,
        string $operation,
        ?\Exception $exception,
        float $startTime,
        int $memoryStart,
        int $queryCountStart,
        array $context
    ): array {
        $endTime = microtime(true);
        $memoryEnd = memory_get_usage(true);
        $queryCountEnd = $this->getDatabaseQueryCount();
        
        $metrics = [
            'operation_id' => $operationId,
            'operation' => $operation,
            'success' => false,
            'duration_ms' => round(($endTime - $startTime) * 1000, 3),
            'memory_used_bytes' => $memoryEnd - $memoryStart,
            'memory_used_mb' => round(($memoryEnd - $memoryStart) / 1024 / 1024, 2),
            'database_queries' => $queryCountEnd - $queryCountStart,
            'error_message' => $exception?->getMessage(),
            'error_class' => $exception ? get_class($exception) : null,
            'context' => $context,
            'timestamp' => $endTime
        ];

        $this->metrics['operations'][] = $metrics;
        $this->metrics['errors'][] = $metrics;

        $this->logger->error('Operation failed', $metrics);

        $this->storeMetric('operation_errors', $metrics);

        return $metrics;
    }

    /**
     * Get database query count (placeholder - would integrate with Doctrine).
     */
    private function getDatabaseQueryCount(): int
    {
        // In a real implementation, this would integrate with Doctrine's SQL logger
        // For now, return a mock value
        return 0;
    }

    /**
     * Initialize default alert thresholds.
     */
    private function initializeAlertThresholds(): void
    {
        $this->alertThresholds = [
            'response_time_warning' => 1000, // 1 second
            'response_time_critical' => 2000, // 2 seconds
            'memory_usage_warning' => 256 * 1024 * 1024, // 256MB
            'memory_usage_critical' => 512 * 1024 * 1024, // 512MB
            'database_queries_warning' => 20,
            'database_queries_critical' => 50,
            'cache_hit_ratio_warning' => 80, // 80%
            'cache_hit_ratio_critical' => 60, // 60%
            'slow_query_threshold' => 100, // 100ms
        ];
    }

    /**
     * Check for performance-related alerts.
     */
    private function checkPerformanceAlerts(array $metrics): void
    {
        // Response time alerts
        if ($metrics['duration_ms'] > $this->alertThresholds['response_time_critical']) {
            $this->triggerAlert('CRITICAL', 'Response time exceeded critical threshold', $metrics);
        } elseif ($metrics['duration_ms'] > $this->alertThresholds['response_time_warning']) {
            $this->triggerAlert('WARNING', 'Response time exceeded warning threshold', $metrics);
        }

        // Memory usage alerts
        if ($metrics['memory_used_bytes'] > $this->alertThresholds['memory_usage_critical']) {
            $this->triggerAlert('CRITICAL', 'Memory usage exceeded critical threshold', $metrics);
        } elseif ($metrics['memory_used_bytes'] > $this->alertThresholds['memory_usage_warning']) {
            $this->triggerAlert('WARNING', 'Memory usage exceeded warning threshold', $metrics);
        }

        // Database query alerts
        if ($metrics['database_queries'] > $this->alertThresholds['database_queries_critical']) {
            $this->triggerAlert('CRITICAL', 'Database query count exceeded critical threshold', $metrics);
        } elseif ($metrics['database_queries'] > $this->alertThresholds['database_queries_warning']) {
            $this->triggerAlert('WARNING', 'Database query count exceeded warning threshold', $metrics);
        }
    }

    /**
     * Check slow query alert.
     */
    private function checkSlowQueryAlert(float $durationMs): void
    {
        if ($durationMs > $this->alertThresholds['slow_query_threshold']) {
            $this->triggerAlert('WARNING', 'Slow database query detected', [
                'duration_ms' => $durationMs,
                'threshold_ms' => $this->alertThresholds['slow_query_threshold']
            ]);
        }
    }

    /**
     * Check high memory alert.
     */
    private function checkHighMemoryAlert(int $memoryUsage): void
    {
        if ($memoryUsage > $this->alertThresholds['memory_usage_critical']) {
            $this->triggerAlert('CRITICAL', 'High memory usage detected', [
                'memory_usage_bytes' => $memoryUsage,
                'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2)
            ]);
        }
    }

    /**
     * Trigger performance alert.
     */
    private function triggerAlert(string $level, string $message, array $context): void
    {
        $alert = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true),
            'alert_id' => uniqid('alert_')
        ];

        $this->metrics['alerts'][] = $alert;

        $this->logger->log(
            $level === 'CRITICAL' ? 'critical' : 'warning',
            "Performance Alert: {$message}",
            $alert
        );

        $this->storeMetric('performance_alerts', $alert);
    }

    /**
     * Store metric in persistent storage.
     */
    private function storeMetric(string $type, array $metric): void
    {
        try {
            $key = "performance_metrics:{$type}:" . date('Y-m-d-H');
            $item = $this->cacheAdapter->getItem($key);
            
            $metrics = $item->isHit() ? $item->get() : [];
            $metrics[] = $metric;
            
            $item->set($metrics);
            $item->expiresAfter(self::METRIC_TTL);
            
            $this->cacheAdapter->save($item);
        } catch (\Exception $e) {
            $this->logger->error('Failed to store performance metric', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update cache hit ratio statistics.
     */
    private function updateCacheHitRatio(bool $hit): void
    {
        $key = 'cache_hit_ratio:' . date('Y-m-d-H');
        
        try {
            $item = $this->cacheAdapter->getItem($key);
            $data = $item->isHit() ? $item->get() : ['hits' => 0, 'total' => 0];
            
            $data['total']++;
            if ($hit) {
                $data['hits']++;
            }
            
            $data['ratio'] = $data['total'] > 0 ? ($data['hits'] / $data['total']) * 100 : 0;
            
            $item->set($data);
            $item->expiresAfter(self::METRIC_TTL);
            $this->cacheAdapter->save($item);

            // Check cache hit ratio alerts
            if ($data['ratio'] < $this->alertThresholds['cache_hit_ratio_critical']) {
                $this->triggerAlert('CRITICAL', 'Cache hit ratio below critical threshold', $data);
            } elseif ($data['ratio'] < $this->alertThresholds['cache_hit_ratio_warning']) {
                $this->triggerAlert('WARNING', 'Cache hit ratio below warning threshold', $data);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to update cache hit ratio', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get timeframe start timestamp.
     */
    private function getTimeframeStart(string $timeframe): float
    {
        return match($timeframe) {
            '1h' => microtime(true) - 3600,
            '24h' => microtime(true) - 86400,
            '7d' => microtime(true) - 604800,
            '30d' => microtime(true) - 2592000,
            default => microtime(true) - 3600
        };
    }

    /**
     * Generate executive summary.
     */
    private function generateExecutiveSummary(array $stats): array
    {
        return [
            'total_operations' => count($stats['operations'] ?? []),
            'success_rate' => $this->calculateSuccessRate($stats['operations'] ?? []),
            'avg_response_time_ms' => $this->calculateAverageResponseTime($stats['operations'] ?? []),
            'cache_hit_ratio' => $stats['cache']['hit_ratio'] ?? 0,
            'memory_efficiency' => $this->calculateMemoryEfficiency($stats['memory'] ?? []),
            'performance_grade' => $this->calculatePerformanceGrade($stats)
        ];
    }

    /**
     * Generate performance recommendations.
     */
    private function generateRecommendations(array $stats): array
    {
        $recommendations = [];

        // Analyze response times
        $avgResponseTime = $this->calculateAverageResponseTime($stats['operations'] ?? []);
        if ($avgResponseTime > 1000) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'category' => 'Response Time',
                'issue' => 'Average response time exceeds 1 second',
                'recommendation' => 'Implement database query optimization and enable multi-level caching',
                'impact' => 'User experience improvement'
            ];
        }

        // Analyze cache performance
        $cacheHitRatio = $stats['cache']['hit_ratio'] ?? 0;
        if ($cacheHitRatio < 80) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'category' => 'Caching',
                'issue' => 'Cache hit ratio below optimal threshold',
                'recommendation' => 'Review cache warming strategies and increase cache TTL for stable data',
                'impact' => 'Reduced database load and faster response times'
            ];
        }

        return $recommendations;
    }

    /**
     * Analyze performance trends.
     */
    private function analyzeTrends(array $stats): array
    {
        // This would analyze trends over time
        // For now, return placeholder data
        return [
            'response_time_trend' => 'stable',
            'memory_usage_trend' => 'increasing',
            'cache_performance_trend' => 'improving',
            'error_rate_trend' => 'decreasing'
        ];
    }

    /**
     * Calculate success rate.
     */
    private function calculateSuccessRate(array $operations): float
    {
        if (empty($operations)) {
            return 100.0;
        }

        $successCount = count(array_filter($operations, fn($op) => $op['success'] ?? true));
        return round(($successCount / count($operations)) * 100, 2);
    }

    /**
     * Calculate average response time.
     */
    private function calculateAverageResponseTime(array $operations): float
    {
        if (empty($operations)) {
            return 0.0;
        }

        $totalTime = array_sum(array_column($operations, 'duration_ms'));
        return round($totalTime / count($operations), 2);
    }

    /**
     * Calculate memory efficiency.
     */
    private function calculateMemoryEfficiency(array $memoryStats): float
    {
        // Placeholder calculation - would be more sophisticated in reality
        return 85.0;
    }

    /**
     * Calculate overall performance grade.
     */
    private function calculatePerformanceGrade(array $stats): string
    {
        $score = 0;
        $maxScore = 400;

        // Response time score (100 points max)
        $avgResponseTime = $this->calculateAverageResponseTime($stats['operations'] ?? []);
        $score += max(0, 100 - ($avgResponseTime / 10));

        // Success rate score (100 points max)
        $successRate = $this->calculateSuccessRate($stats['operations'] ?? []);
        $score += $successRate;

        // Cache hit ratio score (100 points max)
        $cacheHitRatio = $stats['cache']['hit_ratio'] ?? 0;
        $score += $cacheHitRatio;

        // Memory efficiency score (100 points max)
        $memoryEfficiency = $this->calculateMemoryEfficiency($stats['memory'] ?? []);
        $score += $memoryEfficiency;

        $percentage = ($score / $maxScore) * 100;

        return match(true) {
            $percentage >= 90 => 'A+',
            $percentage >= 80 => 'A',
            $percentage >= 70 => 'B',
            $percentage >= 60 => 'C',
            $percentage >= 50 => 'D',
            default => 'F'
        };
    }

    /**
     * Get operation statistics.
     */
    private function getOperationStats(float $startTime): array
    {
        $operations = array_filter($this->metrics['operations'] ?? [], 
            fn($op) => $op['timestamp'] >= $startTime);

        return [
            'total_operations' => count($operations),
            'successful_operations' => count(array_filter($operations, fn($op) => $op['success'])),
            'failed_operations' => count(array_filter($operations, fn($op) => !$op['success'])),
            'avg_response_time_ms' => $this->calculateAverageResponseTime($operations),
            'operations' => $operations
        ];
    }

    /**
     * Get database statistics.
     */
    private function getDatabaseStats(float $startTime): array
    {
        $queries = array_filter($this->metrics['database_queries'] ?? [], 
            fn($q) => $q['timestamp'] >= $startTime);

        return [
            'total_queries' => count($queries),
            'slow_queries' => count(array_filter($queries, fn($q) => $q['slow_query'])),
            'avg_query_time_ms' => count($queries) > 0 ? 
                array_sum(array_column($queries, 'duration_ms')) / count($queries) : 0,
            'queries' => $queries
        ];
    }

    /**
     * Get cache statistics.
     */
    private function getCacheStats(float $startTime): array
    {
        $operations = array_filter($this->metrics['cache_operations'] ?? [], 
            fn($op) => $op['timestamp'] >= $startTime);

        $hits = count(array_filter($operations, fn($op) => $op['hit']));
        $total = count($operations);

        return [
            'total_operations' => $total,
            'cache_hits' => $hits,
            'cache_misses' => $total - $hits,
            'hit_ratio' => $total > 0 ? round(($hits / $total) * 100, 2) : 0,
            'operations' => $operations
        ];
    }

    /**
     * Get memory statistics.
     */
    private function getMemoryStats(float $startTime): array
    {
        $memoryData = array_filter($this->metrics['memory_usage'] ?? [], 
            fn($m) => $m['timestamp'] >= $startTime);

        return [
            'total_measurements' => count($memoryData),
            'avg_memory_usage_mb' => count($memoryData) > 0 ? 
                array_sum(array_column($memoryData, 'memory_usage_mb')) / count($memoryData) : 0,
            'peak_memory_usage_mb' => count($memoryData) > 0 ? 
                max(array_column($memoryData, 'memory_usage_mb')) : 0,
            'measurements' => $memoryData
        ];
    }

    /**
     * Get recent alerts.
     */
    private function getRecentAlerts(float $startTime): array
    {
        return array_filter($this->metrics['alerts'] ?? [], 
            fn($alert) => $alert['timestamp'] >= $startTime);
    }
}