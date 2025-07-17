<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class AnalyticsQueryRequest extends AbstractRequestDTO
{
    #[Assert\Choice(choices: [
        'top_products',
        'conversion_rate',
        'share_statistics',
        'user_activity',
        'abandoned_wishlists',
    ])]
    private string $metric;

    #[Assert\DateTime]
    #[Assert\NotBlank]
    private \DateTimeInterface $startDate;

    #[Assert\DateTime]
    #[Assert\NotBlank]
    private \DateTimeInterface $endDate;

    #[Assert\Choice(choices: ['hour', 'day', 'week', 'month'])]
    private string $groupBy = 'day';

    #[Assert\Type('array')]
    private array $filters = [];

    #[Assert\Type('int')]
    #[Assert\Range(min: 1, max: 1000)]
    private int $limit = 100;

    #[Assert\Type('int')]
    #[Assert\Range(min: 0)]
    private int $offset = 0;

    public function validate(): array
    {
        $errors = [];

        if ($this->startDate > $this->endDate) {
            $errors[] = 'Start date must be before end date';
        }

        $maxRange = new \DateInterval('P1Y');
        if ($this->startDate->diff($this->endDate) > $maxRange) {
            $errors[] = 'Date range cannot exceed 1 year';
        }

        return $errors;
    }
}
