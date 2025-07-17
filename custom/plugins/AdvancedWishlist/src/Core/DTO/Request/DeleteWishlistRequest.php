<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class DeleteWishlistRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;

    #[Assert\Type('bool')]
    private bool $force = false;

    #[Assert\Uuid]
    private ?string $transferToWishlistId = null;

    // When deleting, optionally transfer items to another wishlist
    public function shouldTransferItems(): bool
    {
        return null !== $this->transferToWishlistId;
    }

    public function validate(): array
    {
        return [];
    }
}
