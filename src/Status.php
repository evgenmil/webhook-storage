<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage;

enum Status: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Done       = 'done';
    case Failed     = 'failed';
}
