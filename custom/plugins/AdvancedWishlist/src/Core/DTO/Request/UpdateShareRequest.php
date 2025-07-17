<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateShareRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $shareId;

    #[Assert\DateTime]
    private ?\DateTimeInterface $expiresAt = null;

    #[Assert\Type('string')]
    private ?string $password = null;

    #[Assert\Type('bool')]
    private ?bool $active = null;

    #[Assert\Type('array')]
    private array $settings = [];

    public function validate(): array
    {
        return [];
    }
}
