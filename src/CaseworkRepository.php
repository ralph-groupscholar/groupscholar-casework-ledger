<?php

declare(strict_types=1);

namespace GroupScholar\CaseworkLedger;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class CaseworkRepository
{
    private PDO $pdo;
    private string $schema;
    private string $driver;

    public function __construct(PDO $pdo, string $schema, string $driver)
    {
        $this->pdo = $pdo;
        $this->schema = $schema;
        $this->driver = $driver;
    }

    public function addNote(array $payload): int
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $table = Database::qualify('casework_notes', $this->schema, $this->driver);
        $status = $payload['status'] ?? 'open';
        $completedAt = $payload['completed_at'] ?? null;

        $statement = $this->pdo->prepare(
            "INSERT INTO {$table} (scholar_name, note_type, priority, tags, note_body, follow_up_on, status, completed_at, created_at, updated_at)"
            . " VALUES (:scholar_name, :note_type, :priority, :tags, :note_body, :follow_up_on, :status, :completed_at, :created_at, :updated_at)"
        );

        $statement->execute([
            'scholar_name' => $payload['scholar_name'],
            'note_type' => $payload['note_type'],
            'priority' => $payload['priority'],
            'tags' => $payload['tags'],
            'note_body' => $payload['note_body'],
            'follow_up_on' => $payload['follow_up_on'],
            'status' => $status,
            'completed_at' => $completedAt,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listNotes(array $filters): array
    {
        $table = Database::qualify('casework_notes', $this->schema, $this->driver);
        $conditions = [];
        $params = [];

        if (!empty($filters['scholar_name'])) {
            $conditions[] = 'scholar_name LIKE :scholar_name';
            $params['scholar_name'] = '%' . $filters['scholar_name'] . '%';
        }

        if (!empty($filters['priority'])) {
            $conditions[] = 'priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['since'])) {
            $conditions[] = 'created_at >= :since';
            $params['since'] = $filters['since'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $statement = $this->pdo->prepare(
            "SELECT id, scholar_name, note_type, priority, tags, note_body, follow_up_on, status, completed_at, created_at"
            . " FROM {$table} {$where} ORDER BY created_at DESC LIMIT 200"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function stats(string $since): array
    {
        $table = Database::qualify('casework_notes', $this->schema, $this->driver);

        $priorityStmt = $this->pdo->prepare(
            "SELECT priority, COUNT(*) AS total FROM {$table} WHERE created_at >= :since GROUP BY priority"
        );
        $priorityStmt->execute(['since' => $since]);

        $typeStmt = $this->pdo->prepare(
            "SELECT note_type, COUNT(*) AS total FROM {$table} WHERE created_at >= :since GROUP BY note_type"
        );
        $typeStmt->execute(['since' => $since]);

        return [
            'priorities' => $priorityStmt->fetchAll(),
            'types' => $typeStmt->fetchAll(),
        ];
    }

    public function countNotes(): int
    {
        $table = Database::qualify('casework_notes', $this->schema, $this->driver);
        $statement = $this->pdo->query("SELECT COUNT(*) AS total FROM {$table}");
        $result = $statement->fetch();

        return isset($result['total']) ? (int) $result['total'] : 0;
    }

    public function seedNotes(array $notes, bool $force = false): int
    {
        if (!$force && $this->countNotes() > 0) {
            return 0;
        }

        $inserted = 0;
        foreach ($notes as $note) {
            $this->addNote($note);
            $inserted++;
        }

        return $inserted;
    }

    public function listFollowUps(array $filters): array
    {
        $table = Database::qualify('casework_notes', $this->schema, $this->driver);
        $conditions = ['follow_up_on IS NOT NULL', "status = 'open'"];
        $params = [];

        if (!empty($filters['scholar_name'])) {
            $conditions[] = 'scholar_name LIKE :scholar_name';
            $params['scholar_name'] = '%' . $filters['scholar_name'] . '%';
        }

        if (!empty($filters['priority'])) {
            $conditions[] = 'priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['overdue'])) {
            $conditions[] = 'follow_up_on < :today';
            $params['today'] = $filters['today'];
        } else {
            if (!empty($filters['start'])) {
                $conditions[] = 'follow_up_on >= :start';
                $params['start'] = $filters['start'];
            }
            if (!empty($filters['end'])) {
                $conditions[] = 'follow_up_on <= :end';
                $params['end'] = $filters['end'];
            }
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $statement = $this->pdo->prepare(
            "SELECT id, scholar_name, note_type, priority, tags, note_body, follow_up_on, status, completed_at, created_at"
            . " FROM {$table} {$where} ORDER BY follow_up_on ASC LIMIT 200"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function resolveNote(int $id, string $completedAt): bool
    {
        $table = Database::qualify('casework_notes', $this->schema, $this->driver);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $statement = $this->pdo->prepare(
            "UPDATE {$table} SET status = :status, completed_at = :completed_at, updated_at = :updated_at WHERE id = :id"
        );

        $statement->execute([
            'status' => 'resolved',
            'completed_at' => $completedAt,
            'updated_at' => $now->format('Y-m-d H:i:s'),
            'id' => $id,
        ]);

        return $statement->rowCount() > 0;
    }
}
