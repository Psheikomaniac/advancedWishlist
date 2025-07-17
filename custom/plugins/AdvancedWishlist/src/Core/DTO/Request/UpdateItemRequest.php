<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateItemRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;

    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $itemId;

    #[Assert\Positive]
    private ?int $quantity = null;

    #[Assert\Type('string')]
    #[Assert\Length(max: 500)]
    private ?string $note = null;

    #[Assert\Type('int')]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $priority = null;

    #[Assert\Type('float')]
    #[Assert\Positive]
    private ?float $priceAlertThreshold = null;

    #[Assert\Type('bool')]
    private ?bool $priceAlertActive = null;

    public function validate(): array
    {
        return [];
    }
}
