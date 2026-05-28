<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage;

use evgenmil\WebhookStorage\Exception\UnknownSourceException;
use evgenmil\WebhookStorage\Internal\TableNameGuard;

final class SourceTableMap
{
    /** @var array<string,string> */
    private readonly array $map;

    /**
     * @param array<string,string> $map source slug => table name
     */
    public function __construct(array $map)
    {
        foreach ($map as $table) {
            TableNameGuard::assertValid($table);
        }
        $this->map = $map;
    }

    public function tableFor(string $source): string
    {
        return $this->map[$source]
            ?? throw new UnknownSourceException("Unknown webhook source: {$source}");
    }
}
