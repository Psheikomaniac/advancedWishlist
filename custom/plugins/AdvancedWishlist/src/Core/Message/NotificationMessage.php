<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message for handling notifications asynchronously.
 */
class NotificationMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $recipientId,
        private readonly string $type,
        private readonly string $subject,
        private readonly string $body,
        private readonly array $metadata = [],
        private readonly string $messageId = null
    ) {
        $this->messageId = $messageId ?? uniqid('notification_', true);
    }

    public function getRecipientId(): string
    {
        return $this->recipientId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getId(): string
    {
        return $this->messageId;
    }

    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'recipient_id' => $this->recipientId,
            'type' => $this->type,
            'subject' => $this->subject,
            'body' => $this->body,
            'metadata' => $this->metadata,
            'created_at' => time()
        ];
    }
}