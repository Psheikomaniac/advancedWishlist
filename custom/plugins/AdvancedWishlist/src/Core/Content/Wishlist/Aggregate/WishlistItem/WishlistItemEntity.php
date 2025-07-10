<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * Enterprise WishlistItemEntity with PHP 8.4 Property Hooks and Asymmetric Visibility
 * Demonstrates advanced wishlist item management with validation and computed properties
 */
class WishlistItemEntity extends Entity
{
    // Immutable properties - public read, private write
    public private(set) string $id;
    public private(set) \DateTime $createdAt;
    public protected(set) \DateTime $updatedAt;

    // Relationship properties with protected write access
    public protected(set) string $wishlistId;
    protected ?WishlistEntity $wishlist = null;
    public protected(set) string $productId;
    public protected(set) string $productVersionId;
    protected ?ProductEntity $product = null;

    // Quantity with validation and business logic
    private int $_quantity = 1;
    public int $quantity {
        get => $this->_quantity;
        set {
            if ($value < 1) {
                throw new \InvalidArgumentException('Quantity must be at least 1');
            }
            if ($value > 999) {
                throw new \InvalidArgumentException('Quantity cannot exceed 999');
            }
            $this->_quantity = $value;
            $this->updatedAt = new \DateTime();
        }
    }

    // Note with trimming and length validation
    private ?string $_note = null;
    public ?string $note {
        get => $this->_note;
        set {
            if ($value !== null) {
                $trimmed = trim($value);
                if (mb_strlen($trimmed) > 500) {
                    throw new \InvalidArgumentException('Note cannot exceed 500 characters');
                }
                $this->_note = $trimmed ?: null;
            } else {
                $this->_note = null;
            }
            $this->updatedAt = new \DateTime();
        }
    }

    // Priority with validation (1-5 scale)
    private ?int $_priority = null;
    public ?int $priority {
        get => $this->_priority;
        set {
            if ($value !== null && ($value < 1 || $value > 5)) {
                throw new \InvalidArgumentException('Priority must be between 1 and 5');
            }
            $this->_priority = $value;
            $this->updatedAt = new \DateTime();
        }
    }

    // Price tracking with automatic updates
    public protected(set) ?float $priceAtAddition = null;

    // Price alert threshold with validation
    private ?float $_priceAlertThreshold = null;
    public ?float $priceAlertThreshold {
        get => $this->_priceAlertThreshold;
        set {
            if ($value !== null && $value <= 0) {
                throw new \InvalidArgumentException('Price alert threshold must be greater than 0');
            }
            $this->_priceAlertThreshold = $value;
            $this->updatedAt = new \DateTime();
        }
    }

    // Price alert status with automatic activation
    private ?bool $_priceAlertActive = null;
    public ?bool $priceAlertActive {
        get => $this->_priceAlertActive;
        set {
            $this->_priceAlertActive = $value;
            if ($value) {
                $this->updatedAt = new \DateTime();
            }
        }
    }

    // Virtual property for current price drop calculation
    public ?float $priceDrop {
        get {
            if (!$this->priceAtAddition || !$this->product?->getPrice()) {
                return null;
            }
            $currentPrice = $this->product->getPrice()->getGross();
            return max(0, $this->priceAtAddition - $currentPrice);
        }
    }

    // Virtual property for price drop percentage
    public ?float $priceDropPercentage {
        get {
            if (!$this->priceAtAddition || !$this->priceDrop) {
                return null;
            }
            return round(($this->priceDrop / $this->priceAtAddition) * 100, 2);
        }
    }

    // Virtual property to check if price alert should trigger
    public bool $shouldTriggerPriceAlert {
        get {
            if (!$this->priceAlertActive || !$this->priceAlertThreshold || !$this->product?->getPrice()) {
                return false;
            }
            return $this->product->getPrice()->getGross() <= $this->priceAlertThreshold;
        }
    }

    // Virtual property for total value (quantity * current price)
    public float $totalValue {
        get {
            if (!$this->product?->getPrice()) {
                return 0.0;
            }
            return round($this->quantity * $this->product->getPrice()->getGross(), 2);
        }
    }

    // Virtual property for display priority
    public string $priorityDisplay {
        get => match ($this->priority) {
            1 => 'Very Low',
            2 => 'Low', 
            3 => 'Medium',
            4 => 'High',
            5 => 'Very High',
            default => 'No Priority'
        };
    }

    protected ?array $customFields = null;

    // Legacy methods for framework compatibility
    public function getWishlist(): ?WishlistEntity
    {
        return $this->wishlist;
    }

    public function setWishlist(?WishlistEntity $wishlist): void
    {
        $this->wishlist = $wishlist;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
        
        // Auto-capture price when product is set for the first time
        if ($product && !$this->priceAtAddition && $product->getPrice()) {
            $this->priceAtAddition = $product->getPrice()->getGross();
        }
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    /**
     * Factory method for creating new wishlist items
     */
    public static function create(
        string $id,
        string $wishlistId,
        string $productId,
        string $productVersionId,
        int $quantity = 1
    ): self {
        $item = new self();
        $item->id = $id;
        $item->wishlistId = $wishlistId;
        $item->productId = $productId;
        $item->productVersionId = $productVersionId;
        $item->quantity = $quantity;
        $item->createdAt = new \DateTime();
        $item->updatedAt = new \DateTime();
        
        return $item;
    }

    /**
     * Check if this item qualifies for any discounts or promotions
     */
    public function checkPromotionEligibility(): array
    {
        $promotions = [];
        
        // Price drop promotion
        if ($this->priceDropPercentage && $this->priceDropPercentage >= 10) {
            $promotions[] = [
                'type' => 'price_drop',
                'discount' => $this->priceDropPercentage,
                'message' => "Price dropped by {$this->priceDropPercentage}%!"
            ];
        }
        
        // Quantity discount
        if ($this->quantity >= 5) {
            $promotions[] = [
                'type' => 'bulk_discount',
                'quantity' => $this->quantity,
                'message' => 'Eligible for bulk discount!'
            ];
        }
        
        // High priority item
        if ($this->priority === 5) {
            $promotions[] = [
                'type' => 'priority_item',
                'message' => 'High priority item - consider purchasing soon!'
            ];
        }
        
        return $promotions;
    }

    /**
     * Generate analytics data for this item
     */
    public function getAnalyticsData(): array
    {
        return [
            'item_id' => $this->id,
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'priority' => $this->priority,
            'has_note' => !empty($this->note),
            'has_price_alert' => $this->priceAlertActive,
            'price_at_addition' => $this->priceAtAddition,
            'current_price' => $this->product?->getPrice()?->getGross(),
            'price_drop' => $this->priceDrop,
            'price_drop_percentage' => $this->priceDropPercentage,
            'total_value' => $this->totalValue,
            'days_in_wishlist' => $this->createdAt ? $this->createdAt->diff(new \DateTime())->days : 0,
            'should_trigger_alert' => $this->shouldTriggerPriceAlert,
            'promotion_eligible' => !empty($this->checkPromotionEligibility()),
        ];
    }

    /**
     * Update quantity with business logic
     */
    public function updateQuantity(int $newQuantity): void
    {
        $oldQuantity = $this->quantity;
        $this->quantity = $newQuantity; // Uses property hook validation
        
        // Log quantity change for analytics
        if ($oldQuantity !== $newQuantity) {
            $this->customFields = array_merge($this->customFields ?? [], [
                'quantity_history' => [
                    'old' => $oldQuantity,
                    'new' => $newQuantity,
                    'changed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]
            ]);
        }
    }

    /**
     * Activate price alert with automatic threshold setting
     */
    public function activatePriceAlert(?float $threshold = null): void
    {
        if ($threshold) {
            $this->priceAlertThreshold = $threshold;
        } elseif (!$this->priceAlertThreshold && $this->product?->getPrice()) {
            // Auto-set threshold to 10% below current price
            $currentPrice = $this->product->getPrice()->getGross();
            $this->priceAlertThreshold = round($currentPrice * 0.9, 2);
        }
        
        $this->priceAlertActive = true;
    }

    /**
     * Convert to array for API responses (with virtual properties)
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'wishlistId' => $this->wishlistId,
            'productId' => $this->productId,
            'quantity' => $this->quantity,
            'note' => $this->note,
            'priority' => $this->priority,
            'priorityDisplay' => $this->priorityDisplay,
            'priceAtAddition' => $this->priceAtAddition,
            'priceAlertThreshold' => $this->priceAlertThreshold,
            'priceAlertActive' => $this->priceAlertActive,
            'priceDrop' => $this->priceDrop,
            'priceDropPercentage' => $this->priceDropPercentage,
            'totalValue' => $this->totalValue,
            'shouldTriggerPriceAlert' => $this->shouldTriggerPriceAlert,
            'isShared' => $this->wishlist?->isShared ?? false,
            'promotions' => $this->checkPromotionEligibility(),
            'createdAt' => $this->createdAt->format('c'),
            'updatedAt' => $this->updatedAt->format('c'),
        ];
    }
}
