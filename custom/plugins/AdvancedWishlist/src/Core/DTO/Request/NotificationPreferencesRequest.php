<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class NotificationPreferencesRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $customerId;

    #[Assert\Type('bool')]
    private bool $priceDropNotifications = true;

    #[Assert\Type('bool')]
    private bool $backInStockNotifications = true;

    #[Assert\Type('bool')]
    private bool $shareNotifications = true;

    #[Assert\Type('bool')]
    private bool $reminderNotifications = false;

    #[Assert\Choice(choices: ['immediate', 'daily', 'weekly'])]
    private string $notificationFrequency = 'immediate';

    #[Assert\Choice(choices: ['email', 'push', 'both'])]
    private string $notificationChannel = 'email';

    public function validate(): array
    {
        $errors = [];

        // Validate frequency choices
        if (!in_array($this->notificationFrequency, ['immediate', 'daily', 'weekly'], true)) {
            $errors['notificationFrequency'] = 'Invalid notification frequency. Must be one of: immediate, daily, weekly';
        }

        // Validate channel choices
        if (!in_array($this->notificationChannel, ['email', 'push', 'both'], true)) {
            $errors['notificationChannel'] = 'Invalid notification channel. Must be one of: email, push, both';
        }

        // Business logic validation: if reminder notifications are enabled, frequency cannot be immediate
        if ($this->reminderNotifications && $this->notificationFrequency === 'immediate') {
            $errors['notificationFrequency'] = 'Reminder notifications cannot use immediate frequency';
        }

        // Validate at least one notification type is enabled
        if (!$this->priceDropNotifications && 
            !$this->backInStockNotifications && 
            !$this->shareNotifications && 
            !$this->reminderNotifications) {
            $errors['general'] = 'At least one notification type must be enabled';
        }

        return $errors;
    }

    // Getters and Setters
    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function isPriceDropNotifications(): bool
    {
        return $this->priceDropNotifications;
    }

    public function setPriceDropNotifications(bool $priceDropNotifications): void
    {
        $this->priceDropNotifications = $priceDropNotifications;
    }

    public function isBackInStockNotifications(): bool
    {
        return $this->backInStockNotifications;
    }

    public function setBackInStockNotifications(bool $backInStockNotifications): void
    {
        $this->backInStockNotifications = $backInStockNotifications;
    }

    public function isShareNotifications(): bool
    {
        return $this->shareNotifications;
    }

    public function setShareNotifications(bool $shareNotifications): void
    {
        $this->shareNotifications = $shareNotifications;
    }

    public function isReminderNotifications(): bool
    {
        return $this->reminderNotifications;
    }

    public function setReminderNotifications(bool $reminderNotifications): void
    {
        $this->reminderNotifications = $reminderNotifications;
    }

    public function getNotificationFrequency(): string
    {
        return $this->notificationFrequency;
    }

    public function setNotificationFrequency(string $notificationFrequency): void
    {
        $this->notificationFrequency = $notificationFrequency;
    }

    public function getNotificationChannel(): string
    {
        return $this->notificationChannel;
    }

    public function setNotificationChannel(string $notificationChannel): void
    {
        $this->notificationChannel = $notificationChannel;
    }
}
