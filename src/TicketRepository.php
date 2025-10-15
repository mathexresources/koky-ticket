<?php

namespace App;

use DateTimeImmutable;

class TicketRepository
{
    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';

    public function __construct(private Database $database)
    {
    }

    public function create(string $firstName, string $title, string $description): int
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->database->query(
            'INSERT INTO tickets (first_name, title, description, status, created_at, updated_at) VALUES (:first_name, :title, :description, :status, :created_at, :updated_at)',
            [
                'first_name' => $firstName,
                'title' => $title,
                'description' => $description,
                'status' => self::STATUS_NEW,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) $this->database->lastInsertId();
    }

    public function all(): array
    {
        return $this->database->query('SELECT * FROM tickets ORDER BY created_at DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $ticket = $this->database->query('SELECT * FROM tickets WHERE id = :id', ['id' => $id])->fetch();

        return $ticket ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->database->query(
            'UPDATE tickets SET status = :status, updated_at = :updated_at WHERE id = :id',
            [
                'status' => $status,
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->database->query('DELETE FROM tickets WHERE id = :id', ['id' => $id]);
    }

    public function deleteAll(): void
    {
        $this->database->query('TRUNCATE TABLE tickets');
    }
}
