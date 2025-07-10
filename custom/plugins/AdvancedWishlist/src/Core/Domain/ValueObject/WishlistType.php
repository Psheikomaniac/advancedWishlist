<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\ValueObject;

/**
 * Enum representing the type of a wishlist
 */
enum WishlistType: string
{
    /**
     * Private wishlist type - only visible to the owner
     */
    case PRIVATE = 'private';

    /**
     * Public wishlist type - visible to everyone
     */
    case PUBLIC = 'public';

    /**
     * Shared wishlist type - visible to specific users
     */
    case SHARED = 'shared';

    /**
     * Check if this is a private wishlist
     */
    public function isPrivate(): bool
    {
        return $this === self::PRIVATE;
    }

    /**
     * Check if this is a public wishlist
     */
    public function isPublic(): bool
    {
        return $this === self::PUBLIC;
    }

    /**
     * Check if this is a shared wishlist
     */
    public function isShared(): bool
    {
        return $this === self::SHARED;
    }

    /**
     * Check if this wishlist type allows sharing
     */
    public function allowsSharing(): bool
    {
        return in_array($this, [self::PUBLIC, self::SHARED], true);
    }

    /**
     * Get the wishlist type as a string
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Get all valid wishlist types as strings
     * 
     * @return array<string> Array of valid wishlist types
     */
    public static function getValidTypes(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Create a wishlist type from a string
     * 
     * @param string $value The wishlist type as a string
     * @return self|null The wishlist type enum or null if invalid
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
