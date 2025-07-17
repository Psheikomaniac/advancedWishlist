<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Async;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Async processor for handling asynchronous tasks.
 *
 * This class provides methods for dispatching tasks to be processed asynchronously
 * using Symfony Messenger.
 */
class AsyncProcessor
{
    /**
     * @param MessageBusInterface $messageBus The Symfony Messenger message bus
     * @param LoggerInterface     $logger     Logger for logging async processing
     */
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Dispatch a message to be processed asynchronously.
     *
     * @param object   $message The message to dispatch
     * @param int|null $delay   The delay in milliseconds before processing the message
     */
    public function dispatch(object $message, ?int $delay = null): void
    {
        $stamps = [];

        if (null !== $delay) {
            // Convert milliseconds to milliseconds for DelayStamp
            $stamps[] = new DelayStamp($delay);
        }

        $this->logger->debug('Dispatching async message', [
            'messageClass' => get_class($message),
            'delay' => $delay,
        ]);

        $this->messageBus->dispatch($message, $stamps);
    }

    /**
     * Dispatch a message to be processed asynchronously with a delay.
     *
     * @param object $message The message to dispatch
     * @param int    $delay   The delay in milliseconds before processing the message
     */
    public function dispatchDelayed(object $message, int $delay): void
    {
        $this->dispatch($message, $delay);
    }

    /**
     * Dispatch a message to be processed asynchronously at a specific time.
     *
     * @param object             $message The message to dispatch
     * @param \DateTimeInterface $time    The time at which to process the message
     */
    public function dispatchAt(object $message, \DateTimeInterface $time): void
    {
        $now = new \DateTime();
        $delay = max(0, $time->getTimestamp() - $now->getTimestamp()) * 1000; // Convert to milliseconds

        $this->dispatch($message, (int) $delay);
    }
}
