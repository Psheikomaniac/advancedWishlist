<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Specification;

/**
 * AND specification - satisfied when both specifications are satisfied.
 */
class AndSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly SpecificationInterface $left,
        private readonly SpecificationInterface $right
    ) {
    }

    public function isSatisfiedBy(object $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate) 
            && $this->right->isSatisfiedBy($candidate);
    }
}