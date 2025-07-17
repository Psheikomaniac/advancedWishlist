<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class AddItemRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;

    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $productId;

    #[Assert\Positive]
    private int $quantity = 1;

    #[Assert\Type('string')]
    #[Assert\Length(max: 500)]
    private ?string $note = null;

    #[Assert\Type('int')]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $priority = null;

    #[Assert\Type('float')]
    #[Assert\Positive]
    private ?float $priceAlertThreshold = null;

    #[Assert\Type('array')]
    private array $productOptions = [];

    // For configurable products
    #[Assert\Type('array')]
    private array $lineItemData = [];

    public function validate(): array
    {
        $errors = [];

        // Validate product options structure
        foreach ($this->productOptions as $optionId => $optionValue) {
            if (!is_string($optionId) || !is_string($optionValue)) {
                $errors[] = 'Invalid product option format';
            }
        }

        return $errors;
    }
}
