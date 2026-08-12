<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

final class SignalService
{
    private const OPEN_STATUSES = ['PENDING', 'ACTIVE', 'TP1', 'TP2', 'RISK_FREE'];
    private const CLOSED_STATUSES = ['TP3', 'STOPPED', 'COMPLETED', 'CANCELLED'];

    /** @return array{0:string,1:float} [result, profitPercent] */
    public static function computeResult(string $side, float $entry, float $closePrice): array
    {
        $direction = $side === 'LONG' ? 1 : -1;
        $profitPercent = (($closePrice - $entry) / $entry) * 100 * $direction;
        if ($profitPercent > 0.05) {
            $result = 'WIN';
        } elseif ($profitPercent < -0.05) {
            $result = 'LOSS';
        } else {
            $result = 'BREAKEVEN';
        }
        return [$result, round($profitPercent, 3)];
    }

    /** @param array<string, mixed> $fields */
    public static function create(array $fields): int
    {
        if (isset($fields['reasons']) && is_array($fields['reasons'])) {
            $fields['reasons'] = json_encode($fields['reasons'], JSON_UNESCAPED_UNICODE);
        }
        $fields['created_at'] = Database::now();

        $columns = array_keys($fields);
        $sql = 'INSERT INTO signals (' . implode(',', array_map(fn($c) => "`{$c}`", $columns)) . ') VALUES ('
            . implode(',', array_map(fn($c) => ":{$c}", $columns)) . ')';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($fields);
        return (int) Database::connection()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public static function get(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM signals WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function getOpenForSymbol(string $symbol): ?array
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT * FROM signals WHERE symbol = ? AND status IN ({$placeholders}) ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute(array_merge([$symbol], self::OPEN_STATUSES));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getLastSignalTime(string $symbol): ?string
    {
        $stmt = Database::connection()->prepare('SELECT created_at FROM signals WHERE symbol = :s ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(['s' => $symbol]);
        $row = $stmt->fetch();
        return $row ? $row['created_at'] : null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function getActive(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));
        $stmt = Database::connection()->prepare("SELECT * FROM signals WHERE status IN ({$placeholders}) ORDER BY created_at DESC");
        $stmt->execute(self::OPEN_STATUSES);
        return $stmt->fetchAll();
    }

    public static function countActive(): int
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));
        $stmt = Database::connection()->prepare("SELECT COUNT(*) c FROM signals WHERE status IN ({$placeholders})");
        $stmt->execute(self::OPEN_STATUSES);
        return (int) $stmt->fetch()['c'];
    }

    public static function countSince(string $sinceDateTime): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) c FROM signals WHERE created_at >= :s');
        $stmt->execute(['s' => $sinceDateTime]);
        return (int) $stmt->fetch()['c'];
    }

    /** @return array<int, array<string, mixed>> */
    public static function listHistory(int $limit = 10, int $offset = 0): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM signals ORDER BY created_at DESC LIMIT :l OFFSET :o');
        $stmt->bindValue(':l', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $fields */
    public static function update(int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }
        $sets = [];
        foreach (array_keys($fields) as $col) {
            $sets[] = "`{$col}` = :{$col}";
        }
        $fields['id'] = $id;
        $sql = 'UPDATE signals SET ' . implode(', ', $sets) . ' WHERE id = :id';
        Database::connection()->prepare($sql)->execute($fields);
    }

    public static function addSignalUpdate(int $signalId, string $updateType, string $message): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO signal_updates (signal_id, update_type, message, created_at) VALUES (:sid, :t, :m, :now)'
        );
        $stmt->execute(['sid' => $signalId, 't' => $updateType, 'm' => $message, 'now' => Database::now()]);
    }

    /** @return array<string, mixed>|null */
    public static function close(int $id, string $status, float $closePrice): ?array
    {
        $signal = self::get($id);
        if ($signal === null) {
            return null;
        }
        [$result, $profitPercent] = self::computeResult($signal['side'], (float) $signal['entry'], $closePrice);
        self::update($id, [
            'status' => $status,
            'close_price' => $closePrice,
            'result' => $result,
            'profit_percent' => $profitPercent,
            'closed_at' => Database::now(),
        ]);
        return self::get($id);
    }

    /** @return array<string, mixed> */
    public static function dashboardStats(): array
    {
        $pdo = Database::connection();
        $todayStart = gmdate('Y-m-d 00:00:00');

        $total = (int) $pdo->query('SELECT COUNT(*) c FROM signals')->fetch()['c'];

        $openPlaceholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));
        $activeStmt = $pdo->prepare("SELECT COUNT(*) c FROM signals WHERE status IN ({$openPlaceholders})");
        $activeStmt->execute(self::OPEN_STATUSES);
        $active = (int) $activeStmt->fetch()['c'];

        $todayStmt = $pdo->prepare('SELECT COUNT(*) c FROM signals WHERE created_at >= :s');
        $todayStmt->execute(['s' => $todayStart]);
        $today = (int) $todayStmt->fetch()['c'];

        $closedPlaceholders = implode(',', array_fill(0, count(self::CLOSED_STATUSES), '?'));
        $closedStmt = $pdo->prepare("SELECT * FROM signals WHERE status IN ({$closedPlaceholders})");
        $closedStmt->execute(self::CLOSED_STATUSES);
        $closed = $closedStmt->fetchAll();

        $wins = 0;
        $stopped = 0;
        $tp1 = 0;
        $tp2 = 0;
        $tp3 = 0;
        $riskFree = 0;
        foreach ($closed as $s) {
            if ($s['result'] === 'WIN') {
                $wins++;
            }
            if ($s['status'] === 'STOPPED') {
                $stopped++;
            }
            if ($s['tp1_hit_at'] !== null) {
                $tp1++;
            }
            if ($s['tp2_hit_at'] !== null) {
                $tp2++;
            }
            if ($s['tp3_hit_at'] !== null) {
                $tp3++;
            }
            if ($s['risk_free_at'] !== null) {
                $riskFree++;
            }
        }
        $decided = count($closed);
        $winRate = $decided ? round($wins / $decided * 100, 2) : 0.0;

        $avgRr = (float) ($pdo->query('SELECT AVG(rr) a FROM signals')->fetch()['a'] ?? 0);
        $avgScore = (float) ($pdo->query('SELECT AVG(score) a FROM signals')->fetch()['a'] ?? 0);

        return [
            'total_signals' => $total,
            'active_signals' => $active,
            'today_signals' => $today,
            'winning_signals' => $wins,
            'stopped_signals' => $stopped,
            'tp1_count' => $tp1,
            'tp2_count' => $tp2,
            'tp3_count' => $tp3,
            'risk_free_count' => $riskFree,
            'win_rate' => $winRate,
            'avg_rr' => round($avgRr, 2),
            'avg_score' => round($avgScore, 2),
        ];
    }
}
