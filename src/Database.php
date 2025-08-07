<?php

declare(strict_types=1);

namespace GroupScholar\CaseworkLedger;

use PDO;
use PDOException;

final class Database
{
    public static function connect(): PDO
    {
        $dsn = getenv('GS_CASEWORK_DSN') ?: '';
        $user = getenv('GS_CASEWORK_DB_USER') ?: '';
        $pass = getenv('GS_CASEWORK_DB_PASS') ?: '';

        if ($dsn === '') {
            fwrite(STDERR, "Missing GS_CASEWORK_DSN environment variable.\n");
            exit(1);
        }

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            fwrite(STDERR, "Database connection failed: {$exception->getMessage()}\n");
            exit(1);
        }

        return $pdo;
    }

    public static function driver(PDO $pdo, string $dsn): string
    {
        if (str_starts_with($dsn, 'pgsql:')) {
            return 'pgsql';
        }

        if (str_starts_with($dsn, 'sqlite:')) {
            return 'sqlite';
        }

        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public static function schema(): string
    {
        return getenv('GS_CASEWORK_SCHEMA') ?: '';
    }

    public static function qualify(string $table, string $schema, string $driver): string
    {
        if ($schema === '' || $driver === 'sqlite') {
            return $table;
        }

        return $schema . '.' . $table;
    }
}
