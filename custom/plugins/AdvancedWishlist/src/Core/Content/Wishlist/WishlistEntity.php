<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistItem\WishlistItemCollection;
use AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare\WishlistShareCollection;

class WishlistEntity extends Entity
{
    // Properties with asymmetric visibility - public read, protected write
    public protected(set) string $customerId;
    protected ?CustomerEntity $customer = null;

    // Property with validation hook
    public string $name {
        get => $this->name;
        set {
            if (strlen($value) < 3) {
                throw new \InvalidArgumentException('Name too short');
            }
            $this->name = $value;
        }
    }

    protected ?string $description = null;
    protected string $type;
    protected bool $isDefault;
    protected ?string $salesChannelId = null;
    protected ?SalesChannelEntity $salesChannel = null;
    protected ?string $languageId = null;
    protected ?LanguageEntity $language = null;

    // Computed property for item count
    public int $itemCount {
        get => $this->items?->count() ?? 0;
        set => $this->itemCount = $value;
    }

    // Property with validation hook
    public float $totalValue {
        get => $this->totalValue;
        set {
            if ($value < 0) {
                throw new \InvalidArgumentException('Total value cannot be negative');
            }
            $this->totalValue = $value;
        }
    }

    protected ?array $customFields = null;
    protected ?WishlistItemCollection $items = null;
    protected ?WishlistShareCollection $shareInfo = null;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public function setLanguageId(?string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(?LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getItemCount(): int
    {
        return $this->itemCount;
    }

    public function setItemCount(int $itemCount): void
    {
        $this->itemCount = $itemCount;
    }

    public function getTotalValue(): float
    {
        return $this->totalValue;
    }

    public function setTotalValue(float $totalValue): void
    {
        $this->totalValue = $totalValue;
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
    }

    public function getShareInfo(): ?WishlistShareCollection
    {
        return $this->shareInfo;
    }

    public function setShareInfo(?WishlistShareCollection $shareInfo): void
    {
        $this->shareInfo = $shareInfo;
    }
}
