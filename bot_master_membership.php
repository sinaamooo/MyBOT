<?php
/**
 * 🛍️💎 سیستم فروش ممبرشیپ — ربات مادر + ربات‌های فرعی اپلودری
 *
 * یک فایل، همه قابلیت‌ها:
 *  - فروش ممبرشیپ با USDT / TRX / تومان
 *  - تایید پرداخت فقط توسط ادمین (با رسید)
 *  - اتصال خودکار به ربات اپلودری بعد از تایید
 *  - کد دسترسی یکتا و قابل اعتبارسنجی
 *  - محدودیت تعداد اعضا
 *  - ربات‌های فرعی: آپلود تکی/گروهی، آمار، همگانی، تنظیمات
 *  - دکمه‌های رنگی 🟢🔴🔵 + ایموجی پریمیوم
 *
 * Webhook ربات مادر : https://DOMAIN/bot_master_membership.php
 * Webhook ربات فرعی : https://DOMAIN/bot_master_membership.php?bot=<BOT_ID>
 */

// ============================================================
// ⚙️ تنظیمات
// ============================================================

if (!defined('BOT_TOKEN')) define('BOT_TOKEN', '8580931982:AAHQb5vGDnG6n9vFWqBMpPWksRiuyhsWv_g');
if (!defined('ADMIN_ID'))  define('ADMIN_ID',  8213021584);
if (!defined('DATA_DIR'))  define('DATA_DIR',  __DIR__ . '/data_master');

if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0755, true);
if (!is_dir(DATA_DIR . '/bots')) @mkdir(DATA_DIR . '/bots', 0755, true);

$EMOJI = [
    'shop' => '🛍️', 'member' => '👥', 'money' => '💰', 'lock' => '🔒',
    'check' => '✅', 'error' => '❌', 'warning' => '⚠️', 'star' => '⭐',
    'rocket' => '🚀', 'wallet' => '💳', 'chart' => '📊', 'bot' => '🤖',
    'upload' => '📤', 'file' => '📁', 'settings' => '⚙️', 'delete' => '🗑️',
    'premium' => '✨', 'crown' => '👑', 'fire' => '🔥', 'link' => '🔗',
    'clock' => '⏳', 'receipt' => '🧾', 'back' => '◀️',
];

// ============================================================
// 📚 لایه ذخیره‌سازی (نوشتن اتمیک)
// ============================================================

function dataPath($file) {
    return DATA_DIR . '/' . $file . '.json';
}

function load($file) {
    $path = dataPath($file);
    if (!file_exists($path)) return [];
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') return [];
    $out = json_decode($raw, true);
    return is_array($out) ? $out : [];
}

function save($file, $data) {
    $path = dataPath($file);
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $tmp = $path . '.' . getmypid() . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, $path); // اتمیک: نوشتن ناقص هرگز دیده نمی‌شود
}

/** اجرای یک تغییر روی فایل با قفل انحصاری تا دو درخواست همزمان همدیگر را پاک نکنند */
function mutate($file, callable $fn) {
    $lockPath = dataPath($file) . '.lock';
    $dir = dirname($lockPath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fp = fopen($lockPath, 'c');
    if ($fp) { flock($fp, LOCK_EX); }
    $data = load($file);
    $result = $fn($data);   // $data با ارجاع تغییر می‌کند
    save($file, $data);
    if ($fp) { flock($fp, LOCK_UN); fclose($fp); }
    return $result;
}

function uid($prefix) {
    return $prefix . '_' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(3));
}

function genCode($len = 10) {
    return substr(rtrim(strtr(base64_encode(random_bytes(16)), '+/', 'ab'), '='), 0, $len);
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function nowStr() { return date('Y-m-d H:i:s'); }

// ============================================================
// 🔌 تلگرام API
// ============================================================

function tg($token, $method, $data = []) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'description' => 'curl error'];
    $out = json_decode($res, true);
    return is_array($out) ? $out : ['ok' => false, 'description' => 'bad response'];
}

function kb($rows) {
    $rows = array_values(array_filter($rows, fn($r) => !empty($r)));
    return $rows ? json_encode(['inline_keyboard' => $rows]) : null;
}

function sendMsg($token, $chatId, $text, $rows = null, $extra = []) {
    $data = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => 'true',
    ], $extra);
    $markup = $rows ? kb($rows) : null;
    if ($markup) $data['reply_markup'] = $markup;
    return tg($token, 'sendMessage', $data);
}

function editMsg($token, $chatId, $msgId, $text, $rows = null) {
    $data = [
        'chat_id' => $chatId,
        'message_id' => $msgId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => 'true',
    ];
    $markup = $rows ? kb($rows) : null;
    if ($markup) $data['reply_markup'] = $markup;
    $res = tg($token, 'editMessageText', $data);
    // اگر پیام قابل ویرایش نبود (مثلا پیام عکس) پیام تازه بفرست
    if (empty($res['ok'])) sendMsg($token, $chatId, $text, $rows);
    return $res;
}

function answerCb($token, $cbId, $text = '', $alert = false) {
    return tg($token, 'answerCallbackQuery', [
        'callback_query_id' => $cbId,
        'text' => $text,
        'show_alert' => $alert ? 'true' : 'false',
    ]);
}

function sendFile($token, $chatId, $type, $fileId, $caption = '', $protect = false) {
    $map = [
        'document' => ['sendDocument', 'document'],
        'photo'    => ['sendPhoto', 'photo'],
        'video'    => ['sendVideo', 'video'],
        'audio'    => ['sendAudio', 'audio'],
        'voice'    => ['sendVoice', 'voice'],
        'animation'=> ['sendAnimation', 'animation'],
        'sticker'  => ['sendSticker', 'sticker'],
    ];
    [$method, $field] = $map[$type] ?? $map['document'];
    $data = ['chat_id' => $chatId, $field => $fileId];
    if ($caption !== '' && $type !== 'sticker') {
        $data['caption'] = $caption;
        $data['parse_mode'] = 'HTML';
    }
    if ($protect) $data['protect_content'] = 'true';
    return tg($token, $method, $data);
}

// ============================================================
// 🎨 دکمه‌های رنگی
// ============================================================

function bGreen($label, $data) { return ['text' => "🟢 $label", 'callback_data' => $data]; }
function bRed($label, $data)   { return ['text' => "🔴 $label", 'callback_data' => $data]; }
function bBlue($label, $data)  { return ['text' => "🔵 $label", 'callback_data' => $data]; }
function bUrl($label, $url)    { return ['text' => $label, 'url' => $url]; }

// ============================================================
// 🗂️ تنظیمات فروشگاه
// ============================================================

function shopSettings() {
    $s = load('settings');
    return array_merge([
        'wallet_usdt'  => 'آدرس کیف پول USDT را در پنل وارد کنید',
        'wallet_trx'   => 'آدرس کیف پول TRX را در پنل وارد کنید',
        'card_number'  => 'شماره کارت را در پنل وارد کنید',
        'welcome_text' => '',
        'support'      => '',
        'domain'       => '',
    ], $s);
}

function walletFor($currency) {
    $s = shopSettings();
    $c = strtoupper(trim($currency));
    if ($c === 'USDT') return ['USDT (TRC20)', $s['wallet_usdt']];
    if ($c === 'TRX')  return ['TRX', $s['wallet_trx']];
    return ['کارت به کارت', $s['card_number']];
}

// ============================================================
// 👤 کاربران
// ============================================================

function getUser($id) {
    $users = load('users');
    return $users[(string)$id] ?? null;
}

function touchUser($id, $username, $firstName = '') {
    return mutate('users', function (&$users) use ($id, $username, $firstName) {
        $k = (string)$id;
        $users[$k] = array_merge([
            'telegram_id' => (int)$id,
            'joined_at'   => nowStr(),
            'banned'      => false,
        ], $users[$k] ?? [], [
            'username'   => $username,
            'first_name' => $firstName,
            'seen_at'    => nowStr(),
        ]);
        return $users[$k];
    });
}

function userIsBanned($id) {
    $u = getUser($id);
    return $u && !empty($u['banned']);
}

// ============================================================
// 🧠 وضعیت گفتگو (State)
// ============================================================

function getState($userId) {
    $s = load('states');
    return $s[(string)$userId] ?? null;
}

function setState($userId, $action, $payload = []) {
    mutate('states', function (&$s) use ($userId, $action, $payload) {
        $s[(string)$userId] = ['action' => $action, 'data' => $payload, 'at' => nowStr()];
    });
}

function clearState($userId) {
    mutate('states', function (&$s) use ($userId) {
        unset($s[(string)$userId]);
    });
}

// ============================================================
// 🛍️ ممبرشیپ
// ============================================================

class MembershipManager
{
    public static function all() {
        return load('memberships');
    }

    public static function get($id) {
        $all = load('memberships');
        return $all[$id] ?? null;
    }

    public static function create($name, $price, $currency, $limit = 0, $desc = '') {
        $id = uid('m');
        mutate('memberships', function (&$all) use ($id, $name, $price, $currency, $limit, $desc) {
            $all[$id] = [
                'id'         => $id,
                'name'       => $name,
                'desc'       => $desc,
                'price'      => (float)$price,
                'currency'   => $currency,
                'limit'      => (int)$limit,
                'members'    => [],
                'bot_id'     => null,
                'active'     => true,
                'created_at' => nowStr(),
            ];
        });
        return self::get($id);
    }

    public static function isFull($m) {
        return ((int)$m['limit']) > 0 && count($m['members']) >= (int)$m['limit'];
    }

    public static function isMember($membershipId, $userId) {
        $m = self::get($membershipId);
        if (!$m) return false;
        return in_array((int)$userId, array_map('intval', $m['members']), true);
    }

    public static function addMember($membershipId, $userId) {
        return mutate('memberships', function (&$all) use ($membershipId, $userId) {
            if (!isset($all[$membershipId])) return false;
            $members = array_map('intval', $all[$membershipId]['members']);
            if (!in_array((int)$userId, $members, true)) {
                $members[] = (int)$userId;
                $all[$membershipId]['members'] = array_values($members);
            }
            return true;
        });
    }

    public static function removeMember($membershipId, $userId) {
        mutate('memberships', function (&$all) use ($membershipId, $userId) {
            if (!isset($all[$membershipId])) return;
            $all[$membershipId]['members'] = array_values(array_filter(
                array_map('intval', $all[$membershipId]['members']),
                fn($u) => $u !== (int)$userId
            ));
        });
    }

    public static function stats($id) {
        $m = self::get($id);
        if (!$m) return null;
        $count = count($m['members']);
        return [
            'name'      => $m['name'],
            'members'   => $count,
            'limit'     => (int)$m['limit'],
            'available' => ((int)$m['limit']) > 0 ? max(0, (int)$m['limit'] - $count) : '∞',
            'price'     => $m['price'],
            'currency'  => $m['currency'],
        ];
    }
}

// ============================================================
// 🤖 ربات‌های فرعی
// ============================================================

class BotManager
{
    public static function all() { return load('bots'); }

    public static function get($id) {
        $all = load('bots');
        return $all[$id] ?? null;
    }

    public static function create($token, $username, $membershipId = null) {
        $id = uid('b');
        mutate('bots', function (&$all) use ($id, $token, $username, $membershipId) {
            $all[$id] = [
                'id'            => $id,
                'token'         => $token,
                'username'      => ltrim($username, '@'),
                'membership_id' => $membershipId,
                'active'        => true,
                'created_at'    => nowStr(),
            ];
        });
        if ($membershipId) {
            mutate('memberships', function (&$all) use ($membershipId, $id) {
                if (isset($all[$membershipId])) $all[$membershipId]['bot_id'] = $id;
            });
        }
        return self::get($id);
    }

    public static function botFor($membershipId) {
        $m = MembershipManager::get($membershipId);
        if (!$m || empty($m['bot_id'])) return null;
        return self::get($m['bot_id']);
    }
}

// ============================================================
// 🔗 کدهای دسترسی — پل بین ممبرشیپ و ربات اپلودری
// ============================================================

class AccessManager
{
    /** ساخت کد دسترسی یکتا و ذخیره آن (باگ قبلی: کد ساخته می‌شد ولی ذخیره نمی‌شد) */
    public static function issue($membershipId, $userId, $botId) {
        $code = genCode(12);
        mutate('access', function (&$all) use ($code, $membershipId, $userId, $botId) {
            $all[$code] = [
                'code'          => $code,
                'membership_id' => $membershipId,
                'user_id'       => (int)$userId,
                'bot_id'        => $botId,
                'created_at'    => nowStr(),
                'used_at'       => null,
                'revoked'       => false,
            ];
        });
        return $code;
    }

    public static function get($code) {
        $all = load('access');
        return $all[$code] ?? null;
    }

    /** کد فعال کاربر برای این ممبرشیپ، اگر نبود بساز */
    public static function ensure($membershipId, $userId, $botId) {
        $all = load('access');
        foreach ($all as $code => $a) {
            if ($a['membership_id'] === $membershipId
                && (int)$a['user_id'] === (int)$userId
                && empty($a['revoked'])) {
                return $code;
            }
        }
        return self::issue($membershipId, $userId, $botId);
    }

    public static function markUsed($code) {
        mutate('access', function (&$all) use ($code) {
            if (isset($all[$code]) && empty($all[$code]['used_at'])) {
                $all[$code]['used_at'] = nowStr();
            }
        });
    }

    /**
     * اعتبارسنجی کامل: کد وجود دارد، باطل نشده، متعلق به همین کاربر است،
     * و کاربر واقعا عضو تایید‌شده ممبرشیپ است.
     */
    public static function validate($code, $userId) {
        $a = self::get($code);
        if (!$a) return [false, 'کد دسترسی معتبر نیست.'];
        if (!empty($a['revoked'])) return [false, 'این کد دسترسی باطل شده است.'];
        if ((int)$a['user_id'] !== (int)$userId) return [false, 'این لینک برای حساب دیگری صادر شده است.'];
        if (!MembershipManager::isMember($a['membership_id'], $userId)) {
            return [false, 'عضویت شما فعال نیست.'];
        }
        return [true, $a];
    }

    public static function linkFor($membershipId, $userId) {
        $bot = BotManager::botFor($membershipId);
        if (!$bot) return null;
        $code = self::ensure($membershipId, $userId, $bot['id']);
        return "https://t.me/{$bot['username']}?start={$code}";
    }
}

// ============================================================
// 💳 پرداخت‌ها
// ============================================================

class PaymentManager
{
    const PENDING  = 'pending';   // ساخته شده، منتظر رسید کاربر
    const REVIEW   = 'review';    // رسید آمده، منتظر تایید ادمین
    const APPROVED = 'approved';  // ادمین تایید کرد
    const REJECTED = 'rejected';  // ادمین رد کرد

    public static function all() { return load('payments'); }

    public static function get($id) {
        $all = load('payments');
        return $all[$id] ?? null;
    }

    public static function create($userId, $username, $membershipId, $amount, $currency) {
        $id = uid('p'); // یکتا — باگ قبلی: time() برای دو خرید همزمان یکسان می‌شد
        mutate('payments', function (&$all) use ($id, $userId, $username, $membershipId, $amount, $currency) {
            $all[$id] = [
                'id'            => $id,
                'user_id'       => (int)$userId,
                'username'      => $username,
                'membership_id' => $membershipId,
                'amount'        => (float)$amount,
                'currency'      => $currency,
                'status'        => self::PENDING,
                'receipt_type'  => null,
                'receipt'       => null,
                'created_at'    => nowStr(),
                'decided_at'    => null,
                'decided_by'    => null,
            ];
        });
        return $id;
    }

    public static function attachReceipt($id, $type, $value) {
        return mutate('payments', function (&$all) use ($id, $type, $value) {
            if (!isset($all[$id])) return false;
            if ($all[$id]['status'] !== self::PENDING) return false;
            $all[$id]['receipt_type'] = $type;
            $all[$id]['receipt']      = $value;
            $all[$id]['status']       = self::REVIEW;
            $all[$id]['receipt_at']   = nowStr();
            return true;
        });
    }

    /**
     * تایید پرداخت — فقط از مسیرهایی صدا زده می‌شود که هویت ادمین چک شده.
     * برگشت: [موفق؟, پیام]
     */
    public static function approve($id, $adminId) {
        $ok = mutate('payments', function (&$all) use ($id, $adminId) {
            if (!isset($all[$id])) return 'notfound';
            if (in_array($all[$id]['status'], [self::APPROVED, self::REJECTED], true)) return 'done';
            $all[$id]['status']     = self::APPROVED;
            $all[$id]['decided_at'] = nowStr();
            $all[$id]['decided_by'] = (int)$adminId;
            return 'ok';
        });

        if ($ok === 'notfound') return [false, 'پرداخت پیدا نشد.'];
        if ($ok === 'done')     return [false, 'این پرداخت قبلا بررسی شده است.'];

        $p = self::get($id);
        $m = MembershipManager::get($p['membership_id']);
        if (!$m) return [false, 'ممبرشیپ پیدا نشد.'];

        if (MembershipManager::isFull($m) && !MembershipManager::isMember($m['id'], $p['user_id'])) {
            return [false, 'ظرفیت این ممبرشیپ تکمیل است.'];
        }

        MembershipManager::addMember($p['membership_id'], $p['user_id']);
        return [true, $p];
    }

    public static function reject($id, $adminId) {
        $ok = mutate('payments', function (&$all) use ($id, $adminId) {
            if (!isset($all[$id])) return 'notfound';
            if (in_array($all[$id]['status'], [self::APPROVED, self::REJECTED], true)) return 'done';
            $all[$id]['status']     = self::REJECTED;
            $all[$id]['decided_at'] = nowStr();
            $all[$id]['decided_by'] = (int)$adminId;
            return 'ok';
        });
        if ($ok === 'notfound') return [false, 'پرداخت پیدا نشد.'];
        if ($ok === 'done')     return [false, 'این پرداخت قبلا بررسی شده است.'];
        return [true, self::get($id)];
    }

    public static function countBy($status) {
        $n = 0;
        foreach (self::all() as $p) if ($p['status'] === $status) $n++;
        return $n;
    }
}

// ============================================================
// 📤 داده ربات فرعی
// ============================================================

function botFile($botId, $name) { return 'bots/' . $botId . '/' . $name; }

function botSettings($botId) {
    return array_merge([
        'welcome'         => "سلام {name} 👋\nبه ربات اختصاصی اعضا خوش آمدید.",
        'protect_content' => false,
        'caption'         => '',
    ], load(botFile($botId, 'settings')));
}

class FileManager
{
    public static function addFile($botId, $fileId, $name, $type, $batchCode = null) {
        $code = genCode(10);
        mutate(botFile($botId, 'files'), function (&$all) use ($code, $fileId, $name, $type, $batchCode) {
            $all[$code] = [
                'code'       => $code,
                'file_id'    => $fileId,
                'file_name'  => $name,
                'file_type'  => $type,
                'batch_code' => $batchCode,
                'downloads'  => 0,
                'created_at' => nowStr(),
            ];
        });
        return $code;
    }

    public static function getFile($botId, $code) {
        $all = load(botFile($botId, 'files'));
        return $all[$code] ?? null;
    }

    public static function listFiles($botId) {
        return load(botFile($botId, 'files'));
    }

    public static function countDownload($botId, $code) {
        mutate(botFile($botId, 'files'), function (&$all) use ($code) {
            if (isset($all[$code])) $all[$code]['downloads'] = (int)$all[$code]['downloads'] + 1;
        });
    }

    public static function startBatch($botId, $title = '') {
        $code = genCode(10);
        mutate(botFile($botId, 'batches'), function (&$all) use ($code, $title) {
            $all[$code] = [
                'code' => $code, 'title' => $title ?: 'مجموعه فایل',
                'files' => [], 'downloads' => 0, 'created_at' => nowStr(),
            ];
        });
        return $code;
    }

    public static function addToBatch($botId, $batchCode, $fileCode) {
        mutate(botFile($botId, 'batches'), function (&$all) use ($batchCode, $fileCode) {
            if (isset($all[$batchCode])) $all[$batchCode]['files'][] = $fileCode;
        });
    }

    public static function getBatch($botId, $code) {
        $all = load(botFile($botId, 'batches'));
        return $all[$code] ?? null;
    }

    public static function listBatches($botId) {
        return load(botFile($botId, 'batches'));
    }
}

function botUserTouch($botId, $userId, $username) {
    mutate(botFile($botId, 'users'), function (&$all) use ($userId, $username) {
        $k = (string)$userId;
        $all[$k] = array_merge(['joined_at' => nowStr()], $all[$k] ?? [], [
            'id' => (int)$userId, 'username' => $username, 'seen_at' => nowStr(),
        ]);
    });
}

// ============================================================
// 👑 اعلان به ادمین
// ============================================================

function notifyAdminPayment($paymentId) {
    global $EMOJI;
    $p = PaymentManager::get($paymentId);
    if (!$p) return;
    $m = MembershipManager::get($p['membership_id']);
    $memName = $m ? $m['name'] : '—';
    $uname = $p['username'] ? '@' . $p['username'] : '—';

    $text  = "{$EMOJI['receipt']} <b>درخواست تایید پرداخت</b>\n\n";
    $text .= "کاربر: " . h($uname) . " (<code>{$p['user_id']}</code>)\n";
    $text .= "ممبرشیپ: " . h($memName) . "\n";
    $text .= "مبلغ: " . h($p['amount']) . ' ' . h($p['currency']) . "\n";
    $text .= "شناسه: <code>" . h($p['id']) . "</code>\n";
    $text .= "زمان: " . h($p['created_at']) . "\n\n";
    if ($p['receipt_type'] === 'text') {
        $text .= "{$EMOJI['premium']} رسید:\n<code>" . h($p['receipt']) . "</code>";
    } else {
        $text .= "{$EMOJI['premium']} رسید: تصویر (پایین ارسال شد)";
    }

    $rows = [[
        bGreen('تایید', 'adm_ok_' . $p['id']),
        bRed('رد', 'adm_no_' . $p['id']),
    ]];

    if ($p['receipt_type'] === 'photo') {
        tg(BOT_TOKEN, 'sendPhoto', [
            'chat_id' => ADMIN_ID, 'photo' => $p['receipt'],
            'caption' => $text, 'parse_mode' => 'HTML',
            'reply_markup' => kb($rows),
        ]);
    } else {
        sendMsg(BOT_TOKEN, ADMIN_ID, $text, $rows);
    }
}

// ============================================================
// 🏠 ربات مادر — نمایش‌ها
// ============================================================

function masterStart($userId, $chatId, $username, $firstName) {
    global $EMOJI;
    touchUser($userId, $username, $firstName);
    clearState($userId);

    $s = shopSettings();
    $text = "{$EMOJI['crown']} <b>خوش آمدید " . h($firstName) . "</b>\n\n";
    if (!empty($s['welcome_text'])) {
        $text .= h($s['welcome_text']) . "\n\n";
    } else {
        $text .= "{$EMOJI['shop']} <b>فروشگاه ممبرشیپ</b>\n\n";
    }

    $memberships = MembershipManager::all();
    $active = array_filter($memberships, fn($m) => !empty($m['active']));

    if (!$active) {
        $rows = ($userId === ADMIN_ID) ? [[bBlue('پنل مدیریت', 'adm_home')]] : null;
        sendMsg(BOT_TOKEN, $chatId, $text . "{$EMOJI['clock']} هنوز ممبرشیپی ثبت نشده است.", $rows);
        return;
    }

    $rows = [];
    foreach ($active as $m) {
        $count = count($m['members']);
        $cap   = ((int)$m['limit']) > 0 ? "{$count}/{$m['limit']}" : "{$count}/∞";
        $mine  = MembershipManager::isMember($m['id'], $userId);
        $full  = MembershipManager::isFull($m);

        $text .= "{$EMOJI['money']} <b>" . h($m['name']) . "</b>\n";
        if (!empty($m['desc'])) $text .= "   " . h($m['desc']) . "\n";
        $text .= "   قیمت: " . h($m['price']) . ' ' . h($m['currency']) . "\n";
        $text .= "   اعضا: {$cap}" . ($full ? " {$EMOJI['lock']} تکمیل" : "") . "\n\n";

        // باگ قبلی: همه دکمه‌ها فقط «خرید» بودند و اسم ممبرشیپ روی دکمه نمی‌آمد
        if ($mine) {
            $rows[] = [bBlue('دریافت لینک — ' . $m['name'], 'mylink_' . $m['id'])];
        } elseif ($full) {
            $rows[] = [bRed('تکمیل ظرفیت — ' . $m['name'], 'full_' . $m['id'])];
        } else {
            $rows[] = [bGreen('خرید ' . $m['name'] . ' — ' . $m['price'] . ' ' . $m['currency'], 'buy_' . $m['id'])];
        }
    }

    $rows[] = [bBlue('عضویت‌های من', 'my_subs')];
    if (!empty($s['support'])) $rows[] = [bUrl('💬 پشتیبانی', 't.me/' . ltrim($s['support'], '@'))];
    if ($userId === ADMIN_ID)  $rows[] = [bBlue('پنل مدیریت', 'adm_home')];

    sendMsg(BOT_TOKEN, $chatId, $text, $rows);
}

function showMySubs($userId, $chatId, $msgId = null) {
    global $EMOJI;
    $mine = [];
    foreach (MembershipManager::all() as $m) {
        if (MembershipManager::isMember($m['id'], $userId)) $mine[] = $m;
    }

    if (!$mine) {
        $text = "{$EMOJI['member']} <b>عضویت‌های من</b>\n\nهنوز عضویتی ندارید.";
        $rows = [[bBlue('بازگشت', 'home')]];
    } else {
        $text = "{$EMOJI['member']} <b>عضویت‌های من</b>\n\n";
        $rows = [];
        foreach ($mine as $m) {
            $text .= "{$EMOJI['check']} " . h($m['name']) . "\n";
            $rows[] = [bBlue('لینک ' . $m['name'], 'mylink_' . $m['id'])];
        }
        $rows[] = [bBlue('بازگشت', 'home')];
    }

    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, $rows);
    else sendMsg(BOT_TOKEN, $chatId, $text, $rows);
}

function sendAccessLink($userId, $chatId, $membershipId) {
    global $EMOJI;
    $m = MembershipManager::get($membershipId);
    if (!$m) { sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['error']} ممبرشیپ پیدا نشد."); return; }

    if (!MembershipManager::isMember($membershipId, $userId)) {
        sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['error']} شما عضو این ممبرشیپ نیستید.");
        return;
    }

    $link = AccessManager::linkFor($membershipId, $userId);
    if (!$link) {
        sendMsg(BOT_TOKEN, $chatId,
            "{$EMOJI['warning']} هنوز ربات اپلودری به این ممبرشیپ وصل نشده است.\nلطفا با پشتیبانی تماس بگیرید.");
        return;
    }

    $text  = "{$EMOJI['check']} <b>" . h($m['name']) . "</b>\n\n";
    $text .= "{$EMOJI['link']} لینک دسترسی اختصاصی شما:\n{$link}\n\n";
    $text .= "{$EMOJI['warning']} این لینک فقط با همین حساب کار می‌کند.";

    sendMsg(BOT_TOKEN, $chatId, $text, [[bUrl('🚀 ورود به ربات', $link)]]);
}

function startPurchase($userId, $chatId, $username, $membershipId) {
    global $EMOJI;
    $m = MembershipManager::get($membershipId);
    if (!$m || empty($m['active'])) {
        sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['error']} این ممبرشیپ در دسترس نیست.");
        return;
    }
    if (MembershipManager::isMember($membershipId, $userId)) {
        sendAccessLink($userId, $chatId, $membershipId);
        return;
    }
    if (MembershipManager::isFull($m)) {
        sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['lock']} ظرفیت این ممبرشیپ تکمیل شده است.");
        return;
    }

    $paymentId = PaymentManager::create($userId, $username, $membershipId, $m['price'], $m['currency']);
    [$label, $wallet] = walletFor($m['currency']);

    $text  = "{$EMOJI['wallet']} <b>اطلاعات پرداخت</b>\n\n";
    $text .= "ممبرشیپ: " . h($m['name']) . "\n";
    $text .= "مبلغ: <b>" . h($m['price']) . ' ' . h($m['currency']) . "</b>\n";
    $text .= "روش: " . h($label) . "\n\n";
    $text .= "{$EMOJI['premium']} مقصد پرداخت:\n<code>" . h($wallet) . "</code>\n\n";
    $text .= "شناسه سفارش: <code>" . h($paymentId) . "</code>\n\n";
    $text .= "{$EMOJI['warning']} بعد از واریز، دکمه زیر را بزنید و رسید (عکس یا کد تراکنش) را بفرستید.\n";
    $text .= "عضویت شما پس از <b>تایید ادمین</b> فعال می‌شود.";

    $rows = [
        [bGreen('پرداخت کردم، ارسال رسید', 'pay_rcpt_' . $paymentId)],
        [bRed('انصراف', 'pay_cancel_' . $paymentId)],
    ];
    sendMsg(BOT_TOKEN, $chatId, $text, $rows);
}

// ============================================================
// 👑 پنل ادمین داخل تلگرام
// ============================================================

function admHome($chatId, $msgId = null) {
    global $EMOJI;
    $mems = MembershipManager::all();
    $bots = BotManager::all();
    $users = load('users');

    $text  = "{$EMOJI['crown']} <b>پنل مدیریت</b>\n\n";
    $text .= "{$EMOJI['member']} کاربران: " . count($users) . "\n";
    $text .= "{$EMOJI['shop']} ممبرشیپ‌ها: " . count($mems) . "\n";
    $text .= "{$EMOJI['bot']} ربات‌ها: " . count($bots) . "\n";
    $text .= "{$EMOJI['clock']} در انتظار بررسی: " . PaymentManager::countBy(PaymentManager::REVIEW) . "\n";
    $text .= "{$EMOJI['check']} تایید شده: " . PaymentManager::countBy(PaymentManager::APPROVED) . "\n";

    $rows = [
        [bBlue('ممبرشیپ‌ها', 'adm_mems'), bBlue('ربات‌ها', 'adm_bots')],
        [bBlue('پرداخت‌های در انتظار', 'adm_pays')],
        [bGreen('افزودن ممبرشیپ', 'adm_addmem'), bGreen('افزودن ربات', 'adm_addbot')],
        [bBlue('پیام همگانی', 'adm_bc'), bBlue('تنظیمات', 'adm_set')],
        [bBlue('بازگشت', 'home')],
    ];

    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, $rows);
    else sendMsg(BOT_TOKEN, $chatId, $text, $rows);
}

function admMems($chatId, $msgId) {
    global $EMOJI;
    $mems = MembershipManager::all();
    $text = "{$EMOJI['shop']} <b>ممبرشیپ‌ها</b>\n\n";
    $rows = [];
    if (!$mems) {
        $text .= "موردی ثبت نشده.";
    } else {
        foreach ($mems as $m) {
            $bot = BotManager::botFor($m['id']);
            $cap = ((int)$m['limit']) > 0 ? count($m['members']) . '/' . $m['limit'] : count($m['members']) . '/∞';
            $text .= ($m['active'] ? $EMOJI['check'] : $EMOJI['error']) . " <b>" . h($m['name']) . "</b>\n";
            $text .= "   " . h($m['price']) . ' ' . h($m['currency']) . " | اعضا: {$cap}\n";
            $text .= "   ربات: " . ($bot ? '@' . h($bot['username']) : 'وصل نشده') . "\n\n";
            $rows[] = [bRed('حذف ' . $m['name'], 'adm_delmem_' . $m['id'])];
        }
    }
    $rows[] = [bGreen('افزودن ممبرشیپ', 'adm_addmem')];
    $rows[] = [bBlue('بازگشت', 'adm_home')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, $rows);
}

function admBots($chatId, $msgId) {
    global $EMOJI;
    $bots = BotManager::all();
    $text = "{$EMOJI['bot']} <b>ربات‌های فرعی</b>\n\n";
    $rows = [];
    if (!$bots) {
        $text .= "رباتی ثبت نشده.";
    } else {
        foreach ($bots as $b) {
            $m = $b['membership_id'] ? MembershipManager::get($b['membership_id']) : null;
            $text .= "{$EMOJI['bot']} @" . h($b['username']) . "\n";
            $text .= "   ممبرشیپ: " . ($m ? h($m['name']) : 'وصل نشده') . "\n";
            $text .= "   فایل‌ها: " . count(FileManager::listFiles($b['id'])) . "\n\n";
            $rows[] = [bRed('حذف @' . $b['username'], 'adm_delbot_' . $b['id'])];
        }
    }
    $rows[] = [bGreen('افزودن ربات', 'adm_addbot')];
    $rows[] = [bBlue('بازگشت', 'adm_home')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, $rows);
}

function admPays($chatId, $msgId) {
    global $EMOJI;
    $pays = PaymentManager::all();
    $pending = array_filter($pays, fn($p) => $p['status'] === PaymentManager::REVIEW);

    $text = "{$EMOJI['receipt']} <b>پرداخت‌های در انتظار</b>\n\n";
    $rows = [];
    if (!$pending) {
        $text .= "موردی در انتظار نیست.";
    } else {
        foreach (array_slice($pending, 0, 10, true) as $p) {
            $m = MembershipManager::get($p['membership_id']);
            $text .= "{$EMOJI['clock']} " . h($p['username'] ? '@' . $p['username'] : $p['user_id']) . "\n";
            $text .= "   " . ($m ? h($m['name']) : '—') . " | " . h($p['amount']) . ' ' . h($p['currency']) . "\n";
            $text .= "   <code>" . h($p['id']) . "</code>\n\n";
            $rows[] = [bGreen('تایید', 'adm_ok_' . $p['id']), bRed('رد', 'adm_no_' . $p['id'])];
        }
    }
    $rows[] = [bBlue('بازگشت', 'adm_home')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, $rows);
}

function admSettings($chatId, $msgId) {
    global $EMOJI;
    $s = shopSettings();
    $text  = "{$EMOJI['settings']} <b>تنظیمات فروشگاه</b>\n\n";
    $text .= "USDT: <code>" . h($s['wallet_usdt']) . "</code>\n";
    $text .= "TRX: <code>" . h($s['wallet_trx']) . "</code>\n";
    $text .= "کارت: <code>" . h($s['card_number']) . "</code>\n";
    $text .= "پشتیبانی: " . h($s['support'] ?: '—') . "\n";

    $rows = [
        [bBlue('ویرایش USDT', 'adm_set_wallet_usdt'), bBlue('ویرایش TRX', 'adm_set_wallet_trx')],
        [bBlue('ویرایش کارت', 'adm_set_card_number')],
        [bBlue('ویرایش پشتیبانی', 'adm_set_support'), bBlue('متن خوش‌آمد', 'adm_set_welcome_text')],
        [bBlue('بازگشت', 'adm_home')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, $rows);
}

// ============================================================
// 🎬 ربات مادر — پردازش آپدیت
// ============================================================

function masterHandle($update) {
    global $EMOJI;

    // ---------- Callback ----------
    if (isset($update['callback_query'])) {
        $cb     = $update['callback_query'];
        $userId = (int)$cb['from']['id'];
        $chatId = $cb['message']['chat']['id'] ?? $userId;
        $msgId  = $cb['message']['message_id'] ?? null;
        $data   = $cb['data'] ?? '';
        $uname  = $cb['from']['username'] ?? '';
        $cbId   = $cb['id'];

        if (userIsBanned($userId)) { answerCb(BOT_TOKEN, $cbId, 'دسترسی شما مسدود است.', true); return; }

        $isAdmin = ($userId === ADMIN_ID);

        // ---- عمومی ----
        if ($data === 'home') {
            answerCb(BOT_TOKEN, $cbId);
            masterStart($userId, $chatId, $uname, $cb['from']['first_name'] ?? '');
            return;
        }
        if ($data === 'my_subs') {
            answerCb(BOT_TOKEN, $cbId);
            showMySubs($userId, $chatId, $msgId);
            return;
        }
        if (str_starts_with($data, 'full_')) {
            answerCb(BOT_TOKEN, $cbId, "{$EMOJI['lock']} ظرفیت تکمیل شده است.", true);
            return;
        }
        if (str_starts_with($data, 'mylink_')) {
            answerCb(BOT_TOKEN, $cbId);
            sendAccessLink($userId, $chatId, substr($data, 7));
            return;
        }
        if (str_starts_with($data, 'buy_')) {
            answerCb(BOT_TOKEN, $cbId);
            startPurchase($userId, $chatId, $uname, substr($data, 4));
            return;
        }
        if (str_starts_with($data, 'pay_rcpt_')) {
            $pid = substr($data, 9);
            $p = PaymentManager::get($pid);
            if (!$p || (int)$p['user_id'] !== $userId) {
                answerCb(BOT_TOKEN, $cbId, "{$EMOJI['error']} سفارش نامعتبر.", true); return;
            }
            if ($p['status'] !== PaymentManager::PENDING) {
                answerCb(BOT_TOKEN, $cbId, 'این سفارش قبلا ثبت شده.', true); return;
            }
            answerCb(BOT_TOKEN, $cbId);
            setState($userId, 'await_receipt', ['payment_id' => $pid]);
            sendMsg(BOT_TOKEN, $chatId,
                "{$EMOJI['receipt']} لطفا رسید پرداخت را ارسال کنید.\n\n" .
                "می‌توانید <b>عکس رسید</b> بفرستید یا <b>کد تراکنش (TxID)</b> را بنویسید.",
                [[bRed('انصراف', 'pay_cancel_' . $pid)]]);
            return;
        }
        if (str_starts_with($data, 'pay_cancel_')) {
            $pid = substr($data, 11);
            $p = PaymentManager::get($pid);
            if ($p && (int)$p['user_id'] === $userId) clearState($userId);
            answerCb(BOT_TOKEN, $cbId, 'لغو شد.');
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "{$EMOJI['error']} سفارش لغو شد.", [[bBlue('بازگشت', 'home')]]);
            return;
        }

        // ---- ادمین ----
        if (str_starts_with($data, 'adm_')) {
            if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, "{$EMOJI['lock']} دسترسی ندارید.", true); return; }

            if ($data === 'adm_home')  { answerCb(BOT_TOKEN, $cbId); admHome($chatId, $msgId); return; }
            if ($data === 'adm_mems')  { answerCb(BOT_TOKEN, $cbId); admMems($chatId, $msgId); return; }
            if ($data === 'adm_bots')  { answerCb(BOT_TOKEN, $cbId); admBots($chatId, $msgId); return; }
            if ($data === 'adm_pays')  { answerCb(BOT_TOKEN, $cbId); admPays($chatId, $msgId); return; }
            if ($data === 'adm_set')   { answerCb(BOT_TOKEN, $cbId); admSettings($chatId, $msgId); return; }

            if ($data === 'adm_addmem') {
                answerCb(BOT_TOKEN, $cbId);
                setState(ADMIN_ID, 'mem_name', []);
                sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['shop']} نام ممبرشیپ را بفرستید:", [[bRed('انصراف', 'adm_cancel')]]);
                return;
            }
            if ($data === 'adm_addbot') {
                answerCb(BOT_TOKEN, $cbId);
                setState(ADMIN_ID, 'bot_token', []);
                sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['bot']} توکن ربات اپلودری را از @BotFather بگیرید و بفرستید:", [[bRed('انصراف', 'adm_cancel')]]);
                return;
            }
            if ($data === 'adm_bc') {
                answerCb(BOT_TOKEN, $cbId);
                setState(ADMIN_ID, 'broadcast', []);
                sendMsg(BOT_TOKEN, $chatId, "📢 متن پیام همگانی را بفرستید:", [[bRed('انصراف', 'adm_cancel')]]);
                return;
            }
            if ($data === 'adm_cancel') {
                clearState(ADMIN_ID);
                answerCb(BOT_TOKEN, $cbId, 'لغو شد.');
                admHome($chatId, $msgId);
                return;
            }
            if (str_starts_with($data, 'adm_set_')) {
                $key = substr($data, 8);
                $allowed = ['wallet_usdt', 'wallet_trx', 'card_number', 'support', 'welcome_text'];
                if (!in_array($key, $allowed, true)) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر'); return; }
                answerCb(BOT_TOKEN, $cbId);
                setState(ADMIN_ID, 'set_value', ['key' => $key]);
                sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['settings']} مقدار جدید <b>" . h($key) . "</b> را بفرستید:", [[bRed('انصراف', 'adm_cancel')]]);
                return;
            }

            // تایید / رد پرداخت — تنها مسیر تایید، و فقط برای ادمین
            if (str_starts_with($data, 'adm_ok_')) {
                $pid = substr($data, 7);
                [$ok, $res] = PaymentManager::approve($pid, $userId);
                if (!$ok) { answerCb(BOT_TOKEN, $cbId, $res, true); return; }

                $p = $res;
                $m = MembershipManager::get($p['membership_id']);
                $link = AccessManager::linkFor($p['membership_id'], $p['user_id']);

                $userText  = "{$EMOJI['check']} <b>پرداخت شما تایید شد!</b>\n\n";
                $userText .= "ممبرشیپ: " . h($m['name']) . "\n";
                if ($link) {
                    $userText .= "\n{$EMOJI['link']} لینک دسترسی اختصاصی شما:\n{$link}";
                    sendMsg(BOT_TOKEN, $p['user_id'], $userText, [[bUrl('🚀 ورود به ربات', $link)]]);
                } else {
                    $userText .= "\n{$EMOJI['warning']} ربات اپلودری هنوز وصل نشده. به زودی لینک ارسال می‌شود.";
                    sendMsg(BOT_TOKEN, $p['user_id'], $userText);
                }

                answerCb(BOT_TOKEN, $cbId, "{$EMOJI['check']} تایید شد");
                if ($msgId) {
                    editMsg(BOT_TOKEN, $chatId, $msgId,
                        "{$EMOJI['check']} پرداخت <code>" . h($pid) . "</code> تایید شد.\nکاربر عضو «" . h($m['name']) . "» شد.",
                        [[bBlue('پنل', 'adm_home')]]);
                }
                return;
            }
            if (str_starts_with($data, 'adm_no_')) {
                $pid = substr($data, 7);
                [$ok, $res] = PaymentManager::reject($pid, $userId);
                if (!$ok) { answerCb(BOT_TOKEN, $cbId, $res, true); return; }
                sendMsg(BOT_TOKEN, $res['user_id'],
                    "{$EMOJI['error']} پرداخت شما تایید نشد.\nشناسه: <code>" . h($pid) . "</code>\nدر صورت نیاز با پشتیبانی تماس بگیرید.");
                answerCb(BOT_TOKEN, $cbId, 'رد شد');
                if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "{$EMOJI['error']} پرداخت <code>" . h($pid) . "</code> رد شد.", [[bBlue('پنل', 'adm_home')]]);
                return;
            }
            if (str_starts_with($data, 'adm_delmem_')) {
                $mid = substr($data, 11);
                mutate('memberships', function (&$all) use ($mid) { unset($all[$mid]); });
                answerCb(BOT_TOKEN, $cbId, 'حذف شد');
                admMems($chatId, $msgId);
                return;
            }
            if (str_starts_with($data, 'adm_delbot_')) {
                $bid = substr($data, 11);
                mutate('bots', function (&$all) use ($bid) { unset($all[$bid]); });
                mutate('memberships', function (&$all) use ($bid) {
                    foreach ($all as $k => $m) if (($m['bot_id'] ?? null) === $bid) $all[$k]['bot_id'] = null;
                });
                answerCb(BOT_TOKEN, $cbId, 'حذف شد');
                admBots($chatId, $msgId);
                return;
            }
            if (str_starts_with($data, 'adm_linkbot_')) {
                // adm_linkbot_<botId>_<memId>
                $rest = substr($data, 12);
                $pos = strrpos($rest, '|');
                if ($pos === false) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر'); return; }
                $bid = substr($rest, 0, $pos);
                $mid = substr($rest, $pos + 1);
                mutate('bots', function (&$all) use ($bid, $mid) {
                    if (isset($all[$bid])) $all[$bid]['membership_id'] = $mid;
                });
                mutate('memberships', function (&$all) use ($bid, $mid) {
                    if (isset($all[$mid])) $all[$mid]['bot_id'] = $bid;
                });
                answerCb(BOT_TOKEN, $cbId, "{$EMOJI['check']} وصل شد");
                admBots($chatId, $msgId);
                return;
            }

            answerCb(BOT_TOKEN, $cbId);
            return;
        }

        answerCb(BOT_TOKEN, $cbId);
        return;
    }

    // ---------- Message ----------
    if (!isset($update['message'])) return;

    $msg     = $update['message'];
    $userId  = (int)($msg['from']['id'] ?? 0);
    $chatId  = $msg['chat']['id'] ?? $userId;
    $uname   = $msg['from']['username'] ?? '';
    $fname   = $msg['from']['first_name'] ?? '';
    $text    = trim($msg['text'] ?? '');
    if (!$userId) return;

    if (userIsBanned($userId)) return;
    touchUser($userId, $uname, $fname);

    if ($text === '/start') { masterStart($userId, $chatId, $uname, $fname); return; }
    if ($text === '/panel' || $text === '/admin') {
        if ($userId !== ADMIN_ID) { sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['lock']} دسترسی ندارید."); return; }
        admHome($chatId);
        return;
    }
    if ($text === '/cancel') { clearState($userId); sendMsg(BOT_TOKEN, $chatId, "لغو شد."); return; }
    if ($text === '/id')     { sendMsg(BOT_TOKEN, $chatId, "آیدی عددی شما: <code>{$userId}</code>"); return; }

    $state = getState($userId);
    if (!$state) return;

    $action = $state['action'];
    $sd     = $state['data'] ?? [];

    // ---- رسید پرداخت ----
    if ($action === 'await_receipt') {
        $pid = $sd['payment_id'] ?? '';
        $p   = PaymentManager::get($pid);
        if (!$p || (int)$p['user_id'] !== $userId) { clearState($userId); return; }

        $type = null; $value = null;
        if (!empty($msg['photo'])) {
            $photos = $msg['photo'];
            $type  = 'photo';
            $value = $photos[count($photos) - 1]['file_id'];
        } elseif ($text !== '') {
            $type = 'text'; $value = $text;
        }

        if (!$type) {
            sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['warning']} لطفا عکس رسید یا کد تراکنش را بفرستید.");
            return;
        }

        if (!PaymentManager::attachReceipt($pid, $type, $value)) {
            clearState($userId);
            sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['error']} این سفارش دیگر قابل ویرایش نیست.");
            return;
        }

        clearState($userId);
        sendMsg(BOT_TOKEN, $chatId,
            "{$EMOJI['check']} رسید شما ثبت شد.\n\n{$EMOJI['clock']} پس از <b>تایید ادمین</b> لینک دسترسی برایتان ارسال می‌شود.",
            [[bBlue('عضویت‌های من', 'my_subs')]]);
        notifyAdminPayment($pid);
        return;
    }

    // ---- ویزارد ادمین ----
    if ($userId !== ADMIN_ID) return;

    if ($action === 'mem_name') {
        if ($text === '') return;
        setState($userId, 'mem_price', ['name' => $text]);
        sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['money']} قیمت را بفرستید (فقط عدد):", [[bRed('انصراف', 'adm_cancel')]]);
        return;
    }
    if ($action === 'mem_price') {
        if (!is_numeric(str_replace(',', '', $text))) {
            sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['error']} فقط عدد بفرستید."); return;
        }
        $sd['price'] = (float)str_replace(',', '', $text);
        setState($userId, 'mem_currency', $sd);
        sendMsg(BOT_TOKEN, $chatId, "واحد پول را بفرستید: <code>USDT</code> یا <code>TRX</code> یا <code>تومان</code>", [[bRed('انصراف', 'adm_cancel')]]);
        return;
    }
    if ($action === 'mem_currency') {
        if ($text === '') return;
        $sd['currency'] = $text;
        setState($userId, 'mem_limit', $sd);
        sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['member']} محدودیت تعداد اعضا را بفرستید (<code>0</code> = نامحدود):", [[bRed('انصراف', 'adm_cancel')]]);
        return;
    }
    if ($action === 'mem_limit') {
        if (!ctype_digit($text)) { sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['error']} فقط عدد صحیح."); return; }
        $m = MembershipManager::create($sd['name'], $sd['price'], $sd['currency'], (int)$text);
        clearState($userId);
        sendMsg(BOT_TOKEN, $chatId,
            "{$EMOJI['check']} ممبرشیپ ساخته شد.\n\n" .
            "نام: " . h($m['name']) . "\nقیمت: " . h($m['price']) . ' ' . h($m['currency']) . "\n" .
            "محدودیت: " . ((int)$m['limit'] > 0 ? $m['limit'] : 'نامحدود'),
            [[bGreen('افزودن ربات به این ممبرشیپ', 'adm_addbot')], [bBlue('پنل', 'adm_home')]]);
        return;
    }

    if ($action === 'bot_token') {
        $token = $text;
        if (!preg_match('/^\d{6,}:[A-Za-z0-9_\-]{30,}$/', $token)) {
            sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['error']} فرمت توکن درست نیست. دوباره بفرستید."); return;
        }
        $me = tg($token, 'getMe', []);
        if (empty($me['ok'])) {
            sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['error']} توکن معتبر نیست: " . h($me['description'] ?? '')); return;
        }
        $username = $me['result']['username'];

        $mems = MembershipManager::all();
        if (!$mems) {
            clearState($userId);
            sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['warning']} اول یک ممبرشیپ بسازید.", [[bGreen('افزودن ممبرشیپ', 'adm_addmem')]]);
            return;
        }

        $bot = BotManager::create($token, $username, null);

        // تنظیم خودکار وبهوک ربات فرعی
        $domain = shopSettings()['domain'];
        if (!$domain) {
            $domain = (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) : '');
        }
        $hookMsg = '';
        if ($domain) {
            $url = rtrim($domain, '/') . '/' . basename(__FILE__) . '?bot=' . $bot['id'];
            $r = tg($token, 'setWebhook', ['url' => $url, 'drop_pending_updates' => 'true']);
            $hookMsg = !empty($r['ok'])
                ? "\n{$EMOJI['check']} وبهوک تنظیم شد."
                : "\n{$EMOJI['warning']} وبهوک تنظیم نشد: " . h($r['description'] ?? '');
        }

        clearState($userId);
        $rows = [];
        foreach ($mems as $m) {
            $rows[] = [bGreen('اتصال به ' . $m['name'], 'adm_linkbot_' . $bot['id'] . '|' . $m['id'])];
        }
        $rows[] = [bBlue('پنل', 'adm_home')];

        sendMsg(BOT_TOKEN, $chatId,
            "{$EMOJI['check']} ربات <b>@" . h($username) . "</b> ثبت شد." . $hookMsg .
            "\n\nحالا انتخاب کنید به کدام ممبرشیپ وصل شود:", $rows);
        return;
    }

    if ($action === 'set_value') {
        $key = $sd['key'] ?? '';
        $allowed = ['wallet_usdt', 'wallet_trx', 'card_number', 'support', 'welcome_text'];
        if (!in_array($key, $allowed, true)) { clearState($userId); return; }
        mutate('settings', function (&$s) use ($key, $text) { $s[$key] = $text; });
        clearState($userId);
        sendMsg(BOT_TOKEN, $chatId, "{$EMOJI['check']} ذخیره شد.", [[bBlue('تنظیمات', 'adm_set')], [bBlue('پنل', 'adm_home')]]);
        return;
    }

    if ($action === 'broadcast') {
        if ($text === '') return;
        clearState($userId);
        $users = load('users');
        $sent = 0; $fail = 0;
        foreach ($users as $u) {
            if (!empty($u['banned'])) continue;
            $r = sendMsg(BOT_TOKEN, $u['telegram_id'], $text);
            if (!empty($r['ok'])) $sent++; else $fail++;
            usleep(50000); // ~20 پیام در ثانیه — جلوگیری از محدودیت تلگرام
        }
        sendMsg(BOT_TOKEN, $chatId, "📢 ارسال شد.\n{$EMOJI['check']} موفق: {$sent}\n{$EMOJI['error']} ناموفق: {$fail}", [[bBlue('پنل', 'adm_home')]]);
        return;
    }
}

// ============================================================
// 🤖 ربات فرعی (اپلودر)
// ============================================================

function childStateKey($botId) { return botFile($botId, 'states'); }

function childGetState($botId, $userId) {
    $s = load(childStateKey($botId));
    return $s[(string)$userId] ?? null;
}
function childSetState($botId, $userId, $action, $payload = []) {
    mutate(childStateKey($botId), function (&$s) use ($userId, $action, $payload) {
        $s[(string)$userId] = ['action' => $action, 'data' => $payload];
    });
}
function childClearState($botId, $userId) {
    mutate(childStateKey($botId), function (&$s) use ($userId) { unset($s[(string)$userId]); });
}

function extractFile($msg) {
    if (!empty($msg['document']))  return ['document', $msg['document']['file_id'], $msg['document']['file_name'] ?? 'file'];
    if (!empty($msg['video']))     return ['video', $msg['video']['file_id'], $msg['video']['file_name'] ?? 'video'];
    if (!empty($msg['audio']))     return ['audio', $msg['audio']['file_id'], $msg['audio']['title'] ?? 'audio'];
    if (!empty($msg['voice']))     return ['voice', $msg['voice']['file_id'], 'voice'];
    if (!empty($msg['animation'])) return ['animation', $msg['animation']['file_id'], 'animation'];
    if (!empty($msg['photo']))     { $p = $msg['photo']; return ['photo', $p[count($p) - 1]['file_id'], 'photo']; }
    return null;
}

function childMenu($bot, $chatId, $userId, $msgId = null) {
    global $EMOJI;
    $isOwner = ($userId === ADMIN_ID);
    $m = $bot['membership_id'] ? MembershipManager::get($bot['membership_id']) : null;

    $text  = "{$EMOJI['crown']} <b>ربات اعضا</b>\n\n";
    if ($m) $text .= "ممبرشیپ: " . h($m['name']) . "\n";
    $files = FileManager::listFiles($bot['id']);
    $text .= "{$EMOJI['file']} فایل‌ها: " . count($files) . "\n";

    $rows = [[bBlue('فایل‌های من', 'c_files')]];
    if ($isOwner) {
        $rows[] = [bGreen('آپلود فایل', 'c_up'), bGreen('آپلود گروهی', 'c_batch')];
        $rows[] = [bBlue('آمار', 'c_stats'), bBlue('پیام همگانی', 'c_bc')];
    }

    if ($msgId) editMsg($bot['token'], $chatId, $msgId, $text, $rows);
    else sendMsg($bot['token'], $chatId, $text, $rows);
}

function childSendAllFiles($bot, $chatId) {
    global $EMOJI;
    $files = FileManager::listFiles($bot['id']);
    $set   = botSettings($bot['id']);
    if (!$files) {
        sendMsg($bot['token'], $chatId, "{$EMOJI['file']} هنوز فایلی آپلود نشده است.");
        return;
    }
    $n = 0;
    foreach ($files as $f) {
        $cap = $set['caption'] ?: ($f['file_name'] ?? '');
        sendFile($bot['token'], $chatId, $f['file_type'], $f['file_id'], $cap, !empty($set['protect_content']));
        FileManager::countDownload($bot['id'], $f['code']);
        $n++;
        usleep(60000);
        if ($n >= 50) break; // سقف امن در یک درخواست
    }
    sendMsg($bot['token'], $chatId, "{$EMOJI['check']} {$n} فایل ارسال شد.");
}

function childHandle($botId, $update) {
    global $EMOJI;

    $bot = BotManager::get($botId);
    if (!$bot || empty($bot['active'])) return;
    $token = $bot['token'];

    // ---------- Callback ----------
    if (isset($update['callback_query'])) {
        $cb     = $update['callback_query'];
        $userId = (int)$cb['from']['id'];
        $chatId = $cb['message']['chat']['id'] ?? $userId;
        $msgId  = $cb['message']['message_id'] ?? null;
        $data   = $cb['data'] ?? '';
        $cbId   = $cb['id'];
        $isOwner = ($userId === ADMIN_ID);

        $isMember = $bot['membership_id']
            ? MembershipManager::isMember($bot['membership_id'], $userId)
            : false;

        if (!$isOwner && !$isMember) {
            answerCb($token, $cbId, "{$EMOJI['lock']} عضویت شما فعال نیست.", true);
            return;
        }

        if ($data === 'c_home')  { answerCb($token, $cbId); childMenu($bot, $chatId, $userId, $msgId); return; }
        if ($data === 'c_files') { answerCb($token, $cbId); childSendAllFiles($bot, $chatId); return; }

        if (!$isOwner) { answerCb($token, $cbId); return; }

        if ($data === 'c_up') {
            answerCb($token, $cbId);
            childSetState($botId, $userId, 'upload');
            sendMsg($token, $chatId, "{$EMOJI['upload']} فایل را بفرستید.", [[bRed('انصراف', 'c_cancel')]]);
            return;
        }
        if ($data === 'c_batch') {
            answerCb($token, $cbId);
            $batch = FileManager::startBatch($botId);
            childSetState($botId, $userId, 'batch', ['batch' => $batch, 'count' => 0]);
            sendMsg($token, $chatId, "{$EMOJI['upload']} فایل‌ها را یکی‌یکی بفرستید. در پایان /done را بزنید.", [[bRed('انصراف', 'c_cancel')]]);
            return;
        }
        if ($data === 'c_bc') {
            answerCb($token, $cbId);
            childSetState($botId, $userId, 'bc');
            sendMsg($token, $chatId, "📢 متن پیام همگانی را بفرستید:", [[bRed('انصراف', 'c_cancel')]]);
            return;
        }
        if ($data === 'c_cancel') {
            childClearState($botId, $userId);
            answerCb($token, $cbId, 'لغو شد');
            childMenu($bot, $chatId, $userId, $msgId);
            return;
        }
        if ($data === 'c_stats') {
            answerCb($token, $cbId);
            $files = FileManager::listFiles($botId);
            $users = load(botFile($botId, 'users'));
            $dl = 0; foreach ($files as $f) $dl += (int)$f['downloads'];
            $m = $bot['membership_id'] ? MembershipManager::get($bot['membership_id']) : null;
            $text  = "{$EMOJI['chart']} <b>آمار</b>\n\n";
            $text .= "{$EMOJI['member']} کاربران ربات: " . count($users) . "\n";
            $text .= "{$EMOJI['file']} فایل‌ها: " . count($files) . "\n";
            $text .= "{$EMOJI['upload']} دانلودها: {$dl}\n";
            if ($m) $text .= "{$EMOJI['shop']} اعضای ممبرشیپ: " . count($m['members']) . "\n";
            editMsg($token, $chatId, $msgId, $text, [[bBlue('بازگشت', 'c_home')]]);
            return;
        }

        answerCb($token, $cbId);
        return;
    }

    // ---------- Message ----------
    if (!isset($update['message'])) return;
    $msg    = $update['message'];
    $userId = (int)($msg['from']['id'] ?? 0);
    $chatId = $msg['chat']['id'] ?? $userId;
    $uname  = $msg['from']['username'] ?? '';
    $fname  = $msg['from']['first_name'] ?? '';
    $text   = trim($msg['text'] ?? '');
    if (!$userId) return;

    $isOwner = ($userId === ADMIN_ID);

    // ---- /start [code] ----
    if (str_starts_with($text, '/start')) {
        $parts = explode(' ', $text, 2);
        $code  = trim($parts[1] ?? '');

        if ($isOwner) {
            botUserTouch($botId, $userId, $uname);
            childMenu($bot, $chatId, $userId);
            return;
        }

        // عضو موجود بدون کد
        if ($code === '' && $bot['membership_id'] && MembershipManager::isMember($bot['membership_id'], $userId)) {
            botUserTouch($botId, $userId, $uname);
            $set = botSettings($botId);
            sendMsg($token, $chatId, str_replace('{name}', h($fname), $set['welcome']));
            childMenu($bot, $chatId, $userId);
            return;
        }

        if ($code === '') {
            sendMsg($token, $chatId,
                "{$EMOJI['lock']} این ربات مخصوص اعضاست.\n\nبرای دریافت دسترسی ابتدا ممبرشیپ را از ربات فروش تهیه کنید.");
            return;
        }

        [$ok, $res] = AccessManager::validate($code, $userId);
        if (!$ok) {
            sendMsg($token, $chatId, "{$EMOJI['error']} " . h($res));
            return;
        }

        AccessManager::markUsed($code);
        botUserTouch($botId, $userId, $uname);
        $set = botSettings($botId);
        sendMsg($token, $chatId,
            "{$EMOJI['check']} دسترسی شما فعال شد!\n\n" . str_replace('{name}', h($fname), $set['welcome']));
        childMenu($bot, $chatId, $userId);
        return;
    }

    if (!$isOwner) {
        // اعضا فقط منو دارند
        if ($bot['membership_id'] && MembershipManager::isMember($bot['membership_id'], $userId)) {
            childMenu($bot, $chatId, $userId);
        }
        return;
    }

    // ---- مالک ----
    $st = childGetState($botId, $userId);
    if (!$st) {
        if ($text === '/panel') childMenu($bot, $chatId, $userId);
        return;
    }
    $action = $st['action'];
    $sd     = $st['data'] ?? [];

    if ($action === 'upload') {
        $f = extractFile($msg);
        if (!$f) { sendMsg($token, $chatId, "{$EMOJI['warning']} یک فایل بفرستید."); return; }
        [$type, $fileId, $name] = $f;
        FileManager::addFile($botId, $fileId, $name, $type);
        childClearState($botId, $userId);
        sendMsg($token, $chatId, "{$EMOJI['check']} فایل «" . h($name) . "» ذخیره شد.", [[bBlue('منو', 'c_home')]]);
        return;
    }

    if ($action === 'batch') {
        if ($text === '/done') {
            childClearState($botId, $userId);
            sendMsg($token, $chatId, "{$EMOJI['check']} مجموعه با " . (int)$sd['count'] . " فایل ذخیره شد.", [[bBlue('منو', 'c_home')]]);
            return;
        }
        $f = extractFile($msg);
        if (!$f) { sendMsg($token, $chatId, "{$EMOJI['warning']} فایل بفرستید یا /done بزنید."); return; }
        [$type, $fileId, $name] = $f;
        $fc = FileManager::addFile($botId, $fileId, $name, $type, $sd['batch']);
        FileManager::addToBatch($botId, $sd['batch'], $fc);
        $sd['count'] = (int)$sd['count'] + 1;
        childSetState($botId, $userId, 'batch', $sd);
        sendMsg($token, $chatId, "{$EMOJI['check']} " . $sd['count'] . " فایل ثبت شد. (/done برای پایان)");
        return;
    }

    if ($action === 'bc') {
        if ($text === '') return;
        childClearState($botId, $userId);
        $users = load(botFile($botId, 'users'));
        $sent = 0; $fail = 0;
        foreach ($users as $u) {
            $r = sendMsg($token, $u['id'], $text);
            if (!empty($r['ok'])) $sent++; else $fail++;
            usleep(50000);
        }
        sendMsg($token, $chatId, "📢 موفق: {$sent} | ناموفق: {$fail}", [[bBlue('منو', 'c_home')]]);
        return;
    }
}

// ============================================================
// 🎯 ورودی Webhook
// ============================================================

// پنل ادمین این فایل را به عنوان کتابخانه include می‌کند؛ در آن حالت
// وبهوک نباید اجرا شود (تا منطق داده در دو جا کپی و واگرا نشود).
if (defined('MEMBERSHIP_LIB_ONLY')) return;

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);

http_response_code(200);

if (is_array($update)) {
    $botId = $_GET['bot'] ?? null;
    try {
        if ($botId) childHandle($botId, $update);
        else        masterHandle($update);
    } catch (Throwable $e) {
        error_log('[membership-bot] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}

echo json_encode(['ok' => true]);
