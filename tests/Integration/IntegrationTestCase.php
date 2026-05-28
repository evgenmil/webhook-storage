<?php

declare(strict_types=1);

namespace evgenmil\WebhookStorage\Tests\Integration;

use evgenmil\WebhookStorage\Schema\WebhookSchema;
use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests that need a real MySQL connection.
 *
 * Connection parameters are read from WH_TEST_DSN / WH_TEST_USER / WH_TEST_PASS.
 * Defaults are declared in phpunit.xml.dist and can be overridden by exporting
 * real environment variables.
 *
 * Each test gets its own freshly created table with a unique name. The table is
 * dropped in tearDown(), even if the test fails. If the DB is unreachable, the
 * test is skipped rather than failed — this keeps the unit suite green for
 * developers without MySQL.
 */
abstract class IntegrationTestCase extends TestCase
{
    /**
     * Result of the first connection attempt, cached across all integration
     * tests in the run. If MySQL is unreachable, the very first test pays the
     * PDO timeout; the rest skip instantly using this cached failure message.
     */
    private static ?string $connectionFailure = null;

    private static bool $connectionAttempted = false;

    protected \PDO $pdo;

    protected string $table;

    protected function setUp(): void
    {
        $this->pdo = $this->connectOrSkip();
        $this->table = $this->createUniqueTable();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo, $this->table)) {
            $this->pdo->exec(WebhookSchema::dropTableSql($this->table));
        }
    }

    private function connectOrSkip(): \PDO
    {
        if (self::$connectionAttempted && self::$connectionFailure !== null) {
            self::markTestSkipped(self::$connectionFailure);
        }

        $dsn  = (string) getenv('WH_TEST_DSN');
        $user = (string) getenv('WH_TEST_USER');
        $pass = (string) getenv('WH_TEST_PASS');

        if ($dsn === '') {
            self::$connectionAttempted = true;
            self::$connectionFailure = 'WH_TEST_DSN is not configured.';
            self::markTestSkipped(self::$connectionFailure);
        }

        try {
            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::ATTR_TIMEOUT            => 2,
            ]);
            self::$connectionAttempted = true;

            return $pdo;
        } catch (\PDOException $e) {
            self::$connectionAttempted = true;
            self::$connectionFailure = 'MySQL is not reachable for integration tests: ' . $e->getMessage();
            self::markTestSkipped(self::$connectionFailure);
        }
    }

    private function createUniqueTable(): string
    {
        $name = '__wh_test_' . bin2hex(random_bytes(6));
        $this->pdo->exec(WebhookSchema::createTableSql($name));

        return $name;
    }

    /**
     * Fetches a single row by id from the test table.
     *
     * @return array<string,mixed>|null
     */
    protected function fetchRow(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    protected function countRows(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM `{$this->table}`")->fetchColumn();
    }
}
