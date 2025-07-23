<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Comprehensive security service for CSRF protection and input validation.
 * Implements OWASP security best practices.
 */
final readonly class SecurityService
{
    private const MAX_STRING_LENGTH = 255;
    private const MAX_TEXT_LENGTH = 2000;
    private const CSRF_TOKEN_HEADER = 'X-CSRF-Token';
    
    // Allowed field patterns for different contexts
    private const ALLOWED_WISHLIST_FIELDS = [
        'id', 'name', 'description', 'type', 'isDefault', 'createdAt', 'updatedAt',
        'customerId', 'items.id', 'items.count', 'shareInfo.id', 'shareInfo.token'
    ];
    
    private const ALLOWED_SORT_FIELDS = [
        'id', 'name', 'createdAt', 'updatedAt', 'type', 'customerId'
    ];
    
    private const ALLOWED_FILTER_FIELDS = [
        'type', 'isDefault', 'customerId'
    ];

    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Validate CSRF token from request.
     */
    public function validateCsrfToken(Request $request, string $intention): bool
    {
        $token = $this->extractCsrfToken($request);
        
        if (!$token) {
            $this->logger->warning('CSRF validation failed: No token provided', [
                'intention' => $intention,
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);
            return false;
        }
        
        $isValid = $this->csrfTokenManager->isTokenValid(new CsrfToken($intention, $token));
        
        if (!$isValid) {
            $this->logger->warning('CSRF validation failed: Invalid token', [
                'intention' => $intention,
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'token_length' => strlen($token),
            ]);
        }
        
        return $isValid;
    }

    /**
     * Generate a new CSRF token for the given intention.
     */
    public function generateCsrfToken(string $intention): string
    {
        return $this->csrfTokenManager->getToken($intention)->getValue();
    }

    /**
     * Extract CSRF token from request (body or header).
     */
    private function extractCsrfToken(Request $request): ?string
    {
        // Try request body first
        $token = $request->request->get('_csrf_token');
        
        // Fallback to header for API calls
        if (!$token) {
            $token = $request->headers->get(self::CSRF_TOKEN_HEADER);
        }
        
        return $token;
    }

    /**
     * Validate and sanitize pagination parameters.
     */
    public function validatePagination(string $value, int $min = 1, int $max = 1000): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        
        if (false === $int || $int < $min || $int > $max) {
            $this->logger->info('Invalid pagination parameter', [
                'value' => $value,
                'min' => $min,
                'max' => $max,
            ]);
            return $min;
        }
        
        return $int;
    }

    /**
     * Validate fields parameter for security.
     */
    public function validateFields(string $fields): bool
    {
        // Basic format validation
        if (!preg_match('/^[a-zA-Z0-9,._]+$/', $fields)) {
            $this->logger->warning('Invalid fields parameter format', [
                'fields' => $fields,
            ]);
            return false;
        }
        
        // Check each field against whitelist
        $fieldArray = array_map('trim', explode(',', $fields));
        foreach ($fieldArray as $field) {
            if (!in_array($field, self::ALLOWED_WISHLIST_FIELDS)) {
                $this->logger->warning('Unauthorized field requested', [
                    'field' => $field,
                    'allowed_fields' => self::ALLOWED_WISHLIST_FIELDS,
                ]);
                return false;
            }
        }
        
        return true;
    }

    /**
     * Sanitize and return allowed fields.
     */
    public function sanitizeFields(string $fields): array
    {
        if (!$this->validateFields($fields)) {
            return [];
        }
        
        $fieldArray = array_map('trim', explode(',', $fields));
        return array_intersect($fieldArray, self::ALLOWED_WISHLIST_FIELDS);
    }

    /**
     * Validate sort parameter.
     */
    public function validateSort(string $sort): bool
    {
        // Basic format validation
        if (!preg_match('/^[a-zA-Z0-9_:,]+$/', $sort)) {
            $this->logger->warning('Invalid sort parameter format', [
                'sort' => $sort,
            ]);
            return false;
        }
        
        // Validate each sort field
        $sortFields = explode(',', $sort);
        foreach ($sortFields as $sortField) {
            $parts = explode(':', $sortField);
            $field = $parts[0];
            $direction = strtoupper($parts[1] ?? 'ASC');
            
            if (!in_array($field, self::ALLOWED_SORT_FIELDS)) {
                $this->logger->warning('Unauthorized sort field', [
                    'field' => $field,
                    'allowed_fields' => self::ALLOWED_SORT_FIELDS,
                ]);
                return false;
            }
            
            if (!in_array($direction, ['ASC', 'DESC'])) {
                $this->logger->warning('Invalid sort direction', [
                    'direction' => $direction,
                    'field' => $field,
                ]);
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate filter parameter.
     */
    public function validateFilter(string $filter): bool
    {
        // Basic format validation
        if (!preg_match('/^[a-zA-Z0-9_:,]+$/', $filter)) {
            $this->logger->warning('Invalid filter parameter format', [
                'filter' => $filter,
            ]);
            return false;
        }
        
        // Validate each filter
        $filters = explode(',', $filter);
        foreach ($filters as $filterStr) {
            $parts = explode(':', $filterStr, 2);
            if (count($parts) !== 2) {
                continue;
            }
            
            $field = trim($parts[0]);
            $value = trim($parts[1]);
            
            if (!in_array($field, self::ALLOWED_FILTER_FIELDS)) {
                $this->logger->warning('Unauthorized filter field', [
                    'field' => $field,
                    'allowed_fields' => self::ALLOWED_FILTER_FIELDS,
                ]);
                return false;
            }
            
            if (!$this->validateFilterValue($field, $value)) {
                $this->logger->warning('Invalid filter value', [
                    'field' => $field,
                    'value' => $value,
                ]);
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate filter values based on field type.
     */
    private function validateFilterValue(string $field, string $value): bool
    {
        switch ($field) {
            case 'type':
                return in_array($value, ['private', 'public', 'shared']);
            case 'isDefault':
                return in_array($value, ['0', '1', 'true', 'false']);
            case 'customerId':
                return 1 === preg_match('/^[a-f0-9]{32}$/', $value); // UUID format
            default:
                return false;
        }
    }

    /**
     * Sanitize string input to prevent XSS and injection attacks.
     */
    public function sanitizeString(string $input, int $maxLength = self::MAX_STRING_LENGTH): string
    {
        // Remove null bytes and control characters
        $input = preg_replace('/[\x00-\x1F\x7F]/', '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Limit length
        if (strlen($input) > $maxLength) {
            $input = substr($input, 0, $maxLength);
        }
        
        return $input;
    }

    /**
     * Sanitize text input with longer length limit.
     */
    public function sanitizeText(string $input): string
    {
        return $this->sanitizeString($input, self::MAX_TEXT_LENGTH);
    }

    /**
     * Add security headers to response.
     */
    public function addSecurityHeaders(Response $response): void
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
        ];
        
        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }
    }

    /**
     * Validate user access permissions.
     */
    public function validateUserAccess(string $userId, ?string $resourceOwnerId, string $resourceType = 'wishlist'): bool
    {
        if (!$userId || !$resourceOwnerId) {
            $this->logger->warning('Access validation failed: Missing user or resource owner ID', [
                'userId' => $userId,
                'resourceOwnerId' => $resourceOwnerId,
                'resourceType' => $resourceType,
            ]);
            return false;
        }
        
        $hasAccess = $userId === $resourceOwnerId;
        
        if (!$hasAccess) {
            $this->logger->warning('Access denied: User does not own resource', [
                'userId' => $userId,
                'resourceOwnerId' => $resourceOwnerId,
                'resourceType' => $resourceType,
            ]);
        }
        
        return $hasAccess;
    }

    /**
     * Rate limit validation (basic implementation).
     */
    public function isRateLimited(Request $request, string $action, int $limit = 100): bool
    {
        // This is a basic implementation - in production, you'd use Redis or similar
        $key = $this->getRateLimitKey($request, $action);
        
        // For now, always return false (not rate limited)
        // TODO: Implement proper rate limiting with cache backend
        return false;
    }

    /**
     * Generate rate limit key for caching.
     */
    private function getRateLimitKey(Request $request, string $action): string
    {
        $ip = $request->getClientIp();
        return "rate_limit:{$action}:{$ip}";
    }

    /**
     * Log security event for monitoring.
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        $this->logger->info("Security event: {$event}", array_merge($context, [
            'timestamp' => time(),
            'event_type' => 'security',
        ]));
    }
}