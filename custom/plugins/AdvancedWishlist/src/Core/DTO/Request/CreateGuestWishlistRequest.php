<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateGuestWishlistRequest extends AbstractRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private string $sessionId;

    #[Assert\Email]
    private ?string $guestEmail = null;

    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    private array $items = [];

    #[Assert\Type('int')]
    private int $ttl = 2592000; // 30 days in seconds

    public function validate(): array
    {
        return [];
    }
}
