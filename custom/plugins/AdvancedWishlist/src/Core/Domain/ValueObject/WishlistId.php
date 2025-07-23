<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\ValueObject;

use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Value object for Wishlist ID.
 * Ensures type safety and validation for wishlist identifiers.
 */
final readonly class WishlistId
{
    public function __construct(
        private string $value
    ) {
        $this->validate();
    }

    /**
     * Create a new WishlistId from string.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Generate a new random WishlistId.
     */
    public static function generate(): self
    {
        return new self(Uuid::randomHex());
    }

    /**
     * Validate the ID format.
     */
    private function validate(): void
    {
        if (empty($this->value)) {
            throw new \InvalidArgumentException('Wishlist ID cannot be empty');
        }

        if (!Uuid::isValid($this->value)) {
            throw new \InvalidArgumentException("Invalid wishlist ID format: {$this->value}");
        }
    }

    /**
     * Get the string representation of the ID.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Get the string representation (magic method).
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Check equality with another WishlistId.
     */
    public function equals(WishlistId $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Get the value for serialization.
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}