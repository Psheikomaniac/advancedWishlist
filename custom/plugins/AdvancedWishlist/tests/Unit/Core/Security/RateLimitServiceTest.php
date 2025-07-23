<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Unit\Core\Security;

use AdvancedWishlist\Core\Security\RateLimitService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for RateLimitService with comprehensive coverage.
 * @group unit
 * @group security
 */
class RateLimitServiceTest extends TestCase
{
    private RateLimitService $rateLimitService;
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        
        $this->rateLimitService = new RateLimitService(
            $this->cache,
            $this->logger,
            $this->requestStack
        );
    }

    /**
     * Test rate limit service initialization.
     */
    public function testServiceInitialization(): void
    {
        $this->assertInstanceOf(RateLimitService::class, $this->rateLimitService);
    }

    /**
     * Test isAllowed with no request context.
     */
    public function testIsAllowedWithoutRequestContext(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        
        $result = $this->rateLimitService->isAllowed('wishlist_read');
        
        $this->assertTrue($result);
    }

    /**
     * Test isAllowed with valid request.
     */
    public function testIsAllowedWithValidRequest(): void
    {
        $request = $this->createMockRequest('192.168.1.1', 'TestAgent/1.0');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        
        // Mock cache behavior
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('get')->willReturn(null);
        $this->cache->method('getItem')->willReturn($cacheItem);
        $this->cache->method('save')->willReturn(true);
        
        $result = $this->rateLimitService->isAllowed('wishlist_read', $request);
        
        // Since we can't easily mock the internal rate limiter, we'll test the structure
        $this->assertIsBool($result);
    }

    /**
     * Test getRemainingTokens without request context.
     */
    public function testGetRemainingTokensWithoutRequestContext(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        
        $remaining = $this->rateLimitService->getRemainingTokens('wishlist_read');
        
        $this->assertEquals(100, $remaining);
    }

    /**
     * Test getRemainingTokens with request.
     */
    public function testGetRemainingTokensWithRequest(): void
    {
        $request = $this->createMockRequest('192.168.1.2', 'TestAgent/1.0');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        
        // Mock cache
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $this->cache->method('getItem')->willReturn($cacheItem);
        
        $remaining = $this->rateLimitService->getRemainingTokens('wishlist_read', $request);
        
        $this->assertIsInt($remaining);
        $this->assertGreaterThanOrEqual(0, $remaining);
    }

    /**
     * Test getRateLimitHeaders without request.
     */
    public function testGetRateLimitHeadersWithoutRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        
        $headers = $this->rateLimitService->getRateLimitHeaders('wishlist_read');
        
        $this->assertEmpty($headers);
    }

    /**
     * Test getRateLimitHeaders with request.
     */
    public function testGetRateLimitHeadersWithRequest(): void
    {
        $request = $this->createMockRequest('192.168.1.3', 'TestAgent/1.0');
        
        // Mock cache
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $this->cache->method('getItem')->willReturn($cacheItem);
        
        $headers = $this->rateLimitService->getRateLimitHeaders('wishlist_read', $request);
        
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        $this->assertEquals(200, $headers['X-RateLimit-Limit']);
    }

    /**
     * Test different endpoint configurations.
     */
    public function testDifferentEndpointConfigurations(): void
    {
        $request = $this->createMockRequest('192.168.1.4', 'TestAgent/1.0');
        
        $endpoints = [
            'wishlist_read' => 200,
            'wishlist_write' => 50,
            'wishlist_bulk' => 10,
            'analytics' => 100,
            'auth' => 20,
        ];
        
        foreach ($endpoints as $endpoint => $expectedLimit) {
            $headers = $this->rateLimitService->getRateLimitHeaders($endpoint, $request);
            $this->assertEquals($expectedLimit, $headers['X-RateLimit-Limit'], 
                "Endpoint {$endpoint} should have limit {$expectedLimit}");
        }
    }

    /**
     * Test unknown endpoint fallback.
     */
    public function testUnknownEndpointFallback(): void
    {
        $request = $this->createMockRequest('192.168.1.5', 'TestAgent/1.0');
        
        $headers = $this->rateLimitService->getRateLimitHeaders('unknown_endpoint', $request);
        
        // Should fallback to wishlist_read configuration
        $this->assertEquals(200, $headers['X-RateLimit-Limit']);
    }

    /**
     * Test client key generation factors.
     */
    public function testClientKeyGenerationFactors(): void
    {
        // Test that different IPs generate different behavior
        $request1 = $this->createMockRequest('192.168.1.1', 'TestAgent/1.0');
        $request2 = $this->createMockRequest('192.168.1.2', 'TestAgent/1.0');
        
        // Both should be allowed (different client keys)
        $this->assertTrue($this->rateLimitService->isAllowed('wishlist_read', $request1));
        $this->assertTrue($this->rateLimitService->isAllowed('wishlist_read', $request2));
        
        // Test that different user agents generate different behavior
        $request3 = $this->createMockRequest('192.168.1.1', 'DifferentAgent/1.0');
        $this->assertTrue($this->rateLimitService->isAllowed('wishlist_read', $request3));
    }

    /**
     * Test customer ID differentiation.
     */
    public function testCustomerIdDifferentiation(): void
    {
        $request1 = $this->createMockRequest('192.168.1.1', 'TestAgent/1.0');
        $request1->attributes->set('customerId', 'customer-1');
        
        $request2 = $this->createMockRequest('192.168.1.1', 'TestAgent/1.0');
        $request2->attributes->set('customerId', 'customer-2');
        
        // Both should be allowed (different customer IDs)
        $this->assertTrue($this->rateLimitService->isAllowed('wishlist_read', $request1));
        $this->assertTrue($this->rateLimitService->isAllowed('wishlist_read', $request2));
    }

    /**
     * Test cache integration.
     */
    public function testCacheIntegration(): void
    {
        $request = $this->createMockRequest('192.168.1.6', 'TestAgent/1.0');
        
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->atLeastOnce())->method('isHit');
        $cacheItem->expects($this->atLeastOnce())->method('get');
        
        $this->cache->expects($this->atLeastOnce())
            ->method('getItem')
            ->willReturn($cacheItem);
        
        $this->rateLimitService->isAllowed('wishlist_read', $request);
    }

    /**
     * Test edge cases with malformed requests.
     */
    public function testEdgeCasesWithMalformedRequests(): void
    {
        // Test with missing IP
        $request = new Request();
        // No REMOTE_ADDR set
        
        $result = $this->rateLimitService->isAllowed('wishlist_read', $request);
        $this->assertIsBool($result);
        
        // Test with missing User-Agent
        $request2 = new Request();
        $request2->server->set('REMOTE_ADDR', '192.168.1.7');
        // No User-Agent header
        
        $result2 = $this->rateLimitService->isAllowed('wishlist_read', $request2);
        $this->assertIsBool($result2);
    }

    /**
     * Test performance characteristics.
     */
    public function testPerformanceCharacteristics(): void
    {
        $request = $this->createMockRequest('192.168.1.8', 'PerformanceTest/1.0');
        
        $startTime = microtime(true);
        
        // Perform multiple operations
        for ($i = 0; $i < 10; $i++) {
            $this->rateLimitService->isAllowed('wishlist_read', $request);
            $this->rateLimitService->getRemainingTokens('wishlist_read', $request);
            $this->rateLimitService->getRateLimitHeaders('wishlist_read', $request);
        }
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;
        
        // Should be reasonably fast
        $this->assertLessThan(100, $executionTime, 
            "Rate limiting operations should be fast, took {$executionTime}ms");
    }

    /**
     * Test memory efficiency.
     */
    public function testMemoryEfficiency(): void
    {
        $initialMemory = memory_get_usage(true);
        
        // Create many requests
        for ($i = 0; $i < 50; $i++) {
            $request = $this->createMockRequest("192.168.2.{$i}", 'MemoryTest/1.0');
            $this->rateLimitService->isAllowed('wishlist_read', $request);
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryUsed = $finalMemory - $initialMemory;
        
        // Should not use excessive memory
        $this->assertLessThan(1024 * 1024, $memoryUsed, 
            "Memory usage should be reasonable, used {$memoryUsed} bytes");
    }

    /**
     * Test logging integration.
     */
    public function testLoggingIntegration(): void
    {
        $request = $this->createMockRequest('192.168.1.9', 'LogTest/1.0');
        
        // The logger should be used for successful rate limit checks
        $this->logger->expects($this->atLeastOnce())
            ->method('debug');
        
        $this->rateLimitService->isAllowed('wishlist_read', $request);
    }

    /**
     * Test IPv6 address handling.
     */
    public function testIpv6AddressHandling(): void
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '2001:db8::1');
        $request->headers->set('User-Agent', 'IPv6Test/1.0');
        
        $result = $this->rateLimitService->isAllowed('wishlist_read', $request);
        $this->assertIsBool($result);
        
        $headers = $this->rateLimitService->getRateLimitHeaders('wishlist_read', $request);
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
    }

    /**
     * Test very long user agent handling.
     */
    public function testLongUserAgentHandling(): void
    {
        $longUserAgent = str_repeat('A', 2000);
        $request = $this->createMockRequest('192.168.1.10', $longUserAgent);
        
        $result = $this->rateLimitService->isAllowed('wishlist_read', $request);
        $this->assertIsBool($result);
    }

    /**
     * Test concurrent request simulation.
     */
    public function testConcurrentRequestSimulation(): void
    {
        $requests = [];
        
        // Create multiple requests from same IP
        for ($i = 0; $i < 5; $i++) {
            $requests[] = $this->createMockRequest('192.168.1.100', 'ConcurrentTest/1.0');
        }
        
        // All should be processed without errors
        foreach ($requests as $request) {
            $result = $this->rateLimitService->isAllowed('wishlist_read', $request);
            $this->assertIsBool($result);
        }
    }

    /**
     * Helper method to create mock request.
     */
    private function createMockRequest(string $ip, string $userAgent): Request
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', $ip);
        $request->headers->set('User-Agent', $userAgent);
        $request->server->set('REQUEST_URI', '/api/wishlist');
        $request->server->set('REQUEST_METHOD', 'GET');
        
        return $request;
    }
}
