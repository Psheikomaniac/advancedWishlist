<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist;

use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemCollection;
use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareCollection;
use AdvancedWishlist\Core\Content\Wishlist\Enum\WishlistType;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Enterprise WishlistEntity with PHP 8.4 Property Hooks and Asymmetric Visibility
 * Demonstrates modern PHP features for better encapsulation and performance.
 */
class WishlistEntity extends Entity
{
    // Asymmetric visibility - public read, private write for immutable properties
    public private(set) string $id;
    public private(set) \DateTime $createdAt;

    // Customer relationship with protected write access
    public protected(set) string $customerId;
    protected ?CustomerEntity $customer = null;

    // Property with validation hooks and trimming
    private string $_name;
    public string $name {
        get => $this->_name;
        set {
            $trimmed = trim($value);
            if (mb_strlen($trimmed) < 2) {
                throw new \InvalidArgumentException('Wishlist name must be at least 2 characters long');
            }
            if (mb_strlen($trimmed) > 255) {
                throw new \InvalidArgumentException('Wishlist name cannot exceed 255 characters');
            }
            $this->_name = $trimmed;
        }
    }

    // Description with automatic trimming and null handling
    private ?string $_description = null;
    public ?string $description {
        get => $this->_description;
        set => $this->_description = $value ? trim($value) : null;
    }

    // Type with validation hook
    private string $_type = 'private';
    public string $type {
        get => $this->_type;
        set {
            if (!in_array($value, ['private', 'public', 'shared'], true)) {
                throw new \InvalidArgumentException('Invalid wishlist type. Must be: private, public, or shared');
            }
            $this->_type = $value;
        }
    }

    // Default flag with business logic
    private bool $_isDefault = false;
    public bool $isDefault {
        get => $this->_isDefault;
        set {
            $this->_isDefault = $value;
            // Auto-update timestamp when default status changes
            if ($value) {
                $this->updatedAt = new \DateTime();
            }
        }
    }

    // Channel and language relationships
    public protected(set) ?string $salesChannelId = null;
    protected ?SalesChannelEntity $salesChannel = null;
    public protected(set) ?string $languageId = null;
    protected ?LanguageEntity $language = null;

    // Computed property for item count with caching
    private ?int $_itemCount = null;
    public int $itemCount {
        get {
            if (null === $this->_itemCount) {
                $this->_itemCount = $this->items?->count() ?? 0;
            }

            return $this->_itemCount;
        }
        set {
            $this->_itemCount = $value;
        }
    }

    // Computed property for total value with validation and caching
    private ?float $_totalValue = null;
    public float $totalValue {
        get {
            if (null === $this->_totalValue) {
                $this->_totalValue = $this->calculateTotalValue();
            }

            return $this->_totalValue;
        }
        set {
            if ($value < 0) {
                throw new \InvalidArgumentException('Total value cannot be negative');
            }
            $this->_totalValue = $value;
        }
    }

    // Virtual property for display name
    public string $displayName {
        get => $this->name.($this->isDefault ? ' (Default)' : '').' ['.ucfirst($this->type).']';
    }

    // Virtual property for share status
    public bool $isShared {
        get => !empty($this->shareInfo) && $this->shareInfo->count() > 0;
    }

    // Timestamps with automatic updates
    public protected(set) \DateTime $updatedAt;

    // Collections and custom fields
    protected ?array $customFields = null;
    protected ?WishlistItemCollection $items = null;
    protected ?WishlistShareCollection $shareInfo = null;

    /**
     * Calculate total value of all items in wishlist.
     */
    private function calculateTotalValue(): float
    {
        if (!$this->items) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($this->items as $item) {
            if ($item->getProduct() && $item->getProduct()->getPrice()) {
                $total += $item->getProduct()->getPrice()->getGross();
            }
        }

        return round($total, 2);
    }

    /**
     * Invalidate computed property caches.
     */
    public function invalidateCache(): void
    {
        $this->_itemCount = null;
        $this->_totalValue = null;
        $this->updatedAt = new \DateTime();
    }

    // Legacy getter/setter methods for backward compatibility and framework integration

    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getType(): WishlistType
    {
        return WishlistType::from($this->type);
    }

    public function getMembers(): array
    {
        return $this->customFields['members'] ?? [];
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(?LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getItems(): ?WishlistItemCollection
    {
        return $this->items;
    }

    public function setItems(?WishlistItemCollection $items): void
    {
        $this->items = $items;
        // Invalidate caches when items change
        $this->invalidateCache();
    }

    public function getShareInfo(): ?WishlistShareCollection
    {
        return $this->shareInfo;
    }

    public function setShareInfo(?WishlistShareCollection $shareInfo): void
    {
        $this->shareInfo = $shareInfo;
    }

    /**
     * Factory method for creating new wishlist instances.
     */
    public static function create(
        string $id,
        string $customerId,
        string $name,
        string $type = 'private',
        bool $isDefault = false,
    ): self {
        $wishlist = new self();
        $wishlist->id = $id;
        $wishlist->customerId = $customerId;
        $wishlist->name = $name;
        $wishlist->type = $type;
        $wishlist->isDefault = $isDefault;
        $wishlist->createdAt = new \DateTime();
        $wishlist->updatedAt = new \DateTime();

        return $wishlist;
    }

    /**
     * Add item to wishlist and update computed properties.
     */
    public function addItem($item): void
    {
        if (!$this->items) {
            $this->items = new WishlistItemCollection();
        }

        $this->items->add($item);
        $this->invalidateCache();
    }

    /**
     * Remove item from wishlist and update computed properties.
     */
    public function removeItem($item): void
    {
        if ($this->items) {
            $this->items->remove($item->getId());
            $this->invalidateCache();
        }
    }
}
