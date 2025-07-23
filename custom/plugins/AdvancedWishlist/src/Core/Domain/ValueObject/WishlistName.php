<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\ValueObject;

/**
 * Value object for Wishlist Name.
 * Ensures validation and consistency for wishlist names.
 */
final readonly class WishlistName
{
    private const int MIN_LENGTH = 1;
    private const int MAX_LENGTH = 255;

    public function __construct(
        private string $value
    ) {
        $this->validate();
    }

    /**
     * Create a WishlistName from string.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Validate the name.
     */
    private function validate(): void
    {
        $trimmedValue = trim($this->value);
        
        if (empty($trimmedValue)) {
            throw new \InvalidArgumentException('Wishlist name cannot be empty');
        }

        $length = mb_strlen($trimmedValue);
        if ($length < self::MIN_LENGTH) {
            throw new \InvalidArgumentException(
                "Wishlist name is too short. Minimum length is " . self::MIN_LENGTH . " characters"
            );
        }

        if ($length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                "Wishlist name is too long. Maximum length is " . self::MAX_LENGTH . " characters"
            );
        }

        // Check for invalid characters
        if (preg_match('/[<>"\']/', $trimmedValue)) {
            throw new \InvalidArgumentException(
                'Wishlist name contains invalid characters (<, >, ", \')'
            );
        }
    }

    /**
     * Get the string value.
     */
    public function toString(): string
    {
        return trim($this->value);
    }

    /**
     * Get the string representation (magic method).
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Check equality with another WishlistName.
     */
    public function equals(WishlistName $other): bool
    {
        return $this->toString() === $other->toString();
    }

    /**
     * Get the length of the name.
     */
    public function getLength(): int
    {
        return mb_strlen($this->toString());
    }

    /**
     * Check if the name is within valid length bounds.
     */
    public function isValidLength(): bool
    {
        $length = $this->getLength();
        return $length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH;
    }

    /**
     * Get a truncated version of the name.
     */
    public function truncate(int $maxLength = 50): string
    {
        $name = $this->toString();
        if (mb_strlen($name) <= $maxLength) {
            return $name;
        }

        return mb_substr($name, 0, $maxLength - 3) . '...';
    }

    /**
     * Convert to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'value' => $this->toString(),
            'length' => $this->getLength()
        ];
    }

    /**
     * Get the value for JSON serialization.
     */
    public function jsonSerialize(): string
    {
        return $this->toString();
    }
}