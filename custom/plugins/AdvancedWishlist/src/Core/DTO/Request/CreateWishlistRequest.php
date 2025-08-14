<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\Constraints as Assert;

#[MapRequestPayload]
class CreateWishlistRequest extends AbstractRequestDTO
{
    #[Assert\NotBlank(message: 'wishlist.name.not_blank')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'wishlist.name.too_short',
        maxMessage: 'wishlist.name.too_long'
    )]
    private string $name;

    #[Assert\Type('string')]
    #[Assert\Length(max: 1000)]
    private ?string $description = null;

    #[Assert\Choice(choices: ['private', 'public', 'shared'])]
    private string $type = 'private';

    #[Assert\Type('bool')]
    private bool $isDefault = false;

    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $customerId;

    #[Assert\Uuid]
    private ?string $salesChannelId = null;

    // Getters and Setters
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function validate(): array
    {
        $errors = [];

        // Validate name contains only allowed characters
        if (preg_match('/[<>"\']/', $this->name)) {
            $errors['name'] = 'Wishlist name contains invalid characters';
        }

        // Validate description if provided
        if ($this->description !== null && preg_match('/[<>"\']/', $this->description)) {
            $errors['description'] = 'Wishlist description contains invalid characters';
        }

        // Validate type is one of allowed values
        if (!in_array($this->type, ['private', 'public', 'shared'], true)) {
            $errors['type'] = 'Invalid wishlist type. Must be one of: private, public, shared';
        }

        // Additional business rule: only one default wishlist per customer
        // This validation would typically be done in service layer with database access
        // but we can add basic structure validation here

        return $errors;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }
}
