# Store API Documentation - Advanced Wishlist System

## Overview

The Store API provides all endpoints for the frontend. All endpoints follow RESTful principles and use JSON for request/response.

## Authentication

```http
POST /store-api/account/login
Authorization: Bearer {sw-access-key}
Content-Type: application/json

{
  "username": "customer@example.com",
  "password": "password123"
}
```

Response:
```json
{
  "contextToken": "SWSC1234567890",
  "customer": {
    "id": "abc123",
    "email": "customer@example.com"
  }
}
```

All subsequent requests require:
```http
sw-context-token: SWSC1234567890
```

## Wishlist Endpoints

### Get Customer Wishlists

```http
GET /store-api/wishlist
sw-context-token: {token}
```

Query Parameters:
- `limit` (integer): Max results per page (default: 10)
- `page` (integer): Page number (default: 1)
- `sort` (string): Sort field (default: createdAt)
- `order` (string): Sort order ASC/DESC (default: DESC)
- `filter[type]` (string): Filter by type (private/public/shared)

Response:
```json
{
  "items": [
    {
      "id": "0189abcd-ef12-3456-7890-abcdef123456",
      "customerId": "0189abcd-ef12-3456-7890-abcdef123457",
      "name": "Birthday 2024",
      "description": "My birthday wishes",
      "type": "private",
      "isDefault": true,
      "itemCount": 12,
      "totalValue": 299.99,
      "createdAt": "2024-01-15T10:30:00.000Z",
      "updatedAt": "2024-01-20T14:45:00.000Z",
      "items": null,
      "shareInfo": null
    }
  ],
  "total": 3,
  "page": 1,
  "limit": 10,
  "totalPages": 1,
  "aggregations": {
    "itemCount": {
      "sum": 25
    },
    "totalValue": {
      "sum": 1249.97
    }
  }
}
```

### Get Single Wishlist

```http
GET /store-api/wishlist/{id}
sw-context-token: {token}
```

Query Parameters:
- `includes[wishlist]` (array): Fields to include
- `associations[items]` (boolean): Include items (default: false)
- `associations[shareInfo]` (boolean): Include share info (default: false)

Response:
```json
{
  "id": "0189abcd-ef12-3456-7890-abcdef123456",
  "customerId": "0189abcd-ef12-3456-7890-abcdef123457",
  "name": "Birthday 2024",
  "description": "My birthday wishes",
  "type": "private",
  "isDefault": true,
  "itemCount": 12,
  "totalValue": 299.99,
  "items": [
    {
      "id": "0189abcd-ef12-3456-7890-abcdef123458",
      "wishlistId": "0189abcd-ef12-3456-7890-abcdef123456",
      "productId": "0189abcd-ef12-3456-7890-abcdef123459",
      "quantity": 1,
      "note": "Size M, Color Blue",
      "priority": 1,
      "priceAlertThreshold": 29.99,
      "priceAlertActive": true,
      "addedAt": "2024-01-15T10:35:00.000Z",
      "product": {
        "id": "0189abcd-ef12-3456-7890-abcdef123459",
        "productNumber": "SW10001",
        "name": "Premium T-Shirt",
        "description": "High-quality cotton t-shirt",
        "price": {
          "net": 25.21,
          "gross": 29.99,
          "currencyId": "EUR",
          "listPrice": 39.99,
          "discount": 10.00,
          "percentage": 25.0
        },
        "available": true,
        "stock": 125,
        "cover": {
          "url": "https://shop.example.com/media/product/tshirt-blue.jpg",
          "alt": "Premium T-Shirt Blue"
        }
      }
    }
  ],
  "shareInfo": {
    "id": "0189abcd-ef12-3456-7890-abcdef123460",
    "token": "xY3kL9mN2pQ5rS7tU1vW",
    "url": "https://shop.example.com/wishlist/shared/xY3kL9mN2pQ5rS7tU1vW",
    "shortUrl": "https://shop.example.com/w/xY3kL9mN",
    "active": true,
    "views": 42,
    "uniqueViews": 15,
    "expiresAt": null,
    "passwordProtected": false
  }
}
```

### Create Wishlist

```http
POST /store-api/wishlist
sw-context-token: {token}
Content-Type: application/json

{
  "name": "Christmas 2024",
  "description": "My Christmas wishes",
  "type": "private",
  "isDefault": false
}
```

Response:
```json
{
  "id": "0189abcd-ef12-3456-7890-abcdef123461",
  "customerId": "0189abcd-ef12-3456-7890-abcdef123457",
  "name": "Christmas 2024",
  "description": "My Christmas wishes",
  "type": "private",
  "isDefault": false,
  "itemCount": 0,
  "totalValue": 0.00,
  "createdAt": "2024-01-25T16:20:00.000Z",
  "updatedAt": "2024-01-25T16:20:00.000Z"
}
```

### Update Wishlist

```http
PUT /store-api/wishlist/{id}
sw-context-token: {token}
Content-Type: application/json

{
  "name": "Christmas 2024 - Family",
  "description": "Gift ideas for the family",
  "type": "shared",
  "isDefault": true
}
```

### Delete Wishlist

```http
DELETE /store-api/wishlist/{id}
sw-context-token: {token}
```

Query Parameters:
- `transferTo` (string): Wishlist ID to transfer items to

Response:
```json
{
  "success": true,
  "message": "Wishlist deleted successfully"
}
```

## Wishlist Item Endpoints

### Add Item to Wishlist

```http
POST /store-api/wishlist/{wishlistId}/items
sw-context-token: {token}
Content-Type: application/json

{
  "productId": "0189abcd-ef12-3456-7890-abcdef123462",
  "quantity": 2,
  "note": "Size L, Color Black",
  "priceAlertThreshold": 49.99,
  "productOptions": {
    "color": "black",
    "size": "L"
  }
}
```

Response:
```json
{
  "id": "0189abcd-ef12-3456-7890-abcdef123463",
  "wishlistId": "0189abcd-ef12-3456-7890-abcdef123461",
  "productId": "0189abcd-ef12-3456-7890-abcdef123462",
  "quantity": 2,
  "note": "Size L, Color Black",
  "priority": 2,
  "priceAlertThreshold": 49.99,
  "priceAlertActive": true,
  "addedAt": "2024-01-25T16:25:00.000Z",
  "product": {
    "id": "0189abcd-ef12-3456-7890-abcdef123462",
    "productNumber": "SW10002",
    "name": "Premium Hoodie",
    "price": {
      "gross": 59.99
    }
  }
}
```

### Update Wishlist Item

```http
PUT /store-api/wishlist/{wishlistId}/items/{itemId}
sw-context-token: {token}
Content-Type: application/json

{
  "quantity": 3,
  "priority": 1,
  "note": "URGENT! Size L, Color Black",
  "priceAlertThreshold": 45.00
}
```

### Remove Item from Wishlist

```http
DELETE /store-api/wishlist/{wishlistId}/items/{itemId}
sw-context-token: {token}
```

### Move Item Between Wishlists

```http
POST /store-api/wishlist/{wishlistId}/items/{itemId}/move
sw-context-token: {token}
Content-Type: application/json

{
  "targetWishlistId": "0189abcd-ef12-3456-7890-abcdef123464",
  "copy": false
}
```

### Bulk Operations

```http
POST /store-api/wishlist/{wishlistId}/items/bulk
sw-context-token: {token}
Content-Type: application/json

{
  "action": "add",
  "items": [
    {
      "productId": "0189abcd-ef12-3456-7890-abcdef123465",
      "quantity": 1,
      "note": "Variant A"
    },
    {
      "productId": "0189abcd-ef12-3456-7890-abcdef123466",
      "quantity": 2,
      "note": "Variant B"
    }
  ]
}
```

Response:
```json
{
  "total": 2,
  "successful": 2,
  "failed": 0,
  "results": [
    {
      "success": true,
      "itemId": "0189abcd-ef12-3456-7890-abcdef123467",
      "productId": "0189abcd-ef12-3456-7890-abcdef123465"
    },
    {
      "success": true,
      "itemId": "0189abcd-ef12-3456-7890-abcdef123468",
      "productId": "0189abcd-ef12-3456-7890-abcdef123466"
    }
  ]
}
```

## Share Endpoints

### Create Share Link

```http
POST /store-api/wishlist/{wishlistId}/share
sw-context-token: {token}
Content-Type: application/json

{
  "shareMethod": "link",
  "shareSettings": {
    "password": "secret123",
    "expiresAt": "2024-12-31T23:59:59.000Z",
    "hidePrices": false,
    "allowGuestPurchase": true,
    "readOnly": true
  }
}
```

Response:
```json
{
  "id": "0189abcd-ef12-3456-7890-abcdef123469",
  "token": "aB3cD5eF7gH9iJ1kL",
  "url": "https://shop.example.com/wishlist/shared/aB3cD5eF7gH9iJ1kL",
  "shortUrl": "https://shop.example.com/w/aB3cD5eF",
  "qrCode": "data:image/png;base64,iVBORw0KGgoAAAANS...",
  "active": true,
  "views": 0,
  "uniqueViews": 0,
  "expiresAt": "2024-12-31T23:59:59.000Z",
  "passwordProtected": true,
  "settings": {
    "hidePrices": false,
    "allowGuestPurchase": true,
    "readOnly": true
  }
}
```

### Share via Email

```http
POST /store-api/wishlist/{wishlistId}/share
sw-context-token: {token}
Content-Type: application/json

{
  "shareMethod": "email",
  "recipientEmail": "friend@example.com",
  "message": "Check out my wishlist!",
  "shareSettings": {
    "expiresAt": "2024-06-30T23:59:59.000Z"
  }
}
```

### Access Shared Wishlist

```http
GET /store-api/wishlist/shared/{token}
```

Headers (optional):
```http
X-Wishlist-Password: secret123
```

Response:
```json
{
  "id": "0189abcd-ef12-3456-7890-abcdef123456",
  "name": "Birthday 2024",
  "description": "My birthday wishes",
  "itemCount": 12,
  "items": [
    {
      "id": "0189abcd-ef12-3456-7890-abcdef123470",
      "product": {
        "id": "0189abcd-ef12-3456-7890-abcdef123471",
        "name": "Premium T-Shirt",
        "price": null, // Hidden if hidePrices = true
        "available": true,
        "cover": {
          "url": "https://shop.example.com/media/product/tshirt.jpg"
        }
      },
      "quantity": 1,
      "note": "Size M"
    }
  ],
  "owner": {
    "firstName": "John",
    "lastName": "D." // Abbreviated for privacy
  },
  "shareSettings": {
    "hidePrices": false,
    "allowGuestPurchase": true,
    "readOnly": true
  }
}
```

### Revoke Share

```http
DELETE /store-api/wishlist/share/{shareId}
sw-context-token: {token}
```

## Guest Wishlist Endpoints

### Get Guest Wishlist

```http
GET /store-api/guest-wishlist
sw-access-key: {access-key}
```

Headers:
```http
X-Guest-Id: {guest-id-from-cookie}
```

### Add Item to Guest Wishlist

```http
POST /store-api/guest-wishlist/items
sw-access-key: {access-key}
Content-Type: application/json

{
  "productId": "0189abcd-ef12-3456-7890-abcdef123472",
  "quantity": 1,
  "note": "Interesting!"
}
```

### Send Guest Reminder

```http
POST /store-api/guest-wishlist/reminder
sw-access-key: {access-key}
Content-Type: application/json

{
  "email": "guest@example.com",
  "consentNewsletter": true
}
```

### Merge Guest Wishlist (After Login)

```http
POST /store-api/wishlist/merge-guest
sw-context-token: {token}
Content-Type: application/json

{
  "guestId": "{guest-id-from-cookie}",
  "conflictResolution": "merge"
}
```

## Utility Endpoints

### Check Product in Wishlists

```http
GET /store-api/wishlist/check/{productId}
sw-context-token: {token}
```

Response:
```json
{
  "inWishlist": true,
  "wishlists": [
    {
      "id": "0189abcd-ef12-3456-7890-abcdef123456",
      "name": "Birthday 2024",
      "itemId": "0189abcd-ef12-3456-7890-abcdef123473"
    }
  ]
}
```

### Get Wishlist Statistics

```http
GET /store-api/wishlist/statistics
sw-context-token: {token}
```

Response:
```json
{
  "totalWishlists": 3,
  "totalItems": 25,
  "totalValue": 1249.97,
  "topCategories": [
    {
      "id": "0189abcd-ef12-3456-7890-abcdef123474",
      "name": "Clothing",
      "itemCount": 12
    }
  ],
  "priceAlerts": {
    "active": 5,
    "triggered": 2
  },
  "recentActivity": [
    {
      "type": "item_added",
      "wishlistName": "Birthday 2024",
      "productName": "Premium T-Shirt",
      "timestamp": "2024-01-25T16:30:00.000Z"
    }
  ]
}
```

### Search Wishlists

```http
POST /store-api/wishlist/search
sw-context-token: {token}
Content-Type: application/json

{
  "query": "birthday",
  "filters": {
    "type": ["private", "shared"],
    "hasItems": true
  },
  "sort": {
    "field": "createdAt",
    "order": "DESC"
  },
  "limit": 10,
  "page": 1
}
```

## Error Responses

### 400 Bad Request

```json
{
  "errors": [
    {
      "status": "400",
      "code": "WISHLIST__VALIDATION_FAILED",
      "title": "Validation Failed",
      "detail": "The request contains invalid data",
      "errors": [
        {
          "field": "name",
          "messages": ["Name must be at least 3 characters long"],
          "code": "WISHLIST_NAME_INVALID"
        }
      ]
    }
  ]
}
```

### 401 Unauthorized

```json
{
  "errors": [
    {
      "status": "401",
      "code": "WISHLIST__UNAUTHORIZED",
      "title": "Unauthorized",
      "detail": "Customer not logged in"
    }
  ]
}
```

### 404 Not Found

```json
{
  "errors": [
    {
      "status": "404",
      "code": "WISHLIST__NOT_FOUND",
      "title": "Wishlist not found",
      "detail": "The wishlist with id '0189abcd-ef12-3456-7890-abcdef123456' could not be found",
      "meta": {
        "parameters": {
          "wishlistId": "0189abcd-ef12-3456-7890-abcdef123456"
        }
      }
    }
  ]
}
```

### 429 Too Many Requests

```json
{
  "errors": [
    {
      "status": "429",
      "code": "WISHLIST__RATE_LIMIT_EXCEEDED",
      "title": "Rate limit exceeded",
      "detail": "Too many requests. Please try again later.",
      "meta": {
        "retryAfter": 60
      }
    }
  ]
}
```

## Rate Limiting

| Endpoint | Limit | Window |
|----------|-------|--------|
| Create wishlist | 10 | 1 hour |
| Add item | 100 | 1 hour |
| Share wishlist | 20 | 1 hour |
| Access shared | 100 | 1 hour |
| Guest operations | 50 | 1 hour |

## Pagination

Standard pagination parameters:
- `limit`: Items per page (max: 100)
- `page`: Page number (starts at 1)

Response includes:
```json
{
  "items": [...],
  "total": 150,
  "page": 2,
  "limit": 25,
  "totalPages": 6
}
```

## Filtering

Filter syntax:
```
filter[field]=value
filter[field][operator]=value
```

Operators:
- `eq`: Equal (default)
- `neq`: Not equal
- `gt`: Greater than
- `gte`: Greater than or equal
- `lt`: Less than
- `lte`: Less than or equal
- `contains`: Contains string
- `in`: In array

Example:
```
GET /store-api/wishlist?filter[type]=private&filter[itemCount][gte]=5
```

## Sorting

Sort syntax:
```
sort=field:direction,field2:direction
```

Example:
```
GET /store-api/wishlist?sort=createdAt:desc,name:asc
```

## Field Selection

Include only specific fields:
```
includes[wishlist]=id,name,itemCount
includes[product]=id,name,price
```

## Associations

Load related data:
```
associations[items]=true
associations[items][product]=true
associations[items][product][manufacturer]=true
```