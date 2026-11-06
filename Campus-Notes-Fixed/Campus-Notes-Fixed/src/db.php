<?php
// Issue 8: database connection from environment only, no root/empty fallback.
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn  = getenv('LAB_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=notes_lab;charset=utf8mb4';
    $user = getenv('LAB_DB_USER');
    $pass = getenv('LAB_DB_PASSWORD');

    if (!str_starts_with($dsn, 'sqlite:') && ($user === false || $user === '' || $pass === false)) {
        throw new RuntimeException('Database credentials are not configured (LAB_DB_USER / LAB_DB_PASSWORD).');
    }

    $pdo = new PDO($dsn, $user ?: null, $pass ?: null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
