<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\ValueObject;

use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Value object for Customer ID.
 * Ensures type safety and validation for customer identifiers.
 */
final readonly class CustomerId
{
    public function __construct(
        private string $value
    ) {
        $this->validate();
    }

    /**
     * Create a CustomerId from string.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Validate the ID format.
     */
    private function validate(): void
    {
        if (empty($this->value)) {
            throw new \InvalidArgumentException('Customer ID cannot be empty');
        }

        if (!Uuid::isValid($this->value)) {
            throw new \InvalidArgumentException("Invalid customer ID format: {$this->value}");
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
     * Check equality with another CustomerId.
     */
    public function equals(CustomerId $other): bool
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