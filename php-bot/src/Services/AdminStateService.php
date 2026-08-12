<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

/**
 * Replaces aiogram's in-memory FSMContext: PHP webhook requests are
 * stateless, so "waiting for the admin to type a value" has to be
 * persisted between the request that asked the question and the one
 * carrying the answer.
 */
final class AdminStateService
{
    /** @param array<string, mixed> $context */
    public static function set(int $telegramId, string $state, array $context = []): void
    {
        $pdo = Database::connection();
        $json = json_encode($context, JSON_UNESCAPED_UNICODE);
        $stmt = $pdo->prepare('SELECT telegram_id FROM admin_states WHERE telegram_id = :t');
        $stmt->execute(['t' => $telegramId]);
        if ($stmt->fetch()) {
            $upd = $pdo->prepare('UPDATE admin_states SET state = :s, context = :c, updated_at = :now WHERE telegram_id = :t');
            $upd->execute(['s' => $state, 'c' => $json, 'now' => Database::now(), 't' => $telegramId]);
        } else {
            $ins = $pdo->prepare('INSERT INTO admin_states (telegram_id, state, context, updated_at) VALUES (:t, :s, :c, :now)');
            $ins->execute(['t' => $telegramId, 's' => $state, 'c' => $json, 'now' => Database::now()]);
        }
    }

    /** @return array{state:string, context:array<string,mixed>}|null */
    public static function get(int $telegramId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT state, context FROM admin_states WHERE telegram_id = :t');
        $stmt->execute(['t' => $telegramId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return ['state' => $row['state'], 'context' => $row['context'] ? json_decode((string) $row['context'], true) : []];
    }

    public static function clear(int $telegramId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM admin_states WHERE telegram_id = :t');
        $stmt->execute(['t' => $telegramId]);
    }
}
