<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

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
        // Custom validation logic
        $errors = [];

        // Check if customer already has default wishlist
        if ($this->isDefault) {
            // This would be checked in service layer
            // Just example of custom validation
        }

        return $errors;
    }
}
