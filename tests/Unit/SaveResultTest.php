<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Tests\Unit;

use evgenmil\WebhookStorage\SaveResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SaveResult::class)]
final class SaveResultTest extends TestCase
{
    #[Test]
    public function it_stores_id_and_duplicate_flag(): void
    {
        $result = new SaveResult(42, false);

        self::assertSame(42, $result->id);
        self::assertFalse($result->isDuplicate);
    }

    #[Test]
    public function duplicate_flag_propagates(): void
    {
        $result = new SaveResult(7, true);

        self::assertSame(7, $result->id);
        self::assertTrue($result->isDuplicate);
    }

    #[Test]
    public function properties_are_readonly(): void
    {
        $result = new SaveResult(1, false);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        /** @phpstan-ignore-next-line readonly write is the point of the test */
        $result->id = 2;
    }
}
