<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Specification;

/**
 * NOT specification - satisfied when the wrapped specification is not satisfied.
 */
class NotSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly SpecificationInterface $specification
    ) {
    }

    public function isSatisfiedBy(object $candidate): bool
    {
        return !$this->specification->isSatisfiedBy($candidate);
    }
}