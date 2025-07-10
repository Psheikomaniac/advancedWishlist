<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\WishlistAnalytics;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;

class WishlistAnalyticsEntity extends Entity
{
    use EntityCustomFieldsTrait;

    // Properties with asymmetric visibility - public read, protected write
    public protected(set) string $wishlistId;
    public protected(set) \DateTimeInterface $date;

    // Properties with validation hooks
    public int $views {
        get => $this->views;
        set {
            if ($value < 0) {
                throw new \InvalidArgumentException('Views cannot be negative');
            }
            $this->views = $value;
        }
    }

    public int $shares {
        get => $this->shares;
        set {
            if ($value < 0) {
                throw new \InvalidArgumentException('Shares cannot be negative');
            }
            $this->shares = $value;
        }
    }

    public int $itemsAdded {
        get => $this->itemsAdded;
        set {
            if ($value < 0) {
                throw new \InvalidArgumentException('Items added cannot be negative');
            }
            $this->itemsAdded = $value;
        }
    }

    public int $itemsRemoved {
        get => $this->itemsRemoved;
        set {
            if ($value < 0) {
                throw new \InvalidArgumentException('Items removed cannot be negative');
            }
            $this->itemsRemoved = $value;
        }
    }

    public int $conversions {
        get => $this->conversions;
        set {
            if ($value < 0) {
                throw new \InvalidArgumentException('Conversions cannot be negative');
            }
            $this->conversions = $value;
        }
    }

    public float $conversionValue {
        get => $this->conversionValue;
        set {
            if ($value < 0) {
                throw new \InvalidArgumentException('Conversion value cannot be negative');
            }
            $this->conversionValue = $value;
        }
    }

    protected ?WishlistEntity $wishlist;

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function setWishlistId(string $wishlistId): void
    {
        $this->wishlistId = $wishlistId;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): void
    {
        $this->date = $date;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): void
    {
        $this->views = $views;
    }

    public function getShares(): int
    {
        return $this->shares;
    }

    public function setShares(int $shares): void
    {
        $this->shares = $shares;
    }

    public function getItemsAdded(): int
    {
        return $this->itemsAdded;
    }

    public function setItemsAdded(int $itemsAdded): void
    {
        $this->itemsAdded = $itemsAdded;
    }

    public function getItemsRemoved(): int
    {
        return $this->itemsRemoved;
    }

    public function setItemsRemoved(int $itemsRemoved): void
    {
        $this->itemsRemoved = $itemsRemoved;
    }

    public function getConversions(): int
    {
        return $this->conversions;
    }

    public function setConversions(int $conversions): void
    {
        $this->conversions = $conversions;
    }

    public function getConversionValue(): float
    {
        return $this->conversionValue;
    }

    public function setConversionValue(float $conversionValue): void
    {
        $this->conversionValue = $conversionValue;
    }

    public function getWishlist(): ?WishlistEntity
    {
        return $this->wishlist;
    }

    public function setWishlist(?WishlistEntity $wishlist): void
    {
        $this->wishlist = $wishlist;
    }
}
