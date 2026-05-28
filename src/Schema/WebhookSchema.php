<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Schema;

use evgenmil\WebhookStorage\Internal\TableNameGuard;

/**
 * Генератор SQL для миграций приложения.
 * Схема одна и та же для всех источников — разное только имя таблицы.
 */
final class WebhookSchema
{
    public static function createTableSql(string $table): string
    {
        TableNameGuard::assertValid($table);

        return <<<SQL
CREATE TABLE `{$table}` (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_event_id VARCHAR(191) NOT NULL,
    payload           JSON         NOT NULL,
    status            ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
    attempts          INT UNSIGNED NOT NULL DEFAULT 0,
    last_error        TEXT         NULL,
    received_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_external_event_id (external_event_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }

    public static function dropTableSql(string $table): string
    {
        TableNameGuard::assertValid($table);

        return "DROP TABLE IF EXISTS `{$table}`;";
    }
}
