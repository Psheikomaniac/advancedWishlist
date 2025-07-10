<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SecurityMonitoringService
{
    private const LOG_CHANNEL = 'security';
    
    private LoggerInterface $logger;
    private RequestStack $requestStack;
    private TokenStorageInterface $tokenStorage;
    private array $suspiciousPatterns = [
        'sql_injection' => [
            '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
            '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i',
            '/\w*((\%27)|(\'))((\%6F)|o|(\%4F))((\%72)|r|(\%52))/i',
            '/((\%27)|(\'))union/i',
        ],
        'xss' => [
            '/((\%3C)|<)((\%2F)|\/)*[a-z0-9\%]+((\%3E)|>)/i',
            '/((\%3C)|<)((\%69)|i|(\%49))((\%6D)|m|(\%4D))((\%67)|g|(\%47))[^\n]+((\%3E)|>)/i',
            '/((\%3C)|<)[^\n]+((\%3E)|>)/i',
        ],
        'path_traversal' => [
            '/\.\.\//i',
            '/\.\.\\\/i',
        ],
    ];
    
    /**
     * SecurityMonitoringService constructor.
     *
     * @param LoggerInterface $logger
     * @param RequestStack $requestStack
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(
        LoggerInterface $logger,
        RequestStack $requestStack,
        TokenStorageInterface $tokenStorage
    ) {
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
    }
    
    /**
     * Log a security event.
     *
     * @param string $event The security event type
     * @param array $context Additional context information
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $token = $this->tokenStorage->getToken();
        
        $logContext = array_merge($context, [
            'event' => $event,
            'ip' => $request ? $request->getClientIp() : 'unknown',
            'user_id' => $token && $token->getUser() ? $token->getUser()->getId() : 'anonymous',
            'uri' => $request ? $request->getUri() : 'unknown',
            'method' => $request ? $request->getMethod() : 'unknown',
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
        
        $this->logger->warning('[SECURITY] ' . $event, $logContext);
    }
    
    /**
     * Monitor a request for suspicious patterns.
     */
    public function monitorRequest(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return;
        }
        
        // Check query parameters
        foreach ($request->query->all() as $key => $value) {
            $this->checkForSuspiciousPatterns($key, $value, 'query');
        }
        
        // Check request parameters
        foreach ($request->request->all() as $key => $value) {
            $this->checkForSuspiciousPatterns($key, $value, 'request');
        }
        
        // Check headers
        foreach ($request->headers->all() as $key => $values) {
            foreach ($values as $value) {
                $this->checkForSuspiciousPatterns($key, $value, 'header');
            }
        }
        
        // Check cookies
        foreach ($request->cookies->all() as $key => $value) {
            $this->checkForSuspiciousPatterns($key, $value, 'cookie');
        }
    }
    
    /**
     * Log failed authentication attempts.
     *
     * @param string $username The username that failed to authenticate
     */
    public function logFailedAuthentication(string $username): void
    {
        $this->logSecurityEvent('failed_authentication', [
            'username' => $username,
        ]);
    }
    
    /**
     * Log unauthorized access attempts.
     *
     * @param string $resource The resource that was attempted to be accessed
     * @param string $action The action that was attempted
     */
    public function logUnauthorizedAccess(string $resource, string $action): void
    {
        $this->logSecurityEvent('unauthorized_access', [
            'resource' => $resource,
            'action' => $action,
        ]);
    }
    
    /**
     * Log suspicious API requests.
     *
     * @param string $endpoint The API endpoint
     * @param array $params The request parameters
     */
    public function logSuspiciousApiRequest(string $endpoint, array $params): void
    {
        $this->logSecurityEvent('suspicious_api_request', [
            'endpoint' => $endpoint,
            'params' => $params,
        ]);
    }
    
    /**
     * Check for suspicious patterns in a value.
     *
     * @param string $key The parameter key
     * @param mixed $value The parameter value
     * @param string $source The source of the parameter (query, request, header, cookie)
     */
    private function checkForSuspiciousPatterns(string $key, $value, string $source): void
    {
        if (!is_string($value)) {
            return;
        }
        
        foreach ($this->suspiciousPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $this->logSecurityEvent('suspicious_pattern_detected', [
                        'type' => $type,
                        'pattern' => $pattern,
                        'value' => $value,
                        'key' => $key,
                        'source' => $source,
                    ]);
                    
                    break 2; // Break out of both loops once a match is found
                }
            }
        }
    }
}