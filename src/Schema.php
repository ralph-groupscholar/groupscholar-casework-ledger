<?php

declare(strict_types=1);

namespace GroupScholar\CaseworkLedger;

use PDO;

final class Schema
{
    public static function ensure(PDO $pdo, string $driver, string $schema): void
    {
        if ($driver === 'pgsql' && $schema !== '') {
            $pdo->exec('CREATE SCHEMA IF NOT EXISTS ' . $schema);
        }

        $table = Database::qualify('casework_notes', $schema, $driver);

        if ($driver === 'pgsql') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS {$table} (\n"
                . "  id BIGSERIAL PRIMARY KEY,\n"
                . "  scholar_name TEXT NOT NULL,\n"
                . "  note_type TEXT NOT NULL,\n"
                . "  priority TEXT NOT NULL,\n"
                . "  tags TEXT NOT NULL,\n"
                . "  note_body TEXT NOT NULL,\n"
                . "  follow_up_on DATE NULL,\n"
                . "  created_at TIMESTAMP NOT NULL,\n"
                . "  updated_at TIMESTAMP NOT NULL\n"
                . ")"
            );
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS {$table} (\n"
            . "  id INTEGER PRIMARY KEY AUTOINCREMENT,\n"
            . "  scholar_name TEXT NOT NULL,\n"
            . "  note_type TEXT NOT NULL,\n"
            . "  priority TEXT NOT NULL,\n"
            . "  tags TEXT NOT NULL,\n"
            . "  note_body TEXT NOT NULL,\n"
            . "  follow_up_on TEXT NULL,\n"
            . "  created_at TEXT NOT NULL,\n"
            . "  updated_at TEXT NOT NULL\n"
            . ")"
        );
    }
}
