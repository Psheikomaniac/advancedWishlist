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
        $errors = [];

        // Validate share method specific requirements
        switch ($this->shareMethod) {
            case 'email':
                if (empty($this->recipientEmail)) {
                    $errors['recipientEmail'] = 'Recipient email is required for email sharing';
                }
                break;

            case 'social':
                if (empty($this->platform)) {
                    $errors['platform'] = 'Platform is required for social sharing';
                }
                if (!in_array($this->platform, ['facebook', 'twitter', 'whatsapp', 'pinterest'], true)) {
                    $errors['platform'] = 'Invalid social platform';
                }
                break;

            case 'link':
                // Link sharing validation
                if ($this->expiresAt !== null && $this->expiresAt <= new \DateTime()) {
                    $errors['expiresAt'] = 'Expiration date must be in the future';
                }
                
                if ($this->password !== null && strlen($this->password) < 4) {
                    $errors['password'] = 'Password must be at least 4 characters long';
                }
                break;
        }

        // Validate message content if provided
        if ($this->message !== null && strlen(trim($this->message)) === 0) {
            $errors['message'] = 'Message cannot be empty if provided';
        }

        // Validate share settings structure
        foreach ($this->shareSettings as $key => $value) {
            if (!is_string($key) || empty($key)) {
                $errors['shareSettings'] = 'Share settings must have non-empty string keys';
                break;
            }
            if (!is_scalar($value) && $value !== null) {
                $errors['shareSettings'] = 'Share settings values must be scalar or null';
                break;
            }
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

    public function getShareMethod(): string
    {
        return $this->shareMethod;
    }

    public function setShareMethod(string $shareMethod): void
    {
        $this->shareMethod = $shareMethod;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(?string $recipientEmail): void
    {
        $this->recipientEmail = $recipientEmail;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function isAllowGuestPurchase(): bool
    {
        return $this->allowGuestPurchase;
    }

    public function setAllowGuestPurchase(bool $allowGuestPurchase): void
    {
        $this->allowGuestPurchase = $allowGuestPurchase;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(?string $platform): void
    {
        $this->platform = $platform;
    }

    public function setShareSettings(array $shareSettings): void
    {
        $this->shareSettings = $shareSettings;
    }
}
