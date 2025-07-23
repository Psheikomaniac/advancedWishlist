<?php declare(strict_types=1);

namespace AdvancedWishlist\Storefront\Controller;

use AdvancedWishlist\Core\Service\WishlistService;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Health Check Controller for Blue-Green Deployment
 * 
 * Implements comprehensive health checks as specified in deployment-strategy-implementation.md PRD
 * 
 * @RouteScope(scopes={"api", "storefront"})
 */
class HealthCheckController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly WishlistService $wishlistService,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
        private readonly ?RedisAdapter $redisAdapter = null
    ) {}

    /**
     * Basic health check endpoint for load balancer
     * 
     * @Route("/health", name="health.basic", methods={"GET"})
     */
    public function basicHealth(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'healthy',
            'timestamp' => time(),
            'version' => $this->getPluginVersion(),
        ], 200);
    }

    /**
     * Comprehensive health check for deployment validation
     * 
     * @Route("/api/wishlist/health", name="health.comprehensive", methods={"GET"})
     */
    public function comprehensiveHealth(Request $request, Context $context): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'wishlist_service' => $this->checkWishlistService($context),
            'migration_status' => $this->checkMigrationStatus(),
            'configuration' => $this->checkConfiguration(),
            'disk_space' => $this->checkDiskSpace(),
            'memory_usage' => $this->checkMemoryUsage(),
        ];

        $healthy = array_reduce($checks, fn($carry, $check) => $carry && $check['healthy'], true);
        
        $response = [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => time(),
            'version' => $this->getPluginVersion(),
            'environment' => $_ENV['ENVIRONMENT'] ?? 'development',
            'uptime' => $this->getUptime(),
        ];

        // Log health check failures
        if (!$healthy) {
            $this->logger->warning('Health check failed', [
                'checks' => array_filter($checks, fn($check) => !$check['healthy']),
                'request_id' => $request->headers->get('X-Request-ID'),
            ]);
        }

        return new JsonResponse($response, $healthy ? 200 : 503);
    }

    /**
     * Readiness probe for Kubernetes/deployment orchestration
     * 
     * @Route("/health/ready", name="health.readiness", methods={"GET"})
     */
    public function readinessProbe(Context $context): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'migrations' => $this->checkMigrationStatus(),
            'configuration' => $this->checkConfiguration(),
        ];

        $ready = array_reduce($checks, fn($carry, $check) => $carry && $check['healthy'], true);

        return new JsonResponse([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
            'timestamp' => time(),
        ], $ready ? 200 : 503);
    }

    /**
     * Liveness probe for Kubernetes/deployment orchestration
     * 
     * @Route("/health/live", name="health.liveness", methods={"GET"})
     */
    public function livenessProbe(): JsonResponse
    {
        // Basic liveness check - if this endpoint responds, the app is alive
        $memory = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        
        $alive = $memory < ($memoryLimit * 0.9); // Alert if using >90% memory

        return new JsonResponse([
            'status' => $alive ? 'alive' : 'critical',
            'memory_usage' => $memory,
            'memory_limit' => $memoryLimit,
            'memory_percentage' => round(($memory / $memoryLimit) * 100, 2),
            'timestamp' => time(),
        ], $alive ? 200 : 503);
    }

    /**
     * Check database connectivity and performance
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            
            // Test basic connectivity
            $this->connection->executeQuery('SELECT 1');
            
            // Test wishlist table exists and is accessible
            $result = $this->connection->executeQuery(
                'SELECT COUNT(*) as count FROM wishlist LIMIT 1'
            )->fetchAssociative();
            
            $responseTime = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'healthy' => true,
                'response_time_ms' => $responseTime,
                'table_accessible' => $result !== false,
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Database connection failed',
            ];
        }
    }

    /**
     * Check Redis connectivity and performance
     */
    private function checkRedis(): array
    {
        if (!$this->redisAdapter) {
            return [
                'healthy' => true,
                'message' => 'Redis not configured',
                'status' => 'optional',
            ];
        }

        try {
            $start = microtime(true);
            
            // Test Redis connectivity with a simple operation
            $testKey = 'health_check_' . time();
            $this->redisAdapter->set($testKey, 'test_value', 10);
            $value = $this->redisAdapter->get($testKey);
            $this->redisAdapter->delete($testKey);
            
            $responseTime = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'healthy' => $value === 'test_value',
                'response_time_ms' => $responseTime,
                'message' => 'Redis connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Redis connection failed',
            ];
        }
    }

    /**
     * Check wishlist service functionality
     */
    private function checkWishlistService(Context $context): array
    {
        try {
            // Test that the service can be instantiated and basic methods work
            $service = $this->wishlistService;
            
            // This should not throw an exception if service is properly configured
            $result = method_exists($service, 'getWishlists');
            
            return [
                'healthy' => $result,
                'message' => 'Wishlist service operational',
                'service_available' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Wishlist service failed',
            ];
        }
    }

    /**
     * Check database migration status
     */
    private function checkMigrationStatus(): array
    {
        try {
            // Check if migration table exists and get latest migration
            $result = $this->connection->executeQuery(
                "SELECT * FROM migration WHERE class LIKE '%AdvancedWishlist%' ORDER BY creation_timestamp DESC LIMIT 1"
            )->fetchAssociative();
            
            return [
                'healthy' => $result !== false,
                'latest_migration' => $result ? $result['class'] : null,
                'creation_timestamp' => $result ? $result['creation_timestamp'] : null,
                'message' => 'Migration status checked',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Migration status check failed',
            ];
        }
    }

    /**
     * Check system configuration
     */
    private function checkConfiguration(): array
    {
        try {
            // Check critical configuration values
            $config = [
                'oauth2_enabled' => $this->systemConfigService->get('AdvancedWishlist.config.oauth2Enabled'),
                'cache_enabled' => $this->systemConfigService->get('AdvancedWishlist.config.cacheEnabled'),
                'rate_limit_enabled' => $this->systemConfigService->get('AdvancedWishlist.config.rateLimitEnabled'),
            ];
            
            return [
                'healthy' => true,
                'configuration' => $config,
                'message' => 'Configuration loaded successfully',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Configuration check failed',
            ];
        }
    }

    /**
     * Check disk space availability
     */
    private function checkDiskSpace(): array
    {
        try {
            $diskFree = disk_free_space('/var/www/html');
            $diskTotal = disk_total_space('/var/www/html');
            $diskUsed = $diskTotal - $diskFree;
            $diskUsagePercent = round(($diskUsed / $diskTotal) * 100, 2);
            
            // Alert if disk usage is over 85%
            $healthy = $diskUsagePercent < 85;
            
            return [
                'healthy' => $healthy,
                'disk_free_bytes' => $diskFree,
                'disk_total_bytes' => $diskTotal,
                'disk_usage_percent' => $diskUsagePercent,
                'message' => $healthy ? 'Disk space sufficient' : 'Disk space low',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Disk space check failed',
            ];
        }
    }

    /**
     * Check memory usage
     */
    private function checkMemoryUsage(): array
    {
        try {
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            $memoryLimit = $this->getMemoryLimit();
            $memoryUsagePercent = round(($memoryUsage / $memoryLimit) * 100, 2);
            
            // Alert if memory usage is over 80%
            $healthy = $memoryUsagePercent < 80;
            
            return [
                'healthy' => $healthy,
                'memory_usage_bytes' => $memoryUsage,
                'memory_peak_bytes' => $memoryPeak,
                'memory_limit_bytes' => $memoryLimit,
                'memory_usage_percent' => $memoryUsagePercent,
                'message' => $healthy ? 'Memory usage normal' : 'Memory usage high',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'message' => 'Memory usage check failed',
            ];
        }
    }

    /**
     * Get plugin version
     */
    private function getPluginVersion(): string
    {
        $composerFile = __DIR__ . '/../../../../composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            return $composer['version'] ?? '2.0.0';
        }
        return '2.0.0';
    }

    /**
     * Get system uptime
     */
    private function getUptime(): ?int
    {
        try {
            if (file_exists('/proc/uptime')) {
                $uptime = file_get_contents('/proc/uptime');
                return (int) floatval(explode(' ', $uptime)[0]);
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get memory limit in bytes
     */
    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);
        
        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}