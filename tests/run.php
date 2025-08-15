<?php

declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Schema.php';
require __DIR__ . '/../src/CaseworkRepository.php';
require __DIR__ . '/../src/CaseworkSeed.php';

use GroupScholar\CaseworkLedger\CaseworkRepository;
use GroupScholar\CaseworkLedger\CaseworkSeed;
use GroupScholar\CaseworkLedger\Database;
use GroupScholar\CaseworkLedger\Schema;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

putenv('GS_CASEWORK_DSN=sqlite::memory:');
putenv('GS_CASEWORK_DB_USER=');
putenv('GS_CASEWORK_DB_PASS=');
putenv('GS_CASEWORK_SCHEMA=');

$pdo = Database::connect();
$driver = Database::driver($pdo, 'sqlite::memory:');
Schema::ensure($pdo, $driver, '');

$repo = new CaseworkRepository($pdo, '', $driver);
$seeded = $repo->seedNotes(CaseworkSeed::notes());
assertTrue($seeded === 4, 'expected four seed notes to be inserted');
assertTrue($repo->countNotes() === 4, 'expected four total notes after seeding');

$repo->addNote([
    'scholar_name' => 'Test Scholar',
    'note_type' => 'attendance',
    'priority' => 'high',
    'tags' => 'test',
    'note_body' => 'Test note',
    'follow_up_on' => null,
]);
$overdueId = $repo->addNote([
    'scholar_name' => 'Overdue Scholar',
    'note_type' => 'wellness',
    'priority' => 'medium',
    'tags' => 'check-in',
    'note_body' => 'Overdue follow-up note',
    'follow_up_on' => '2026-02-01',
]);
$repo->addNote([
    'scholar_name' => 'Upcoming Scholar',
    'note_type' => 'academic',
    'priority' => 'low',
    'tags' => 'tutoring',
    'note_body' => 'Upcoming follow-up note',
    'follow_up_on' => '2026-03-01',
]);

$seededAgain = $repo->seedNotes(CaseworkSeed::notes());
assertTrue($seededAgain === 0, 'expected seed to skip when notes exist');

assertTrue($repo->countNotes() === 7, 'expected seven notes after seeding and inserts');

$repo->resolveNote($overdueId, '2026-02-05 12:00:00');

$notes = $repo->listNotes(['scholar_name' => 'Test', 'priority' => 'high', 'since' => '2000-01-01 00:00:00']);
assertTrue(count($notes) === 1, 'expected one note to be returned');

$stats = $repo->stats('2000-01-01 00:00:00');
assertTrue(count($stats['priorities']) >= 1, 'expected at least one priority group');
$priorities = array_column($stats['priorities'], 'priority');
assertTrue(in_array('high', $priorities, true), 'expected high priority group');

$overdue = $repo->listFollowUps([
    'scholar_name' => '',
    'priority' => '',
    'overdue' => 'true',
    'today' => '2026-02-10',
    'start' => '',
    'end' => '',
]);
assertTrue(count($overdue) === 0, 'expected resolved overdue follow-up to be excluded');

$upcoming = $repo->listFollowUps([
    'scholar_name' => '',
    'priority' => '',
    'overdue' => '',
    'today' => '2026-02-10',
    'start' => '2026-02-10',
    'end' => '2026-03-10',
]);
assertTrue(count($upcoming) === 3, 'expected three upcoming follow-ups');
$upcomingNames = array_column($upcoming, 'scholar_name');
assertTrue(in_array('Upcoming Scholar', $upcomingNames, true), 'expected upcoming scholar');

$resolved = $repo->listNotes([
    'scholar_name' => '',
    'priority' => '',
    'since' => '2000-01-01 00:00:00',
    'status' => 'resolved',
]);
assertTrue(count($resolved) === 2, 'expected seeded and resolved note');

fwrite(STDOUT, "All tests passed.\n");
