<?php declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class BulkAddItemsRequest extends AbstractRequestDTO
{
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private string $wishlistId;
    
    #[Assert\Type('array')]
    #[Assert\Count(min: 1, max: 100)]
    #[Assert\All([
        new Assert\Collection([
            'fields' => [
                'productId' => [
                    new Assert\Uuid(),
                    new Assert\NotBlank()
                ],
                'quantity' => [
                    new Assert\Type('int'),
                    new Assert\Positive()
                ],
                'note' => [
                    new Assert\Type('string'),
                    new Assert\Length(['max' => 500])
                ]
            ]
        ])
    ])]
    private array $items;
    
    #[Assert\Type('bool')]
    private bool $skipDuplicates = true;
    
    #[Assert\Type('bool')]
    private bool $mergeQuantities = false;

    public function validate(): array
    {
        return [];
    }
}
