<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Minimal `.env` loader for tests. Avoids pulling in vlucas/phpdotenv just to
 * read three lines.
 *
 * Rules:
 *  - Lines that are blank or start with `#` are ignored.
 *  - Lines must look like KEY=VALUE; surrounding double or single quotes are
 *    stripped from VALUE.
 *  - Already-set environment variables (real shell export, CI secrets) win.
 *  - phpunit.xml `<env force="false">` defaults still win over our `.env`
 *    fallback only if `<env>` is processed first by PHPUnit; in practice this
 *    means: put real secrets in `.env`, leave xml as harmless defaults.
 */
(static function (): void {
    $envFile = __DIR__ . '/../.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || $trimmed[0] === '#') {
            continue;
        }

        $eq = strpos($trimmed, '=');
        if ($eq === false) {
            continue;
        }

        $key   = trim(substr($trimmed, 0, $eq));
        $value = trim(substr($trimmed, $eq + 1));

        if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) !== false) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }
})();
