<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Tests\Unit\Internal;

use evgenmil\WebhookStorage\Internal\TableNameGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableNameGuard::class)]
final class TableNameGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validNamesProvider(): iterable
    {
        yield 'simple lowercase'           => ['webhooks'];
        yield 'snake case'                 => ['webhooks_amocrm'];
        yield 'leading underscore'         => ['_internal'];
        yield 'mixed case with digits'     => ['Webhooks2024'];
        yield 'single letter'              => ['a'];
        yield 'single underscore'          => ['_'];
        yield 'long but valid identifier'  => [str_repeat('a', 64)];
    }

    #[Test]
    #[DataProvider('validNamesProvider')]
    public function it_accepts_valid_identifiers(string $name): void
    {
        TableNameGuard::assertValid($name);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNamesProvider(): iterable
    {
        yield 'empty string'      => [''];
        yield 'leading digit'     => ['1webhooks'];
        yield 'space inside'      => ['webhooks amocrm'];
        yield 'leading space'     => [' webhooks'];
        yield 'trailing space'    => ['webhooks '];
        yield 'dash'              => ['webhooks-amocrm'];
        yield 'dot'               => ['schema.webhooks'];
        yield 'semicolon'         => ['webhooks;'];
        yield 'backtick'          => ['`webhooks`'];
        yield 'quote'             => ["webhooks'"];
        yield 'sql injection'     => ['a; DROP TABLE users; --'];
        yield 'cyrillic letters'  => ['вебхуки'];
        yield 'unicode digits'    => ["webhooks\u{0660}"];
        yield 'trailing newline'  => ["webhooks\n"];
        yield 'trailing CRLF'     => ["webhooks\r\n"];
        yield 'newline in middle' => ["web\nhooks"];
    }

    #[Test]
    #[DataProvider('invalidNamesProvider')]
    public function it_rejects_invalid_identifiers(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid table name');

        TableNameGuard::assertValid($name);
    }
}
