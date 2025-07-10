<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class WishlistItemEntity extends Entity
{
    // Properties with asymmetric visibility - public read, protected write
    public protected(set) string $wishlistId;
    protected ?WishlistEntity $wishlist = null;
    public protected(set) string $productId;
    public protected(set) string $productVersionId;
    protected ?ProductEntity $product = null;

    // Property with validation hook
    public int $quantity {
        get => $this->quantity;
        set {
            if ($value < 1) {
                throw new \InvalidArgumentException('Quantity must be at least 1');
            }
            $this->quantity = $value;
        }
    }

    protected ?string $note = null;
    protected ?int $priority = null;
    protected ?float $priceAtAddition = null;

    // Property with validation hook
    public ?float $priceAlertThreshold {
        get => $this->priceAlertThreshold;
        set {
            if ($value !== null && $value <= 0) {
                throw new \InvalidArgumentException('Price alert threshold must be greater than 0');
            }
            $this->priceAlertThreshold = $value;
        }
    }

    protected ?bool $priceAlertActive = null;
    protected ?array $customFields = null;

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function setWishlistId(string $wishlistId): void
    {
        $this->wishlistId = $wishlistId;
    }

    public function getWishlist(): ?WishlistEntity
    {
        return $this->wishlist;
    }

    public function setWishlist(?WishlistEntity $wishlist): void
    {
        $this->wishlist = $wishlist;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProductVersionId(): string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): void
    {
        $this->priority = $priority;
    }

    public function getPriceAtAddition(): ?float
    {
        return $this->priceAtAddition;
    }

    public function setPriceAtAddition(?float $priceAtAddition): void
    {
        $this->priceAtAddition = $priceAtAddition;
    }

    public function getPriceAlertThreshold(): ?float
    {
        return $this->priceAlertThreshold;
    }

    public function setPriceAlertThreshold(?float $priceAlertThreshold): void
    {
        $this->priceAlertThreshold = $priceAlertThreshold;
    }

    public function isPriceAlertActive(): ?bool
    {
        return $this->priceAlertActive;
    }

    public function setPriceAlertActive(?bool $priceAlertActive): void
    {
        $this->priceAlertActive = $priceAlertActive;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }
}
