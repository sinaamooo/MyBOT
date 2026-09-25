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
    /** اگر ارسال کلاً ناموفق شود (مثلاً قطعی لحظه‌ای اینترنت) چند بار دوباره تلاش شود */
    public const MAX_ATTEMPTS = 3;

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
            $attempt = self::claim($due['job_key'], $due['slot']);
            if ($attempt === 0) {
                continue; // قبلاً ارسال شده یا در حال ارسال است
            }
            Log::info('Scheduler firing job', ['job' => $due['job_key'], 'slot' => $due['slot'], 'attempt' => $attempt]);
            $result = Dispatcher::run($due['job_key'], ['slot' => $due['slot']]);

            // هیچ پیامی نرفته و خطا گذرا بوده: دقیقه‌ی بعد دوباره امتحان می‌شود.
            // ارسال ناقص (به بعضی کانال‌ها رفته) هرگز تکرار نمی‌شود تا پست دوبار نرود.
            if (($result['retry'] ?? false) && (int) ($result['sent'] ?? 0) === 0 && $attempt < self::MAX_ATTEMPTS) {
                self::release($due['job_key'], $due['slot'], $attempt);
                Log::warn('Job will retry', ['job' => $due['job_key'], 'slot' => $due['slot'], 'attempt' => $attempt]);
            }
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
            $atTime = (string) $row['at_time'];
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $atTime, $m)) {
                continue;
            }

            // اسلات امروز، و اسلات دیروز برای زمان‌های نزدیک نیمه‌شب (مثلاً ۲۳:۵۸ که
            // سرور تا ۰۰:۰۲ خواب بوده)
            foreach ([$now, $now->modify('-1 day')] as $day) {
                if (!self::matchesDay((string) $row['days'], $day)) {
                    continue;
                }
                $slotTime = $day->setTime((int) $m[1], (int) $m[2], 0);
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
        }

        return $out;
    }

    /**
     * رزرو اتمیک یک اسلات تا از ارسال تکراری جلوگیری شود.
     *
     * @return int شماره‌ی تلاش (۱ برای بار اول)، یا ۰ اگر اسلات مال کس دیگری است
     */
    private static function claim(string $jobKey, string $slot): int
    {
        $affected = Db::exec(
            'INSERT OR IGNORE INTO runs(job_key, slot, status, detail, created_at)
             VALUES(:j, :s, :st, :d, :t)',
            [':j' => $jobKey, ':s' => $slot, ':st' => 'running', ':d' => '', ':t' => time()]
        );
        if ($affected > 0) {
            return 1;
        }

        // اسلاتی که برای تلاش دوباره آزاد شده: با مقایسه‌ی وضعیت قبلی برداشته
        // می‌شود تا دو پردازش هم‌زمان هر دو آن را نگیرند.
        $row = Db::one('SELECT status FROM runs WHERE job_key = :j AND slot = :s', [':j' => $jobKey, ':s' => $slot]);
        $status = (string) ($row['status'] ?? '');
        if (!preg_match('/^retry:(\d+)$/', $status, $m)) {
            return 0;
        }
        $taken = Db::exec(
            "UPDATE runs SET status = 'running' WHERE job_key = :j AND slot = :s AND status = :old",
            [':j' => $jobKey, ':s' => $slot, ':old' => $status]
        );

        return $taken > 0 ? (int) $m[1] + 1 : 0;
    }

    /** آزاد کردن اسلات برای تلاش دوباره در تیک بعدی */
    private static function release(string $jobKey, string $slot, int $attempt): void
    {
        Db::exec(
            'UPDATE runs SET status = :st WHERE job_key = :j AND slot = :s',
            [':st' => 'retry:' . $attempt, ':j' => $jobKey, ':s' => $slot]
        );
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
