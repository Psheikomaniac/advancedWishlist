<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class BulkAddItemsRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;

    #[Assert\Type('array')]
    #[Assert\Count(min: 1, max: 100)]
    #[Assert\All([
        new Assert\Collection([
            'fields' => [
                'productId' => [
                    new Assert\Uuid(),
                    new Assert\NotBlank(),
                ],
                'quantity' => [
                    new Assert\Type('int'),
                    new Assert\Positive(),
                ],
                'note' => [
                    new Assert\Type('string'),
                    new Assert\Length(['max' => 500]),
                ],
            ],
        ]),
    ])]
    private array $items;

    #[Assert\Type('bool')]
    private bool $skipDuplicates = true;

    #[Assert\Type('bool')]
    private bool $mergeQuantities = false;

    public function validate(): array
    {
        $errors = [];

        // Validate items array is not empty
        if (empty($this->items)) {
            $errors['items'] = 'Items array cannot be empty';
            return $errors;
        }

        // Validate each item structure
        foreach ($this->items as $index => $item) {
            if (!is_array($item)) {
                $errors["items[{$index}]"] = 'Each item must be an array';
                continue;
            }

            // Check required fields
            if (empty($item['productId'])) {
                $errors["items[{$index}].productId"] = 'Product ID is required';
            }

            // Validate quantity
            if (isset($item['quantity'])) {
                if (!is_int($item['quantity']) || $item['quantity'] <= 0) {
                    $errors["items[{$index}].quantity"] = 'Quantity must be a positive integer';
                } elseif ($item['quantity'] > 1000) {
                    $errors["items[{$index}].quantity"] = 'Quantity cannot exceed 1000';
                }
            }

            // Validate note length
            if (isset($item['note']) && is_string($item['note']) && strlen($item['note']) > 500) {
                $errors["items[{$index}].note"] = 'Note cannot exceed 500 characters';
            }

            // Validate priority
            if (isset($item['priority']) && (!is_int($item['priority']) || $item['priority'] < 1 || $item['priority'] > 5)) {
                $errors["items[{$index}].priority"] = 'Priority must be an integer between 1 and 5';
            }
        }

        // Validate bulk operation settings combination
        if ($this->skipDuplicates && $this->mergeQuantities) {
            $errors['settings'] = 'Cannot both skip duplicates and merge quantities';
        }

        // Check for duplicate product IDs in the request
        $productIds = array_column($this->items, 'productId');
        if (count($productIds) !== count(array_unique($productIds))) {
            $errors['items'] = 'Duplicate product IDs found in the request';
        }

        return $errors;
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

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function isSkipDuplicates(): bool
    {
        return $this->skipDuplicates;
    }

    public function setSkipDuplicates(bool $skipDuplicates): void
    {
        $this->skipDuplicates = $skipDuplicates;
    }

    public function isMergeQuantities(): bool
    {
        return $this->mergeQuantities;
    }

    public function setMergeQuantities(bool $mergeQuantities): void
    {
        $this->mergeQuantities = $mergeQuantities;
    }
}
