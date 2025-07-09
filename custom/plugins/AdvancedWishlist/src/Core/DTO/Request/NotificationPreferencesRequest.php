<?php declare(strict_types=1);

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
        return [];
    }
}
