<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Tests\Unit\Schema;

use evgenmil\WebhookStorage\Internal\TableNameGuard;
use evgenmil\WebhookStorage\Schema\WebhookSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookSchema::class)]
#[UsesClass(TableNameGuard::class)]
final class WebhookSchemaTest extends TestCase
{
    #[Test]
    public function create_table_sql_contains_all_required_columns_and_indexes(): void
    {
        $sql = WebhookSchema::createTableSql('webhooks_amocrm');

        self::assertStringContainsString('CREATE TABLE `webhooks_amocrm`', $sql);

        self::assertMatchesRegularExpression('/\bid\s+BIGINT\s+UNSIGNED\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', $sql);
        self::assertStringContainsString('external_event_id VARCHAR(191) NOT NULL', $sql);
        self::assertStringContainsString('payload           JSON         NOT NULL', $sql);
        self::assertStringContainsString("status            ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending'", $sql);
        self::assertStringContainsString('attempts          INT UNSIGNED NOT NULL DEFAULT 0', $sql);
        self::assertStringContainsString('last_error        TEXT         NULL', $sql);
        self::assertStringContainsString('received_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP', $sql);
        self::assertStringContainsString('updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', $sql);

        self::assertStringContainsString('UNIQUE KEY uk_external_event_id (external_event_id)', $sql);
        self::assertStringContainsString('KEY idx_status (status)', $sql);

        self::assertStringContainsString('ENGINE=InnoDB', $sql);
        self::assertStringContainsString('DEFAULT CHARSET=utf8mb4', $sql);
        self::assertStringContainsString('COLLATE=utf8mb4_unicode_ci', $sql);
    }

    #[Test]
    public function create_table_sql_uses_backticks_around_table_name(): void
    {
        $sql = WebhookSchema::createTableSql('custom_table');

        self::assertStringContainsString('`custom_table`', $sql);
        self::assertStringNotContainsString('`amocrm`', $sql);
    }

    #[Test]
    public function drop_table_sql_uses_if_exists_and_backticks(): void
    {
        $sql = WebhookSchema::dropTableSql('webhooks_bitrix24');

        self::assertSame('DROP TABLE IF EXISTS `webhooks_bitrix24`;', $sql);
    }

    #[Test]
    public function create_table_sql_rejects_invalid_table_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid table name');

        WebhookSchema::createTableSql('bad name');
    }

    #[Test]
    public function drop_table_sql_rejects_invalid_table_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid table name');

        WebhookSchema::dropTableSql('1bad');
    }
}
