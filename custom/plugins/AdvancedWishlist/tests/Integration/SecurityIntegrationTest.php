<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Integration;

use AdvancedWishlist\Core\Security\RateLimitService;
use AdvancedWishlist\Core\Security\SecurityService;
use AdvancedWishlist\Storefront\Controller\WishlistController;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Integration tests for all security fixes implemented
 */
class SecurityIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use KernelTestBehaviour;

    private SecurityService $securityService;
    private WishlistController $controller;
    private CsrfTokenManagerInterface $csrfTokenManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->securityService = $this->getContainer()->get(SecurityService::class);
        $this->controller = $this->getContainer()->get(WishlistController::class);
        $this->csrfTokenManager = $this->getContainer()->get('security.csrf.token_manager');
    }

    /**
     * Test that CSRF token manager is non-nullable
     */
    public function testCsrfTokenManagerIsRequired(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        
        $parameters = $constructor->getParameters();
        $csrfParam = null;
        
        foreach ($parameters as $param) {
            if ($param->getName() === 'csrfTokenManager') {
                $csrfParam = $param;
                break;
            }
        }
        
        $this->assertNotNull($csrfParam, 'CSRF token manager parameter not found');
        $this->assertFalse($csrfParam->allowsNull(), 'CSRF token manager should not be nullable');
    }

    /**
     * Test that state-changing operations require CSRF token
     */
    public function testCreateRequiresCsrfToken(): void
    {
        $request = new Request([], [
            'name' => 'Test Wishlist',
            'type' => 'private',
            // No CSRF token provided
        ]);
        
        $context = $this->createSalesChannelContext();
        
        $response = $this->controller->create(
            new \AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest(),
            $request,
            $context
        );
        
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $content);
        $this->assertEquals('WISHLIST__INVALID_CSRF_TOKEN', $content['errors'][0]['code']);
    }

    /**
     * Test that valid CSRF token allows operation
     */
    public function testValidCsrfTokenAllowsOperation(): void
    {
        $token = $this->csrfTokenManager->getToken('wishlist_create')->getValue();
        
        $request = new Request([], [
            'name' => 'Test Wishlist',
            'type' => 'private',
            '_csrf_token' => $token,
        ]);
        
        $context = $this->createSalesChannelContext();
        
        // Mock the service to avoid actual database operations
        $crudService = $this->createMock(\AdvancedWishlist\Core\Service\WishlistCrudService::class);
        $crudService->expects($this->once())
            ->method('createWishlist')
            ->willReturn(['id' => 'test-id', 'name' => 'Test Wishlist']);
        
        // This should succeed with valid token
        $response = $this->controller->create(
            new \AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest(),
            $request,
            $context
        );
        
        $this->assertNotEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * Test rate limiting functionality
     */
    public function testRateLimitingWorks(): void
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        
        // Make requests up to the limit
        $limited = false;
        for ($i = 0; $i < 300; $i++) {
            if ($this->securityService->isRateLimited($request, 'wishlist_read')) {
                $limited = true;
                break;
            }
        }
        
        $this->assertTrue($limited, 'Rate limiting should trigger after limit exceeded');
    }

    /**
     * Test rate limit headers are added to response
     */
    public function testRateLimitHeadersAreAdded(): void
    {
        $request = new Request();
        $response = new Response();
        
        $this->securityService->addRateLimitHeaders($response, $request, 'wishlist_read');
        
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('X-RateLimit-Reset'));
    }

    /**
     * Test OAuth2 CSRF doesn't accept X-Requested-With
     */
    public function testOAuth2CsrfRejectsXRequestedWith(): void
    {
        $oauth2Controller = $this->getContainer()->get(\AdvancedWishlist\Core\OAuth\Controller\OAuth2Controller::class);
        
        $request = new Request();
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        // Not setting proper CSRF token
        
        $reflection = new \ReflectionClass($oauth2Controller);
        $method = $reflection->getMethod('validateCsrfToken');
        $method->setAccessible(true);
        
        $result = $method->invoke($oauth2Controller, $request, 'oauth2_authorize');
        
        $this->assertFalse($result, 'X-Requested-With should not be accepted as CSRF token');
    }

    /**
     * Test OAuth2 CSRF accepts proper token
     */
    public function testOAuth2CsrfAcceptsProperToken(): void
    {
        $oauth2Controller = $this->getContainer()->get(\AdvancedWishlist\Core\OAuth\Controller\OAuth2Controller::class);
        
        $token = $this->csrfTokenManager->getToken('oauth2_authorize')->getValue();
        
        $request = new Request([], [
            '_csrf_token' => $token,
        ]);
        
        $reflection = new \ReflectionClass($oauth2Controller);
        $method = $reflection->getMethod('validateCsrfToken');
        $method->setAccessible(true);
        
        $result = $method->invoke($oauth2Controller, $request, 'oauth2_authorize');
        
        $this->assertTrue($result, 'Proper CSRF token should be accepted');
    }

    /**
     * Test DTO validation throws exceptions
     */
    public function testDtoValidationThrowsExceptions(): void
    {
        $validator = $this->getContainer()->get(\AdvancedWishlist\Core\DTO\DTOValidator::class);
        
        $dto = new \AdvancedWishlist\Core\DTO\Request\CreateWishlistRequest();
        // Don't set required fields
        
        $this->expectException(\AdvancedWishlist\Core\Exception\ValidationException::class);
        
        $validator->validateOrThrow($dto);
    }

    /**
     * Test PHP version check
     */
    public function testPhpVersionRequirement(): void
    {
        $this->assertGreaterThanOrEqual(
            version_compare('8.4.0', PHP_VERSION),
            0,
            'PHP version should be 8.4 or higher'
        );
        
        // Check composer.json requirement
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../composer.json'),
            true
        );
        
        $this->assertEquals('^8.4', $composerJson['require']['php']);
        $this->assertEquals('8.4', $composerJson['config']['platform']['php']);
    }

    /**
     * Helper method to create sales channel context
     */
    private function createSalesChannelContext()
    {
        // Mock implementation for testing
        return $this->createMock(\Shopware\Core\System\SalesChannel\SalesChannelContext::class);
    }
}