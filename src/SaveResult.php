<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage;

final class SaveResult
{
    public function __construct(
        public readonly int  $id,
        public readonly bool $isDuplicate,
    ) {}
}
