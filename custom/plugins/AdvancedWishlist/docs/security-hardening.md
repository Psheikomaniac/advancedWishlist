# Security Hardening Features

This document outlines the security hardening features implemented in the AdvancedWishlist plugin as part of Phase 4, Step 1 of the implementation roadmap.

## 1. Encryption for Sensitive Data

### EncryptionService

We've implemented a secure encryption service using the [defuse/php-encryption](https://github.com/defuse/php-encryption) library, which provides authenticated encryption with industry-standard algorithms.

**Key Features:**
- Authenticated encryption (AES-256-CBC with HMAC)
- Secure key management
- Protection against timing attacks
- Proper IV generation and handling

**Usage:**
```php
// Encrypt data
$encryptedData = $encryptionService->encrypt($sensitiveData);

// Decrypt data
$decryptedData = $encryptionService->decrypt($encryptedData);

// Generate a secure token
$token = $encryptionService->generateToken();
```

### Configuration

The encryption key is stored in the environment variable `WISHLIST_ENCRYPTION_KEY`. This key should be generated using the static method `EncryptionService::generateEncryptionKey()` and stored securely.

## 2. OAuth2 for API Access

We've implemented OAuth2 authentication for API access using the [league/oauth2-server](https://oauth2.thephpleague.com/) library.

### Components

1. **OAuth2 Entities**
   - AccessTokenEntity
   - ClientEntity
   - ScopeEntity
   - RefreshTokenEntity

2. **OAuth2 Repositories**
   - ClientRepository
   - ScopeRepository
   - AccessTokenRepository
   - RefreshTokenRepository

3. **OAuth2 Service**
   - Configures and manages the OAuth2 server
   - Handles token generation and validation

4. **OAuth2 Controller**
   - Provides endpoints for token issuance and introspection
   - `/api/oauth/token` - Token endpoint
   - `/api/oauth/introspect` - Token introspection endpoint

5. **OAuth2 Middleware**
   - Protects API routes with OAuth2 authentication
   - Validates access tokens
   - Adds OAuth2 attributes to the request

### Supported Grant Types

- Client Credentials
- Password
- Refresh Token

### Scopes

The following scopes are available:
- `wishlist:read` - Read wishlists
- `wishlist:write` - Create and update wishlists
- `wishlist:delete` - Delete wishlists
- `wishlist:share` - Share wishlists

### Configuration

OAuth2 requires the following configuration:
- Private and public keys for JWT signing
- Encryption key for token encryption

These should be configured in the environment:
- `OAUTH2_ENCRYPTION_KEY` - Key for token encryption
- JWT keys should be stored in `config/jwt/private.pem` and `config/jwt/public.pem`

## 3. Security Monitoring

We've implemented a comprehensive security monitoring system to detect and log security events.

### SecurityMonitoringService

**Features:**
- Monitors requests for suspicious patterns
- Detects potential SQL injection attempts
- Detects potential XSS attacks
- Detects path traversal attempts
- Logs security events with detailed context

**Logged Information:**
- Event type
- IP address
- User ID
- URI
- HTTP method
- Timestamp
- Additional context specific to the event

### Security Event Types

- `suspicious_pattern_detected` - Suspicious pattern detected in request
- `failed_authentication` - Failed authentication attempt
- `unauthorized_access` - Unauthorized access attempt
- `suspicious_api_request` - Suspicious API request

## 4. Implementation Details

### Directory Structure

```
src/
├── Core/
│   ├── OAuth/
│   │   ├── Controller/
│   │   │   └── OAuth2Controller.php
│   │   ├── Entity/
│   │   │   ├── AccessTokenEntity.php
│   │   │   ├── ClientEntity.php
│   │   │   ├── RefreshTokenEntity.php
│   │   │   └── ScopeEntity.php
│   │   ├── Middleware/
│   │   │   └── OAuth2Middleware.php
│   │   ├── Repository/
│   │   │   ├── AccessTokenRepository.php
│   │   │   ├── ClientRepository.php
│   │   │   ├── RefreshTokenRepository.php
│   │   │   └── ScopeRepository.php
│   │   ├── Service/
│   │   │   └── OAuth2Service.php
│   │   └── Subscriber/
│   │       └── OAuth2Subscriber.php
│   └── Security/
│       ├── SecurityMonitoringService.php
│       └── SecurityMonitoringSubscriber.php
└── Service/
    ├── EncryptionService.php
    └── ShareService.php
```

## 5. Future Enhancements

- Implement rate limiting for API endpoints
- Add IP-based blocking for suspicious activity
- Implement two-factor authentication
- Set up automated security scanning
- Implement CAPTCHA for sensitive operations
- Add security headers middleware