<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Performance;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Service for monitoring and tracking performance metrics
 */
class PerformanceMonitoringService
{
    /**
     * @var array<string, array<string, mixed>> Performance metrics storage
     */
    private array $metrics = [];
    
    /**
     * @var array<string, float> Start times for operations
     */
    private array $startTimes = [];
    
    /**
     * @var array<string, int> Counter for operation types
     */
    private array $counters = [];
    
    /**
     * @var array<string, array<float>> Execution times for calculating averages
     */
    private array $executionTimes = [];
    
    /**
     * @var float|null Request start time
     */
    private ?float $requestStartTime = null;
    
    /**
     * @var int Memory usage threshold for logging (in bytes)
     */
    private int $memoryThreshold;
    
    /**
     * @var float Execution time threshold for logging (in seconds)
     */
    private float $timeThreshold;
    
    /**
     * @var bool Whether to collect detailed metrics
     */
    private bool $detailedMetrics;
    
    /**
     * @var string|null Current transaction ID
     */
    private ?string $currentTransactionId = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
        int $memoryThreshold = 5 * 1024 * 1024, // 5MB
        float $timeThreshold = 1.0, // 1 second
        bool $detailedMetrics = true
    ) {
        $this->memoryThreshold = $memoryThreshold;
        $this->timeThreshold = $timeThreshold;
        $this->detailedMetrics = $detailedMetrics;
    }
    
    /**
     * Start tracking request performance
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        
        $this->requestStartTime = microtime(true);
        $this->currentTransactionId = uniqid('txn_', true);
        
        $request = $event->getRequest();
        $this->logger->debug('Request started', [
            'transaction_id' => $this->currentTransactionId,
            'uri' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'memory_start' => $this->formatBytes(memory_get_usage(true))
        ]);
    }
    
    /**
     * Finish tracking request performance
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $this->requestStartTime === null) {
            return;
        }
        
        $request = $event->getRequest();
        $response = $event->getResponse();
        $totalTime = microtime(true) - $this->requestStartTime;
        
        $metrics = [
            'transaction_id' => $this->currentTransactionId,
            'uri' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'status_code' => $response->getStatusCode(),
            'execution_time' => round($totalTime, 4),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'memory_final' => $this->formatBytes(memory_get_usage(true))
        ];
        
        // Add detailed metrics if enabled
        if ($this->detailedMetrics && !empty($this->metrics)) {
            $metrics['operations'] = $this->metrics;
            $metrics['counters'] = $this->counters;
            
            // Calculate averages
            $averages = [];
            foreach ($this->executionTimes as $operation => $times) {
                $averages[$operation] = [
                    'avg' => round(array_sum($times) / count($times), 4),
                    'min' => round(min($times), 4),
                    'max' => round(max($times), 4),
                    'count' => count($times)
                ];
            }
            $metrics['averages'] = $averages;
        }
        
        // Log slow requests
        if ($totalTime > $this->timeThreshold) {
            $this->logger->warning('Slow request detected', $metrics);
        } else {
            $this->logger->debug('Request completed', $metrics);
        }
        
        // Reset metrics for next request
        $this->resetMetrics();
    }
    
    /**
     * Start timing an operation
     */
    public function startOperation(string $operation, array $context = []): string
    {
        $id = $operation . '_' . uniqid('', true);
        $this->startTimes[$id] = microtime(true);
        
        // Initialize counter if not exists
        if (!isset($this->counters[$operation])) {
            $this->counters[$operation] = 0;
        }
        
        // Increment counter
        $this->counters[$operation]++;
        
        // Store context
        $this->metrics[$id] = [
            'operation' => $operation,
            'started_at' => date('Y-m-d H:i:s'),
            'context' => $context,
            'transaction_id' => $this->currentTransactionId
        ];
        
        return $id;
    }
    
    /**
     * End timing an operation and record metrics
     */
    public function endOperation(string $id, array $additionalContext = []): float
    {
        if (!isset($this->startTimes[$id])) {
            $this->logger->warning('Attempted to end unknown operation', [
                'operation_id' => $id,
                'transaction_id' => $this->currentTransactionId
            ]);
            return 0.0;
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $this->startTimes[$id];
        $memoryUsage = memory_get_usage(true);
        
        // Store execution time for averages
        $operation = $this->metrics[$id]['operation'] ?? 'unknown';
        if (!isset($this->executionTimes[$operation])) {
            $this->executionTimes[$operation] = [];
        }
        $this->executionTimes[$operation][] = $executionTime;
        
        // Update metrics
        $this->metrics[$id] = array_merge($this->metrics[$id], [
            'execution_time' => round($executionTime, 4),
            'memory_usage' => $this->formatBytes($memoryUsage),
            'additional_context' => $additionalContext
        ]);
        
        // Log high memory usage
        if ($memoryUsage > $this->memoryThreshold) {
            $this->logger->warning('High memory usage detected', [
                'operation' => $operation,
                'memory_usage' => $this->formatBytes($memoryUsage),
                'execution_time' => round($executionTime, 4),
                'transaction_id' => $this->currentTransactionId
            ]);
        }
        
        // Log slow operations
        if ($executionTime > $this->timeThreshold) {
            $this->logger->warning('Slow operation detected', [
                'operation' => $operation,
                'execution_time' => round($executionTime, 4),
                'memory_usage' => $this->formatBytes($memoryUsage),
                'transaction_id' => $this->currentTransactionId
            ]);
        }
        
        // Clean up
        unset($this->startTimes[$id]);
        
        return $executionTime;
    }
    
    /**
     * Track a database query
     */
    public function trackDatabaseQuery(string $sql, float $executionTime, array $params = []): void
    {
        $id = 'db_query_' . uniqid('', true);
        
        // Initialize counter if not exists
        if (!isset($this->counters['database_query'])) {
            $this->counters['database_query'] = 0;
        }
        
        // Increment counter
        $this->counters['database_query']++;
        
        // Store execution time for averages
        if (!isset($this->executionTimes['database_query'])) {
            $this->executionTimes['database_query'] = [];
        }
        $this->executionTimes['database_query'][] = $executionTime;
        
        // Store metrics
        $this->metrics[$id] = [
            'operation' => 'database_query',
            'execution_time' => round($executionTime, 4),
            'sql' => $this->truncateSql($sql),
            'params' => $params,
            'transaction_id' => $this->currentTransactionId
        ];
        
        // Log slow queries
        if ($executionTime > $this->timeThreshold) {
            $this->logger->warning('Slow database query detected', [
                'execution_time' => round($executionTime, 4),
                'sql' => $this->truncateSql($sql),
                'transaction_id' => $this->currentTransactionId
            ]);
        }
    }
    
    /**
     * Track cache operations
     */
    public function trackCacheOperation(string $operation, string $key, bool $hit, float $executionTime): void
    {
        $id = 'cache_' . $operation . '_' . uniqid('', true);
        
        // Initialize counter if not exists
        $counterKey = 'cache_' . $operation;
        if (!isset($this->counters[$counterKey])) {
            $this->counters[$counterKey] = 0;
        }
        
        // Increment counter
        $this->counters[$counterKey]++;
        
        // Initialize hit/miss counters
        $hitMissKey = 'cache_' . ($hit ? 'hit' : 'miss');
        if (!isset($this->counters[$hitMissKey])) {
            $this->counters[$hitMissKey] = 0;
        }
        
        // Increment hit/miss counter
        $this->counters[$hitMissKey]++;
        
        // Store execution time for averages
        if (!isset($this->executionTimes[$counterKey])) {
            $this->executionTimes[$counterKey] = [];
        }
        $this->executionTimes[$counterKey][] = $executionTime;
        
        // Store metrics
        $this->metrics[$id] = [
            'operation' => 'cache_' . $operation,
            'key' => $key,
            'hit' => $hit,
            'execution_time' => round($executionTime, 4),
            'transaction_id' => $this->currentTransactionId
        ];
    }
    
    /**
     * Get current performance metrics
     */
    public function getMetrics(): array
    {
        return [
            'metrics' => $this->metrics,
            'counters' => $this->counters,
            'execution_times' => $this->executionTimes,
            'transaction_id' => $this->currentTransactionId
        ];
    }
    
    /**
     * Reset all metrics
     */
    private function resetMetrics(): void
    {
        $this->metrics = [];
        $this->startTimes = [];
        $this->counters = [];
        $this->executionTimes = [];
        $this->requestStartTime = null;
        $this->currentTransactionId = null;
    }
    
    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Truncate SQL query for logging
     */
    private function truncateSql(string $sql, int $maxLength = 500): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        
        if (strlen($sql) <= $maxLength) {
            return $sql;
        }
        
        return substr($sql, 0, $maxLength) . '...';
    }
}