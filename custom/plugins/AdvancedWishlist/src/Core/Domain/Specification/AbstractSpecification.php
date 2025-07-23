<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Specification;

/**
 * Abstract base class for specifications.
 * Provides default implementations for boolean logic operations.
 */
abstract class AbstractSpecification implements SpecificationInterface
{
    /**
     * Check if the specification is satisfied by the given candidate.
     * Must be implemented by concrete specifications.
     */
    abstract public function isSatisfiedBy(object $candidate): bool;

    /**
     * Combine this specification with another using AND logic.
     */
    public function and(SpecificationInterface $other): SpecificationInterface
    {
        return new AndSpecification($this, $other);
    }

    /**
     * Combine this specification with another using OR logic.
     */
    public function or(SpecificationInterface $other): SpecificationInterface
    {
        return new OrSpecification($this, $other);
    }

    /**
     * Negate this specification using NOT logic.
     */
    public function not(): SpecificationInterface
    {
        return new NotSpecification($this);
    }
}