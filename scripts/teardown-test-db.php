<?php

declare(strict_types=1);

/**
 * One-shot helper: drops the test database listed in WH_TEST_DSN.
 * Safe to run when the database does not exist (uses DROP DATABASE IF EXISTS).
 *
 * Usage:
 *   composer db:test:drop
 *   php scripts/teardown-test-db.php
 */

require_once __DIR__ . '/../tests/bootstrap.php';

$dsn  = (string) getenv('WH_TEST_DSN');
$user = (string) getenv('WH_TEST_USER');
$pass = (string) getenv('WH_TEST_PASS');

if ($dsn === '') {
    fwrite(STDERR, "WH_TEST_DSN is not set. Configure it in .env or export it in your shell.\n");
    exit(1);
}

$parsed = parseMysqlDsn($dsn);
if ($parsed['dbname'] === null) {
    fwrite(STDERR, "WH_TEST_DSN must include a dbname= component.\n");
    exit(1);
}

$dbname  = $parsed['dbname'];
$charset = $parsed['charset'] ?? 'utf8mb4';

guardDbName($dbname);

$serverDsn = sprintf(
    'mysql:host=%s;port=%d;charset=%s',
    $parsed['host'],
    $parsed['port'],
    $charset,
);

try {
    $pdo = new PDO($serverDsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot connect to MySQL ({$serverDsn}): {$e->getMessage()}\n");
    exit(1);
}

$quotedDb = '`' . str_replace('`', '``', $dbname) . '`';
$pdo->exec("DROP DATABASE IF EXISTS {$quotedDb}");

echo "Test database `{$dbname}` has been dropped from {$parsed['host']}:{$parsed['port']}.\n";

/**
 * Belt-and-suspenders: refuse to drop anything that does not look like a
 * disposable test database. Better to fail loudly than to nuke production
 * because someone copied a real DSN into `.env` by mistake.
 */
function guardDbName(string $dbname): void
{
    $looksLikeTest =
        str_contains($dbname, 'test')
        || str_contains($dbname, 'Test')
        || str_starts_with($dbname, '__');

    if (!$looksLikeTest) {
        fwrite(STDERR,
            "Refusing to drop `{$dbname}`: its name does not contain 'test' or start with '__'.\n"
            . "Rename the database, or drop it manually if you really mean it.\n"
        );
        exit(1);
    }
}

/**
 * @return array{host:string,port:int,dbname:?string,charset:?string}
 */
function parseMysqlDsn(string $dsn): array
{
    if (!str_starts_with($dsn, 'mysql:')) {
        fwrite(STDERR, "Only mysql DSN is supported. Got: {$dsn}\n");
        exit(1);
    }

    $body  = substr($dsn, strlen('mysql:'));
    $parts = explode(';', $body);
    $kv    = [];
    foreach ($parts as $part) {
        $eq = strpos($part, '=');
        if ($eq === false) {
            continue;
        }
        $kv[strtolower(trim(substr($part, 0, $eq)))] = trim(substr($part, $eq + 1));
    }

    return [
        'host'    => $kv['host']    ?? '127.0.0.1',
        'port'    => isset($kv['port']) ? (int) $kv['port'] : 3306,
        'dbname'  => $kv['dbname']  ?? null,
        'charset' => $kv['charset'] ?? null,
    ];
}
