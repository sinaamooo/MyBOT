<?php

declare(strict_types=1);

namespace EmojiMaker\Bot\Controllers;

use EmojiMaker\Bot\Context;
use EmojiMaker\Bot\Texts;
use EmojiMaker\Queue\Worker;
use EmojiMaker\Telegram\Keyboard;

/**
 * /admin for bot admins (users.role = admin or listed in the admin ids):
 * quick stats, queue health and worker heartbeat, the active / failed jobs
 * (cancel, retry), maintenance mode on/off and a cleanup run. Everything else
 * lives in the web panel. Non-admins get the "unknown command" answer.
 *
 * Callbacks: adm:home, adm:q, adm:bot, adm:clean, adm:rt:<job>, adm:cx:<job>
 */
final class AdminController extends Controller
{
    public function show(Context $ctx, bool $edit = false): void
    {
        if (!$ctx->isAdmin()) {
            $ctx->reply(Texts::UNKNOWN, $this->menu());
            return;
        }
        $ctx->clearState();
        $db = $this->app->db();
        $s = $this->app->settings();
        $q = $this->app->queue()->stats();
        $files = $this->app->files()->stats();
        $enabled = $s->bool('bot_enabled', true);
        $activeToday = (int) $db->value('SELECT COUNT(*) FROM users WHERE last_seen_at >= CURDATE()');
        $newToday = (int) $db->value('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()');
        $lines = [
            '🛠 <b>مدیریت ربات</b>',
            '',
            'وضعیت: ' . ($enabled ? '✅ فعال' : '⏸ حالت تعمیر (فقط مدیرها)'),
            'کاربران: ' . Texts::fa($this->app->users()->count()) . ' · فعال امروز: ' . Texts::fa($activeToday) . ' · جدید امروز: ' . Texts::fa($newToday),
            'پروژه‌ها: ' . Texts::fa($this->app->projects()->countAll()) . ' · فایل‌ها: ' . Texts::fa($files['main']) . ' ایموجی (' . self::size($files['bytes']) . ')',
            '',
            '<b>صف</b>: ' . Texts::fa($q['by_status']['queued']) . ' در صف · ' . Texts::fa($q['by_status']['running']) . ' در حال اجرا',
            'امروز: ' . Texts::fa($q['completed_today']) . ' تمام‌شده · ' . Texts::fa($q['failed_today']) . ' ناموفق',
        ];
        if ($q['oldest_queued_seconds'] > 0) {
            $lines[] = 'انتظار قدیمی‌ترین کار: ' . self::ago($q['oldest_queued_seconds']);
        }
        if ($q['avg_duration'] !== null) {
            $lines[] = 'میانگین زمان ساخت (۲۴ ساعت): ' . Texts::fa($q['avg_duration']) . ' ثانیه';
        }
        if ($q['stale_running'] > 0) {
            $lines[] = '⚠️ ' . Texts::fa($q['stale_running']) . ' کار بیش از ۵ دقیقه بی‌پاسخ مانده است.';
        }
        $lines[] = $this->workerLine();
        $caps = $this->app->tools()->capabilities();
        $lines[] = 'FFmpeg: ' . (!empty($caps['ffmpeg']) ? '✅' : '❌') . ' · VP9 (WEBM): ' . (!empty($caps['vp9']) ? '✅' : '❌')
            . ' · اجرای فرمان: ' . (!empty($caps['proc_open']) ? '✅' : '❌');
        if (empty($caps['vp9'])) {
            $lines[] = 'ℹ️ بدون FFmpeg/VP9 فقط خروجی TGS و PNG ساخته می‌شود.';
        }
        $kb = [
            [['🔄 به‌روزرسانی', 'adm:home'], ['📋 کارهای صف', 'adm:q']],
            [[$enabled ? '⏸ توقف ربات (حالت تعمیر)' : '▶️ فعال‌سازی ربات', 'adm:bot']],
            [['🧹 پاک‌سازی فایل‌های قدیمی', 'adm:clean']],
        ];
        $url = rtrim($this->app->url(), '/');
        if (preg_match('#^https://#i', $url)) {
            $kb[] = [Keyboard::url('🌐 پنل مدیریت', $url . '/admin/')];
        }
        $text = implode("\n", $lines);
        $edit ? $ctx->edit($text, Keyboard::inline($kb)) : $ctx->reply($text, Keyboard::inline($kb));
    }

    /** @param list<string> $p */
    public function callback(Context $ctx, array $p): void
    {
        if (!$ctx->isAdmin()) {
            $this->expired($ctx);
            return;
        }
        $a = $p[1] ?? '';
        switch ($a) {
            case 'home':
                $ctx->toast();
                $this->show($ctx, true);
                return;
            case 'q':
                $ctx->toast();
                $this->jobs($ctx);
                return;
            case 'bot':
                $s = $this->app->settings();
                $on = !$s->bool('bot_enabled', true);
                $s->set('bot_enabled', $on, 'bool');
                $this->app->misc()->audit('user', $ctx->userId(), $on ? 'bot.enable' : 'bot.disable', 'setting', null, ['via' => 'bot']);
                $ctx->toast($on ? '✅ ربات فعال شد' : '⏸ ربات در حالت تعمیر است', true);
                $this->show($ctx, true);
                return;
            case 'clean':
                if (!$this->app->misc()->hit('adm:clean', 1, 60)) {
                    $ctx->toast('پاک‌سازی همین الان در صف است.');
                    return;
                }
                $id = $this->app->queue()->push('maintenance', ['task' => 'cleanup'], $ctx->userId(), null, 0, $ctx->chatId);
                $this->app->misc()->audit('user', $ctx->userId(), 'maintenance.cleanup', 'job', $id, ['via' => 'bot']);
                $ctx->toast('🧹 پاک‌سازی در صف قرار گرفت (کار #' . $id . ')', true);
                return;
            case 'rt':
                $id = (int) ($p[2] ?? 0);
                $ok = $id > 0 && $this->app->queue()->retry($id);
                if ($ok) {
                    $this->app->misc()->audit('user', $ctx->userId(), 'job.retry', 'job', $id, ['via' => 'bot']);
                }
                $ctx->toast($ok ? '🔁 دوباره در صف قرار گرفت' : 'این کار قابل تکرار نیست.');
                $this->jobs($ctx);
                return;
            case 'cx':
                $id = (int) ($p[2] ?? 0);
                $ok = $id > 0 && $this->app->queue()->cancel($id);
                if ($ok) {
                    $this->app->misc()->audit('user', $ctx->userId(), 'job.cancel', 'job', $id, ['via' => 'bot']);
                }
                $ctx->toast($ok ? '⛔️ لغو شد' : 'این کار دیگر فعال نیست.');
                $this->jobs($ctx);
                return;
        }
        $this->expired($ctx);
    }

    private function jobs(Context $ctx): void
    {
        $db = $this->app->db();
        $active = $db->fetchAll(
            "SELECT j.id, j.type, j.status, j.progress, j.created_at, u.telegram_id, u.username
             FROM generation_jobs j LEFT JOIN users u ON u.id = j.user_id
             WHERE j.status IN ('queued','running') ORDER BY j.status = 'running' DESC, j.priority DESC, j.id ASC LIMIT 10"
        );
        $failed = $db->fetchAll(
            "SELECT j.id, j.type, j.error_message, j.finished_at, u.telegram_id, u.username
             FROM generation_jobs j LEFT JOIN users u ON u.id = j.user_id
             WHERE j.status = 'failed' AND j.finished_at > (NOW() - INTERVAL 1 DAY) ORDER BY j.id DESC LIMIT 5"
        );
        $lines = ['📋 <b>کارهای صف</b>', ''];
        $kb = [];
        if (!$active) {
            $lines[] = 'کار فعالی در صف نیست.';
        }
        foreach ($active as $j) {
            $lines[] = '#' . Texts::fa((int) $j['id']) . ' ' . self::typeName((string) $j['type']) . ' — '
                . ($j['status'] === 'running' ? '⚙️ ' . Texts::fa((int) $j['progress']) . '٪' : '⏳ در صف')
                . ' — ' . self::who($j) . ' — ' . Texts::date((string) $j['created_at'], true);
            $kb[] = [['⛔️ لغو #' . $j['id'], 'adm:cx:' . (int) $j['id']]];
        }
        if ($failed) {
            $lines[] = '';
            $lines[] = '<b>ناموفق‌های ۲۴ ساعت اخیر</b>';
            $retry = [];
            foreach ($failed as $j) {
                $lines[] = '#' . Texts::fa((int) $j['id']) . ' ' . self::typeName((string) $j['type']) . ' — ' . self::who($j)
                    . "\n    " . Texts::e(Texts::cut((string) ($j['error_message'] ?? ''), 120));
                $retry[] = ['🔁 #' . $j['id'], 'adm:rt:' . (int) $j['id']];
            }
            foreach (Keyboard::rows($retry, 3) as $row) {
                $kb[] = $row;
            }
        }
        $kb[] = [['🔄 به‌روزرسانی', 'adm:q'], [Texts::BACK, 'adm:home']];
        $ctx->edit(implode("\n", $lines), Keyboard::inline($kb));
    }

    private function workerLine(): string
    {
        $hb = Worker::lastHeartbeat($this->app);
        if ($hb === null) {
            return 'ورکر: ❌ هنوز اجرا نشده (کران‌جاب یا سرویس ورکر را بررسی کنید)';
        }
        $age = max(0, time() - (int) ($hb['at'] ?? 0));
        $mode = ($hb['mode'] ?? '') === 'daemon' ? 'سرویس دائمی' : 'کران‌جاب';
        if ($age <= 150) {
            return 'ورکر: ✅ فعال (' . $mode . '، آخرین پیام ' . self::ago($age) . ' پیش)';
        }
        return 'ورکر: ⚠️ آخرین پیام ' . self::ago($age) . ' پیش (' . $mode . ') — اجرای ورکر را بررسی کنید';
    }

    private static function who(array $j): string
    {
        if (!empty($j['username'])) {
            return '@' . Texts::e((string) $j['username']);
        }
        return $j['telegram_id'] !== null ? '<code>' . (int) $j['telegram_id'] . '</code>' : 'سیستم';
    }

    private static function typeName(string $type): string
    {
        return match ($type) {
            'preview' => 'پیش‌نمایش',
            'generate' => 'ساخت',
            'publish' => 'انتشار',
            'deliver' => 'ارسال',
            'maintenance' => 'نگه‌داری',
            'update' => 'پیام',
            default => $type,
        };
    }

    private static function ago(int $seconds): string
    {
        return match (true) {
            $seconds < 90 => Texts::fa($seconds) . ' ثانیه',
            $seconds < 5400 => Texts::fa((int) round($seconds / 60)) . ' دقیقه',
            $seconds < 172800 => Texts::fa((int) round($seconds / 3600)) . ' ساعت',
            default => Texts::fa((int) round($seconds / 86400)) . ' روز',
        };
    }

    private static function size(int $bytes): string
    {
        return match (true) {
            $bytes >= 1073741824 => Texts::fa(round($bytes / 1073741824, 1)) . ' گیگابایت',
            $bytes >= 1048576 => Texts::fa(round($bytes / 1048576, 1)) . ' مگابایت',
            default => Texts::fa((int) ceil($bytes / 1024)) . ' کیلوبایت',
        };
    }
}
