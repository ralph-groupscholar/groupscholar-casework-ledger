<?php

declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Schema.php';
require __DIR__ . '/../src/CaseworkRepository.php';

use GroupScholar\CaseworkLedger\CaseworkRepository;
use GroupScholar\CaseworkLedger\Database;
use GroupScholar\CaseworkLedger\Schema;

$pdo = Database::connect();
$dsn = getenv('GS_CASEWORK_DSN') ?: '';
$schema = Database::schema();
$driver = Database::driver($pdo, $dsn);

Schema::ensure($pdo, $driver, $schema);
$repo = new CaseworkRepository($pdo, $schema, $driver);

$notes = [
    [
        'scholar_name' => 'Avery Johnson',
        'note_type' => 'attendance',
        'priority' => 'high',
        'tags' => 'attendance,check-in',
        'note_body' => 'Missed two sessions this week, needs outreach to confirm schedule.',
        'follow_up_on' => '2026-02-12',
    ],
    [
        'scholar_name' => 'Maya Chen',
        'note_type' => 'financial',
        'priority' => 'medium',
        'tags' => 'fafsa,documents',
        'note_body' => 'Waiting on FAFSA verification docs; follow-up planned.',
        'follow_up_on' => '2026-02-15',
    ],
    [
        'scholar_name' => 'Luis Ramirez',
        'note_type' => 'academic',
        'priority' => 'low',
        'tags' => 'midterm,progress',
        'note_body' => 'Reported improved midterm scores after tutoring sessions.',
        'follow_up_on' => null,
    ],
];

foreach ($notes as $note) {
    $repo->addNote($note);
}

fwrite(STDOUT, "Seeded " . count($notes) . " casework notes.\n");
