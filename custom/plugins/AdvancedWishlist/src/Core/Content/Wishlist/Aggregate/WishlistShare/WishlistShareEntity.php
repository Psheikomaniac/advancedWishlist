<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class WishlistShareEntity extends Entity
{
    // Public read, protected write for ID fields
    public protected(set) string $wishlistId;
    protected ?WishlistEntity $wishlist = null;

    // Token should be read-only after creation
    public private(set) ?string $token;

    // Property with validation hook
    public string $type {
        get => $this->type;
        set {
            $allowedTypes = ['public', 'private', 'shared', 'temporary'];
            if (!in_array($value, $allowedTypes)) {
                throw new \InvalidArgumentException('Invalid share type. Allowed types: '.implode(', ', $allowedTypes));
            }
            $this->type = $value;
        }
    }

    protected ?string $platform = null;

    // Property with validation hook
    public bool $active {
        get => $this->active;
        set => $this->active = $value;
    }

    // Password should be write-only
    private ?string $password = null;

    // Property with validation hook for expiration date
    public ?\DateTimeInterface $expiresAt {
        get => $this->expiresAt;
        set {
            if (null !== $value && $value < new \DateTime()) {
                throw new \InvalidArgumentException('Expiration date cannot be in the past');
            }
            $this->expiresAt = $value;
        }
    }

    protected ?array $settings = null;

    // Views can be read publicly but only modified internally
    public protected(set) int $views {
        get => $this->views;
        set => $this->views = $value;
    }

    public protected(set) int $uniqueViews {
        get => $this->uniqueViews;
        set => $this->uniqueViews = $value;
    }

    public protected(set) int $conversions {
        get => $this->conversions;
        set => $this->conversions = $value;
    }

    protected ?\DateTimeInterface $lastViewedAt = null;
    protected ?string $createdBy = null;
    protected ?\DateTimeInterface $revokedAt = null;

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

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(?string $platform): void
    {
        $this->platform = $platform;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getSettings(): ?array
    {
        return $this->settings;
    }

    public function setSettings(?array $settings): void
    {
        $this->settings = $settings;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): void
    {
        $this->views = $views;
    }

    public function getUniqueViews(): int
    {
        return $this->uniqueViews;
    }

    public function setUniqueViews(int $uniqueViews): void
    {
        $this->uniqueViews = $uniqueViews;
    }

    public function getConversions(): int
    {
        return $this->conversions;
    }

    public function setConversions(int $conversions): void
    {
        $this->conversions = $conversions;
    }

    public function getLastViewedAt(): ?\DateTimeInterface
    {
        return $this->lastViewedAt;
    }

    public function setLastViewedAt(?\DateTimeInterface $lastViewedAt): void
    {
        $this->lastViewedAt = $lastViewedAt;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getRevokedAt(): ?\DateTimeInterface
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeInterface $revokedAt): void
    {
        $this->revokedAt = $revokedAt;
    }
}
