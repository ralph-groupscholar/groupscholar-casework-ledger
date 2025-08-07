<?php

declare(strict_types=1);

namespace GroupScholar\CaseworkLedger;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class Commands
{
    public static function run(array $argv): void
    {
        $command = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);
        $options = self::parseOptions($args);

        if (in_array($command, ['help', '--help', '-h'], true)) {
            self::printHelp();
            return;
        }

        if ($command === 'init') {
            self::handleInit();
            return;
        }

        if ($command === 'add') {
            self::handleAdd($options);
            return;
        }

        if ($command === 'list') {
            self::handleList($options);
            return;
        }

        if ($command === 'stats') {
            self::handleStats($options);
            return;
        }

        if ($command === 'export') {
            self::handleExport($options);
            return;
        }

        fwrite(STDERR, "Unknown command: {$command}\n");
        self::printHelp();
        exit(1);
    }

    private static function handleInit(): void
    {
        $pdo = Database::connect();
        $dsn = getenv('GS_CASEWORK_DSN') ?: '';
        $schema = Database::schema();
        $driver = Database::driver($pdo, $dsn);

        Schema::ensure($pdo, $driver, $schema);
        fwrite(STDOUT, "Casework schema ready.\n");
    }

    private static function handleAdd(array $options): void
    {
        $required = ['scholar', 'type', 'note'];
        foreach ($required as $field) {
            if (empty($options[$field])) {
                fwrite(STDERR, "Missing required option --{$field}.\n");
                exit(1);
            }
        }

        $priority = $options['priority'] ?? 'medium';
        $tags = $options['tags'] ?? 'general';
        $followUp = $options['follow-up'] ?? null;

        $pdo = Database::connect();
        $dsn = getenv('GS_CASEWORK_DSN') ?: '';
        $schema = Database::schema();
        $driver = Database::driver($pdo, $dsn);

        Schema::ensure($pdo, $driver, $schema);

        $repo = new CaseworkRepository($pdo, $schema, $driver);
        $id = $repo->addNote([
            'scholar_name' => $options['scholar'],
            'note_type' => $options['type'],
            'priority' => $priority,
            'tags' => $tags,
            'note_body' => $options['note'],
            'follow_up_on' => $followUp,
        ]);

        fwrite(STDOUT, "Added note {$id}.\n");
    }

    private static function handleList(array $options): void
    {
        $pdo = Database::connect();
        $dsn = getenv('GS_CASEWORK_DSN') ?: '';
        $schema = Database::schema();
        $driver = Database::driver($pdo, $dsn);
        $repo = new CaseworkRepository($pdo, $schema, $driver);

        $notes = $repo->listNotes([
            'scholar_name' => $options['scholar'] ?? '',
            'priority' => $options['priority'] ?? '',
            'since' => $options['since'] ?? '',
        ]);

        if (!$notes) {
            fwrite(STDOUT, "No notes found.\n");
            return;
        }

        foreach ($notes as $note) {
            $line = sprintf(
                "#%s | %s | %s | %s | %s | %s\n",
                $note['id'],
                $note['created_at'],
                $note['scholar_name'],
                $note['note_type'],
                $note['priority'],
                $note['note_body']
            );
            fwrite(STDOUT, $line);
        }
    }

    private static function handleStats(array $options): void
    {
        $days = (int) ($options['days'] ?? 30);
        if ($days <= 0) {
            $days = 30;
        }

        $since = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->sub(new DateInterval('P' . $days . 'D'))
            ->format('Y-m-d H:i:s');

        $pdo = Database::connect();
        $dsn = getenv('GS_CASEWORK_DSN') ?: '';
        $schema = Database::schema();
        $driver = Database::driver($pdo, $dsn);
        $repo = new CaseworkRepository($pdo, $schema, $driver);

        $stats = $repo->stats($since);

        fwrite(STDOUT, "Priorities (last {$days} days):\n");
        foreach ($stats['priorities'] as $row) {
            fwrite(STDOUT, sprintf("  %s: %s\n", $row['priority'], $row['total']));
        }

        fwrite(STDOUT, "Types (last {$days} days):\n");
        foreach ($stats['types'] as $row) {
            fwrite(STDOUT, sprintf("  %s: %s\n", $row['note_type'], $row['total']));
        }
    }

    private static function handleExport(array $options): void
    {
        $output = $options['output'] ?? 'casework-export.csv';
        $pdo = Database::connect();
        $dsn = getenv('GS_CASEWORK_DSN') ?: '';
        $schema = Database::schema();
        $driver = Database::driver($pdo, $dsn);
        $repo = new CaseworkRepository($pdo, $schema, $driver);

        $notes = $repo->listNotes([
            'scholar_name' => $options['scholar'] ?? '',
            'priority' => $options['priority'] ?? '',
            'since' => $options['since'] ?? '',
        ]);

        $handle = fopen($output, 'w');
        if ($handle === false) {
            fwrite(STDERR, "Unable to write {$output}.\n");
            exit(1);
        }

        fputcsv($handle, ['id', 'created_at', 'scholar_name', 'note_type', 'priority', 'tags', 'note_body', 'follow_up_on']);
        foreach ($notes as $note) {
            fputcsv($handle, [
                $note['id'],
                $note['created_at'],
                $note['scholar_name'],
                $note['note_type'],
                $note['priority'],
                $note['tags'],
                $note['note_body'],
                $note['follow_up_on'],
            ]);
        }
        fclose($handle);

        fwrite(STDOUT, "Exported " . count($notes) . " notes to {$output}.\n");
    }

    private static function parseOptions(array $args): array
    {
        $options = [];
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--')) {
                continue;
            }
            $pair = explode('=', substr($arg, 2), 2);
            $key = $pair[0];
            $value = $pair[1] ?? 'true';
            $options[$key] = $value;
        }
        return $options;
    }

    private static function printHelp(): void
    {
        $help = <<<TXT
Groupscholar Casework Ledger

Usage:
  gs-casework init
  gs-casework add --scholar="Name" --type="attendance" --note="Summary" [--priority=high] [--tags=comma,list] [--follow-up=YYYY-MM-DD]
  gs-casework list [--scholar="Name"] [--priority=high] [--since="YYYY-MM-DD"]
  gs-casework stats [--days=30]
  gs-casework export [--scholar="Name"] [--priority=high] [--since="YYYY-MM-DD"] [--output=casework.csv]

Environment:
  GS_CASEWORK_DSN (required)
  GS_CASEWORK_DB_USER
  GS_CASEWORK_DB_PASS
  GS_CASEWORK_SCHEMA
TXT;
        fwrite(STDOUT, $help . "\n");
    }
}
