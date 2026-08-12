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

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) c FROM symbols')->fetch()['c'];
    }

    /** @return array<int, array<string, mixed>> */
    public static function listPage(int $limit, int $offset): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM symbols ORDER BY symbol LIMIT :l OFFSET :o');
        $stmt->bindValue(':l', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Adds (and enables) the top-N USDT-M perpetual symbols by 24h quote
     * volume, as reported by the exchange. Purely additive - never removes
     * or disables a symbol the admin already configured.
     *
     * @param array<int, array{symbol:string, quote_volume:float}> $ranked already sorted, highest volume first
     * @return int how many new symbols were added
     */
    public static function syncTopByVolume(array $ranked, int $topN): int
    {
        $pdo = Database::connection();
        $existing = array_column($pdo->query('SELECT symbol FROM symbols')->fetchAll(), 'symbol');
        $existingSet = array_flip($existing);

        $added = 0;
        foreach (array_slice($ranked, 0, $topN) as $row) {
            $symbol = $row['symbol'];
            if (isset($existingSet[$symbol])) {
                continue;
            }
            $stmt = $pdo->prepare('INSERT INTO symbols (symbol, enabled, added_at) VALUES (:s, 1, :now)');
            $stmt->execute(['s' => $symbol, 'now' => Database::now()]);
            $existingSet[$symbol] = true;
            $added++;
        }
        return $added;
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
