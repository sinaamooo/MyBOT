<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

final class StatisticsService
{
    /** @return array<string, mixed> */
    public static function refreshToday(): array
    {
        $pdo = Database::connection();
        $todayStart = gmdate('Y-m-d 00:00:00');
        $dateKey = gmdate('Y-m-d');

        $stmt = $pdo->prepare('SELECT * FROM signals WHERE created_at >= :s');
        $stmt->execute(['s' => $todayStart]);
        $todays = $stmt->fetchAll();

        $tp1 = $tp2 = $tp3 = $riskFree = $stopped = $cancelled = 0;
        $decidedCount = 0;
        $wins = 0;
        $rrSum = 0.0;
        $scoreSum = 0.0;
        foreach ($todays as $s) {
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
            if ($s['status'] === 'STOPPED') {
                $stopped++;
            }
            if ($s['status'] === 'CANCELLED') {
                $cancelled++;
            }
            if ($s['result'] !== null) {
                $decidedCount++;
                if ($s['result'] === 'WIN') {
                    $wins++;
                }
            }
            $rrSum += (float) $s['rr'];
            $scoreSum += (float) $s['score'];
        }

        $count = count($todays);
        $winRate = $decidedCount ? round($wins / $decidedCount * 100, 2) : 0.0;
        $avgRr = $count ? round($rrSum / $count, 2) : 0.0;
        $avgScore = $count ? round($scoreSum / $count, 2) : 0.0;

        $data = [
            'total_signals' => $count,
            'tp1_count' => $tp1,
            'tp2_count' => $tp2,
            'tp3_count' => $tp3,
            'stopped_count' => $stopped,
            'risk_free_count' => $riskFree,
            'cancelled_count' => $cancelled,
            'win_rate' => $winRate,
            'avg_rr' => $avgRr,
            'avg_score' => $avgScore,
        ];

        $sel = $pdo->prepare('SELECT id FROM system_stats WHERE `date` = :d');
        $sel->execute(['d' => $dateKey]);
        $row = $sel->fetch();

        if ($row) {
            $sets = implode(', ', array_map(fn($c) => "`{$c}` = :{$c}", array_keys($data)));
            $updateData = $data;
            $updateData['id'] = $row['id'];
            $pdo->prepare("UPDATE system_stats SET {$sets} WHERE id = :id")->execute($updateData);
        } else {
            $insertData = $data;
            $insertData['date'] = $dateKey;
            $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($insertData)));
            $placeholders = implode(',', array_map(fn($c) => ":{$c}", array_keys($insertData)));
            $pdo->prepare("INSERT INTO system_stats ({$cols}) VALUES ({$placeholders})")->execute($insertData);
        }

        return array_merge(['date' => $dateKey], $data);
    }
}
