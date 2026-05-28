<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Repository;

use evgenmil\WebhookStorage\Internal\TableNameGuard;
use evgenmil\WebhookStorage\SaveResult;
use evgenmil\WebhookStorage\Status;
use evgenmil\WebhookStorage\WebhookRecord;
use evgenmil\WebhookStorage\WebhookRepositoryInterface;

final class PdoMysqlWebhookRepository implements WebhookRepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {}

    public function save(string $table, string $externalEventId, array $payload): SaveResult
    {
        TableNameGuard::assertValid($table);

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `{$table}` (external_event_id, payload)
                 VALUES (:eid, :payload)"
            );
            $stmt->execute([':eid' => $externalEventId, ':payload' => $json]);

            return new SaveResult((int) $this->pdo->lastInsertId(), false);

        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $sel = $this->pdo->prepare(
                "SELECT id FROM `{$table}` WHERE external_event_id = :eid"
            );
            $sel->execute([':eid' => $externalEventId]);

            return new SaveResult((int) $sel->fetchColumn(), true);
        }
    }

    public function markProcessing(string $table, int $id): void
    {
        TableNameGuard::assertValid($table);

        $stmt = $this->pdo->prepare(
            "UPDATE `{$table}`
             SET status = :status, attempts = attempts + 1
             WHERE id = :id"
        );
        $stmt->execute([':status' => Status::Processing->value, ':id' => $id]);
    }

    public function markDone(string $table, int $id): void
    {
        TableNameGuard::assertValid($table);

        $stmt = $this->pdo->prepare(
            "UPDATE `{$table}`
             SET status = :status, last_error = NULL
             WHERE id = :id"
        );
        $stmt->execute([':status' => Status::Done->value, ':id' => $id]);
    }

    public function markFailed(string $table, int $id, string $error): void
    {
        TableNameGuard::assertValid($table);

        $stmt = $this->pdo->prepare(
            "UPDATE `{$table}`
             SET status = :status, last_error = :err
             WHERE id = :id"
        );
        $stmt->execute([
            ':status' => Status::Failed->value,
            ':err'    => $error,
            ':id'     => $id,
        ]);
    }

    public function findById(string $table, int $id): ?WebhookRecord
    {
        TableNameGuard::assertValid($table);

        $stmt = $this->pdo->prepare(
            "SELECT id, external_event_id, payload, status, attempts, last_error, received_at, updated_at
             FROM `{$table}` WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): WebhookRecord
    {
        return new WebhookRecord(
            id:                (int) $row['id'],
            externalEventId:   (string) $row['external_event_id'],
            payload:           json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR),
            status:            Status::from((string) $row['status']),
            attempts:          (int) $row['attempts'],
            lastError:         $row['last_error'] !== null ? (string) $row['last_error'] : null,
            receivedAt:        new \DateTimeImmutable((string) $row['received_at']),
            updatedAt:         new \DateTimeImmutable((string) $row['updated_at']),
        );
    }
}
