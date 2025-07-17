<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Security;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RequestStack;

class SecurityMonitoringService
{
    private const LOG_CHANNEL = 'security';

    private LoggerInterface $logger;
    private RequestStack $requestStack;
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
     */
    public function __construct(
        LoggerInterface $logger,
        RequestStack $requestStack,
    ) {
        $this->logger = $logger;
        $this->requestStack = $requestStack;
    }

    /**
     * Log a security event.
     *
     * @param string       $event           The security event type
     * @param array        $context         Additional context information
     * @param Context|null $shopwareContext Optional Shopware context for user identification
     */
    public function logSecurityEvent(string $event, array $context = [], ?Context $shopwareContext = null): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $userId = $this->getUserId($shopwareContext);

        $logContext = array_merge($context, [
            'event' => $event,
            'ip' => $request ? $request->getClientIp() : 'unknown',
            'user_id' => $userId ?? 'anonymous',
            'uri' => $request ? $request->getUri() : 'unknown',
            'method' => $request ? $request->getMethod() : 'unknown',
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $this->logger->warning('[SECURITY] '.$event, $logContext);
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
     * @param string       $username The username that failed to authenticate
     * @param Context|null $context  Optional Shopware context for user identification
     */
    public function logFailedAuthentication(string $username, ?Context $context = null): void
    {
        $this->logSecurityEvent('failed_authentication', [
            'username' => $username,
        ], $context);
    }

    /**
     * Log unauthorized access attempts.
     *
     * @param string       $resource The resource that was attempted to be accessed
     * @param string       $action   The action that was attempted
     * @param Context|null $context  Optional Shopware context for user identification
     */
    public function logUnauthorizedAccess(string $resource, string $action, ?Context $context = null): void
    {
        $this->logSecurityEvent('unauthorized_access', [
            'resource' => $resource,
            'action' => $action,
        ], $context);
    }

    /**
     * Log suspicious API requests.
     *
     * @param string       $endpoint The API endpoint
     * @param array        $params   The request parameters
     * @param Context|null $context  Optional Shopware context for user identification
     */
    public function logSuspiciousApiRequest(string $endpoint, array $params, ?Context $context = null): void
    {
        $this->logSecurityEvent('suspicious_api_request', [
            'endpoint' => $endpoint,
            'params' => $params,
        ], $context);
    }

    /**
     * Check for suspicious patterns in a value.
     *
     * @param string $key    The parameter key
     * @param mixed  $value  The parameter value
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

    /**
     * Extract user ID from Shopware context.
     *
     * @param Context|null $context The Shopware context
     *
     * @return string|null The user ID if available, null otherwise
     */
    private function getUserId(?Context $context): ?string
    {
        if (!$context) {
            return null;
        }

        $contextSource = $context->getSource();

        return $contextSource instanceof AdminApiSource ? $contextSource->getUserId() : null;
    }
}
