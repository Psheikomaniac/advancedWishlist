<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Specification;

/**
 * Interface for the Specification pattern.
 * Specifications encapsulate business rules and can be combined using boolean logic.
 */
interface SpecificationInterface
{
    /**
     * Check if the specification is satisfied by the given candidate.
     */
    public function isSatisfiedBy(object $candidate): bool;

    /**
     * Combine this specification with another using AND logic.
     */
    public function and(SpecificationInterface $other): SpecificationInterface;

    /**
     * Combine this specification with another using OR logic.
     */
    public function or(SpecificationInterface $other): SpecificationInterface;

    /**
     * Negate this specification using NOT logic.
     */
    public function not(): SpecificationInterface;
}