# Error Handling Guidelines for Advanced Wishlist System

This document outlines the standards and best practices for error handling, logging, and debugging in the Advanced Wishlist System plugin for Shopware 6.

## Error Handling Principles

1. **Fail Fast**: Detect and report errors as early as possible
2. **Be Specific**: Use specific exception types for different error scenarios
3. **Provide Context**: Include relevant information in error messages
4. **Graceful Degradation**: Maintain core functionality when non-critical features fail
5. **User-Friendly Messages**: Display helpful, non-technical messages to end users

## Exception Hierarchy

Create a consistent exception hierarchy for the plugin:

```
AdvancedWishlistException (base exception)
├── ValidationException
│   ├── InvalidWishlistNameException
│   └── InvalidItemQuantityException
├── AuthorizationException
│   ├── UnauthorizedAccessException
│   └── PermissionDeniedException
├── ResourceNotFoundException
│   ├── WishlistNotFoundException
│   └── WishlistItemNotFoundException
├── LimitExceededException
│   ├── WishlistLimitExceededException
│   └── ItemLimitExceededException
└── ExternalServiceException
    ├── EmailServiceException
    └── PaymentServiceException
```

## Exception Implementation

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class AdvancedWishlistException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'ADVANCED_WISHLIST__BASE_EXCEPTION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}

class ValidationException extends AdvancedWishlistException
{
    public function __construct(
        string $message,
        array $parameters = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'ADVANCED_WISHLIST__VALIDATION_ERROR';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
```

## Error Handling in Services

```php
public function createWishlist(CreateWishlistRequest $request, Context $context): WishlistResponse
{
    try {
        // Validate request
        $this->validator->validateCreateRequest($request, $context);
        
        // Check limits
        if (!$this->limitService->canCreateWishlist($request->getCustomerId(), $context)) {
            throw new WishlistLimitExceededException(
                'Customer has reached the maximum number of wishlists',
                ['limit' => self::DEFAULT_WISHLIST_LIMIT]
            );
        }
        
        // Create wishlist
        // ...
    } catch (ValidationException $e) {
        // Log validation errors
        $this->logger->warning('Wishlist validation failed', [
            'error' => $e->getMessage(),
            'parameters' => $e->getParameters(),
            'customerId' => $request->getCustomerId(),
        ]);
        
        throw $e; // Re-throw for API response
    } catch (WishlistLimitExceededException $e) {
        // Log limit exceeded
        $this->logger->info('Wishlist limit exceeded', [
            'customerId' => $request->getCustomerId(),
            'limit' => self::DEFAULT_WISHLIST_LIMIT,
        ]);
        
        throw $e; // Re-throw for API response
    } catch (\Exception $e) {
        // Log unexpected errors
        $this->logger->error('Unexpected error creating wishlist', [
            'error' => $e->getMessage(),
            'customerId' => $request->getCustomerId(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        // Convert to plugin exception
        throw new AdvancedWishlistException(
            'An unexpected error occurred while creating the wishlist',
            ['error' => $e->getMessage()],
            $e
        );
    }
}
```

## API Error Responses

All API endpoints should return consistent error responses:

```json
{
  "errors": [
    {
      "code": "ADVANCED_WISHLIST__VALIDATION_ERROR",
      "status": "400",
      "title": "Validation Error",
      "detail": "The wishlist name contains invalid characters",
      "meta": {
        "parameters": {
          "field": "name",
          "value": "Invalid/Name"
        }
      }
    }
  ]
}
```

## Frontend Error Handling

```typescript
async function createWishlist(data: CreateWishlistRequest): Promise<void> {
  try {
    await wishlistApi.createWishlist(data);
    notification.success('Wishlist created successfully');
  } catch (error) {
    if (error.response?.data?.errors) {
      // Handle specific API errors
      const apiError = error.response.data.errors[0];
      
      if (apiError.code === 'ADVANCED_WISHLIST__VALIDATION_ERROR') {
        notification.error(`Validation error: ${apiError.detail}`);
      } else if (apiError.code === 'ADVANCED_WISHLIST__LIMIT_EXCEEDED') {
        notification.warning(`Limit exceeded: ${apiError.detail}`);
      } else {
        notification.error('An error occurred while creating the wishlist');
      }
      
      // Log to monitoring service
      errorMonitoring.captureApiError(apiError);
    } else {
      // Handle network or unexpected errors
      notification.error('Could not connect to the server. Please try again.');
      errorMonitoring.captureException(error);
    }
  }
}
```

## Logging Standards

### Log Levels

- **ERROR**: Application errors that require immediate attention
- **WARNING**: Potentially harmful situations that don't stop the application
- **INFO**: Interesting events and milestones
- **DEBUG**: Detailed information for debugging

### Logging Context

Always include relevant context with log messages:

```php
$this->logger->error('Failed to send wishlist share email', [
    'wishlistId' => $wishlistId,
    'recipientEmail' => $recipientEmail,
    'errorMessage' => $e->getMessage(),
    'errorCode' => $e->getCode(),
]);
```

### Sensitive Data

Never log sensitive information:

- Passwords or authentication tokens
- Full credit card numbers
- Personal identification information
- API keys or secrets

### Structured Logging

Use structured logging for better searchability:

```php
$this->logger->info('Wishlist shared', [
    'event' => 'wishlist_shared',
    'wishlistId' => $wishlist->getId(),
    'customerId' => $wishlist->getCustomerId(),
    'shareMethod' => $request->getShareMethod(),
    'timestamp' => (new \DateTime())->format('c'),
]);
```

## Debugging

### Debug Mode

Implement a debug mode that can be enabled in the plugin configuration:

```php
if ($this->configService->isDebugModeEnabled()) {
    $this->logger->debug('Detailed operation information', [
        'request' => $request->toArray(),
        'context' => $this->getContextInfo($context),
    ]);
}
```

### Development Tools

- Use Xdebug for PHP debugging
- Use Vue.js DevTools for frontend debugging
- Use Shopware's debugging tools (profiler, debug templates)

### Troubleshooting Guide

Create a troubleshooting guide for common issues:

1. **Wishlist not saving**: Check database permissions, validate request data
2. **Sharing not working**: Verify email configuration, check share token generation
3. **Performance issues**: Check caching configuration, optimize database queries

## Error Monitoring

Implement error monitoring for production environments:

1. **Aggregation**: Group similar errors
2. **Alerting**: Set up alerts for critical errors
3. **Context**: Capture environment information
4. **User Impact**: Track affected users

## Error Prevention

### Input Validation

- Validate all user input using DTOs
- Use type hints and assertions
- Implement business rule validation

### Defensive Programming

- Check for null values
- Validate array indexes before access
- Use null coalescing operator (`??`)
- Use optional chaining in JavaScript

### Feature Flags

Use feature flags to safely roll out new features:

```php
if ($this->featureService->isEnabled('advanced_sharing')) {
    // Implement advanced sharing logic
} else {
    // Fall back to basic sharing
}
```

## Recovery Strategies

### Retry Mechanism

Implement retry logic for transient failures:

```php
public function sendNotification(string $wishlistId, Context $context): void
{
    $retries = 0;
    $maxRetries = 3;
    
    while ($retries < $maxRetries) {
        try {
            $this->notificationService->send($wishlistId, $context);
            return;
        } catch (TransientException $e) {
            $retries++;
            $this->logger->warning('Notification failed, retrying', [
                'attempt' => $retries,
                'maxRetries' => $maxRetries,
                'wishlistId' => $wishlistId,
            ]);
            
            // Exponential backoff
            sleep(2 ** $retries);
        }
    }
    
    throw new NotificationFailedException('Failed to send notification after multiple attempts');
}
```

### Circuit Breaker

Implement circuit breaker pattern for external services:

```php
public function callExternalService(): void
{
    if ($this->circuitBreaker->isOpen('email_service')) {
        throw new ServiceUnavailableException('Email service is currently unavailable');
    }
    
    try {
        $result = $this->emailService->send();
        $this->circuitBreaker->reportSuccess('email_service');
    } catch (\Exception $e) {
        $this->circuitBreaker->reportFailure('email_service');
        throw $e;
    }
}
```

## Testing Error Scenarios

- Write unit tests for error cases
- Test exception handling
- Simulate service failures
- Validate error responses

Example test:

```php
public function testCreateWishlistWithInvalidName(): void
{
    $request = new CreateWishlistRequest();
    $request->setName('Invalid/Name');
    $request->setCustomerId('customer123');
    
    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('Wishlist name contains invalid characters');
    
    $this->service->createWishlist($request, $this->createMock(Context::class));
}
```