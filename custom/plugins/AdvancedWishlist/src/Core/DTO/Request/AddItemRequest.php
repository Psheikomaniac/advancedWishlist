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
                $errors['productOptions'] = 'Product options must be key-value pairs of strings';
                break;
            }
        }

        // Validate quantity is reasonable
        if ($this->quantity > 1000) {
            $errors['quantity'] = 'Quantity cannot exceed 1000 items';
        }

        // Validate price alert threshold
        if ($this->priceAlertThreshold !== null && $this->priceAlertThreshold <= 0) {
            $errors['priceAlertThreshold'] = 'Price alert threshold must be positive';
        }

        // Validate priority
        if ($this->priority !== null && ($this->priority < 1 || $this->priority > 5)) {
            $errors['priority'] = 'Priority must be between 1 and 5';
        }

        // Validate line item data structure
        if (!empty($this->lineItemData) && !$this->isValidLineItemData()) {
            $errors['lineItemData'] = 'Invalid line item data structure';
        }

        return $errors;
    }

    private function isValidLineItemData(): bool
    {
        // Basic validation for line item data structure
        if (!is_array($this->lineItemData)) {
            return false;
        }

        // Check for required fields in configurable products
        foreach ($this->lineItemData as $key => $value) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    // Getters and Setters
    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function setWishlistId(string $wishlistId): void
    {
        $this->wishlistId = $wishlistId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): void
    {
        $this->priority = $priority;
    }

    public function getPriceAlertThreshold(): ?float
    {
        return $this->priceAlertThreshold;
    }

    public function setPriceAlertThreshold(?float $priceAlertThreshold): void
    {
        $this->priceAlertThreshold = $priceAlertThreshold;
    }

    public function getProductOptions(): array
    {
        return $this->productOptions;
    }

    public function setProductOptions(array $productOptions): void
    {
        $this->productOptions = $productOptions;
    }

    public function getLineItemData(): array
    {
        return $this->lineItemData;
    }

    public function setLineItemData(array $lineItemData): void
    {
        $this->lineItemData = $lineItemData;
    }
}
