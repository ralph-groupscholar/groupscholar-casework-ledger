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

$pdo = Database::connect();
$dsn = getenv('GS_CASEWORK_DSN') ?: '';
$schema = Database::schema();
$driver = Database::driver($pdo, $dsn);

Schema::ensure($pdo, $driver, $schema);
$repo = new CaseworkRepository($pdo, $schema, $driver);

$force = in_array('--force', $argv, true) || in_array('--force=true', $argv, true);
$existing = $repo->countNotes();
if ($existing > 0 && !$force) {
    fwrite(STDERR, "Seed aborted: {$existing} notes already exist. Re-run with --force to append.\n");
    exit(1);
}

$notes = CaseworkSeed::notes();

foreach ($notes as $note) {
    $repo->addNote($note);
}

fwrite(STDOUT, "Seeded " . count($notes) . " casework notes.\n");
