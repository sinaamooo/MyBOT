<?php

declare(strict_types=1);

namespace EmojiMaker\Bot\Controllers;

use EmojiMaker\Bot\Context;
use EmojiMaker\Bot\Texts;
use EmojiMaker\Telegram\Keyboard;
use EmojiMaker\Telegram\StickerPublisher;
use EmojiMaker\Telegram\TelegramException;

/**
 * Publishing the outputs of a generate job as a Telegram custom emoji set.
 * A "publish card" (one message, edited in place) collects the title, the
 * related emoji, the destination (new set / one of the user's sets), the
 * set link name and the repainting flag, then queues a "publish" job.
 *
 * The draft lives in users.state_data while the state is pub_*:
 * {"job", "title", "emoji", "set", "repaint", "name"}.
 *
 * Callbacks: pub:new|c|t|e|n|na|d|r|go|x:<job>, pub:em:<job>:<i>, pub:s:<job>:<set id|0>
 */
final class PublishController extends Controller
{
    private const EMOJI = ['⭐', '🔥', '❤️', '😎', '✅', '💎', '🎉', '👑', '🚀', '💯', '👍', '😍'];
    private const MAX_SET = 200;

    /** @param list<string> $p */
    public function callback(Context $ctx, array $p): void
    {
        $a = $p[1] ?? '';
        $jobId = (int) ($p[2] ?? 0);
        if (!$this->allowed($ctx)) {
            return;
        }
        $job = $this->job($ctx, $jobId);
        if ($job === null) {
            return;
        }
        if ($a === 'new') {
            $draft = $this->draft($ctx, $job, true);
            if ($draft === null) {
                return;
            }
            $ctx->toast();
            $this->card($ctx, $draft, false);
            return;
        }
        $draft = $this->draft($ctx, $job, false);
        if ($draft === null) {
            return;
        }
        switch ($a) {
            case 'c':
                $this->setDraft($ctx, 'pub_card', $draft);
                $ctx->toast();
                $this->card($ctx, $draft, true);
                return;
            case 't':
                $this->setDraft($ctx, 'pub_title', $draft);
                $ctx->toast();
                $ctx->reply("✏️ عنوان پک را بفرست (حداکثر ۶۴ حرف).\nاین عنوان بالای پک در تلگرام نمایش داده می‌شود.", Keyboard::inline([[['✖️ انصراف', 'pub:c:' . $jobId]]]));
                return;
            case 'e':
                $this->setDraft($ctx, 'pub_emoji', $draft);
                $ctx->toast();
                $buttons = [];
                foreach (self::EMOJI as $i => $e) {
                    $buttons[] = [$e, 'pub:em:' . $jobId . ':' . $i];
                }
                $kb = Keyboard::rows($buttons, 6);
                $kb[] = [[Texts::BACK, 'pub:c:' . $jobId]];
                $ctx->edit("😀 <b>ایموجی مرتبط</b>\nتلگرام هر ایموجی سفارشی را به یک یا چند ایموجی معمولی وصل می‌کند (برای پیشنهاد هنگام تایپ).\n\nیکی را انتخاب کن یا ایموجی‌های دلخواهت را بفرست (تا ۲۰ عدد).", Keyboard::inline($kb));
                return;
            case 'em':
                $e = self::EMOJI[(int) ($p[3] ?? -1)] ?? null;
                if ($e === null) {
                    $this->expired($ctx);
                    return;
                }
                $draft['emoji'] = $e;
                $this->setDraft($ctx, 'pub_card', $draft);
                $ctx->toast('ایموجی: ' . $e);
                $this->card($ctx, $draft, true);
                return;
            case 'n':
                if ((int) $draft['set'] > 0) {
                    $ctx->toast('نام لینک فقط برای پک جدید انتخاب می‌شود.', true);
                    return;
                }
                $this->setDraft($ctx, 'pub_name', $draft);
                $ctx->toast();
                $suffix = '_by_' . strtolower($this->app->botUsername());
                $ctx->reply(
                    "🔗 نام لینک پک را بفرست (فقط حروف انگلیسی، عدد و _ ، با حرف شروع شود).\n"
                    . 'مثال: <code>mybrand</code> → <code>t.me/addemoji/mybrand' . Texts::e($suffix) . "</code>\n"
                    . 'حداکثر ' . Texts::fa(64 - strlen($suffix)) . ' کاراکتر.',
                    Keyboard::inline([[['🔄 ساخت خودکار', 'pub:na:' . $jobId], ['✖️ انصراف', 'pub:c:' . $jobId]]])
                );
                return;
            case 'na':
                $draft['name'] = null;
                $this->setDraft($ctx, 'pub_card', $draft);
                $ctx->toast('نام لینک خودکار ساخته می‌شود');
                $this->card($ctx, $draft, true);
                return;
            case 'd':
                $this->setDraft($ctx, 'pub_card', $draft);
                $ctx->toast();
                $this->destinations($ctx, $draft);
                return;
            case 's':
                $setId = (int) ($p[3] ?? 0);
                if ($setId > 0) {
                    $set = $this->app->misc()->stickerSetForUser($setId, $ctx->userId());
                    if ($set === null || $set['status'] !== 'published') {
                        $this->expired($ctx);
                        return;
                    }
                    $draft['name'] = null;
                    $draft['repaint'] = (bool) $set['needs_repainting'];
                }
                $draft['set'] = $setId;
                $this->setDraft($ctx, 'pub_card', $draft);
                $ctx->toast();
                $this->card($ctx, $draft, true);
                return;
            case 'r':
                if ((int) $draft['set'] > 0) {
                    $ctx->toast('این ویژگی هنگام ساخت پک تعیین می‌شود و برای پک موجود قابل تغییر نیست.', true);
                    return;
                }
                $draft['repaint'] = !$draft['repaint'];
                $this->setDraft($ctx, 'pub_card', $draft);
                $ctx->toast($draft['repaint'] ? 'تطبیق رنگ روشن شد' : 'تطبیق رنگ خاموش شد');
                $this->card($ctx, $draft, true);
                return;
            case 'go':
                $this->go($ctx, $job, $draft);
                return;
            case 'x':
                $ctx->clearState();
                $ctx->toast();
                $ctx->edit('انتشار لغو شد. هر وقت خواستی از «🗂 پروژه‌ها و خروجی‌های من» دوباره منتشر کن.');
                return;
        }
        $this->expired($ctx);
    }

    /** Text input while a pub_* state is active (title, emoji, link name). */
    public function input(Context $ctx): void
    {
        $data = $ctx->stateData();
        $job = (int) ($data['job'] ?? 0) > 0 ? $this->app->queue()->find((int) $data['job']) : null;
        if ($job === null || (int) $job['user_id'] !== $ctx->userId()) {
            $ctx->clearState();
            $ctx->reply(Texts::UNKNOWN, $this->menu());
            return;
        }
        $draft = $this->normalize($data, $job);
        $text = $ctx->text();
        switch ($ctx->state()) {
            case 'pub_title':
                $title = (string) preg_replace('/(?![\x{200C}\x{200D}])\p{Cf}/u', '', $text);
                $title = trim((string) preg_replace('/[\p{Cc}\s]+/u', ' ', $title));
                if ($title === '' || mb_strlen($title) > 64) {
                    $ctx->reply('عنوان باید بین ۱ تا ۶۴ حرف باشد. دوباره بفرست:');
                    return;
                }
                $draft['title'] = $title;
                break;
            case 'pub_emoji':
                $list = self::parseEmoji($text);
                if ($list === null) {
                    $ctx->reply('فقط ایموجی بفرست (مثلاً 🔥 یا ⭐️😎)، حداکثر ۲۰ عدد:');
                    return;
                }
                $draft['emoji'] = implode(' ', $list);
                break;
            case 'pub_name':
                $name = $this->checkName($ctx, $text);
                if ($name === null) {
                    return;
                }
                $draft['name'] = $name;
                break;
            default:
                $ctx->reply('برای ادامه‌ی انتشار از دکمه‌های کارت انتشار استفاده کن 👇');
                break;
        }
        $this->setDraft($ctx, 'pub_card', $draft);
        $this->card($ctx, $draft, false);
    }

    // -------------------------------------------------------------- views

    /** @param array<string,mixed> $draft */
    private function card(Context $ctx, array $draft, bool $edit): void
    {
        $jobId = (int) $draft['job'];
        $set = (int) $draft['set'] > 0 ? $this->app->misc()->stickerSetForUser((int) $draft['set'], $ctx->userId()) : null;
        $count = $this->selectedCount($jobId, $set !== null ? (int) $set['id'] : 0);
        $lines = ['📤 <b>انتشار پک ایموجی</b>', ''];
        $lines[] = 'تعداد: ' . Texts::fa($count) . ' ایموجی (ساخت #' . Texts::fa($jobId) . ')';
        if ($set !== null && $count === 0) {
            $lines[] = 'ℹ️ ایموجی‌های انتخاب‌شده‌ی این ساخت قبلاً در این پک هستند.';
        }
        if ($set !== null) {
            $lines[] = 'مقصد: ➕ افزودن به «' . Texts::e((string) $set['title']) . '» (' . Texts::fa((int) $set['item_count']) . ' ایموجی)';
            $room = self::MAX_SET - (int) $set['item_count'];
            if ($count > $room) {
                $lines[] = '⚠️ این پک فقط جای ' . Texts::fa(max(0, $room)) . ' ایموجی دیگر دارد.';
            }
        } else {
            $lines[] = 'مقصد: 🆕 پک جدید';
            $lines[] = 'عنوان: <b>' . Texts::e((string) $draft['title']) . '</b>';
            $lines[] = 'لینک: ' . ($draft['name'] !== null
                ? '<code>t.me/addemoji/' . Texts::e((string) $draft['name']) . '</code>'
                : 'خودکار ساخته می‌شود');
            $lines[] = 'تطبیق رنگ با تم: ' . ($draft['repaint'] ? '✅ روشن' : 'خاموش');
            if ($count > self::MAX_SET) {
                $lines[] = '⚠️ هر پک حداکثر ' . Texts::fa(self::MAX_SET) . ' ایموجی دارد؛ بقیه منتشر نمی‌شوند.';
            }
        }
        $lines[] = 'ایموجی مرتبط: ' . Texts::e((string) $draft['emoji']);
        $lines[] = '';
        $lines[] = $draft['repaint'] && $set === null
            ? 'ℹ️ تطبیق رنگ: ایموجی‌ها در پیام‌ها به رنگ متن/تم کاربر درمی‌آیند؛ فقط برای طرح‌های تک‌رنگ مناسب است.'
            : 'ℹ️ بعد از انتشار، لینک افزودن پک برایت فرستاده می‌شود. استفاده از ایموجی سفارشی به تلگرام پریمیوم نیاز دارد.';
        $kb = [];
        $kb[] = $set === null
            ? [['✏️ عنوان', 'pub:t:' . $jobId], ['😀 ایموجی', 'pub:e:' . $jobId]]
            : [['😀 ایموجی', 'pub:e:' . $jobId]];
        $kb[] = [['🗂 مقصد: ' . ($set !== null ? Texts::cut((string) $set['title'], 18) : 'پک جدید'), 'pub:d:' . $jobId]];
        if ($set === null) {
            $kb[] = [['🔗 نام لینک', 'pub:n:' . $jobId], ['🎨 تطبیق رنگ: ' . ($draft['repaint'] ? 'روشن' : 'خاموش'), 'pub:r:' . $jobId]];
        }
        $kb[] = [['☑️ انتخاب ایموجی‌ها', 'j:sel:' . $jobId . ':1']];
        if ($count > 0) {
            $kb[] = [['✅ انتشار', 'pub:go:' . $jobId]];
        }
        $kb[] = [['✖️ انصراف', 'pub:x:' . $jobId]];
        $text = implode("\n", $lines);
        $edit ? $ctx->edit($text, Keyboard::inline($kb)) : $ctx->reply($text, Keyboard::inline($kb));
    }

    /** @param array<string,mixed> $draft */
    private function destinations(Context $ctx, array $draft): void
    {
        $jobId = (int) $draft['job'];
        $sets = array_values(array_filter(
            $this->app->misc()->stickerSetsForUser($ctx->userId()),
            static fn ($s) => $s['status'] === 'published'
        ));
        $kb = [[[((int) $draft['set'] === 0 ? '✅ ' : '') . '🆕 پک جدید', 'pub:s:' . $jobId . ':0']]];
        foreach (array_slice($sets, 0, 12) as $s) {
            $full = (int) $s['item_count'] >= self::MAX_SET;
            $label = ((int) $draft['set'] === (int) $s['id'] ? '✅ ' : '') . Texts::cut((string) $s['title'], 24)
                . ' (' . Texts::fa((int) $s['item_count']) . ($full ? '، پر' : '') . ')';
            $kb[] = [[$label, $full ? 'pub:d:' . $jobId : 'pub:s:' . $jobId . ':' . (int) $s['id']]];
        }
        $kb[] = [[Texts::BACK, 'pub:c:' . $jobId]];
        $text = "🗂 <b>مقصد انتشار</b>\n";
        $text .= $sets
            ? 'یک پک جدید بساز یا ایموجی‌ها را به یکی از پک‌های قبلی‌ات اضافه کن (هر پک تا ۲۰۰ ایموجی).'
            : 'هنوز پکی منتشر نکرده‌ای؛ یک پک جدید ساخته می‌شود.';
        $ctx->edit($text, Keyboard::inline($kb));
    }

    // ------------------------------------------------------------ actions

    /** @param array<string,mixed> $draft */
    private function go(Context $ctx, array $job, array $draft): void
    {
        $jobId = (int) $job['id'];
        $count = $this->selectedCount($jobId, (int) $draft['set']);
        if ($count === 0) {
            $ctx->toast((int) $draft['set'] > 0 ? 'این ایموجی‌ها قبلاً در این پک هستند.' : 'هیچ ایموجی سازگاری انتخاب نشده است.', true);
            return;
        }
        $setId = null;
        if ((int) $draft['set'] > 0) {
            $set = $this->app->misc()->stickerSetForUser((int) $draft['set'], $ctx->userId());
            if ($set === null || $set['status'] !== 'published') {
                $ctx->toast('پک انتخاب‌شده پیدا نشد؛ مقصد را دوباره انتخاب کن.', true);
                return;
            }
            if ((int) $set['item_count'] >= self::MAX_SET) {
                $ctx->toast('این پک پر است (۲۰۰ ایموجی). پک جدید بساز.', true);
                return;
            }
            $setId = (int) $set['id'];
        } elseif ($draft['name'] !== null && $this->app->misc()->stickerSetNameExists((string) $draft['name'])) {
            $ctx->toast('این نام لینک قبلاً استفاده شده است؛ نام دیگری انتخاب کن.', true);
            return;
        }
        // double taps on the same card (a second publish with other choices is fine)
        if (!$this->app->misc()->hit('pub:' . $jobId . ':' . substr(md5((string) json_encode($draft)), 0, 12), 1, 20)) {
            $ctx->toast('⏳ درخواست انتشار در حال ثبت است…');
            return;
        }
        $id = $this->pushJob($ctx, 'publish', [
            'generate_job_id' => $jobId,
            'file_ids' => [],
            'set_name' => $setId === null ? $draft['name'] : null,
            'title' => (string) $draft['title'],
            'emoji' => (string) $draft['emoji'],
            'needs_repainting' => $setId === null && (bool) $draft['repaint'],
            'sticker_set_id' => $setId,
        ], $job['project_id'] !== null ? (int) $job['project_id'] : null, 0, 6);
        if ($id === null) {
            return;
        }
        $ctx->clearState();
        $ctx->toast('در صف انتشار قرار گرفت');
        $ctx->edit('⏳ درخواست انتشار ثبت شد (کار #' . Texts::fa($id) . ").\nوقتی پک آماده شد لینک افزودن آن همین‌جا فرستاده می‌شود.", Keyboard::inline([[['✖️ لغو', 'j:cancel:' . $id]]]));
    }

    // ------------------------------------------------------------ helpers

    private function allowed(Context $ctx): bool
    {
        if (!$this->app->settings()->bool('allow_publish', true)) {
            $ctx->toast('انتشار پک ایموجی فعلاً غیرفعال است.', true);
            return false;
        }
        if (($this->perms($ctx)['publish'] ?? true) === false) {
            $ctx->toast('امکان انتشار پک برای حساب شما فعال نیست.', true);
            return false;
        }
        try {
            $bot = $this->app->botUsername();
        } catch (\Throwable) {
            $bot = '';
        }
        if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/i', $bot)) {
            $ctx->toast('انتشار پک هنوز روی این ربات تنظیم نشده است (نام کاربری ربات).', true);
            return false;
        }
        return true;
    }

    /** Completed generate job of this user. @return array<string,mixed>|null */
    private function job(Context $ctx, int $jobId): ?array
    {
        $job = $jobId > 0 ? $this->app->queue()->find($jobId) : null;
        if ($job === null || (int) $job['user_id'] !== $ctx->userId() || $job['type'] !== 'generate') {
            $this->expired($ctx);
            return null;
        }
        if ($job['status'] !== 'completed') {
            $ctx->toast('این ساخت هنوز تمام نشده یا خروجی ندارد.', true);
            return null;
        }
        return $job;
    }

    /**
     * The draft of this job from the conversation state, or a fresh one.
     * @return array<string,mixed>|null
     */
    private function draft(Context $ctx, array $job, bool $fresh): ?array
    {
        $data = $ctx->stateData();
        $same = str_starts_with($ctx->state(), 'pub_') && (int) ($data['job'] ?? 0) === (int) $job['id'];
        $draft = $this->normalize($same ? $data : [], $job);
        if ($fresh) {
            if ($this->selectedCount((int) $job['id']) === 0) {
                $ctx->toast('هیچ ایموجی سازگاری انتخاب نشده است؛ از «☑️ انتخاب ایموجی‌ها» چند مورد را انتخاب کن.', true);
                return null;
            }
            $this->setDraft($ctx, 'pub_card', $draft);
        }
        return $draft;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{job:int,title:string,emoji:string,set:int,repaint:bool,name:?string}
     */
    private function normalize(array $data, array $job): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $project = $job['project_id'] !== null ? $this->app->projects()->find((int) $job['project_id']) : null;
            $title = trim(str_replace("\n", ' ', (string) ($project['title'] ?? '')));
            $title = $title !== '' ? mb_substr($title, 0, 64) : 'Emoji Pack';
        }
        $emoji = trim((string) ($data['emoji'] ?? ''));
        return [
            'job' => (int) $job['id'],
            'title' => $title,
            'emoji' => $emoji !== '' ? $emoji : $this->app->settings()->string('publish_default_emoji', StickerPublisher::DEFAULT_EMOJI),
            'set' => max(0, (int) ($data['set'] ?? 0)),
            'repaint' => !empty($data['repaint']),
            'name' => is_string($data['name'] ?? null) && $data['name'] !== '' ? $data['name'] : null,
        ];
    }

    /** @param array<string,mixed> $draft */
    private function setDraft(Context $ctx, string $state, array $draft): void
    {
        $ctx->setState($state, $draft);
    }

    /**
     * Stickers that would be published: selected main files that passed
     * validation (and, for an existing set, are not in that set yet).
     */
    private function selectedCount(int $jobId, int $setId = 0): int
    {
        $storage = $this->app->storage();
        return count(array_filter(
            $this->app->files()->forJob($jobId, 'main', true),
            static fn ($r) => $r['validation_status'] !== 'invalid' && $storage->exists((string) $r['path'])
                && ($setId === 0 || (int) ($r['sticker_set_id'] ?? 0) !== $setId || empty($r['custom_emoji_id']))
        ));
    }

    /** Validates a custom link name; tells the user what is wrong. */
    private function checkName(Context $ctx, string $text): ?string
    {
        $bot = strtolower($this->app->botUsername());
        $suffix = '_by_' . $bot;
        $base = strtolower(trim($text));
        if (str_ends_with($base, $suffix)) {
            $base = substr($base, 0, -strlen($suffix));
        }
        $max = 64 - strlen($suffix);
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $base) || str_contains($base, '__') || str_ends_with($base, '_') || strlen($base) > $max) {
            $ctx->reply('نام معتبر نیست. فقط حروف انگلیسی کوچک، عدد و _ (با حرف شروع شود، بدون __ و حداکثر ' . Texts::fa($max) . ' کاراکتر). دوباره بفرست:');
            return null;
        }
        $name = $base . $suffix;
        $publisher = new StickerPublisher($ctx->tg(), $bot, (array) $this->app->config()->get('telegram_formats.pack', []));
        if (!$publisher->isValidName($name)) {
            $ctx->reply('این نام قابل استفاده نیست؛ نام دیگری بفرست:');
            return null;
        }
        if ($this->app->misc()->stickerSetNameExists($name) || $this->takenOnTelegram($ctx, $name)) {
            $ctx->reply('این نام قبلاً گرفته شده است؛ نام دیگری بفرست:');
            return null;
        }
        return $name;
    }

    private function takenOnTelegram(Context $ctx, string $name): bool
    {
        try {
            $ctx->tg()->getStickerSet($name);
            return true;
        } catch (TelegramException) {
            return false; // STICKERSET_INVALID: free (other errors are caught by the publish job)
        }
    }

    /**
     * One or more emoji (grapheme clusters), separated or not.
     * @return list<string>|null
     */
    public static function parseEmoji(string $s): ?array
    {
        $parts = preg_split('/[\s,،]+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $part) {
            foreach (self::clusters($part) as $g) {
                if (!self::isEmoji($g)) {
                    return null;
                }
                $out[] = $g;
            }
        }
        $out = array_values(array_unique($out));
        return $out && count($out) <= 20 ? $out : null;
    }

    /**
     * Splits a string into emoji clusters (variation selectors, keycaps, skin
     * tones, ZWJ sequences, tag sequences and flag pairs stay together). PCRE
     * \X is not used: older PCRE2 builds do not split adjacent pictographs.
     * @return list<string>
     */
    private static function clusters(string $s): array
    {
        $out = [];
        $cur = '';
        $afterZwj = false;
        $flags = 0;
        foreach (mb_str_split($s, 1, 'UTF-8') as $ch) {
            $cp = mb_ord($ch, 'UTF-8');
            $isFlag = $cp >= 0x1F1E6 && $cp <= 0x1F1FF;
            $joins = $cur !== '' && ($afterZwj
                || $cp === 0xFE0F || $cp === 0xFE0E || $cp === 0x200D || $cp === 0x20E3
                || ($cp >= 0x1F3FB && $cp <= 0x1F3FF)
                || ($cp >= 0xE0020 && $cp <= 0xE007F)
                || ($isFlag && $flags === 1));
            if (!$joins) {
                if ($cur !== '') {
                    $out[] = $cur;
                }
                $cur = '';
                $flags = 0;
            }
            $cur .= $ch;
            $afterZwj = $cp === 0x200D;
            $flags += $isFlag ? 1 : 0;
        }
        if ($cur !== '') {
            $out[] = $cur;
        }
        return $out;
    }

    private static function isEmoji(string $g): bool
    {
        $cp = mb_ord(mb_substr($g, 0, 1, 'UTF-8'), 'UTF-8');
        if ($cp === false || strlen($g) > 64) {
            return false;
        }
        if ($cp < 0x80) {
            // keycaps: 1️⃣ #️⃣ *️⃣
            return preg_match('/^[0-9#*]\x{FE0F}?\x{20E3}$/u', $g) === 1;
        }
        return $cp === 0xA9 || $cp === 0xAE
            || ($cp >= 0x203C && $cp <= 0x3299)
            || ($cp >= 0x1F000 && $cp <= 0x1FAFF);
    }
}
