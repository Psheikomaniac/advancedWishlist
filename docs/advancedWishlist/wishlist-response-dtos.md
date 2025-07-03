# Response DTOs - Advanced Wishlist System

## Overview

Response DTOs standardize API responses and ensure consistent data structures. They implement JsonSerializable for automatic JSON conversion and provide type safety for frontend developers.

## Base Response DTO

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

use Shopware\Core\Framework\Struct\Struct;

abstract class AbstractResponseDTO extends Struct implements \JsonSerializable
{
    protected array $extensions = [];
    protected array $meta = [];
    
    public function jsonSerialize(): array
    {
        $data = $this->toArray();
        
        if (!empty($this->extensions)) {
            $data['extensions'] = $this->extensions;
        }
        
        if (!empty($this->meta)) {
            $data['meta'] = $this->meta;
        }
        
        return $data;
    }
    
    abstract public function toArray(): array;
    
    public function addExtension(string $key, mixed $data): void
    {
        $this->extensions[$key] = $data;
    }
    
    public function addMeta(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }
}
```

## Wishlist Response DTOs

### WishlistResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;

class WishlistResponse extends AbstractResponseDTO
{
    private string $id;
    private string $customerId;
    private string $name;
    private ?string $description;
    private string $type;
    private bool $isDefault;
    private int $itemCount;
    private ?float $totalValue;
    private ?\DateTimeInterface $createdAt;
    private ?\DateTimeInterface $updatedAt;
    private ?array $items;
    private ?CustomerResponse $customer;
    private ?ShareInfoResponse $shareInfo;
    private array $customFields;
    
    public static function fromEntity(WishlistEntity $entity, bool $includeItems = false): self
    {
        $response = new self();
        
        $response->id = $entity->getId();
        $response->customerId = $entity->getCustomerId();
        $response->name = $entity->getName();
        $response->description = $entity->getDescription();
        $response->type = $entity->getType();
        $response->isDefault = $entity->isDefault();
        $response->itemCount = $entity->getItems()->count();
        $response->createdAt = $entity->getCreatedAt();
        $response->updatedAt = $entity->getUpdatedAt();
        $response->customFields = $entity->getCustomFields() ?? [];
        
        // Calculate total value
        $response->totalValue = $entity->getItems()->reduce(
            fn($carry, $item) => $carry + ($item->getProduct()->getPrice()->getGross() * $item->getQuantity()),
            0.0
        );
        
        // Include items if requested
        if ($includeItems && $entity->getItems()) {
            $response->items = $entity->getItems()->map(
                fn($item) => WishlistItemResponse::fromEntity($item)
            )->getElements();
        }
        
        // Include customer if loaded
        if ($entity->getCustomer()) {
            $response->customer = CustomerResponse::fromEntity($entity->getCustomer());
        }
        
        // Include share info if exists
        if ($entity->getShareInfo()) {
            $response->shareInfo = ShareInfoResponse::fromEntity($entity->getShareInfo());
        }
        
        return $response;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customerId,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'isDefault' => $this->isDefault,
            'itemCount' => $this->itemCount,
            'totalValue' => $this->totalValue,
            'createdAt' => $this->createdAt?->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
            'items' => $this->items,
            'customer' => $this->customer?->toArray(),
            'shareInfo' => $this->shareInfo?->toArray(),
            'customFields' => $this->customFields,
        ];
    }
    
    // Getters...
    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getItemCount(): int { return $this->itemCount; }
    public function getTotalValue(): ?float { return $this->totalValue; }
}
```

### WishlistItemResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemEntity;

class WishlistItemResponse extends AbstractResponseDTO
{
    private string $id;
    private string $wishlistId;
    private string $productId;
    private int $quantity;
    private ?string $note;
    private ?int $priority;
    private ?float $priceAlertThreshold;
    private bool $priceAlertActive;
    private ?\DateTimeInterface $addedAt;
    private ?ProductResponse $product;
    private ?PriceInfoResponse $priceInfo;
    private array $customFields;
    
    public static function fromEntity(WishlistItemEntity $entity): self
    {
        $response = new self();
        
        $response->id = $entity->getId();
        $response->wishlistId = $entity->getWishlistId();
        $response->productId = $entity->getProductId();
        $response->quantity = $entity->getQuantity();
        $response->note = $entity->getNote();
        $response->priority = $entity->getPriority();
        $response->priceAlertThreshold = $entity->getPriceAlertThreshold();
        $response->priceAlertActive = $entity->isPriceAlertActive();
        $response->addedAt = $entity->getAddedAt();
        $response->customFields = $entity->getCustomFields() ?? [];
        
        // Include product if loaded
        if ($entity->getProduct()) {
            $response->product = ProductResponse::fromEntity($entity->getProduct());
            $response->priceInfo = PriceInfoResponse::fromProduct($entity->getProduct());
        }
        
        return $response;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'wishlistId' => $this->wishlistId,
            'productId' => $this->productId,
            'quantity' => $this->quantity,
            'note' => $this->note,
            'priority' => $this->priority,
            'priceAlertThreshold' => $this->priceAlertThreshold,
            'priceAlertActive' => $this->priceAlertActive,
            'addedAt' => $this->addedAt?->format('c'),
            'product' => $this->product?->toArray(),
            'priceInfo' => $this->priceInfo?->toArray(),
            'customFields' => $this->customFields,
        ];
    }
}
```

### ProductResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

use Shopware\Core\Content\Product\ProductEntity;

class ProductResponse extends AbstractResponseDTO
{
    private string $id;
    private string $productNumber;
    private string $name;
    private ?string $description;
    private ?MediaResponse $cover;
    private PriceResponse $price;
    private bool $available;
    private ?int $stock;
    private ?float $rating;
    private ?string $manufacturerName;
    private array $categories;
    private array $properties;
    
    public static function fromEntity(ProductEntity $entity): self
    {
        $response = new self();
        
        $response->id = $entity->getId();
        $response->productNumber = $entity->getProductNumber();
        $response->name = $entity->getTranslated()['name'];
        $response->description = $entity->getTranslated()['description'];
        $response->available = $entity->getAvailable();
        $response->stock = $entity->getStock();
        $response->rating = $entity->getRatingAverage();
        
        // Price
        if ($entity->getCheapestPrice()) {
            $response->price = PriceResponse::fromPrice($entity->getCheapestPrice());
        }
        
        // Cover image
        if ($entity->getCover() && $entity->getCover()->getMedia()) {
            $response->cover = MediaResponse::fromEntity($entity->getCover()->getMedia());
        }
        
        // Manufacturer
        if ($entity->getManufacturer()) {
            $response->manufacturerName = $entity->getManufacturer()->getTranslated()['name'];
        }
        
        // Categories
        if ($entity->getCategories()) {
            $response->categories = $entity->getCategories()->map(
                fn($cat) => [
                    'id' => $cat->getId(),
                    'name' => $cat->getTranslated()['name'],
                    'path' => $cat->getPath()
                ]
            )->getElements();
        }
        
        return $response;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'productNumber' => $this->productNumber,
            'name' => $this->name,
            'description' => $this->description,
            'cover' => $this->cover?->toArray(),
            'price' => $this->price->toArray(),
            'available' => $this->available,
            'stock' => $this->stock,
            'rating' => $this->rating,
            'manufacturerName' => $this->manufacturerName,
            'categories' => $this->categories,
            'properties' => $this->properties,
        ];
    }
}
```

### PriceResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;

class PriceResponse extends AbstractResponseDTO
{
    private float $net;
    private float $gross;
    private string $currencyId;
    private ?float $listPrice;
    private ?float $discount;
    private ?float $percentage;
    
    public static function fromPrice(Price $price): self
    {
        $response = new self();
        
        $response->net = $price->getNet();
        $response->gross = $price->getGross();
        $response->currencyId = $price->getCurrencyId();
        
        if ($price->getListPrice()) {
            $response->listPrice = $price->getListPrice()->getGross();
            $response->discount = $response->listPrice - $response->gross;
            $response->percentage = round(($response->discount / $response->listPrice) * 100, 2);
        }
        
        return $response;
    }
    
    public function toArray(): array
    {
        return [
            'net' => $this->net,
            'gross' => $this->gross,
            'currencyId' => $this->currencyId,
            'listPrice' => $this->listPrice,
            'discount' => $this->discount,
            'percentage' => $this->percentage,
        ];
    }
}
```

## Collection Response DTOs

### WishlistCollectionResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class WishlistCollectionResponse extends AbstractResponseDTO
{
    private array $items;
    private int $total;
    private int $page;
    private int $limit;
    private array $aggregations;
    
    public static function fromSearchResult(EntitySearchResult $searchResult): self
    {
        $response = new self();
        
        $response->items = array_map(
            fn($entity) => WishlistResponse::fromEntity($entity),
            $searchResult->getElements()
        );
        
        $response->total = $searchResult->getTotal();
        $response->page = (int) ($searchResult->getCriteria()->getOffset() / $searchResult->getCriteria()->getLimit()) + 1;
        $response->limit = $searchResult->getCriteria()->getLimit();
        
        // Process aggregations
        foreach ($searchResult->getAggregations() as $name => $aggregation) {
            $response->aggregations[$name] = $aggregation->getResult();
        }
        
        return $response;
    }
    
    public function toArray(): array
    {
        return [
            'items' => array_map(fn($item) => $item->toArray(), $this->items),
            'total' => $this->total,
            'page' => $this->page,
            'limit' => $this->limit,
            'totalPages' => ceil($this->total / $this->limit),
            'aggregations' => $this->aggregations,
        ];
    }
}
```

## Share Response DTOs

### ShareInfoResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareEntity;

class ShareInfoResponse extends AbstractResponseDTO
{
    private string $id;
    private string $token;
    private string $url;
    private string $shortUrl;
    private ?string $qrCode;
    private bool $active;
    private int $views;
    private int $uniqueViews;
    private ?\DateTimeInterface $expiresAt;
    private bool $passwordProtected;
    private array $settings;
    private ?\DateTimeInterface $lastViewedAt;
    
    public static function fromEntity(WishlistShareEntity $entity): self
    {
        $response = new self();
        
        $response->id = $entity->getId();
        $response->token = $entity->getToken();
        $response->url = self::generateUrl($entity->getToken());
        $response->shortUrl = self::generateShortUrl($entity->getToken());
        $response->active = $entity->isActive();
        $response->views = $entity->getViews();
        $response->uniqueViews = $entity->getUniqueViews();
        $response->expiresAt = $entity->getExpiresAt();
        $response->passwordProtected = $entity->hasPassword();
        $response->settings = $entity->getSettings() ?? [];
        $response->lastViewedAt = $entity->getLastViewedAt();
        
        // Generate QR code if requested
        if ($entity->getCustomFields()['generateQr'] ?? false) {
            $response->qrCode = self::generateQrCode($response->url);
        }
        
        return $response;
    }
    
    private static function generateUrl(string $token): string
    {
        return sprintf('%s/wishlist/shared/%s', $_ENV['APP_URL'], $token);
    }
    
    private static function generateShortUrl(string $token): string
    {
        return sprintf('%s/w/%s', $_ENV['SHORT_URL'] ?? $_ENV['APP_URL'], substr($token, 0, 8));
    }
    
    private static function generateQrCode(string $url): string
    {
        // This would use a QR code library
        return 'data:image/png;base64,...';
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'url' => $this->url,
            'shortUrl' => $this->shortUrl,
            'qrCode' => $this->qrCode,
            'active' => $this->active,
            'views' => $this->views,
            'uniqueViews' => $this->uniqueViews,
            'expiresAt' => $this->expiresAt?->format('c'),
            'passwordProtected' => $this->passwordProtected,
            'settings' => $this->settings,
            'lastViewedAt' => $this->lastViewedAt?->format('c'),
        ];
    }
}
```

## Analytics Response DTOs

### AnalyticsResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

class AnalyticsResponse extends AbstractResponseDTO
{
    private string $metric;
    private \DateTimeInterface $startDate;
    private \DateTimeInterface $endDate;
    private array $data;
    private array $summary;
    private array $breakdown;
    
    public function __construct(
        string $metric,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $data
    ) {
        $this->metric = $metric;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->data = $data;
        
        $this->calculateSummary();
        $this->generateBreakdown();
    }
    
    private function calculateSummary(): void
    {
        $this->summary = match($this->metric) {
            'top_products' => [
                'totalProducts' => count($this->data),
                'totalWishlists' => array_sum(array_column($this->data, 'count')),
                'averagePerProduct' => array_sum(array_column($this->data, 'count')) / count($this->data),
            ],
            'conversion_rate' => [
                'overallRate' => array_sum(array_column($this->data, 'converted')) / array_sum(array_column($this->data, 'total')),
                'convertedCount' => array_sum(array_column($this->data, 'converted')),
                'totalCount' => array_sum(array_column($this->data, 'total')),
            ],
            default => []
        };
    }
    
    private function generateBreakdown(): void
    {
        $this->breakdown = match($this->metric) {
            'share_statistics' => [
                'byMethod' => $this->groupByMethod($this->data),
                'byPlatform' => $this->groupByPlatform($this->data),
            ],
            default => []
        };
    }
    
    public function toArray(): array
    {
        return [
            'metric' => $this->metric,
            'period' => [
                'start' => $this->startDate->format('c'),
                'end' => $this->endDate->format('c'),
            ],
            'data' => $this->data,
            'summary' => $this->summary,
            'breakdown' => $this->breakdown,
        ];
    }
}
```

### TopProductsResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

class TopProductsResponse extends AbstractResponseDTO
{
    private array $products;
    private array $trends;
    
    public function __construct(array $productData)
    {
        $this->products = array_map(
            fn($data) => [
                'rank' => $data['rank'],
                'productId' => $data['product_id'],
                'productName' => $data['product_name'],
                'wishlistCount' => $data['wishlist_count'],
                'uniqueCustomers' => $data['unique_customers'],
                'conversionRate' => $data['conversion_rate'],
                'trend' => $data['trend'], // up, down, stable
                'trendPercentage' => $data['trend_percentage'],
            ],
            $productData
        );
        
        $this->calculateTrends();
    }
    
    private function calculateTrends(): void
    {
        $this->trends = [
            'rising' => array_filter($this->products, fn($p) => $p['trend'] === 'up'),
            'falling' => array_filter($this->products, fn($p) => $p['trend'] === 'down'),
            'newEntries' => array_filter($this->products, fn($p) => $p['trend'] === 'new'),
        ];
    }
    
    public function toArray(): array
    {
        return [
            'products' => $this->products,
            'trends' => [
                'risingCount' => count($this->trends['rising']),
                'fallingCount' => count($this->trends['falling']),
                'newEntriesCount' => count($this->trends['newEntries']),
                'topRising' => array_slice($this->trends['rising'], 0, 5),
                'topFalling' => array_slice($this->trends['falling'], 0, 5),
            ],
        ];
    }
}
```

## Error Response DTOs

### ErrorResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

class ErrorResponse extends AbstractResponseDTO
{
    private string $type;
    private string $title;
    private int $status;
    private string $detail;
    private ?string $instance;
    private array $violations;
    
    public static function fromException(\Exception $exception): self
    {
        $response = new self();
        
        $response->type = '/errors/' . self::getErrorType($exception);
        $response->title = self::getErrorTitle($exception);
        $response->status = self::getStatusCode($exception);
        $response->detail = $exception->getMessage();
        $response->instance = request()->getUri();
        
        if ($exception instanceof ValidationException) {
            $response->violations = $exception->getViolations();
        }
        
        return $response;
    }
    
    private static function getErrorType(\Exception $exception): string
    {
        return match(true) {
            $exception instanceof ValidationException => 'validation-failed',
            $exception instanceof NotFoundException => 'not-found',
            $exception instanceof UnauthorizedException => 'unauthorized',
            $exception instanceof LimitExceededException => 'limit-exceeded',
            default => 'internal-error'
        };
    }
    
    private static function getStatusCode(\Exception $exception): int
    {
        return match(true) {
            $exception instanceof ValidationException => 400,
            $exception instanceof NotFoundException => 404,
            $exception instanceof UnauthorizedException => 401,
            $exception instanceof LimitExceededException => 429,
            default => 500
        };
    }
    
    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
        ];
        
        if ($this->instance) {
            $data['instance'] = $this->instance;
        }
        
        if (!empty($this->violations)) {
            $data['violations'] = $this->violations;
        }
        
        return $data;
    }
}
```

### ValidationErrorResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

class ValidationErrorResponse extends ErrorResponse
{
    private array $fieldErrors;
    
    public static function fromValidationErrors(array $errors): self
    {
        $response = new self();
        
        $response->type = '/errors/validation-failed';
        $response->title = 'Validation Failed';
        $response->status = 400;
        $response->detail = 'The request contains invalid data';
        
        $response->fieldErrors = array_map(
            fn($field, $messages) => [
                'field' => $field,
                'messages' => is_array($messages) ? $messages : [$messages],
                'code' => self::getErrorCode($field),
            ],
            array_keys($errors),
            $errors
        );
        
        return $response;
    }
    
    private static function getErrorCode(string $field): string
    {
        // Map field names to error codes
        return match($field) {
            'name' => 'WISHLIST_NAME_INVALID',
            'customerId' => 'CUSTOMER_ID_INVALID',
            'productId' => 'PRODUCT_ID_INVALID',
            default => 'FIELD_INVALID'
        };
    }
    
    public function toArray(): array
    {
        $data = parent::toArray();
        $data['errors'] = $this->fieldErrors;
        
        return $data;
    }
}
```

## Success Response DTOs

### SuccessResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

class SuccessResponse extends AbstractResponseDTO
{
    private bool $success = true;
    private ?string $message;
    private mixed $data;
    private ?string $redirectUrl;
    
    public static function create(string $message = null, mixed $data = null): self
    {
        $response = new self();
        $response->message = $message;
        $response->data = $data;
        
        return $response;
    }
    
    public function withRedirect(string $url): self
    {
        $this->redirectUrl = $url;
        return $this;
    }
    
    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
        ];
        
        if ($this->message) {
            $result['message'] = $this->message;
        }
        
        if ($this->data !== null) {
            $result['data'] = $this->data;
        }
        
        if ($this->redirectUrl) {
            $result['redirectUrl'] = $this->redirectUrl;
        }
        
        return $result;
    }
}
```

### BulkOperationResponse

```php
<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Response;

class BulkOperationResponse extends AbstractResponseDTO
{
    private int $total;
    private int $successful;
    private int $failed;
    private array $results;
    private array $errors;
    
    public function __construct(array $results)
    {
        $this->results = $results;
        $this->total = count($results);
        $this->successful = count(array_filter($results, fn($r) => $r['success']));
        $this->failed = $this->total - $this->successful;
        
        $this->errors = array_filter(
            $results,
            fn($r) => !$r['success']
        );
    }
    
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'successful' => $this->successful,
            'failed' => $this->failed,
            'results' => $this->results,
            'errors' => array_values($this->errors),
        ];
    }
}
```

## Usage Examples

### Controller Response Examples

```php
// Success response
public function create(Request $request): JsonResponse
{
    $wishlist = $this->wishlistService->create($request);
    
    return new JsonResponse(
        WishlistResponse::fromEntity($wishlist, true),
        Response::HTTP_CREATED
    );
}

// Collection response
public function list(Request $request): JsonResponse
{
    $result = $this->wishlistRepository->search($criteria, $context);
    
    return new JsonResponse(
        WishlistCollectionResponse::fromSearchResult($result)
    );
}

// Error response
public function delete(string $id): JsonResponse
{
    try {
        $this->wishlistService->delete($id);
        return new JsonResponse(
            SuccessResponse::create('Wishlist deleted successfully')
        );
    } catch (WishlistNotFoundException $e) {
        return new JsonResponse(
            ErrorResponse::fromException($e),
            $e->getStatusCode()
        );
    }
}
```