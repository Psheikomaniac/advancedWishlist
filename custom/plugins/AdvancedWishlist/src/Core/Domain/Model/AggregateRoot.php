<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Model;

use AdvancedWishlist\Core\Domain\Event\DomainEvent;

/**
 * Abstract base class for aggregate roots in the domain model.
 * Implements the Aggregate Root pattern with domain event support.
 */
abstract class AggregateRoot
{
    /**
     * @var DomainEvent[]
     */
    private array $domainEvents = [];

    /**
     * Version for optimistic locking.
     */
    private int $version = 0;

    /**
     * Raise a domain event to be published later.
     */
    protected function raiseEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Get uncommitted domain events.
     * 
     * @return DomainEvent[]
     */
    public function getUncommittedEvents(): array
    {
        return $this->domainEvents;
    }

    /**
     * Mark all events as committed (usually after persistence).
     */
    public function markEventsAsCommitted(): void
    {
        $this->domainEvents = [];
    }

    /**
     * Get the current version for optimistic locking.
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Set the version (used by infrastructure layer).
     */
    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    /**
     * Increment version (called when aggregate is modified).
     */
    protected function incrementVersion(): void
    {
        $this->version++;
    }

    /**
     * Check if the aggregate has uncommitted events.
     */
    public function hasUncommittedEvents(): bool
    {
        return !empty($this->domainEvents);
    }

    /**
     * Get the number of uncommitted events.
     */
    public function getUncommittedEventCount(): int
    {
        return count($this->domainEvents);
    }

    /**
     * Clear all events without marking them as committed.
     * Use with caution - typically only for testing.
     */
    protected function clearEvents(): void
    {
        $this->domainEvents = [];
    }

    /**
     * Apply an event to this aggregate.
     * This method should be overridden by concrete aggregates.
     */
    protected function apply(DomainEvent $event): void
    {
        // Default implementation does nothing
        // Concrete aggregates should override this to handle state changes
    }

    /**
     * Reconstitute the aggregate from a sequence of events.
     * Used in event sourcing scenarios.
     * 
     * @param DomainEvent[] $events
     */
    public function reconstitute(array $events): void
    {
        foreach ($events as $event) {
            $this->apply($event);
            $this->version++;
        }

        // Clear events after reconstitution as they're already applied
        $this->domainEvents = [];
    }
}