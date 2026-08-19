<?php

declare(strict_types=1);

namespace MyBot\Service;

use MyBot\Support\Database;
use MyBot\Support\Text;

/** Tracks which multi-step admin flow a user is in (one active flow per user). */
final class States
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $payload */
    public function set(int $tgId, string $name, array $payload = []): void
    {
        $this->db->run(
            'INSERT INTO states (tg_id, name, payload, updated_at)
             VALUES (:tg_id, :name, :payload, :now)
             ON CONFLICT(tg_id) DO UPDATE SET
                name       = excluded.name,
                payload    = excluded.payload,
                updated_at = excluded.updated_at',
            [
                'tg_id'   => $tgId,
                'name'    => $name,
                'payload' => json_encode($payload, \JSON_UNESCAPED_UNICODE),
                'now'     => time(),
            ],
        );
    }

    /** @return array{name: string, payload: array<string, mixed>}|null */
    public function get(int $tgId): ?array
    {
        $row = $this->db->one('SELECT * FROM states WHERE tg_id = :id', ['id' => $tgId]);

        if ($row === null) {
            return null;
        }

        // A half-finished flow should not haunt the user forever.
        if ((int) $row['updated_at'] < time() - self::TTL) {
            $this->clear($tgId);

            return null;
        }

        return [
            'name'    => (string) $row['name'],
            'payload' => Text::json($row['payload']) ?? [],
        ];
    }

    public function clear(int $tgId): void
    {
        $this->db->run('DELETE FROM states WHERE tg_id = :id', ['id' => $tgId]);
    }

    private const TTL = 3600;
}
