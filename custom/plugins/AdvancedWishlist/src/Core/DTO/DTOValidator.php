<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\DTO;

use AdvancedWishlist\Core\DTO\Request\AbstractRequestDTO;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DTOValidator
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    public function validate(AbstractRequestDTO $dto): array
    {
        $violations = $this->validator->validate($dto);

        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        // Add custom validation errors
        $customErrors = $dto->validate();

        return array_merge($errors, $customErrors);
    }

    public function validateOrThrow(AbstractRequestDTO $dto): void
    {
        $errors = $this->validate($dto);

        if (!empty($errors)) {
            // throw new ValidationException($errors);
        }
    }
}
