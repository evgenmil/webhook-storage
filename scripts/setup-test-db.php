<?php

declare(strict_types=1);

/**
 * One-shot helper: creates the database listed in WH_TEST_DSN if it does not
 * exist yet. Reads credentials from the real environment (or from `.env`
 * via tests/bootstrap.php).
 *
 * Usage:
 *   composer db:test:setup
 *   php scripts/setup-test-db.php
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
$sql = "CREATE DATABASE IF NOT EXISTS {$quotedDb} "
     . "DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci";

$pdo->exec($sql);

echo "Test database `{$dbname}` is ready on {$parsed['host']}:{$parsed['port']}.\n";

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
