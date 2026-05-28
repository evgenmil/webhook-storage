<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Tests\Unit;

use evgenmil\WebhookStorage\Exception\UnknownSourceException;
use evgenmil\WebhookStorage\Exception\WebhookStorageException;
use evgenmil\WebhookStorage\Internal\TableNameGuard;
use evgenmil\WebhookStorage\SourceTableMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceTableMap::class)]
#[UsesClass(TableNameGuard::class)]
#[UsesClass(UnknownSourceException::class)]
#[UsesClass(WebhookStorageException::class)]
final class SourceTableMapTest extends TestCase
{
    #[Test]
    public function it_returns_table_for_registered_source(): void
    {
        $map = new SourceTableMap([
            'amocrm'   => 'webhooks_amocrm',
            'bitrix24' => 'webhooks_bitrix24',
        ]);

        self::assertSame('webhooks_amocrm', $map->tableFor('amocrm'));
        self::assertSame('webhooks_bitrix24', $map->tableFor('bitrix24'));
    }

    #[Test]
    public function it_throws_unknown_source_exception_for_unregistered_source(): void
    {
        $map = new SourceTableMap(['amocrm' => 'webhooks_amocrm']);

        $this->expectException(UnknownSourceException::class);
        $this->expectExceptionMessage('Unknown webhook source: bitrix24');

        $map->tableFor('bitrix24');
    }

    #[Test]
    public function unknown_source_exception_is_part_of_package_hierarchy(): void
    {
        $map = new SourceTableMap(['amocrm' => 'webhooks_amocrm']);

        try {
            $map->tableFor('unknown');
            self::fail('Expected UnknownSourceException to be thrown.');
        } catch (WebhookStorageException $e) {
            self::assertInstanceOf(UnknownSourceException::class, $e);
        }
    }

    #[Test]
    public function it_validates_each_table_name_in_constructor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid table name');

        new SourceTableMap([
            'amocrm'   => 'webhooks_amocrm',
            'bitrix24' => 'webhooks; DROP TABLE x',
        ]);
    }

    #[Test]
    public function empty_map_is_allowed_but_any_lookup_fails(): void
    {
        $map = new SourceTableMap([]);

        $this->expectException(UnknownSourceException::class);

        $map->tableFor('amocrm');
    }

    #[Test]
    public function empty_source_key_is_treated_as_unknown(): void
    {
        $map = new SourceTableMap(['amocrm' => 'webhooks_amocrm']);

        $this->expectException(UnknownSourceException::class);

        $map->tableFor('');
    }
}
