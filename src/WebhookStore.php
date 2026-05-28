<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage;

/**
 * Публичный фасад модуля. Единственное место, где source -> table.
 * Репозиторий ниже не знает о понятии "источник".
 */
final class WebhookStore
{
    public function __construct(
        private readonly WebhookRepositoryInterface $repository,
        private readonly SourceTableMap             $tables,
    ) {}

    /**
     * @param array<mixed> $payload
     */
    public function save(string $source, string $externalEventId, array $payload): SaveResult
    {
        return $this->repository->save(
            $this->tables->tableFor($source),
            $externalEventId,
            $payload,
        );
    }

    public function markProcessing(string $source, int $id): void
    {
        $this->repository->markProcessing($this->tables->tableFor($source), $id);
    }

    public function markDone(string $source, int $id): void
    {
        $this->repository->markDone($this->tables->tableFor($source), $id);
    }

    public function markFailed(string $source, int $id, string $error): void
    {
        $this->repository->markFailed($this->tables->tableFor($source), $id, $error);
    }
}
