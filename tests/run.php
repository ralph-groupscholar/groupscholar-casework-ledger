<?php

declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Schema.php';
require __DIR__ . '/../src/CaseworkRepository.php';

use GroupScholar\CaseworkLedger\CaseworkRepository;
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
$repo->addNote([
    'scholar_name' => 'Test Scholar',
    'note_type' => 'attendance',
    'priority' => 'high',
    'tags' => 'test',
    'note_body' => 'Test note',
    'follow_up_on' => null,
]);

$notes = $repo->listNotes(['scholar_name' => 'Test', 'priority' => 'high', 'since' => '2000-01-01 00:00:00']);
assertTrue(count($notes) === 1, 'expected one note to be returned');

$stats = $repo->stats('2000-01-01 00:00:00');
assertTrue(count($stats['priorities']) === 1, 'expected one priority group');
assertTrue($stats['priorities'][0]['priority'] === 'high', 'expected high priority');

fwrite(STDOUT, "All tests passed.\n");
