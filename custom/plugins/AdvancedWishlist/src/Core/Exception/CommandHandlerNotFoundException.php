<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Exception;

/**
 * Exception thrown when a command handler is not found.
 */
class CommandHandlerNotFoundException extends \RuntimeException
{
    public function __construct(string $commandClass, ?\Throwable $previous = null)
    {
        $message = "No handler found for command: {$commandClass}";
        parent::__construct($message, 0, $previous);
    }
}