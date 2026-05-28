<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Tests\Unit\Exception;

use evgenmil\WebhookStorage\Exception\UnknownSourceException;
use evgenmil\WebhookStorage\Exception\WebhookStorageException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnknownSourceException::class)]
#[CoversClass(WebhookStorageException::class)]
final class UnknownSourceExceptionTest extends TestCase
{
    #[Test]
    public function it_extends_package_base_exception(): void
    {
        $e = new UnknownSourceException('boom');

        self::assertInstanceOf(WebhookStorageException::class, $e);
    }

    #[Test]
    public function package_base_exception_extends_runtime_exception(): void
    {
        $e = new UnknownSourceException('boom');

        self::assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function it_preserves_message_and_code(): void
    {
        $previous = new \RuntimeException('prev');
        $e = new UnknownSourceException('Unknown webhook source: x', 42, $previous);

        self::assertSame('Unknown webhook source: x', $e->getMessage());
        self::assertSame(42, $e->getCode());
        self::assertSame($previous, $e->getPrevious());
    }
}
