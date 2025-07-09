<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

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
        return $this->name !== null 
            || $this->description !== null
            || $this->type !== null
            || $this->isDefault !== null
            || !empty($this->customFields);
    }
    
    public function toArray(): array
    {
        $data = [];
        
        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        
        if ($this->type !== null) {
            $data['type'] = $this->type;
        }
        
        if ($this->isDefault !== null) {
            $data['isDefault'] = $this->isDefault;
        }
        
        if (!empty($this->customFields)) {
            $data['customFields'] = $this->customFields;
        }
        
        return $data;
    }

    public function validate(): array
    {
        return [];
    }
}
