<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage;

/**
 * Контракт хранилища. Принимает уже разрешённое имя таблицы — слои выше
 * (WebhookStore + SourceTableMap) отвечают за маппинг source -> table.
 */
interface WebhookRepositoryInterface
{
    /**
     * Идемпотентная вставка. На дубликате по external_event_id
     * возвращает id существующей записи с isDuplicate = true.
     *
     * @param array<mixed> $payload
     */
    public function save(string $table, string $externalEventId, array $payload): SaveResult;

    /**
     * Переводит запись в 'processing' и инкрементирует attempts.
     */
    public function markProcessing(string $table, int $id): void;

    /**
     * Переводит запись в 'done', сбрасывает last_error.
     */
    public function markDone(string $table, int $id): void;

    /**
     * Переводит запись в 'failed', пишет last_error.
     */
    public function markFailed(string $table, int $id, string $error): void;

    /**
     * Возвращает запись по id или null, если строки нет.
     */
    public function findById(string $table, int $id): ?WebhookRecord;
}
