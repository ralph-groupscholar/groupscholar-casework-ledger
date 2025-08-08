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
                . "  status TEXT NOT NULL DEFAULT 'open',\n"
                . "  completed_at TIMESTAMP NULL,\n"
                . "  created_at TIMESTAMP NOT NULL,\n"
                . "  updated_at TIMESTAMP NOT NULL\n"
                . ")"
            );
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS status TEXT NOT NULL DEFAULT 'open'");
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL");
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
            . "  status TEXT NOT NULL DEFAULT 'open',\n"
            . "  completed_at TEXT NULL,\n"
            . "  created_at TEXT NOT NULL,\n"
            . "  updated_at TEXT NOT NULL\n"
            . ")"
        );

        $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');
        if (!in_array('status', $columnNames, true)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN status TEXT NOT NULL DEFAULT 'open'");
        }
        if (!in_array('completed_at', $columnNames, true)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN completed_at TEXT NULL");
        }
    }
}
