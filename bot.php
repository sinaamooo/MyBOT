<?php
/**
 * Premium emoji pack maker bot.
 *
 * Flow: /start → user sends a pack title → user sends premium (custom) emojis
 * in one or more messages → "build" → the bot creates a custom emoji set owned
 * by the user and replies with its t.me/addemoji link.
 *
 * Webhook mode: point Telegram at this file (see set_webhook.php).
 * Polling mode: run `php bot.php` from the command line.
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

const BUILD_TIMEOUT = 900; // seconds after which a stuck "building" state is ignored

const TXT_WELCOME = "👋 سلام <b>%s</b>!\n\n"
    . "با این ربات می‌تونی از ایموجی‌های پریمیوم، پک ایموجی اختصاصی خودت رو بسازی.\n\n"
    . "📝 اول یک <b>اسم</b> برای پکت بفرست:";
const TXT_HELP = "📖 <b>راهنما</b>\n\n"
    . "1️⃣ /start رو بزن و اسم پک رو بفرست.\n"
    . "2️⃣ ایموجی‌های پریمیوم رو بفرست (می‌تونی چند پیام پشت هم بفرستی، تا %d ایموجی).\n"
    . "3️⃣ دکمه «✅ ساخت پک» رو بزن (یا /done).\n\n"
    . "🔗 ربات پک رو می‌سازه و لینکش رو برات می‌فرسته.\n"
    . "❌ برای لغو: /cancel";
const TXT_ASK_TITLE = "📝 اسم پک جدید رو بفرست:";
const TXT_ASK_TITLE_AGAIN = "📝 لطفاً اسم پک رو به صورت یک متن ساده بفرست:";
const TXT_TITLE_TOO_LONG = "⚠️ اسم پک حداکثر می‌تونه ۶۴ کاراکتر باشه (اسم تو %d کاراکتره). یک اسم کوتاه‌تر بفرست:";
const TXT_ASK_EMOJIS = "✅ اسم پک: <b>%s</b>\n\n"
    . "😎 حالا <b>ایموجی‌های پریمیوم</b> رو بفرست.\n"
    . "می‌تونی چند تا پیام پشت هم بفرستی (حداکثر %d ایموجی).\n\n"
    . "هر وقت تموم شد دکمه «✅ ساخت پک» رو بزن.";
const TXT_NO_CUSTOM_EMOJI = "🤔 توی این پیام ایموجی پریمیوم پیدا نکردم.\n"
    . "فقط ایموجی‌های پریمیوم (اختصاصی/متحرک) قابل استفاده‌ان.";
const TXT_NO_EMOJI_YET = "هنوز هیچ ایموجی پریمیومی نفرستادی 🙂";
const TXT_START_FIRST = "اول با /start یک پک جدید شروع کن.";
const TXT_BUSY = "⏳ پک قبلی در حال ساخته، چند لحظه صبر کن...";
const TXT_CANCELLED = "❌ لغو شد.\nبرای ساخت پک جدید /start رو بزن.";
const TXT_PROGRESS = "⏳ در حال ساخت پک <b>%s</b>...\n\n%s  %d/%d";
const TXT_DONE = "✅ پک ایموجی <b>%s</b> ساخته شد!\n\n"
    . "🔢 تعداد: <b>%d</b> ایموجی\n"
    . "🔗 لینک پک:\n%s";
const TXT_SKIPPED = "\n\n⚠️ %d ایموجی قابل اضافه شدن نبود.";
const TXT_FAILED = "❌ ساخت پک انجام نشد.\n\n<code>%s</code>\n\nمی‌تونی دوباره امتحان کنی.";

// ---------------------------------------------------------------- updates

function handleUpdate(array $update): void
{
    if (isset($update['message'])) {
        handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        handleCallback($update['callback_query']);
    }
}

function handleMessage(array $m): void
{
    if (($m['chat']['type'] ?? '') !== 'private' || !isset($m['from']['id'])) {
        return;
    }
    $uid = (int) $m['from']['id'];
    $firstName = h((string) ($m['from']['first_name'] ?? ''));
    $cmd = command((string) ($m['text'] ?? ''));

    $job = withState($uid, function (array &$s) use ($uid, $m, $cmd, $firstName) {
        if (isBuilding($s)) {
            send($uid, TXT_BUSY);
            return null;
        }

        switch ($cmd) {
            case '/start':
                $s = ['step' => 'title'];
                send($uid, sprintf(TXT_WELCOME, $firstName));
                return null;
            case '/help':
                send($uid, sprintf(TXT_HELP, MAX_EMOJIS));
                return null;
            case '/cancel':
                $s = [];
                send($uid, TXT_CANCELLED);
                return null;
            case '/done':
                $job = beginBuild($s, $error);
                if ($job === null) {
                    send($uid, $error);
                }
                return $job;
        }

        switch ($s['step'] ?? '') {
            case 'title':
                onTitle($uid, $s, $m);
                break;
            case 'emojis':
                onEmojis($uid, $s, $m);
                break;
            default:
                $s = ['step' => 'title'];
                send($uid, sprintf(TXT_WELCOME, $firstName));
        }
        return null;
    });

    if ($job !== null) {
        runBuild($uid, $job, null);
    }
}

function handleCallback(array $q): void
{
    $uid = (int) $q['from']['id'];
    $data = (string) ($q['data'] ?? '');
    $messageId = isset($q['message']['message_id']) ? (int) $q['message']['message_id'] : null;
    $alert = null;

    $job = withState($uid, function (array &$s) use ($uid, $data, $messageId, &$alert) {
        if (isBuilding($s)) {
            $alert = TXT_BUSY;
            return null;
        }
        switch ($data) {
            case 'build':
                $job = beginBuild($s, $error);
                $alert = $error;
                return $job;
            case 'cancel':
                $s = [];
                editOrSend($uid, $messageId, TXT_CANCELLED);
                return null;
            case 'new':
                $s = ['step' => 'title'];
                send($uid, TXT_ASK_TITLE);
                return null;
        }
        return null;
    });

    $answer = ['callback_query_id' => $q['id']];
    if ($alert !== null) {
        $answer += ['text' => $alert, 'show_alert' => true];
    }
    tg('answerCallbackQuery', $answer);

    if ($job !== null) {
        runBuild($uid, $job, $messageId);
    }
}

function onTitle(int $uid, array &$s, array $m): void
{
    $title = trim((string) preg_replace('/\s+/u', ' ', (string) ($m['text'] ?? '')));
    if ($title === '' || $title[0] === '/') {
        send($uid, TXT_ASK_TITLE_AGAIN);
        return;
    }
    $length = preg_match_all('/./su', $title);
    if ($length > 64) {
        send($uid, sprintf(TXT_TITLE_TOO_LONG, $length));
        return;
    }

    $s = ['step' => 'emojis', 'title' => $title, 'emojis' => []];
    send($uid, sprintf(TXT_ASK_EMOJIS, h($title), MAX_EMOJIS));
}

function onEmojis(int $uid, array &$s, array $m): void
{
    $found = extractCustomEmojis($m);
    $total = count($s['emojis']);
    if (!$found) {
        send($uid, TXT_NO_CUSTOM_EMOJI, $total > 0 ? collectKeyboard($total) : null);
        return;
    }

    $known = array_flip(array_column($s['emojis'], 'id'));
    $added = $duplicates = $overflow = 0;
    foreach ($found as $emoji) {
        if (isset($known[$emoji['id']])) {
            $duplicates++;
        } elseif (count($s['emojis']) >= MAX_EMOJIS) {
            $overflow++;
        } else {
            $s['emojis'][] = $emoji;
            $known[$emoji['id']] = true;
            $added++;
        }
    }
    $total = count($s['emojis']);

    $lines = [];
    if ($added > 0) {
        $lines[] = "➕ {$added} ایموجی اضافه شد.";
    }
    if ($duplicates > 0) {
        $lines[] = "♻️ {$duplicates} ایموجی تکراری بود.";
    }
    if ($overflow > 0) {
        $lines[] = "⚠️ هر پک حداکثر " . MAX_EMOJIS . " ایموجی می‌تونه داشته باشه؛ {$overflow} ایموجی اضافه نشد.";
    }
    $lines[] = "📦 مجموع: <b>{$total}</b>/" . MAX_EMOJIS;
    $lines[] = '';
    $lines[] = "باز هم بفرست یا دکمه «✅ ساخت پک» رو بزن.";
    send($uid, implode("\n", $lines), collectKeyboard($total));
}

/** Marks the state as building and returns the job, or null with $error set. */
function beginBuild(array &$s, ?string &$error): ?array
{
    $error = null;
    if (($s['step'] ?? '') !== 'emojis') {
        $error = TXT_START_FIRST;
        return null;
    }
    if (empty($s['emojis'])) {
        $error = TXT_NO_EMOJI_YET;
        return null;
    }
    $job = ['title' => $s['title'], 'emojis' => $s['emojis']];
    $s['step'] = 'building';
    $s['started'] = time();
    return $job;
}

function isBuilding(array $s): bool
{
    return ($s['step'] ?? '') === 'building' && time() - (int) ($s['started'] ?? 0) < BUILD_TIMEOUT;
}

function collectKeyboard(int $total): array
{
    return [
        [['text' => "✅ ساخت پک ({$total})", 'callback_data' => 'build']],
        [['text' => '❌ لغو', 'callback_data' => 'cancel']],
    ];
}

/** "/start@MyBot payload" → "/start" */
function command(string $text): string
{
    if ($text === '' || $text[0] !== '/') {
        return '';
    }
    return strtolower((string) strtok($text, " \n@"));
}

/**
 * Returns the custom emojis of a message in order: [['id' => ..., 'alt' => ...], ...].
 * Entity offsets are in UTF-16 code units, so the text is converted before slicing.
 */
function extractCustomEmojis(array $m): array
{
    $text = (string) ($m['text'] ?? $m['caption'] ?? '');
    $entities = $m['entities'] ?? $m['caption_entities'] ?? [];
    $utf16 = null;
    $emojis = [];
    foreach ($entities as $e) {
        if (($e['type'] ?? '') !== 'custom_emoji' || empty($e['custom_emoji_id'])) {
            continue;
        }
        $utf16 = $utf16 ?? convertEncoding($text, 'UTF-16LE', 'UTF-8');
        $alt = convertEncoding(substr($utf16, (int) $e['offset'] * 2, (int) $e['length'] * 2), 'UTF-8', 'UTF-16LE');
        $emojis[] = ['id' => (string) $e['custom_emoji_id'], 'alt' => $alt];
    }
    return $emojis;
}

function convertEncoding(string $text, string $to, string $from): string
{
    if (function_exists('mb_convert_encoding')) {
        return (string) mb_convert_encoding($text, $to, $from);
    }
    return (string) iconv($from, $to, $text);
}

// ---------------------------------------------------------------- building

/** Builds the pack after the update has been acknowledged, so Telegram does not wait for it. */
function runBuild(int $uid, array $job, ?int $messageId): void
{
    inBackground(function () use ($uid, $job, $messageId) {
        $ok = false;
        try {
            $ok = buildPack($uid, $job['title'], $job['emojis'], $messageId);
        } catch (Throwable $e) {
            logError('buildPack: ' . $e);
            send($uid, sprintf(TXT_FAILED, h($e->getMessage())), collectKeyboard(count($job['emojis'])));
        } finally {
            // On failure keep the collected emojis so the user can retry.
            withState($uid, function (array &$s) use ($ok, $job) {
                $s = $ok ? [] : ['step' => 'emojis', 'title' => $job['title'], 'emojis' => $job['emojis']];
            });
        }
    });
}

function inBackground(callable $job): void
{
    if (PHP_SAPI !== 'cli') {
        finishRequest();
        $job();
        return;
    }
    // Polling mode: fork so other users are not blocked while a pack is built.
    if (function_exists('pcntl_fork')) {
        $pid = pcntl_fork();
        if ($pid === 0) {
            $job();
            exit(0);
        }
        if ($pid > 0) {
            return;
        }
    }
    $job();
}

/** Sends the HTTP response to Telegram now and keeps the script running. */
function finishRequest(): void
{
    ignore_user_abort(true);
    set_time_limit(0);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    } elseif (!headers_sent()) {
        header('Connection: close');
        header('Content-Length: 0');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }
}

function buildPack(int $uid, string $title, array $emojis, ?int $messageId): bool
{
    $total = count($emojis);
    $progress = new Progress($uid, $messageId, $title, $total);

    $me = tg('getMe');
    if (!$me['ok']) {
        return $progress->fail($me['description'] ?? 'getMe failed', $total);
    }
    $botUsername = (string) $me['result']['username'];

    // 1. Resolve every custom emoji id to its sticker file.
    $stickers = [];
    foreach (array_chunk(array_column($emojis, 'id'), MAX_EMOJIS) as $ids) {
        $res = tg('getCustomEmojiStickers', ['custom_emoji_ids' => $ids]);
        foreach ($res['result'] ?? [] as $sticker) {
            $stickers[(string) ($sticker['custom_emoji_id'] ?? '')] = $sticker;
        }
    }
    $inputs = [];
    $failed = 0;
    $repaint = true;
    foreach ($emojis as $emoji) {
        $sticker = $stickers[$emoji['id']] ?? null;
        if ($sticker === null) {
            $failed++;
            continue;
        }
        $inputs[] = inputSticker($sticker, $emoji['alt']);
        $repaint = $repaint && !empty($sticker['needs_repainting']);
    }
    if (!$inputs) {
        return $progress->fail('این ایموجی‌ها از تلگرام دریافت نشدن.', $total);
    }

    // 2. Create the set: all of the first batch at once, or sticker by sticker
    //    (re-uploading files) if Telegram rejects the batch.
    $name = setName($uid, $botUsername);
    $create = function (array $stickers) use ($uid, &$name, $botUsername, $title, $repaint): array {
        for ($try = 0; ; $try++) {
            $params = [
                'user_id'      => $uid,
                'name'         => $name,
                'title'        => $title,
                'stickers'     => $stickers,
                'sticker_type' => 'custom_emoji',
            ];
            if ($repaint) {
                $params['needs_repainting'] = true;
            }
            $res = tg('createNewStickerSet', $params);
            if ($res['ok'] || $try >= 3 || stripos($res['description'] ?? '', 'occupied') === false) {
                return $res;
            }
            $name = setName($uid, $botUsername);
        }
    };

    $queue = $inputs;
    $added = 0;
    $batch = array_slice($queue, 0, CREATE_BATCH);
    $res = $create($batch);
    if ($res['ok']) {
        $added = count($batch);
        $queue = array_slice($queue, count($batch));
    } elseif (!isFatal($res)) {
        for ($tries = 0; $queue && $tries < 10; $tries++) {
            $res = withReupload($uid, array_shift($queue), function (array $input) use ($create) {
                return $create([$input]);
            });
            if ($res['ok']) {
                $added = 1;
                break;
            }
            $failed++;
            if (isFatal($res)) {
                break;
            }
        }
    }
    if ($added === 0) {
        return $progress->fail($res['description'] ?? 'createNewStickerSet failed', $total);
    }
    $progress->update($added + $failed);

    // 3. Add the remaining stickers one by one.
    foreach ($queue as $i => $input) {
        $res = withReupload($uid, $input, function (array $input) use ($uid, $name) {
            return tg('addStickerToSet', ['user_id' => $uid, 'name' => $name, 'sticker' => $input]);
        });
        if ($res['ok']) {
            $added++;
        } else {
            $failed++;
            if (isFatal($res)) {
                $failed += count($queue) - $i - 1;
                break;
            }
        }
        $progress->update($added + $failed);
    }

    $progress->done($name, $added, $failed);
    return true;
}

function inputSticker(array $sticker, string $alt): array
{
    if (!empty($sticker['is_animated'])) {
        $format = 'animated';
    } elseif (!empty($sticker['is_video'])) {
        $format = 'video';
    } else {
        $format = 'static';
    }
    $emoji = (string) ($sticker['emoji'] ?? '');
    if ($emoji === '') {
        $emoji = $alt !== '' ? $alt : '⭐';
    }
    return ['sticker' => $sticker['file_id'], 'format' => $format, 'emoji_list' => [$emoji]];
}

/**
 * Tries $call with the sticker's original file_id; if Telegram rejects it,
 * downloads the file, uploads it again for this user and retries once.
 */
function withReupload(int $uid, array $input, callable $call): array
{
    $res = $call($input);
    if ($res['ok'] || isFatal($res)) {
        return $res;
    }
    $fileId = reuploadSticker($uid, $input);
    if ($fileId === null) {
        return $res;
    }
    $input['sticker'] = $fileId;
    return $call($input);
}

function reuploadSticker(int $uid, array $input): ?string
{
    $file = tg('getFile', ['file_id' => $input['sticker']]);
    $path = (string) ($file['result']['file_path'] ?? '');
    if (!$file['ok'] || $path === '') {
        return null;
    }
    $bytes = downloadFile($path);
    if ($bytes === null || $bytes === '') {
        return null;
    }

    $ext = ['static' => 'webp', 'animated' => 'tgs', 'video' => 'webm'][$input['format']] ?? 'webp';
    $tmp = dataDir() . '/tmp_' . bin2hex(random_bytes(8)) . '.' . $ext;
    file_put_contents($tmp, $bytes);
    try {
        $res = tg('uploadStickerFile', ['user_id' => $uid, 'sticker_format' => $input['format']], 120, ['sticker' => $tmp]);
    } finally {
        @unlink($tmp);
    }
    return $res['ok'] ? (string) $res['result']['file_id'] : null;
}

/** Errors that no retry or re-upload can fix. */
function isFatal(array $res): bool
{
    return (bool) preg_match(
        '/STICKERS_TOO_MUCH|STICKERSET_INVALID|TITLE_INVALID|PEER_ID_INVALID|USER_IS_BOT|bot was blocked|user not found/i',
        (string) ($res['description'] ?? '')
    );
}

/** Set names must start with a letter and end with "_by_<bot username>". */
function setName(int $uid, string $botUsername): string
{
    $suffix = '';
    for ($i = 0; $i < 3; $i++) {
        $suffix .= chr(random_int(ord('a'), ord('z')));
    }
    return 'e' . base_convert((string) $uid, 10, 36) . base_convert((string) time(), 10, 36) . $suffix . '_by_' . $botUsername;
}

/** The status message shown while a pack is being built. */
final class Progress
{
    private $chatId;
    private $messageId;
    private $title;
    private $total;
    private $lastText = '';
    private $lastAt = 0.0;

    public function __construct(int $chatId, ?int $messageId, string $title, int $total)
    {
        $this->chatId = $chatId;
        $this->messageId = $messageId;
        $this->title = $title;
        $this->total = $total;
        $this->update(0, true);
    }

    public function update(int $done, bool $force = false): void
    {
        // Editing too often triggers flood limits; refresh every few seconds.
        if (!$force && $done < $this->total && microtime(true) - $this->lastAt < 3) {
            return;
        }
        $filled = $this->total > 0 ? (int) round(10 * $done / $this->total) : 0;
        $bar = str_repeat('▰', $filled) . str_repeat('▱', 10 - $filled);
        $text = sprintf(TXT_PROGRESS, h($this->title), $bar, $done, $this->total);
        if ($text === $this->lastText) {
            return;
        }
        $this->lastText = $text;
        $this->lastAt = microtime(true);
        $this->show($text, []);
    }

    public function done(string $name, int $added, int $failed): void
    {
        $link = 'https://t.me/addemoji/' . $name;
        $text = sprintf(TXT_DONE, h($this->title), $added, $link);
        if ($failed > 0) {
            $text .= sprintf(TXT_SKIPPED, $failed);
        }
        $this->show($text, [
            [['text' => '➕ افزودن پک ایموجی', 'url' => $link]],
            [['text' => '🆕 ساخت پک جدید', 'callback_data' => 'new']],
        ], true);
    }

    public function fail(string $reason, int $total): bool
    {
        $this->show(sprintf(TXT_FAILED, h($reason)), collectKeyboard($total));
        return false;
    }

    private function show(string $text, array $keyboard, bool $preview = false): void
    {
        $res = editOrSend($this->chatId, $this->messageId, $text, $keyboard, $preview);
        if (isset($res['result']['message_id'])) {
            $this->messageId = (int) $res['result']['message_id'];
        }
    }
}

// ---------------------------------------------------------------- entry points

function runPolling(): void
{
    $me = tg('getMe');
    if (!$me['ok']) {
        fwrite(STDERR, 'Cannot connect to Telegram: ' . ($me['description'] ?? '') . "\n");
        exit(1);
    }
    tg('deleteWebhook');
    echo 'Bot @' . $me['result']['username'] . " is running (polling). Press Ctrl+C to stop.\n";

    $offset = 0;
    while (true) {
        if (function_exists('pcntl_waitpid')) {
            while (pcntl_waitpid(-1, $status, WNOHANG) > 0) {
            }
        }
        $res = tg('getUpdates', [
            'offset'          => $offset,
            'timeout'         => 30,
            'allowed_updates' => ['message', 'callback_query'],
        ], 40);
        if (!$res['ok']) {
            sleep(3);
            continue;
        }
        foreach ($res['result'] as $update) {
            $offset = $update['update_id'] + 1;
            try {
                handleUpdate($update);
            } catch (Throwable $e) {
                logError('update ' . $update['update_id'] . ': ' . $e);
            }
        }
    }
}

function handleWebhook(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Bot is running. Use set_webhook.php to connect it to Telegram.\n";
        return;
    }
    $secret = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
    if (!hash_equals(webhookSecret(), $secret)) {
        logError('Rejected webhook request with a wrong secret token; run set_webhook.php again.');
        http_response_code(403);
        return;
    }
    $update = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($update)) {
        return;
    }
    try {
        handleUpdate($update);
    } catch (Throwable $e) {
        logError('update ' . ($update['update_id'] ?? '?') . ': ' . $e);
    }
}

if (PHP_SAPI === 'cli') {
    runPolling();
} else {
    handleWebhook();
}
