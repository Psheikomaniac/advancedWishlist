<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ShareWishlistRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;

    #[Assert\Choice(choices: ['link', 'email', 'social'])]
    private string $shareMethod = 'link';

    #[Assert\Type('array')]
    private array $shareSettings = [];

    // Email sharing specific
    #[Assert\Email]
    private ?string $recipientEmail = null;

    #[Assert\Type('string')]
    #[Assert\Length(max: 1000)]
    private ?string $message = null;

    // Link sharing specific
    #[Assert\DateTime]
    private ?\DateTimeInterface $expiresAt = null;

    #[Assert\Type('string')]
    #[Assert\Length(min: 4, max: 50)]
    private ?string $password = null;

    #[Assert\Type('bool')]
    private bool $allowGuestPurchase = false;

    // Social sharing specific
    #[Assert\Choice(choices: ['facebook', 'twitter', 'whatsapp', 'pinterest'])]
    private ?string $platform = null;

    public function getShareSettings(): array
    {
        return [
            'expiresAt' => $this->expiresAt,
            'password' => $this->password,
            'allowGuestPurchase' => $this->allowGuestPurchase,
            'readOnly' => $this->shareSettings['readOnly'] ?? true,
            'hideQuantity' => $this->shareSettings['hideQuantity'] ?? false,
            'hidePrices' => $this->shareSettings['hidePrices'] ?? false,
        ];
    }

    public function validate(): array
    {
        return [];
    }
}
