<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Domain\Event;

use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Abstract base class for domain events.
 * Provides common functionality for all domain events.
 */
abstract class AbstractDomainEvent implements DomainEvent
{
    private readonly string $eventId;
    private readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        private readonly string $aggregateId,
        private readonly int $version = 1
    ) {
        $this->eventId = Uuid::randomHex();
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getEventName(): string
    {
        return static::class;
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_name' => $this->getEventName(),
            'aggregate_id' => $this->aggregateId,
            'version' => $this->version,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
            'payload' => $this->getPayload()
        ];
    }

    /**
     * Get the event-specific payload data.
     * Must be implemented by concrete event classes.
     */
    abstract protected function getPayload(): array;
}