<?php
/**
 * ربات ساخت پک ایموجی پریمیوم (وب‌هوک، بدون دیتابیس)
 * ایموجی‌ها را بفرست ← ربات پک می‌سازد ← لینک پک را می‌دهد.
 */

// ===================== تنظیمات =====================
const BOT_TOKEN     = 'PUT_YOUR_BOT_TOKEN_HERE';
const ADMIN_ID      = 8213021584;
const PUBLIC_BOT    = true;   // false = فقط مدیر بتواند از ربات استفاده کند
const DEFAULT_EMOJI = '⭐';
const FFMPEG        = 'ffmpeg'; // برای گیف/ویدیو (اختیاری)
// ===================================================

const DATA_DIR = __DIR__ . '/emoji_data';
const MAX_EMOJI = 200;

error_reporting(E_ALL);
ini_set('display_errors', '0');
@mkdir(DATA_DIR, 0775, true);
@file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\nDeny from all\n");
ini_set('error_log', DATA_DIR . '/error.log');

function secret(): string
{
    return substr(hash('sha256', BOT_TOKEN), 0, 32);
}

// ---------- نصب وب‌هوک: bot.php?install=SECRET ----------
if (isset($_GET['install'])) {
    header('Content-Type: text/plain; charset=utf-8');
    if (!hash_equals(secret(), (string) $_GET['install'])) {
        exit('wrong key');
    }
    $url = 'https://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
    $r = tg('setWebhook', ['url' => $url, 'secret_token' => secret(), 'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => true]);
    exit($r === true ? "✅ Webhook set: $url" : '❌ ' . ($GLOBALS['tg_error'] ?? 'error'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('OK');
}
if (!hash_equals(secret(), $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '')) {
    http_response_code(403);
    exit;
}
$update = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($update)) {
    exit;
}

// جواب سریع به تلگرام و ادامه‌ی کار در پس‌زمینه
ignore_user_abort(true);
@set_time_limit(0);
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Length: 0');
header('Connection: close');
flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
}

try {
    handle($update);
} catch (Throwable $e) {
    error_log($e->getMessage() . ' @' . $e->getLine());
}

// ===================== منطق ربات =====================

function handle(array $u): void
{
    if (isset($u['callback_query'])) {
        $q = $u['callback_query'];
        $uid = (int) $q['from']['id'];
        tg('answerCallbackQuery', ['callback_query_id' => $q['id']]);
        if (!allowed($uid)) {
            return;
        }
        if ($q['data'] === 'new') {
            saveState($uid, []);
            send($uid, "🆕 پک جدید شروع شد.\nحالا ایموجی‌ها را بفرست.\n\n(برای اسم دلخواه: <code>/new اسم پک</code>)");
        }
        return;
    }

    $m = $u['message'] ?? null;
    if (!$m || ($m['chat']['type'] ?? '') !== 'private') {
        return;
    }
    $uid = (int) $m['from']['id'];
    if (!allowed($uid)) {
        send($uid, '🔒 این ربات خصوصی است.');
        return;
    }

    $lock = fopen(DATA_DIR . "/$uid.lock", 'c');
    flock($lock, LOCK_EX); // پیام‌های همزمان (آلبوم) یکی‌یکی پردازش شوند

    $text = trim((string) ($m['text'] ?? ''));
    if (preg_match('~^/start~', $text)) {
        send($uid, "سلام 👋\n\nایموجی‌های پریمیومت را بفرست تا برایت <b>پک ایموجی</b> بسازم و لینکش را بدهم ✨\n\n"
            . "• چند ایموجی را می‌توانی در یک پیام بفرستی\n"
            . "• استیکر، عکس و گیف هم قبول است\n\n"
            . "🆕 /new — شروع پک جدید (مثال: <code>/new پک من</code>)\n"
            . "🔗 /link — لینک پک فعلی");
        return;
    }
    if (preg_match('~^/new(?:@\w+)?\s*(.*)$~su', $text, $mm)) {
        $title = mb_substr(trim($mm[1]), 0, 64);
        saveState($uid, $title !== '' ? ['title' => $title] : []);
        send($uid, '🆕 پک جدید' . ($title !== '' ? ' «' . h($title) . '»' : '') . " شروع شد.\nحالا ایموجی‌ها را بفرست.");
        return;
    }
    if (preg_match('~^/link~', $text)) {
        $st = loadState($uid);
        send($uid, empty($st['pack']) ? 'هنوز پکی نساختی. ایموجی‌ها را بفرست.' : '🔗 https://t.me/addemoji/' . $st['pack']);
        return;
    }

    try {
        $items = extractItems($m);
    } catch (Throwable $e) {
        send($uid, '❗️ ' . h($e->getMessage()));
        return;
    }
    if (!$items) {
        send($uid, '❗️ ایموجی پریمیوم، استیکر، عکس یا گیف بفرست.');
        return;
    }

    $wait = send($uid, '⏳ در حال ساخت...');
    $st = loadState($uid);
    $added = 0;
    $errors = [];
    foreach ($items as $it) {
        try {
            $input = toInputSticker($it, $uid);
            if (empty($st['pack']) || ($st['count'] ?? 0) >= MAX_EMOJI) {
                $name = 'e' . base_convert((string) $uid, 10, 36) . '_' . base_convert((string) (int) (microtime(true) * 1000), 10, 36) . '_by_' . botUsername();
                $title = $st['title'] ?? ('پک ایموجی ' . mb_substr((string) ($m['from']['first_name'] ?? ''), 0, 40));
                tgOrFail('createNewStickerSet', [
                    'user_id' => $uid, 'name' => $name, 'title' => $title,
                    'stickers' => [$input], 'sticker_type' => 'custom_emoji',
                    'needs_repainting' => !empty($it['repaint']),
                ]);
                $st = ['pack' => $name, 'title' => $title, 'count' => 1];
            } else {
                tgOrFail('addStickerToSet', ['user_id' => $uid, 'name' => $st['pack'], 'sticker' => $input]);
                $st['count']++;
            }
            saveState($uid, $st);
            $added++;
        } catch (Throwable $e) {
            $errors[] = errorText($e->getMessage());
        }
    }

    $link = empty($st['pack']) ? null : 'https://t.me/addemoji/' . $st['pack'];
    $msg = $added ? "✅ <b>$added</b> ایموجی اضافه شد." : '❌ ایموجی‌ای اضافه نشد.';
    foreach (array_slice(array_unique($errors), 0, 3) as $er) {
        $msg .= "\n⚠️ " . h($er);
    }
    if ($link) {
        $msg .= "\n\n📦 تعداد ایموجی‌های پک: " . $st['count'] . "\n🔗 $link\n\nبرای افزودن، باز هم ایموجی بفرست.";
    }
    $kb = $link ? [[['text' => '📦 دیدن و افزودن پک', 'url' => $link]], [['text' => '🆕 پک جدید', 'callback_data' => 'new']]] : null;
    if ($wait) {
        tg('deleteMessage', ['chat_id' => $uid, 'message_id' => $wait['message_id']]);
    }
    send($uid, $msg, $kb);
}

function allowed(int $uid): bool
{
    return PUBLIC_BOT || $uid === ADMIN_ID;
}

/** پیدا کردن ایموجی‌ها/رسانه‌های داخل پیام */
function extractItems(array $m): array
{
    $em = captionEmoji((string) ($m['caption'] ?? ''));
    $file = fn(string $kind, array $f, ?string $emoji, ?string $thumb = null) => ['kind' => $kind, 'file_id' => $f['file_id'], 'emoji' => $emoji, 'thumb' => $thumb];

    if (isset($m['sticker'])) {
        $s = $m['sticker'];
        $e = $em ?? ($s['emoji'] ?? null);
        if (($s['type'] ?? '') === 'custom_emoji') {
            return [copyItem($s)];
        }
        if (!empty($s['is_animated'])) {
            return [$file('tgs', $s, $e)];
        }
        if (!empty($s['is_video'])) {
            return [$file('video', $s, $e, $s['thumbnail']['file_id'] ?? null)];
        }
        return [$file('image', $s, $e)];
    }
    if (!empty($m['photo'])) {
        return [$file('image', end($m['photo']), $em)];
    }
    foreach (['animation', 'video', 'video_note'] as $k) {
        if (isset($m[$k])) {
            return [$file('video', $m[$k], $em, $m[$k]['thumbnail']['file_id'] ?? null)];
        }
    }
    if (isset($m['document'])) {
        $d = $m['document'];
        $mime = (string) ($d['mime_type'] ?? '');
        $ext = strtolower(pathinfo((string) ($d['file_name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === 'tgs') {
            return [$file('tgs', $d, $em)];
        }
        if (str_starts_with($mime, 'video/') || $mime === 'image/gif') {
            return [$file('video', $d, $em, $d['thumbnail']['file_id'] ?? null)];
        }
        if (str_starts_with($mime, 'image/')) {
            return [$file('image', $d, $em)];
        }
        throw new Exception('این نوع فایل پشتیبانی نمی‌شود.');
    }

    // ایموجی‌های پریمیوم داخل متن
    $ids = [];
    foreach ($m['entities'] ?? [] as $e) {
        if ($e['type'] === 'custom_emoji') {
            $ids[] = $e['custom_emoji_id'];
        }
    }
    $ids = array_slice(array_values(array_unique($ids)), 0, MAX_EMOJI);
    if (!$ids) {
        return [];
    }
    $byId = [];
    foreach (tg('getCustomEmojiStickers', ['custom_emoji_ids' => $ids]) ?: [] as $s) {
        $byId[$s['custom_emoji_id']] = $s;
    }
    $items = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $items[] = copyItem($byId[$id]);
        }
    }
    return $items;
}

function copyItem(array $s): array
{
    return [
        'kind' => 'copy',
        'file_id' => $s['file_id'],
        'format' => !empty($s['is_animated']) ? 'animated' : (!empty($s['is_video']) ? 'video' : 'static'),
        'emoji' => $s['emoji'] ?? null,
        'repaint' => !empty($s['needs_repainting']),
    ];
}

/** تبدیل هر آیتم به InputSticker */
function toInputSticker(array $it, int $uid): array
{
    $emoji = [$it['emoji'] ?: DEFAULT_EMOJI];
    if ($it['kind'] === 'copy') {
        return ['sticker' => $it['file_id'], 'format' => $it['format'], 'emoji_list' => $emoji];
    }
    if ($it['kind'] === 'video') {
        if (hasFfmpeg()) {
            return upload($uid, videoToWebm(download($it['file_id'])), 'video', 'webm', $emoji);
        }
        if (empty($it['thumb'])) {
            throw new Exception('برای گیف/ویدیو، ffmpeg روی هاست لازم است.');
        }
        $it['file_id'] = $it['thumb']; // بدون ffmpeg: ایموجی ثابت از تصویر ویدیو
    }
    $data = download($it['file_id']);
    if ($it['kind'] === 'tgs') {
        return upload($uid, scaleTgs($data), 'animated', 'tgs', $emoji);
    }
    return upload($uid, imageTo100($data), 'static', 'png', $emoji);
}

function upload(int $uid, string $bytes, string $format, string $ext, array $emoji): array
{
    $tmp = DATA_DIR . '/up_' . bin2hex(random_bytes(6)) . '.' . $ext;
    file_put_contents($tmp, $bytes);
    try {
        $f = tgOrFail('uploadStickerFile', ['user_id' => $uid, 'sticker' => new CURLFile($tmp, '', 'emoji.' . $ext), 'sticker_format' => $format]);
    } finally {
        @unlink($tmp);
    }
    return ['sticker' => $f['file_id'], 'format' => $format, 'emoji_list' => $emoji];
}

// ===================== تبدیل فایل‌ها =====================

/** عکس ← PNG شفاف ۱۰۰×۱۰۰ */
function imageTo100(string $bytes): string
{
    $src = @imagecreatefromstring($bytes);
    if (!$src) {
        throw new Exception('تصویر قابل خواندن نیست.');
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $s = 100 / max($w, $h);
    $nw = max(1, (int) round($w * $s));
    $nh = max(1, (int) round($h * $s));
    $out = imagecreatetruecolor(100, 100);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $src, intdiv(100 - $nw, 2), intdiv(100 - $nh, 2), 0, 0, $nw, $nh, $w, $h);
    ob_start();
    imagepng($out, null, 9);
    return (string) ob_get_clean();
}

/** استیکر متحرک TGS (۵۱۲) ← بوم ۱۰۰×۱۰۰ */
function scaleTgs(string $bytes): string
{
    $d = json_decode((string) @gzdecode($bytes));
    if (!$d instanceof stdClass || !isset($d->layers)) {
        throw new Exception('فایل TGS معتبر نیست.');
    }
    $size = max((float) ($d->w ?? 512), (float) ($d->h ?? 512));
    if ($size != 100) {
        $s = 100 / $size * 100;
        $nid = 1;
        foreach ($d->layers as $l) {
            $nid = max($nid, (int) ($l->ind ?? 0) + 1);
        }
        foreach ($d->layers as $l) {
            if (!isset($l->parent)) {
                $l->parent = $nid;
            }
        }
        $p = fn($k) => (object) ['a' => 0, 'k' => $k];
        $d->layers[] = (object) ['ddd' => 0, 'ind' => $nid, 'ty' => 3, 'nm' => 'scale', 'sr' => 1,
            'ks' => (object) ['o' => $p(100), 'r' => $p(0), 'p' => $p([0, 0, 0]), 'a' => $p([0, 0, 0]), 's' => $p([$s, $s, 100])],
            'ao' => 0, 'ip' => $d->ip ?? 0, 'op' => $d->op ?? 180, 'st' => 0, 'bm' => 0];
        $d->w = 100;
        $d->h = 100;
    }
    return gzencode(json_encode($d, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION), 9);
}

function hasFfmpeg(): bool
{
    static $ok = null;
    if ($ok === null) {
        $ok = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
        if ($ok) {
            @exec(escapeshellarg(FFMPEG) . ' -version 2>&1', $o, $code);
            $ok = $code === 0;
        }
    }
    return $ok;
}

/** گیف/ویدیو/استیکر ویدیویی ← WEBM (VP9) ۱۰۰×۱۰۰ حداکثر ۳ ثانیه */
function videoToWebm(string $bytes): string
{
    $in = DATA_DIR . '/in_' . bin2hex(random_bytes(6));
    $out = $in . '.webm';
    file_put_contents($in, $bytes);
    $isWebm = substr($bytes, 0, 4) === "\x1A\x45\xDF\xA3";
    $vf = 'scale=100:100:force_original_aspect_ratio=decrease,format=rgba,pad=100:100:(ow-iw)/2:(oh-ih)/2:color=black@0,fps=30,format=yuva420p';
    try {
        foreach ([32, 42, 52, 63] as $crf) {
            $cmd = escapeshellarg(FFMPEG) . ' -y -loglevel error' . ($isWebm ? ' -c:v libvpx-vp9' : '')
                . ' -i ' . escapeshellarg($in) . ' -t 2.9 -an -vf ' . escapeshellarg($vf)
                . " -c:v libvpx-vp9 -pix_fmt yuva420p -b:v 0 -crf $crf -deadline good -cpu-used 4 " . escapeshellarg($out) . ' 2>&1';
            exec($cmd, $log, $code);
            clearstatcache();
            if ($code !== 0 || !is_file($out)) {
                throw new Exception('تبدیل ویدیو ناموفق بود.');
            }
            if (filesize($out) <= 64000) {
                return (string) file_get_contents($out);
            }
        }
        throw new Exception('ویدیو خیلی حجیم است، یک ویدیو کوتاه‌تر بفرست.');
    } finally {
        @unlink($in);
        @unlink($out);
    }
}

/** اولین ایموجی معمولی داخل کپشن */
function captionEmoji(string $text): ?string
{
    if (preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2B00}-\x{2BFF}][\x{FE0F}\x{1F3FB}-\x{1F3FF}]?/u', $text, $mm)) {
        return $mm[0];
    }
    return null;
}

// ===================== ابزارها =====================

function tg(string $method, array $params = []): mixed
{
    $multipart = false;
    foreach ($params as $k => $v) {
        if ($v instanceof CURLFile) {
            $multipart = true;
        } elseif (is_array($v)) {
            $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
        } elseif (is_bool($v)) {
            $params[$k] = $v ? 'true' : 'false';
        }
    }
    for ($try = 0; $try < 4; $try++) {
        $ch = curl_init('https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 120,
            CURLOPT_POSTFIELDS => $multipart ? $params : http_build_query($params)]);
        $res = json_decode((string) curl_exec($ch), true);
        curl_close($ch);
        if (!empty($res['ok'])) {
            return $res['result'];
        }
        $wait = (int) ($res['parameters']['retry_after'] ?? 0);
        if ($wait > 0 && $wait <= 30) {
            sleep($wait + 1);
            continue;
        }
        break;
    }
    $GLOBALS['tg_error'] = $res['description'] ?? 'network error';
    return null;
}

function tgOrFail(string $method, array $params): mixed
{
    $r = tg($method, $params);
    if ($r === null) {
        throw new Exception($GLOBALS['tg_error']);
    }
    return $r;
}

function send(int $chat, string $text, ?array $kb = null): ?array
{
    $p = ['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => true]];
    if ($kb) {
        $p['reply_markup'] = ['inline_keyboard' => $kb];
    }
    return tg('sendMessage', $p);
}

function download(string $fileId): string
{
    $f = tgOrFail('getFile', ['file_id' => $fileId]);
    $data = @file_get_contents('https://api.telegram.org/file/bot' . BOT_TOKEN . '/' . $f['file_path']);
    if ($data === false) {
        throw new Exception('دانلود فایل ناموفق بود.');
    }
    return $data;
}

function botUsername(): string
{
    $file = DATA_DIR . '/bot_username.txt';
    $u = @file_get_contents($file);
    if (!$u) {
        $u = tgOrFail('getMe', [])['username'];
        file_put_contents($file, $u);
    }
    return $u;
}

function loadState(int $uid): array
{
    return json_decode((string) @file_get_contents(DATA_DIR . "/$uid.json"), true) ?: [];
}

function saveState(int $uid, array $st): void
{
    file_put_contents(DATA_DIR . "/$uid.json", json_encode($st, JSON_UNESCAPED_UNICODE));
}

function errorText(string $e): string
{
    $map = [
        'STICKERS_TOO_MUCH' => 'پک پر است (حداکثر ۲۰۰).',
        'emoji' => 'ایموجی نامعتبر است.',
        'PEER_ID_INVALID' => 'اول ربات را استارت کن.',
        'file is too big' => 'فایل بیشتر از ۲۰ مگابایت است.',
        'DIMENSIONS' => 'ابعاد فایل نامعتبر است.',
    ];
    foreach ($map as $k => $v) {
        if (stripos($e, $k) !== false) {
            return $v;
        }
    }
    return $e;
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
