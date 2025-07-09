<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\Wishlist\Aggregate\WishlistShare;

use AdvancedWishlist\Core\Content\Wishlist\WishlistEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class WishlistShareEntity extends Entity
{
    protected string $wishlistId;
    protected ?WishlistEntity $wishlist = null;
    protected string $token;
    protected string $type;
    protected ?string $platform = null;
    protected bool $active;
    protected ?string $password = null;
    protected ?\DateTimeInterface $expiresAt = null;
    protected ?array $settings = null;
    protected int $views;
    protected int $uniqueViews;
    protected int $conversions;
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
