<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Performance;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Connection;

/**
 * Event subscriber for performance monitoring
 */
class PerformanceMonitoringSubscriber implements EventSubscriberInterface
{
    /**
     * @var DebugStack|null SQL logger
     */
    private ?DebugStack $sqlLogger = null;
    
    public function __construct(
        private readonly PerformanceMonitoringService $performanceMonitoring,
        private readonly Connection $connection,
        private readonly bool $enableSqlLogging = true
    ) {
        // Initialize SQL logger if enabled
        if ($this->enableSqlLogging) {
            $this->sqlLogger = new DebugStack();
            $this->connection->getConfiguration()->setSQLLogger($this->sqlLogger);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256], // High priority to start as early as possible
            KernelEvents::RESPONSE => ['onKernelResponse', -256], // Low priority to end as late as possible
            KernelEvents::TERMINATE => ['onKernelTerminate', -256], // Process SQL queries after response is sent
        ];
    }
    
    /**
     * Handle request event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $this->performanceMonitoring->onKernelRequest($event);
    }
    
    /**
     * Handle response event
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $this->performanceMonitoring->onKernelResponse($event);
    }
    
    /**
     * Process SQL queries after response is sent
     */
    public function onKernelTerminate(): void
    {
        // Process SQL queries if logging is enabled
        if ($this->sqlLogger !== null) {
            $this->processSqlQueries();
        }
    }
    
    /**
     * Process and track SQL queries
     */
    private function processSqlQueries(): void
    {
        if (!$this->sqlLogger || empty($this->sqlLogger->queries)) {
            return;
        }
        
        foreach ($this->sqlLogger->queries as $query) {
            // Skip queries with no execution time (not executed)
            if (!isset($query['executionMS'])) {
                continue;
            }
            
            $this->performanceMonitoring->trackDatabaseQuery(
                $query['sql'] ?? 'Unknown SQL',
                $query['executionMS'],
                $query['params'] ?? []
            );
        }
    }
}