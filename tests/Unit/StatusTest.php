<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Tests\Unit;

use evgenmil\WebhookStorage\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Status::class)]
final class StatusTest extends TestCase
{
    #[Test]
    public function it_exposes_lifecycle_cases_with_lowercase_values(): void
    {
        self::assertSame('pending',    Status::Pending->value);
        self::assertSame('processing', Status::Processing->value);
        self::assertSame('done',       Status::Done->value);
        self::assertSame('failed',     Status::Failed->value);
    }

    #[Test]
    public function it_can_be_built_from_string_value(): void
    {
        self::assertSame(Status::Pending,    Status::from('pending'));
        self::assertSame(Status::Processing, Status::from('processing'));
        self::assertSame(Status::Done,       Status::from('done'));
        self::assertSame(Status::Failed,     Status::from('failed'));
    }

    #[Test]
    public function it_has_exactly_four_cases(): void
    {
        self::assertCount(4, Status::cases());
    }

    #[Test]
    public function values_match_schema_enum_definition(): void
    {
        $values = array_map(static fn (Status $s): string => $s->value, Status::cases());

        self::assertSame(['pending', 'processing', 'done', 'failed'], $values);
    }
}
