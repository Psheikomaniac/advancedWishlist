<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Unit\Core\Security;

use AdvancedWishlist\Core\Security\SecurityService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Comprehensive security service test suite.
 * Tests CSRF protection, input validation, and security headers.
 */
class SecurityServiceTest extends TestCase
{
    private SecurityService $securityService;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->securityService = new SecurityService(
            $this->csrfTokenManager,
            $this->logger
        );
    }

    /**
     * @testdox CSRF token validation with valid token should return true
     */
    public function testValidateCsrfTokenWithValidToken(): void
    {
        $request = new Request();
        $request->request->set('_csrf_token', 'valid_token');
        
        $this->csrfTokenManager
            ->expects($this->once())
            ->method('isTokenValid')
            ->with($this->callback(function (CsrfToken $token) {
                return $token->getId() === 'test_intention' && $token->getValue() === 'valid_token';
            }))
            ->willReturn(true);
        
        $result = $this->securityService->validateCsrfToken($request, 'test_intention');
        
        $this->assertTrue($result);
    }

    /**
     * @testdox CSRF token validation with invalid token should return false and log warning
     */
    public function testValidateCsrfTokenWithInvalidToken(): void
    {
        $request = new Request();
        $request->request->set('_csrf_token', 'invalid_token');
        
        $this->csrfTokenManager
            ->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(false);
        
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('CSRF validation failed: Invalid token');
        
        $result = $this->securityService->validateCsrfToken($request, 'test_intention');
        
        $this->assertFalse($result);
    }

    /**
     * @testdox CSRF token validation with missing token should return false and log warning
     */
    public function testValidateCsrfTokenWithMissingToken(): void
    {
        $request = new Request();
        
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('CSRF validation failed: No token provided');
        
        $result = $this->securityService->validateCsrfToken($request, 'test_intention');
        
        $this->assertFalse($result);
    }

    /**
     * @testdox CSRF token validation should work with header token
     */
    public function testValidateCsrfTokenWithHeaderToken(): void
    {
        $request = new Request();
        $request->headers->set('X-CSRF-Token', 'header_token');
        
        $this->csrfTokenManager
            ->expects($this->once())
            ->method('isTokenValid')
            ->with($this->callback(function (CsrfToken $token) {
                return $token->getValue() === 'header_token';
            }))
            ->willReturn(true);
        
        $result = $this->securityService->validateCsrfToken($request, 'test_intention');
        
        $this->assertTrue($result);
    }

    /**
     * @testdox Pagination validation with valid values should return correct integers
     */
    public function testValidatePaginationWithValidValues(): void
    {
        $this->assertEquals(10, $this->securityService->validatePagination('10'));
        $this->assertEquals(1, $this->securityService->validatePagination('1'));
        $this->assertEquals(100, $this->securityService->validatePagination('100'));
    }

    /**
     * @testdox Pagination validation with invalid values should return minimum
     */
    public function testValidatePaginationWithInvalidValues(): void
    {
        $this->assertEquals(1, $this->securityService->validatePagination('0'));
        $this->assertEquals(1, $this->securityService->validatePagination('-5'));
        $this->assertEquals(1, $this->securityService->validatePagination('invalid'));
        $this->assertEquals(1, $this->securityService->validatePagination('2000', 1, 1000));
    }

    /**
     * @testdox Fields validation with allowed fields should return true
     */
    public function testValidateFieldsWithAllowedFields(): void
    {
        $this->assertTrue($this->securityService->validateFields('id,name,type'));
        $this->assertTrue($this->securityService->validateFields('createdAt'));
        $this->assertTrue($this->securityService->validateFields('items.id,items.count'));
    }

    /**
     * @testdox Fields validation with disallowed fields should return false and log warning
     */
    public function testValidateFieldsWithDisallowedFields(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Unauthorized field requested');
        
        $this->assertFalse($this->securityService->validateFields('id,unauthorized_field'));
    }

    /**
     * @testdox Fields validation with invalid format should return false and log warning
     */
    public function testValidateFieldsWithInvalidFormat(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Invalid fields parameter format');
        
        $this->assertFalse($this->securityService->validateFields('id;DROP TABLE users'));
    }

    /**
     * @testdox Fields sanitization should return only allowed fields
     */
    public function testSanitizeFields(): void
    {
        $result = $this->securityService->sanitizeFields('id,name,unauthorized_field');
        $this->assertEquals([], $result); // Returns empty because validation fails
        
        $result = $this->securityService->sanitizeFields('id,name,type');
        $this->assertEquals(['id', 'name', 'type'], $result);
    }

    /**
     * @testdox Sort validation with valid parameters should return true
     */
    public function testValidateSortWithValidParameters(): void
    {
        $this->assertTrue($this->securityService->validateSort('name:ASC'));
        $this->assertTrue($this->securityService->validateSort('createdAt:DESC'));
        $this->assertTrue($this->securityService->validateSort('id:ASC,name:DESC'));
    }

    /**
     * @testdox Sort validation with invalid field should return false and log warning
     */
    public function testValidateSortWithInvalidField(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Unauthorized sort field');
        
        $this->assertFalse($this->securityService->validateSort('unauthorized_field:ASC'));
    }

    /**
     * @testdox Sort validation with invalid direction should return false and log warning
     */
    public function testValidateSortWithInvalidDirection(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Invalid sort direction');
        
        $this->assertFalse($this->securityService->validateSort('name:INVALID'));
    }

    /**
     * @testdox Filter validation with valid parameters should return true
     */
    public function testValidateFilterWithValidParameters(): void
    {
        $this->assertTrue($this->securityService->validateFilter('type:private'));
        $this->assertTrue($this->securityService->validateFilter('isDefault:true'));
        $this->assertTrue($this->securityService->validateFilter('type:public,isDefault:false'));
    }

    /**
     * @testdox Filter validation with invalid field should return false and log warning
     */
    public function testValidateFilterWithInvalidField(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Unauthorized filter field');
        
        $this->assertFalse($this->securityService->validateFilter('unauthorized:value'));
    }

    /**
     * @testdox Filter validation with invalid value should return false and log warning
     */
    public function testValidateFilterWithInvalidValue(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Invalid filter value');
        
        $this->assertFalse($this->securityService->validateFilter('type:invalid_type'));
    }

    /**
     * @testdox String sanitization should remove control characters and limit length
     */
    public function testSanitizeString(): void
    {
        $this->assertEquals('test', $this->securityService->sanitizeString('test'));
        $this->assertEquals('test', $this->securityService->sanitizeString("test\x00\x1F"));
        $this->assertEquals('test', $this->securityService->sanitizeString('  test  '));
        
        $longString = str_repeat('a', 300);
        $result = $this->securityService->sanitizeString($longString);
        $this->assertEquals(255, strlen($result));
    }

    /**
     * @testdox Text sanitization should allow longer content
     */
    public function testSanitizeText(): void
    {
        $longString = str_repeat('a', 1000);
        $result = $this->securityService->sanitizeText($longString);
        $this->assertEquals(1000, strlen($result));
        
        $veryLongString = str_repeat('a', 3000);
        $result = $this->securityService->sanitizeText($veryLongString);
        $this->assertEquals(2000, strlen($result));
    }

    /**
     * @testdox Security headers should be added to response
     */
    public function testAddSecurityHeaders(): void
    {
        $response = new Response();
        
        $this->securityService->addSecurityHeaders($response);
        
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('default-src \'self\'', $response->headers->get('Content-Security-Policy'));
    }

    /**
     * @testdox User access validation should work correctly
     */
    public function testValidateUserAccess(): void
    {
        $this->assertTrue($this->securityService->validateUserAccess('user123', 'user123'));
        $this->assertFalse($this->securityService->validateUserAccess('user123', 'user456'));
        $this->assertFalse($this->securityService->validateUserAccess('', 'user123'));
        $this->assertFalse($this->securityService->validateUserAccess('user123', ''));
    }

    /**
     * @testdox User access validation failure should log warning
     */
    public function testValidateUserAccessLogsWarningOnFailure(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Access denied: User does not own resource');
        
        $this->securityService->validateUserAccess('user123', 'user456');
    }

    /**
     * @testdox Rate limiting should return false for now (not implemented)
     */
    public function testIsRateLimited(): void
    {
        $request = new Request();
        
        $result = $this->securityService->isRateLimited($request, 'test_action');
        
        $this->assertFalse($result);
    }

    /**
     * @testdox Security event logging should work correctly
     */
    public function testLogSecurityEvent(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Security event: test_event',
                $this->callback(function (array $context) {
                    return isset($context['timestamp']) && 
                           isset($context['event_type']) && 
                           $context['event_type'] === 'security';
                })
            );
        
        $this->securityService->logSecurityEvent('test_event', ['extra' => 'data']);
    }

    /**
     * @testdox CSRF token generation should work
     */
    public function testGenerateCsrfToken(): void
    {
        $mockToken = $this->createMock(CsrfToken::class);
        $mockToken->method('getValue')->willReturn('generated_token');
        
        $this->csrfTokenManager
            ->expects($this->once())
            ->method('getToken')
            ->with('test_intention')
            ->willReturn($mockToken);
        
        $result = $this->securityService->generateCsrfToken('test_intention');
        
        $this->assertEquals('generated_token', $result);
    }
}