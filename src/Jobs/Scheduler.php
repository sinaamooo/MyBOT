<?php
declare(strict_types=1);

namespace Nikto\Jobs;

use DateTimeImmutable;
use Nikto\Core\Db;
use Nikto\Core\Log;
use Nikto\Core\Settings;

/**
 * زمان‌بند دقیقه‌ای: هر دقیقه بررسی می‌کند کدام کار باید اجرا شود.
 */
final class Scheduler
{
    /**
     * یک تیک زمان‌بند.
     * @return array<int,array{job:string,slot:string,result:array}>
     */
    public static function tick(?DateTimeImmutable $now = null): array
    {
        $now ??= Settings::now();
        $catchup = max(0, Settings::int('catchup_minutes', 10));
        $results = [];

        foreach (self::due($now, $catchup) as $due) {
            $claimed = self::claim($due['job_key'], $due['slot']);
            if (!$claimed) {
                continue; // قبلاً ارسال شده یا در حال ارسال است
            }
            Log::info('Scheduler firing job', ['job' => $due['job_key'], 'slot' => $due['slot']]);
            $result = Dispatcher::run($due['job_key'], ['slot' => $due['slot']]);
            $results[] = ['job' => $due['job_key'], 'slot' => $due['slot'], 'result' => $result];
        }

        return $results;
    }

    /**
     * زمان‌بندی‌های سررسیدشده.
     * @return array<int,array{job_key:string,slot:string,at_time:string}>
     */
    public static function due(DateTimeImmutable $now, int $catchupMinutes = 10): array
    {
        $out = [];
        $rows = Db::all('SELECT * FROM schedules WHERE enabled = 1');

        foreach ($rows as $row) {
            $jobKey = (string) $row['job_key'];
            $job = Registry::get($jobKey);
            if ($job === null || !$job->enabled()) {
                continue;
            }
            if (!self::matchesDay((string) $row['days'], $now)) {
                continue;
            }
            $atTime = (string) $row['at_time'];
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $atTime, $m)) {
                continue;
            }
            $slotTime = $now->setTime((int) $m[1], (int) $m[2], 0);
            $diff = ($now->getTimestamp() - $slotTime->getTimestamp()) / 60;
            if ($diff < 0 || $diff > $catchupMinutes) {
                continue;
            }
            $out[] = [
                'job_key' => $jobKey,
                'slot'    => $slotTime->format('Y-m-d H:i'),
                'at_time' => $atTime,
            ];
        }

        return $out;
    }

    /** رزرو اتمیک یک اسلات تا از ارسال تکراری جلوگیری شود */
    private static function claim(string $jobKey, string $slot): bool
    {
        $affected = Db::exec(
            'INSERT OR IGNORE INTO runs(job_key, slot, status, detail, created_at)
             VALUES(:j, :s, :st, :d, :t)',
            [':j' => $jobKey, ':s' => $slot, ':st' => 'running', ':d' => '', ':t' => time()]
        );

        return $affected > 0;
    }

    /** آیا این زمان‌بندی امروز اجرا می‌شود؟ (days: '*' یا فهرست 1..7 با 1=دوشنبه) */
    public static function matchesDay(string $days, DateTimeImmutable $now): bool
    {
        $days = trim($days);
        if ($days === '' || $days === '*') {
            return true;
        }
        $today = (int) $now->format('N');
        $list = array_map('intval', array_filter(explode(',', $days), 'is_numeric'));

        return in_array($today, $list, true);
    }

    /** زمان اجرای بعدی یک کار */
    public static function nextRun(string $jobKey, ?DateTimeImmutable $now = null): ?DateTimeImmutable
    {
        $now ??= Settings::now();
        $rows = Db::all('SELECT * FROM schedules WHERE job_key = :k AND enabled = 1', [':k' => $jobKey]);
        $best = null;

        foreach ($rows as $row) {
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string) $row['at_time'], $m)) {
                continue;
            }
            for ($dayOffset = 0; $dayOffset <= 7; $dayOffset++) {
                $candidate = $now->modify(sprintf('+%d day', $dayOffset))->setTime((int) $m[1], (int) $m[2], 0);
                if ($candidate <= $now) {
                    continue;
                }
                if (!self::matchesDay((string) $row['days'], $candidate)) {
                    continue;
                }
                if ($best === null || $candidate < $best) {
                    $best = $candidate;
                }
                break;
            }
        }

        return $best;
    }
}
