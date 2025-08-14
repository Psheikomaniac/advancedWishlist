<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ValidationException extends ShopwareHttpException
{
    public const VALIDATION_ERROR_CODE = 'WISHLIST__VALIDATION_ERROR';

    private array $errors;

    public function __construct(array $errors, ?\Throwable $previous = null)
    {
        $this->errors = $errors;

        $message = 'Validation failed';
        if (!empty($errors)) {
            $message .= ': ' . $this->formatErrors($errors);
        }

        parent::__construct($message, [], $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return self::VALIDATION_ERROR_CODE;
    }

    public function getValidationErrors(): array
    {
        return $this->errors;
    }

    private function formatErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $field => $error) {
            if (is_array($error)) {
                $messages[] = $field . ': ' . implode(', ', $error);
            } else {
                $messages[] = $field . ': ' . $error;
            }
        }

        return implode('; ', $messages);
    }

    public function getParameters(): array
    {
        return ['errors' => $this->errors];
    }
}