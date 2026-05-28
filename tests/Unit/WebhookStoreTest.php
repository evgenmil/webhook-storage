<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Tests\Unit;

use evgenmil\WebhookStorage\Exception\UnknownSourceException;
use evgenmil\WebhookStorage\Exception\WebhookStorageException;
use evgenmil\WebhookStorage\Internal\TableNameGuard;
use evgenmil\WebhookStorage\SaveResult;
use evgenmil\WebhookStorage\SourceTableMap;
use evgenmil\WebhookStorage\WebhookRepositoryInterface;
use evgenmil\WebhookStorage\WebhookStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookStore::class)]
#[UsesClass(SourceTableMap::class)]
#[UsesClass(SaveResult::class)]
#[UsesClass(TableNameGuard::class)]
#[UsesClass(UnknownSourceException::class)]
#[UsesClass(WebhookStorageException::class)]
final class WebhookStoreTest extends TestCase
{
    private WebhookRepositoryInterface&MockObject $repository;

    private WebhookStore $store;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WebhookRepositoryInterface::class);

        $this->store = new WebhookStore(
            $this->repository,
            new SourceTableMap([
                'amocrm'   => 'webhooks_amocrm',
                'bitrix24' => 'webhooks_bitrix24',
            ]),
        );
    }

    #[Test]
    public function save_resolves_source_to_table_and_returns_repository_result(): void
    {
        $payload = ['event' => 'lead.created', 'id' => 1];
        $expected = new SaveResult(123, false);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with('webhooks_amocrm', 'evt-1', $payload)
            ->willReturn($expected);

        $result = $this->store->save('amocrm', 'evt-1', $payload);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function save_propagates_duplicate_flag(): void
    {
        $this->repository
            ->method('save')
            ->willReturn(new SaveResult(7, true));

        $result = $this->store->save('amocrm', 'evt-dup', []);

        self::assertSame(7, $result->id);
        self::assertTrue($result->isDuplicate);
    }

    #[Test]
    public function save_picks_correct_table_per_source(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with('webhooks_bitrix24', 'evt-x', [])
            ->willReturn(new SaveResult(1, false));

        $this->store->save('bitrix24', 'evt-x', []);
    }

    #[Test]
    public function mark_processing_delegates_to_repository_with_resolved_table(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('markProcessing')
            ->with('webhooks_amocrm', 99);

        $this->store->markProcessing('amocrm', 99);
    }

    #[Test]
    public function mark_done_delegates_to_repository_with_resolved_table(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('markDone')
            ->with('webhooks_bitrix24', 5);

        $this->store->markDone('bitrix24', 5);
    }

    #[Test]
    public function mark_failed_passes_error_message_through(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('markFailed')
            ->with('webhooks_amocrm', 17, 'boom');

        $this->store->markFailed('amocrm', 17, 'boom');
    }

    #[Test]
    public function save_throws_unknown_source_and_does_not_touch_repository(): void
    {
        $this->repository->expects(self::never())->method(self::anything());

        $this->expectException(UnknownSourceException::class);
        $this->expectExceptionMessage('Unknown webhook source: tilda');

        $this->store->save('tilda', 'evt', []);
    }

    #[Test]
    public function mark_methods_throw_unknown_source_and_do_not_touch_repository(): void
    {
        $this->repository->expects(self::never())->method(self::anything());

        $cases = [
            fn () => $this->store->markProcessing('tilda', 1),
            fn () => $this->store->markDone('tilda', 1),
            fn () => $this->store->markFailed('tilda', 1, 'x'),
        ];

        foreach ($cases as $call) {
            try {
                $call();
                self::fail('Expected UnknownSourceException to be thrown.');
            } catch (UnknownSourceException $e) {
                self::assertStringContainsString('tilda', $e->getMessage());
            }
        }
    }

    #[Test]
    public function repository_exceptions_propagate_to_caller(): void
    {
        $this->repository
            ->method('save')
            ->willThrowException(new \RuntimeException('db down'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('db down');

        $this->store->save('amocrm', 'evt', []);
    }
}
