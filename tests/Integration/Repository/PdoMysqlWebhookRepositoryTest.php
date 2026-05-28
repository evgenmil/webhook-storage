<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Tests\Integration\Repository;

use evgenmil\WebhookStorage\Repository\PdoMysqlWebhookRepository;
use evgenmil\WebhookStorage\SaveResult;
use evgenmil\WebhookStorage\Status;
use evgenmil\WebhookStorage\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(PdoMysqlWebhookRepository::class)]
final class PdoMysqlWebhookRepositoryTest extends IntegrationTestCase
{
    private PdoMysqlWebhookRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PdoMysqlWebhookRepository($this->pdo);
    }

    #[Test]
    public function save_inserts_new_row_with_pending_status_and_zero_attempts(): void
    {
        $result = $this->repo->save($this->table, 'evt-1', ['hello' => 'world']);

        self::assertInstanceOf(SaveResult::class, $result);
        self::assertGreaterThan(0, $result->id);
        self::assertFalse($result->isDuplicate);

        $row = $this->fetchRow($result->id);
        self::assertNotNull($row);
        self::assertSame('evt-1', $row['external_event_id']);
        self::assertSame(Status::Pending->value, $row['status']);
        self::assertSame(0, (int) $row['attempts']);
        self::assertNull($row['last_error']);
        self::assertSame(['hello' => 'world'], json_decode($row['payload'], true));
    }

    #[Test]
    public function save_stores_unicode_payload_without_escaping(): void
    {
        $payload = ['msg' => 'Привет, мир', 'emoji' => 'статус-ок'];

        $result = $this->repo->save($this->table, 'evt-unicode', $payload);

        $row = $this->fetchRow($result->id);
        self::assertNotNull($row);
        self::assertSame($payload, json_decode($row['payload'], true));
        self::assertStringContainsString('Привет', $row['payload']);
    }

    #[Test]
    public function save_is_idempotent_by_external_event_id(): void
    {
        $first  = $this->repo->save($this->table, 'evt-dup', ['n' => 1]);
        $second = $this->repo->save($this->table, 'evt-dup', ['n' => 2]);

        self::assertFalse($first->isDuplicate);
        self::assertTrue($second->isDuplicate);
        self::assertSame($first->id, $second->id);
        self::assertSame(1, $this->countRows(), 'Duplicate must not create a second row.');

        $row = $this->fetchRow($first->id);
        self::assertNotNull($row);
        self::assertSame(['n' => 1], json_decode($row['payload'], true),
            'Duplicate save must not overwrite the original payload.');
    }

    #[Test]
    public function mark_processing_sets_status_and_increments_attempts(): void
    {
        $id = $this->repo->save($this->table, 'evt-proc', [])->id;

        $this->repo->markProcessing($this->table, $id);

        $row = $this->fetchRow($id);
        self::assertNotNull($row);
        self::assertSame(Status::Processing->value, $row['status']);
        self::assertSame(1, (int) $row['attempts']);
    }

    #[Test]
    public function mark_processing_increments_attempts_on_each_call(): void
    {
        $id = $this->repo->save($this->table, 'evt-retry', [])->id;

        $this->repo->markProcessing($this->table, $id);
        $this->repo->markProcessing($this->table, $id);
        $this->repo->markProcessing($this->table, $id);

        $row = $this->fetchRow($id);
        self::assertNotNull($row);
        self::assertSame(3, (int) $row['attempts']);
    }

    #[Test]
    public function mark_done_clears_last_error(): void
    {
        $id = $this->repo->save($this->table, 'evt-done', [])->id;
        $this->repo->markFailed($this->table, $id, 'boom');
        $this->repo->markDone($this->table, $id);

        $row = $this->fetchRow($id);
        self::assertNotNull($row);
        self::assertSame(Status::Done->value, $row['status']);
        self::assertNull($row['last_error']);
    }

    #[Test]
    public function mark_failed_writes_error_and_sets_failed_status(): void
    {
        $id = $this->repo->save($this->table, 'evt-fail', [])->id;

        $this->repo->markFailed($this->table, $id, 'database connection lost');

        $row = $this->fetchRow($id);
        self::assertNotNull($row);
        self::assertSame(Status::Failed->value, $row['status']);
        self::assertSame('database connection lost', $row['last_error']);
    }

    #[Test]
    public function failed_then_retry_keeps_attempts_growing_and_can_succeed(): void
    {
        $id = $this->repo->save($this->table, 'evt-cycle', [])->id;

        $this->repo->markProcessing($this->table, $id);
        $this->repo->markFailed($this->table, $id, 'tmp error');

        $row = $this->fetchRow($id);
        self::assertSame(Status::Failed->value, $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertSame('tmp error', $row['last_error']);

        $this->repo->markProcessing($this->table, $id);
        $this->repo->markDone($this->table, $id);

        $row = $this->fetchRow($id);
        self::assertSame(Status::Done->value, $row['status']);
        self::assertSame(2, (int) $row['attempts']);
        self::assertNull($row['last_error']);
    }

    #[Test]
    public function save_rejects_invalid_table_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->save('bad name; DROP TABLE x', 'evt', []);
    }

    #[Test]
    public function mark_processing_rejects_invalid_table_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->markProcessing('1bad', 1);
    }

    #[Test]
    public function mark_done_rejects_invalid_table_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->markDone('1bad', 1);
    }

    #[Test]
    public function mark_failed_rejects_invalid_table_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->markFailed('1bad', 1, 'x');
    }

    #[Test]
    public function save_propagates_non_duplicate_pdo_exception(): void
    {
        $this->expectException(\PDOException::class);
        $this->repo->save('definitely_not_existing_table_xyz', 'evt', []);
    }

    #[Test]
    public function mark_methods_are_no_ops_for_missing_id(): void
    {
        $this->repo->markProcessing($this->table, 999_999);
        $this->repo->markDone($this->table, 999_999);
        $this->repo->markFailed($this->table, 999_999, 'x');

        self::assertSame(0, $this->countRows());
    }
}
