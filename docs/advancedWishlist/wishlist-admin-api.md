# Admin API Documentation - Advanced Wishlist System

## Overview

The Admin API provides complete management functions for the Wishlist System. It enables administrators to monitor, analyze, and manage all wishlist-related data.

## Authentication

Admin API uses OAuth2 with Client Credentials Flow:

```http
POST /api/oauth/token
Content-Type: application/json

{
  "grant_type": "client_credentials",
  "client_id": "SWIAADMIN123456",
  "client_secret": "your-client-secret"
}
```

Response:
```json
{
  "token_type": "Bearer",
  "expires_in": 600,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

All subsequent requests require:
```http
Authorization: Bearer {access_token}
```

## Wishlist Management Endpoints

### List All Wishlists

```http
GET /api/wishlists
Authorization: Bearer {token}
```

Query Parameters:
- `page` (integer): Page number (default: 1)
- `limit` (integer): Items per page (default: 25, max: 500)
- `sort` (string): Sort field and direction (e.g., `-createdAt`)
- `filter` (object): Filter criteria
- `includes` (array): Additional associations to load
- `aggregations` (array): Aggregations to calculate

Example with filters:
```http
GET /api/wishlists?filter[customer.email]=*@example.com&filter[itemCount][gte]=5&sort=-createdAt
```

Response:
```json
{
  "data": [
    {
      "id": "0189abcd-ef12-3456-7890-abcdef123456",
      "type": "wishlist",
      "attributes": {
        "customerId": "0189abcd-ef12-3456-7890-abcdef123457",
        "name": "Birthday Wishlist",
        "description": "My birthday wishes",
        "type": "private",
        "isDefault": true,
        "itemCount": 12,
        "totalValue": 599.99,
        "createdAt": "2024-01-15T10:30:00.000Z",
        "updatedAt": "2024-01-20T14:45:00.000Z",
        "customFields": {}
      },
      "relationships": {
        "customer": {
          "data": {
            "type": "customer",
            "id": "0189abcd-ef12-3456-7890-abcdef123457"
          }
        },
        "items": {
          "links": {
            "related": "/api/wishlists/0189abcd-ef12-3456-7890-abcdef123456/items"
          }
        }
      }
    }
  ],
  "included": [],
  "meta": {
    "total": 1523,
    "page": 1,
    "limit": 25,
    "totalPages": 61
  },
  "aggregations": {
    "itemCount": {
      "sum": 18276,
      "avg": 12.0,
      "min": 0,
      "max": 98
    }
  }
}
```

### Get Wishlist Details

```http
GET /api/wishlists/{id}
Authorization: Bearer {token}
```

Query Parameters:
- `includes` (array): Include related entities (e.g., `customer,items.product`)

Response includes full wishlist details with requested associations.

### Update Wishlist

```http
PATCH /api/wishlists/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Updated Wishlist Name",
  "type": "public",
  "customFields": {
    "internal_note": "Verified by admin"
  }
}
```

### Delete Wishlist

```http
DELETE /api/wishlists/{id}
Authorization: Bearer {token}
```

### Bulk Operations

```http
POST /api/_action/wishlist/bulk
Authorization: Bearer {token}
Content-Type: application/json

{
  "operations": [
    {
      "action": "delete",
      "entity": "wishlist",
      "criteria": {
        "type": "filter",
        "filter": [
          {
            "type": "range",
            "field": "createdAt",
            "parameters": {
              "lt": "2023-01-01T00:00:00.000Z"
            }
          }
        ]
      }
    }
  ]
}
```

## Analytics Endpoints

### Get Analytics Overview

```http
GET /api/_action/wishlist/analytics/overview
Authorization: Bearer {token}
```

Query Parameters:
- `startDate` (string): Start date (ISO 8601)
- `endDate` (string): End date (ISO 8601)
- `salesChannelId` (string): Filter by sales channel
- `interval` (string): Grouping interval (hour/day/week/month)

Response:
```json
{
  "overview": {
    "totalWishlists": 15234,
    "activeWishlists": 8923,
    "totalItems": 89234,
    "totalValue": 2345678.90,
    "conversionRate": 23.5,
    "avgItemsPerWishlist": 5.8,
    "avgDaysToConvert": 12.3
  },
  "trends": [
    {
      "date": "2024-01-01",
      "wishlists": 234,
      "items": 1234,
      "conversions": 56,
      "revenue": 12345.67
    }
  ],
  "topProducts": [
    {
      "productId": "0189abcd-ef12-3456-7890-abcdef123456",
      "productName": "Premium Widget",
      "productNumber": "SW10001",
      "wishlistCount": 234,
      "conversionRate": 34.5,
      "revenue": 23456.78
    }
  ]
}
```

### Export Analytics Report

```http
POST /api/_action/wishlist/analytics/export
Authorization: Bearer {token}
Content-Type: application/json

{
  "type": "comprehensive",
  "format": "xlsx",
  "startDate": "2024-01-01",
  "endDate": "2024-01-31",
  "salesChannelId": null,
  "sections": [
    "overview",
    "trends",
    "products",
    "customers",
    "conversions"
  ]
}
```

Response:
```json
{
  "taskId": "0189abcd-ef12-3456-7890-abcdef123456",
  "status": "processing",
  "estimatedTime": 30
}
```

Check export status:
```http
GET /api/_action/wishlist/analytics/export/{taskId}
```

### Real-time Analytics

```http
GET /api/_action/wishlist/analytics/realtime
Authorization: Bearer {token}
```

Response:
```json
{
  "currentActive": {
    "wishlists": 234,
    "users": 189,
    "itemsBeingAdded": 12
  },
  "last5Minutes": {
    "wishlistsCreated": 5,
    "itemsAdded": 45,
    "itemsRemoved": 8,
    "conversions": 3
  },
  "trending": [
    {
      "productId": "0189abcd-ef12-3456-7890-abcdef123456",
      "addedCount": 12,
      "trend": "+240%"
    }
  ]
}
```

## Customer Management

### Get Customer Wishlist Summary

```http
GET /api/_action/wishlist/customer/{customerId}/summary
Authorization: Bearer {token}
```

Response:
```json
{
  "customer": {
    "id": "0189abcd-ef12-3456-7890-abcdef123456",
    "email": "customer@example.com",
    "firstName": "John",
    "lastName": "Doe"
  },
  "statistics": {
    "totalWishlists": 3,
    "totalItems": 24,
    "totalValue": 899.99,
    "conversions": 5,
    "conversionValue": 234.56,
    "avgDaysToConvert": 8.5,
    "lastActivity": "2024-01-25T16:30:00.000Z"
  },
  "wishlists": [
    {
      "id": "0189abcd-ef12-3456-7890-abcdef123456",
      "name": "Birthday List",
      "itemCount": 12,
      "value": 399.99,
      "createdAt": "2024-01-01T10:00:00.000Z"
    }
  ]
}
```

### Merge Customer Wishlists

```http
POST /api/_action/wishlist/customer/merge
Authorization: Bearer {token}
Content-Type: application/json

{
  "sourceCustomerId": "0189abcd-ef12-3456-7890-abcdef123456",
  "targetCustomerId": "0189abcd-ef12-3456-7890-abcdef123457",
  "mergeStrategy": "combine",
  "deleteDuplicates": true
}
```

## Product Management

### Get Product Wishlist Analytics

```http
GET /api/_action/wishlist/product/{productId}/analytics
Authorization: Bearer {token}
```

Response:
```json
{
  "product": {
    "id": "0189abcd-ef12-3456-7890-abcdef123456",
    "name": "Premium Widget",
    "productNumber": "SW10001"
  },
  "statistics": {
    "currentWishlists": 234,
    "totalAdded": 567,
    "totalRemoved": 333,
    "conversions": 123,
    "conversionRate": 21.7,
    "avgDaysOnWishlist": 14.5,
    "priceAlerts": 45
  },
  "trends": {
    "daily": [...],
    "weekly": [...],
    "monthly": [...]
  },
  "customerSegments": {
    "new": 45,
    "returning": 189,
    "vip": 23
  }
}
```

### Find Products Without Wishlists

```http
GET /api/_action/wishlist/product/without-wishlists
Authorization: Bearer {token}
```

Query Parameters:
- `categoryId` (string): Filter by category
- `manufacturerId` (string): Filter by manufacturer
- `minPrice` (number): Minimum price
- `maxPrice` (number): Maximum price

## Configuration Management

### Get Wishlist Configuration

```http
GET /api/_action/wishlist/config
Authorization: Bearer {token}
```

Response:
```json
{
  "limits": {
    "maxWishlistsPerCustomer": 10,
    "maxItemsPerWishlist": 100,
    "guestWishlistTTL": 2592000
  },
  "features": {
    "guestWishlistEnabled": true,
    "socialSharingEnabled": true,
    "priceAlertsEnabled": true,
    "analyticsEnabled": true
  },
  "notifications": {
    "priceDropEnabled": true,
    "backInStockEnabled": true,
    "abandonedWishlistEnabled": true,
    "abandonedWishlistDelay": 604800
  },
  "sharing": {
    "defaultExpiry": 7776000,
    "requirePassword": false,
    "allowGuestPurchase": true
  }
}
```

### Update Configuration

```http
PATCH /api/_action/wishlist/config
Authorization: Bearer {token}
Content-Type: application/json

{
  "limits": {
    "maxWishlistsPerCustomer": 20
  },
  "features": {
    "priceAlertsEnabled": false
  }
}
```

## Maintenance Endpoints

### Clean Up Expired Data

```http
POST /api/_action/wishlist/maintenance/cleanup
Authorization: Bearer {token}
Content-Type: application/json

{
  "cleanupTypes": [
    "expiredGuestWishlists",
    "oldAnalyticsData",
    "orphanedItems"
  ],
  "dryRun": true
}
```

Response:
```json
{
  "summary": {
    "expiredGuestWishlists": {
      "found": 234,
      "cleaned": 0
    },
    "oldAnalyticsData": {
      "found": 12345,
      "cleaned": 0
    },
    "orphanedItems": {
      "found": 5,
      "cleaned": 0
    }
  },
  "dryRun": true,
  "message": "Dry run completed. No data was deleted."
}
```

### Optimize Database

```http
POST /api/_action/wishlist/maintenance/optimize
Authorization: Bearer {token}
```

### Rebuild Analytics Cache

```http
POST /api/_action/wishlist/maintenance/rebuild-cache
Authorization: Bearer {token}
Content-Type: application/json

{
  "cacheTypes": ["analytics", "statistics", "reports"],
  "force": true
}
```

## Webhook Management

### List Webhooks

```http
GET /api/wishlist-webhooks
Authorization: Bearer {token}
```

### Create Webhook

```http
POST /api/wishlist-webhooks
Authorization: Bearer {token}
Content-Type: application/json

{
  "url": "https://example.com/webhook",
  "events": [
    "wishlist.created",
    "wishlist.item.added",
    "wishlist.converted"
  ],
  "active": true,
  "headers": {
    "X-Custom-Header": "value"
  }
}
```

### Webhook Events

Available events:
- `wishlist.created`
- `wishlist.updated`
- `wishlist.deleted`
- `wishlist.item.added`
- `wishlist.item.removed`
- `wishlist.shared`
- `wishlist.converted`
- `price.alert.triggered`

Webhook payload example:
```json
{
  "event": "wishlist.item.added",
  "timestamp": "2024-01-25T16:30:00.000Z",
  "data": {
    "wishlistId": "0189abcd-ef12-3456-7890-abcdef123456",
    "itemId": "0189abcd-ef12-3456-7890-abcdef123457",
    "productId": "0189abcd-ef12-3456-7890-abcdef123458",
    "customerId": "0189abcd-ef12-3456-7890-abcdef123459"
  }
}
```

## Audit Log

### Get Audit Log

```http
GET /api/_action/wishlist/audit-log
Authorization: Bearer {token}
```

Query Parameters:
- `startDate` (string): Start date
- `endDate` (string): End date
- `userId` (string): Filter by admin user
- `action` (string): Filter by action type
- `entityId` (string): Filter by entity ID

Response:
```json
{
  "entries": [
    {
      "id": "0189abcd-ef12-3456-7890-abcdef123456",
      "timestamp": "2024-01-25T16:30:00.000Z",
      "userId": "0189abcd-ef12-3456-7890-abcdef123457",
      "userName": "admin@shop.com",
      "action": "wishlist.deleted",
      "entityType": "wishlist",
      "entityId": "0189abcd-ef12-3456-7890-abcdef123458",
      "changes": {
        "reason": "Customer request"
      },
      "ipAddress": "192.168.1.1",
      "userAgent": "Mozilla/5.0..."
    }
  ],
  "total": 234,
  "page": 1,
  "limit": 25
}
```

## Import/Export

### Export Wishlists

```http
POST /api/_action/wishlist/export
Authorization: Bearer {token}
Content-Type: application/json

{
  "format": "csv",
  "criteria": {
    "filter": [
      {
        "type": "range",
        "field": "createdAt",
        "parameters": {
          "gte": "2024-01-01T00:00:00.000Z"
        }
      }
    ]
  },
  "fields": [
    "id",
    "customer.email",
    "name",
    "itemCount",
    "totalValue",
    "createdAt"
  ]
}
```

### Import Wishlists

```http
POST /api/_action/wishlist/import
Authorization: Bearer {token}
Content-Type: multipart/form-data

file: wishlists.csv
mapping: {
  "customer_email": "customer.email",
  "wishlist_name": "name",
  "created_date": "createdAt"
}
options: {
  "updateExisting": false,
  "createCustomers": false
}
```

## Error Responses

### 400 Bad Request

```json
{
  "errors": [
    {
      "status": "400",
      "code": "WISHLIST__INVALID_FILTER",
      "title": "Invalid filter parameter",
      "detail": "The filter parameter 'invalid_field' is not supported",
      "source": {
        "parameter": "filter[invalid_field]"
      }
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
      "code": "UNAUTHORIZED",
      "title": "Authentication required",
      "detail": "Invalid or expired access token"
    }
  ]
}
```

### 403 Forbidden

```json
{
  "errors": [
    {
      "status": "403",
      "code": "INSUFFICIENT_PERMISSIONS",
      "title": "Insufficient permissions",
      "detail": "You do not have permission to perform this action",
      "meta": {
        "requiredPermission": "wishlist:delete"
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
      "code": "RATE_LIMIT_EXCEEDED",
      "title": "Rate limit exceeded",
      "detail": "API rate limit exceeded",
      "meta": {
        "limit": 1000,
        "remaining": 0,
        "reset": 1706198400
      }
    }
  ]
}
```

## Rate Limiting

| Endpoint Type | Limit | Window |
|--------------|-------|--------|
| Read operations | 1000 | 1 hour |
| Write operations | 100 | 1 hour |
| Analytics | 100 | 1 hour |
| Bulk operations | 10 | 1 hour |
| Export | 5 | 1 hour |

Rate limit headers:
```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 950
X-RateLimit-Reset: 1706198400
```

## Permissions

Required permissions for different operations:

| Operation | Required Permission |
|-----------|-------------------|
| View wishlists | `wishlist:read` |
| Create/Update wishlists | `wishlist:write` |
| Delete wishlists | `wishlist:delete` |
| View analytics | `wishlist:analytics` |
| Export data | `wishlist:export` |
| Manage configuration | `wishlist:config` |
| Maintenance operations | `wishlist:maintenance` |

## API Versioning

The API uses URL versioning:
- Current version: `/api/v1/wishlists`
- Legacy support: `/api/wishlists` (redirects to v1)

Version information in response headers:
```http
X-API-Version: 1.0
X-API-Deprecation: false
```

## SDK Examples

### PHP SDK

```php
use AdvancedWishlist\SDK\WishlistAdminClient;

$client = new WishlistAdminClient([
    'base_uri' => 'https://shop.example.com',
    'client_id' => 'SWIAADMIN123456',
    'client_secret' => 'your-client-secret'
]);

// Get analytics
$analytics = $client->analytics()->getOverview([
    'startDate' => new DateTime('-30 days'),
    'endDate' => new DateTime(),
]);

// Export wishlists
$export = $client->wishlists()->export([
    'format' => 'xlsx',
    'criteria' => [
        'filter' => [
            ['field' => 'itemCount', 'type' => 'gte', 'value' => 5]
        ]
    ]
]);
```

### JavaScript SDK

```javascript
import { WishlistAdminClient } from '@advanced-wishlist/admin-sdk';

const client = new WishlistAdminClient({
    baseURL: 'https://shop.example.com',
    clientId: 'SWIAADMIN123456',
    clientSecret: 'your-client-secret'
});

// Get real-time analytics
const realtime = await client.analytics.getRealtime();

// Bulk delete old wishlists
const result = await client.wishlists.bulkDelete({
    criteria: {
        filter: [
            {
                type: 'range',
                field: 'createdAt',
                parameters: { lt: '2023-01-01' }
            }
        ]
    }
});
```