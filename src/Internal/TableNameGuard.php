<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Internal;

/**
 * Внутренняя утилита: гарантирует, что имя таблицы — безопасный SQL-идентификатор.
 * PDO не умеет параметризовать идентификаторы, поэтому валидируем по белому списку.
 */
final class TableNameGuard
{
    public static function assertValid(string $name): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid table name: {$name}");
        }
    }
}
