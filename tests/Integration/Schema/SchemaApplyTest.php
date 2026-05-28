<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Tests\Integration\Schema;

use evgenmil\WebhookStorage\Schema\WebhookSchema;
use evgenmil\WebhookStorage\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates that the SQL produced by WebhookSchema is accepted by the target
 * MySQL server. Unit-level tests only check string content; this one talks to
 * a real database.
 */
#[CoversClass(WebhookSchema::class)]
final class SchemaApplyTest extends IntegrationTestCase
{
    #[Test]
    public function created_table_exposes_expected_columns(): void
    {
        $columns = $this->describeColumns($this->table);

        self::assertSame([
            'id',
            'external_event_id',
            'payload',
            'status',
            'attempts',
            'last_error',
            'received_at',
            'updated_at',
        ], array_keys($columns));

        self::assertStringStartsWith('bigint', strtolower($columns['id']['Type']));
        self::assertStringStartsWith('varchar(191)', strtolower($columns['external_event_id']['Type']));
        self::assertStringStartsWith('enum', strtolower($columns['status']['Type']));
        self::assertSame('NO',  $columns['external_event_id']['Null']);
        self::assertSame('YES', $columns['last_error']['Null']);
    }

    #[Test]
    public function unique_index_on_external_event_id_is_enforced(): void
    {
        $this->pdo->exec(
            "INSERT INTO `{$this->table}` (external_event_id, payload)
             VALUES ('dup', '{}')"
        );

        $this->expectException(\PDOException::class);

        $this->pdo->exec(
            "INSERT INTO `{$this->table}` (external_event_id, payload)
             VALUES ('dup', '{}')"
        );
    }

    #[Test]
    public function drop_table_sql_removes_the_table(): void
    {
        $this->pdo->exec(WebhookSchema::dropTableSql($this->table));

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :t"
        );
        $stmt->execute([':t' => $this->table]);
        self::assertSame(0, (int) $stmt->fetchColumn());

        $this->pdo->exec(WebhookSchema::createTableSql($this->table));
    }

    /**
     * @return array<string,array<string,string|null>>
     */
    private function describeColumns(string $table): array
    {
        $rows = $this->pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
        $by = [];
        foreach ($rows as $row) {
            $by[$row['Field']] = $row;
        }

        return $by;
    }
}
