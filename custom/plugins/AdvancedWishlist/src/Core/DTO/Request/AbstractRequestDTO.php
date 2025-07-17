<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO\Request;

use Shopware\Core\Framework\Struct\Struct;

abstract class AbstractRequestDTO extends Struct
{
    /**
     * Create DTO from request data.
     */
    public static function fromArray(array $data): self
    {
        $dto = new static();
        $dto->assign($data);

        return $dto;
    }

    /**
     * Validate the DTO.
     */
    abstract public function validate(): array;
}
