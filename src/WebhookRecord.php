<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage;

final class WebhookRecord
{
    /**
     * @param array<mixed> $payload
     */
    public function __construct(
        public readonly int                 $id,
        public readonly string              $externalEventId,
        public readonly array               $payload,
        public readonly Status              $status,
        public readonly int                 $attempts,
        public readonly ?string             $lastError,
        public readonly \DateTimeImmutable  $receivedAt,
        public readonly \DateTimeImmutable  $updatedAt,
    ) {}
}
