<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class MergeGuestWishlistRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $guestWishlistId;

    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $customerWishlistId;

    #[Assert\Choice(choices: ['merge', 'replace', 'skip'])]
    private string $conflictResolution = 'merge';

    public function validate(): array
    {
        return [];
    }
}
