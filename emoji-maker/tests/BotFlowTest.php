<?php

declare(strict_types=1);

/*
 * End-to-end conversations with the bot against the mock Bot API and the
 * test database: design wizard → preview → generate, projects, deliveries,
 * selection, publishing (new set and existing set), templates, /admin and
 * the guards. Needs TEST_DB_NAME (skipped otherwise).
 */

use EmojiMaker\Tests\Support\BotDriver as D;
use EmojiMaker\Tests\TestCase as T;

require_once __DIR__ . '/Support/BotDriver.php';

function bot(): D
{
    static $d = null;
    if ($d === null) {
        $d = new D();
    } else {
        $d->reset();
    }
    return $d;
}

/** Persian digits → int (job numbers in texts). */
function fa_int(string $s): int
{
    return (int) preg_replace('/\D/', '', strtr($s, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']));
}

/** Creates a finished single-emoji design job for $u; returns [project id, job id]. */
function design_job(D $d, int $u, string $text = 'سلام'): array
{
    $d->press($u, 'w:new:text');
    $c = $d->send($u, $text);
    $gen = D::button($c, 'w:gen:');
    $c = $d->press($u, $gen);
    $job = fa_int(implode('', array_filter(D::texts($c), static fn ($t) => str_contains($t, 'صف ساخت'))));
    $d->work();
    return [(int) explode(':', $gen)[2], $job];
}

return [
    'start shows the welcome and the persistent main menu' => function (): void {
        $d = bot();
        $c = $d->send(5001, '/start');
        T::assertCount(1, $c);
        T::assertSame('sendMessage', $c[0]['method']);
        T::assertStringContains('خوش آمدی', $c[0]['params']['text']);
        $kb = $c[0]['params']['reply_markup']['keyboard'];
        $labels = array_merge(...array_map(static fn ($row) => array_map(static fn ($b) => is_array($b) ? $b['text'] : $b, $row), $kb));
        foreach (['🎨 ساخت ایموجی جدید', '🖼 ساخت از روی عکس یا لوگو', '🔤 ساخت از روی متن', '🎞 انتخاب انیمیشن', '📦 ساخت پک چندتایی', '🗂 پروژه‌ها و خروجی‌های من', '❓ راهنما'] as $l) {
            T::assertTrue(in_array($l, $labels, true), 'menu label ' . $l);
        }
        T::assertSame('HTML', $c[0]['params']['parse_mode']);
    },

    'design wizard: text, preview, generate and delivery' => function (): void {
        $d = bot();
        $u = 5002;
        $c = $d->send($u, '🎨 ساخت ایموجی جدید');
        T::assertSame('w:new:text', D::button($c, 'w:new:text'));
        $c = $d->press($u, 'w:new:text');
        T::assertStringContains('متن ایموجی را بفرست', implode("\n", D::texts($c)));
        $c = $d->send($u, 'سلام');
        T::assertStringContains('متن: «سلام»', implode("\n", D::texts($c)), 'dashboard shows the new text');
        $c = $d->press($u, D::button($c, 'w:prev:'));
        T::assertSame(1, (int) $d->app->db()->value("SELECT COUNT(*) FROM generation_jobs WHERE type = 'preview'"));
        $c = $d->work();
        T::assertTrue(in_array('sendAnimation', D::methods($c), true), 'preview animation sent: ' . implode(',', D::methods($c)));
        $c = $d->press($u, D::button($c, 'w:gen:'));
        T::assertStringContains('صف ساخت', implode("\n", D::texts($c)));
        $c = $d->work();
        $methods = D::methods($c);
        T::assertTrue(in_array('sendDocument', $methods, true), 'emoji file sent');
        $summary = implode("\n", D::texts($c));
        T::assertStringContains('سازگار', $summary);
        $buttons = D::buttons($c);
        foreach (['pub:new:', 'j:zip:', 'j:link:', 'j:again:'] as $prefix) {
            D::button($c, $prefix);
        }
        T::assertTrue(count($buttons) >= 4);
        $file = $d->app->db()->fetch("SELECT * FROM generated_files WHERE kind = 'main'");
        T::assertSame('webm', $file['format']);
        T::assertSame('valid', $file['validation_status']);
    },

    'text validation rejects too long texts and keeps the state' => function (): void {
        $d = bot();
        $u = 5003;
        $d->press($u, 'w:new:text');
        $c = $d->send($u, str_repeat('الف', 40));
        T::assertStringContains('طولانی', implode("\n", D::texts($c)));
        T::assertSame('w_text', $d->app->users()->findByTelegramId($u)['state']);
    },

    'projects: list, open, deliveries, selection, duplicate and delete' => function (): void {
        $d = bot();
        $u = 5004;
        [$pid, $job] = design_job($d, $u);
        T::assertTrue($job > 0);
        $c = $d->send($u, '🗂 پروژه‌ها و خروجی‌های من');
        T::assertStringContains('سلام', implode("\n", D::texts($c)));
        $c = $d->press($u, 'p:open:' . $pid);
        foreach (['j:files:' . $job, 'j:zip:' . $job, 'j:link:' . $job, 'j:sel:' . $job . ':1', 'pub:new:' . $job, 'j:again:' . $job, 'p:dup:' . $pid, 'p:del:' . $pid] as $data) {
            T::assertArrayHasKey($data, D::buttons($c));
        }
        $d->press($u, 'j:zip:' . $job);
        $c = $d->work();
        T::assertSame(['sendDocument'], D::methods($c));
        T::assertMatches('/\.zip$/', (string) ($c[0]['files']['document']['name'] ?? $c[0]['files']['document'] ?? '.zip'));
        $c = $d->press($u, 'j:zip:' . $job);
        T::assertSame([], array_values(array_filter(D::methods($c), static fn ($m) => $m !== 'answerCallbackQuery')));
        T::assertSame(0, (int) $d->app->db()->value("SELECT COUNT(*) FROM generation_jobs WHERE type = 'deliver' AND status = 'queued'"), 'double tap ignored');
        $d->press($u, 'j:link:' . $job);
        $c = $d->work();
        T::assertStringContains('https://emoji.example.test/download.php?', implode("\n", D::texts($c)));
        $d->press($u, 'j:files:' . $job);
        $c = $d->work();
        T::assertSame(['sendDocument'], D::methods($c));

        $c = $d->press($u, 'j:sel:' . $job . ':1');
        T::assertArrayHasKey('j:ts:' . $job . ':0:1', D::buttons($c));
        $d->press($u, 'j:ts:' . $job . ':0:1');
        T::assertSame(0, (int) $d->app->db()->value("SELECT is_selected FROM generated_files WHERE job_id = ? AND kind = 'main'", [$job]));
        $c = $d->press($u, 'pub:new:' . $job);
        T::assertStringContains('انتخاب نشده', (string) ($c[0]['params']['text'] ?? ''));
        $d->press($u, 'j:sa:' . $job . ':1:1');
        T::assertSame(1, (int) $d->app->db()->value("SELECT is_selected FROM generated_files WHERE job_id = ? AND kind = 'main'", [$job]));

        $c = $d->press($u, 'p:dup:' . $pid);
        T::assertStringContains('(کپی)', implode("\n", D::texts($c)));
        $copy = (int) $d->app->db()->value('SELECT MAX(id) FROM projects');
        T::assertTrue($copy > $pid);
        $d->press($u, 'p:del:' . $copy);
        $d->press($u, 'p:delok:' . $copy);
        T::assertNotNull($d->app->db()->value('SELECT deleted_at FROM projects WHERE id = ?', [$copy]));
        T::assertNull($d->app->db()->value('SELECT deleted_at FROM projects WHERE id = ?', [$pid]));
    },

    'again and cancel keep the earlier outputs reachable' => function (): void {
        $d = bot();
        $u = 5005;
        [$pid, $job] = design_job($d, $u);
        $c = $d->press($u, 'j:again:' . $job);
        $again = fa_int(implode('', D::texts($c)));
        T::assertTrue($again > $job, 'new job queued');
        $c = $d->press($u, 'j:cancel:' . $again);
        T::assertSame('cancelled', $d->app->queue()->find($again)['status']);
        T::assertSame('completed', $d->app->projects()->find($pid)['status']);
        $c = $d->press($u, 'p:open:' . $pid);
        T::assertArrayHasKey('j:zip:' . $job, D::buttons($c), 'outputs of the completed job are still offered');
        T::assertStringContains('لغو شد', implode("\n", D::texts($c)));
    },

    'publish a new set with title, link name and emoji, then add to it' => function (): void {
        $d = bot();
        $u = 5006;
        [, $job] = design_job($d, $u);
        $c = $d->press($u, 'pub:new:' . $job);
        T::assertStringContains('انتشار پک ایموجی', implode("\n", D::texts($c)));
        $d->press($u, 'pub:t:' . $job);
        $c = $d->send($u, 'پک تست');
        T::assertStringContains('عنوان: <b>پک تست</b>', implode("\n", D::texts($c)));
        $d->press($u, 'pub:n:' . $job);
        $c = $d->send($u, 'bad name!');
        T::assertStringContains('معتبر نیست', implode("\n", D::texts($c)));
        $c = $d->send($u, 'MyBrand');
        T::assertStringContains('mybrand_by_' . $d->mock['username'], implode("\n", D::texts($c)));
        $d->press($u, 'pub:e:' . $job);
        $c = $d->send($u, 'hello');
        T::assertStringContains('فقط ایموجی', implode("\n", D::texts($c)));
        $c = $d->send($u, '🔥😎');
        T::assertStringContains('🔥 😎', implode("\n", D::texts($c)));
        $d->press($u, 'pub:go:' . $job);
        $publish = $d->app->db()->fetch("SELECT * FROM generation_jobs WHERE type = 'publish'");
        $payload = json_decode((string) $publish['payload'], true);
        T::assertSame('mybrand_by_' . $d->mock['username'], $payload['set_name']);
        T::assertSame('🔥 😎', $payload['emoji']);
        T::assertSame('پک تست', $payload['title']);
        $c = $d->work();
        T::assertTrue(in_array('createNewStickerSet', D::methods($c), true));
        T::assertStringContains('https://t.me/addemoji/mybrand_by_' . $d->mock['username'], implode("\n", D::texts($c)));
        $set = $d->app->db()->fetch('SELECT * FROM sticker_sets');
        T::assertSame('published', $set['status']);
        T::assertSame(1, (int) $set['item_count']);
        T::assertNotNull($d->app->db()->value("SELECT custom_emoji_id FROM generated_files WHERE kind = 'main'"));

        // the same emoji are already in that set: nothing to publish there
        $d->press($u, 'pub:new:' . $job);
        $c = $d->press($u, 'pub:s:' . $job . ':' . $set['id']);
        T::assertStringContains('قبلاً در این پک', implode("\n", D::texts($c)));
        T::assertFalse(isset(D::buttons($c)['pub:go:' . $job]));

        // a second build goes into the existing set
        [, $job2] = design_job($d, $u, 'دو');
        $d->press($u, 'pub:new:' . $job2);
        $c = $d->press($u, 'pub:d:' . $job2);
        T::assertArrayHasKey('pub:s:' . $job2 . ':' . $set['id'], D::buttons($c));
        $d->press($u, 'pub:s:' . $job2 . ':' . $set['id']);
        $d->press($u, 'pub:go:' . $job2);
        $c = $d->work();
        T::assertTrue(in_array('addStickerToSet', D::methods($c), true), implode(',', D::methods($c)));
        T::assertSame(2, (int) $d->app->db()->value('SELECT item_count FROM sticker_sets WHERE id = ?', [$set['id']]));
    },

    'templates: gallery, selection, logo, text, colour and generation' => function (): void {
        $d = bot();
        $u = 5007;
        $c = $d->send($u, '✨ قالب‌های آماده پریمیوم');
        $pack = D::button($c, 't:pk:');
        $c = $d->press($u, $pack);
        T::assertTrue(in_array('sendPhoto', D::methods($c), true), 'contact sheet sent');
        $toggle = D::button($c, 't:tg:');
        $pid = (int) explode(':', $toggle)[2];
        $c = $d->press($u, $toggle, null, true);
        T::assertTrue(in_array('editMessageReplyMarkup', D::methods($c), true));
        $cfg = json_decode((string) $d->app->projects()->find($pid)['config'], true);
        T::assertCount(1, $cfg['templates']);
        $d->press($u, 't:edit:' . $pid);
        $d->press($u, 't:logo:' . $pid);
        $c = $d->document($u, 'logo.png');
        T::assertStringContains('لوگو: ✅', implode("\n", D::texts($c)));
        $d->press($u, 't:txt:' . $pid);
        $d->send($u, 'SALE');
        $d->press($u, 't:co:' . $pid);
        $d->press($u, 't:rc:' . $pid . ':E53935');
        $c = $d->press($u, 't:fm:' . $pid . ':png');
        $cfg = json_decode((string) $d->app->projects()->find($pid)['config'], true);
        T::assertSame('png', $cfg['format']);
        T::assertSame('#E53935', $cfg['recolor']['color']);
        $c = $d->press($u, 't:gen:' . $pid);
        T::assertStringContains('صف ساخت', implode("\n", D::texts($c)));
        $job = $d->app->db()->fetch("SELECT * FROM generation_jobs WHERE type = 'generate'");
        $item = json_decode((string) $job['payload'], true)['items'][0];
        T::assertSame('SALE', $item['content']['text']);
        T::assertSame('#E53935', $item['content']['recolor']);
        T::assertTrue(is_int($item['content']['logo']) && $item['content']['logo'] > 0, 'logo asset id');
        $c = $d->work();
        T::assertTrue(in_array('sendDocument', D::methods($c), true), implode(',', D::methods($c)));
        T::assertSame('completed', $d->app->queue()->find((int) $job['id'])['status']);
    },

    'non-admins do not see the template original colours option' => function (): void {
        $d = bot();
        $u = 5008;
        $c = $d->send($u, '/templates');
        $c = $d->press($u, D::button($c, 't:pk:'));
        $pid = (int) explode(':', D::button($c, 't:tg:'))[2];
        $c = $d->press($u, 't:co:' . $pid);
        T::assertFalse(isset(D::buttons($c)['t:rc:' . $pid . ':orig']));
        $c = $d->press($u, 't:rc:' . $pid . ':orig');
        $cfg = json_decode((string) $d->app->projects()->find($pid)['config'], true);
        T::assertNotSame('orig', $cfg['recolor']['color'] ?? null);
        $a = D::ADMIN;
        $c = $d->send($a, '/templates');
        $c = $d->press($a, D::button($c, 't:pk:'));
        $apid = (int) explode(':', D::button($c, 't:tg:'))[2];
        $c = $d->press($a, 't:co:' . $apid);
        T::assertArrayHasKey('t:rc:' . $apid . ':orig', D::buttons($c));
    },

    'admin: stats, maintenance mode, queue and cleanup' => function (): void {
        $d = bot();
        $u = 5009;
        $a = D::ADMIN;
        $c = $d->send($u, '/admin');
        T::assertStringContains('متوجه نشدم', implode("\n", D::texts($c)));
        $c = $d->press($u, 'adm:bot');
        T::assertTrue($d->app->settings()->bool('bot_enabled', true), 'a user cannot switch the bot off');
        $c = $d->send($a, '/admin');
        T::assertStringContains('مدیریت ربات', implode("\n", D::texts($c)));
        $d->press($a, 'adm:bot');
        T::assertFalse($d->app->settings()->bool('bot_enabled', true));
        $c = $d->send($u, '/start');
        T::assertStringContains('🛠', implode("\n", D::texts($c)));
        $c = $d->send($a, '/help');
        T::assertStringContains('راهنمای ربات', implode("\n", D::texts($c)), 'admins bypass maintenance mode');
        $d->press($a, 'adm:bot');
        T::assertTrue($d->app->settings()->bool('bot_enabled', false));
        $c = $d->press($a, 'adm:q');
        T::assertStringContains('کارهای صف', implode("\n", D::texts($c)));
        $d->press($a, 'adm:clean');
        $c = $d->work();
        T::assertStringContains('پاک‌سازی تمام شد', implode("\n", D::texts($c)));
        T::assertSame(2, (int) $d->app->db()->value("SELECT COUNT(*) FROM audit_logs WHERE action IN ('bot.enable','bot.disable')"));
    },

    'guards: blocked users, rate limit, groups and foreign buttons' => function (): void {
        $d = bot();
        $u = 5010;
        $d->send($u, '/start');
        $id = (int) $d->app->users()->findByTelegramId($u)['id'];
        $d->app->users()->setBlocked($id, true);
        $c = $d->send($u, '/start');
        T::assertStringContains('محدود', implode("\n", D::texts($c)));
        $d->app->users()->setBlocked($id, false);

        $d->app->settings()->set('rate_limit_per_minute', 5, 'int');
        $notices = 0;
        $answered = 0;
        for ($i = 0; $i < 9; $i++) {
            foreach ($d->send($u, '/help') as $call) {
                $answered++;
                $notices += str_contains((string) ($call['params']['text'] ?? ''), 'آهسته') ? 1 : 0;
            }
        }
        T::assertSame(1, $notices, 'one notice per minute');
        T::assertTrue($answered < 9);
        $d->app->settings()->set('rate_limit_per_minute', 40, 'int');

        $c = $d->process(['message' => ['message_id' => 9, 'date' => time(), 'chat' => ['id' => -100123, 'type' => 'supergroup'], 'from' => ['id' => 42, 'is_bot' => false, 'first_name' => 'g'], 'text' => '/start']]);
        T::assertSame([], $c, 'groups are ignored');

        [$pid, $job] = design_job($d, 5011);
        foreach (['w:dash:' . $pid, 'p:open:' . $pid, 'p:delok:' . $pid, 'j:zip:' . $job, 'j:cancel:' . $job, 'pub:new:' . $job, 'j:ts:' . $job . ':0:1', 'zz:1'] as $data) {
            $c = $d->press(5012, $data);
            T::assertSame(['answerCallbackQuery'], D::methods($c), $data);
            T::assertStringContains('متعلق به شما نیست', (string) $c[0]['params']['text'], $data);
        }
        T::assertNull($d->app->db()->value('SELECT deleted_at FROM projects WHERE id = ?', [$pid]));
    },

    'an image without context starts a design from it' => function (): void {
        $d = bot();
        $c = $d->photo(5013, 'logo.png');
        T::assertStringContains('تصویر: ✅', implode("\n", D::texts($c)));
        T::assertSame(1, (int) $d->app->db()->value("SELECT COUNT(*) FROM project_assets"));
    },

    'Solar Hijri dates and emoji parsing' => function (): void {
        T::assertSame('۱۴۰۵/۰۷/۳۰', \EmojiMaker\Bot\Texts::date('2026-10-22 10:00:00'));
        T::assertSame('۱۴۰۴/۰۱/۰۱', \EmojiMaker\Bot\Texts::date('2025-03-21'));
        T::assertSame('۱۴۰۳/۱۲/۳۰', \EmojiMaker\Bot\Texts::date('2025-03-20'));
        T::assertSame(['🔥', '⭐️', '👍🏽', '1️⃣', '🇮🇷', '👨‍👩‍👧'], \EmojiMaker\Bot\Controllers\PublishController::parseEmoji('🔥⭐️ 👍🏽,1️⃣ 🇮🇷 👨‍👩‍👧'));
        T::assertNull(\EmojiMaker\Bot\Controllers\PublishController::parseEmoji('سلام'));
        T::assertNull(\EmojiMaker\Bot\Controllers\PublishController::parseEmoji(''));
    },
];
