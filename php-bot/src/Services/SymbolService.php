<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

final class SymbolService
{
    /** @param string[] $seedSymbols */
    public static function ensureDefaults(array $seedSymbols): void
    {
        $pdo = Database::connection();
        $existing = array_column($pdo->query('SELECT symbol FROM symbols')->fetchAll(), 'symbol');
        $existingSet = array_flip($existing);
        foreach ($seedSymbols as $sym) {
            if (!isset($existingSet[$sym])) {
                $stmt = $pdo->prepare('INSERT INTO symbols (symbol, enabled, added_at) VALUES (:s, 1, :now)');
                $stmt->execute(['s' => $sym, 'now' => Database::now()]);
            }
        }
    }

    /** @return string[] */
    public static function listEnabled(): array
    {
        $rows = Database::connection()->query('SELECT symbol FROM symbols WHERE enabled = 1 ORDER BY symbol')->fetchAll();
        return array_column($rows, 'symbol');
    }

    /** @return array<int, array<string, mixed>> */
    public static function listAll(): array
    {
        return Database::connection()->query('SELECT * FROM symbols ORDER BY symbol')->fetchAll();
    }

    public static function addSymbol(string $symbol): void
    {
        $symbol = strtoupper(trim($symbol));
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM symbols WHERE symbol = :s');
        $stmt->execute(['s' => $symbol]);
        if ($row = $stmt->fetch()) {
            $upd = $pdo->prepare('UPDATE symbols SET enabled = 1 WHERE id = :id');
            $upd->execute(['id' => $row['id']]);
        } else {
            $ins = $pdo->prepare('INSERT INTO symbols (symbol, enabled, added_at) VALUES (:s, 1, :now)');
            $ins->execute(['s' => $symbol, 'now' => Database::now()]);
        }
    }

    public static function removeSymbol(string $symbol): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM symbols WHERE symbol = :s');
        $stmt->execute(['s' => $symbol]);
        return $stmt->rowCount() > 0;
    }

    public static function setEnabled(string $symbol, bool $enabled): bool
    {
        $stmt = Database::connection()->prepare('UPDATE symbols SET enabled = :e WHERE symbol = :s');
        $stmt->execute(['e' => $enabled ? 1 : 0, 's' => $symbol]);
        return $stmt->rowCount() > 0;
    }
}
