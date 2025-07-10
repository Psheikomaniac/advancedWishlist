# AdvancedWishlist API Documentation

This document provides comprehensive documentation for the AdvancedWishlist plugin's API endpoints.

## Authentication

The AdvancedWishlist API supports two authentication methods:

1. **Session-based Authentication**: Used for Storefront API endpoints
2. **OAuth2 Authentication**: Used for headless API access

### OAuth2 Authentication

OAuth2 is used for authenticating API requests from external applications. The following endpoints are available for OAuth2 authentication:

#### Token Endpoint

```
POST /api/oauth/token
```

This endpoint is used to obtain an access token.

**Request Parameters:**

| Parameter     | Type   | Required | Description                                     |
|---------------|--------|----------|-------------------------------------------------|
| grant_type    | string | Yes      | The OAuth2 grant type (client_credentials, password, refresh_token) |
| client_id     | string | Yes      | The client identifier                           |
| client_secret | string | Yes      | The client secret                               |
| username      | string | No       | Required for password grant type                |
| password      | string | No       | Required for password grant type                |
| refresh_token | string | No       | Required for refresh_token grant type           |
| scope         | string | No       | Space-separated list of requested scopes        |

**Response:**

```json
{
  "token_type": "Bearer",
  "expires_in": 3600,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refresh_token": "def50200641f31a9b5f2d0a3d..."
}
```

#### Token Introspection Endpoint

```
POST /api/oauth/introspect
```

This endpoint is used to validate an access token.

**Request Headers:**

| Header          | Value                      |
|-----------------|----------------------------|
| Authorization   | Bearer {access_token}      |

**Response:**

```json
{
  "active": true,
  "client_id": "wishlist_client",
  "user_id": "user123",
  "scopes": ["wishlist:read", "wishlist:write"],
  "expires_at": "2023-12-31T23:59:59Z"
}
```

### Available Scopes

The following scopes are available for OAuth2 authentication:

- `wishlist:read` - Read wishlists
- `wishlist:write` - Create and update wishlists
- `wishlist:delete` - Delete wishlists
- `wishlist:share` - Share wishlists

## Wishlist API Endpoints

### List Wishlists

```
GET /store-api/wishlist
```

Returns a list of wishlists for the authenticated customer.

**Authentication Required**: Yes (Customer Session or OAuth2 with wishlist:read scope)

**Query Parameters:**

| Parameter | Type    | Required | Description                                     |
|-----------|---------|----------|-------------------------------------------------|
| limit     | integer | No       | Maximum number of results (default: 10)         |
| page      | integer | No       | Page number (default: 1)                        |
| fields    | string  | No       | Comma-separated list of fields to include       |
| sort      | string  | No       | Field and direction for sorting (field:direction) |
| filter    | string  | No       | Field and value for filtering (field:value)     |

**Response:**

```json
{
  "total": 2,
  "data": [
    {
      "id": "7a1e2b3c4d5e6f7a8b9c0d1e",
      "name": "Birthday Wishlist",
      "customerId": "a1b2c3d4e5f6g7h8i9j0k1l",
      "type": "private",
      "items": [
        {
          "id": "1a2b3c4d5e6f7g8h9i0j1k2l",
          "productId": "p1q2r3s4t5u6v7w8x9y0z1a",
          "productName": "Product 1",
          "quantity": 1,
          "addedAt": "2023-01-15T10:30:00Z"
        }
      ],
      "createdAt": "2023-01-01T12:00:00Z",
      "updatedAt": "2023-01-15T10:30:00Z"
    },
    {
      "id": "8b9c0d1e2f3g4h5i6j7k8l9m",
      "name": "Christmas Wishlist",
      "customerId": "a1b2c3d4e5f6g7h8i9j0k1l",
      "type": "public",
      "items": [],
      "createdAt": "2023-02-01T09:15:00Z",
      "updatedAt": "2023-02-01T09:15:00Z"
    }
  ]
}
```

### Get Wishlist Details

```
GET /store-api/wishlist/{id}
```

Returns details of a specific wishlist.

**Authentication Required**: Yes (Customer Session or OAuth2 with wishlist:read scope)

**Path Parameters:**

| Parameter | Type   | Required | Description     |
|-----------|--------|----------|-----------------|
| id        | string | Yes      | Wishlist ID     |

**Response:**

```json
{
  "id": "7a1e2b3c4d5e6f7a8b9c0d1e",
  "name": "Birthday Wishlist",
  "customerId": "a1b2c3d4e5f6g7h8i9j0k1l",
  "type": "private",
  "items": [
    {
      "id": "1a2b3c4d5e6f7g8h9i0j1k2l",
      "productId": "p1q2r3s4t5u6v7w8x9y0z1a",
      "productName": "Product 1",
      "quantity": 1,
      "addedAt": "2023-01-15T10:30:00Z"
    }
  ],
  "shareInfo": [
    {
      "id": "s1t2u3v4w5x6y7z8a9b0c1d2",
      "recipientId": "r1e2c3i4p5i6e7n8t9i0d",
      "recipientEmail": "recipient@example.com",
      "token": "encrypted_token_value",
      "createdAt": "2023-01-10T14:25:00Z"
    }
  ],
  "createdAt": "2023-01-01T12:00:00Z",
  "updatedAt": "2023-01-15T10:30:00Z"
}
```

### Create Wishlist

```
POST /store-api/wishlist
```

Creates a new wishlist.

**Authentication Required**: Yes (Customer Session or OAuth2 with wishlist:write scope)

**Request Headers:**

| Header          | Value                      |
|-----------------|----------------------------|
| Content-Type    | application/json           |

**Request Body:**

```json
{
  "name": "New Wishlist",
  "type": "private",
  "_csrf_token": "csrf_token_value"
}
```

**Request Parameters:**

| Parameter   | Type   | Required | Description                                     |
|-------------|--------|----------|-------------------------------------------------|
| name        | string | Yes      | Name of the wishlist                            |
| type        | string | No       | Type of wishlist (private, public, shared)      |
| _csrf_token | string | Yes      | CSRF token for protection (not required for OAuth2) |

**Response:**

```json
{
  "id": "9c0d1e2f3g4h5i6j7k8l9m0n",
  "name": "New Wishlist",
  "customerId": "a1b2c3d4e5f6g7h8i9j0k1l",
  "type": "private",
  "items": [],
  "createdAt": "2023-03-01T15:45:00Z",
  "updatedAt": "2023-03-01T15:45:00Z"
}
```

### Update Wishlist

```
PATCH /store-api/wishlist/{id}
```

Updates an existing wishlist.

**Authentication Required**: Yes (Customer Session or OAuth2 with wishlist:write scope)

**Path Parameters:**

| Parameter | Type   | Required | Description     |
|-----------|--------|----------|-----------------|
| id        | string | Yes      | Wishlist ID     |

**Request Headers:**

| Header          | Value                      |
|-----------------|----------------------------|
| Content-Type    | application/json           |

**Request Body:**

```json
{
  "name": "Updated Wishlist Name",
  "type": "public",
  "_csrf_token": "csrf_token_value"
}
```

**Request Parameters:**

| Parameter   | Type   | Required | Description                                     |
|-------------|--------|----------|-------------------------------------------------|
| name        | string | No       | New name of the wishlist                        |
| type        | string | No       | New type of wishlist (private, public, shared)  |
| _csrf_token | string | Yes      | CSRF token for protection (not required for OAuth2) |

**Response:**

```json
{
  "id": "7a1e2b3c4d5e6f7a8b9c0d1e",
  "name": "Updated Wishlist Name",
  "customerId": "a1b2c3d4e5f6g7h8i9j0k1l",
  "type": "public",
  "items": [
    {
      "id": "1a2b3c4d5e6f7g8h9i0j1k2l",
      "productId": "p1q2r3s4t5u6v7w8x9y0z1a",
      "productName": "Product 1",
      "quantity": 1,
      "addedAt": "2023-01-15T10:30:00Z"
    }
  ],
  "createdAt": "2023-01-01T12:00:00Z",
  "updatedAt": "2023-03-05T09:20:00Z"
}
```

### Delete Wishlist

```
DELETE /store-api/wishlist/{id}
```

Deletes a wishlist.

**Authentication Required**: Yes (Customer Session or OAuth2 with wishlist:delete scope)

**Path Parameters:**

| Parameter | Type   | Required | Description     |
|-----------|--------|----------|-----------------|
| id        | string | Yes      | Wishlist ID     |

**Query Parameters:**

| Parameter | Type   | Required | Description                                     |
|-----------|--------|----------|-------------------------------------------------|
| transferTo| string | No       | ID of wishlist to transfer items to             |

**Request Headers:**

| Header          | Value                      |
|-----------------|----------------------------|
| Content-Type    | application/json           |

**Request Body:**

```json
{
  "_csrf_token": "csrf_token_value"
}
```

**Request Parameters:**

| Parameter   | Type   | Required | Description                                     |
|-------------|--------|----------|-------------------------------------------------|
| _csrf_token | string | Yes      | CSRF token for protection (not required for OAuth2) |

**Response:**

```json
{
  "success": true,
  "message": "Wishlist deleted successfully"
}
```

## Analytics API Endpoints

### Get Analytics Summary

```
GET /api/analytics/summary
```

Returns a summary of wishlist analytics data.

**Authentication Required**: Yes (Admin Session or OAuth2 with admin scope)

**Response:**

```json
{
  "totalWishlists": 150,
  "totalItems": 450,
  "totalShares": 75,
  "totalConversions": 30
}
```

## Error Responses

All API endpoints return standardized error responses in the following format:

```json
{
  "errors": [
    {
      "code": "ERROR_CODE",
      "title": "Error Title",
      "detail": "Detailed error message"
    }
  ]
}
```

### Common Error Codes

| Error Code                  | HTTP Status | Description                                     |
|-----------------------------|-------------|-------------------------------------------------|
| WISHLIST__UNAUTHORIZED      | 401         | User is not authenticated                       |
| WISHLIST__ACCESS_DENIED     | 403         | User does not have permission                   |
| WISHLIST__INVALID_CSRF_TOKEN| 403         | Invalid CSRF token provided                     |
| WISHLIST__NOT_FOUND         | 404         | Wishlist not found                              |
| WISHLIST__CREATE_FAILED     | 500         | Failed to create wishlist                       |
| WISHLIST__UPDATE_FAILED     | 500         | Failed to update wishlist                       |
| WISHLIST__DELETE_FAILED     | 500         | Failed to delete wishlist                       |
| WISHLIST__INVALID_TARGET    | 403         | Target wishlist is invalid                      |
| WISHLIST__TARGET_NOT_FOUND  | 404         | Target wishlist not found                       |

## Rate Limiting

API requests are subject to rate limiting to prevent abuse. The current limits are:

- 100 requests per hour for authenticated users
- 20 requests per hour for unauthenticated users

When a rate limit is exceeded, the API will return a 429 Too Many Requests response with a Retry-After header indicating when the client can make requests again.