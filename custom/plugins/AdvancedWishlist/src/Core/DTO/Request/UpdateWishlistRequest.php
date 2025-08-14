<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\Constraints as Assert;

#[MapRequestPayload]
class UpdateWishlistRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;

    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'wishlist.name.too_short',
        maxMessage: 'wishlist.name.too_long'
    )]
    private ?string $name = null;

    #[Assert\Type('string')]
    #[Assert\Length(max: 1000)]
    private ?string $description = null;

    #[Assert\Choice(choices: ['private', 'public', 'shared'])]
    private ?string $type = null;

    #[Assert\Type('bool')]
    private ?bool $isDefault = null;

    #[Assert\Type('array')]
    private array $customFields = [];

    // Getters for all fields...

    public function hasChanges(): bool
    {
        return null !== $this->name
            || null !== $this->description
            || null !== $this->type
            || null !== $this->isDefault
            || !empty($this->customFields);
    }

    public function toArray(): array
    {
        $data = [];

        if (null !== $this->name) {
            $data['name'] = $this->name;
        }

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if (null !== $this->type) {
            $data['type'] = $this->type;
        }

        if (null !== $this->isDefault) {
            $data['isDefault'] = $this->isDefault;
        }

        if (!empty($this->customFields)) {
            $data['customFields'] = $this->customFields;
        }

        return $data;
    }

    public function validate(): array
    {
        $errors = [];

        // Validate name if provided
        if ($this->name !== null) {
            if (empty(trim($this->name))) {
                $errors['name'] = 'Wishlist name cannot be empty';
            } elseif (preg_match('/[<>"\']/', $this->name)) {
                $errors['name'] = 'Wishlist name contains invalid characters';
            }
        }

        // Validate description if provided
        if ($this->description !== null && preg_match('/[<>"\']/', $this->description)) {
            $errors['description'] = 'Wishlist description contains invalid characters';
        }

        // Validate type if provided
        if ($this->type !== null && !in_array($this->type, ['private', 'public', 'shared'], true)) {
            $errors['type'] = 'Invalid wishlist type. Must be one of: private, public, shared';
        }

        // Validate that at least one field is being updated
        if (!$this->hasChanges()) {
            $errors['general'] = 'At least one field must be provided for update';
        }

        // Validate custom fields structure
        foreach ($this->customFields as $key => $value) {
            if (!is_string($key) || empty($key)) {
                $errors['customFields'] = 'Custom field keys must be non-empty strings';
                break;
            }
            if (!is_scalar($value) && !is_array($value) && $value !== null) {
                $errors['customFields'] = 'Custom field values must be scalar, array, or null';
                break;
            }
        }

        return $errors;
    }

    // Add getter methods for validation
    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function setWishlistId(string $wishlistId): void
    {
        $this->wishlistId = $wishlistId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(?bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    public function setCustomFields(array $customFields): void
    {
        $this->customFields = $customFields;
    }
}
