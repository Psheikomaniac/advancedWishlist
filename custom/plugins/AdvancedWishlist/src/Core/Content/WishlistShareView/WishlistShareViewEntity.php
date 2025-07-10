<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\WishlistShareView;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use AdvancedWishlist\Core\Content\WishlistShare\WishlistShareEntity;

class WishlistShareViewEntity extends Entity
{
    use EntityCustomFieldsTrait;

    // Properties with asymmetric visibility - public read, protected write
    public protected(set) string $shareId;
    public protected(set) string $visitorId;
    protected ?string $customerId;
    protected ?string $ipAddress;
    protected ?string $userAgent;
    protected ?string $referrer;
    protected ?string $countryCode;
    protected ?string $deviceType;

    // Properties with validation hooks
    public bool $purchased {
        get => $this->purchased;
        set => $this->purchased = $value;
    }

    public ?float $purchaseValue {
        get => $this->purchaseValue;
        set {
            if ($value !== null && $value < 0) {
                throw new \InvalidArgumentException('Purchase value cannot be negative');
            }
            $this->purchaseValue = $value;
        }
    }

    public protected(set) \DateTimeInterface $viewedAt;
    protected ?WishlistShareEntity $share;

    public function getShareId(): string
    {
        return $this->shareId;
    }

    public function setShareId(string $shareId): void
    {
        $this->shareId = $shareId;
    }

    public function getVisitorId(): string
    {
        return $this->visitorId;
    }

    public function setVisitorId(string $visitorId): void
    {
        $this->visitorId = $visitorId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(?string $referrer): void
    {
        $this->referrer = $referrer;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): void
    {
        $this->countryCode = $countryCode;
    }

    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    public function setDeviceType(?string $deviceType): void
    {
        $this->deviceType = $deviceType;
    }

    public function getPurchased(): bool
    {
        return $this->purchased;
    }

    public function setPurchased(bool $purchased): void
    {
        $this->purchased = $purchased;
    }

    public function getPurchaseValue(): ?float
    {
        return $this->purchaseValue;
    }

    public function setPurchaseValue(?float $purchaseValue): void
    {
        $this->purchaseValue = $purchaseValue;
    }

    public function getViewedAt(): \DateTimeInterface
    {
        return $this->viewedAt;
    }

    public function setViewedAt(\DateTimeInterface $viewedAt): void
    {
        $this->viewedAt = $viewedAt;
    }

    public function getShare(): ?WishlistShareEntity
    {
        return $this->share;
    }

    public function setShare(?WishlistShareEntity $share): void
    {
        $this->share = $share;
    }
}
