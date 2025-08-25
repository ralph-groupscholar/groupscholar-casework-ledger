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

        if ($command === 'seed') {
            self::handleSeed($options);
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

        if ($command === 'followups') {
            self::handleFollowUps($options);
            return;
        }

        if ($command === 'resolve') {
            self::handleResolve($options);
            return;
        }

        if ($command === 'queue') {
            self::handleQueue($options);
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

    private static function handleSeed(array $options): void
    {
        $pdo = Database::connect();
        $dsn = getenv('GS_CASEWORK_DSN') ?: '';
        $schema = Database::schema();
        $driver = Database::driver($pdo, $dsn);

        Schema::ensure($pdo, $driver, $schema);

        $repo = new CaseworkRepository($pdo, $schema, $driver);
        $force = ($options['force'] ?? '') === 'true';
        $inserted = $repo->seedNotes(CaseworkSeed::notes(), $force);

        if ($inserted === 0) {
            fwrite(STDOUT, "Seed skipped: existing notes detected.\n");
            return;
        }

        fwrite(STDOUT, "Seeded {$inserted} notes.\n");
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
            'status' => $options['status'] ?? '',
        ]);

        if (!$notes) {
            fwrite(STDOUT, "No notes found.\n");
            return;
        }

        foreach ($notes as $note) {
            $line = sprintf(
                "#%s | %s | %s | %s | %s | %s | %s\n",
                $note['id'],
                $note['created_at'],
                $note['scholar_name'],
                $note['note_type'],
                $note['priority'],
                $note['status'],
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
            'status' => $options['status'] ?? '',
        ]);

        $handle = fopen($output, 'w');
        if ($handle === false) {
            fwrite(STDERR, "Unable to write {$output}.\n");
            exit(1);
        }

        fputcsv($handle, ['id', 'created_at', 'scholar_name', 'note_type', 'priority', 'status', 'tags', 'note_body', 'follow_up_on', 'completed_at']);
        foreach ($notes as $note) {
            fputcsv($handle, [
                $note['id'],
                $note['created_at'],
                $note['scholar_name'],
                $note['note_type'],
                $note['priority'],
                $note['status'],
                $note['tags'],
                $note['note_body'],
                $note['follow_up_on'],
                $note['completed_at'],
            ]);
        }
        fclose($handle);

        fwrite(STDOUT, "Exported " . count($notes) . " notes to {$output}.\n");
    }

    private static function handleFollowUps(array $options): void
    {
        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $windowDays = (int) ($options['days'] ?? 14);
        if ($windowDays <= 0) {
            $windowDays = 14;
        }

        $start = $options['start'] ?? $today;
        $end = $options['end'] ?? (new DateTimeImmutable($start, new DateTimeZone('UTC')))
            ->add(new DateInterval('P' . $windowDays . 'D'))
            ->format('Y-m-d');
        $overdue = ($options['overdue'] ?? '') === 'true';

        $pdo = Database::connect();
        $dsn = getenv('GS_CASEWORK_DSN') ?: '';
        $schema = Database::schema();
        $driver = Database::driver($pdo, $dsn);
        $repo = new CaseworkRepository($pdo, $schema, $driver);

        $notes = $repo->listFollowUps([
            'scholar_name' => $options['scholar'] ?? '',
            'priority' => $options['priority'] ?? '',
            'overdue' => $overdue ? 'true' : '',
            'today' => $today,
            'start' => $overdue ? '' : $start,
            'end' => $overdue ? '' : $end,
        ]);

        if (!$notes) {
            fwrite(STDOUT, "No follow-ups found.\n");
            return;
        }

        $label = $overdue ? 'Overdue follow-ups' : "Follow-ups from {$start} to {$end}";
        fwrite(STDOUT, $label . ":\n");
        foreach ($notes as $note) {
            $line = sprintf(
                "#%s | due %s | %s | %s | %s | %s\n",
                $note['id'],
                $note['follow_up_on'],
                $note['scholar_name'],
                $note['note_type'],
                $note['priority'],
                $note['note_body']
            );
            fwrite(STDOUT, $line);
        }
    }

    private static function handleResolve(array $options): void
    {
        if (empty($options['id'])) {
            fwrite(STDERR, "Missing required option --id.\n");
            exit(1);
        }

        $pdo = Database::connect();
        $dsn = getenv('GS_CASEWORK_DSN') ?: '';
        $schema = Database::schema();
        $driver = Database::driver($pdo, $dsn);
        Schema::ensure($pdo, $driver, $schema);

        $completedAt = $options['completed'] ?? (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        $repo = new CaseworkRepository($pdo, $schema, $driver);
        $updated = $repo->resolveNote((int) $options['id'], $completedAt);

        if (!$updated) {
            fwrite(STDERR, "No note found for id {$options['id']}.\n");
            exit(1);
        }

        fwrite(STDOUT, "Resolved note {$options['id']}.\n");
    }

    private static function handleQueue(array $options): void
    {
        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $limit = (int) ($options['limit'] ?? 50);
        if ($limit <= 0) {
            $limit = 50;
        }

        $pdo = Database::connect();
        $dsn = getenv('GS_CASEWORK_DSN') ?: '';
        $schema = Database::schema();
        $driver = Database::driver($pdo, $dsn);
        $repo = new CaseworkRepository($pdo, $schema, $driver);

        $rows = $repo->openQueueSummary([
            'priority' => $options['priority'] ?? '',
            'since' => $options['since'] ?? '',
            'today' => $today,
            'limit' => $limit,
        ]);

        if (!$rows) {
            fwrite(STDOUT, "No open queue items found.\n");
            return;
        }

        fwrite(STDOUT, "Open casework queue (top {$limit}):\n");
        foreach ($rows as $row) {
            $line = sprintf(
                "%s | open %s | overdue %s | next %s | last %s\n",
                $row['scholar_name'],
                $row['open_count'],
                $row['overdue_count'] ?? '0',
                $row['next_follow_up'] ?? 'n/a',
                $row['last_note_at']
            );
            fwrite(STDOUT, $line);
        }
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
  gs-casework seed [--force=true]
  gs-casework add --scholar="Name" --type="attendance" --note="Summary" [--priority=high] [--tags=comma,list] [--follow-up=YYYY-MM-DD]
  gs-casework list [--scholar="Name"] [--priority=high] [--since="YYYY-MM-DD"] [--status=open]
  gs-casework stats [--days=30]
  gs-casework followups [--scholar="Name"] [--priority=high] [--start=YYYY-MM-DD] [--end=YYYY-MM-DD] [--days=14] [--overdue=true]
  gs-casework resolve --id=123 [--completed="YYYY-MM-DD HH:MM:SS"]
  gs-casework queue [--priority=high] [--since="YYYY-MM-DD"] [--limit=50]
  gs-casework export [--scholar="Name"] [--priority=high] [--since="YYYY-MM-DD"] [--status=open] [--output=casework.csv]
Environment:
  GS_CASEWORK_DSN (required)
  GS_CASEWORK_DB_USER
  GS_CASEWORK_DB_PASS
  GS_CASEWORK_SCHEMA
TXT;
        fwrite(STDOUT, $help . "\n");
    }
}
