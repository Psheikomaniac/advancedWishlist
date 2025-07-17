<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class BulkDeleteItemsRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;

    #[Assert\Type('array')]
    #[Assert\Count(min: 1, max: 100)]
    #[Assert\All([
        new Assert\Uuid(),
    ])]
    private array $itemIds;

    public function validate(): array
    {
        return [];
    }
}
