<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\CQRS\Command\Middleware;

use AdvancedWishlist\Core\CQRS\Command\CommandMiddleware;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validation middleware for CQRS command pipeline.
 * Validates commands before execution using Symfony Validator.
 */
class ValidationMiddleware implements CommandMiddleware
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(object $command, callable $next): mixed
    {
        $startTime = microtime(true);

        try {
            // Validate the command
            $violations = $this->validator->validate($command);

            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = [
                        'property' => $violation->getPropertyPath(),
                        'message' => $violation->getMessage(),
                        'invalid_value' => $violation->getInvalidValue()
                    ];
                }

                $this->logger->warning('Command validation failed', [
                    'command' => get_class($command),
                    'violations' => $errors
                ]);

                throw new \InvalidArgumentException(
                    'Command validation failed: ' . json_encode($errors)
                );
            }

            // Proceed to next middleware
            $result = $next($command);

            $duration = microtime(true) - $startTime;
            $this->logger->debug('Command validation completed', [
                'command' => get_class($command),
                'duration_ms' => round($duration * 1000, 2)
            ]);

            return $result;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logger->error('Command validation error', [
                'command' => get_class($command),
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2)
            ]);

            throw $e;
        }
    }
}