<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\GuestWishlistMergeLog;

use AdvancedWishlist\Core\Content\GuestWishlist\GuestWishlistEntity;
use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class GuestWishlistMergeLogEntity extends Entity
{
    protected string $guestWishlistId;
    protected ?GuestWishlistEntity $guestWishlist = null;
    protected string $customerWishlistId;
    protected ?WishlistEntity $customerWishlist = null;
    protected string $customerId;
    protected ?CustomerEntity $customer = null;
    protected string $guestId;
    protected int $itemsMerged;
    protected int $itemsSkipped;
    protected ?string $mergeStrategy = null;
    protected ?array $mergeData = null;
    protected \DateTimeInterface $mergedAt;

    public function getGuestWishlistId(): string
    {
        return $this->guestWishlistId;
    }

    public function setGuestWishlistId(string $guestWishlistId): void
    {
        $this->guestWishlistId = $guestWishlistId;
    }

    public function getGuestWishlist(): ?GuestWishlistEntity
    {
        return $this->guestWishlist;
    }

    public function setGuestWishlist(?GuestWishlistEntity $guestWishlist): void
    {
        $this->guestWishlist = $guestWishlist;
    }

    public function getCustomerWishlistId(): string
    {
        return $this->customerWishlistId;
    }

    public function setCustomerWishlistId(string $customerWishlistId): void
    {
        $this->customerWishlistId = $customerWishlistId;
    }

    public function getCustomerWishlist(): ?WishlistEntity
    {
        return $this->customerWishlist;
    }

    public function setCustomerWishlist(?WishlistEntity $customerWishlist): void
    {
        $this->customerWishlist = $customerWishlist;
    }

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

    public function getGuestId(): string
    {
        return $this->guestId;
    }

    public function setGuestId(string $guestId): void
    {
        $this->guestId = $guestId;
    }

    public function getItemsMerged(): int
    {
        return $this->itemsMerged;
    }

    public function setItemsMerged(int $itemsMerged): void
    {
        $this->itemsMerged = $itemsMerged;
    }

    public function getItemsSkipped(): int
    {
        return $this->itemsSkipped;
    }

    public function setItemsSkipped(int $itemsSkipped): void
    {
        $this->itemsSkipped = $itemsSkipped;
    }

    public function getMergeStrategy(): ?string
    {
        return $this->mergeStrategy;
    }

    public function setMergeStrategy(?string $mergeStrategy): void
    {
        $this->mergeStrategy = $mergeStrategy;
    }

    public function getMergeData(): ?array
    {
        return $this->mergeData;
    }

    public function setMergeData(?array $mergeData): void
    {
        $this->mergeData = $mergeData;
    }

    public function getMergedAt(): \DateTimeInterface
    {
        return $this->mergedAt;
    }

    public function setMergedAt(\DateTimeInterface $mergedAt): void
    {
        $this->mergedAt = $mergedAt;
    }
}
