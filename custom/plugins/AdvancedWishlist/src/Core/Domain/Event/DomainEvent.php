<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Event;

/**
 * Base interface for all domain events.
 * Domain events represent something that happened in the domain.
 */
interface DomainEvent
{
    /**
     * Get the unique event ID.
     */
    public function getEventId(): string;

    /**
     * Get the aggregate ID this event relates to.
     */
    public function getAggregateId(): string;

    /**
     * Get when the event occurred.
     */
    public function occurredAt(): \DateTimeImmutable;

    /**
     * Get the event version (for event sourcing).
     */
    public function getVersion(): int;

    /**
     * Get the event name (typically the class name).
     */
    public function getEventName(): string;

    /**
     * Convert the event to an array for serialization.
     */
    public function toArray(): array;
}