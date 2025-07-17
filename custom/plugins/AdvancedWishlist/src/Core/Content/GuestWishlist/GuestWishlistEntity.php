<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Content\GuestWishlist;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class GuestWishlistEntity extends Entity
{
    // Properties with asymmetric visibility - public read, protected write
    public protected(set) string $guestId;
    protected ?string $sessionId = null;
    public protected(set) string $salesChannelId;
    protected ?SalesChannelEntity $salesChannel = null;
    public protected(set) string $languageId;
    protected ?LanguageEntity $language = null;
    public protected(set) string $currencyId;
    protected ?CurrencyEntity $currency = null;

    // Property with validation hook
    public ?string $name {
        get => $this->name;
        set {
            if (null !== $value && strlen($value) < 3) {
                throw new \InvalidArgumentException('Name must be at least 3 characters long');
            }
            $this->name = $value;
        }
    }

    // Items array with computed property for count
    public array $items;
    public int $itemCount {
        get => count($this->items ?? []);
        set => $this->itemCount = $value;
    }

    public protected(set) \DateTimeInterface $expiresAt;
    protected ?string $ipAddress = null;
    protected ?string $userAgent = null;
    protected ?string $deviceFingerprint = null;
    protected ?\DateTimeInterface $reminderSentAt = null;
    protected ?string $reminderEmail = null;
    protected ?array $conversionTracking = null;

    public function getGuestId(): string
    {
        return $this->guestId;
    }

    public function setGuestId(string $guestId): void
    {
        $this->guestId = $guestId;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(?LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(?CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function getItemCount(): ?int
    {
        return $this->itemCount;
    }

    public function setItemCount(?int $itemCount): void
    {
        $this->itemCount = $itemCount;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getDeviceFingerprint(): ?string
    {
        return $this->deviceFingerprint;
    }

    public function setDeviceFingerprint(?string $deviceFingerprint): void
    {
        $this->deviceFingerprint = $deviceFingerprint;
    }

    public function getReminderSentAt(): ?\DateTimeInterface
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeInterface $reminderSentAt): void
    {
        $this->reminderSentAt = $reminderSentAt;
    }

    public function getReminderEmail(): ?string
    {
        return $this->reminderEmail;
    }

    public function setReminderEmail(?string $reminderEmail): void
    {
        $this->reminderEmail = $reminderEmail;
    }

    public function getConversionTracking(): ?array
    {
        return $this->conversionTracking;
    }

    public function setConversionTracking(?array $conversionTracking): void
    {
        $this->conversionTracking = $conversionTracking;
    }
}
