<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class MoveItemRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $sourceWishlistId;

    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $targetWishlistId;

    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $itemId;

    #[Assert\Type('bool')]
    private bool $copy = false; // false = move, true = copy

    public function validate(): array
    {
        return [];
    }
}
