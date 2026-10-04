<?php
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('zend.exception_ignore_args', '1');

if (!defined('NB_ROOT')) define('NB_ROOT', __DIR__);

function nbModMap() {
    return [
        'numbers' => 'store',
        'services' => 'store',
        'services_pick' => 'store',
        'pay' => 'store',
        'coupons' => 'store',
        'miniapps' => 'miniapp',
        'miniapp_view' => 'miniapp',
        'miniapp_view_tgs' => 'miniapp',
        'miniapp_view_igs' => 'miniapp',
        'miniapp_view_wheel' => 'miniapp',
        'airdrop' => 'miniapp',
        'games' => 'games',
        'mine' => 'games',
        'quiz' => 'games',
        'wheel' => 'games',
        'toplist' => 'games',
        'diamond' => 'games',
        'bank' => 'games',
        'bankcard' => 'games',
        'prices' => 'group',
        'translate' => 'group',
        'autoreply' => 'group',
        'channels' => 'group',
        'fonts' => 'core',
        'metrics' => 'core', 'panel_auth' => 'core', 'shield' => 'core', 'doctor' => 'core', 'emoji' => 'core', 'db' => 'core',
    ];
}

function nbMod($name) {
    return NB_ROOT . '/modules/' . (nbModMap()[$name] ?? 'core') . '/' . $name . '.php';
}

function nbAsset($rel) {
    return NB_ROOT . '/assets/' . ltrim($rel, '/');
}

if (is_file(__DIR__ . '/config.local.php')) require_once __DIR__ . '/config.local.php';

if (!defined('BOT_TOKEN')) define('BOT_TOKEN', (string)getenv('BOT_TOKEN'));
if (!defined('ADMIN_ID'))  define('ADMIN_ID',  (int)getenv('ADMIN_ID'));
if (!defined('ADMIN_IDS')) define('ADMIN_IDS', ADMIN_ID > 0 ? [ADMIN_ID] : []);

function isAdmin($uid) {
    if (is_string($uid)) {
        if (!preg_match('~^\d{1,19}$~', trim($uid))) return false;
    } elseif (!is_int($uid)) {
        return false;
    }
    $id = (int)$uid;
    if ($id <= 0) return false;
    return in_array($id, array_map('intval', ADMIN_IDS), true);
}

function notifyAdmins($text, $kb = null, $extra = []) {
    $sent = false;
    foreach (ADMIN_IDS as $aid) {
        $r = sendMsg(BOT_TOKEN, $aid, $text, $kb, $extra);
        if (!empty($r['ok'])) $sent = true;
    }
    return $sent;
}

if (!defined('DATA_DIR'))
    define('DATA_DIR',  getenv('DATA_DIR') ?: __DIR__ . '/data_master');
if (!defined('CRON_KEY'))
    define('CRON_KEY',  getenv('CRON_KEY') ?: '');

if (!defined('WEBHOOK_SECRET'))
    define('WEBHOOK_SECRET', getenv('WEBHOOK_SECRET') ?: '');

function webhookSecretOk() {
    if (WEBHOOK_SECRET === '') {
        if (function_exists('adminAlertOnce')) {
            adminAlertOnce('webhook_secret_missing',
                "🔴 WEBHOOK_SECRET تنظیم نشده — تا وقتی تنظیم نشود، هیچ آپدیتی از تلگرام پذیرفته نمی‌شود.\n" .
                "در config.local.php مقدارش را بگذارید، بعد یک‌بار «تنظیم وبهوک» را از پنل یا ربات بزنید.", 3600);
        }
        return false;
    }
    $got = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    return is_string($got) && $got !== '' && hash_equals(WEBHOOK_SECRET, $got);
}

require_once nbMod('metrics');
require_once nbMod('shield');
require_once nbMod('doctor');
require_once nbMod('emoji');
require_once nbMod('db');

function nbDeny($code = 401) {
    http_response_code($code);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "✅ ربات سالم است.\n\n" .
             "همین ۴۰۱ یعنی همه‌چیز درست کار می‌کند: این آدرس فقط آپدیتِ\n" .
             "امضاشده‌ی تلگرام را می‌پذیرد و درخواستِ مرورگر را — که امضا\n" .
             "ندارد — رد می‌کند. اگر خراب بود، این پیام را نمی‌دیدید.\n\n" .
             "برای دیدنِ وضعیتِ وبهوک، این را در مرورگر باز کنید\n" .
             "(توکن را از config.local.php بردارید):\n\n" .
             "  https://api.telegram.org/bot<TOKEN>/getWebhookInfo\n";
    }
    exit;
}

function nbEarlyGate() {
    if (defined('MEMBERSHIP_LIB_ONLY') || PHP_SAPI === 'cli' || WEBHOOK_SECRET === '') return;
    foreach (['ipn', 'irpay', 'paystat', 'app', 'maav', 'marp', 'mapi', 'cron', 'bot'] as $k) if (isset($_GET[$k])) return;
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($post && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1048576) { monBlocked('size'); nbDeny(413); }
    if ($post && webhookSecretOk()) return;
    monBlocked($post ? 'secret' : 'probe');
    nbDeny(401);
}

monBegin();
nbEarlyGate();

function ssrfSafeUrl($url, &$reason = null) {
    $url = trim((string)$url);
    $p = parse_url($url);
    if (!$p || empty($p['scheme']) || empty($p['host']) || !in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
        $reason = 'آدرس نامعتبر است';
        return false;
    }
    $host = trim((string)$p['host'], '[]');
    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips = [$host];
    } else {
        $ips = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $ips = array_filter(array_map(fn($r) => $r['ip'] ?? ($r['ipv6'] ?? null), $ips));
        if (!$ips) $ips = [gethostbyname($host)];
    }
    $ips = array_values(array_filter($ips, fn($ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false));
    if (!$ips) { $reason = 'این آدرس پیدا نشد'; return false; }
    foreach ($ips as $ip) {
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $mm)) $ip = $mm[1];
        $isPublic = filter_var($ip, FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if ($isPublic === false || $ip === '169.254.169.254' || preg_match('/^(fe[89ab]|f[cd]|::$|::1$)/i', $ip)) {
            $reason = 'این آدرس به شبکه‌ی داخلی/محلی اشاره می‌کند — اجازه‌ی درخواست به آن نیست';
            return false;
        }
    }
    return true;
}

if (!class_exists('SQLite3') && !dbOn()) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("افزونه‌ی SQLite3 در PHP این هاست خاموش است.\n\n" .
         "کاربرانِ ربات (موجودی، رفرال، سفارش) روی این افزونه ذخیره می‌شوند —\n" .
         "بدونش ربات بالا نمی‌آید تا داده‌ی کسی گم نشود.\n\n" .
         "در cPanel: Select PHP Version ← Extensions ← تیکِ sqlite3 را بزنید ← Save.\n" .
         "چیزی دستی ساخته نمی‌شود؛ فایلِ دیتابیس را خودِ ربات داخلِ data_master می‌سازد.\n" .
         "بعد همین صفحه را دوباره باز کنید.\n");
}

if (!defined('MEMBERSHIP_LIB_ONLY') && (BOT_TOKEN === '' || ADMIN_ID <= 0)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("پیکربندی ناقص است.\n\n" .
         "کنار همین فایل یک config.local.php بسازید:\n\n" .
         "<?php\n" .
         "define('BOT_TOKEN', 'توکن ربات از BotFather');\n" .
         "define('ADMIN_ID', 123456789);\n" .
         "define('CRON_KEY', 'یک رشته تصادفی بلند');\n" .
         "define('ADMIN_PANEL_PASS', 'رمز پنل وب');\n" .
         "define('HEALTH_KEY', 'یک رشته تصادفی بلند دیگر');\n");
}

if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0755, true);
dataDirLockdown();
if (is_dir(DATA_DIR) && is_writable(DATA_DIR)) {
    $__el = DATA_DIR . '/php_errors.log';
    if (is_file($__el) && @filesize($__el) > 5 * 1048576) @rename($__el, $__el . '.1');
    @ini_set('error_log', $__el);
    unset($__el);
}

function dataDirLockdown() {
    $d = rtrim(DATA_DIR, '/');
    if (!is_dir($d)) return;

    $files = [
        '.htaccess' => "Require all denied\n" .
                       "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
        'web.config' => '<?xml version="1.0"?><configuration><system.webServer><security>' .
                        '<authorization><deny users="*" /></authorization>' .
                        '</security></system.webServer></configuration>',
        'index.html' => '',
    ];
    foreach ($files as $name => $content) {
        $f = $d . '/' . $name;
        if (!file_exists($f)) @file_put_contents($f, $content);
    }
}

@ignore_user_abort(true);

require_once nbMod('miniapps');
require_once nbMod('numbers');
require_once nbMod('prices');
require_once nbMod('diamond');
require_once nbMod('channels');
require_once nbMod('games');
require_once nbMod('airdrop');
require_once nbMod('coupons');
require_once nbMod('bank');
require_once nbMod('mine');

migrateOnce('vault_refund', function () {
    $f = DATA_DIR . '/vault.sqlite';
    if (!is_file($f) || !class_exists('SQLite3')) return;
    if (!function_exists('gmAdd')) throw new RuntimeException('gmAdd هنوز بارگذاری نشده');
    $db = new SQLite3($f);
    $db->busyTimeout(5000);
    $res = @$db->query('SELECT id, locked FROM vault_users WHERE locked > 0');
    if (!$res) { $db->close(); return; }
    $n = 0; $sum = 0.0;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $uid = (int)$row['id'];
        $amt = (float)$row['locked'];
        if ($uid <= 0 || $amt <= 0) continue;
        if (function_exists('gmAdd') && gmAdd($uid, $amt)) {
            $up = $db->prepare('UPDATE vault_users SET locked = 0 WHERE id = :i');
            $up->bindValue(':i', $uid, SQLITE3_INTEGER);
            $up->execute();
            $n++; $sum += $amt;
        }
    }
    $db->close();
    if ($n > 0) error_log(sprintf('[shop-bot] بانکِ الماسی بسته شد — %s الماس به %d کیف‌پول برگشت.', number_format($sum), $n));
});

migrateOnce('arcade_refund', function () {
    $f = DATA_DIR . '/arcade_games.sqlite';
    if (!is_file($f) || !class_exists('SQLite3')) return;
    if (!function_exists('gmAdd')) throw new RuntimeException('gmAdd هنوز بارگذاری نشده');

    $db = new SQLite3($f);
    $db->busyTimeout(5000);
    $res = @$db->query("SELECT id, data FROM arcade_games WHERE status IN ('open','playing')");
    if (!$res) { $db->close(); return; }
    $n = 0; $sum = 0.0;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $g = json_decode((string)$row['data'], true);
        if (!is_array($g)) continue;
        $stake = (float)($g['stake'] ?? 0);
        if ($stake <= 0) continue;
        foreach ([(int)($g['host'] ?? 0), (int)($g['guest'] ?? 0)] as $who) {
            if ($who <= 0) continue;
            if (gmAdd($who, $stake)) { $n++; $sum += $stake; }
        }
        $up = $db->prepare("UPDATE arcade_games SET status = 'cancelled' WHERE id = :i");
        $up->bindValue(':i', (string)$row['id'], SQLITE3_TEXT);
        $up->execute();
    }
    $db->close();
    if ($n > 0) error_log(sprintf('[shop-bot] بازی‌های سنگ‌کاغذقیچی/بسکتبال بسته شدند — %s الماس به %d نفر برگشت.', number_format($sum), $n));
});
migrateOnce('numbers_only', function () {
    cfgSet(function (&$c) {
        $old = is_array($c['miniapps'] ?? null) ? $c['miniapps'] : [];
        if (is_array($old['apps'] ?? null) || !isset($old['items'])) {
            $num = is_array($old['apps']['num'] ?? null) ? $old['apps']['num'] : [];
            $m = maDefaultConfig();
            foreach (['base_url', 'init_max_age', 'rate_ip', 'rate_user', 'trust_proxy'] as $k)
                if (array_key_exists($k, $old)) $m[$k] = $old[$k];
            if (is_array($old['sup'] ?? null)) $m['sup'] = array_replace($m['sup'], $old['sup']);
            if (array_key_exists('on', $num)) $m['on'] = !empty($num['on']);
            foreach (['title', 'note'] as $k)
                if (trim((string)($num[$k] ?? '')) !== '') $m[$k] = (string)$num[$k];
            $keep = array_flip(['id', 'cat', 'svc', 'prov', 'emoji', 'name', 'price', 'badge', 'on', 'order']);
            $m['cats'] = array_values(array_filter((array)($num['cats'] ?? []), 'is_array'));
            foreach ((array)($num['items'] ?? []) as $it)
                if (is_array($it)) $m['items'][] = array_intersect_key($it, $keep);
            $c['miniapps'] = $m;
        }

        foreach ((array)($c['buttons'] ?? []) as $id => $b) {
            if (!is_array($b)) continue;
            unset($b['subs'], $b['subs_mode'], $b['carousel_texts'], $b['carousel'], $b['sub_layout'], $b['dot']);
            $dead = ($b['action'] ?? '') === 'product'
                 || (($b['action'] ?? '') === 'text' && str_starts_with((string)$id, 'c_')
                     && in_array(trim(strip_tags((string)($b['value'] ?? ''))),
                                 ['', 'متن این دکمه را تنظیم کنید.', 'این متن را از پنل عوض کنید.'], true));
            if ($dead && str_starts_with((string)$id, 'c_')) { unset($c['buttons'][$id]); continue; }
            if (($b['action'] ?? '') === 'product') { $b['action'] = ''; unset($b['value']); }
            $c['buttons'][$id] = $b;
        }
        if (is_array($c['diamond']['gift'] ?? null)) {
            if (($c['diamond']['gift']['app'] ?? 'num') !== 'num') {
                $c['diamond']['gift']['item'] = '';
                $c['diamond']['gift']['on'] = false;
            }
            unset($c['diamond']['gift']['app']);
        }
    });
});

require_once nbMod('quiz');
require_once nbMod('translate');
require_once nbMod('services');
require_once nbMod('bankcard');

migrateOnce('rand_refund', function () {
    $f = DATA_DIR . '/games.sqlite';
    if (!is_file($f) || !class_exists('SQLite3')) return;
    if (!function_exists('gmAdd')) throw new RuntimeException('gmAdd هنوز بارگذاری نشده');

    $db = new SQLite3($f);
    $db->busyTimeout(5000);
    $res = @$db->query("SELECT id, data FROM games WHERE status IN ('open','playing')");
    if (!$res) { $db->close(); return; }
    $n = 0; $sum = 0.0;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $g = json_decode((string)$row['data'], true);
        if (!is_array($g) || ($g['kind'] ?? '') !== 'rand') continue;
        $stake = (float)($g['stake'] ?? 0);
        foreach ((array)($g['players'] ?? []) as $pl) {
            $pid = (int)($pl['id'] ?? 0);
            if ($pid > 0 && $stake > 0 && gmAdd($pid, $stake)) { $n++; $sum += $stake; }
        }
        $up = $db->prepare("UPDATE games SET status = 'cancelled' WHERE id = :i");
        $up->bindValue(':i', (string)$row['id'], SQLITE3_TEXT);
        $up->execute();
    }
    $db->close();
    if ($n > 0) error_log(sprintf('[shop-bot] قرعه‌کشی حذف شد — %s الماس به %d شرکت‌کننده برگشت.', number_format($sum), $n));
});

migrateOnce('factory_payout', function () {
    $f = DATA_DIR . '/factory_users.sqlite';
    if (!is_file($f) || !class_exists('SQLite3')) return;
    if (!function_exists('gmAdd')) throw new RuntimeException('gmAdd هنوز بارگذاری نشده');

    $db = new SQLite3($f);
    $db->busyTimeout(5000);
    $res = @$db->query('SELECT id, data FROM factory_users');
    if (!$res) { $db->close(); return; }
    $n = 0; $sum = 0.0;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $u = json_decode((string)$row['data'], true);
        if (!is_array($u)) continue;
        $stored = (float)($u['stored'] ?? 0);
        $uid    = (int)$row['id'];
        if ($uid <= 0 || $stored <= 0) continue;
        if (gmAdd($uid, $stored)) {
            $u['stored'] = 0.0;
            $up = $db->prepare('UPDATE factory_users SET data = :d WHERE id = :i');
            $up->bindValue(':d', json_encode($u, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
            $up->bindValue(':i', $uid, SQLITE3_INTEGER);
            $up->execute();
            $n++; $sum += $stored;
        }
    }
    $db->close();
    if ($n > 0) error_log(sprintf('[shop-bot] کارخانه بسته شد — %s الماسِ انبار به %d کیف‌پول ریخت.', number_format($sum), $n));
});

foreach (['fonts', 'toplist', 'pay', 'autoreply', 'wheel', 'miniapp_view_wheel'] as $__m) {
    $__p = nbMod($__m);
    if (is_file($__p)) require_once $__p;
    else error_log('[shop-bot] ماژولِ ' . $__m . '.php روی سرور نیست — آن بخش خاموش می‌ماند.');
}
unset($__m, $__p);

function dataPath($file) { return DATA_DIR . '/' . $file . '.json'; }

function dataCache($file, $mode = 'has', $data = null, $raw = null) {
    static $v = [], $r = [];
    switch ($mode) {
        case 'has': return array_key_exists($file, $v);
        case 'get': return $v[$file] ?? [];
        case 'raw': return $r[$file] ?? null;
        case 'put': $v[$file] = $data; $r[$file] = $raw; return $data;
        case 'drop': unset($v[$file], $r[$file]); return null;
    }
    return null;
}

function dataBad($file, $set = null) {
    static $bad = [];
    if ($set !== null) $bad[$file] = (bool)$set;
    return !empty($bad[$file]);
}

function load($file, $fresh = false) {
    if (!$fresh && dataCache($file, 'has')) return dataCache($file, 'get');

    dataBad($file, false);
    $path = dataPath($file);
    if (!is_file($path)) return dataCache($file, 'put', [], '');
    $raw = @file_get_contents($path);
    if ($raw === false) {
        dataBad($file, true);
        error_log('[shop-bot] فایلِ داده خوانده نشد: ' . $path);
        return dataCache($file, 'has') ? dataCache($file, 'get') : [];
    }
    if ($raw === '') return dataCache($file, 'put', [], '');

    if (dataCache($file, 'raw') === $raw) return dataCache($file, 'get');

    $out = json_decode($raw, true);
    if (is_array($out)) {
        if ($file === 'config' && !is_file($path . '.bak')
            && @file_put_contents($path . '.bak.tmp', $raw) === strlen($raw)) @rename($path . '.bak.tmp', $path . '.bak');
        return dataCache($file, 'put', $out, $raw);
    }

    $bak = @file_get_contents($path . '.bak');
    $old = is_string($bak) && $bak !== '' ? json_decode($bak, true) : null;
    error_log('[shop-bot] فایلِ داده خراب بود: ' . $path . (is_array($old) ? ' — از نسخه‌ی پشتیبان برگشت' : ' — پشتیبان هم نبود'));
    if (is_array($old)) return dataCache($file, 'put', $old, null);
    dataBad($file, true);
    return [];
}

function save($file, $data) {
    $path = dataPath($file);
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;

    if (dataCache($file, 'raw') === $json && is_file($path)) {
        dataCache($file, 'put', $data, $json);
        return true;
    }

    $tmp = $path . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) !== strlen($json)) {
        @unlink($tmp);
        error_log('[shop-bot] نوشتنِ فایلِ داده ناقص ماند (فضای هاست پر است؟): ' . $path);
        return false;
    }
    $ok = rename($tmp, $path);
    if ($ok) {
        if (in_array((string)$file, ['config', 'kyc_queue', 'bots'], true)
            && @file_put_contents($path . '.bak.tmp', $json) === strlen($json)) @rename($path . '.bak.tmp', $path . '.bak');
        dataCache($file, 'put', $data, $json);

        if (function_exists('maForget')) maForget();
    } else @unlink($tmp);
    return $ok;
}

function mutate($file, callable $fn) {
    $lockPath = dataPath($file) . '.lock';
    $dir = dirname($lockPath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fp = @fopen($lockPath, 'c');
    if ($fp) {
        flock($fp, LOCK_EX);
    } else {
        static $warned = [];
        if (empty($warned[$file])) {
            $warned[$file] = true;
            error_log('[shop-bot] قفل ساخته نشد (پوشه‌ی داده قابل نوشتن نیست؟): ' . $lockPath);
        }
    }
    try {
        $data = load($file, true);
        if (dataBad($file)) return null;
        $result = $fn($data);
        save($file, $data);
        return $result;
    } finally {
        if ($fp) { flock($fp, LOCK_UN); fclose($fp); }
    }
}

function uid($p) { return $p . '_' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(3)); }
function h($s)      { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function nowStr()   { return date('Y-m-d H:i:s'); }
function fmtNum($n) { return rtrim(rtrim(number_format((float)$n, 2, '.', ','), '0'), '.'); }

if (!defined('TG_API_BASE')) define('TG_API_BASE', 'https://api.telegram.org');

function tgHtmlFix($html) {
    static $allow = ['b', 'strong', 'i', 'em', 'u', 'ins', 's', 'strike', 'del', 'span', 'tg-spoiler', 'a', 'code', 'pre', 'blockquote', 'tg-emoji'];
    $out = ''; $stack = [];
    foreach (preg_split('~(<[^<>]*>)~u', (string)$html, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $p) {
        if ($i % 2 === 0) {
            $p = preg_replace('~&(?!(?:lt|gt|amp|quot|#\d+|#x[0-9a-fA-F]+);)~', '&amp;', $p);
            $out .= str_replace(['<', '>'], ['&lt;', '&gt;'], $p);
            continue;
        }
        if (!preg_match('~^<(/?)([a-zA-Z][a-zA-Z0-9-]*)(?:\s[^>]*)?/?>$~', $p, $m)) { $out .= htmlspecialchars($p, ENT_NOQUOTES); continue; }
        $tag = strtolower($m[2]);
        if ($tag === 'br') { $out .= "\n"; continue; }
        if (!in_array($tag, $allow, true)) { $out .= htmlspecialchars($p, ENT_NOQUOTES); continue; }
        if ($m[1] === '') { $stack[] = $tag; $out .= $p; continue; }
        $at = array_search($tag, array_reverse($stack, true), true);
        if ($at === false) continue;
        while (count($stack) > $at) $out .= '</' . array_pop($stack) . '>';
    }
    while ($stack) $out .= '</' . array_pop($stack) . '>';
    return preg_replace('~<blockquote(?:\s[^>]*)?>\s*</blockquote>~u', '', $out);
}

function tg($token, $method, $data = [], $timeout = 20) {
    $data = emOut($method, $data);
    if (function_exists('__tgHook')) return __tgHook($token, $method, $data);
    $res = tgRaw($token, $method, $data, $timeout);
    $key = isset($data['text']) ? 'text' : (isset($data['caption']) ? 'caption' : '');
    if (!empty($res['ok']) || $key === '' || !is_string($data[$key]) || ($data['parse_mode'] ?? '') !== 'HTML'
        || stripos((string)($res['description'] ?? ''), "can't parse entities") === false) return $res;
    error_log('[shop-bot] HTML نامعتبر در ' . $method . ': ' . ($res['description'] ?? '') . ' — ترمیم و ارسال دوباره');
    $fixed = tgHtmlFix($data[$key]);
    if ($fixed !== $data[$key]) {
        $res = tgRaw($token, $method, [$key => $fixed] + $data, $timeout);
        if (!empty($res['ok']) || stripos((string)($res['description'] ?? ''), "can't parse entities") === false) return $res;
    }
    $plain = $data;
    unset($plain['parse_mode']);
    $plain[$key] = emText(html_entity_decode(strip_tags($data[$key]), ENT_QUOTES | ENT_HTML5, 'UTF-8'), false);
    return tgRaw($token, $method, $plain, $timeout);
}

function &tgNet() {
    static $n = null;
    if ($n === null) {
        $n = ['mh' => null, 'bg' => [], 'done' => [], 'shut' => false];
        if (function_exists('curl_multi_init')) $n['mh'] = curl_multi_init();
    }
    return $n;
}

function tgPin($learn = null) {
    static $st = null;
    if ($st === null) {
        $st = ['host' => '', 'port' => 0, 'ip' => '', 'drop' => false, 'f' => DATA_DIR . '/.tg_ip'];
        $u = parse_url((string)TG_API_BASE);
        $h = strtolower((string)($u['host'] ?? ''));
        if ($h !== '' && $h !== 'localhost' && !filter_var($h, FILTER_VALIDATE_IP)) {
            $st['host'] = $h;
            $st['port'] = (int)($u['port'] ?? (strtolower((string)($u['scheme'] ?? '')) === 'http' ? 80 : 443));
            $raw = @file_get_contents($st['f']);
            if (is_string($raw) && preg_match('/^(\S+) (\S+) (\d+)$/', trim($raw), $m) && $m[1] === $h
                && filter_var($m[2], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && time() - (int)$m[3] < 900)
                $st['ip'] = $m[2];
        }
    }
    if ($learn === false && $st['ip'] !== '') {
        $st['ip'] = '';
        $st['drop'] = true;
        @unlink($st['f']);
    } elseif (is_string($learn) && $st['host'] !== '' && $learn !== $st['ip']
              && filter_var($learn, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $st['ip'] = $learn;
        @file_put_contents($st['f'], $st['host'] . ' ' . $learn . ' ' . time(), LOCK_EX);
    }
    return $st;
}

function tgHandle($url, $post, $timeout, $file = false) {
    $ch = curl_init($url);
    $o = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(3, (int)$timeout) * ($file ? 3 : 1),
        CURLOPT_CONNECTTIMEOUT => min(5, max(3, (int)$timeout)),
        CURLOPT_TCP_NODELAY => true,
    ];
    $p = tgPin();
    if ($p['host'] !== '') {
        $o[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        if ($p['ip'] !== '') $o[CURLOPT_RESOLVE] = [$p['host'] . ':' . $p['port'] . ':' . $p['ip']];
        elseif ($p['drop']) $o[CURLOPT_RESOLVE] = ['-' . $p['host'] . ':' . $p['port']];
    }
    curl_setopt_array($ch, $o);
    return $ch;
}

function tgPump(array $want = [], $deadline = 0.0) {
    $n = &tgNet();
    $mh = $n['mh'];
    while (true) {
        do { $st = curl_multi_exec($mh, $running); } while ($st === CURLM_CALL_MULTI_PERFORM);
        while ($info = curl_multi_info_read($mh)) {
            $h = $info['handle'];
            $id = spl_object_id($h);
            if (isset($n['bg'][$id])) {
                monCurlDone($h, 'telegram');
                if ((int)$info['result'] === 0) tgPin((string)curl_getinfo($h, CURLINFO_PRIMARY_IP));
                elseif (in_array((int)$info['result'], [6, 7], true)) tgPin(false);
                curl_multi_remove_handle($mh, $h);
                curl_close($h);
                unset($n['bg'][$id]);
            } else {
                $n['done'][$id] = (int)$info['result'];
            }
        }
        $left = false;
        foreach ($want as $h) if (!isset($n['done'][spl_object_id($h)])) { $left = true; break; }
        if ($want ? !$left : !$n['bg']) return;
        if (!$running) {
            foreach ($want as $h) if (!isset($n['done'][spl_object_id($h)])) $n['done'][spl_object_id($h)] = 28;
            return;
        }
        if ($deadline > 0 && microtime(true) > $deadline) return;
        if (curl_multi_select($mh, 0.25) === -1) usleep(2000);
    }
}

function tgRun($ch) {
    $n = &tgNet();
    if (!$n['mh']) {
        $body = monCurl($ch, 'telegram');
        return [$body === false ? '' : (string)$body, (int)curl_errno($ch)];
    }
    curl_multi_add_handle($n['mh'], $ch);
    tgPump([$ch]);
    $id = spl_object_id($ch);
    $errno = (int)($n['done'][$id] ?? 28);
    unset($n['done'][$id]);
    $body = $errno === 0 ? (string)curl_multi_getcontent($ch) : '';
    monCurlDone($ch, 'telegram');
    curl_multi_remove_handle($n['mh'], $ch);
    return [$body, $errno];
}

function tgRaw($token, $method, $data = [], $timeout = 20) {
    $hasFile = false;
    foreach ($data as $v) if ($v instanceof CURLFile) { $hasFile = true; break; }
    $url = TG_API_BASE . "/bot{$token}/{$method}";
    for ($try = 0; ; $try++) {
        $ch = tgHandle($url, $hasFile ? $data : http_build_query($data), $timeout, $hasFile);
        [$body, $errno] = tgRun($ch);
        $err = $errno ? curl_error($ch) : '';
        $ip = $errno === 0 ? (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP) : '';
        curl_close($ch);
        if ($errno === 0) { tgPin($ip); break; }
        if ($try === 0 && in_array($errno, [6, 7], true) && tgPin()['ip'] !== '') { tgPin(false); continue; }
        return ['ok' => false, 'description' => 'curl error: ' . ($err !== '' ? $err : 'code ' . $errno)];
    }
    $out = json_decode($body, true);
    return is_array($out) ? $out : ['ok' => false, 'description' => 'bad response'];
}

function tgAsync($token, $method, $data = []) {
    $data = emOut($method, $data);
    if (function_exists('__tgHook')) return __tgHook($token, $method, $data);
    $n = &tgNet();
    if (!$n['mh']) return tgRaw($token, $method, $data, 8);
    $ch = tgHandle(TG_API_BASE . "/bot{$token}/{$method}", http_build_query($data), 8);
    curl_multi_add_handle($n['mh'], $ch);
    $n['bg'][spl_object_id($ch)] = $ch;
    do { $st = curl_multi_exec($n['mh'], $running); } while ($st === CURLM_CALL_MULTI_PERFORM);
    if (!$n['shut']) { $n['shut'] = true; register_shutdown_function('tgDrain'); }
    return ['ok' => true, 'result' => true];
}

function tgDrain($max = 8.0) {
    $n = &tgNet();
    if (!$n['mh'] || !$n['bg']) return;
    tgPump([], microtime(true) + (float)$max);
}

function tgMulti($token, $method, array $items, $timeout = 6) {
    if (!$items) return [];
    $n = &tgNet();
    if (function_exists('__tgHook') || !$n['mh']) {
        $out = [];
        foreach ($items as $k => $d) $out[$k] = tg($token, $method, $d, $timeout);
        return $out;
    }

    $url = TG_API_BASE . "/bot{$token}/{$method}";
    $hs  = [];
    foreach ($items as $k => $d) {
        $ch = tgHandle($url, http_build_query(emOut($method, $d)), $timeout);
        curl_multi_add_handle($n['mh'], $ch);
        $hs[$k] = $ch;
    }
    tgPump(array_values($hs));

    $out = [];
    foreach ($hs as $k => $ch) {
        $id = spl_object_id($ch);
        $errno = (int)($n['done'][$id] ?? 28);
        unset($n['done'][$id]);
        $res = $errno === 0 ? curl_multi_getcontent($ch) : null;
        monCurlDone($ch, 'telegram');
        if ($errno === 0) tgPin((string)curl_getinfo($ch, CURLINFO_PRIMARY_IP));
        curl_multi_remove_handle($n['mh'], $ch);
        curl_close($ch);
        $j = is_string($res) ? json_decode($res, true) : null;
        $out[$k] = is_array($j) ? $j : ['ok' => false, 'description' => 'curl error'];
    }
    return $out;
}

function cleanRows($rows) {
    $out = [];
    foreach ($rows as $row) {
        if (empty($row)) continue;
        $line = [];
        foreach ($row as $btn) {
            if (!is_array($btn) || (string)($btn['text'] ?? '') === '') continue;
            $line[] = array_filter($btn, fn($v) => $v !== null && $v !== '');
        }
        if ($line) $out[] = $line;
    }
    return $out;
}

function inlineKb($rows) {
    $rows = cleanRows($rows);
    return $rows ? ['inline_keyboard' => $rows] : null;
}

function txMeta($cfg = null) {
    if (is_array($cfg) && is_array($cfg['meta'] ?? null)) return $cfg['meta'];
    return [
        'g' => '📢 پیام‌های داخلِ گروه',
        'p' => '⚠️ هشدارهای کلیکی',
        'b' => '🔘 برچسبِ دکمه‌ها',
    ];
}

function txSplit($cfg) {
    $out = array_fill_keys(array_keys(txMeta($cfg)), []);
    $popup = (array)($cfg['popup'] ?? []);
    $btns  = (array)($cfg['btns']  ?? []);
    foreach ((array)($cfg['keys'] ?? []) as $k) {
        if (isset($cfg['secOf']) && is_callable($cfg['secOf'])) { $out[$cfg['secOf']($k)][] = $k; continue; }
        if (in_array($k, $btns, true))       $out['b'][] = $k;
        elseif (in_array($k, $popup, true))  $out['p'][] = $k;
        else                                 $out['g'][] = $k;
    }
    return $out;
}

function txHome($cfg, $chatId, $msgId) {
    $split = txSplit($cfg);
    $t  = '✏️ <b>' . $cfg['title'] . '</b>';
    $rows = [];
    foreach (txMeta($cfg) as $sec => $name) {
        $n = count($split[$sec]);
        if (!$n) continue;
        $rows[] = [btnCb($name . ' — ' . $n, $cfg['cb'] . $sec . '0', 'admin')];
    }
    $rows[] = [btnCb(UT('back'), $cfg['back'], 'nav')];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function txList($cfg, $chatId, $msgId, $sec, $page = 0) {
    $meta = txMeta($cfg);
    if (!isset($meta[$sec])) { txHome($cfg, $chatId, $msgId); return; }
    $keys = txSplit($cfg)[$sec];
    if (!$keys) { txHome($cfg, $chatId, $msgId); return; }

    $per   = 10;
    $tot   = max(1, (int)ceil(count($keys) / $per));
    $page  = max(0, min($tot - 1, (int)$page));
    $slice = array_slice($keys, $page * $per, $per);

    $label = $cfg['label'];
    $value = $cfg['value'];

    $t  = $meta[$sec] . ' — <b>' . $cfg['title'] . "</b>\n";
    if ($tot > 1) $t .= 'صفحه ' . ($page + 1) . " از {$tot}\n";
    $t .= "\n";

    $rows = [];
    foreach ($slice as $k) {
        $v = trim(str_replace("\n", ' ', strip_tags((string)$value($k))));
        $t .= '• <b>' . h($label($k)) . '</b>: <code>' . h(mb_substr($v, 0, 34)) . "</code>\n";
        $rows[] = [btnCb($label($k), $cfg['edit'] . $k, 'admin')];
    }
    $nav = [];
    if ($page > 0)        $nav[] = btnCb('◀️', $cfg['cb'] . $sec . ($page - 1), 'nav');
    if ($page < $tot - 1) $nav[] = btnCb('▶️', $cfg['cb'] . $sec . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb('🗂 دسته‌ها', $cfg['cb'] . 'home', 'nav'), btnCb(UT('back'), $cfg['back'], 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($rows));
}

function txRoute($cfg, $data, $chatId, $msgId, $cbId) {
    $pre = (string)$cfg['cb'];
    if (!str_starts_with((string)$data, $pre)) return false;
    $rest = substr((string)$data, strlen($pre));
    if ($rest === 'home') { answerCb(BOT_TOKEN, $cbId); txHome($cfg, $chatId, $msgId); return true; }
    if (preg_match('/^([a-z])(\d+)$/', $rest, $m)) {
        answerCb(BOT_TOKEN, $cbId); txList($cfg, $chatId, $msgId, $m[1], (int)$m[2]); return true;
    }
    if (preg_match('/^\d+$/', $rest)) { answerCb(BOT_TOKEN, $cbId); txHome($cfg, $chatId, $msgId); return true; }
    return false;
}

function menuKb($rows) {
    $rows = cleanRows($rows);
    if (!$rows) return ['remove_keyboard' => true];
    $kb = [
        'keyboard' => $rows,
        'resize_keyboard' => true,
        'input_field_placeholder' => cfg()['ui']['placeholder'] ?? '',
    ];
    if (!empty(cfg()['ui']['persistent'])) $kb['is_persistent'] = true;
    if ($kb['input_field_placeholder'] === '') unset($kb['input_field_placeholder']);
    return $kb;
}

function isStyleError($res) {
    $d = strtolower($res['description'] ?? '');
    if ($d === '') return false;
    return str_contains($d, 'style')
        || str_contains($d, 'icon_custom_emoji_id')
        || str_contains($d, 'button_text_empty')
        || str_contains($d, 'button text is empty');
}

if (!defined('BTN_BLANK')) define('BTN_BLANK', "\u{2060}");

function textIsOnlyEmoji($t) {
    $t = trim((string)$t);
    return $t !== '' && !preg_match('/[\p{L}\p{N}]/u', $t);
}

function kbHideDupEmoji($markup) {
    if (!is_array($markup)) return $markup;
    foreach (['inline_keyboard', 'keyboard'] as $k) {
        if (empty($markup[$k]) || !is_array($markup[$k])) continue;
        foreach ($markup[$k] as $i => $row) {
            if (!is_array($row)) continue;
            foreach ($row as $j => $btn) {
                if (!is_array($btn)) continue;
                if (trim((string)($btn['icon_custom_emoji_id'] ?? '')) === '') continue;
                if (!textIsOnlyEmoji($btn['text'] ?? '')) continue;
                $markup[$k][$i][$j]['text'] = BTN_BLANK;
            }
        }
    }
    return $markup;
}

function kbJson($markup) {
    return json_encode(kbHideDupEmoji($markup));
}

function sendMsg($token, $chatId, $text, $markup = null, $extra = []) {
    $data = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => 'true',
    ], $extra);
    if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : kbJson($markup);
    $res = tg($token, 'sendMessage', $data);
    if (empty($res['ok']) && $markup && !is_string($markup) && isStyleError($res)) {
        $data['reply_markup'] = json_encode(stripStyles($markup));
        $res = tg($token, 'sendMessage', $data);
    }
    return $res;
}

function editMsg($token, $chatId, $msgId, $text, $markup = null) {
    $data = [
        'chat_id' => $chatId, 'message_id' => $msgId, 'text' => $text,
        'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true',
    ];
    if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : kbJson($markup);
    $res = tg($token, 'editMessageText', $data);
    if (empty($res['ok']) && $markup && !is_string($markup) && isStyleError($res)) {
        $data['reply_markup'] = json_encode(stripStyles($markup));
        $res = tg($token, 'editMessageText', $data);
    }
    if (empty($res['ok']) && !isNotModified($res)) sendMsg($token, $chatId, $text, $markup);
    return $res;
}

function isNotModified($res) {
    $d = strtolower((string)($res['description'] ?? ''));
    return $d !== '' && str_contains($d, 'not modified');
}

function editKb($token, $chatId, $msgId, $markup = null) {
    $data = ['chat_id' => $chatId, 'message_id' => $msgId];
    if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : kbJson($markup);
    $res = tg($token, 'editMessageReplyMarkup', $data);
    if (empty($res['ok']) && $markup && !is_string($markup) && isStyleError($res)) {
        $data['reply_markup'] = json_encode(stripStyles($markup));
        $res = tg($token, 'editMessageReplyMarkup', $data);
    }
    return $res;
}

function plainAlert($text) {
    $t = (string)$text;
    if ($t === '') return '';
    $t = preg_replace('~<br\s*/?>|</(p|div|blockquote|pre)>~i', "\n", $t);
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t]+/u", ' ', $t);
    $t = preg_replace("/\n{3,}/u", "\n\n", $t);
    $t = trim($t);
    return mb_strlen($t, 'UTF-8') > 200 ? mb_substr($t, 0, 199, 'UTF-8') . '…' : $t;
}

function answerCb($token, $cbId, $text = '', $alert = false) {
    return tgAsync($token, 'answerCallbackQuery', [
        'callback_query_id' => $cbId, 'text' => plainAlert($text),
        'show_alert' => $alert ? 'true' : 'false',
    ]);
}

function delMsg($token, $chatId, $msgId) {
    return tgAsync($token, 'deleteMessage', ['chat_id' => $chatId, 'message_id' => $msgId]);
}

function sendFile($token, $chatId, $type, $fileId, $caption = '', $protect = false, $markup = null) {
    $map = [
        'document' => ['sendDocument', 'document'], 'photo' => ['sendPhoto', 'photo'],
        'video' => ['sendVideo', 'video'],          'audio' => ['sendAudio', 'audio'],
        'voice' => ['sendVoice', 'voice'],          'animation' => ['sendAnimation', 'animation'],
        'sticker' => ['sendSticker', 'sticker'],    'video_note' => ['sendVideoNote', 'video_note'],
    ];
    [$method, $field] = $map[$type] ?? $map['document'];
    $data = ['chat_id' => $chatId, $field => $fileId];
    if ($caption !== '' && !in_array($type, ['sticker', 'video_note'], true)) {
        $data['caption'] = $caption;
        $data['parse_mode'] = 'HTML';
    }
    if ($protect) $data['protect_content'] = 'true';
    if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : kbJson($markup);
    $res = tg($token, $method, $data);
    if (empty($res['ok']) && $markup && !is_string($markup) && isStyleError($res)) {
        $data['reply_markup'] = json_encode(stripStyles($markup));
        $res = tg($token, $method, $data);
    }
    return $res;
}

function entitiesToHtml($text, $entities = null) {
    if ($text === null || $text === '') return '';
    if (empty($entities)) return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

    $u16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    $len = (int)(strlen($u16) / 2);

    $open = array_fill(0, $len + 1, []);
    $close = array_fill(0, $len + 1, []);

    usort($entities, function ($a, $b) {
        if ($a['offset'] !== $b['offset']) return $a['offset'] <=> $b['offset'];
        return $b['length'] <=> $a['length'];
    });

    foreach ($entities as $e) {
        $o = (int)$e['offset'];
        $l = (int)$e['length'];
        if ($o < 0 || $l <= 0 || $o + $l > $len) continue;

        $ot = null; $ct = null;
        switch ($e['type']) {
            case 'bold':          $ot = '<b>';  $ct = '</b>';  break;
            case 'italic':        $ot = '<i>';  $ct = '</i>';  break;
            case 'underline':     $ot = '<u>';  $ct = '</u>';  break;
            case 'strikethrough': $ot = '<s>';  $ct = '</s>';  break;
            case 'spoiler':       $ot = '<tg-spoiler>'; $ct = '</tg-spoiler>'; break;
            case 'code':          $ot = '<code>'; $ct = '</code>'; break;
            case 'pre':
                $lang = !empty($e['language']) ? ' class="language-' . htmlspecialchars($e['language'], ENT_QUOTES, 'UTF-8') . '"' : '';
                $ot = '<pre><code' . $lang . '>'; $ct = '</code></pre>'; break;
            case 'blockquote':            $ot = '<blockquote>'; $ct = '</blockquote>'; break;
            case 'expandable_blockquote': $ot = '<blockquote expandable>'; $ct = '</blockquote>'; break;
            case 'text_link':
                if (empty($e['url'])) break;
                $ot = '<a href="' . htmlspecialchars($e['url'], ENT_QUOTES, 'UTF-8') . '">'; $ct = '</a>'; break;
            case 'text_mention':
                if (empty($e['user']['id'])) break;
                $ot = '<a href="tg://user?id=' . (int)$e['user']['id'] . '">'; $ct = '</a>'; break;
            case 'custom_emoji':
                if (empty($e['custom_emoji_id'])) break;
                $ot = '<tg-emoji emoji-id="' . htmlspecialchars($e['custom_emoji_id'], ENT_QUOTES, 'UTF-8') . '">';
                $ct = '</tg-emoji>'; break;
        }
        if ($ot === null) continue;
        $open[$o][]  = $ot;
        array_unshift($close[$o + $l], $ct);
    }

    $out = '';
    for ($i = 0; $i < $len; $i++) {
        foreach ($close[$i] as $t) $out .= $t;
        foreach ($open[$i]  as $t) $out .= $t;
        $ch = mb_convert_encoding(substr($u16, $i * 2, 2), 'UTF-8', 'UTF-16LE');
        $cp = unpack('v', substr($u16, $i * 2, 2))[1];
        if ($cp >= 0xD800 && $cp <= 0xDBFF && $i + 1 < $len) {
            $ch = mb_convert_encoding(substr($u16, $i * 2, 4), 'UTF-8', 'UTF-16LE');
            $i++;
        }
        $out .= htmlspecialchars($ch, ENT_NOQUOTES, 'UTF-8');
    }
    foreach ($close[$len] as $t) $out .= $t;
    return $out;
}

function msgHtml($msg) {
    $t = $msg['text'] ?? $msg['caption'] ?? '';
    $e = $msg['entities'] ?? $msg['caption_entities'] ?? null;
    return entitiesToHtml($t, $e);
}

function btnLabelEmoji($raw) {
    $raw = (string)$raw;
    $id  = '';
    if (preg_match('/<tg-emoji\s+emoji-id\s*=\s*["\']?(\d+)["\']?\s*>/i', $raw, $m))
        $id = $m[1];
    $txt = trim(preg_replace('/\s+/u', ' ', strip_tags(preg_replace('#<tg-emoji\b[^>]*>.*?</tg-emoji>#isu', ' ', $raw))));
    if ($txt === '') $txt = trim(strip_tags($raw));
    if ($txt === '') $txt = ' ';
    return [$txt, $id];
}

function btnApplyLabel(array $b, $raw, $fallbackIconId = '') {
    [$txt, $id] = btnLabelEmoji($raw);
    $b['text'] = $txt;
    $id = $id !== '' ? $id : trim((string)$fallbackIconId);
    if ($id !== '') $b['icon_custom_emoji_id'] = $id;
    return $b;
}

function customEmojiIds($msg) {
    $out = [];
    foreach (($msg['entities'] ?? $msg['caption_entities'] ?? []) as $e) {
        if (($e['type'] ?? '') !== 'custom_emoji') continue;
        $id = (string)($e['custom_emoji_id'] ?? '');
        if ($id !== '' && !in_array($id, $out, true)) $out[] = $id;
    }
    return $out;
}

function textWithoutCustomEmoji($msg) {
    $text = (string)($msg['text'] ?? $msg['caption'] ?? '');
    $cuts = [];
    foreach (($msg['entities'] ?? $msg['caption_entities'] ?? []) as $e) {
        if (($e['type'] ?? '') !== 'custom_emoji') continue;
        $cuts[] = [(int)($e['offset'] ?? 0), (int)($e['length'] ?? 0)];
    }
    if (!$cuts) return trim($text);

    usort($cuts, fn($a, $b) => $b[0] <=> $a[0]);
    $u16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    foreach ($cuts as [$off, $len]) {
        $n = strlen($u16) >> 1;
        if ($len <= 0 || $off < 0 || $off >= $n) continue;
        $u16 = substr($u16, 0, $off * 2) . substr($u16, min($off + $len, $n) * 2);
    }
    $out = mb_convert_encoding($u16, 'UTF-8', 'UTF-16LE');
    return trim(preg_replace('/\s{2,}/u', ' ', $out));
}

function styleMap() {
    return [
        'none'    => '— بدون رنگ',
        'primary' => '🔵 آبی',
        'success' => '🟢 سبز',
        'danger'  => '🔴 قرمز',
    ];
}
function isStyle($s) { return in_array($s, ['primary', 'success', 'danger'], true); }

function gs($role) {
    if (function_exists('panelMode') && panelMode()) return null;
    if ($role === 'admin') return null;
    $c = cfg()['glass_colors'][$role] ?? 'none';
    return isStyle($c) ? $c : null;
}

function btnCb($label, $data, $role = null, $style = null) {
    if (function_exists('panelMode') && panelMode()) $label = panelLabel($label);
    $b = ['text' => $label, 'callback_data' => $data];
    $st = $style ?: ($role ? gs($role) : null);
    if (isStyle($st)) $b['style'] = $st;
    return $b;
}
function btnUrl($label, $url, $role = null, $style = null) {
    if (function_exists('panelMode') && panelMode()) $label = panelLabel($label);
    $b = ['text' => $label, 'url' => $url];
    $st = $style ?: ($role ? gs($role) : null);
    if (isStyle($st)) $b['style'] = $st;
    return $b;
}

function stripStyles($markup) {
    if (!is_array($markup)) return $markup;
    foreach (['inline_keyboard', 'keyboard'] as $k) {
        if (empty($markup[$k])) continue;
        foreach ($markup[$k] as $i => $row)
            foreach ($row as $j => $btn)
                unset($markup[$k][$i][$j]['style'], $markup[$k][$i][$j]['icon_custom_emoji_id']);
    }
    return $markup;
}

function defaultConfig() {
    return [
        'ui' => ['mode' => 'menu', 'layout' => '1,2,1,2,1',
                 'persistent' => false,
                 'placeholder' => 'یک گزینه را انتخاب کنید…'],

        'gateway' => [
            'on'        => false,
            'provider'  => 'oxapay',
            'api_key'   => '',
            'ipn_secret'=> '',
            'base_url'  => '',
            'coin'      => 'USDT',
            'network'   => 'TRC20',
            'rate'      => 0,
            'expire'    => 30,
            'min'       => 50000,
            'custom_url'=> '',
            'mode'      => 'address',
            'underpaid' => 1,
        ],

        'irpay' => [
            'on' => false, 'provider' => 'zarinpal', 'merchant' => '', 'sandbox' => false,
            'min' => 10000, 'max' => 0, 'kyc' => true, 'kyc_limit' => 500000, 'kyc_mode' => 'docs', 'ir_only' => true,
            'kyc_guide' => '',
            'oauth_id' => '', 'oauth_secret' => '', 'otp_ch' => 'sms',
            'card_check' => false, 'desc' => 'شارژ کیف پول',
        ],
        'pay_page' => ['th' => 'num', 't' => []],

        'topup_btns' => [
            'crypto' => ['text' => 'پرداخت با ارز دیجیتال', 'color' => 'success', 'icon' => ''],
            'iran'   => ['text' => 'درگاه پرداخت ایرانی',   'color' => 'primary', 'icon' => ''],
            'phone'  => ['text' => 'ارسال شماره',           'color' => 'success', 'icon' => ''],
            'open'   => ['text' => 'ورود به درگاه پرداخت',  'color' => 'success', 'icon' => ''],
            'copy'   => ['text' => 'کپی آدرس ولت',          'color' => 'primary', 'icon' => '', 'on' => false],
            'check'  => ['text' => 'بررسی پرداخت',          'color' => 'primary', 'icon' => '', 'on' => true],
            'pay'    => ['text' => 'پرداخت آنلاین',          'color' => 'success', 'icon' => ''],
            'kyc'    => ['text' => 'احراز هویت',            'color' => 'primary', 'icon' => ''],
            'less'   => ['text' => 'مبلغ دیگر',             'color' => 'primary', 'icon' => ''],
            'back'   => ['text' => 'روش دیگر',              'color' => 'primary', 'icon' => ''],
            'resend' => ['text' => 'ارسال دوباره‌ی کد',     'color' => 'primary', 'icon' => ''],
            'cancel' => ['text' => 'انصراف',                'color' => 'danger',  'icon' => ''],
        ],

        'join' => [
            'on'       => false,
            'channels' => [],
            'text'     => "<b>عضویت در کانال‌ها</b>\n\n{channels}",
            'btn'      => ['text' => 'عضو شدم', 'color' => 'success', 'icon' => ''],
        ],

        'buttons' => [
            'buy'      => ['text' => 'ثبت سفارش',                       'color' => 'success', 'icon' => '', 'row' => 1, 'order' => 1, 'on' => true, 'action' => ''],
            'account'  => ['text' => 'حساب کاربری',                     'color' => 'primary', 'icon' => '', 'row' => 2, 'order' => 2, 'on' => true, 'action' => ''],
            'topup'    => ['text' => 'افزایش موجودی',                   'color' => 'primary', 'icon' => '', 'row' => 2, 'order' => 3, 'on' => true, 'action' => ''],
            'referral' => ['text' => 'زیر مجموعه گیری',                 'color' => 'danger',  'icon' => '', 'row' => 3, 'order' => 4, 'on' => true, 'action' => ''],
            'orders'   => ['text' => 'پیگیری سفارش',                    'color' => 'primary', 'icon' => '', 'row' => 4, 'order' => 5, 'on' => true, 'action' => ''],
            'support'  => ['text' => 'پشتیبانی',                        'color' => 'primary', 'icon' => '', 'row' => 4, 'order' => 6, 'on' => true, 'action' => ''],
            'trust'    => ['text' => 'چطوری میتوانم به شما اعتماد کنم', 'color' => 'danger',  'icon' => '', 'row' => 5, 'order' => 7, 'on' => true, 'action' => ''],
        ],

        'ui_texts' => [
            'back'      => 'بازگشت',
            'home'      => 'منوی اصلی',
            'cancel'    => 'انصراف',
            'confirm'   => 'تایید',
            'reject'    => 'رد',
            'panel'     => 'پنل',
            'topup'     => 'افزایش موجودی',
            'my_orders' => 'پیگیری سفارش',
            'trk_refresh' => 'به‌روزرسانی وضعیت',
            'coupon'    => 'وارد کردن کد تخفیف',
            'open_app'  => 'شماره مجازی تلگرام',
            'open'      => 'باز کردن',
            'open_tgs'  => 'خدمات تلگرام',
            'open_igs'  => 'خدمات اینستاگرام',
        ],

        'ui_icons' => [],
        'ui_colors' => ['open_tgs' => 'primary', 'open_igs' => 'danger'],

        'glass_colors' => [
            'buy'     => 'success',
            'confirm' => 'success',
            'cancel'  => 'danger',
            'reject'  => 'danger',
            'nav'     => 'primary',
            'info'    => 'primary',
            'admin'   => 'primary',
            'link'    => 'success',
        ],

        'texts' => [
            'welcome'      => "سلام {name} عزیز\nبه ربات خدمات مجازی خوش آمدید.\n\nخدمات تلگرام، خدمات اینستاگرام و شماره مجازی تلگرام.",
            'account'      => "<b>حساب کاربری</b>\n\nآیدی: <code>{id}</code>\nنام: {name}\nیوزرنیم: {username}\n\nموجودی: <b>{balance}</b> تومان\nتعداد خریدها: {orders}\nزیرمجموعه: {referrals}\nدرآمد معرفی: {ref_earned} تومان\n\nعضویت: {joined}",
            'trust'        => "<b>چرا می‌توانید به ما اعتماد کنید؟</b>\n\nسال‌ها سابقه فعالیت\nتحویل آنی و خودکار\nپشتیبانی ۲۴ ساعته\nضمانت بازگشت وجه\nهزاران مشتری راضی",
            'support'      => "<b>پشتیبانی</b>",
            'track_ask'    => "<b>پیگیری سفارش</b>\nکد پیگیری\n{recent}",
            'track_recent' => "\nسفارش‌های اخیر:\n{list}",
            'track_bad'    => "سفارشی با کد <code>{code}</code> پیدا نشد.",
            'order_status' => "<b>وضعیت سفارش</b>\n\nکد پیگیری: <code>{code}</code>\nوضعیت: <b>{status}</b>\nمحصول: {product}\n<blockquote>لینک: {link}</blockquote>\n<blockquote>تعداد: {qty}</blockquote>\n<blockquote>شماره: <code>{phone}</code></blockquote>\n<blockquote>کد ورود: <code>{sms}</code></blockquote>\n\nپیشرفت سفارش:\n{progress}\n\nمبلغ: {amount} {currency}\nزمان ثبت: {created}",
            'referral'     => "<b>داشبورد زیرمجموعه</b>\n\nپورسانت: <b>{percent}%</b>\nتعداد زیرمجموعه: <b>{referrals}</b>\nکل درآمد: <b>{ref_earned}</b> تومان\nقابل برداشت: <b>{ref_pending}</b> تومان",
            'referral_link'=> "<b>لینک دعوت شما</b>\n\n{link}",
            'referral_share'=> "با این لینک وارد ربات شو و خدمات تلگرام، اینستاگرام و شماره مجازی بگیر",
            'referral_wallet_none' => "<b>برداشت پورسانت</b>\n\nهنوز چیزی برای برداشت جمع نشده.",
            'referral_wallet_ok'   => "<b>{amount}</b> تومان به کیف‌پولِ شما (داخلِ ربات) واریز شد.",
            'referral_hist_head' => "<b>تاریخچه‌ی پورسانت</b>\n",
            'referral_hist_row'  => "{date} — از خرید <b>{amount}</b> تومانی: <b>+{commission}</b> تومان\n",
            'referral_hist_none' => "هنوز پورسانتی ثبت نشده است.",
            'topup_off'    => "شارژ حساب فعلا در دسترس نیست.",
            'topup_ok'     => "<b>حساب شما شارژ شد</b>\n\n<blockquote>مبلغ: <b>{amount}</b> تومان\nموجودی: <b>{balance}</b> تومان</blockquote>",
            'shop'         => "<b>ثبت سفارش</b>\n\nبخشِ موردنظرتان را از دکمه‌های زیر باز کنید.\n\nموجودی شما: <b>{balance}</b> تومان",
            'shop_closed'  => "فروش موقتا بسته است",
            'coupon_ask'   => "<b>کد تخفیف</b>\n\nکدِ فعال: <code>{code}</code> ({value})",
            'coupon_ok'    => "<b>کد تخفیف فعال شد</b>\n\nکد: <code>{code}</code>\nتخفیف: <b>{value}</b>\nاعتبار تا: {exp}",
            'coupon_bad'   => "{reason}",
            'no_balance'   => "موجودی شما کافی نیست.\nموجودی فعلی: {balance} تومان",
            'banned'       => "دسترسی شما مسدود شده است.",

            'sup_ticket'   => "<b>ارتباط غیر مستقیم</b>\n\nپیام خود را ارسال کنید، ادمین بررسی می‌کند.",
            'sup_sent'     => "پیام شما برای پشتیبانی ارسال شد.",

            'topup_choose'         => "<b>افزایش موجودی</b>\n\n<blockquote>موجودیِ فعلی: <b>{balance}</b> تومان</blockquote>",
            'topup_crypto_amount'  => "<b>پرداخت با ارز دیجیتال</b>\n\nمبلغ (تومان)\nحداقل: <b>{min}</b> تومان",
            'topup_crypto_invoice' => "<b>درگاهِ پرداختِ ارز دیجیتال آماده است</b>\n\n<blockquote>مبلغِ شارژ: <b>{amount}</b> تومان\nمهلتِ پرداخت: <b>{expire}</b> دقیقه\nکدِ پیگیری: {id}</blockquote>",
            'topup_crypto_invoice_addr' => "<b>فاکتورِ ارز دیجیتال</b>\n\n<blockquote>مبلغ: <b>{amount}</b> تومان\nمقدارِ واریز: {crypto} <b>{coin}</b>\nشبکه: <b>{network}</b></blockquote>\n<b>آدرسِ ولت</b>\n{address}\n\nمهلت: <b>{expire}</b> دقیقه · {id}",
            'topup_gw_down'        => "درگاهِ ارز دیجیتال الان جواب نمی‌دهد.",
            'topup_ir_phone'       => "<b>درگاه پرداخت ایرانی</b>\n\n<blockquote>شماره‌ی موبایلِ صاحبِ کارتِ بانکی</blockquote>",
            'topup_ir_phone_ok'    => "شماره‌ی <b>{phone}</b> ثبت شد.",
            'topup_ir_phone_bad'   => "شماره‌ی موبایلِ ایرانی معتبر نیست.",
            'topup_ir_amount'      => "<b>درگاه پرداخت ایرانی</b>\n\nشماره: <b>{phone}</b>\nمبلغ (تومان)\nحداقل: <b>{min}</b> تومان",
            'topup_ir_kyc_need'    => "<b>احراز هویت لازم است</b>\n\n<blockquote>بیش از <b>{limit}</b> تومان: یک عکس از متنِ دست‌نوشته + امضا + کارتِ ملی + کارتِ بانکی</blockquote>",
            'topup_ir_kyc_need_phone' => "<b>احراز هویت لازم است</b>\n\n<blockquote>بیش از <b>{limit}</b> تومان: احراز با شماره‌ی موبایل</blockquote>",
            'kyc_guide'            => "<b>احراز هویت</b>\n\nمتنِ دست‌نوشته · امضا · کارتِ ملی · کارتِ بانکی",
            'kyc_note_ask'         => "<b>احراز هویت — یک عکس، فقط یک‌بار</b>\n\nدر دفترتان با خطِ خودتان این را بنویسید:\n<blockquote>اینجانب ……………… (نام و نام خانوادگی) در تاریخ {date} به مبلغ {amount} تومان از ربات نامبیکس خدمات خریداری می‌کنم.</blockquote>\n<b>امضا</b> · <b>کارتِ ملی</b> · <b>کارتِ بانکی</b>",
            'kyc_photo_bad'        => "فقط <b>عکس</b> پذیرفته می‌شود.",
            'kyc_otp_ask'          => "<b>احراز هویت با شماره‌ی موبایل</b>\n\nکدِ تاییدِ یک‌بارمصرفِ <b>زرین‌پال</b> برای <b>{phone}</b> فرستاده شد ({channel}).\n{ussd}",
            'kyc_otp_bad'          => "{error}",
            'kyc_otp_fail'         => "کدِ تایید فرستاده نشد.\n<code>{error}</code>",
            'kyc_phone_confirm'    => "<b>احراز هویت</b>\n\n<blockquote>شماره: <b>{phone}</b></blockquote>",
            'topup_ir_confirm'     => "<b>تاییدِ پرداخت</b>\n\n<blockquote>مبلغ: <b>{amount}</b> تومان\nشماره: {phone}\nکدِ پیگیری: {id}</blockquote>",
            'topup_ir_down'        => "درگاهِ پرداختِ ایرانی الان جواب نمی‌دهد.",
            'topup_paid'           => "<b>پرداخت انجام شد</b>\n\n<blockquote>مبلغ: <b>{amount}</b> تومان\nموجودی: <b>{balance}</b> تومان\n{id}</blockquote>",
            'kyc_code_ask'         => "<b>احراز هویت — مرحله‌ی ۱ از ۲</b>\n\nکدِ ملی",
            'kyc_code_bad'         => "کدِ ملی درست نیست.",
            'kyc_photo_ask'        => "<b>احراز هویت — مرحله‌ی ۲ از ۲</b>\n\nعکسِ <b>کارتِ ملی</b>",
            'kyc_sent'             => "درخواستِ احراز هویتِ شما ثبت شد.",
            'kyc_pending'          => "احراز هویتِ شما در حالِ بررسی است.",
            'kyc_ok'               => "<b>احراز هویتِ شما تایید شد</b>",
            'kyc_no'               => "احراز هویتِ شما تایید نشد.\n{note}",
        ],

        'support_main' => [
            'direct' => ['text' => 'ارتباط مستقیم', 'color' => 'success',
                         'icon' => '', 'value' => ''],
            'indirect' => ['text' => 'ارتباط غیر مستقیم', 'color' => 'primary',
                           'icon' => '', 'value' => ''],
            'group' => ['text' => 'گروه ما', 'color' => 'primary',
                        'icon' => '', 'value' => ''],
        ],

        'trust_btn' => ['text' => 'ورود به کانال گزارشات', 'url' => '', 'color' => 'primary', 'icon' => ''],

        'referral' => [
            'on' => true, 'percent' => 2,
            'btns' => [
                'link'   => ['text' => 'ساخت لینک دعوت',   'color' => 'success', 'icon' => ''],
                'hist'   => ['text' => 'تاریخچه پورسانت', 'color' => 'primary', 'icon' => ''],
                'wallet' => ['text' => 'برداشت پورسانت', 'color' => 'primary', 'icon' => ''],
                'share'  => ['text' => 'ارسال برای دوستان', 'color' => 'success', 'icon' => ''],
            ],
        ],

        'topup_rules' => [
            'on'   => true,
            'text' => "<b>قوانین افزایش موجودی</b>\n\nبا ادامه دادن، شرایط زیر را می‌پذیرید:\n\n" .
                      "- موجودیِ شارژشده فقط برای خرید از همین ربات قابل استفاده است.\n" .
                      "- درست واردکردن مبلغ و ارسال رسیدِ صحیح با شماست.\n" .
                      "- بازگشت وجه فقط طبق قوانینِ پشتیبانی انجام می‌شود.",
            'btns' => [
                'ok'     => ['text' => 'تایید',       'color' => 'success', 'icon' => ''],
                'cancel' => ['text' => 'لغو',         'color' => 'danger',  'icon' => ''],
                'done'   => ['text' => 'تایید شده',  'color' => 'primary', 'icon' => ''],
            ],
        ],
    ];
}

function cfg($refresh = false) {
    static $c = null;
    if ($c === null || $refresh) {
        $saved = load('config');
        $c = array_replace_recursive(defaultConfig(), is_array($saved) ? $saved : []);
        if (!empty($saved['buttons']))         $c['buttons'] = array_replace_recursive(defaultConfig()['buttons'], $saved['buttons']);
    }
    return $c;
}

function cfgSet(callable $fn) {
    mutate('config', function (&$c) use ($fn) {
        if (!is_array($c) || !$c) $c = defaultConfig();
        $fn($c);
    });
    cfg(true);

    if (function_exists('maForget')) maForget();
}

function UI($key) { return cfg()['ui_icons'][$key] ?? ''; }

function UC($key) {
    $c = cfg()['ui_colors'][$key] ?? '';
    return isStyle($c) ? $c : null;
}

function btnUI($key, $data, $role = null) {
    $b = ['text' => UT($key), 'callback_data' => $data];
    $st = UC($key) ?: ($role ? gs($role) : null);
    if (isStyle($st)) $b['style'] = $st;
    $ic = (string)UI($key);
    if ($ic !== '') $b['icon_custom_emoji_id'] = $ic;
    return $b;
}

function UT($key) {
    $d = defaultConfig()['ui_texts'];
    $v = cfg()['ui_texts'][$key] ?? ($d[$key] ?? $key);
    return $v !== '' ? $v : ($d[$key] ?? $key);
}

function T($key, $vars = []) {
    $t = cfg()['texts'][$key] ?? '';
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function tplLegacy($t) {
    $x = cfg()['texts'] ?? [];
    foreach (['{link_line}' => ['track_link_line', 'track_phone_line'], '{qty_line}' => ['track_qty_line', 'track_sms_line'],
              '{active}' => ['coupon_active_line'], '{exp_line}' => ['coupon_exp_line']] as $ph => $keys) {
        if (strpos($t, $ph) === false) continue;
        $rep = '';
        foreach ($keys as $k) $rep .= (string)($x[$k] ?? '');
        $t = str_replace($ph, $rep, $t);
    }
    return str_replace(['{perday_line}', '{eta_line}', '{approved_line}', '{hint}'], '', $t);
}

function tplTags($s) {
    preg_match_all('#</?([a-z][a-z0-9-]*)\b[^>]*>#i', $s, $m, PREG_SET_ORDER);
    $open = []; $close = '';
    foreach ($m as $t) {
        if ($t[0][1] === '/') {
            if ($open && end($open)[0] === strtolower($t[1])) array_pop($open); else $close .= $t[0];
        } else $open[] = [strtolower($t[1]), $t[0]];
    }
    return [$close, implode('', array_column($open, 1))];
}

function TL($key, array $vars = []) {
    return tplFill(tplLegacy((string)(cfg()['texts'][$key] ?? '')), $vars);
}

function tplFill($tpl, array $vars = []) {
    $lines = explode("\n", (string)$tpl);
    $out = []; $carry = '';
    foreach ($lines as $ln) {
        if (preg_match_all('/\{([a-z_]+)\}/', $ln, $m)) {
            $any = false;
            foreach ($m[1] as $k) if (trim((string)($vars[$k] ?? '')) !== '') { $any = true; break; }
            if (!$any) {
                [$close, $open] = tplTags($ln);
                if ($close !== '' && $out) $out[count($out) - 1] .= $close;
                $carry .= $open;
                continue;
            }
        }
        $out[] = $carry . $ln;
        $carry = '';
    }
    $t = implode("\n", $out) . $carry;
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function btnLabel($b) {
    return trim((string)($b['text'] ?? ''));
}

function btnTextIn($msg) {
    $plain = trim((string)($msg['text'] ?? ''));
    $ids = customEmojiIds($msg);
    $txt = $ids ? textWithoutCustomEmoji($msg) : $plain;
    return [trim(emPlain($txt, false)), (string)($ids[0] ?? '')];
}

function activeButtons() {
    $list = [];
    foreach (cfg()['buttons'] as $id => $b) {
        if (empty($b['on'])) continue;
        $b['id'] = $id;
        $list[] = $b;
    }
    usort($list, fn($x, $y) => ((int)($x['order'] ?? 99)) <=> ((int)($y['order'] ?? 99)));
    return $list;
}

function parseLayout($str) {
    $out = [];
    foreach (preg_split('/[^0-9]+/', (string)$str) as $n) {
        if ($n === '') continue;
        $n = (int)$n;
        if ($n >= 1 && $n <= 8) $out[] = $n;
    }
    return $out;
}

function layoutRows(array $items, $layoutStr) {
    $layout = parseLayout($layoutStr);
    $rows = [];
    $i = 0; $n = count($items); $k = 0;
    while ($i < $n) {
        $take = ($k < count($layout)) ? $layout[$k] : 1;
        $rows[] = array_slice($items, $i, $take);
        $i += $take; $k++;
    }
    return $rows;
}

function applyLayoutToRows($layoutStr) {
    $items = activeButtons();
    $rows = layoutRows($items, $layoutStr);
    $map = [];
    foreach ($rows as $r => $line) foreach ($line as $b) $map[$b['id']] = $r + 1;
    return $map;
}

function menuRows() {
    $items = activeButtons();
    $hasRows = false;
    foreach ($items as $b) if (!empty($b['row'])) { $hasRows = true; break; }
    if (!$hasRows) return layoutRows($items, cfg()['ui']['layout'] ?? '');

    $byRow = [];
    foreach ($items as $b) $byRow[(int)(($b['row'] ?? 0) ?: 99)][] = $b;
    ksort($byRow);
    return array_values($byRow);
}

function mainKeyboard() {
    $c = cfg();
    $glass = ($c['ui']['mode'] === 'glass');
    $rows = menuRows();

    $out = [];
    foreach ($rows as $r) {
        $line = [];
        foreach ($r as $b) {
            $btn = ['text' => btnLabel($b)];
            if ($glass) $btn['callback_data'] = 'menu_' . $b['id'];
            if (isStyle($b['color'] ?? '')) $btn['style'] = $b['color'];
            if (!empty($b['icon'])) $btn['icon_custom_emoji_id'] = (string)$b['icon'];
            $line[] = $btn;
        }
        if ($line) $out[] = $line;
    }
    return $glass ? inlineKb($out) : menuKb($out);
}

function findMenuAction($text, $exact = false) {
    $text = trim($text);
    if ($text === '') return null;
    $bare = trim(emPlain($text, false));
    foreach (cfg()['buttons'] as $id => $b) {
        if (empty($b['on'])) continue;
        $l = btnLabel($b);
        if ($l === $text) return $id;
        if (!$exact && ($l === $bare || trim(emPlain($l, false)) === $bare)) return $id;
    }
    return null;
}

function usersDbPath() { return DATA_DIR . '/users.sqlite'; }

function usersDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;

    $path = usersDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fresh = !is_file($path);

    try {
        $db = nbRawOpen($path);
    } catch (Throwable $e) {
        error_log('[shop-bot] users.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, data TEXT NOT NULL)');
    @$db->exec("CREATE INDEX IF NOT EXISTS users_un ON users (lower(json_extract(data,'$.username')))");
    @$db->exec("CREATE INDEX IF NOT EXISTS users_rc ON users (json_extract(data,'$.ref_count'))");
    @$db->exec("CREATE INDEX IF NOT EXISTS users_ref ON users (CAST(json_extract(data,'$.referrer') AS INTEGER))");
    $db->exec('CREATE TABLE IF NOT EXISTS ref_log (rid INTEGER NOT NULL, amount REAL NOT NULL, commission REAL NOT NULL, at TEXT NOT NULL)');
    $db->exec('CREATE INDEX IF NOT EXISTS ref_log_rid ON ref_log (rid)');
    $db->exec('CREATE TABLE IF NOT EXISTS wallet_tx (id INTEGER PRIMARY KEY AUTOINCREMENT, uid INTEGER NOT NULL, delta INTEGER NOT NULL,
        bal INTEGER NOT NULL, kind TEXT NOT NULL, ref TEXT NOT NULL, idem TEXT, at INTEGER NOT NULL)');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS wallet_tx_idem ON wallet_tx (idem)');
    $db->exec('CREATE INDEX IF NOT EXISTS wallet_tx_uid ON wallet_tx (uid, id)');

    if ($fresh) usersImportFromJson($db);
    if (is_file(dataPath('ref_log'))) refLogImport($db);
    return $db;
}

function usersImportFromJson($db) {
    $old = dataPath('users');
    if (!is_file($old)) return;
    $raw = @file_get_contents($old);
    $arr = $raw ? json_decode($raw, true) : null;

    if (is_array($arr) && $arr) {
        $db->exec('BEGIN');
        $stmt = $db->prepare('INSERT OR REPLACE INTO users (id, data) VALUES (:id, :data)');
        foreach ($arr as $k => $v) {
            $id = (int)$k;
            if ($id <= 0 || !is_array($v)) continue;
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->bindValue(':data', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();
        }
        $db->exec('COMMIT');
    }
    @rename($old, $old . '.migrated');
}

function refLogImport($db) {
    $old = dataPath('ref_log');
    $arr = json_decode((string)@file_get_contents($old), true);
    if (is_array($arr) && $arr) {
        $db->exec('BEGIN IMMEDIATE');
        $st = $db->prepare('INSERT INTO ref_log (rid, amount, commission, at) VALUES (:r, :a, :c, :t)');
        foreach ($arr as $rid => $rows) {
            if ((int)$rid <= 0 || !is_array($rows)) continue;
            foreach (array_reverse($rows) as $r) {
                if (!is_array($r)) continue;
                $st->bindValue(':r', (int)$rid, SQLITE3_INTEGER);
                $st->bindValue(':a', (float)($r['amount'] ?? 0), SQLITE3_FLOAT);
                $st->bindValue(':c', (float)($r['commission'] ?? 0), SQLITE3_FLOAT);
                $st->bindValue(':t', (string)($r['at'] ?? ''), SQLITE3_TEXT);
                $st->execute();
                $st->reset();
            }
        }
        $db->exec('COMMIT');
    }
    @rename($old, $old . '.migrated');
}

function refLogAdd($rid, $amount, $commission) {
    $db = usersDb();
    if (!$db) return;
    $st = $db->prepare('INSERT INTO ref_log (rid, amount, commission, at) VALUES (:r, :a, :c, :t)');
    $st->bindValue(':r', (int)$rid, SQLITE3_INTEGER);
    $st->bindValue(':a', (float)$amount, SQLITE3_FLOAT);
    $st->bindValue(':c', (float)$commission, SQLITE3_FLOAT);
    $st->bindValue(':t', nowStr(), SQLITE3_TEXT);
    $st->execute();
    $st = $db->prepare('DELETE FROM ref_log WHERE rid = :r AND rowid NOT IN (SELECT rowid FROM ref_log WHERE rid = :r ORDER BY rowid DESC LIMIT 50)');
    $st->bindValue(':r', (int)$rid, SQLITE3_INTEGER);
    $st->execute();
}

function refLogOf($rid, $limit = 20) {
    $db = usersDb();
    if (!$db) return [];
    $st = $db->prepare('SELECT amount, commission, at FROM ref_log WHERE rid = :r ORDER BY rowid DESC LIMIT :n');
    $st->bindValue(':r', (int)$rid, SQLITE3_INTEGER);
    $st->bindValue(':n', (int)$limit, SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) $out[] = $row;
    return $out;
}

function getUser($id) {
    $db = usersDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT data FROM users WHERE id = :id');
    $stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function mutateUser($id, callable $fn) {
    $db = usersDb();
    if (!$db) return null;
    $id = (int)$id;

    if (!@$db->exec('BEGIN IMMEDIATE')) return null;
    try {
        $stmt = $db->prepare('SELECT data FROM users WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $u = $row ? json_decode((string)$row['data'], true) : null;
        if ($row && !is_array($u)) throw new RuntimeException('user ' . $id . ' row is damaged; left untouched');

        $result = $fn($u);

        if (is_array($u)) {
            $json = json_encode($u, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if (!is_string($json) || $json === '' || $json[0] !== '{') throw new RuntimeException('encode');
            $up = $db->prepare('INSERT OR REPLACE INTO users (id, data) VALUES (:id, :data)');
            $up->bindValue(':id', $id, SQLITE3_INTEGER);
            $up->bindValue(':data', $json, SQLITE3_TEXT);
            if (!$up->execute()) throw new RuntimeException('write');
        }
        if (!@$db->exec('COMMIT')) throw new RuntimeException('commit');
        return $result;
    } catch (Throwable $e) {
        @$db->exec('ROLLBACK');
        error_log('[shop-bot] mutateUser خطا: ' . $e->getMessage());
        if (str_contains($e->getMessage(), 'damaged') && function_exists('adminAlertOnce'))
            adminAlertOnce('user_damaged_' . $id, "🔴 <b>اطلاعاتِ یک کاربر خراب است</b>\n\nکاربر <code>" . $id .
                "</code>\nردیفش دست نخورد تا موجودی‌اش از بین نرود. از بکاپ برگردانید یا در پنلِ وب ← کاربران موجودی را دستی ثبت کنید.", 86400);
        return null;
    }
}

function touchUser($id, $username = '', $firstName = '', $referrer = null) {
    $cur = getUser($id);
    $whole = is_array($cur)
          && array_key_exists('balance', $cur) && array_key_exists('banned', $cur)
          && array_key_exists('referrer', $cur) && array_key_exists('joined_at', $cur);
    if ($whole && $referrer === null
        && (string)($cur['username'] ?? '')   === (string)$username
        && (string)($cur['first_name'] ?? '') === (string)$firstName) {
        $seen = strtotime((string)($cur['seen_at'] ?? '')) ?: 0;
        if ($seen > 0 && (time() - $seen) < 60) return $cur;
    }

    $referrerOk = $referrer && (int)$referrer !== (int)$id && getUser($referrer) !== null;

    $didSetReferrer = false;
    $out = mutateUser($id, function (&$user) use ($id, $username, $firstName, $referrerOk, $referrer, &$didSetReferrer) {
        $isNew = ($user === null);
        $user = array_merge([
            'telegram_id' => (int)$id,
            'balance'     => 0,
            'referrer'    => null,
            'ref_earned'  => 0,
            'banned'      => false,
            'joined_at'   => nowStr(),
        ], is_array($user) ? $user : [], [
            'username'   => $username,
            'first_name' => $firstName,
            'seen_at'    => nowStr(),
        ]);
        if ($isNew && $referrerOk) {
            $user['referrer'] = (int)$referrer;
            $didSetReferrer = true;
        }
        return $user;
    });

    if ($didSetReferrer) {
        mutateUser($referrer, function (&$r) {
            if ($r !== null) $r['ref_count'] = (int)($r['ref_count'] ?? 0) + 1;
        });
    }

    return $out;
}

function walletIdemSeen($idem) {
    $db = usersDb();
    if (!$db || $idem === null || $idem === '') return false;
    $st = $db->prepare('SELECT 1 FROM wallet_tx WHERE idem = :i');
    $st->bindValue(':i', (string)$idem, SQLITE3_TEXT);
    $r = $st->execute();
    return (bool)($r ? $r->fetchArray(SQLITE3_NUM) : false);
}

function walletTx($uid, $old, $new, $kind, $ref = '', $idem = null) {
    $db = usersDb();
    if (!$db) throw new RuntimeException('wallet_tx');
    $st = $db->prepare('INSERT INTO wallet_tx (uid, delta, bal, kind, ref, idem, at) VALUES (:u, :d, :b, :k, :r, :i, :t)');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':d', (int)round(((float)$new - (float)$old) * 100), SQLITE3_INTEGER);
    $st->bindValue(':b', (int)round((float)$new * 100), SQLITE3_INTEGER);
    $st->bindValue(':k', substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string)$kind)), 0, 24), SQLITE3_TEXT);
    $st->bindValue(':r', mb_substr((string)$ref, 0, 64), SQLITE3_TEXT);
    if ($idem === null || $idem === '') $st->bindValue(':i', null, SQLITE3_NULL);
    else $st->bindValue(':i', mb_substr((string)$idem, 0, 120), SQLITE3_TEXT);
    $st->bindValue(':t', time(), SQLITE3_INTEGER);
    if (!$st->execute()) throw new RuntimeException('wallet_tx');
}

function walletHistory($uid, $limit = 20) {
    $db = usersDb();
    if (!$db) return [];
    $st = $db->prepare('SELECT delta, bal, kind, ref, at FROM wallet_tx WHERE uid = :u ORDER BY id DESC LIMIT :n');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':n', max(1, min(200, (int)$limit)), SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC)))
        $out[] = ['delta' => (int)$r['delta'] / 100, 'bal' => (int)$r['bal'] / 100, 'kind' => (string)$r['kind'], 'ref' => (string)$r['ref'], 'at' => (int)$r['at']];
    return $out;
}

function addBalance($userId, $amount, $kind = 'credit', $ref = '', $idem = null) {
    for ($i = 0; $i < 3; $i++) {
        $r = mutateUser($userId, function (&$user) use ($amount, $userId, $kind, $ref, $idem) {
            if ($user === null) return false;
            if ($idem !== null && walletIdemSeen($idem)) return true;
            $old = (float)($user['balance'] ?? 0);
            $user['balance'] = round($old + (float)$amount, 2);
            walletTx($userId, $old, $user['balance'], $kind, $ref, $idem);
            return true;
        });
        if ($r !== null) return (bool)$r;
        usleep(150000);
    }
    error_log('[shop-bot] addBalance ناموفق: uid=' . (int)$userId . ' amount=' . (float)$amount);
    return false;
}

function debitBalance($userId, $amount, $kind = 'debit', $ref = '') {
    $amount = round((float)$amount, 2);
    if ($amount <= 0) return true;
    return (bool)mutateUser($userId, function (&$user) use ($amount, $userId, $kind, $ref) {
        if ($user === null) return false;
        $old = (float)($user['balance'] ?? 0);
        $bal = round($old, 2);
        if ($bal + 0.001 < $amount) return false;
        $user['balance'] = round($bal - $amount, 2);
        walletTx($userId, $old, $user['balance'], $kind, $ref);
        return true;
    });
}

function countReferrals($userId) {
    return (int)(getUser($userId)['ref_count'] ?? 0);
}

function backfillRefCounts() {
    $db = usersDb();
    if (!$db) return;
    $counts = [];
    $res = $db->query("SELECT CAST(json_extract(data,'$.referrer') AS INTEGER) AS r, COUNT(*) AS n FROM users WHERE r > 0 GROUP BY r");
    while ($res && ($row = $res->fetchArray(SQLITE3_NUM))) $counts[(int)$row[0]] = (int)$row[1];
    $res = $db->query("SELECT id FROM users WHERE COALESCE(json_extract(data,'$.ref_count'), 0) != 0");
    while ($res && ($row = $res->fetchArray(SQLITE3_NUM))) $counts[(int)$row[0]] = $counts[(int)$row[0]] ?? 0;
    foreach ($counts as $id => $n)
        mutateUser($id, function (&$user) use ($n) { if ($user !== null) $user['ref_count'] = $n; });
}

function payReferralCommission($buyerId, $amount) {
    $u = getUser($buyerId);
    if (!$u || empty($u['referrer'])) return;
    $c = cfg()['referral'];
    if (empty($c['on'])) return;
    $commission = round((float)$amount * ((float)$c['percent'] / 100), 2);
    if ($commission <= 0) return;
    mutateUser($u['referrer'], function (&$user) use ($commission) {
        if ($user === null) return;
        $user['ref_earned']  = round((float)($user['ref_earned'] ?? 0) + $commission, 2);
        $user['ref_pending'] = round((float)($user['ref_pending'] ?? 0) + $commission, 2);
    });
    refLogAdd($u['referrer'], $amount, $commission);
    sendMsg(BOT_TOKEN, $u['referrer'],
        "🎉 خریدِ زیرمجموعه\n💵 پورسانت: <b>" . fmtNum($commission) . "</b> تومان");
}

function slotGet($uid, $slot) {
    $u = getUser($uid);
    $v = $u['slots'][$slot] ?? null;
    return $v ? (int)$v : null;
}

function slotSet($uid, $slot, $mid) {
    mutateUser($uid, function (&$user) use ($slot, $mid) {
        if ($user === null) return;
        if (!is_array($user['slots'] ?? null)) $user['slots'] = [];
        if ($mid) $user['slots'][$slot] = (int)$mid;
        else unset($user['slots'][$slot]);
    });
}

function slotClear($uid, $slot = null) {
    mutateUser($uid, function (&$user) use ($slot) {
        if ($user === null) return;
        if ($slot === null) $user['slots'] = array_intersect_key((array)($user['slots'] ?? []), ['umsg' => 1]);
        else unset($user['slots'][$slot]);
    });
}

function panelShow($uid, $chatId, $slot, $text, $markup = null, $replyTo = null) {
    if ($replyTo && (string)$chatId === (string)$uid) {
        $slots = (array)(getUser($uid)['slots'] ?? []);
        unset($slots['umsg']);
        $old = array_unique(array_filter(array_map('intval', $slots)));
        $r = sendMsg(BOT_TOKEN, $chatId, $text, $markup);
        $nid = (int)($r['result']['message_id'] ?? 0);
        mutateUser($uid, function (&$user) use ($slot, $nid) {
            if ($user === null) return;
            $keep = array_intersect_key((array)($user['slots'] ?? []), ['umsg' => 1]);
            $user['slots'] = $nid ? $keep + [$slot => $nid] : $keep;
        });
        foreach ($old as $o) if ($o !== $nid) delMsg(BOT_TOKEN, $chatId, $o);
        return $nid ?: null;
    }
    $mid = slotGet($uid, $slot);
    if ($replyTo) {
        if ($mid) delMsg(BOT_TOKEN, $chatId, $mid);
        $r = sendMsg(BOT_TOKEN, $chatId, $text, $markup, [
            'reply_to_message_id' => $replyTo,
            'allow_sending_without_reply' => 'true',
        ]);
        $nid = $r['result']['message_id'] ?? null;
        slotSet($uid, $slot, $nid);
        return $nid;
    }

    if ($mid) {
        $data = [
            'chat_id' => $chatId, 'message_id' => $mid, 'text' => $text,
            'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true',
        ];
        if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : kbJson($markup);
        $r = tg(BOT_TOKEN, 'editMessageText', $data);

        if (empty($r['ok']) && $markup && !is_string($markup) && isStyleError($r)) {
            $data['reply_markup'] = json_encode(stripStyles($markup));
            $r = tg(BOT_TOKEN, 'editMessageText', $data);
        }
        if (!empty($r['ok'])) return $mid;

        if (str_contains(strtolower($r['description'] ?? ''), 'not modified')) return $mid;
    }

    $r = sendMsg(BOT_TOKEN, $chatId, $text, $markup);
    $nid = $r['result']['message_id'] ?? null;
    slotSet($uid, $slot, $nid);
    return $nid;
}

function statesDb() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    if (!class_exists('SQLite3') && !dbOn()) return $db = false;

    $path = DATA_DIR . '/states.sqlite';
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    try { $db = nbRawOpen($path); }
    catch (Throwable $e) { error_log('[states] باز نشد: ' . $e->getMessage()); return $db = false; }

    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS states (
        id INTEGER PRIMARY KEY, action TEXT, data TEXT, at INTEGER)');
    $db->exec('CREATE INDEX IF NOT EXISTS states_at ON states (at)');

    stateMigrateOnce($db);
    return $db;
}

function stateMigrateOnce($db) {
    $old = dataPath('states');
    if (!is_file($old)) return;
    $raw = @file_get_contents($old);
    $a = $raw ? json_decode($raw, true) : null;
    if (is_array($a) && $a) {
        $ins = $db->prepare('INSERT OR REPLACE INTO states (id, action, data, at) VALUES (:i,:a,:d,:t)');
        $db->exec('BEGIN');
        foreach ($a as $uid => $st) {
            if (!is_array($st)) continue;
            $ins->bindValue(':i', (int)$uid, SQLITE3_INTEGER);
            $ins->bindValue(':a', (string)($st['action'] ?? ''), SQLITE3_TEXT);
            $ins->bindValue(':d', json_encode((array)($st['data'] ?? []), JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
            $ins->bindValue(':t', strtotime((string)($st['at'] ?? '')) ?: time(), SQLITE3_INTEGER);
            $ins->execute(); $ins->reset();
        }
        $db->exec('COMMIT');
    }
    @rename($old, $old . '.migrated');
}

function getState($uid) {
    $db = statesDb();
    if (!$db) return null;
    $st = $db->prepare('SELECT action, data, at FROM states WHERE id = :i');
    $st->bindValue(':i', (int)$uid, SQLITE3_INTEGER);
    $row = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode((string)$row['data'], true);
    return [
        'action' => (string)$row['action'],
        'data'   => is_array($d) ? $d : [],
        'at'     => date('Y-m-d H:i:s', (int)$row['at']),
    ];
}

function setState($uid, $action, $data = []) {
    $db = statesDb();
    if (!$db) return;
    $st = $db->prepare('INSERT OR REPLACE INTO states (id, action, data, at) VALUES (:i,:a,:d,:t)');
    $st->bindValue(':i', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':a', (string)$action, SQLITE3_TEXT);
    $st->bindValue(':d', json_encode((array)$data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
    $st->bindValue(':t', time(), SQLITE3_INTEGER);
    $st->execute();
}

function clearState($uid) {
    $db = statesDb();
    if (!$db) return;
    $st = $db->prepare('DELETE FROM states WHERE id = :i');
    $st->bindValue(':i', (int)$uid, SQLITE3_INTEGER);
    $st->execute();
}

function stateSweep($ttl = 86400, $limit = 500) {
    $db = statesDb();
    if (!$db) return 0;
    $cut = time() - max(60, (int)$ttl);
    $st = $db->prepare('DELETE FROM states WHERE at < :c');
    $st->bindValue(':c', $cut, SQLITE3_INTEGER);
    $st->execute();
    return $db->changes();
}

function parseChatLink($s) {
    $s = trim((string)$s);
    if ($s === '') return [null, 0];

    if (preg_match('/^-?\d{5,}$/', $s)) return [$s, 0];
    if (preg_match('/^@[A-Za-z0-9_]{4,}$/', $s)) return [$s, 0];

    $s = preg_replace('#^https?://#i', '', $s);
    $s = preg_replace('#^t\.me/#i', '', $s, 1, $n);
    if (!$n) return [null, 0];
    $qt = 0;
    if (($qp = strpos($s, '?')) !== false) {
        parse_str(substr($s, $qp + 1), $qs);
        $qt = max(0, (int)($qs['thread'] ?? $qs['topic'] ?? 0));
        $s = substr($s, 0, $qp);
    }
    $s = trim($s, '/');
    $parts = array_values(array_filter(explode('/', $s), fn($x) => $x !== ''));
    if (!$parts) return [null, 0];

    if (strtolower($parts[0]) === 'c') {
        if (!isset($parts[1]) || !ctype_digit($parts[1])) return [null, 0];
        $chat = '-100' . $parts[1];
        $th   = $qt ?: ((isset($parts[2]) && ctype_digit($parts[2])) ? (int)$parts[2] : 0);
        return [$chat, $th];
    }

    if (str_starts_with($parts[0], '+') || strtolower($parts[0]) === 'joinchat') return [null, 0];

    if (!preg_match('/^[A-Za-z0-9_]{4,}$/', $parts[0])) return [null, 0];
    $chat = '@' . $parts[0];
    $th   = $qt ?: ((isset($parts[1]) && ctype_digit($parts[1])) ? (int)$parts[1] : 0);
    return [$chat, $th];
}

function chatLinkResolve($s) {
    [$chat, $th] = parseChatLink($s);
    if ($chat === null) return [null, 0, null];
    $r = tg(BOT_TOKEN, 'getChat', ['chat_id' => $chat], 10);
    if (empty($r['ok'])) return [(string)$chat, (int)$th, $r];
    if ($th > 0 && empty($r['result']['is_forum'])) $th = 0;
    return [(string)($r['result']['id'] ?? $chat), (int)$th, $r];
}

function tgChatId($chat) {
    $chat = trim((string)$chat);
    if ($chat === '' || $chat[0] !== '@') return $chat;
    static $memo = [];
    if (isset($memo[$chat])) return $memo[$chat];
    $hit = function_exists('maCacheGet') ? maCacheGet('cid_' . md5(strtolower($chat)), 86400) : null;
    if (is_string($hit) && $hit !== '') return $memo[$chat] = $hit;
    $r = tg(BOT_TOKEN, 'getChat', ['chat_id' => $chat], 8);
    $id = !empty($r['ok']) ? (string)($r['result']['id'] ?? '') : '';
    if ($id === '') return $chat;
    if (function_exists('maCachePut')) maCachePut('cid_' . md5(strtolower($chat)), $id);
    return $memo[$chat] = $id;
}

function tgThreadGone($res) {
    $d = strtolower((string)($res['description'] ?? ''));
    return str_contains($d, 'thread not found') || str_contains($d, 'topic_deleted') || str_contains($d, 'topic not found');
}

function tgChatDead($res) {
    if (!empty($res['ok'])) return false;
    $d = strtolower((string)($res['description'] ?? ''));
    foreach (['chat not found', 'bot was kicked', 'not a member', 'chat_write_forbidden', 'have no rights to send',
              'not enough rights to send text', 'bot is not a member', 'group chat was deactivated', 'peer_id_invalid'] as $k)
        if (str_contains($d, $k)) return true;
    return false;
}

function chatDown($chat, $res = null) {
    $k = preg_replace('/[^0-9A-Za-z_@-]/', '', (string)$chat);
    if ($k === '') return null;
    if ($res === null) {
        $v = load('chat_down')[$k] ?? null;
        return (is_array($v) && time() - (int)($v[0] ?? 0) < 600) ? ['ok' => false, 'description' => (string)($v[1] ?? '')] : null;
    }
    if (!empty($res['ok']) || $res === false) {
        if (isset(load('chat_down')[$k])) mutate('chat_down', function (&$a) use ($k) { unset($a[$k]); });
        return null;
    }
    if (!tgChatDead($res)) return null;
    mutate('chat_down', function (&$a) use ($k, $res) {
        foreach ($a as $x => $v) if (!is_array($v) || time() - (int)($v[0] ?? 0) > 86400) unset($a[$x]);
        $a[$k] = [time(), mb_substr((string)($res['description'] ?? ''), 0, 160)];
    });
    return null;
}

function tgWhy($res) {
    $d = (string)($res['description'] ?? '');
    $l = strtolower($d);
    $code = (int)($res['error_code'] ?? 0);
    if ($code === 429) $why = '⏳ تلگرام گفت چند ثانیه صبر کنید';
    elseif (tgThreadGone($res)) $why = '🧵 تاپیک پیدا نشد — برای گروهِ معمولی (بدونِ تاپیک) لینکِ پیام کافی است؛ برای گروهِ تاپیک‌دار لینکِ خودِ تاپیک را بدهید';
    elseif (str_contains($l, 'topic_closed')) $why = '🧵 تاپیک بسته است؛ بازش کنید یا تاپیکِ دیگری بدهید';
    elseif (str_contains($l, 'not enough rights') || str_contains($l, 'have no rights') || str_contains($l, 'chat_write_forbidden') || str_contains($l, 'need administrator'))
        $why = '🔒 ربات اجازه‌ی فرستادن ندارد — در تنظیماتِ ادمینِ ربات «ارسالِ پیام/پست» و «ارسالِ رسانه» را روشن کنید';
    elseif (str_contains($l, 'upgraded to a supergroup')) $why = '↪️ گروه سوپرگروه شده؛ یک بار دیگر لینک یا آیدیِ تازه را بدهید';
    elseif (str_contains($l, 'kicked') || str_contains($l, 'not a member') || str_contains($l, 'chat not found') || $code === 403)
        $why = '🚫 ربات عضوِ این گروه/کانال نیست یا گروه پیدا نشد';
    elseif ($d === '') $why = '⏳ ارتباط با تلگرام برقرار نشد؛ دوباره امتحان کنید';
    else $why = '⚠️ تلگرام قبول نکرد';
    return $d !== '' ? $why . "\n<code>" . h(mb_substr($d, 0, 160)) . '</code>' : $why;
}

function ordersDbPath() { return DATA_DIR . '/orders.sqlite'; }

function ordersDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;

    $path = ordersDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fresh = !is_file($path);

    try {
        $db = nbRawOpen($path);
    } catch (Throwable $e) {
        error_log('[shop-bot] orders.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS orders (
        id TEXT PRIMARY KEY, user_id INTEGER NOT NULL, type TEXT NOT NULL,
        status TEXT NOT NULL, created_at TEXT NOT NULL, data TEXT NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_orders_user   ON orders(user_id, created_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)');
    $db->exec('CREATE TABLE IF NOT EXISTS orders_old (id TEXT PRIMARY KEY, data TEXT NOT NULL)');

    if ($fresh) ordersImportFromJson($db);
    return $db;
}

function ordersImportFromJson($db) {
    $map = ['orders' => 'orders', 'orders_old' => 'orders_old'];
    foreach ($map as $file => $table) {
        $old = dataPath($file);
        if (!is_file($old)) continue;
        $raw = @file_get_contents($old);
        $arr = $raw ? json_decode($raw, true) : null;
        if (is_array($arr) && $arr) {
            $db->exec('BEGIN');
            if ($table === 'orders') {
                $stmt = $db->prepare('INSERT OR REPLACE INTO orders (id, user_id, type, status, created_at, data) VALUES (:id, :uid, :type, :status, :created, :data)');
                foreach ($arr as $k => $v) {
                    if ($k === '' || !is_array($v)) continue;
                    $stmt->bindValue(':id', (string)$k, SQLITE3_TEXT);
                    $stmt->bindValue(':uid', (int)($v['user_id'] ?? 0), SQLITE3_INTEGER);
                    $stmt->bindValue(':type', (string)($v['type'] ?? ''), SQLITE3_TEXT);
                    $stmt->bindValue(':status', (string)($v['status'] ?? ''), SQLITE3_TEXT);
                    $stmt->bindValue(':created', (string)($v['created_at'] ?? ''), SQLITE3_TEXT);
                    $stmt->bindValue(':data', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
                    $stmt->execute();
                    $stmt->reset();
                }
            } else {
                $stmt = $db->prepare('INSERT OR REPLACE INTO orders_old (id, data) VALUES (:id, :data)');
                foreach ($arr as $k => $v) {
                    if ($k === '' || !is_array($v)) continue;
                    $stmt->bindValue(':id', (string)$k, SQLITE3_TEXT);
                    $stmt->bindValue(':data', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
                    $stmt->execute();
                    $stmt->reset();
                }
            }
            $db->exec('COMMIT');
        }
        @rename($old, $old . '.migrated');
    }
}

class Order
{
    const PENDING = 'pending', REVIEW = 'review', APPROVED = 'approved', REJECTED = 'rejected';

    public static function pendingGw($limit = 10) {
        $db = ordersDb();
        if (!$db) return [];
        $stmt = $db->prepare("SELECT data FROM orders WHERE status = :s AND type = 'topup' ORDER BY created_at DESC LIMIT :n");
        $stmt->bindValue(':s', self::PENDING, SQLITE3_TEXT);
        $stmt->bindValue(':n', max(1, (int)$limit) * 4, SQLITE3_INTEGER);
        $out = [];
        $res = $stmt->execute();
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $d = json_decode($row['data'], true);
            if (is_array($d) && !empty($d['gw']['invoice'])) $out[] = $d;
        }
        return $out;
    }

    public static function get($id) {
        $db = ordersDb();
        if (!$db) return null;
        $id = (string)$id;

        $stmt = $db->prepare('SELECT data FROM orders WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row) { $d = json_decode($row['data'], true); if (is_array($d)) return $d; }

        $stmt = $db->prepare('SELECT data FROM orders_old WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row) { $d = json_decode($row['data'], true); if (is_array($d)) return $d; }

        return null;
    }

    public static function create($userId, $username, $amount) {
        $id = uid('or');
        $o = [
            'id' => $id, 'user_id' => (int)$userId, 'username' => $username,
            'type' => 'topup',
            'amount' => (float)$amount, 'currency' => 'تومان',
            'status' => self::PENDING,
            'receipt_type' => null, 'receipt' => null,
            'created_at' => nowStr(), 'decided_at' => null, 'decided_by' => null,
        ];
        $db = ordersDb();
        if ($db) {
            $stmt = $db->prepare('INSERT INTO orders (id, user_id, type, status, created_at, data) VALUES (:id, :uid, :type, :status, :created, :data)');
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $stmt->bindValue(':uid', (int)$userId, SQLITE3_INTEGER);
            $stmt->bindValue(':type', 'topup', SQLITE3_TEXT);
            $stmt->bindValue(':status', self::PENDING, SQLITE3_TEXT);
            $stmt->bindValue(':created', $o['created_at'], SQLITE3_TEXT);
            $stmt->bindValue(':data', json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $stmt->execute();
        }
        return $id;
    }

    public static function delete($id) {
        $db = ordersDb();
        if (!$db) return;
        $stmt = $db->prepare("DELETE FROM orders WHERE id = :id AND status = 'pending'");
        $stmt->bindValue(':id', (string)$id, SQLITE3_TEXT);
        $stmt->execute();
    }

    // A user pressing "cancel" on an invoice must not lose money that is already on
    // its way: once a gateway invoice exists the order is only marked cancelled and
    // stays pending, so a late IPN / poll still credits the wallet.
    public static function cancel($id) {
        $o = self::get($id);
        if (!$o || ($o['status'] ?? '') !== self::PENDING) return;
        if (!empty($o['gw']['invoice']) || !empty($o['ir']['ref'])) {
            self::set($id, function (&$x) { if (($x['status'] ?? '') === self::PENDING) $x['cancelled_at'] = time(); });
            return;
        }
        self::delete($id);
    }

    public static function set($id, callable $fn) {
        $db = ordersDb();
        if (!$db) return false;
        $id = (string)$id;

        if (!@$db->exec('BEGIN IMMEDIATE')) return false;
        try {
            $stmt = $db->prepare('SELECT data FROM orders WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            $o = $row ? json_decode($row['data'], true) : null;
            if (!is_array($o)) { $db->exec('ROLLBACK'); return false; }

            $r = $fn($o);

            $up = $db->prepare('UPDATE orders SET user_id = :uid, type = :type, status = :status, created_at = :created, data = :data WHERE id = :id');
            $up->bindValue(':id', $id, SQLITE3_TEXT);
            $up->bindValue(':uid', (int)($o['user_id'] ?? 0), SQLITE3_INTEGER);
            $up->bindValue(':type', (string)($o['type'] ?? ''), SQLITE3_TEXT);
            $up->bindValue(':status', (string)($o['status'] ?? ''), SQLITE3_TEXT);
            $up->bindValue(':created', (string)($o['created_at'] ?? ''), SQLITE3_TEXT);
            $up->bindValue(':data', json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            if (!$up->execute()) throw new RuntimeException('write');
            if (!@$db->exec('COMMIT')) throw new RuntimeException('commit');
            return $r === null ? true : $r;
        } catch (Throwable $e) {
            @$db->exec('ROLLBACK');
            error_log('[shop-bot] Order::set خطا: ' . $e->getMessage());
            return false;
        }
    }

    public static function attachReceipt($id, $type, $value) {
        return self::set($id, function (&$x) use ($type, $value) {
            if ($x['status'] !== self::PENDING) return false;
            $x['receipt_type'] = $type;
            $x['receipt'] = $value;
            $x['status'] = self::REVIEW;
            return true;
        });
    }

    public static function approve($id, $adminId) {
        $o0 = self::get($id);
        if (!$o0) return [false, 'سفارش پیدا نشد.'];
        if (($o0['currency'] ?? 'تومان') !== 'تومان')
            return [false, 'مبلغ این سفارشِ قدیمی تومانی نیست — دستی رسیدگی کنید.'];

        $r = self::set($id, function (&$x) use ($adminId) {
            if (in_array($x['status'], [self::APPROVED, self::REJECTED], true)) return 'done';
            $x['status'] = self::APPROVED;
            $x['decided_at'] = nowStr();
            $x['decided_by'] = (int)$adminId;
            return 'ok';
        });
        if ($r === false)  return [false, 'سفارش پیدا نشد.'];
        if ($r === 'done') return [false, 'این سفارش قبلا بررسی شده است.'];

        $o = self::get($id);
        if (!addBalance($o['user_id'], $o['amount'], 'topup', (string)$id, 'topup:' . $id)) {
            // Never leave a paid order "approved" with no money in the wallet: put it
            // back to pending so the next poll/IPN retries (the idem key prevents a double credit).
            self::set($id, function (&$x) {
                $x['status'] = self::PENDING; $x['decided_at'] = null; $x['decided_by'] = null;
                $x['credit_fail'] = (int)($x['credit_fail'] ?? 0) + 1;
            });
            error_log('[shop-bot] topup credit failed, order back to pending: ' . $id);
            if (function_exists('adminAlertOnce'))
                adminAlertOnce('credit_fail_' . md5((string)$id), "🔴 <b>شارژِ کیف پول انجام نشد</b>\n\nسفارش <code>" . h((string)$id) .
                    "</code> پرداخت شده ولی نوشتنِ موجودی ناموفق بود؛ دوباره خودکار تلاش می‌شود.", 3600);
            return [false, 'نوشتنِ موجودی ناموفق بود؛ دوباره تلاش می‌شود.'];
        }
        return [true, $o];
    }

    public static function reject($id, $adminId) {
        $r = self::set($id, function (&$x) use ($adminId) {
            if (in_array($x['status'], [self::APPROVED, self::REJECTED], true)) return 'done';
            $x['status'] = self::REJECTED;
            $x['decided_at'] = nowStr();
            $x['decided_by'] = (int)$adminId;
            return 'ok';
        });
        if ($r === false)  return [false, 'سفارش پیدا نشد.'];
        if ($r === 'done') return [false, 'این سفارش قبلا بررسی شده است.'];
        return [true, self::get($id)];
    }

    public static function countBy($status) {
        $db = ordersDb();
        if (!$db) return 0;
        $stmt = $db->prepare('SELECT COUNT(*) c FROM orders WHERE status = :status');
        $stmt->bindValue(':status', (string)$status, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return (int)($row['c'] ?? 0);
    }

    public static function statusLabel($s) {
        return [
            'pending'  => '⏳ منتظر پرداخت',
            'review'   => '🧾 در حال بررسی',
            'approved' => '✅ تایید شده',
            'rejected' => '❌ رد شده',
        ][$s] ?? '—';
    }
}

class JoinCheck
{
    private static $memCache = [];
    private static $memFresh = [];

    private static function takeFresh($k) {
        if (!isset(self::$memFresh[$k])) return false;
        unset(self::$memFresh[$k]);
        return true;
    }

    private static function joinCacheShardFile($k) {
        return 'mjoin_cache_' . (hexdec(substr(md5((string)$k), 0, 4)) % 16);
    }
    private static function joinCacheGet($k) {
        if (function_exists('apcu_fetch')) {
            $v = @apcu_fetch('mjoin_' . $k, $ok);
            if ($ok) return $v;
        }
        $bag = load(self::joinCacheShardFile($k));
        $e = $bag[$k] ?? null;
        if (!is_array($e) || (int)($e['exp'] ?? 0) < time()) return null;
        return $e['v'];
    }
    private static function joinCachePut($k, $v) {
        $ttl = !empty($v['member']) ? 300 : 45;
        if (function_exists('apcu_store')) { @apcu_store('mjoin_' . $k, $v, $ttl); return; }
        mutate(self::joinCacheShardFile($k), function (&$bag) use ($k, $v, $ttl) {
            $bag[$k] = ['v' => $v, 'exp' => time() + $ttl];
            if (count($bag) > 400) {
                $now = time();
                foreach ($bag as $kk => $e) if ((int)($e['exp'] ?? 0) < $now) unset($bag[$kk]);
            }
        });
    }

    private static function memberResult($r) {
        if (empty($r['ok'])) {
            $desc = strtolower($r['description'] ?? '');
            $reallyNotMember = str_contains($desc, 'user not found')
                            || str_contains($desc, 'participant_id_invalid');
            return ['ok' => $reallyNotMember, 'member' => false, 'error' => $r['description'] ?? ''];
        }
        $st = $r['result']['status'] ?? '';
        return ['ok' => true,
                'member' => in_array($st, ['member', 'administrator', 'creator'], true),
                'error' => ''];
    }

    public static function warmMembership($chatIds, $userId, $fresh = false) {
        $need = [];
        foreach ($chatIds as $cid) {
            $cid = (string)$cid;
            if ($cid === '') continue;
            $k = $cid . ':' . $userId;
            if ($fresh && !self::takeFresh($k)) unset(self::$memCache[$k]);
            if (isset(self::$memCache[$k]) || isset($need[$k])) continue;
            if (!$fresh) {
                $cached = self::joinCacheGet($k);
                if ($cached !== null) { self::$memCache[$k] = $cached; continue; }
            }
            $need[$k] = ['chat_id' => $cid, 'user_id' => $userId];
        }
        if (count($need) < 2) return;
        foreach (tgMulti(BOT_TOKEN, 'getChatMember', $need, 6) as $k => $r) {
            self::$memCache[$k] = self::memberResult($r);
            self::$memFresh[$k] = true;
            self::joinCachePut($k, self::$memCache[$k]);
        }
    }

    public static function isMemberOf($chatId, $userId, $fresh = false) {
        $k = $chatId . ':' . $userId;
        if ($fresh && !self::takeFresh($k)) unset(self::$memCache[$k]);
        if (isset(self::$memCache[$k])) return self::$memCache[$k];

        if (!$fresh) {
            $cached = self::joinCacheGet($k);
            if ($cached !== null) return self::$memCache[$k] = $cached;
        }

        $r = tg(BOT_TOKEN, 'getChatMember',
                ['chat_id' => $chatId, 'user_id' => $userId], 6);
        unset(self::$memFresh[$k]);
        $res = self::memberResult($r);
        self::joinCachePut($k, $res);
        return self::$memCache[$k] = $res;
    }

}

function showHome($uid, $chatId, $firstName) {
    sendMsg(BOT_TOKEN, $chatId, T('welcome', ['name' => h($firstName)]), mainKeyboard());
}

function countApprovedOrders($uid) {
    return (int)(getUser($uid)['approved_orders'] ?? 0) + (function_exists('svPaidCount') ? svPaidCount($uid) : 0);
}

function showAccount($uid, $chatId, $replyTo = null) {
    $u = getUser($uid) ?: [];

    $text = T('account', [
        'id'         => $uid,
        'name'       => h($u['first_name'] ?? '—'),
        'username'   => !empty($u['username']) ? '@' . h($u['username']) : '—',
        'balance'    => fmtNum($u['balance'] ?? 0),
        'orders'     => countApprovedOrders($uid),
        'referrals'  => countReferrals($uid),
        'ref_earned' => fmtNum($u['ref_earned'] ?? 0),
        'joined'     => h($u['joined_at'] ?? '—'),
    ]);
    $rows = [[btnUI('topup', 'menu_topup', 'buy')], [btnUI('my_orders', 'menu_orders', 'info')]];
    panelShow($uid, $chatId, 'menu', $text, inlineKb($rows), $replyTo);
}

function refBtn($which, $cb) {
    $m = cfg()['referral']['btns'][$which] ?? [];
    $b = ['text' => trim((string)($m['text'] ?? '')), 'callback_data' => $cb];
    if (isStyle($m['color'] ?? '')) $b['style'] = $m['color'];
    if (!empty($m['icon'])) $b['icon_custom_emoji_id'] = (string)$m['icon'];
    return $b;
}

function refInviteLink($uid) {
    $un = botUsername();
    return $un !== '' ? "https://t.me/{$un}?start=ref{$uid}" : '';
}

function showReferral($uid, $chatId, $replyTo = null) {
    $cardMid = slotGet($uid, 'refcard');
    if ($cardMid) { delMsg(BOT_TOKEN, $chatId, $cardMid); slotClear($uid, 'refcard'); }

    $u = getUser($uid) ?: [];
    $refText = T('referral', [
        'percent'     => cfg()['referral']['percent'],
        'referrals'   => countReferrals($uid),
        'ref_earned'  => fmtNum($u['ref_earned'] ?? 0),
        'ref_pending' => fmtNum($u['ref_pending'] ?? 0),
    ]);
    $rows = [
        [refBtn('link', 'ref_link')],
        [refBtn('hist', 'ref_hist'), refBtn('wallet', 'ref_wallet')],
    ];
    panelShow($uid, $chatId, 'menu', $refText, inlineKb($rows), $replyTo);
}

function showReferralLink($uid, $chatId) {
    $link = refInviteLink($uid);
    if ($link === '') {
        panelShow($uid, $chatId, 'menu', '⚠️ لینک هنوز آماده نیست.',
            inlineKb([[btnUI('back', 'menu_referral', 'nav')]]));
        return;
    }
    $oldMid = slotGet($uid, 'menu');
    if ($oldMid) { delMsg(BOT_TOKEN, $chatId, $oldMid); slotClear($uid, 'menu'); }

    $share = refBtn('share', '');
    unset($share['callback_data']);
    $share['url'] = 'https://t.me/share/url?url=' . rawurlencode($link) .
                    '&text=' . rawurlencode(strip_tags(T('referral_share')));
    $rows = [[$share], [btnUI('back', 'menu_referral', 'nav')]];
    $caption = T('referral_link', ['link' => $link]);
    $ok = refCardShow($uid, $chatId, $caption, inlineKb($rows));
    if (!$ok) panelShow($uid, $chatId, 'menu', $caption, inlineKb($rows));
}

function refCardBytes($link, $uid) {
    if (!function_exists('pxCardReady') || !pxCardReady() || !function_exists('pxBase')) return '';
    $q = pxQrMatrix($link);
    if (!$q) return '';
    $S     = PX_SS;
    $im    = pxBase('12C488', '2F72DF');
    $white = pxCol($im, 'F5F5F7');
    $gray  = pxCol($im, '9A9AA0');
    $lat   = pxLat(600);
    $pct   = (float)(cfg()['referral']['percent'] ?? 0);

    [, $tr] = pxTag($im, 232, 172, 'INVITE & EARN', 'green', 'l');
    $title = 'دعوت از دوستان';
    pxSay($im, pxSayFit(38, $title, 966 - $tr - 40, 18, ''), 966, 186, $white, $title, 'r');

    pxRRect($im, 226 * $S, 226 * $S, 486 * $S, 486 * $S, 26 * $S, pxCol($im, 'FFFFFF'));
    pxQrDraw($im, $q, 244, 244, 224, '0B0B0C');
    pxSay($im, 17, 356, 522, $gray, 'اسکن کن و عضو شو', 'c');

    if ($pct > 0) {
        pxSay($im, 19.5, 966, 240, $gray, 'پورسانت از هر خرید دوستانت', 'r');
        $big  = rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') . '%';
        $size = pxFit($lat, 58, $big, 380, 30);
        pxPut($im, $lat, $size, 966, 334 - (58 - $size) * 0.3, $white, $big, 'r');
    } else {
        pxSay($im, 19.5, 966, 240, $gray, 'لینک اختصاصی تو', 'r');
        pxSay($im, 40, 966, 330, $white, 'دعوت کن', 'r');
    }

    pxRRect($im, 530 * $S, 402 * $S, 966 * $S, 454 * $S, 16 * $S, pxCol($im, 'FFFFFF', 0.12));
    pxRRect($im, 531.5 * $S, 403.5 * $S, 964.5 * $S, 452.5 * $S, 14.5 * $S, pxCol($im, '18191C'));
    $short = preg_replace('#^https?://#', '', (string)$link);
    pxPut($im, $lat, pxFit($lat, 17, $short, 400, 11), 748, 434, pxCol($im, 'D7DAE0'), $short, 'c');

    $code = (string)(int)$uid;
    $lab  = 'کد دعوت';
    $lw   = pxSayW(17, $lab);
    pxSay($im, 17, 966, 512, $gray, $lab, 'r');
    pxPut($im, $lat, 21, 966 - $lw - 20, 512, pxCol($im, '86EBC9'), $code, 'r');

    pxFoot($im, 'INVITE & EARN');
    return pxJpg($im);
}

function refCardPhotoIdKey($uid) {
    return 'refcard3_fid_' . (int)$uid . '_' . substr(md5(refInviteLink($uid) . '|' . (cfg()['referral']['percent'] ?? '')), 0, 10);
}

function refCardRemember($uid, $fid, $nid) {
    if ($fid !== '' && function_exists('maCachePut')) {
        maCachePut(refCardPhotoIdKey($uid), $fid);
        $check = function_exists('maCacheGet') ? (string)(maCacheGet(refCardPhotoIdKey($uid), 2592000) ?? '') : $fid;
        if ($check !== $fid && function_exists('adminAlertOnce')) {
            adminAlertOnce('refcard_cache_fail',
                '🖼 <b>ذخیره‌ی فایل‌شناسه‌ی کارتِ دعوت شکست خورد</b>' . "\n\n" .
                'یعنی هر بار کارت از نو ساخته می‌شود، نه اینکه یک‌بار بسازد و دوباره بفرستد. ' .
                'دسترسیِ نوشتن روی پوشه‌ی data_master را چک کنید.');
        }
    }
    if ($nid) {
        slotSet($uid, 'refcard', $nid);
        $checkMid = slotGet($uid, 'refcard');
        if ((int)$checkMid !== (int)$nid && function_exists('adminAlertOnce')) {
            adminAlertOnce('refcard_slot_fail',
                '🖼 <b>ذخیره‌ی شناسه‌ی پیامِ کارتِ دعوت شکست خورد</b>' . "\n\n" .
                'دسترسیِ نوشتن روی پوشه‌ی data_master را چک کنید.');
        }
    }
}

function refCardShow($uid, $chatId, $caption = '', $markup = null) {
    $link = refInviteLink($uid);
    if ($link === '') return false;
    $mid = slotGet($uid, 'refcard');
    $fid = function_exists('maCacheGet') ? (string)(maCacheGet(refCardPhotoIdKey($uid), 2592000) ?? '') : '';
    $rm  = $markup ? (is_string($markup) ? $markup : json_encode($markup)) : null;

    if (function_exists('__tgHook')) {
        if ($mid && $fid !== '') {
            $media = ['type' => 'photo', 'media' => $fid, 'caption' => $caption, 'parse_mode' => 'HTML'];
            $data = ['chat_id' => $chatId, 'message_id' => $mid, 'media' => json_encode($media)];
            if ($rm !== null) $data['reply_markup'] = $rm;
            $out = __tgHook(BOT_TOKEN, 'editMessageMedia', $data);
            if (!empty($out['ok']) || isNotModified($out)) return true;
        }
        $data = ['chat_id' => $chatId, 'caption' => $caption, 'photo_len' => 999];
        if ($rm !== null) $data['reply_markup'] = $rm;
        if ($fid !== '') $data['reused_fid'] = $fid;
        $out = __tgHook(BOT_TOKEN, 'sendPhoto', $data);
        if (empty($out['ok'])) return false;
        $nid = $out['result']['message_id'] ?? null;
        $newFid = $out['result']['photo'][0]['file_id'] ?? ('TESTFID_' . $uid);
        refCardRemember($uid, $newFid, $nid);
        return true;
    }

    if ($mid && $fid !== '') {
        $media = ['type' => 'photo', 'media' => $fid, 'caption' => $caption, 'parse_mode' => 'HTML'];
        $data = ['chat_id' => $chatId, 'message_id' => $mid, 'media' => json_encode($media)];
        if ($rm !== null) $data['reply_markup'] = $rm;
        $r = tg(BOT_TOKEN, 'editMessageMedia', $data, 8);
        if (!empty($r['ok']) || isNotModified($r)) return true;

        if (function_exists('pxSendPhotoById')) {
            $resend = pxSendPhotoById($chatId, $fid, $caption, $rm, null, 8);
            if (!empty($resend['ok'])) {
                $nid = $resend['result']['message_id'] ?? null;
                refCardRemember($uid, '', $nid);
                return true;
            }
        }
    }

    $bytes = refCardBytes($link, $uid);
    if ($bytes === '') {
        if (function_exists('adminAlertOnce') && function_exists('pxCardWhy')) {
            $why = pxCardWhy();
            if ($why !== '') adminAlertOnce('refcard_broken', '🖼 <b>کارتِ دعوت ساخته نمی‌شود</b>' . "\n\n" . $why);
        }
        return false;
    }

    $dir = rtrim(DATA_DIR, '/') . '/tmp';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $base = defined('TG_API_BASE') ? TG_API_BASE : 'https://api.telegram.org';
    $upload = function ($asEdit) use ($base, $chatId, $mid, $caption, $rm, $dir, $bytes) {
        $tmp = $dir . '/refcard_' . bin2hex(random_bytes(6)) . '.jpg';
        if (@file_put_contents($tmp, $bytes) === false) return [false, '', ''];
        if ($asEdit) {
            $post = ['chat_id' => $chatId, 'message_id' => $mid,
                'media' => json_encode(['type' => 'photo', 'media' => 'attach://photo',
                    'caption' => $caption, 'parse_mode' => 'HTML']),
                'photo' => new CURLFile($tmp, 'image/jpeg', 'card.jpg')];
            if ($rm !== null) $post['reply_markup'] = $rm;
            $post = emOut('editMessageMedia', $post);
            $ch = curl_init($base . '/bot' . BOT_TOKEN . '/editMessageMedia');
        } else {
            $post = ['chat_id' => $chatId, 'caption' => $caption, 'parse_mode' => 'HTML',
                'photo' => new CURLFile($tmp, 'image/jpeg', 'card.jpg')];
            if ($rm !== null) $post['reply_markup'] = $rm;
            $post = emOut('sendPhoto', $post);
            $ch = curl_init($base . '/bot' . BOT_TOKEN . '/sendPhoto');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $res = monCurl($ch, 'telegram');
        $err = curl_error($ch);
        curl_close($ch);
        @unlink($tmp);
        return [true, $res, $err];
    };

    $applyResult = function ($j) use ($uid) {
        $ph = $j['result']['photo'] ?? [];
        $newFid = '';
        if ($ph) {
            $last = end($ph);
            $newFid = (string)($last['file_id'] ?? '');
        }
        $nid = $j['result']['message_id'] ?? null;
        refCardRemember($uid, $newFid, $nid);
    };

    [$wrote, $res, $curlErr] = $upload((bool)$mid);
    if (!$wrote) return false;
    $j = json_decode((string)$res, true);

    if (!empty($j['ok'])) { $applyResult($j); return true; }
    if (isNotModified($j)) return true;

    if ($mid) {
        [$wrote2, $res2] = $upload(false);
        if ($wrote2) {
            $j2 = json_decode((string)$res2, true);
            if (!empty($j2['ok'])) { $applyResult($j2); return true; }
        }
    }

    if (function_exists('adminAlertOnce')) {
        $desc = $curlErr !== '' ? $curlErr : (string)($j['description'] ?? 'پاسخ نامعتبر از تلگرام');
        adminAlertOnce('refcard_upload_fail_' . substr(md5($desc), 0, 10),
            '🖼 <b>آپلودِ کارتِ دعوت رد شد</b>' . "\n\n" . h($desc));
    }
    return false;
}

function showReferralHistory($uid, $chatId) {
    $rows = refLogOf($uid, 20);
    $t = T('referral_hist_head');
    if (!$rows) {
        $t .= "\n" . T('referral_hist_none');
    } else {
        foreach (array_slice($rows, 0, 20) as $r) {
            $t .= T('referral_hist_row', [
                'date'       => (string)($r['at'] ?? ''),
                'amount'     => fmtNum((float)($r['amount'] ?? 0)),
                'commission' => fmtNum((float)($r['commission'] ?? 0)),
            ]);
        }
    }
    panelShow($uid, $chatId, 'menu', $t, inlineKb([[btnUI('back', 'menu_referral', 'nav')]]));
}

function refWithdraw($uid, $chatId) {
    $pending = (float)mutateUser($uid, function (&$user) use ($uid) {
        if ($user === null) return 0.0;
        $p = round((float)($user['ref_pending'] ?? 0), 2);
        if ($p <= 0) return 0.0;
        $user['ref_pending'] = 0;
        $old = (float)($user['balance'] ?? 0);
        $user['balance'] = round($old + $p, 2);
        walletTx($uid, $old, $user['balance'], 'ref_withdraw');
        return $p;
    });
    if ($pending <= 0) {
        panelShow($uid, $chatId, 'menu', T('referral_wallet_none'),
            inlineKb([[btnUI('back', 'menu_referral', 'nav')]]));
        return;
    }
    panelShow($uid, $chatId, 'menu', T('referral_wallet_ok', ['amount' => fmtNum($pending)]),
        inlineKb([[btnUI('back', 'menu_referral', 'nav')]]));
}

function supMainBtn($which, $cb) {
    $m = cfg()['support_main'][$which] ?? [];
    $b = ['text' => trim((string)($m['text'] ?? ''))];
    if (in_array($which, ['direct', 'group'], true) && !empty($m['value'])) $b['url'] = $m['value'];
    else $b['callback_data'] = $cb;
    if (isStyle($m['color'] ?? '')) $b['style'] = $m['color'];
    if (!empty($m['icon'])) $b['icon_custom_emoji_id'] = (string)$m['icon'];
    return $b;
}

function showSupport($uid, $chatId, $replyTo = null) {
    $d = supMainBtn('direct', 'sup_direct');
    $i = supMainBtn('indirect', 'sup_list');
    panelShow($uid, $chatId, 'menu', T('support'), inlineKb([[$d, $i], [supMainBtn('group', 'sup_group')]]), $replyTo);
}

function showSupportIndirect($uid, $chatId, $msgId = null) {
    setState($uid, 'ticket');
    panelShow($uid, $chatId, 'menu', T('sup_ticket'),
        inlineKb([[btnUI('cancel', 'menu_support', 'cancel')]]));
}

function trBtnLabel($which) { return ['ok' => 'تایید', 'cancel' => 'لغو', 'done' => 'تایید شده'][$which] ?? $which; }

function trBtn($which, $cb = null) {
    $m = cfg()['topup_rules']['btns'][$which] ?? [];
    $b = ['text' => trim((string)($m['text'] ?? trBtnLabel($which)))];
    if ($cb !== null) $b['callback_data'] = $cb;
    if (isStyle($m['color'] ?? '')) $b['style'] = $m['color'];
    if (!empty($m['icon'])) $b['icon_custom_emoji_id'] = (string)$m['icon'];
    return $b;
}

function topupRulesGate($uid, $chatId) {
    if (empty(cfg()['topup_rules']['on'])) return true;
    $u = getUser($uid);
    if (!empty($u['topup_rules_ok'])) return true;

    if (!empty($u['topup_rules_msg'])) {
        sendMsg(BOT_TOKEN, $chatId,
            '📌 تاییدِ قوانین در پیامِ پین‌شده');
        return false;
    }

    $rows = [[trBtn('cancel', 'tr_cancel'), trBtn('ok', 'tr_ok')]];
    $res = sendMsg(BOT_TOKEN, $chatId, (string)cfg()['topup_rules']['text'], inlineKb($rows));
    $mid = (int)($res['result']['message_id'] ?? 0);
    if ($mid) {
        tgAsync(BOT_TOKEN, 'pinChatMessage', ['chat_id' => $chatId, 'message_id' => $mid, 'disable_notification' => true]);
        mutateUser($uid, function (&$user) use ($mid) {
            if ($user !== null) $user['topup_rules_msg'] = $mid;
        });
    }
    return false;
}

function startTopup($uid, $chatId, $replyTo = null) {
    if (!topupRulesGate($uid, $chatId)) return;
    tuStart($uid, $chatId, $replyTo);
}

function gwOn() {
    $g = cfg()['gateway'] ?? [];
    return !empty($g['on']) && gwCleanKey($g['api_key'] ?? '') !== '' && gwCallbackUrl() !== '';
}

function gwCleanKey($k) {
    $k = strtr((string)$k, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                            '‐' => '-', '‑' => '-', '‒' => '-', '–' => '-', '—' => '-', 'ـ' => '']);
    $c = preg_replace('/[\p{Cf}\p{Z}\s]+/u', '', $k);
    return $c === null ? trim($k) : $c;
}

function gwKeyError($k) {
    $k = gwCleanKey($k);
    if ($k === '' || $k === '-') return '';
    if (preg_match('#^(https?://|www\.)|oxapay\.com/|nowpayments\.io/|/#i', $k))
        return 'این یک لینک است، نه کلیدِ API. لینکی مثلِ pay.oxapay.com/… لینکِ پرداختِ ثابت است و شارژِ خودکار با آن ممکن نیست. ' .
               'کلید را از داشبوردِ OxaPay ← Merchant Service ← Generate API Key بسازید (یک رشته‌ی حروف و عدد، بدونِ https).';
    if (ctype_digit($k))
        return 'این فقط یک عدد است (شماره‌ی لینک یا حساب)، نه کلیدِ API. ' .
               'کلیدِ مرچنت را از داشبوردِ OxaPay ← Merchant Service ← Generate API Key بسازید و همان را کامل کپی کنید.';
    if (preg_match('/[^\x21-\x7E]/', $k))
        return 'کلیدِ API فقط حروف و عددِ انگلیسی است؛ در متنی که فرستادید حرفِ فارسی یا نویسه‌ی دیگری هست.';
    if (strlen($k) < 6 || strlen($k) > 200)
        return 'کلیدِ API درست به نظر نمی‌رسد؛ همان کلیدِ Merchant را کامل کپی کنید.';
    return '';
}

function gwCallbackUrl() {
    $g = cfg()['gateway'] ?? [];
    $b = rtrim(trim((string)$g['base_url']), '/');
    if ($b === '' && function_exists('maBaseUrl')) $b = rtrim(maBaseUrl(), '/');
    if ($b === '' || !preg_match('#^https://#i', $b)) return '';
    return $b . (str_contains($b, '?') ? '&' : '?') . 'ipn=1';
}

function gwHttp($url, $headers = [], $body = null, $timeout = 20) {
    if (function_exists('__payHook')) { $hk = __payHook($url, $headers, $body); if ($hk !== null) return $hk; }
    $ch = curl_init($url);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
    ];
    if ($body !== null) { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = json_encode($body); }
    curl_setopt_array($ch, $opt);
    $res  = monCurl($ch, 'gateway');
    $err  = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'error' => $err ?: 'curl error'];
    $j = json_decode($res, true);
    return ['ok' => true, 'data' => is_array($j) ? $j : [], 'raw' => $res];
}

function gwCryptoAmount($toman) {
    $g = cfg()['gateway'] ?? [];
    $coin = strtoupper(trim((string)($g['coin'] ?? 'USDT'))) ?: 'USDT';
    $rate = (float)($g['rate'] ?? 0);
    if ($rate > 0) return [ceil($toman / $rate * 1000000) / 1000000, $coin];
    $live = function_exists('pxUsdtToman') ? (float)pxUsdtToman() : 0;
    if ($live <= 0) return [null, 'USDT'];
    return [ceil($toman / $live * 100) / 100, 'USDT'];
}

function gwNowCur($coin, $net) {
    $coin = strtolower($coin); $net = strtoupper($net);
    if ($coin === 'usdt') {
        $m = ['TRC20' => 'usdttrc20', 'TRON' => 'usdttrc20', 'BEP20' => 'usdtbsc', 'BSC' => 'usdtbsc', 'ERC20' => 'usdterc20',
              'TON' => 'usdtton', 'POLYGON' => 'usdtmatic', 'SOL' => 'usdtsol', 'SOLANA' => 'usdtsol'];
        return $m[$net] ?? 'usdttrc20';
    }
    return $coin;
}

function gwOxa($r) {
    if (empty($r['ok'])) return [null, (string)($r['error'] ?? 'خطای شبکه')];
    $j = (array)$r['data'];
    if (isset($j['data']) && is_array($j['data']) && (int)($j['status'] ?? 200) === 200) return [$j['data'], ''];
    if ((string)($j['result'] ?? '') === '100') return [$j, ''];
    $e = $j['error']['message'] ?? $j['message'] ?? '';
    if (is_array($e)) $e = json_encode($e, JSON_UNESCAPED_UNICODE);
    $e = 'OxaPay: ' . ((string)$e ?: ('کد ' . ($j['status'] ?? $j['result'] ?? '?')));
    if (preg_match('/api.?key.*(invalid|not valid|wrong)|invalid.*api.?key/i', $e))
        $e .= ' — کلیدِ ثبت‌شده را OxaPay قبول نکرد. باید «Merchant API Key» باشد (از داشبورد ← Merchant Service ← Generate API Key)، ' .
              'نه کلیدِ Payout یا General API، نه لینکِ pay.oxapay.com. کلید را دوباره کامل کپی کنید و در پنل بگذارید.';
    return [null, $e];
}

function gwCreateInvoice($orderId, $toman) {
    $g   = cfg()['gateway'] ?? [];
    $cb  = gwCallbackUrl();
    $exp = max(5, (int)($g['expire'] ?? 30));
    [$amt, $cur] = gwCryptoAmount($toman);
    $coin = strtoupper(trim((string)($g['coin'] ?? 'USDT'))) ?: 'USDT';
    $net  = strtoupper(trim((string)($g['network'] ?? '')));
    $prov = strtolower(trim((string)($g['provider'] ?? 'oxapay')));
    $key  = gwCleanKey($g['api_key'] ?? '');

    if ($prov === 'custom') {
        $u = strtr((string)($g['custom_url'] ?? ''), [
            '{amount}'   => (string)$toman,
            '{order}'    => rawurlencode($orderId),
            '{callback}' => rawurlencode($cb),
        ]);
        if (trim($u) === '') return [false, null, 'آدرس درگاه دلخواه تنظیم نشده'];
        return [true, ['url' => $u, 'address' => '', 'amount' => $amt, 'coin' => $coin, 'network' => $net,
                       'expires_at' => time() + $exp * 60, 'invoice' => $orderId], ''];
    }
    if ($amt === null) return [false, null, 'نرخِ تتر معلوم نیست — در تنظیمِ درگاه «نرخ تومان» را بگذارید'];

    if ($prov === 'nowpayments') {
        $h = ['x-api-key: ' . $key];
        $body = ['price_amount' => $amt, 'price_currency' => $cur === 'USDT' ? 'usd' : strtolower($cur),
                 'pay_currency' => gwNowCur($coin, $net), 'order_id' => $orderId, 'order_description' => 'Wallet top-up',
                 'ipn_callback_url' => $cb, 'is_fixed_rate' => true];
        if (($g['mode'] ?? 'address') !== 'page') {
            $r = gwHttp('https://api.nowpayments.io/v1/payment', $h, $body);
            $d = (array)($r['data'] ?? []);
            if (!empty($r['ok']) && !empty($d['pay_address']) && !empty($d['payment_id']))
                return [true, ['url' => '', 'address' => (string)$d['pay_address'], 'amount' => $d['pay_amount'] ?? $amt,
                               'coin' => $coin,
                               'network' => $net, 'expires_at' => time() + $exp * 60,
                               'invoice' => (string)$d['payment_id'], 'kind' => 'payment'], ''];
        }
        $r = gwHttp('https://api.nowpayments.io/v1/invoice', $h, $body);
        if (empty($r['ok'])) return [false, null, (string)$r['error']];
        $d = (array)$r['data'];
        if (empty($d['invoice_url'])) return [false, null, (string)($d['message'] ?? 'پاسخ نامعتبر درگاه')];
        return [true, ['url' => (string)$d['invoice_url'], 'address' => '', 'amount' => $amt, 'coin' => $coin, 'network' => $net,
                       'expires_at' => time() + $exp * 60, 'invoice' => (string)($d['id'] ?? $orderId), 'kind' => 'invoice'], ''];
    }

    $h = ['merchant_api_key: ' . $key];
    $base = ['amount' => $amt, 'currency' => $cur, 'lifetime' => $exp, 'fee_paid_by_payer' => 1,
             'under_paid_coverage' => max(0, min(60, (float)($g['underpaid'] ?? 1))),
             'callback_url' => $cb, 'order_id' => $orderId, 'description' => 'Wallet top-up'];
    $err = '';
    if (($g['mode'] ?? 'address') !== 'page') {
        $wl = $base + ['pay_currency' => $coin];
        if ($net !== '') $wl['network'] = $net;
        [$d, $err] = gwOxa(gwHttp('https://api.oxapay.com/v1/payment/white-label', $h, $wl));
        if (!$d && $net !== '' && stripos($err, 'network') !== false) {
            unset($wl['network']);
            [$d, $err] = gwOxa(gwHttp('https://api.oxapay.com/v1/payment/white-label', $h, $wl));
        }
        if ($d && !empty($d['address'])) {
            return [true, ['url' => '', 'address' => (string)$d['address'],
                           'amount' => $d['pay_amount'] ?? $d['payAmount'] ?? $amt,
                           'coin' => strtoupper((string)($d['pay_currency'] ?? $d['payCurrency'] ?? $coin)),
                           'network' => (string)($d['network'] ?? $net),
                           'expires_at' => (int)($d['expired_at'] ?? $d['expiredAt'] ?? 0) ?: time() + $exp * 60,
                           'invoice' => (string)($d['track_id'] ?? $d['trackId'] ?? $orderId), 'kind' => 'white_label'], ''];
        }
    }
    $iv = $base + ['to_currency' => $coin === 'USDT' ? 'USDT' : $coin, 'mixed_payment' => true, 'sandbox' => false];
    if (($ru = (function_exists('botUsername') && botUsername() !== '') ? 'https://t.me/' . botUsername() : '') !== '') $iv['return_url'] = $ru;
    [$d, $e2] = gwOxa(gwHttp('https://api.oxapay.com/v1/payment/invoice', $h, $iv));
    if (!$d || empty($d['payment_url'] ?? $d['payLink'] ?? ''))
        return [false, null, trim($err . ($err !== '' && $e2 !== '' && $e2 !== $err ? ' · ' : '') . ($e2 !== $err ? $e2 : '')) ?: 'پاسخ نامعتبر درگاه'];
    return [true, ['url' => (string)($d['payment_url'] ?? $d['payLink']), 'address' => '', 'amount' => $amt, 'coin' => $coin,
                   'network' => $net, 'expires_at' => (int)($d['expired_at'] ?? $d['expiredAt'] ?? 0) ?: time() + $exp * 60,
                   'invoice' => (string)($d['track_id'] ?? $d['trackId'] ?? $orderId), 'kind' => 'invoice'], ''];
}

function gwPaidStatus($st) {
    return in_array(strtolower(trim((string)$st)), ['paid', 'manual_accept', 'finished', 'confirmed'], true);
}

function gwCheck($order) {
    $g  = cfg()['gateway'] ?? [];
    $gw = $order['gw'] ?? null;
    if (!$gw || empty($gw['invoice'])) return [false, 'بدون فاکتور'];
    $prov = strtolower(trim((string)($g['provider'] ?? 'oxapay')));

    if ($prov === 'nowpayments') {
        if (($gw['kind'] ?? '') === 'invoice')
            return [false, 'منتظرِ خبرِ درگاه (IPN)'];
        $r = gwHttp('https://api.nowpayments.io/v1/payment/' . rawurlencode($gw['invoice']),
                    ['x-api-key: ' . gwCleanKey($g['api_key'] ?? '')]);
        if (empty($r['ok'])) return [false, $r['error']];
        $st = strtolower((string)($r['data']['payment_status'] ?? ''));
        return [gwPaidStatus($st), $st ?: 'نامشخص'];
    }
    if ($prov === 'custom') return [false, 'در حالت دلخواه، تایید فقط با IPN انجام می‌شود'];

    [$d, $err] = gwOxa(gwHttp('https://api.oxapay.com/v1/payment/' . rawurlencode((string)$gw['invoice']),
                              ['merchant_api_key: ' . gwCleanKey($g['api_key'] ?? '')]));
    if (!$d) return [false, $err];
    $st = strtolower((string)($d['status'] ?? ''));
    return [gwPaidStatus($st), $st ?: 'نامشخص'];
}

function gwSettle($orderId, $note = 'پرداخت خودکار درگاه') {
    $o = Order::get($orderId);
    if (!$o) return false;
    if (in_array($o['status'], [Order::APPROVED, Order::REJECTED], true)) return false;

    Order::attachReceipt($orderId, 'text', $note);
    [$ok, ] = Order::approve($orderId, ADMIN_ID);
    if (!$ok) return false;

    $fresh = Order::get($orderId);
    completeApprovedOrder($fresh);
    if (function_exists('payAfterSettle')) payAfterSettle($fresh);
    return true;
}

function handleIpn() {
    $g   = cfg()['gateway'] ?? [];
    $raw = file_get_contents('php://input');
    $d   = json_decode($raw, true);
    if (!is_array($d)) { http_response_code(400); echo 'bad'; return; }

    $prov = strtolower(trim((string)($g['provider'] ?? 'oxapay')));
    $orderId = ''; $paid = false;

    $secret = $prov === 'nowpayments' ? gwCleanKey($g['ipn_secret'] ?? '') : gwCleanKey($g['api_key'] ?? '');
    if ($prov === 'custom' || strlen($secret) < 6) { http_response_code(403); echo 'off'; return; }

    if ($prov === 'nowpayments') {
        $sig = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';
        $sorted = $d; gwKsortDeep($sorted);
        $calc = hash_hmac('sha512', json_encode($sorted, JSON_UNESCAPED_SLASHES), $secret);
        if (!$sig || !hash_equals($calc, $sig)) { http_response_code(403); echo 'sig'; return; }
        $orderId = (string)($d['order_id'] ?? '');
        $paid = gwPaidStatus($d['payment_status'] ?? '');
    } else {
        $sig = $_SERVER['HTTP_HMAC'] ?? '';
        $calc = hash_hmac('sha512', $raw, $secret);
        if (!$sig || !hash_equals($calc, $sig)) { http_response_code(403); echo 'sig'; return; }
        $orderId = (string)($d['order_id'] ?? $d['orderId'] ?? '');
        $paid = gwPaidStatus($d['status'] ?? '');
        if ($orderId !== '' && ($o0 = Order::get($orderId))) {
            $tid = (string)($d['track_id'] ?? $d['trackId'] ?? '');
            if ($tid !== '' && (string)($o0['gw']['invoice'] ?? '') !== '' && $tid !== (string)$o0['gw']['invoice']) $paid = false;
        }
    }

    if ($orderId === '') { http_response_code(400); echo 'no order'; return; }
    $oi = Order::get($orderId);
    if (!$oi || empty($oi['gw']['invoice']) || (string)($oi['method'] ?? '') === 'iran') { http_response_code(200); echo 'ignored'; return; }
    if ($paid && !gwSettle($orderId)) {
        $now = Order::get($orderId);
        if (($now['status'] ?? '') !== Order::APPROVED) { http_response_code(500); echo 'retry'; return; }
    }
    http_response_code(200);
    echo 'ok';
}

function gwKsortDeep(array &$a) {
    ksort($a);
    foreach ($a as &$v) if (is_array($v)) gwKsortDeep($v);
    unset($v);
}

function ordersArchive($days = 0, $limit = 4000) {
    $days = $days > 0 ? $days : (int)(cfg()['orders_keep_days'] ?? 14);
    if ($days <= 0) return 0;
    $cut = time() - $days * 86400;

    $db = ordersDb();
    if (!$db) return 0;

    $stmt = $db->prepare('SELECT id, data FROM orders WHERE (status = :s1 OR status = :s2) AND created_at < :c');
    $stmt->bindValue(':s1', Order::APPROVED, SQLITE3_TEXT);
    $stmt->bindValue(':s2', Order::REJECTED, SQLITE3_TEXT);
    $stmt->bindValue(':c', date('Y-m-d H:i:s', $cut), SQLITE3_TEXT);
    $res = $stmt->execute();

    $moved = [];
    while (count($moved) < $limit && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
        $o = json_decode($row['data'], true);
        if (!is_array($o)) continue;
        $when = strtotime((string)(($o['decided_at'] ?? '') ?: ($o['created_at'] ?? ''))) ?: 0;
        if ($when === 0 || $when > $cut) continue;
        $moved[(string)$row['id']] = $o;
    }
    if (!$moved) return 0;

    $db->exec('BEGIN');
    $del = $db->prepare('DELETE FROM orders WHERE id = :id');
    $ins = $db->prepare('INSERT OR REPLACE INTO orders_old (id, data) VALUES (:id, :data)');
    foreach ($moved as $id => $o) {
        $del->bindValue(':id', $id, SQLITE3_TEXT); $del->execute(); $del->reset();
        $ins->bindValue(':id', $id, SQLITE3_TEXT);
        $ins->bindValue(':data', json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $ins->execute(); $ins->reset();
    }
    $db->exec('COMMIT');
    return count($moved);
}

function gwPoll($limit = 10) {
    if (!gwOn()) return 0;
    $n = 0;
    foreach (Order::pendingGw($limit) as $o) {
        if ($n >= $limit) break;
        if (time() > (int)($o['gw']['expires_at'] ?? 0) + 3600) continue;
        $n++;
        [$paid, ] = gwCheck($o);
        if ($paid) gwSettle($o['id']);
    }
    return $n;
}

function gwInvoiceText($order) {
    $gw = $order['gw'] ?? [];
    $left = max(0, (int)($gw['expires_at'] ?? 0) - time());
    $mm = (int)floor($left / 60); $ss = $left % 60;

    $t  = "💠 <b>پرداخت خودکار</b>\n\n";
    $t .= "💰 مبلغ: <b>" . fmtNum($order['amount']) . " تومان</b>\n";
    if (!empty($gw['amount'])) $t .= "🪙 معادل: <b>" . $gw['amount'] . ' ' . h($gw['coin'] ?? '') . "</b>\n";
    if (!empty($gw['address'])) {
        $t .= "\n📥 آدرس واریز" . (!empty(cfg()['gateway']['network'])
              ? ' (' . h(cfg()['gateway']['network']) . ')' : '') . ":\n";
        $t .= "<code>" . h($gw['address']) . "</code>\n";
    }
    $t .= "\n⏳ مهلت: <b>" . ($left > 0 ? sprintf('%02d:%02d', $mm, $ss) : 'تمام شد') . "</b>\n";
    $t .= "🧾 کد پیگیری: <code>" . h($order['id']) . "</code>";
    if ($left <= 0) $t .= "\n\n⌛️ مهلت این فاکتور تمام شد.";
    return $t;
}

function gwInvoiceKb($order) {
    $gw = $order['gw'] ?? [];
    $rows = [];
    if (!empty($gw['url'])) $rows[] = [['text' => '💳 صفحه پرداخت', 'url' => $gw['url'],
                                        'style' => gs('buy') ?: null]];
    $rows[] = [btnCb('🔄 بررسی پرداخت', 'gwchk_' . $order['id'], 'confirm')];
    $rows[] = [btnCb(UT('cancel'), 'ocancel_' . $order['id'], 'cancel')];
    return inlineKb($rows);
}

function botUsername() {
    static $u = null;
    if ($u !== null) return $u;
    $c = load('config');
    $tokHash = md5(BOT_TOKEN);
    if (!empty($c['bot_username']) && ($c['bot_username_tok'] ?? '') === $tokHash) return $u = $c['bot_username'];
    $r = tg(BOT_TOKEN, 'getMe', [], 8);
    $u = $r['result']['username'] ?? '';
    if ($u !== '') cfgSet(function (&$x) use ($u, $tokHash) { $x['bot_username'] = $u; $x['bot_username_tok'] = $tokHash; });
    return $u;
}

function adminAlertOnce($key, $text, $everySeconds = 3600) {
    $fresh = mutate('alerts', function (&$a) use ($key, $everySeconds) {
        $last = (int)($a[$key] ?? 0);
        if (time() - $last < $everySeconds) return false;
        $a[$key] = time();
        foreach ($a as $k => $t) if (time() - (int)$t > 86400 * 7) unset($a[$k]);
        return true;
    });
    if ($fresh) {
        if (function_exists('chTechAlert')) chTechAlert($text);
        else notifyAdmins($text);
    }
    return (bool)$fresh;
}

function completeApprovedOrder($order) {
    $uid = (int)$order['user_id'];
    $t = T('topup_ok', ['amount' => fmtNum($order['amount']),
                        'balance' => fmtNum((float)(getUser($uid)['balance'] ?? 0))]);
    if (trim($t) === '') $t = '✅ حساب شما <b>' . fmtNum($order['amount']) . '</b> تومان شارژ شد.';
    sendMsg(BOT_TOKEN, $uid, $t, maOpenKb());
    if (function_exists('chTopup')) chTopup($order);
}

function admHome($chatId, $msgId = null) {
    $text  = "👑 <b>پنل مدیریت</b>\n\n";
    $text .= "☎️ فروش: <b>" . (maReady() && numReady() ? '🟢 باز' : '🔴 بسته') . "</b>";
    $text .= " · باز: <b>" . number_format(MaOrder::countBy(MaOrder::PAID)) . "</b>";
    $text .= " · تحویل‌شده: <b>" . number_format(MaOrder::countBy(MaOrder::DONE)) . "</b>";

    $rows = [
        [btnCb('☎️ شماره مجازی', 'num_home', 'admin'),  btnCb('🚀 مینی‌اپ', 'maadm_home', 'admin')],
        [btnCb('💳 پرداخت', 'ag_pay', 'admin'),         btnCb('📡 کانال‌های گزارش', 'ch_home', 'admin')],
        [btnCb('🎮 بازی‌ها', 'ag_games', 'admin'),      btnCb('💎 الماس', 'dm_home', 'admin')],
        [btnCb('💹 قیمت لحظه‌ای', 'px_home', 'admin'),  btnCb('🎨 ظاهر و متن‌ها', 'ag_look', 'admin')],
        [btnCb('🔒 عضویت اجباری', 'adm_join', 'admin'), btnCb('📢 پیام همگانی', 'adm_bc', 'admin')],
        [btnCb('🎟 کدهای تخفیف', 'cpadm_home', 'admin')],
        [btnCb('🩺 چکاپِ بخش‌ها', 'adm_check', 'info')],
        [btnCb('🌐 پنل وب — کشورها، قیمت‌ها، کاربران، سفارش‌ها', 'adm_web', 'info')],
        [btnCb(UT('home'), 'home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($rows));
}

function admWriteTestText() {
    $d = rtrim(DATA_DIR, '/');
    $t = "🩺 <b>تست نوشتن روی دیسک</b>\n\n";
    $t .= '📁 پوشه: <code>' . h($d) . "</code>\n";
    $t .= (is_dir($d) ? '✅' : '🔴') . " پوشه هست\n";
    $t .= (is_writable($d) ? '✅' : '🔴') . " قابل نوشتن است\n\n";

    $f1 = $d . '/.wtest';
    $ok1 = @file_put_contents($f1, 'x') !== false;
    $t .= ($ok1 ? '✅' : '🔴') . " نوشتن فایل\n";
    @unlink($f1);

    $f2 = $d . '/.wtest.lock';
    $fp = @fopen($f2, 'c');
    $ok2 = ($fp !== false) && flock($fp, LOCK_EX);
    if ($fp) { @flock($fp, LOCK_UN); @fclose($fp); }
    @unlink($f2);
    $t .= ($ok2 ? '✅' : '🔴') . " قفل انحصاری (flock)\n";

    $k3 = 'wtest' . bin2hex(random_bytes(4));
    $ok3 = seenKey($k3) && !seenKey($k3);
    $sdb = seenDb();
    if ($sdb) { $st = $sdb->prepare('DELETE FROM seen WHERE k = :k'); if ($st) { $st->bindValue(':k', $k3, SQLITE3_TEXT); @$st->execute(); } }
    $t .= ($ok3 ? '✅' : '🔴') . " جلوگیری از پیام تکراری\n";

    $n = $sdb ? (int)@$sdb->querySingle('SELECT COUNT(*) FROM seen') : 0;
    $t .= "\n📊 نشانه‌های ثبت‌شده‌ی آپدیت: <b>" . $n . "</b>\n";

    if ($ok1 && $ok2 && $ok3) {
        $t .= "\n✅ <b>همه‌چیز سالم است.</b>\n";
        $t .= 'اگر باز پیام تکراری دیدید، یعنی وبهوک دوبار ثبت شده — ' .
              "از پنلِ وب ← داشبورد ← «ثبتِ دوباره‌ی وبهوک» یک بار دوباره ست کنید.";
    } else {
        $t .= "\n🔴 <b>پوشه‌ی داده قابل نوشتن نیست.</b>\n";
        $t .= "در cPanel → File Manager روی پوشه‌ی <code>data_master</code> راست‌کلیک کنید،\n" .
              "Change Permissions را بزنید و دسترسی را روی <b>755</b> (یا اگر نشد ۷۷۵) بگذارید.\n\n" .
              "تا این درست نشود، پیام تکراری و گم شدن موجودی ادامه دارد.";
    }
    return $t;
}

function admLeakTestText() {
    $base = function_exists('maBaseUrl') ? maBaseUrl() : '';
    if ($base === '') {
        return "⚠️ اول آدرس عمومی را ثبت کنید تا بشود از بیرون امتحان کرد.\n\n" .
               "پنلِ وب ← API و اتصال‌ها ← آدرسِ عمومی";
    }
    $dir  = basename(rtrim(DATA_DIR, '/'));
    $root = preg_replace('#/[^/]+$#', '', $base);

    $t = "🔒 <b>تست نشتی داده</b>\n\nاز بیرون امتحان می‌کنم که فایل‌های حساس خوانده می‌شوند یا نه…\n\n";
    $leaks = [];
    foreach (['config.json', 'users.sqlite', 'orders.sqlite'] as $f) {
        $url = $root . '/' . $dir . '/' . $f;
        [$body, $err] = maHttpRaw($url, 8);
        $open = is_string($body) && strlen($body) > 2 &&
                (str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '[') ||
                 str_starts_with($body, 'SQLite format 3'));
        if ($open) $leaks[] = $f;
        $t .= ($open ? '🔴' : '✅') . ' <code>' . h($dir . '/' . $f) . '</code>' .
              ($open ? ' — <b>از بیرون باز است!</b>' : ' — بسته') . "\n";
    }

    $isLog = fn($b) => is_string($b) && preg_match('/^\[\d{1,2}-\w{3}-\d{4}|PHP (Warning|Notice|Fatal|Parse|Deprecated)|\[shop-bot\]/m', $b);
    foreach (['error_log' => $root . '/error_log', $dir . '/php_errors.log' => $root . '/' . $dir . '/php_errors.log'] as $lbl => $url) {
        [$body, ] = maHttpRaw($url, 8);
        $open = $isLog($body);
        if ($open) $leaks[] = $lbl;
        $t .= ($open ? '🔴' : '✅') . ' <code>' . h($lbl) . '</code>' . ($open ? ' — <b>لاگِ خطا از بیرون خوانده می‌شود!</b>' : ' — بسته') . "\n";
    }
    [$body, ] = maHttpRaw($root . '/config.local.php', 8);
    $open = is_string($body) && (str_contains($body, 'BOT_TOKEN') || str_contains($body, '<?php'));
    if ($open) $leaks[] = 'config.local.php';
    $t .= ($open ? '🔴' : '✅') . ' <code>config.local.php</code>' . ($open ? ' — <b>سورس و توکن دیده می‌شود! PHP اجرا نمی‌شود</b>' : ' — اجرا می‌شود، چیزی دیده نمی‌شود') . "\n";

    if ($leaks) {
        if (in_array('error_log', $leaks, true))
            $t .= "\n🗑 فایلِ <code>error_log</code> کنارِ اسکریپت‌ها را از هاست پاک کنید؛ از این به بعد لاگ داخلِ پوشه‌ی داده نوشته می‌شود.\n";
        $t .= "\n🚨 <b>همین حالا باید بسته شود.</b>\n";
        $t .= "این فایل‌ها شماره کارت، کلید API، و موجودی همه‌ی کاربران را دارند.\n\n";
        $t .= "<b>اگر سرورتان nginx است</b>، این را به کانفیگ اضافه کنید:\n";
        $t .= "<code>location ~ /" . h($dir) . "/ { deny all; return 404; }</code>\n\n";
        $t .= "<b>اگر آپاچی است</b> و باز هم باز مانده، یعنی <code>AllowOverride</code> " .
              "خاموش است — از پشتیبانی هاست بخواهید روشنش کند.\n\n";
        $t .= "<b>راه مطمئن‌تر:</b> پوشه‌ی داده را کلا بیرون از ریشه‌ی وب ببرید و در " .
              "<code>config.local.php</code> بنویسید:\n" .
              "<code>define('DATA_DIR', '/home/user/private/data_master');</code>";
    } else {
        $t .= "\n✅ هیچ‌کدام از بیرون خوانده نمی‌شوند. پوشه‌ی داده امن است.";
    }
    return $t;
}

function admGroups() {
    return [
        'pay' => ['💳 <b>پرداخت و شارژِ حساب</b>', '', [
            [['💳 شارژِ حساب — وضعیتِ همه', 'payx_home']],
            [['🪪 احراز هویت‌ها', 'payx_kyc']],
            [['✏️ متن و دکمه‌های شارژ', 'payx_ed']],
        ]],
        'look' => ['🎨 <b>ظاهر و متن‌ها</b>', '', [
            [['🎨 دکمه‌ها', 'ebuttons'], ['📝 متن‌ها', 'etexts']],
            [['✨ ایموجی‌های پریمیوم', 'epm']],
            [['🛍 پیام و دکمه‌ی فروشگاه', 'eshop']],
            [['💠 رنگ دکمه‌های شیشه‌ای', 'eglass']],
            [['🔤 فونت‌ها', 'fnt_home']],
            [['📞 دکمه‌های پشتیبانی', 'esup']],
            [['📋 قوانین شارژ', 'etop_home'], ['🔗 دکمه‌ی زیرِ «اعتماد»', 'etrust']],
            [['👥 دکمه‌های زیرمجموعه', 'eref_home']],
        ]],
        'games' => ['🎮 <b>بازی‌ها</b>', '', [
            [['🎮 چالش و دوز', 'gm_home'], ['💣 مین‌یاب', 'mn_home']],
            [['🏆 تاپ الماسی', 'tp_home']],
            [['🏦 بانک الماس', 'bk_home']],
            [['🧠 چالش روزانه', 'qz_home'], ['🌐 ترجمه', 'tl_home']],
            [['🎡 گردونه شانس', 'whp_home'], ['🎁 ایردراپ', 'adadm_home']],
            [['💬 پاسخ خودکار گروه', 'arp_home']],
        ]],
    ];
}

function admGroup($chatId, $msgId, $key) {
    $g = admGroups()[$key] ?? null;
    if (!$g) { admHome($chatId, $msgId); return; }
    [$title, $desc, $rows] = $g;

    $kb = [];
    foreach ($rows as $row) {
        $line = [];
        foreach ($row as [$label, $data]) $line[] = btnCb($label, $data, 'admin');
        $kb[] = $line;
    }
    $kb[] = [btnCb(UT('back'), 'adm_home', 'nav')];

    $text = $title . ($desc !== '' ? "\n\n" . $desc : '');
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($kb));
    else sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($kb));
}

function edPremium($chatId, $msgId = null) {
    $map = emMap(true);
    $t = "✨ <b>ایموجی‌های پریمیوم</b>\n\n<b>" . count($map) . "</b>";
    if ($map) {
        $t .= "\n\n";
        $i = 0;
        foreach ($map as $plain => $id) {
            if (++$i > 90) break;
            $t .= '<tg-emoji emoji-id="' . h($id) . '">' . h($plain) . '</tg-emoji>';
        }
    }
    $rows = [[btnCb('➕ افزودن', 'epm_add', 'confirm')]];
    if (!empty(cfg()['premium_map'])) $rows[] = [btnCb('🗑 پاک کردنِ افزوده‌ها', 'epm_clr', 'reject')];
    $rows[] = [btnUI('back', 'ag_look', 'nav')];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function premiumLearn($msg) {
    $text = (string)($msg['text'] ?? $msg['caption'] ?? '');
    $u16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    $out = [];
    foreach (($msg['entities'] ?? $msg['caption_entities'] ?? []) as $en) {
        if (($en['type'] ?? '') !== 'custom_emoji' || empty($en['custom_emoji_id'])) continue;
        $ch = mb_convert_encoding(substr($u16, (int)$en['offset'] * 2, (int)$en['length'] * 2), 'UTF-8', 'UTF-16LE');
        $k = emKey(trim($ch));
        if ($k !== '' && emHas($k)) $out[$k] = (string)$en['custom_emoji_id'];
    }
    return $out;
}

function admJoin($chatId, $msgId) {
    $j  = cfg()['join'] ?? [];
    $ch = array_values((array)($j['channels'] ?? []));
    $text  = "🔒 <b>عضویت اجباری</b>\n\n";
    $text .= "وضعیت: " . (!empty($j['on']) ? '✅ روشن' : '❌ خاموش') . " · کانال‌ها: <b>" . count($ch) . "</b>\n\n";
    foreach ($ch as $i => $c)
        $text .= ($i + 1) . '. <b>' . h((string)($c['title'] ?? $c['chat_id'])) . '</b> — <code>' . h((string)$c['chat_id']) . "</code>\n";

    $rows = [[btnCb(!empty($j['on']) ? '✅ روشن است' : '❌ خاموش است', 'jno', 'info'),
              btnCb('➕ افزودن کانال', 'jna', 'confirm')]];
    foreach ($ch as $i => $c)
        $rows[] = [btnCb('🗑 ' . mb_substr((string)($c['title'] ?? $c['chat_id']), 0, 28), 'jnd_' . substr(md5((string)($c['chat_id'] ?? '')), 0, 12), 'reject')];
    $rows[] = [btnCb('✏️ متن قفل', 'jnt', 'admin'), btnCb('🔘 متن دکمه', 'jnb', 'admin')];
    $rows[] = [btnUI('back', 'adm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function joinChannelRef($msg) {
    $f = $msg['forward_origin']['chat'] ?? ($msg['forward_from_chat'] ?? null);
    if (is_array($f) && !empty($f['id'])) return (string)$f['id'];
    $t = trim((string)($msg['text'] ?? ''));
    if (preg_match('#^(?:https?://)?(?:t\.me|telegram\.me)/([A-Za-z][A-Za-z0-9_]{3,31})/?$#i', $t, $m)) return '@' . $m[1];
    if (preg_match('/^@?([A-Za-z][A-Za-z0-9_]{3,31})$/', $t, $m)) return '@' . $m[1];
    if (preg_match('/^-100\d{5,15}$/', $t)) return $t;
    return '';
}

function joinAddChannel($cid, $title = '', $url = '') {
    $info = tg(BOT_TOKEN, 'getChat', ['chat_id' => $cid], 8);
    if (empty($info['ok'])) return [false, 'ربات به این کانال دسترسی ندارد: ' . ($info['description'] ?? '—')];
    $r  = $info['result'];
    $id = (string)($r['id'] ?? $cid);
    $me = tg(BOT_TOKEN, 'getChatMember', ['chat_id' => $id, 'user_id' => (int)strtok(BOT_TOKEN, ':')], 8);
    if (!in_array($me['result']['status'] ?? '', ['administrator', 'creator'], true))
        return [false, 'اول ربات را در این کانال ادمین کنید تا بتواند عضویت را چک کند.'];
    $title = trim((string)$title) ?: (string)($r['title'] ?? $id);
    $url   = trim((string)$url);
    if ($url === '' && !empty($r['username'])) $url = 'https://t.me/' . $r['username'];
    if ($url === '') {
        $inv = tg(BOT_TOKEN, 'exportChatInviteLink', ['chat_id' => $id], 8);
        $url = (string)($inv['result'] ?? '');
    }
    $keys = [$id, (string)$cid];
    if (!empty($r['username'])) $keys[] = '@' . $r['username'];
    $dup = false;
    cfgSet(function (&$c) use ($id, $title, $url, $keys, &$dup) {
        if (!is_array($c['join']['channels'] ?? null)) $c['join']['channels'] = [];
        foreach ($c['join']['channels'] as $x) if (in_array((string)($x['chat_id'] ?? ''), $keys, true)) { $dup = true; return; }
        $c['join']['channels'][] = ['chat_id' => $id, 'title' => $title, 'url' => $url];
        $c['join']['on'] = true;
    });
    return $dup ? [false, 'این کانال از قبل در فهرست است.'] : [true, $title];
}

function textLabels() {
    return [
        'welcome' => '👋 خوش‌آمد', 'account' => '👤 حساب کاربری',
        'trust' => '💚 اعتماد', 'support' => '📞 سربرگ پشتیبانی',
        'referral' => '👥 زیرمجموعه', 'referral_share' => '📤 متن ارسال لینک دعوت',
        'topup_off' => '➕ شارژ در دسترس نیست',
        'shop' => '☎️ متنِ دکمه‌ی خرید (ثبت سفارش)', 'shop_closed' => '🔒 فروش بسته است',
        'coupon_ask' => '🏷 کد تخفیف — درخواست کد', 'coupon_ok' => '🏷 کد تخفیف — فعال شد',
        'coupon_bad' => '🏷 کد تخفیف — قبول نشد',
        'track_ask' => '📊 پیگیری سفارش — درخواست کد', 'track_recent' => '📊 پیگیری سفارش — فهرست کدهای اخیر',
        'track_bad' => '📊 پیگیری سفارش — کد پیدا نشد', 'order_status' => '📊 پیگیری سفارش — وضعیت سفارش',
        'no_balance' => '❌ موجودی کم',
        'banned' => '🚫 کاربر مسدود', 'topup_ok' => '✅ متن شارژ شدن حساب',
        'sup_ticket' => '💬 پیام ارتباط غیر مستقیم', 'sup_sent' => '✅ پیام ارسال شد',
    ] + (function_exists('payTextLabels') ? payTextLabels() : []);
}

function uiTextLabels() {
    return [
        'back' => 'بازگشت', 'home' => 'منوی اصلی', 'cancel' => 'انصراف',
        'confirm' => 'تایید', 'reject' => 'رد', 'panel' => 'پنل',
        'topup' => 'افزایش موجودی',
        'my_orders' => 'پیگیری سفارش (داخلِ حساب کاربری)', 'open_app' => 'شماره مجازی (پایینِ ثبت سفارش)',
        'trk_refresh' => 'به‌روزرسانی وضعیت (زیرِ وضعیت سفارش)',
        'coupon' => 'وارد کردن کد تخفیف (زیرِ دکمه‌های ثبت سفارش)',
        'open' => 'باز کردن',
        'open_tgs' => 'خدمات تلگرام (بالای ثبت سفارش)',
        'open_igs' => 'خدمات اینستاگرام (بالای ثبت سفارش)',
    ];
}

function glassRoleLabels() {
    return [
        'buy' => '☎️ خرید و شارژ', 'confirm' => '✅ تایید', 'cancel' => '↩️ انصراف',
        'reject' => '🗑 رد و حذف', 'nav' => '◀️ بازگشت و منو', 'info' => 'ℹ️ اطلاعات',
        'link' => '📱 باز کردن فروشگاه و لینک‌ها',
    ];
}

function nextStyle($cur) {
    $keys = array_keys(styleMap());
    $i = array_search($cur, $keys, true);
    return $keys[(($i === false ? 0 : $i) + 1) % count($keys)];
}

function edButtons($chatId, $msgId) {
    $c = cfg();
    $text  = "🎨 <b>ویرایش دکمه‌ها</b>\n\n";
    $text .= "حالت: <b>" . ($c['ui']['mode'] === 'glass' ? 'شیشه‌ای' : 'منو') . "</b>\n";
    $text .= "کیبورد چسبان: <b>" . (!empty($c['ui']['persistent']) ? 'روشن' : 'خاموش') . "</b>";

    $rows = [];
    foreach ($c['buttons'] as $id => $b) {
        $col = styleMap()[$b['color'] ?? 'none'] ?? '';
        $rows[] = [btnCb((!empty($b['on']) ? '✅ ' : '❌ ') . btnLabel($b) . '  ' . mb_substr($col, 0, 2),
                         'eb_' . $id, 'info')];
    }
    $rows[] = [
        btnCb($c['ui']['mode'] === 'glass' ? '🔄 به منو' : '🔄 به شیشه‌ای', 'ebmode', 'admin'),
        btnCb('📐 چیدمان', 'eblay', 'admin'),
    ];
    $rows[] = [
        btnCb(!empty($c['ui']['persistent']) ? '📌 چسبان: روشن' : '📌 چسبان: خاموش', 'ebpin', 'admin'),
        btnCb('➕ دکمه جدید', 'ebnew', 'confirm'),
    ];
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function edButton($chatId, $msgId, $id) {
    $b = cfg()['buttons'][$id] ?? null;
    if (!$b) { edButtons($chatId, $msgId); return; }

    $text  = "🎨 <b>" . h(btnLabel($b)) . "</b>\n\n";
    $text .= "رنگ: " . (styleMap()[$b['color'] ?? 'none'] ?? '—') . "\n";
    $text .= "✨ پریمیوم: " . (!empty($b['icon']) ? '<code>' . h($b['icon']) . '</code>' : '—') . "\n";
    $text .= "ردیف: " . (int)($b['row'] ?? 0) . "  |  ترتیب: " . (int)($b['order'] ?? 0) . "\n";
    $text .= "وضعیت: " . (!empty($b['on']) ? '✅ روشن' : '❌ خاموش');
    if (!empty($b['action'])) {
        $text .= "\nنوع: " . h(['text' => 'متن', 'url' => 'لینک'][$b['action']] ?? $b['action']);
    }

    $rows = [
        [btnCb('✏️ متن', 'ebt_' . $id, 'admin'), btnCb('✨ پریمیوم', 'ebi_' . $id, 'admin')],
        [btnCb('🎨 رنگ', 'ebc_' . $id, 'admin')],
        [btnCb('📐 ردیف', 'ebr_' . $id, 'admin'), btnCb('🔢 ترتیب', 'ebo_' . $id, 'admin')],
        [btnCb(!empty($b['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'ebx_' . $id, 'info')],
    ];
    if (!empty($b['action'])) {
        $rows[] = [btnCb('📝 مقدار', 'ebv_' . $id, 'admin'), btnCb('🗑 حذف دکمه', 'ebd_' . $id, 'reject')];
    }
    $tk = btnTextKey($id);
    if ($tk !== '') $rows[] = [btnCb('📝 متنی که این دکمه نشان می‌دهد', 'et_' . $tk, 'confirm')];
    if ($id === 'buy') $rows[] = [btnCb('📱 متن دکمه‌ی ورود به مینی‌اپ', 'eu_open_app', 'confirm')];
    $rows[] = [btnCb(UT('back'), 'ebuttons', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function btnTextKey($id) {
    return [
        'buy' => 'shop', 'account' => 'account', 'topup' => 'topup', 'referral' => 'referral',
        'orders' => 'track_ask', 'support' => 'support', 'trust' => 'trust',
    ][$id] ?? '';
}

function edSupMain($chatId, $msgId) {
    $c = cfg()['support_main'] ?? [];
    $rows = [];
    foreach (['direct' => 'esup_direct', 'indirect' => 'esup_indirect', 'group' => 'esup_group'] as $k => $cb) {
        $b = supMainBtn($k, $cb);
        unset($b['url']);
        $b['callback_data'] = $cb;
        $rows[] = [$b];
    }
    $rows[] = [btnUI('back', 'ag_look', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, "📞 <b>دکمه‌های پشتیبانی</b>", inlineKb($rows));
}

function edSupOne($chatId, $msgId, $which) {
    if (!in_array($which, ['direct', 'indirect', 'group'], true)) { edSupMain($chatId, $msgId); return; }
    $m   = cfg()['support_main'][$which] ?? [];
    $lbl = ['direct' => 'ارتباط مستقیم', 'indirect' => 'ارتباط غیر مستقیم', 'group' => 'گروه'][$which];

    $t  = "🔘 <b>" . $lbl . "</b>\n\n";
    $t .= 'رنگ: ' . (styleMap()[$m['color'] ?? 'none'] ?? '—') . "\n";
    if ($which === 'direct' || $which === 'group')
        $t .= 'لینک: ' . (trim((string)($m['value'] ?? '')) !== ''
              ? '<code>' . h($m['value']) . '</code>' : '—') . "\n";

    $prev = supMainBtn($which, 'esup_' . $which);
    unset($prev['url']);
    $prev['callback_data'] = 'esup_' . $which;
    $rows = [
        [$prev],
        [btnCb('✏️ متن', 'esupt_' . $which, 'admin'), btnCb('✨ پریمیوم', 'esupi_' . $which, 'admin')],
        [btnCb('🎨 رنگ', 'esupc_' . $which, 'admin')],
    ];
    if ($which === 'direct' || $which === 'group') $rows[] = [btnCb('🔗 لینک', 'esupu_' . $which, 'admin')];
    $rows[] = [btnUI('back', 'esup', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function edRefButtons($chatId, $msgId) {
    $rows = [];
    foreach (['link', 'hist', 'wallet', 'share'] as $k) $rows[] = [refBtn($k, 'eref_' . $k)];
    $rows[] = [btnUI('back', 'ag_look', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, "👥 <b>دکمه‌های زیرمجموعه</b>", inlineKb($rows));
}

function edRefButtonOne($chatId, $msgId, $which) {
    if (!in_array($which, ['link', 'hist', 'wallet', 'share'], true)) { edRefButtons($chatId, $msgId); return; }
    $m   = cfg()['referral']['btns'][$which] ?? [];
    $lbl = ['link' => 'ساخت لینک دعوت', 'hist' => 'تاریخچه پورسانت', 'wallet' => 'برداشت پورسانت',
            'share' => 'ارسال برای دوستان'][$which];

    $t  = "🔘 <b>" . h($lbl) . "</b>\n\n";
    $t .= 'رنگ: ' . (styleMap()[$m['color'] ?? 'none'] ?? '—');

    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [refBtn($which, 'eref_' . $which)],
        [btnCb('✏️ متن', 'erefbt_' . $which, 'admin'), btnCb('✨ پریمیوم', 'erefbi_' . $which, 'admin')],
        [btnCb('🎨 رنگ', 'erefbc_' . $which, 'admin')],
        [btnUI('back', 'eref_home', 'nav')],
    ]));
}

function edTopupRules($chatId, $msgId) {
    $c = cfg()['topup_rules'] ?? [];
    $t  = "📋 <b>قوانین افزایش موجودی</b>\n\n";
    $t .= 'وضعیت: ' . (!empty($c['on']) ? '✅ روشن' : '❌ خاموش') . "\n\n";
    $t .= (string)($c['text'] ?? '');

    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb([
        [btnCb(!empty($c['on']) ? '✅ روشن' : '❌ خاموش', 'etopx', 'info')],
        [btnCb('✏️ متن قوانین', 'etopm', 'admin')],
        [btnCb('🔘 دکمه‌ی لغو', 'etopb_cancel', 'admin'), btnCb('🔘 دکمه‌ی تایید', 'etopb_ok', 'admin')],
        [btnCb('🔘 دکمه‌ی «تایید شده»', 'etopb_done', 'admin')],
        [btnUI('back', 'ag_look', 'nav')],
    ]));
}

function edTopupRulesBtn($chatId, $msgId, $which) {
    if (!in_array($which, ['ok', 'cancel', 'done'], true)) { edTopupRules($chatId, $msgId); return; }
    $m   = cfg()['topup_rules']['btns'][$which] ?? [];
    $lbl = trBtnLabel($which);

    $t  = "🔘 <b>" . h($lbl) . "</b>\n\n";
    $t .= 'رنگ: ' . (styleMap()[$m['color'] ?? 'none'] ?? '—');

    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [trBtn($which, 'etopb_' . $which)],
        [btnCb('✏️ متن', 'etopbt_' . $which, 'admin'), btnCb('✨ پریمیوم', 'etopbi_' . $which, 'admin')],
        [btnCb('🎨 رنگ', 'etopbc_' . $which, 'admin')],
        [btnUI('back', 'etop_home', 'nav')],
    ]));
}

function edTexts($chatId, $msgId, $page = 0) {
    $labels = textLabels();
    $keys = array_keys($labels);
    $per = 8;
    $slice = array_slice($keys, $page * $per, $per);

    $rows = [];
    foreach ($slice as $k) $rows[] = [btnCb($labels[$k], 'et_' . $k, 'info')];

    $nav = [];
    if ($page > 0) $nav[] = btnCb('⬅️ قبلی', 'ets_' . ($page - 1), 'nav');
    if (($page + 1) * $per < count($keys)) $nav[] = btnCb('بعدی ➡️', 'ets_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb('🔤 متن دکمه‌های ثابت', 'euis', 'admin')];
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];

    editMsg(BOT_TOKEN, $chatId, $msgId,
        "📝 <b>متن‌های ربات</b>",
        inlineKb($rows));
}

function textVars($key) {
    return [
        'welcome'      => '{name}',
        'account'      => '{id} {name} {username} {balance} {orders} {referrals} {ref_earned} {joined}',
        'referral'     => '{percent} {referrals} {ref_earned} {ref_pending}',
        'shop'         => '{balance}',
        'no_balance'   => '{balance}',
        'track_ask'    => '{recent}',
        'coupon_ask'   => '{code} {value}',
        'coupon_ok'    => '{code} {value} {exp}',
        'coupon_bad'   => '{reason}',
        'track_recent' => '{list}',
        'track_bad'    => '{code}',
        'order_status' => '{code} {status} {product} {link} {qty} {phone} {sms} {progress} {amount} {currency} {created}',
    ][$key] ?? ((function_exists('payTextVars') ? payTextVars() : [])[$key] ?? '');
}

function edText($chatId, $msgId, $key) {
    $labels = textLabels();
    if (!isset($labels[$key])) { edTexts($chatId, $msgId); return; }
    $cur = tplLegacy((string)(cfg()['texts'][$key] ?? ''));
    $isQuoted = str_starts_with(trim($cur), '<blockquote');

    $text  = "📝 <b>" . h($labels[$key]) . "</b>\n\n";
    $text .= "<b>پیش‌نمایش:</b>\n" . ($cur !== '' ? $cur : '<i>خالی</i>') . "\n\n";
    $text .= "<b>کد:</b>\n<code>" . h(mb_substr($cur, 0, 700)) . "</code>";
    if ($v = textVars($key)) $text .= "\n\n<b>متغیرها:</b>\n<code>" . h($v) . "</code>";

    $rows = [
        [btnCb('✏️ تغییر متن', 'ete_' . $key, 'confirm')],
        [btnCb($isQuoted ? '❝ حذف نقل‌قول' : '❝ نقل‌قول', 'etq_' . $key, 'admin'),
         btnCb('❝ نقل‌قول بازشو', 'etx_' . $key, 'admin')],
        [btnCb('♻️ بازگردانی پیش‌فرض', 'etr_' . $key, 'reject')],
        [btnCb(UT('back'), 'etexts', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function edUiTexts($chatId, $msgId, $page = 0) {
    $labels = uiTextLabels();
    $keys = array_keys($labels);
    $per = 9;
    $slice = array_slice($keys, $page * $per, $per);

    $rows = [];
    foreach ($slice as $k) {
        $col = UC($k) ? (styleMap()[UC($k)] ?? '') : '—';
        $rows[] = [
            btnCb(UT($k), 'eu_' . $k, 'info'),
            btnCb('🎨 ' . mb_substr($col, 0, 2), 'euc_' . $k, 'admin'),
        ];
    }
    $nav = [];
    if ($page > 0) $nav[] = btnCb('⬅️ قبلی', 'eus_' . ($page - 1), 'nav');
    if (($page + 1) * $per < count($keys)) $nav[] = btnCb('بعدی ➡️', 'eus_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb(UT('back'), 'etexts', 'nav')];

    editMsg(BOT_TOKEN, $chatId, $msgId,
        "🔤 <b>متن دکمه‌های ثابت</b>",
        inlineKb($rows));
}

function shopBtnKeys() {
    return ['open_tgs' => 'بالا (تلگرام)', 'open_igs' => 'بالا (اینستاگرام)', 'open_app' => 'پایین', 'coupon' => 'کد تخفیف'];
}

function edShop($chatId, $msgId) {
    $text = "🛍 <b>پیام و دکمه‌های «ثبت سفارش»</b>\n\n" . T('shop', ['balance' => fmtNum(0)]) . "\n\n";
    foreach (shopBtnKeys() as $k => $pos) {
        $st = UC($k);
        $text .= '• <b>' . h($pos) . ':</b> ' . h(UT($k)) . ' — ' . h($st ? (string)(styleMap()[$st] ?? $st) : 'هم‌رنگ لینک‌ها') .
                 (UI($k) !== '' ? ' ✨' : '') . "\n";
    }
    $rows = [];
    $prev = function_exists('svShopKb') ? svShopKb(ADMIN_ID) : null;
    if ($prev) {
        foreach ($prev['inline_keyboard'] as $r) $rows[] = $r;
    }
    $rows[] = [btnCb('✏️ متن پیام', 'et_shop', 'confirm')];
    foreach (shopBtnKeys() as $k => $pos)
        $rows[] = [btnCb('🔤 ' . $pos . ': متن', 'eshopb_' . $k, 'confirm'), btnCb('🎨 ' . $pos . ': رنگ', 'eshopc_' . $k, 'admin')];
    $rows[] = [btnUI('back', 'ag_look', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function edGlass($chatId, $msgId) {
    $c = cfg()['glass_colors'];
    $rows = [];
    foreach (glassRoleLabels() as $role => $lbl) {
        $rows[] = [btnCb($lbl . ' — ' . (styleMap()[$c[$role] ?? 'none'] ?? ''), 'egc_' . $role, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "💠 <b>رنگ دکمه‌های شیشه‌ای</b>", inlineKb($rows));
}

function trustBtn() {
    $b   = cfg()['trust_btn'] ?? [];
    $url = trim((string)($b['url'] ?? ''));
    if ($url === '') return null;
    $btn = ['text' => trim((string)($b['text'] ?? '')) ?: '📢 کانال گزارشات', 'url' => $url];
    if (isStyle($b['color'] ?? '')) $btn['style'] = $b['color'];
    if (trim((string)($b['icon'] ?? '')) !== '') $btn['icon_custom_emoji_id'] = (string)$b['icon'];
    return $btn;
}

function trustKb() {
    $b = trustBtn();
    return $b ? inlineKb([[$b]]) : null;
}

function trustUrl($raw) {
    $u = trim((string)$raw);
    if (preg_match('/^@([A-Za-z0-9_]{4,32})$/', $u, $m)) return 'https://t.me/' . $m[1];
    if (preg_match('#^(?:https?://)?(?:t\.me|telegram\.me)/(\S+)$#i', $u, $m)) return 'https://t.me/' . $m[1];
    return preg_match('#^https://\S+$#i', $u) ? $u : '';
}

function edTrustBtn($chatId, $msgId) {
    $b   = cfg()['trust_btn'] ?? [];
    $url = trim((string)($b['url'] ?? ''));
    $t  = "🔗 <b>دکمه‌ی زیرِ «اعتماد»</b>\n\n";
    $t .= "زیرِ متنِ «چطوری می‌توانم به شما اعتماد کنم» یک دکمه‌ی شیشه‌ای می‌نشیند که کاربر را " .
          "به کانالِ گزارش‌ها می‌برد.\n\n";
    $t .= '🔤 متن: <b>' . h((string)($b['text'] ?? '')) . "</b>\n";
    $t .= '🔗 لینک: ' . ($url !== '' ? '<code>' . h($url) . '</code>' : '<b>ثبت نشده</b> — تا لینک ندهید دکمه دیده نمی‌شود') . "\n";
    $t .= '🎨 رنگ: ' . (styleMap()[$b['color'] ?? 'none'] ?? styleMap()['none']) . "\n";
    $t .= '✨ ایموجی پریمیوم: ' . (trim((string)($b['icon'] ?? '')) !== '' ? 'دارد' : 'ندارد');
    if ($url !== '') $t .= "\n\n👇 پیش‌نمایش:";

    $rows = [];
    if ($prev = trustBtn()) $rows[] = [$prev];
    $rows[] = [btnCb('✏️ متن و ایموجی پریمیوم', 'etrust_t', 'admin'), btnCb('🔗 لینک', 'etrust_u', 'admin')];
    $rows[] = [btnCb('🎨 رنگ: ' . (styleMap()[$b['color'] ?? 'none'] ?? ''), 'etrust_c', 'info')];
    if ($url !== '') $rows[] = [btnCb('🗑 برداشتنِ دکمه', 'etrust_x', 'reject')];
    $rows[] = [btnUI('back', 'ag_look', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function masterJoinMissing($uid, $fresh = false) {
    $j = cfg()['join'] ?? [];
    if (empty($j['on']) || empty($j['channels'])) return [];
    if (isAdmin($uid)) return [];

    $ids = [];
    foreach ($j['channels'] as $ch) {
        $cid = trim((string)($ch['chat_id'] ?? ''));
        if ($cid !== '') $ids[] = $cid;
    }
    JoinCheck::warmMembership($ids, $uid, $fresh);

    $missing = [];
    foreach ($j['channels'] as $ch) {
        $cid = trim((string)($ch['chat_id'] ?? ''));
        if ($cid === '') continue;
        $r = JoinCheck::isMemberOf($cid, $uid, $fresh);
        if (!$r['ok'] || !$r['member']) {
            $missing[] = ['title' => $ch['title'] ?? $cid, 'url' => $ch['url'] ?? '', 'chat_id' => $cid];
        }
    }
    return $missing;
}

function masterJoinGate($uid, $chatId, $missing) {
    $j = cfg()['join'] ?? [];

    $b = ['text' => trim((string)($j['btn']['text'] ?? '')) ?: 'عضو شدم', 'callback_data' => 'mjchk'];
    if (isStyle($j['btn']['color'] ?? '')) $b['style'] = $j['btn']['color'];
    if (!empty($j['btn']['icon'])) $b['icon_custom_emoji_id'] = (string)$j['btn']['icon'];

    $text = (string)($j['text'] ?? '🔒 <b>عضویت در کانال‌ها</b>');

    if (str_contains($text, '{channels}')) {
        $list = '';
        foreach ($missing as $m) {
            $url = trim((string)($m['url'] ?? ''));
            if ($url === '' && str_starts_with((string)$m['chat_id'], '@'))
                $url = 'https://t.me/' . ltrim((string)$m['chat_id'], '@');
            $list .= "\n📣 " . ($url !== ''
                     ? '<a href="' . h($url) . '">' . h((string)$m['title']) . '</a>'
                     : h((string)$m['title']));
        }
        $text = str_replace('{channels}', ltrim($list, "\n"), $text);
    }

    sendMsg(BOT_TOKEN, $chatId, $text, inlineKb([[$b]]));
}

function masterHandle($update) {
    if (!shGuard($update)) return;
    maHealQuick();

    if (isset($update['callback_query'])) {
        $cb     = $update['callback_query'];
        $uid    = (int)$cb['from']['id'];
        $chatId = $cb['message']['chat']['id'] ?? $uid;
        $msgId  = $cb['message']['message_id'] ?? null;
        $data   = $cb['data'] ?? '';
        $uname  = $cb['from']['username'] ?? '';
        $fname  = $cb['from']['first_name'] ?? '';
        $cbId   = $cb['id'];
        $isAdmin = isAdmin($uid);

        if (!$isAdmin && ($bu = getUser($uid)) && !empty($bu['banned'])) { answerCb(BOT_TOKEN, $cbId, T('banned'), true); return; }

        if (gmCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (function_exists('dmCallback') && dmCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (function_exists('bkCallback') && bkCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (function_exists('mnCallback') && mnCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (function_exists('qzCallback') && qzCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;


        if (function_exists('whCallback') && whCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (tlCallback($cb)) return;

        if (function_exists('tpCallback') && tpCallback($data, $uid, $chatId, $msgId, $cbId, $cb['from'] ?? [])) return;

        if (($cb['message']['chat']['type'] ?? 'private') !== 'private' && str_starts_with($data, 'reply_')
            && function_exists('maSupPrompt') && maSupPrompt($cb, $uid, $cbId)) return;

        if (($cb['message']['chat']['type'] ?? 'private') !== 'private') {
            answerCb(BOT_TOKEN, $cbId);
            return;
        }

        $u = getUser($uid);
        if ($u && !empty($u['banned'])) { answerCb(BOT_TOKEN, $cbId, T('banned'), true); return; }

        if ($data === 'mjchk') {
            $miss = masterJoinMissing($uid, true);
            if ($miss) { answerCb(BOT_TOKEN, $cbId, '❌ هنوز در همه کانال‌ها عضو نشده‌اید.', true); return; }
            answerCb(BOT_TOKEN, $cbId, '✅ تایید شد');
            if ($msgId) delMsg(BOT_TOKEN, $chatId, $msgId);
            showHome($uid, $chatId, $fname);
            return;
        }
        if (!$isAdmin && ($miss = masterJoinMissing($uid))) {
            answerCb(BOT_TOKEN, $cbId, '🔒 عضویت در کانال‌ها کامل نیست', true);
            masterJoinGate($uid, $chatId, $miss);
            return;
        }

        if (maCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin)) return;
        if (numCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin)) return;
        if (function_exists('svCallback') && svCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin)) return;

        if ($data === 'cp_enter') { answerCb(BOT_TOKEN, $cbId); couponAsk($uid, $chatId); return; }

        if (str_starts_with($data, 'trk_')) {
            answerCb(BOT_TOKEN, $cbId);
            trkShow($uid, $chatId, substr($data, 4), $msgId);
            return;
        }
        if (str_starts_with($data, 'menu_')) {
            $act = substr($data, 5);
            answerCb(BOT_TOKEN, $cbId);
            runMenuAction($act, $uid, $chatId, $uname, $fname);
            return;
        }

        if ($data === 'cancel') {
            clearState($uid);
            answerCb(BOT_TOKEN, $cbId, 'لغو شد');
            if ($msgId) { delMsg(BOT_TOKEN, $chatId, $msgId); slotClear($uid); }
            showHome($uid, $chatId, $fname);
            return;
        }

        if ($data === 'sup_list') {
            answerCb(BOT_TOKEN, $cbId);
            showSupportIndirect($uid, $chatId, $msgId);
            return;
        }
        if ($data === 'sup_direct') { answerCb(BOT_TOKEN, $cbId); return; }
        if ($data === 'sup_group')  {
            answerCb(BOT_TOKEN, $cbId, 'لینکِ گروه هنوز تنظیم نشده.', true);
            return;
        }

        if ($data === 'ref_link')   { answerCb(BOT_TOKEN, $cbId); showReferralLink($uid, $chatId); return; }
        if ($data === 'ref_hist')   { answerCb(BOT_TOKEN, $cbId); showReferralHistory($uid, $chatId); return; }
        if ($data === 'ref_wallet') { answerCb(BOT_TOKEN, $cbId); refWithdraw($uid, $chatId); return; }

        if ($data === 'trnop') { answerCb(BOT_TOKEN, $cbId); return; }
        if ($data === 'tr_cancel') {
            answerCb(BOT_TOKEN, $cbId, strip_tags(trBtnLabel('cancel')), true);
            return;
        }
        if ($data === 'tr_ok') {
            mutateUser($uid, function (&$user) {
                if ($user !== null) $user['topup_rules_ok'] = true;
            });
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, (string)cfg()['topup_rules']['text'],
                inlineKb([[trBtn('done', 'trnop')]]));
            answerCb(BOT_TOKEN, $cbId, '✅');
            startTopup($uid, $chatId);
            return;
        }

        if (str_starts_with($data, 'gwchk_')) {
            $oid = substr($data, 6);
            $o = Order::get($oid);
            if (!$o || (int)$o['user_id'] !== $uid) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return; }
            if ($o['status'] === Order::APPROVED) {
                answerCb(BOT_TOKEN, $cbId, '✅ قبلا تایید شده', true); return;
            }
            if (!maRateOk('gwchk', (string)$uid, 8, 60)) { answerCb(BOT_TOKEN, $cbId, '⏳ کمی صبر کنید؛ خودکار هم بررسی می‌شود.', true); return; }
            [$paid, $st] = gwCheck($o);
            if ($paid) {
                answerCb(BOT_TOKEN, $cbId, '✅ پرداخت تایید شد');
                gwSettle($oid);
                return;
            }
            $left = max(0, (int)(($o['gw']['expires_at'] ?? 0)) - time());
            answerCb(BOT_TOKEN, $cbId,
                $left > 0
                    ? "⏳ هنوز واریزی دیده نشد.\nوضعیت: {$st}\nمهلت: " . sprintf('%02d:%02d', (int)floor($left/60), $left % 60)
                    : "⌛️ مهلت تمام شد. دوباره درخواست شارژ بدهید.", true);
            return;
        }

        if (str_starts_with($data, 'ocancel_')) {
            $oid = substr($data, 8);
            $o   = Order::get($oid);
            clearState($uid);
            if ($o && (int)$o['user_id'] === $uid && ($o['status'] ?? '') === Order::PENDING)
                Order::cancel($oid);
            answerCb(BOT_TOKEN, $cbId, 'لغو شد');

            if ($msgId) { delMsg(BOT_TOKEN, $chatId, $msgId); slotClear($uid, 'shop'); }
            startTopup($uid, $chatId);
            return;
        }

        if (function_exists('payUserCallback') && payUserCallback($data, $uid, $chatId, $msgId, $cbId, $uname)) return;

        $adminPrefixes = ['akyc_', 'adm_', 'ag_', 'eb', 'et', 'eg', 'eu', 'eref', 'esup',
                          'jn', 'gw', 'pay', 'px', 'dm', 'ch', 'gma', 'gm_', 'num', 'reply_', 'bk', 'mn',
                          'qz_home', 'qza', 'qzb',
                          'fnt_', 'fntv_',
                          'tp_home', 'tpa',
                          'tl_home', 'tla',
                          'whp_', 'arp_', 'adadm', 'cpadm', 'cpn_',
                          'eshop', 'epm',
                          'locks_'];
        $isAdminCb = false;
        foreach ($adminPrefixes as $pref) {
            if (str_starts_with($data, $pref)) { $isAdminCb = true; break; }
        }
        if ($isAdminCb) {
            if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒 دسترسی ندارید.', true); return; }
            panelMode(true);
        } else {
            answerCb(BOT_TOKEN, $cbId);
            return;
        }

        if ($data === 'ebuttons') { answerCb(BOT_TOKEN, $cbId); edButtons($chatId, $msgId); return; }
        if ($data === 'etexts')   { answerCb(BOT_TOKEN, $cbId); edTexts($chatId, $msgId); return; }
        if ($data === 'eglass')   { answerCb(BOT_TOKEN, $cbId); edGlass($chatId, $msgId); return; }
        if ($data === 'euis')     { answerCb(BOT_TOKEN, $cbId); clearState(admStateUid($chatId)); edUiTexts($chatId, $msgId); return; }
        if ($data === 'eshop')    { answerCb(BOT_TOKEN, $cbId); clearState(admStateUid($chatId)); edShop($chatId, $msgId); return; }
        if ($data === 'eshop_c' || preg_match('/^eshopc_(\w+)$/', $data, $em)) {
            $k = $data === 'eshop_c' ? 'open_app' : $em[1];
            if (!isset(shopBtnKeys()[$k])) { answerCb(BOT_TOKEN, $cbId); return; }
            $cur = (string)(cfg()['ui_colors'][$k] ?? 'none');
            cfgSet(function (&$c) use ($k, $cur) { $c['ui_colors'][$k] = nextStyle($cur); });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            edShop($chatId, $msgId);
            return;
        }
        if ($data === 'eshop_b' || preg_match('/^eshopb_(\w+)$/', $data, $em)) {
            $k = $data === 'eshop_b' ? 'open_app' : $em[1];
            if (!isset(shopBtnKeys()[$k])) { answerCb(BOT_TOKEN, $cbId); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'ed_uitext', ['key' => $k, 'back' => 'eshop']);
            sendMsg(BOT_TOKEN, $chatId, "🔤 " . h(UT($k)), inlineKb([[btnCb(UT('cancel'), 'eshop', 'cancel')]]));
            return;
        }
        if ($data === 'etrust')   { answerCb(BOT_TOKEN, $cbId); edTrustBtn($chatId, $msgId); return; }
        if ($data === 'etrust_c') {
            cfgSet(function (&$c) { $c['trust_btn']['color'] = nextStyle($c['trust_btn']['color'] ?? 'none'); });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            edTrustBtn($chatId, $msgId);
            return;
        }
        if ($data === 'etrust_x') {
            cfgSet(function (&$c) { $c['trust_btn']['url'] = ''; });
            answerCb(BOT_TOKEN, $cbId, '🗑 برداشته شد');
            edTrustBtn($chatId, $msgId);
            return;
        }
        if ($data === 'etrust_t' || $data === 'etrust_u') {
            answerCb(BOT_TOKEN, $cbId);
            $isUrl = $data === 'etrust_u';
            setState(admStateUid($chatId), $isUrl ? 'ed_trust_url' : 'ed_trust_text', []);
            sendMsg(BOT_TOKEN, $chatId, $isUrl
                ? "🔗 لینک"
                : "✏️ متن",
                inlineKb([[btnCb(UT('cancel'), 'etrust', 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'ets_')) { answerCb(BOT_TOKEN, $cbId); edTexts($chatId, $msgId, (int)substr($data, 4)); return; }
        if (str_starts_with($data, 'eus_')) { answerCb(BOT_TOKEN, $cbId); edUiTexts($chatId, $msgId, (int)substr($data, 4)); return; }
        if (str_starts_with($data, 'eb_'))  { answerCb(BOT_TOKEN, $cbId); edButton($chatId, $msgId, substr($data, 3)); return; }
        if (str_starts_with($data, 'et_'))  { answerCb(BOT_TOKEN, $cbId); edText($chatId, $msgId, substr($data, 3)); return; }

        if ($data === 'adm_join')    { answerCb(BOT_TOKEN, $cbId); admJoin($chatId, $msgId); return; }
        if ($data === 'epm')         { answerCb(BOT_TOKEN, $cbId); clearState(admStateUid($chatId)); edPremium($chatId, $msgId); return; }
        if ($data === 'epm_add') {
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'epm_add');
            sendMsg(BOT_TOKEN, $chatId, "✨", inlineKb([[btnUI('cancel', 'epm', 'cancel')]]));
            return;
        }
        if ($data === 'epm_clr') {
            cfgSet(function (&$c) { unset($c['premium_map']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edPremium($chatId, $msgId);
            return;
        }
        if (str_starts_with($data, 'ag_')) {
            answerCb(BOT_TOKEN, $cbId);
            admGroup($chatId, $msgId, substr($data, 3));
            return;
        }
        if (pxAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (dmAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (chAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (gmAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('bkAdminCallback') && bkAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('mnAdminCallback') && mnAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('qzAdminCallback') && qzAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('whAdminCallback') && whAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('arAdminCallback') && arAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('adAdminCallback') && adAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('cpAdminCallback') && cpAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (tlAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('fntCallback') && fntCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('tpAdminCallback') && tpAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if (function_exists('payAdminCallback') && payAdminCallback($data, $chatId, $msgId, $cbId)) return;
        if ($data === 'jno') {
            cfgSet(function (&$c) { $c['join']['on'] = empty($c['join']['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
            admJoin($chatId, $msgId);
            return;
        }
        if ($data === 'jna') {
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'jn_add', []);
            sendMsg(BOT_TOKEN, $chatId,
                "➕ <b>کانال عضویت اجباری</b>",
                inlineKb([[btnUI('cancel', 'adm_join', 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'jnd_')) {
            $key = substr($data, 4);
            $gone = false;
            cfgSet(function (&$c) use ($key, &$gone) {
                $list = array_values((array)($c['join']['channels'] ?? []));
                foreach ($list as $k => $x)
                    if (substr(md5((string)($x['chat_id'] ?? '')), 0, 12) === $key) { unset($list[$k]); $gone = true; break; }
                $c['join']['channels'] = array_values($list);
            });
            answerCb(BOT_TOKEN, $cbId, $gone ? '🗑 حذف شد' : 'این کانال دیگر در فهرست نیست');
            admJoin($chatId, $msgId);
            return;
        }
        foreach ([['jnt', 'jn_text', "✏️ متن قفل · <code>{channels}</code>"],
                  ['jnb', 'jn_btn', '🔘 متن دکمه']] as [$d0, $act, $ask]) {
            if ($data !== $d0) continue;
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), $act, []);
            sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'adm_join', 'cancel')]]));
            return;
        }

        if ($data === 'ebmode') {
            cfgSet(function (&$c) { $c['ui']['mode'] = ($c['ui']['mode'] === 'glass') ? 'menu' : 'glass'; });
            answerCb(BOT_TOKEN, $cbId, '✅ حالت عوض شد');
            edButtons($chatId, $msgId);
            sendMsg(BOT_TOKEN, $chatId, '👇 منوی جدید:', mainKeyboard());
            return;
        }
        if ($data === 'ebpin') {
            cfgSet(function (&$c) { $c['ui']['persistent'] = empty($c['ui']['persistent']); });
            $on = !empty(cfg()['ui']['persistent']);
            answerCb(BOT_TOKEN, $cbId, $on ? 'چسبان روشن شد' : 'چسبان خاموش شد', true);
            edButtons($chatId, $msgId);
            sendMsg(BOT_TOKEN, $chatId,
                $on ? '📌 کیبورد چسبان شد — کاربر نمی‌تواند ببنددش.'
                    : '✅ کیبورد قابل بستن شد — کاربر می‌تواند ببنددش.',
                mainKeyboard());
            return;
        }
        if ($data === 'eblay') {
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'ed_layout');
            sendMsg(BOT_TOKEN, $chatId,
                "📐 <code>2,1,1</code>",
                inlineKb([[btnCb(UT('cancel'), 'ebuttons', 'cancel')]]));
            return;
        }
        if ($data === 'ebnew') {
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'ed_newbtn');
            sendMsg(BOT_TOKEN, $chatId, "➕ متن دکمه", inlineKb([[btnCb(UT('cancel'), 'ebuttons', 'cancel')]]));
            return;
        }

        foreach ([['ebt_', 'ed_btext', '✏️ متن'],
                  ['ebi_', 'ed_bicon', '✨ ایموجی پریمیوم'],
                  ['ebr_', 'ed_brow', '📐 ردیف'],
                  ['ebo_', 'ed_border', '🔢 ترتیب'],
                  ['ebv_', 'ed_bvalue', '📝 مقدار']] as $it) {
            [$pref, $act, $ask] = $it;
            if (str_starts_with($data, $pref)) {
                $bid = substr($data, strlen($pref));
                if (!isset(cfg()['buttons'][$bid])) { answerCb(BOT_TOKEN, $cbId, 'دکمه پیدا نشد', true); return; }
                answerCb(BOT_TOKEN, $cbId);
                setState(admStateUid($chatId), $act, ['btn' => $bid]);
                sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnCb(UT('cancel'), 'eb_' . $bid, 'cancel')]]));
                return;
            }
        }

        if (str_starts_with($data, 'ebc_')) {
            $bid = substr($data, 4);
            cfgSet(function (&$c) use ($bid) {
                if (isset($c['buttons'][$bid])) $c['buttons'][$bid]['color'] = nextStyle($c['buttons'][$bid]['color'] ?? 'none');
            });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            edButton($chatId, $msgId, $bid);
            return;
        }
        if (str_starts_with($data, 'ebx_')) {
            $bid = substr($data, 4);
            cfgSet(function (&$c) use ($bid) {
                if (isset($c['buttons'][$bid])) $c['buttons'][$bid]['on'] = empty($c['buttons'][$bid]['on']);
            });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edButton($chatId, $msgId, $bid);
            return;
        }
        if (str_starts_with($data, 'ebd_')) {
            $bid = substr($data, 4);
            if (!str_starts_with($bid, 'c_')) { answerCb(BOT_TOKEN, $cbId, 'فقط دکمه‌های ساخته‌شده حذف می‌شوند', true); return; }
            cfgSet(function (&$c) use ($bid) { unset($c['buttons'][$bid]); });
            answerCb(BOT_TOKEN, $cbId, 'حذف شد');
            edButtons($chatId, $msgId);
            return;
        }

        if (str_starts_with($data, 'ete_')) {
            $k = substr($data, 4);
            if (!isset(textLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'edit_text', ['key' => $k]);
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متن",
                inlineKb([[btnCb(UT('cancel'), 'et_' . $k, 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'etq_') || str_starts_with($data, 'etx_')) {
            $exp = str_starts_with($data, 'etx_');
            $k = substr($data, 4);
            if (!isset(textLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($k, $exp) {
                $t = trim($c['texts'][$k] ?? '');
                if (str_starts_with($t, '<blockquote')) {
                    $t = preg_replace('#^<blockquote[^>]*>#', '', $t);
                    $t = preg_replace('#</blockquote>$#', '', trim($t));
                    if ($exp) $t = '<blockquote expandable>' . trim($t) . '</blockquote>';
                } else {
                    $t = ($exp ? '<blockquote expandable>' : '<blockquote>') . $t . '</blockquote>';
                }
                $c['texts'][$k] = trim($t);
            });
            answerCb(BOT_TOKEN, $cbId, '❝ اعمال شد');
            edText($chatId, $msgId, $k);
            return;
        }
        if (str_starts_with($data, 'etr_')) {
            $k = substr($data, 4);
            $d = defaultConfig()['texts'][$k] ?? null;
            if ($d === null) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($k, $d) { $c['texts'][$k] = $d; });
            answerCb(BOT_TOKEN, $cbId, '♻️ بازگردانی شد');
            edText($chatId, $msgId, $k);
            return;
        }
        if (str_starts_with($data, 'euc_')) {
            $k = substr($data, 4);
            if (!isset(uiTextLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($k) {
                $cur = $c['ui_colors'][$k] ?? 'none';
                $c['ui_colors'][$k] = nextStyle($cur);
            });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            $page = 0;
            $keys = array_keys(uiTextLabels());
            $i = array_search($k, $keys, true);
            if ($i !== false) $page = intdiv($i, 9);
            edUiTexts($chatId, $msgId, $page);
            return;
        }
        if (str_starts_with($data, 'eu_')) {
            $k = substr($data, 3);
            if (!isset(uiTextLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'ed_uitext', ['key' => $k]);
            sendMsg(BOT_TOKEN, $chatId,
                "🔤 " . h(uiTextLabels()[$k]),
                inlineKb([[btnCb(UT('cancel'), 'euis', 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'egc_')) {
            $role = substr($data, 4);
            if (!isset(glassRoleLabels()[$role])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($role) { $c['glass_colors'][$role] = nextStyle($c['glass_colors'][$role] ?? 'none'); });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            edGlass($chatId, $msgId);
            return;
        }

        if ($data === 'adm_home')   { answerCb(BOT_TOKEN, $cbId); admHome($chatId, $msgId); return; }

        if (str_starts_with($data, 'reply_')) {
            $target = (int)substr($data, 6);
            if ($target <= 0) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'reply_user', ['to' => $target, 'mid' => (int)$msgId]);
            sendMsg(BOT_TOKEN, $chatId, "💬 <code>{$target}</code>",
                inlineKb([[btnUI('cancel', 'adm_home', 'cancel')]]));
            return;
        }

        if (str_starts_with($data, 'adm_txt_')) {
            $key = substr($data, 8);
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'edit_text', ['key' => $key]);
            $cur = cfg()['texts'][$key] ?? '';
            sendMsg(BOT_TOKEN, $chatId,
                "📝 <code>" . h($cur) . "</code>",
                inlineKb([[['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }

        if ($data === 'adm_bc') {
            answerCb(BOT_TOKEN, $cbId);
            setState(admStateUid($chatId), 'broadcast');
            sendMsg(BOT_TOKEN, $chatId,
                "📢 <b>پیام همگانی</b>\n\n" .
                "👥 گیرنده: <b>" . number_format(bcCount()) . "</b> نفر",
                inlineKb([[btnUI('cancel', 'ag_rep', 'cancel')]]));
            return;
        }

        if ($data === 'esup')  { answerCb(BOT_TOKEN, $cbId); edSupMain($chatId, $msgId); return; }
        if (str_starts_with($data, 'esup_')) {
            answerCb(BOT_TOKEN, $cbId);
            edSupOne($chatId, $msgId, substr($data, 5));
            return;
        }
        if (preg_match('/^esup([ticu])_(direct|indirect|group)$/', $data, $em)) {
            static $ask = [
                't' => ["✏️ متن", 'sup_text'],
                'i' => ["✨ ایموجی پریمیوم", 'sup_icon'],
                'u' => ["🔗 لینک", 'sup_url'],
            ];
            if ($em[1] === 'c') {
                answerCb(BOT_TOKEN, $cbId);
                $rows = [];
                foreach (styleMap() as $sk => $sl)
                    $rows[] = [btnCb($sl, 'esupC_' . $em[2] . '_' . $sk, 'info')];
                $rows[] = [btnUI('back', 'esup_' . $em[2], 'nav')];
                editMsg(BOT_TOKEN, $chatId, $msgId, "🎨 <b>رنگ دکمه</b>", inlineKb($rows));
                return;
            }
            [$txt, $st] = $ask[$em[1]];
            setState($uid, $st, ['which' => $em[2]]);
            answerCb(BOT_TOKEN, $cbId);
            sendMsg(BOT_TOKEN, $chatId, $txt,
                    inlineKb([[btnUI('cancel', 'esup_' . $em[2], 'cancel')]]));
            return;
        }
        if (preg_match('/^esupC_(direct|indirect|group)_(\w+)$/', $data, $em)) {
            $col = isStyle($em[2]) ? $em[2] : 'none';
            cfgSet(function (&$c) use ($em, $col) { $c['support_main'][$em[1]]['color'] = $col; });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edSupOne($chatId, $msgId, $em[1]);
            return;
        }

        if ($data === 'etop_home') { answerCb(BOT_TOKEN, $cbId); edTopupRules($chatId, $msgId); return; }
        if ($data === 'etopx') {
            cfgSet(function (&$c) { $c['topup_rules']['on'] = empty($c['topup_rules']['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅'); edTopupRules($chatId, $msgId); return;
        }
        if ($data === 'etopm') {
            answerCb(BOT_TOKEN, $cbId);
            setState($uid, 'tr_text', []);
            sendMsg(BOT_TOKEN, $chatId, "✏️ متن", inlineKb([[btnUI('cancel', 'etop_home', 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'etopb_')) {
            answerCb(BOT_TOKEN, $cbId);
            edTopupRulesBtn($chatId, $msgId, substr($data, 6));
            return;
        }
        if (preg_match('/^etopb([tic])_(ok|cancel|done)$/', $data, $em)) {
            if ($em[1] === 'c') {
                answerCb(BOT_TOKEN, $cbId);
                $rows = [];
                foreach (styleMap() as $sk => $sl) $rows[] = [btnCb($sl, 'etopbC_' . $em[2] . '_' . $sk, 'info')];
                $rows[] = [btnUI('back', 'etopb_' . $em[2], 'nav')];
                editMsg(BOT_TOKEN, $chatId, $msgId, "🎨 <b>رنگ دکمه</b>", inlineKb($rows));
                return;
            }
            static $trAsk = [
                't' => ["✏️ متن", 'tr_btext'],
                'i' => ["✨ ایموجی پریمیوم", 'tr_bicon'],
            ];
            [$txt, $st] = $trAsk[$em[1]];
            setState($uid, $st, ['which' => $em[2]]);
            answerCb(BOT_TOKEN, $cbId);
            sendMsg(BOT_TOKEN, $chatId, $txt, inlineKb([[btnUI('cancel', 'etopb_' . $em[2], 'cancel')]]));
            return;
        }
        if (preg_match('/^etopbC_(ok|cancel|done)_(\w+)$/', $data, $em)) {
            $col = isStyle($em[2]) ? $em[2] : 'none';
            cfgSet(function (&$c) use ($em, $col) { $c['topup_rules']['btns'][$em[1]]['color'] = $col; });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edTopupRulesBtn($chatId, $msgId, $em[1]);
            return;
        }

        if ($data === 'eref_home') { answerCb(BOT_TOKEN, $cbId); edRefButtons($chatId, $msgId); return; }
        if (preg_match('/^eref_(link|hist|wallet|share)$/', $data, $em)) {
            answerCb(BOT_TOKEN, $cbId);
            edRefButtonOne($chatId, $msgId, $em[1]);
            return;
        }
        if (preg_match('/^erefb([tic])_(link|hist|wallet|share)$/', $data, $em)) {
            if ($em[1] === 'c') {
                answerCb(BOT_TOKEN, $cbId);
                $rows = [];
                foreach (styleMap() as $sk => $sl) $rows[] = [btnCb($sl, 'erefbC_' . $em[2] . '_' . $sk, 'info')];
                $rows[] = [btnUI('back', 'eref_' . $em[2], 'nav')];
                editMsg(BOT_TOKEN, $chatId, $msgId, "🎨 <b>رنگ دکمه</b>", inlineKb($rows));
                return;
            }
            static $refAsk = [
                't' => ["✏️ متن", 'ref_btext'],
                'i' => ["✨ ایموجی پریمیوم", 'ref_bicon'],
            ];
            [$txt, $st] = $refAsk[$em[1]];
            setState($uid, $st, ['which' => $em[2]]);
            answerCb(BOT_TOKEN, $cbId);
            sendMsg(BOT_TOKEN, $chatId, $txt, inlineKb([[btnUI('cancel', 'eref_' . $em[2], 'cancel')]]));
            return;
        }
        if (preg_match('/^erefbC_(link|hist|wallet|share)_(\w+)$/', $data, $em)) {
            $col = isStyle($em[2]) ? $em[2] : 'none';
            cfgSet(function (&$c) use ($em, $col) { $c['referral']['btns'][$em[1]]['color'] = $col; });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edRefButtonOne($chatId, $msgId, $em[1]);
            return;
        }

        if ($data === 'adm_check') { answerCb(BOT_TOKEN, $cbId); admCheck($chatId, $msgId); return; }

        if ($data === 'adm_web') {
            answerCb(BOT_TOKEN, $cbId);
            editMsg(BOT_TOKEN, $chatId, $msgId,
                "🌐 <b>پنل وب</b>\n\n" .
                "🔑 کلیدها و اتصال‌ها · 💳 درگاه‌ها · ☎️ کشورها و قیمت‌ها · 🧩 سرویس‌ها\n" .
                "📦 سفارش‌ها و شارژها · 👥 کاربران و رفرال · 📞 تیکت‌ها · 🛰 رصدِ زنده\n\n" .
                "<code>admin_panel.php</code>",
                inlineKb([[btnCb(UT('back'), 'adm_home', 'nav')]]));
            return;
        }

        answerCb(BOT_TOKEN, $cbId);
        return;
    }

    if (isset($update['my_chat_member'])) {
        if (function_exists('qzGroupMember')) qzGroupMember($update['my_chat_member']);
        return;
    }

    if (!isset($update['message'])) return;

    $msg    = $update['message'];
    $uid    = (int)($msg['from']['id'] ?? 0);
    $chatId = $msg['chat']['id'] ?? $uid;
    $uname  = $msg['from']['username'] ?? '';
    $fname  = $msg['from']['first_name'] ?? '';
    $text   = trim($msg['text'] ?? '');
    if (!$uid) return;

    if (($msg['chat']['type'] ?? 'private') !== 'private') {
        if (!empty($msg['migrate_to_chat_id'])) {
            if (function_exists('qzGroupMigrate')) qzGroupMigrate($chatId, $msg['migrate_to_chat_id']);
            return;
        }
        if (function_exists('maSupReply') && maSupReply($msg)) return;
        if (!isAdmin($uid) && ($bu = getUser($uid)) && !empty($bu['banned'])) return;
        if (preg_match('/^\/start(?:@(\w+))?(?:\s|$)/i', $text, $sm)) {
            $bu = botUsername();
            if ($bu !== '' && (($sm[1] ?? '') === '' || strcasecmp($sm[1], $bu) === 0) && !maRatePeek('gstart', (string)$chatId, 1, 20)) {
                maRateOk('gstart', (string)$chatId, 1, 20);
                sendMsg(BOT_TOKEN, $chatId, '🤖 <b>' . h($bu) . '</b>',
                    inlineKb([[btnUrl(UT('open'), 'https://t.me/' . $bu . '?start=g', 'link')]]),
                    ['reply_to_message_id' => (int)($msg['message_id'] ?? 0), 'allow_sending_without_reply' => 'true']);
            }
            return;
        }
        if (function_exists('whGroupCmd') && whGroupCmd($msg, $uid, $chatId)) return;
        if (tlHandle($msg, $uid, $chatId)) return;
        $rt = $msg['message_id'] ?? null;
        if (function_exists('tpTouch')) tpTouch($chatId, $uid);
        $gk = $chatId . ':' . $uid;
        $gfree = isAdmin($uid);
        if (($gfree || !maRatePeek('gcmd', $gk, 10, 30))
            && ((function_exists('tpHandleText') && tpHandleText($text, $uid, $chatId, $fname, $uname, $rt, false))
                || pxAnswerThenWarm($text, $chatId, $rt)
                || gmHandleText($text, $uid, $chatId, $fname, $uname, $rt, false, $msg)
                || dmHandleText($text, $uid, $chatId, $fname, $uname, $rt, false)
                || (function_exists('bkHandleText') && bkHandleText($text, $uid, $chatId, $fname, $uname, $rt, false, $msg))
                || (function_exists('mnHandleText') && mnHandleText($text, $uid, $chatId, $fname, $uname, $rt, false, $msg)))) {
            if (!$gfree) maRateOk('gcmd', $gk, 10, 30);
            return;
        }
        if (function_exists('arHandleGroup') && arHandleGroup($msg, $uid, $chatId)) return;
        return;
    }

    if (str_starts_with($text, '/start')) {
        $arg = trim(explode(' ', $text, 2)[1] ?? '');
        $ref = (str_starts_with($arg, 'ref')) ? (int)substr($arg, 3) : null;
        touchUser($uid, $uname, $fname, $ref);
        clearState($uid);
        slotClear($uid);
        if ($miss = masterJoinMissing($uid)) { masterJoinGate($uid, $chatId, $miss); return; }
        if ($arg === 'topup') { startTopup($uid, $chatId); return; }
        if ($arg === 'wheel' && function_exists('whStartEntry')) { whStartEntry($uid, $chatId); return; }
        if ($arg === 'kyc' && function_exists('kycStart')) { kycStart($uid, $chatId); return; }
        if ($arg === 'phone' && function_exists('tuAskPhone')) { tuAskPhone($uid, $chatId); return; }
        showHome($uid, $chatId, $fname);
        return;
    }

    touchUser($uid, $uname, $fname);
    $u = getUser($uid);
    if ($u && !empty($u['banned'])) { sendMsg(BOT_TOKEN, $chatId, T('banned')); return; }
    if (tlHandle($msg, $uid, $chatId)) return;

    if (!empty($msg['reply_to_message'])) {
        if (function_exists('maSupReply') && maSupReply($msg)) return;
        if (function_exists('maSupFollowUp') && maSupFollowUp($msg, $uid, $uname, $fname, $chatId)) return;
    }

    if ($text === '/panel' || $text === '/admin') {
        if (!isAdmin($uid)) { sendMsg(BOT_TOKEN, $chatId, "🔒 دسترسی ندارید."); return; }
        panelMode(true);
        admHome($chatId);
        return;
    }
    if ($text === '/id')     { sendMsg(BOT_TOKEN, $chatId, "🆔 <code>{$uid}</code>"); return; }
    if ($text === '/emoji') {
        if (!isAdmin($uid)) return;
        setState($uid, 'grab_emoji');
        sendMsg(BOT_TOKEN, $chatId, "✨", inlineKb([[btnCb(UT('cancel'), 'cancel', 'cancel')]]));
        return;
    }
    if ($text === '/cancel') { clearState($uid); sendMsg(BOT_TOKEN, $chatId, "❌ لغو شد.", mainKeyboard()); return; }
    if ($text === '/orders') {
        clearState($uid); showOrders($uid, $chatId, $msg['message_id'] ?? null); return;
    }
    if ($text === '/menu')   { showHome($uid, $chatId, $fname); return; }

    if (!isAdmin($uid) && ($miss = masterJoinMissing($uid))) {
        masterJoinGate($uid, $chatId, $miss);
        return;
    }

    if (!empty($msg['contact']) && function_exists('payOnContact') && payOnContact($msg, $uid, $chatId)) return;

    $act = customEmojiIds($msg) ? null : findMenuAction($text, isAdmin($uid) && getState($uid));
    if ($act) {
        clearState($uid);
        $mine = (string)$chatId === (string)$uid ? (int)($msg['message_id'] ?? 0) : 0;
        $prevU = $mine ? slotGet($uid, 'umsg') : null;
        if ($prevU && $prevU !== $mine) delMsg(BOT_TOKEN, $chatId, $prevU);
        runMenuAction($act, $uid, $chatId, $uname, $fname, $msg['message_id'] ?? null);
        if ($mine) slotSet($uid, 'umsg', $mine);
        return;
    }

    $st = getState($uid);
    if (!$st) {
        $rt = $msg['message_id'] ?? null;
        if (($tc = trkCodeOf($text)) !== '') { trkShow($uid, $chatId, $tc, null, $rt); return; }
        if (pxAnswerThenWarm($text, $chatId, $rt)) return;
        if (gmHandleText($text, $uid, $chatId, $fname, $uname, $rt, true, $msg)) return;
        if (dmHandleText($text, $uid, $chatId, $fname, $uname, $rt, true)) return;
        if (function_exists('bkHandleText') && bkHandleText($text, $uid, $chatId, $fname, $uname, $rt, true, $msg)) return;
        return;
    }
    $action = $st['action'];
    $sd     = $st['data'] ?? [];

    if ($action === 'coupon') {
        $code = strtoupper(preg_replace('/\s+/u', '', norm_fa_digits($text)));
        if (!isAdmin($uid) && maRatePeek('cpbad', (string)$uid, 8, 3600)) {
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, '⏳ تلاش‌های اشتباه برای کد تخفیف زیاد شد؛ یک ساعت دیگر دوباره امتحان کنید.', mainKeyboard());
            return;
        }
        [$cok, $why, $c] = $code !== '' && mb_strlen($code) <= 40 && function_exists('cpActivate') ? cpActivate($uid, $code) : [false, 'کد تخفیف پیدا نشد.', null];
        if (!$cok) {
            if (!isAdmin($uid)) maRateOk('cpbad', (string)$uid, 1000, 3600);
            sendMsg(BOT_TOKEN, $chatId, T('coupon_bad', ['reason' => h($why)]), inlineKb([[btnUI('cancel', 'cancel', 'cancel')]]));
            return;
        }
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, TL('coupon_ok', [
            'code'  => h((string)$c['code']),
            'value' => h(cpValueText($c)),
            'exp'   => (int)$c['expires_at'] > 0 ? h(cpDate((int)$c['expires_at'])) : '',
        ]), function_exists('svShopKb') ? svShopKb($uid, false) : null);
        return;
    }
    if ($action === 'track') {
        $tc = trkCodeOf($text);
        if ($tc === '') { sendMsg(BOT_TOKEN, $chatId, T('track_bad', ['code' => h(mb_substr($text, 0, 40))])); return; }
        trkShow($uid, $chatId, $tc, null, $msg['message_id'] ?? null);
        return;
    }
    if (maStateHandle($action, $sd, $msg, $uid, $chatId)) return;
    if (function_exists('svStateHandle') && svStateHandle($action, $msg, $uid, $chatId)) return;

    if (pxStateHandle($action, $msg, $uid, $chatId)) return;
    if (dmStateHandle($action, $msg, $uid, $chatId)) return;
    if (chStateHandle($action, $msg, $uid, $chatId)) return;
    if (gmStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('bkStateHandle') && bkStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('mnStateHandle') && mnStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('qzStateHandle') && qzStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('whStateHandle') && whStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('arStateHandle') && arStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('adStateHandle') && adStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('cpAdminState') && cpAdminState($action, $msg, $uid, $chatId)) return;
    if (tlStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('tpStateHandle') && tpStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('payStateHandle') && payStateHandle($action, $msg, $uid, $chatId)) return;
    if (function_exists('payAdminState') && isAdmin($uid) && payAdminState($action, $msg, $uid, $chatId)) return;
    if (function_exists('fntState') && fntState($action, getState($uid)['data'] ?? [], $msg, $uid, $chatId)) return;


    if ($action === 'ticket') {
        $body = msgHtml($msg);
        if (trim($body) === '' && empty($msg['photo'])) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است");
            return;
        }
        clearState($uid);
        if (!isAdmin($uid) && !maRateOk('tkt', (string)$uid, 5, 600)) {
            sendMsg(BOT_TOKEN, $chatId, '⏳ در ده دقیقه‌ی گذشته چند تیکت فرستاده‌اید؛ کمی صبر کنید.', mainKeyboard());
            return;
        }

        sendMsg(BOT_TOKEN, $chatId, T('sup_sent'), mainKeyboard());
        maSupLog($uid, 'u', trim((string)($msg['text'] ?? $msg['caption'] ?? '')) ?: '📎 ' . maSupKind($msg));

        $r = chTicketAlert($uid, $uname, $fname, $body,
            inlineKb([[btnCb('💬 پاسخ', 'reply_' . $uid, 'admin')]]));
        if (!$r) error_log('[ticket] تیکتِ کاربر ' . $uid . ' نه به گروه رسید نه به پیویِ مدیر — ربات را در پیویِ مدیر استارت کنید');
        return;
    }

    // Everything below edits bot settings: admins only, whatever state a user ends up in.
    if (!isAdmin($uid)) { clearState($uid); return; }

    if (str_starts_with($action, 'sup_')) {
        $st    = getState($uid);
        $which = (string)(($st['data'] ?? [])['which'] ?? 'direct');
        if (!in_array($which, ['direct', 'indirect', 'group'], true)) { clearState($uid); return; }
        $plain = trim((string)($msg['text'] ?? ''));
        $blank = ($plain === '-' || $plain === '—');
        $back  = inlineKb([[btnCb('📞 دکمه‌های پشتیبانی', 'esup', 'admin')]]);

        if ($action === 'sup_text') {
            [$bt, $bi] = btnTextIn($msg);
            if ($bt === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            $v = mb_substr($bt, 0, 40);
            cfgSet(function (&$c) use ($which, $v, $bi) {
                $c['support_main'][$which]['text'] = $v;
                unset($c['support_main'][$which]['emoji']);
                if ($bi !== '') $c['support_main'][$which]['icon'] = $bi;
            });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, '✅', $back); return;
        }
        if ($action === 'sup_icon') {
            $ids = customEmojiIds($msg);
            $v   = $blank ? '' : (string)($ids[0] ?? '');
            if (!$blank && $v === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ ایموجی پریمیوم"); return; }
            cfgSet(function (&$c) use ($which, $v) { $c['support_main'][$which]['icon'] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, '✅', $back);
            return;
        }
        if ($action === 'sup_url') {
            if (!$blank && !preg_match('#^https?://[^\s]+$#i', $plain)) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ <code>https://</code>"); return;
            }
            $v = $blank ? '' : $plain;
            cfgSet(function (&$c) use ($which, $v) { $c['support_main'][$which]['value'] = $v; });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, '✅ ثبت شد.', $back); return;
        }
        clearState($uid);
        return;
    }

    if ($action === 'tr_text') {
        $html = function_exists('msgHtml') ? msgHtml($msg) : trim((string)($msg['text'] ?? ''));
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return; }
        cfgSet(function (&$c) use ($html) { $c['topup_rules']['text'] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ثبت شد.', inlineKb([[btnCb('📋 قوانین شارژ', 'etop_home', 'admin')]]));
        return;
    }
    if (str_starts_with($action, 'tr_b')) {
        $st    = getState($uid);
        $which = (string)(($st['data'] ?? [])['which'] ?? '');
        if (!in_array($which, ['ok', 'cancel', 'done'], true)) { clearState($uid); return; }
        $plain = trim((string)($msg['text'] ?? ''));
        $blank = ($plain === '-' || $plain === '—');
        $back  = inlineKb([[btnCb('📋 قوانین شارژ', 'etop_home', 'admin')]]);

        if ($action === 'tr_btext') {
            [$bt, $bi] = btnTextIn($msg);
            if ($bt === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            $v = mb_substr($bt, 0, 40);
            cfgSet(function (&$c) use ($which, $v, $bi) {
                $c['topup_rules']['btns'][$which]['text'] = $v;
                unset($c['topup_rules']['btns'][$which]['emoji']);
                if ($bi !== '') $c['topup_rules']['btns'][$which]['icon'] = $bi;
            });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, '✅', $back); return;
        }
        if ($action === 'tr_bicon') {
            $ids = customEmojiIds($msg);
            $v   = $blank ? '' : (string)($ids[0] ?? '');
            if (!$blank && $v === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ ایموجی پریمیوم"); return; }
            cfgSet(function (&$c) use ($which, $v) { $c['topup_rules']['btns'][$which]['icon'] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, '✅', $back);
            return;
        }
        clearState($uid);
        return;
    }

    if (str_starts_with($action, 'ref_b')) {
        $st    = getState($uid);
        $which = (string)(($st['data'] ?? [])['which'] ?? '');
        if (!in_array($which, ['link', 'hist', 'wallet', 'share'], true)) { clearState($uid); return; }
        $plain = trim((string)($msg['text'] ?? ''));
        $blank = ($plain === '-' || $plain === '—');
        $back  = inlineKb([[btnCb('👥 دکمه‌های زیرمجموعه', 'eref_home', 'admin')]]);

        if ($action === 'ref_btext') {
            [$bt, $bi] = btnTextIn($msg);
            if ($bt === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            $v = mb_substr($bt, 0, 40);
            cfgSet(function (&$c) use ($which, $v, $bi) {
                $c['referral']['btns'][$which]['text'] = $v;
                unset($c['referral']['btns'][$which]['emoji']);
                if ($bi !== '') $c['referral']['btns'][$which]['icon'] = $bi;
            });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, '✅', $back); return;
        }
        if ($action === 'ref_bicon') {
            $ids = customEmojiIds($msg);
            $v   = $blank ? '' : (string)($ids[0] ?? '');
            if (!$blank && $v === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ ایموجی پریمیوم"); return; }
            cfgSet(function (&$c) use ($which, $v) { $c['referral']['btns'][$which]['icon'] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, '✅', $back);
            return;
        }
        clearState($uid);
        return;
    }

    if (str_starts_with($action, 'jn_')) {
        $plain = trim($msg['text'] ?? '');
        $ids   = customEmojiIds($msg);
        $back  = inlineKb([[btnCb('🔒 عضویت اجباری', 'adm_join', 'admin')]]);

        if ($action === 'jn_add') {
            $ref = joinChannelRef($msg);
            if ($ref === '') {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ کانال پیدا نشد.");
                return;
            }
            [$ok, $res] = joinAddChannel($ref);
            if (!$ok) { sendMsg(BOT_TOKEN, $chatId, '⚠️ ' . h($res)); return; }
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ کانال «" . h($res) . "» اضافه شد و عضویت اجباری روشن است.", $back);
            return;
        }
        if ($action === 'jn_text') {
            $html = msgHtml($msg);
            if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            cfgSet(function (&$c) use ($html) { $c['join']['text'] = $html; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅", $back);
            return;
        }
        if ($action === 'jn_btn') {
            [$bt, $bi] = btnTextIn($msg);
            if ($bt === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            cfgSet(function (&$c) use ($bt, $bi) {
                $c['join']['btn']['text'] = $bt;
                unset($c['join']['btn']['emoji']);
                if ($bi !== '') $c['join']['btn']['icon'] = $bi;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
            return;
        }
        clearState($uid);
        return;
    }

    if ($action === 'epm_add') {
        $got = premiumLearn($msg);
        if (!$got) { sendMsg(BOT_TOKEN, $chatId, "⚠️ ایموجی پریمیوم", inlineKb([[btnUI('cancel', 'epm', 'cancel')]])); return; }
        cfgSet(function (&$c) use ($got) {
            $m = is_array($c['premium_map'] ?? null) ? $c['premium_map'] : [];
            foreach ($got as $k => $id) $m[$k] = $id;
            $c['premium_map'] = $m;
        });
        clearState($uid);
        edPremium($chatId);
        return;
    }

    if ($action === 'grab_emoji') {
        $ids = customEmojiIds($msg);
        if (!$ids) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ ایموجی پریمیوم");
            return;
        }
        clearState($uid);
        $out = "✨ <b>کدهای ایموجی پریمیوم</b>\n\n";
        foreach ($ids as $id) {
            $out .= "<tg-emoji emoji-id=\"" . h($id) . "\">✨</tg-emoji>  <code>" . h($id) . "</code>\n";
        }
        sendMsg(BOT_TOKEN, $chatId, rtrim($out), mainKeyboard());
        return;
    }

    if (!isAdmin($uid)) return;

    if ($action === 'reply_user') {
        $to = (int)($sd['to'] ?? 0);
        clearState($uid);
        if ($to <= 0) return;
        [$r, $logged] = maSupDeliver($to, $msg);
        if (!empty($r['ok']) || $logged) {
            $mid = (int)($sd['mid'] ?? 0);
            if ($mid > 0) tg(BOT_TOKEN, 'editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $mid,
                'reply_markup' => kbJson(inlineKb([[btnCb('✅ پاسخ داده شد · پاسخِ دوباره', 'reply_' . $to, 'admin')]]))], 8);
        }
        sendMsg(BOT_TOKEN, $chatId, !empty($r['ok']) ? "✅ پاسخ ارسال شد و در چتِ مینی‌اپ هم دیده می‌شود."
            : ($logged ? "⚠️ پیوی نرسید، ولی در چتِ مینی‌اپ دیده می‌شود: " : "❌ ارسال نشد: ") . h((string)($r['description'] ?? '')));
        return;
    }

    if ($action === 'edit_text') {
        $key = $sd['key'] ?? '';
        if (!array_key_exists($key, defaultConfig()['texts'])) { clearState($uid); return; }
        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        cfgSet(function (&$c) use ($key, $html) { $c['texts'][$key] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ متن ذخیره شد.\n\n<b>پیش‌نمایش:</b>\n" . $html, mainKeyboard());
        return;
    }

    if (str_starts_with($action, 'ed_b')) {
        $bid = $sd['btn'] ?? '';
        if (!isset(cfg()['buttons'][$bid])) { clearState($uid); return; }
        $plain = trim($msg['text'] ?? '');
        $ids   = customEmojiIds($msg);
        $back  = inlineKb([[btnCb(UT('back'), 'eb_' . $bid, 'nav')]]);

        if ($action === 'ed_btext') {
            [$bt, $bi] = btnTextIn($msg);
            if ($bt === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            cfgSet(function (&$c) use ($bid, $bt, $bi) {
                $c['buttons'][$bid]['text'] = $bt;
                unset($c['buttons'][$bid]['emoji']);
                if ($bi !== '') $c['buttons'][$bid]['icon'] = $bi;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ <b>" . h($bt) . "</b>", $back);
            sendMsg(BOT_TOKEN, $chatId, '👇', mainKeyboard());
            return;
        }
        if ($action === 'ed_bicon') {
            $ic = '';
            if ($ids) $ic = $ids[0];
            elseif (ctype_digit($plain)) $ic = $plain;
            elseif ($plain === '-' || $plain === '—') $ic = '';
            else { sendMsg(BOT_TOKEN, $chatId, "⚠️ ایموجی پریمیوم"); return; }
            cfgSet(function (&$c) use ($bid, $ic) { $c['buttons'][$bid]['icon'] = $ic; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅", $back);
            return;
        }
        if ($action === 'ed_brow' || $action === 'ed_border') {
            if (!ctype_digit($plain)) { sendMsg(BOT_TOKEN, $chatId, "⚠️ فقط عدد."); return; }
            $f = ($action === 'ed_brow') ? 'row' : 'order';
            $v = (int)$plain;
            cfgSet(function (&$c) use ($bid, $f, $v) { $c['buttons'][$bid][$f] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
            sendMsg(BOT_TOKEN, $chatId, '👇 منوی به‌روز:', mainKeyboard());
            return;
        }
        if ($action === 'ed_bvalue') {
            $html = msgHtml($msg);
            cfgSet(function (&$c) use ($bid, $html, $plain) {
                $c['buttons'][$bid]['value'] = (($c['buttons'][$bid]['action'] ?? '') === 'text') ? $html : $plain;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ مقدار ذخیره شد.", $back);
            return;
        }

    }

    if ($action === 'ed_layout') {
        if (!parseLayout($text)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ <code>2,1,1</code>");
            return;
        }
        $map = applyLayoutToRows($text);
        cfgSet(function (&$c) use ($map, $text) {
            $c['ui']['layout'] = trim($text);
            foreach ($map as $b => $r) if (isset($c['buttons'][$b])) $c['buttons'][$b]['row'] = $r;
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ چیدمان اعمال شد.", mainKeyboard());
        return;
    }

    if ($action === 'ed_newbtn') {
        [$plain, $bi] = btnTextIn($msg);
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        $nid = 'c_' . bin2hex(random_bytes(4));
        cfgSet(function (&$c) use ($nid, $plain, $bi) {
            $c['buttons'][$nid] = [
                'text' => $plain, 'color' => 'none',
                'icon' => $bi, 'row' => 0, 'order' => 50,
                'on' => true, 'action' => 'text', 'value' => '',
            ];
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ <b>" . h($plain) . "</b>", inlineKb([[btnCb('⚙️ ' . $plain, 'eb_' . $nid, 'admin')]]));
        return;
    }

    if ($action === 'ed_trust_text' || $action === 'ed_trust_url') {
        $plain = trim($msg['text'] ?? '');
        $back  = inlineKb([[btnCb('🔗 دکمه‌ی زیرِ «اعتماد»', 'etrust', 'admin')]]);
        if ($action === 'ed_trust_url') {
            $u = trustUrl($plain);
            if ($u === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ لینک نامعتبر"); return; }
            cfgSet(function (&$c) use ($u) { $c['trust_btn']['url'] = $u; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ لینک ثبت شد: <code>" . h($u) . "</code>", $back);
            return;
        }
        [$txt, $bi] = btnTextIn($msg);
        if ($txt === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        $txt = mb_substr($txt, 0, 60);
        cfgSet(function (&$c) use ($txt, $bi) {
            $c['trust_btn']['text'] = $txt;
            $c['trust_btn']['icon'] = $bi;
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ <b>" . h($txt) . "</b>", $back);
        return;
    }

    if ($action === 'ed_uitext') {
        $k = $sd['key'] ?? '';
        if (!isset(uiTextLabels()[$k])) { clearState($uid); return; }
        [$txt, $bi] = btnTextIn($msg);
        if ($txt === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        cfgSet(function (&$c) use ($k, $txt, $bi) {
            $c['ui_texts'][$k] = $txt;
            if ($bi !== '') $c['ui_icons'][$k] = $bi;
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ <b>" . h($txt) . "</b>",
            inlineKb([[btnUI('back', ($sd['back'] ?? '') === 'eshop' ? 'eshop' : 'euis', 'nav')]]));
        return;
    }

    if ($action === 'broadcast') {
        $media = !isset($msg['text']);
        $html = $media ? '' : msgHtml($msg);
        if (!$media && trim($html) === '') return;
        clearState($uid);
        [$n, $err] = bcQueue($html, $media ? [(int)$chatId, (int)($msg['message_id'] ?? 0)] : null);
        sendMsg(BOT_TOKEN, $chatId, $n > 0
            ? "📢 <b>پیام همگانی در صف قرار گرفت</b>\n\n" .
              "👥 گیرنده: <b>" . number_format($n) . "</b> نفر"
            : "⚠️ " . h($err ?: 'کسی برای فرستادن نیست.'));
        return;
    }
}

function bcWhere() {
    return "COALESCE(json_extract(data,'$.banned'), 0) NOT IN (1, '1', 'true')";
}

function bcCount() {
    $db = usersDb();
    return $db ? (int)@$db->querySingle('SELECT COUNT(*) FROM users WHERE ' . bcWhere()) : 0;
}

function bcLeft($b) {
    if (!is_array($b) || !$b || !empty($b['done'])) return 0;
    if (isset($b['ids'])) return max(0, count((array)$b['ids']) - (int)($b['i'] ?? 0));
    return max(1, (int)($b['total'] ?? 0) - (int)($b['i'] ?? 0));
}

function bcQueue($html, $copy = null) {
    $n = bcCount();
    if ($n <= 0) return [0, 'هیچ کاربری نیست.'];
    $left = mutate('broadcast', function (&$b) use ($html, $copy, $n) {
        if (($l = bcLeft($b)) > 0) return $l;
        $b = ['text' => (string)$html, 'copy' => $copy, 'last' => 0, 'total' => $n, 'i' => 0,
              'sent' => 0, 'fail' => 0, 'at' => time(), 'done' => false];
        return 0;
    });
    if ($left > 0) return [0, 'یک پیام همگانی نیمه‌تمام در صف است (' .
                              number_format($left) . ' گیرنده مانده). بگذارید تمام شود.'];
    return [$n, ''];
}

function bcTick($limit = 25, $budget = 5) {
    $b = load('broadcast', true);
    if (!$b || !empty($b['done'])) return 0;
    $fp = @fopen(dataPath('broadcast') . '.run', 'c');
    if (!$fp) return 0;
    if (!flock($fp, LOCK_EX | LOCK_NB)) { fclose($fp); return 0; }
    try { return bcRun($limit, $budget); }
    finally { flock($fp, LOCK_UN); fclose($fp); }
}

function bcRun($limit, $budget) {
    $b = load('broadcast', true);
    if (!$b || !empty($b['done'])) return 0;
    if (isset($b['ids'])) {
        $ids = array_values((array)$b['ids']);
        $i = min(count($ids), (int)($b['i'] ?? 0));
        $last = $i > 0 ? (int)$ids[$i - 1] : 0;
        mutate('broadcast', function (&$x) use ($last, $ids) {
            if (!is_array($x)) return;
            unset($x['ids']);
            $x['last'] = $last;
            $x['total'] = count($ids);
        });
        $b = load('broadcast', true);
    }
    $text = (string)($b['text'] ?? '');
    $copy = is_array($b['copy'] ?? null) ? $b['copy'] : null;
    $db = usersDb();
    $end = !$db || ($text === '' && !$copy);
    $last = (int)($b['last'] ?? 0);
    $t0 = microtime(true);
    $n = 0;
    $st = $end ? null : $db->prepare('SELECT id FROM users WHERE id > :l AND ' . bcWhere() . ' ORDER BY id LIMIT :n');
    while ($st && $n < $limit && microtime(true) - $t0 < $budget) {
        $st->bindValue(':l', $last, SQLITE3_INTEGER);
        $st->bindValue(':n', min(25, $limit - $n), SQLITE3_INTEGER);
        $r = $st->execute();
        $ids = [];
        while ($r && ($row = $r->fetchArray(SQLITE3_NUM))) $ids[] = (int)$row[0];
        $st->reset();
        if (!$ids) { $end = true; break; }
        $tb = microtime(true);
        [$sent, $fail] = bcSendBatch($ids, $text, $copy);
        $last = (int)end($ids);
        $n += count($ids);
        mutate('broadcast', function (&$x) use ($last, $sent, $fail, $ids) {
            if (!is_array($x) || !empty($x['done'])) return;
            $x['last'] = $last;
            $x['i']    = (int)($x['i'] ?? 0) + count($ids);
            $x['sent'] = (int)($x['sent'] ?? 0) + $sent;
            $x['fail'] = (int)($x['fail'] ?? 0) + $fail;
        });
        $wait = 1.05 - (microtime(true) - $tb);
        if ($wait > 0 && $n < $limit) usleep((int)($wait * 1000000));
    }
    if ($end) {
        mutate('broadcast', function (&$x) { if (is_array($x)) $x['done'] = true; });
        bcFinish();
    }
    return $n;
}

function bcSendBatch(array $ids, $text, $copy) {
    $method = $copy ? 'copyMessage' : 'sendMessage';
    $items = [];
    foreach ($ids as $id)
        $items[$id] = $copy
            ? ['chat_id' => $id, 'from_chat_id' => $copy[0], 'message_id' => $copy[1]]
            : ['chat_id' => $id, 'text' => emKeep($text), 'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true'];
    $sent = 0; $fail = 0; $again = []; $wait = 0;
    foreach (tgMulti(BOT_TOKEN, $method, $items, 10) as $id => $r) {
        if (!empty($r['ok'])) { $sent++; continue; }
        $code = (int)($r['error_code'] ?? 0);
        if ($code === 429) { $again[] = $id; $wait = max($wait, (int)($r['parameters']['retry_after'] ?? 1)); continue; }
        if ($code === 400 && stripos((string)($r['description'] ?? ''), "can't parse entities") !== false) { $again[] = $id; continue; }
        $fail++;
    }
    if ($wait > 0) sleep(min(30, $wait));
    foreach ($again as $id) {
        $r = tg(BOT_TOKEN, $method, $items[$id], 10);
        if (!empty($r['ok'])) $sent++; else $fail++;
    }
    return [$sent, $fail];
}

function bcFinish() {
    $told = mutate('broadcast', function (&$x) {
        if (!is_array($x) || !empty($x['told'])) return null;
        $x['told'] = true;
        return $x;
    });
    if (!$told) return;
    notifyAdmins(
        "📢 <b>پیام همگانی تمام شد</b>\n\n" .
        '✅ رسید: <b>' . number_format((int)($told['sent'] ?? 0)) . "</b>\n" .
        '❌ نرسید: <b>' . number_format((int)($told['fail'] ?? 0)) . '</b>');
}

function runMenuAction($act, $uid, $chatId, $uname, $fname, $replyTo = null) {
    $b = cfg()['buttons'][$act] ?? null;
    if ($b && ($b['action'] ?? '') === 'url') {
        sendMsg(BOT_TOKEN, $chatId, btnLabel($b), inlineKb([[btnUrl(UT('open'), $b['value'], 'link')]]));
        return;
    }
    if ($b && ($b['action'] ?? '') === 'text') {
        $v = trim((string)($b['value'] ?? ''));
        if ($v === '' && isAdmin($uid)) {
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ «" . h($b['text']) . "» بدونِ مقدار");
            return;
        }
        if ($v !== '') sendMsg(BOT_TOKEN, $chatId, $v);
        return;
    }

    switch ($act) {
        case 'buy':      showShop($uid, $chatId, $replyTo); break;
        case 'account':  showAccount($uid, $chatId, $replyTo); break;
        case 'topup':    startTopup($uid, $chatId, $replyTo); break;
        case 'referral': showReferral($uid, $chatId, $replyTo); break;
        case 'orders':   showOrders($uid, $chatId, $replyTo); break;
        case 'support':  showSupport($uid, $chatId, $replyTo); break;
        case 'trust':    panelShow($uid, $chatId, 'menu', T('trust'), trustKb(), $replyTo); break;
        default:         showHome($uid, $chatId, $fname); break;
    }
}

function showShop($uid, $chatId, $replyTo = null) {
    $kb = function_exists('svShopKb') ? svShopKb($uid) : (maReady() ? maOpenKb() : null);
    if (!$kb) {
        $t = T('shop_closed');
        if (isAdmin($uid))
            $t .= "\n\n👑 مینی‌اپ خاموش است یا آدرسش ثبت نشده.";
        panelShow($uid, $chatId, 'shop', $t, null, $replyTo);
        return;
    }
    if (isAdmin($uid) && (string)$chatId === (string)$uid)
        $kb['inline_keyboard'][] = [btnCb('✏️ ویرایش این پیام و دکمه (فقط ادمین)', 'eshop', 'admin')];
    panelShow($uid, $chatId, 'shop',
        T('shop', ['balance' => fmtNum((float)(getUser($uid)['balance'] ?? 0))]), $kb, $replyTo);
}

function couponAsk($uid, $chatId) {
    setState($uid, 'coupon');
    $c = function_exists('cpActivePublic') ? cpActivePublic($uid) : null;
    sendMsg(BOT_TOKEN, $chatId, TL('coupon_ask', $c ? ['code' => h($c['code']), 'value' => h(cpValueText($c))] : []),
        inlineKb([[btnUI('cancel', 'cancel', 'cancel')]]));
}

function trkCodeOf($text) {
    return preg_match('/(?<![a-z0-9_])((?:sv|ma)_[a-z0-9]{6,24})(?![a-z0-9_])/i', (string)$text, $m) ? strtolower($m[1]) : '';
}

function trkRecent($uid) {
    $rows = [];
    foreach (MaOrder::forUser($uid, 5) as $o) $rows[] = [(int)strtotime((string)($o['created_at'] ?? '')), (string)$o['id']];
    if (function_exists('svOrdersFor')) foreach (svOrdersFor($uid, '', 5) as $o) $rows[] = [(int)$o['created'], (string)$o['id']];
    usort($rows, fn($x, $y) => $y[0] <=> $x[0]);
    $list = '';
    foreach (array_slice($rows, 0, 5) as [, $id]) $list .= '<code>' . h($id) . "</code>\n";
    return $list === '' ? '' : T('track_recent', ['list' => $list]);
}

function showOrders($uid, $chatId, $replyTo = null) {
    setState($uid, 'track');
    panelShow($uid, $chatId, 'menu', T('track_ask', ['recent' => trkRecent($uid)]),
        inlineKb([[btnUI('cancel', 'cancel', 'cancel')]]), $replyTo);
}

function trkBar($pc) {
    $pc = max(0, min(100, (int)$pc));
    $n = (int)round($pc / 10);
    return str_repeat('█', $n) . str_repeat('░', 10 - $n) . ' ' . $pc . '%';
}

function trkSvFresh($o) {
    if (($o['status'] ?? '') !== 'run' || (string)($o['pid'] ?? '') === '' || (int)($o['checked'] ?? 0) > time() - 30) return $o;
    if (!function_exists('svSyncPanel') || !function_exists('svOpv')) return $o;
    $pv = svOpv($o);
    if (!svReady($pv)) return $o;
    svOrderSet((string)$o['id'], ['checked' => time()]);
    svSyncPanel($pv, [(string)$o['pid'] => $o]);
    return svOrder((string)$o['id']) ?: $o;
}

function trkSvText($o) {
    $r   = svRow($o);
    $qty = (int)$o['qty'];
    $rem = (int)$o['remains'];
    $st  = (string)$o['status'];
    $done = $st === 'done' ? $qty : ($rem >= 0 ? max(0, $qty - $rem) : 0);
    return TL('order_status', [
        'code'     => h((string)$o['id']),
        'status'   => h($r['sx']),
        'product'  => h(trim((svApps()[(string)$o['app']]['name'] ?? '') . ' — ' . (string)$o['name'], ' —')),
        'link'     => h((string)$o['link']),
        'qty'      => $qty > 0 ? fmtNum($qty) : '',
        'progress' => trkBar($r['pc']) . ($qty > 0 ? '  (' . fmtNum($done) . '/' . fmtNum($qty) . ')' : ''),
        'amount'   => fmtNum((float)$o['total']),
        'currency' => 'تومان',
        'created'  => date('Y-m-d H:i', (int)$o['created']),
    ]);
}

function trkNumText($o) {
    $act   = function_exists('numGet') ? (numGet((string)$o['id']) ?: []) : [];
    $st    = (string)($o['status'] ?? '');
    $phone = trim((string)($act['phone'] ?? ''));
    $sms   = trim((string)($act['code'] ?? ''));
    $pc    = ['done' => 100, 'paid' => 50][$st] ?? 0;
    return TL('order_status', [
        'code'     => h((string)$o['id']),
        'status'   => h(MaOrder::statusLabel($st)),
        'product'  => h((string)($o['item_name'] ?? '')),
        'phone'    => h($phone),
        'sms'      => h($sms),
        'progress' => trkBar($pc),
        'amount'   => fmtNum((float)($o['total'] ?? 0)),
        'currency' => h((string)($o['currency'] ?? 'تومان')),
        'created'  => h((string)($o['created_at'] ?? '')),
    ]);
}

function trkShow($uid, $chatId, $code, $msgId = null, $replyTo = null) {
    $code = strtolower(trim((string)$code));
    $text = null;
    if (str_starts_with($code, 'sv_') && function_exists('svOrder')) {
        $o = svOrder($code);
        if ($o && ((int)$o['uid'] === (int)$uid || isAdmin($uid))) $text = trkSvText(trkSvFresh($o));
    } elseif (str_starts_with($code, 'ma_')) {
        $o = MaOrder::get($code);
        if ($o && ((int)($o['user_id'] ?? 0) === (int)$uid || isAdmin($uid))) $text = trkNumText($o);
    }
    $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];
    if ($text === null) {
        $bad = T('track_bad', ['code' => h($code)]);
        if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $bad);
        else sendMsg(BOT_TOKEN, $chatId, $bad, null, $extra);
        return false;
    }
    $kb = inlineKb([[btnUI('trk_refresh', 'trk_' . $code, 'info')]]);
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, $kb);
    else sendMsg(BOT_TOKEN, $chatId, $text, $kb, $extra);
    return true;
}

if (!defined('PANEL_MAX_TRIES'))      define('PANEL_MAX_TRIES', 6);
if (!defined('PANEL_MAX_TRIES_ALL'))  define('PANEL_MAX_TRIES_ALL', 30);
if (!defined('PANEL_LOCK_SECONDS'))   define('PANEL_LOCK_SECONDS', 900);
if (!defined('PANEL_IDLE_SECONDS'))   define('PANEL_IDLE_SECONDS', 7200);

function panelIp() {
    return substr(hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '0')), 0, 24);
}

function panelLockLeft() {
    $a = load('panel_lock');
    $now = time();

    $r = $a[panelIp()] ?? null;
    if (is_array($r) && (int)($r['n'] ?? 0) >= PANEL_MAX_TRIES) {
        $left = PANEL_LOCK_SECONDS - ($now - (int)($r['at'] ?? 0));
        if ($left > 0) return $left;
    }

    $g = $a['_all'] ?? null;
    if (is_array($g) && (int)($g['n'] ?? 0) >= PANEL_MAX_TRIES_ALL) {
        $left = PANEL_LOCK_SECONDS - ($now - (int)($g['at'] ?? 0));
        if ($left > 0) return $left;
    }
    return 0;
}

function panelNoteFail() {
    $k = panelIp();
    mutate('panel_lock', function (&$a) use ($k) {
        foreach ([$k, '_all'] as $kk) {
            $r = $a[$kk] ?? ['n' => 0, 'at' => 0];
            if (time() - (int)$r['at'] > PANEL_LOCK_SECONDS) $r = ['n' => 0, 'at' => 0];
            $r['n'] = (int)$r['n'] + 1;
            $r['at'] = time();
            $a[$kk] = $r;
        }
        foreach ($a as $kk => $vv)
            if ($kk !== '_all' && time() - (int)($vv['at'] ?? 0) > 86400) unset($a[$kk]);
    });
}

function panelClearFails() {
    $k = panelIp();
    mutate('panel_lock', function (&$a) use ($k) { unset($a[$k], $a['_all']); });
}

function panelPassIn($s) {
    $s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{00A0}]/u', '', (string)$s);
    return trim(norm_fa_digits((string)$s));
}

function panelPassVerify($given) {
    $given = panelPassIn($given);
    $hash = defined('ADMIN_PASSWORD_HASH') ? (string)ADMIN_PASSWORD_HASH : (string)getenv('ADMIN_PASSWORD_HASH');
    if (strlen($hash) >= 20) return password_verify($given, $hash);
    $plain = defined('ADMIN_PASSWORD') ? (string)ADMIN_PASSWORD : (string)getenv('ADMIN_PANEL_PASS');
    return $plain !== '' && hash_equals($plain, $given);
}

function panelPhoneNorm($s) {
    $s = function_exists('norm_fa_digits') ? norm_fa_digits((string)$s) : (string)$s;
    return preg_replace('/\D+/', '', (string)$s);
}

function panelEmailMatch($given) {
    $want = defined('ADMIN_EMAIL') ? (string)ADMIN_EMAIL : (string)getenv('ADMIN_EMAIL');
    if (trim($want) === '') return true;
    return hash_equals(mb_strtolower(trim($want)), mb_strtolower(trim((string)$given)));
}

function panelPhoneMatch($given) {
    $want = defined('ADMIN_PHONE') ? (string)ADMIN_PHONE : (string)getenv('ADMIN_PHONE');
    if (trim($want) === '') return true;
    $w = substr(panelPhoneNorm($want), -10);
    $g = substr(panelPhoneNorm($given), -10);
    return $w !== '' && strlen($g) >= 7 && hash_equals($w, $g);
}

function panelOtpAvailable() {
    return defined('BOT_TOKEN') && BOT_TOKEN !== '' && defined('ADMIN_ID') && (int)ADMIN_ID > 0 && function_exists('sendMsg');
}

function panelOtpKey() {
    $s = (defined('WEBHOOK_SECRET') ? (string)WEBHOOK_SECRET : '') . '|' . (defined('ADMIN_PASSWORD') ? (string)ADMIN_PASSWORD : '');
    return $s !== '|' ? $s : 'numbix_panel_otp';
}

function panelOtpSend() {
    if (!panelOtpAvailable()) return false;
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp'] = ['h' => hash_hmac('sha256', $code, panelOtpKey()), 'at' => time(), 'n' => 0];
    $r = sendMsg(BOT_TOKEN, (int)ADMIN_ID,
        "\u{1F510} کدِ ورود به پنلِ وب:\n\n<code>" . $code . "</code>\n\n۵ دقیقه اعتبار دارد. اگر خودتان تلاش نکرده‌اید، نادیده بگیرید و رمز را عوض کنید.");
    return !empty($r['ok']);
}

function panelOtpOk($given) {
    $o = $_SESSION['otp'] ?? null;
    if (!is_array($o) || empty($o['h'])) return false;
    if (time() - (int)($o['at'] ?? 0) > 300) { unset($_SESSION['otp']); return false; }
    if ((int)($o['n'] ?? 0) >= 5) { unset($_SESSION['otp']); return false; }
    $_SESSION['otp']['n'] = (int)($o['n'] ?? 0) + 1;
    $code = preg_replace('/\D+/', '', function_exists('norm_fa_digits') ? norm_fa_digits((string)$given) : (string)$given);
    $ok = hash_equals((string)$o['h'], hash_hmac('sha256', $code, panelOtpKey()));
    if ($ok) unset($_SESSION['otp']);
    return $ok;
}


if (defined('MEMBERSHIP_LIB_ONLY')) return;

if (isset($_GET['ipn'])) {
    monSection('wallet', 'ipn');
    try { handleIpn(); }
    catch (Throwable $e) { error_log('[ipn] ' . $e->getMessage()); http_response_code(500); echo 'err'; }
    exit;
}

if (isset($_GET['irpay'])) {
    monSection('wallet', 'irpay');
    try { if (function_exists('irCallback')) irCallback(); else { http_response_code(404); echo 'off'; } }
    catch (Throwable $e) { error_log('[irpay] ' . $e->getMessage()); http_response_code(500); echo 'err'; }
    exit;
}
if (isset($_GET['paystat'])) {
    monSection('wallet', 'paystat');
    try { if (function_exists('payStatusJson')) payStatusJson(); else { http_response_code(404); echo '{"ok":false}'; } }
    catch (Throwable $e) { error_log('[paystat] ' . $e->getMessage()); http_response_code(500); echo '{"ok":false}'; }
    exit;
}

if (isset($_GET['app'])) {
    $__ak = (string)$_GET['app'];
    monSection(['tgs' => 'services', 'igs' => 'services', 'wheel' => 'games'][$__ak] ?? 'numbers', 'page_' . $__ak);
    unset($__ak);
    if (!shHit('ip:' . maClientIp(), max(300, (int)(maCfg()['rate_ip'] ?? 3000)), 60)) {
        monSection('shield', 'ipflood');
        http_response_code(429);
        exit;
    }
    if (function_exists('maNoNet')) maNoNet(true);
    try { maServe((string)$_GET['app']); }
    catch (Throwable $e) {
        error_log('[miniapp] ' . $e->getMessage());
        http_response_code(500);
        echo 'server error';
    }
    exit;
}

if (isset($_GET['maav'])) {
    monSection('media', 'avatar');
    try { maServeAvatar(); }
    catch (Throwable $e) { error_log('[maav] ' . $e->getMessage()); http_response_code(204); }
    exit;
}

if (isset($_GET['marp'])) {
    monSection('media', 'review');
    try { maServeReviewPhoto(); }
    catch (Throwable $e) { error_log('[marp] ' . $e->getMessage()); http_response_code(204); }
    exit;
}

if (isset($_GET['mapi'])) {
    monSection('numbers', 'api');
    if (function_exists('maNoNet')) maNoNet(true);
    try { maApi(); }
    catch (Throwable $e) {
        error_log('[mapi] ' . $e->getMessage());
        maApiOut(['ok' => false, 'error' => 'server_error', 'message' => 'خطای سرور — دوباره تلاش کنید.'], 500);
    }
    exit;
}

if (isset($_GET['cron'])) {
    monSection('cron', 'cron');
    http_response_code(200);
    if (strlen(CRON_KEY) < 12 || !hash_equals(CRON_KEY, (string)$_GET['cron'])) {
        echo 'forbidden'; exit;
    }
    @set_time_limit(170);
    @touch(DATA_DIR . '/.cron_at');
    echo 'gw: ' . gwPoll(50) .
         ' · games: ' . gmTick(50) .
         ' · numbers: ' . numTick(50) .
         ' · services: ' . (function_exists('svSync') ? svSync(100) : 0) .
         ' · refills: ' . (function_exists('svRefillSync') ? svRefillSync(60) : 0) .
         ' · archive: ' . (ordersArchive() + maOrdersArchive()) .
         ' · mine: ' . (function_exists('mnTick') ? mnTick(50) : 0) .
         ' · bank: ' . (function_exists('bkPendSweep') ? bkPendSweep(200) : 0) .
         ' · quiz: ' . (function_exists('qzTick') ? qzTick(20) : 0) .
         ' · wheel: ' . (function_exists('whTick') ? whTick() : 0) .
         ' · states: ' . (function_exists('stateSweep') ? stateSweep() : 0) .
         ' · top_members: ' . (function_exists('tpSweep') ? tpSweep(120, 500) : 0) .
         ' · broadcast: ' . bcTick(3000, 50);
    monCompact();
    drTick();
    runBackgroundQueues(true);
    exit;
}

function seenMessage($update) {
    foreach (['message', 'edited_message', 'callback_query'] as $k) {
        if (!isset($update[$k])) continue;
        $m = ($k === 'callback_query') ? ($update[$k]['message'] ?? null) : $update[$k];
        $chat = $m['chat']['id'] ?? null;
        $mid  = $m['message_id'] ?? null;
        if ($chat === null || $mid === null) continue;
        $extra = ($k === 'callback_query') ? ('c' . ($update[$k]['id'] ?? '')) : '';
        return seenKey('m' . $chat . '_' . $mid . '_' . $extra);
    }
    return true;
}

function seenUpdate($id) {
    $id = (int)$id;
    if ($id <= 0) return true;
    return seenKey('u' . $id);
}

function seenDb() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    if (!class_exists('SQLite3') && !dbOn()) return $db = false;
    try { $db = nbRawOpen(DATA_DIR . '/seen.sqlite'); }
    catch (Throwable $e) { error_log('[seen] باز نشد: ' . $e->getMessage()); return $db = false; }
    $db->busyTimeout(3000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS seen (k TEXT PRIMARY KEY, at INTEGER NOT NULL) WITHOUT ROWID');
    $db->exec('CREATE INDEX IF NOT EXISTS seen_at ON seen (at)');
    return $db;
}

function seenKey($key) {
    $key = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$key);
    if ($key === '') return true;
    $db = seenDb();
    if (!$db) return true;
    $st = $db->prepare('INSERT OR IGNORE INTO seen (k, at) VALUES (:k, :t)');
    if (!$st) return true;
    $st->bindValue(':k', $key, SQLITE3_TEXT);
    $st->bindValue(':t', time(), SQLITE3_INTEGER);
    if (!@$st->execute()) return true;
    if ($db->changes() === 0) return false;
    if (mt_rand(1, 400) === 1) { $st = $db->prepare('DELETE FROM seen WHERE at < :c'); if ($st) { $st->bindValue(':c', time() - 3600, SQLITE3_INTEGER); @$st->execute(); } }
    return true;
}

function seenDropOldDir() {
    $base = DATA_DIR . '/.upd';
    if (!is_dir($base)) return;
    foreach ((array)@scandir($base) as $sh) {
        if ($sh === '.' || $sh === '..' || $sh === '') continue;
        $d = $base . '/' . $sh;
        if (is_dir($d)) {
            foreach ((array)@scandir($d) as $f) if ($f !== '.' && $f !== '..') @unlink($d . '/' . $f);
            @rmdir($d);
        } else @unlink($d);
    }
    @rmdir($base);
}

function panelLabel($label) {
    $t = trim(strip_tags((string)$label));
    $clean = preg_replace(
        '/[\x{1F300}-\x{1FAFF}\x{2190}-\x{21FF}\x{2300}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{20E3}\x{2600}-\x{26FF}]/u',
        '', $t);
    $clean = preg_replace('/\s{2,}/u', ' ', (string)$clean);
    $clean = (string)preg_replace('/^[\s|\-–—]+|[\s|\-–—]+$/u', '', (string)$clean);
    if ($clean !== '') return $clean;
    return $t !== '' ? $t : '•';
}

function panelMode($set = null) {
    static $on = false;
    if ($set !== null) $on = (bool)$set;
    return $on;
}

function admStateUid($chatId) {
    $c = (int)$chatId;
    return $c > 0 ? $c : (int)ADMIN_ID;
}

function tickDue($key, $secs = 20) {
    $f = rtrim(DATA_DIR, '/') . '/.tick_' . preg_replace('/[^a-z0-9_]/i', '', (string)$key);
    if (time() - (int)@filemtime($f) < max(1, (int)$secs)) return false;
    @touch($f);
    return true;
}

function closeRequest($body = '') {
    ignore_user_abort(true);
    if (!headers_sent()) {
        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
    }
    echo $body;

    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
    else { while (ob_get_level() > 0) @ob_end_flush(); @flush(); }
}

function migrateOnce($key, callable $fn) {
    $mark = DATA_DIR . '/.migrated_' . $key;
    if (is_file($mark)) return false;
    try {
        $fn();
    } catch (Throwable $e) {
        error_log('[shop-bot] مهاجرت ' . $key . ' نگرفت: ' . $e->getMessage());
        return false;
    }
    @touch($mark);
    return true;
}

function hookAllowMemberUpdates() {
    $i = tg(BOT_TOKEN, 'getWebhookInfo', [], 8);
    if (empty($i['ok'])) throw new RuntimeException('getWebhookInfo');
    $url  = (string)($i['result']['url'] ?? '');
    $have = (array)($i['result']['allowed_updates'] ?? []);
    if ($url === '' || WEBHOOK_SECRET === '' || !$have || in_array('my_chat_member', $have, true)) return;
    $p = [
        'url' => $url, 'secret_token' => WEBHOOK_SECRET,
        'allowed_updates' => json_encode(array_values(array_unique(array_merge($have, ['my_chat_member'])))),
    ];
    $p['max_connections'] = nbHookMax();
    $r = tg(BOT_TOKEN, 'setWebhook', $p, 8);
    if (empty($r['ok'])) throw new RuntimeException('setWebhook');
}

function nbHookMax() {
    return defined('NB_HOOK_MAX') ? max(5, min(100, (int)NB_HOOK_MAX)) : 12;
}

function hookTune() {
    $i = tg(BOT_TOKEN, 'getWebhookInfo', [], 8);
    if (empty($i['ok'])) throw new RuntimeException('getWebhookInfo');
    $url = (string)($i['result']['url'] ?? '');
    if ($url === '' || WEBHOOK_SECRET === '' || (int)($i['result']['max_connections'] ?? 40) === nbHookMax()) return;
    $p = ['url' => $url, 'secret_token' => WEBHOOK_SECRET, 'max_connections' => nbHookMax()];
    $have = (array)($i['result']['allowed_updates'] ?? []);
    if ($have) $p['allowed_updates'] = json_encode(array_values($have));
    $r = tg(BOT_TOKEN, 'setWebhook', $p, 8);
    if (empty($r['ok'])) throw new RuntimeException('setWebhook');
}

function pxAnswerThenWarm($text, $chatId, $replyTo = null) {
    if (!function_exists('pxHandleText')) return false;

    $cold = !function_exists('pxHasAnyCache') || !pxHasAnyCache();

    $stale = function_exists('pxStale') ? pxStale() : false;
    if (!$cold && function_exists('pxNoNet')) pxNoNet(true);
    $hit = pxHandleText($text, $chatId, $replyTo);
    if (function_exists('pxNoNet')) pxNoNet(false);

    if (!$hit) return false;
    if (($stale || $cold) && function_exists('pxWarm')) pxWarm();
    return true;
}

function techHealthCheck() {
    $warn = [];

    if (function_exists('opcache_get_status')) {
        $st = @opcache_get_status(false);
        if (!is_array($st) || empty($st['opcache_enabled']))
            $warn[] = '⚡️ opcache خاموش است — کدِ ربات در هر درخواست از نو کامپایل می‌شود.';
        else {
            $free = (float)($st['memory_usage']['free_memory'] ?? 0) / 1048576;
            if ($free < 8) $warn[] = '⚡️ حافظه‌ی opcache دارد تمام می‌شود (' . number_format($free, 0) . ' مگابایت آزاد).';
        }
    }

    $biggest = 0; $biggestName = '';
    foreach ((glob(DATA_DIR . '/*.json') ?: []) as $f) {
        $kb = @filesize($f) / 1024;
        if ($kb > $biggest) { $biggest = $kb; $biggestName = basename($f, '.json'); }
    }
    if ($biggest > 2000)
        $warn[] = '🗄 فایلِ «' . $biggestName . '» به ' . number_format($biggest / 1024, 1) .
                   ' مگابایت رسیده — هر نوشتن روی آن کندتر می‌شود.';

    $free = @disk_free_space(DATA_DIR);
    if ($free !== false && $free < 100 * 1048576)
        $warn[] = '💾 فضای دیسک کم مانده (' . number_format($free / 1048576, 0) . ' مگابایت آزاد).';

    if (numReady()) {
        [$bal, $cur, $bErr] = numBalanceDo();
        if ($bErr === '' && $bal < 1)
            $warn[] = '☎️ موجودیِ حسابِ ' . numProvName() .
                       ' رو به اتمام است: <b>' . fmtNum($bal) . '</b> ' . h($cur) . '.';
    }

    if ($warn && function_exists('chTechAlert'))
        chTechAlert("🩺 <b>بررسیِ روزانه</b>\n\n" . implode("\n", $warn));
}

function admCheckText() {
    $ok = fn($b) => $b ? '✅' : '🔴';
    $sw = function ($fn) use ($ok) {
        if (!function_exists($fn)) return '➖ نصب نیست';
        return $fn() ? '✅ روشن' : '❌ خاموش';
    };

    $t  = "🩺 <b>چکاپِ بخش‌ها</b>\n\n";

    $gd   = function_exists('imagecreatetruecolor');
    $ft   = function_exists('imagettftext');
    $font = function_exists('pxFont') ? (string)pxFont(true) : '';
    $t .= "<b>ساختِ کارت‌ها</b>\n";
    $t .= $ok($gd) . " GD · " . $ok($ft) . " FreeType\n";
    $t .= ($font !== '' ? '✅ فونت: <code>' . h(basename($font)) . '</code>'
                        : '🔴 هیچ فونتی پیدا نشد') . "\n";
    if ($font === '')
        $t .= "└ تا این درست نشود، کارتِ قیمت و تاپ الماسی و بانک هر سه\n" .
              "   بی‌صدا به متنِ ساده تبدیل می‌شوند.\n" .
              "   پنل ← 💹 قیمت ← 🖼 کارت ← 🔄 گشتنِ دوباره‌ی فونت\n";
    $t .= "\n";

    $t .= "<b>قابلیت‌ها</b>\n";
    $rows = [
        'قیمت لحظه‌ای — کارت' => function_exists('pxVal')
            ? (!empty(pxVal('card.on')) ? (function_exists('pxCardWhy') && pxCardWhy() !== ''
                ? '⚠️ روشن، ولی ساخته نمی‌شود' : '✅ روشن') : '❌ خاموش')
            : '➖ نصب نیست',
        'تاپ الماسی'   => $sw('tpOn'),
        'تاپ الماسی — کارت' => function_exists('tpCardOn')
            ? (tpCardOn() ? '✅ روشن'
                          : (function_exists('tpVal') && !empty(tpVal('card'))
                             ? '⚠️ روشن، ولی ساخته نمی‌شود' : '❌ خاموش'))
            : '➖ نصب نیست',
        'بانک الماسی'  => $sw('bkOn'),
        'الماس'        => $sw('dmOn'),
        'بازی‌ها'      => $sw('gmOn'),
        'ماین'         => $sw('mnOn'),
        'کوییز'        => $sw('qzOn'),
        'درگاه رمزارز' => $sw('gwOn'),
    ];
    foreach ($rows as $k => $v) $t .= '• ' . $k . ': ' . $v . "\n";

    $t .= "\n<b>☎️ فروش شماره</b>\n";
    $t .= '• مینی‌اپ: ' . (maReady() ? '✅ آماده' : (empty(maCfg()['on']) ? '❌ خاموش' : '🔴 آدرس ثبت نشده')) . "\n";
    $t .= '• فروشنده: ' . (numReady() ? '✅ ' . h(numProvName()) : '🔴 وصل نیست') . "\n";
    $t .= '• کشور/اپراتورِ فعال: <b>' . count(maCatalogPublic()['cats']) . '</b> / <b>' .
          count(maCatalogPublic()['items']) . "</b>\n";
    $t .= '• شماره‌ی باز: <b>' . number_format(MaOrder::countBy(MaOrder::PAID)) . "</b>\n";

    if (function_exists('pxAssetPrice')) {
        $t .= "\n<b>قیمت‌ها</b>\n";
        foreach (['usd' => 'دلار', 'aed' => 'درهم', 'eur' => 'یورو',
                  'try' => 'لیر', 'gold' => 'طلا'] as $k => $nm) {
            $v = (float)pxAssetPrice($k);
            $t .= '• ' . $nm . ': ' . ($v > 0 ? '✅ ' . number_format($v) : '🔴 نیامد') . "\n";
        }
    }

    return $t;
}

function admCheck($chatId, $msgId = 0) {
    $t  = admCheckText();
    $kb = [[btnCb('🔄 دوباره چک کن', 'adm_check', 'confirm')],
           [btnCb(UT('back'), 'adm_home', 'nav')]];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($kb));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($kb));
}

function admSpeedText() {
    $t  = "⚡️ <b>سرعت ربات</b>\n\n";

    $st = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
    $on = is_array($st) && !empty($st['opcache_enabled']);
    $t .= "<b>opcache</b>: " . ($on ? '✅ روشن' : '❌ خاموش') . "\n";
    if ($on) {
        $mem  = $st['memory_usage'] ?? [];
        $free = (float)($mem['free_memory'] ?? 0) / 1048576;
        $hit  = (float)($st['opcache_statistics']['opcache_hit_rate'] ?? 0);
        $t .= 'اصابت: <b>' . number_format($hit, 1) . '٪</b> · حافظه‌ی آزاد: <b>' .
              number_format($free, 0) . "</b> مگابایت\n";
        if ($free < 8) $t .= "⚠️ حافظه‌اش کم است — <code>opcache.memory_consumption=128</code>\n";
        if ($hit > 0 && $hit < 90) $t .= "⚠️ نرخ اصابت پایین است؛ شاید حافظه‌اش کم باشد.\n";
    } else {
        $t .= "\n🔴 <b>مهم‌ترین کاری که می‌توانید بکنید همین است.</b>\n" .
              "بدون آن، PHP در هر درخواست کلِ کدِ ربات را از نو می‌خواند و " .
              "کامپایل می‌کند. روی یک سرور معمولی این تفاوت <b>۳۳</b> میلی‌ثانیه " .
              "با <b>۵</b> میلی‌ثانیه است — در هر پیام، هر دکمه، هر باز کردنِ مینی‌اپ.\n\n" .
              "در php.ini بگذارید:\n" .
              "<code>opcache.enable=1</code>\n" .
              "<code>opcache.memory_consumption=128</code>\n" .
              "<code>opcache.max_accelerated_files=10000</code>\n" .
              "<code>opcache.revalidate_freq=2</code>\n\n" .
              "در سی‌پنل: Select PHP Version ← Extensions ← تیک <b>opcache</b>\n";
    }

    $t .= "\n<b>فایل‌های داده</b>\n";
    $files = glob(DATA_DIR . '/*.json') ?: [];
    usort($files, fn($a, $b) => filesize($b) <=> filesize($a));
    $total = 0;
    foreach ($files as $f) $total += filesize($f);
    foreach (array_slice($files, 0, 6) as $f) {
        $kb = filesize($f) / 1024;
        $t .= '• <code>' . h(basename($f, '.json')) . '</code> — <b>' .
              number_format($kb, 1) . "</b> KB" .
              ($kb > 800 ? ' ⚠️' : '') . "\n";
    }
    $t .= 'مجموع: <b>' . number_format($total / 1024, 1) . "</b> KB\n";
    if ($total > 3145728)
        $t .= "⚠️ داده‌ها بزرگ شده‌اند. هر نوشتن یعنی بازنویسیِ کلِ فایل.\n";

    $probe = DATA_DIR . '/.speed.tmp';
    $blob  = str_repeat('x', 262144);
    $t0 = microtime(true);
    for ($i = 0; $i < 5; $i++) { file_put_contents($probe, $blob); }
    $w = (microtime(true) - $t0) / 5 * 1000;
    $t0 = microtime(true);
    for ($i = 0; $i < 5; $i++) { @file_get_contents($probe); }
    $r = (microtime(true) - $t0) / 5 * 1000;
    @unlink($probe);
    $t .= "\n<b>دیسک</b> (۲۵۶ کیلوبایت)\n";
    $t .= 'نوشتن: <b>' . number_format($w, 2) . '</b> ms · خواندن: <b>' .
          number_format($r, 2) . "</b> ms\n";
    if ($w > 20) $t .= "⚠️ دیسک کند است — روی هاست اشتراکی معمول است.\n";

    $t0 = microtime(true);
    for ($i = 0; $i < 5; $i++) maBoot();
    $b = (microtime(true) - $t0) / 5 * 1000;
    $t .= "\n<b>ساختِ صفحه‌ی مینی‌اپ</b>: <b>" . number_format($b, 2) . "</b> ms\n";

    return $t;
}

function nbDropOldLayout() {
    foreach (['numbers', 'services', 'services_pick', 'pay', 'coupons', 'miniapps', 'miniapp_view', 'miniapp_view_tgs', 'miniapp_view_igs',
              'miniapp_view_wheel', 'airdrop', 'games', 'mine', 'quiz', 'wheel', 'toplist', 'diamond', 'bank', 'bankcard',
              'prices', 'translate', 'autoreply', 'channels', 'fonts'] as $m)
        if (is_file(NB_ROOT . '/' . $m . '.php') && is_file(nbMod($m))) @unlink(NB_ROOT . '/' . $m . '.php');
    foreach (['fonts', 'img'] as $d) {
        $old = NB_ROOT . '/' . $d;
        if (!is_dir($old) || !is_dir(nbAsset($d))) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($old, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $rel = substr($f->getPathname(), strlen($old) + 1);
            if ($f->isDir()) { @rmdir($f->getPathname()); continue; }
            $new = nbAsset($d . '/' . $rel);
            if (is_file($new) && filesize($new) === $f->getSize()) @unlink($f->getPathname());
        }
        @rmdir($old);
    }
}

function bgBudget($until = null) {
    static $t = 0.0;
    if ($until !== null) $t = (float)$until;
    return $t;
}

function bgCronAlive() {
    return time() - (int)@filemtime(DATA_DIR . '/.cron_at') < 150;
}

function bgRun($mark, $every, callable $fn) {
    if (bgBudget() > 0 && microtime(true) > bgBudget()) return;
    $f = DATA_DIR . '/' . $mark;
    if (time() - (@filemtime($f) ?: 0) < $every) return;
    $lk = @fopen($f . '.lock', 'c');
    if (!$lk) return;
    if (!flock($lk, LOCK_EX | LOCK_NB)) { fclose($lk); return; }
    try {
        clearstatcache(true, $f);
        if (time() - (@filemtime($f) ?: 0) < $every) return;
        @touch($f);
        $fn();
    } finally {
        flock($lk, LOCK_UN);
        fclose($lk);
    }
}

function runBackgroundQueues($cron = false) {
    $hook = !$cron;
    if ($hook && bgCronAlive()) bgBudget(microtime(true));
    elseif ($hook) bgBudget(microtime(true) + 1.2);

    if ($hook) bgRun('.gw_at', 20, fn() => gwPoll(10));

    bgRun('.queue_at', 60, function () use ($hook) {
        if ($hook) {
            gmTick(5);
            if (function_exists('mnTick')) mnTick(10);
            if (function_exists('qzTick')) qzTick(10);
            if (function_exists('whTick')) whTick(10);
            bcTick(300, 15);
        }

        if (function_exists('pxWarm') && function_exists('pxStale') && pxStale()) pxWarm();
        monCompact();
        monBgTick();
        shPrune();
        drTick();

        foreach (['preview_tg.php', 'preview_num.php', 'miniapp_view_tg.php', 'miniapp_view_num.php',
                  'miniapp_view_react.php', 'miniapp_view_unified.php', 'ops.php', 'ops_bot.php',
                  'ton_wallet.php', 'admin_ext.php', 'profit.php'] as $old)
            if (is_file(NB_ROOT . '/' . $old)) @unlink(NB_ROOT . '/' . $old);
        nbDropOldLayout();

        try {
            $healed = maHealUrls();
            if ($healed) error_log('[heal-urls] ' . implode(', ', $healed));
            maMenuSync();
        } catch (Throwable $e) { error_log('[heal-urls] ' . $e->getMessage()); }
    });

    if ($hook) bgRun('.num_at', 20, fn() => numTick(10));
    if (function_exists('svTick')) bgRun('.svc_at', 60, fn() => svTick());
    bgRun('.ma_cache_prune_at', 21600, fn() => maCachePrune());
    bgRun('.states_prune_at', 21600, fn() => stateSweep());
    bgRun('.health_at', 86400, fn() => techHealthCheck());

    migrateOnce('numbers_only_hooks', function () {
        $toks = [];
        foreach ((array)load('bots') as $b)
            if (is_array($b) && trim((string)($b['token'] ?? '')) !== '') $toks[] = trim((string)$b['token']);
        $ops = trim((string)(load('config')['ops']['token'] ?? ''));
        if ($ops !== '') $toks[] = $ops;
        if (defined('OPS_BOT_TOKEN') && trim((string)OPS_BOT_TOKEN) !== '') $toks[] = trim((string)OPS_BOT_TOKEN);
        foreach (array_unique($toks) as $t)
            if (!hash_equals((string)BOT_TOKEN, $t)) tg($t, 'deleteWebhook', ['drop_pending_updates' => 'true'], 8);
        maMenuSync();
        if (!maItems())
            adminAlertOnce('num_catalog_empty',
                "☎️ <b>فروشگاه شماره هنوز خالی است</b>\n\n" .
                "کشورها و اپراتورها را از فروشنده وارد کنید: /panel ← ☎️ شماره مجازی ← 📥 وارد کردن", 86400);
    });

    migrateOnce('v33_seen_db', function () {
        seenDropOldDir();
    });

    migrateOnce('v2', function () {
        if (function_exists('pxDropOldDemo')) pxDropOldDemo();
    });
    migrateOnce('v14_ref_approved_counts', function () {
        backfillRefCounts();
    });
    migrateOnce('v15_quiz_groups', function () {
        if (function_exists('qzAdoptOldChat')) qzAdoptOldChat();
    });
    migrateOnce('v15_member_updates', function () {
        hookAllowMemberUpdates();
    });
    migrateOnce('v38_hook_max', function () {
        hookTune();
    });
    migrateOnce('v15_cards', function () {
        foreach (['card_*.png', '_bg_v*.png', '_top_v*.png', 'top_*.png'] as $g)
            foreach ((array)glob(DATA_DIR . '/cards/' . $g) as $f) @unlink($f);
    });
    if (defined('FIVESIM_TOKEN') && trim((string)FIVESIM_TOKEN) !== '' && function_exists('numUse5sim'))
        migrateOnce('v16_5sim_' . substr(md5(preg_replace('/\s+/', '', (string)FIVESIM_TOKEN)), 0, 12), function () {
            if (!numTokenShape(preg_replace('/\s+/', '', (string)FIVESIM_TOKEN))['ok']) return;
            numUse5sim((string)FIVESIM_TOKEN);
            numSet(function (&$c) { $c['api']['on'] = true; });
        });
    migrateOnce('v5', function () {
        if (function_exists('gmDropDoubleIcons')) gmDropDoubleIcons();
    });
    migrateOnce('v7_cooldown', function () {
        if (function_exists('dmSet')) dmSet(function (&$c) { $c['cooldown'] = 300; });
    });
    migrateOnce('v17_bank_on', function () {
        if (function_exists('bkSet')) bkSet(function (&$c) { $c['on'] = 1; $c['group_only'] = 0; });
    });
    migrateOnce('v22_kyc_docs', function () {
        cfgSet(function (&$c) {
            if (($c['irpay']['kyc_mode'] ?? 'auto') === 'auto') $c['irpay']['kyc_mode'] = 'docs';
        });
    });
    migrateOnce('v7_ttl', function () {
        if (function_exists('pxSet'))
            pxSet(function (&$c) { if ((int)($c['ttl'] ?? 0) < 60) $c['ttl'] = 60; });
    });

    migrateOnce('v10_cardfiles', function () {
        if (function_exists('pxDropCardCache')) pxDropCardCache();
    });

    migrateOnce('v12_5sim', function () {
        numForceTelegramOnly();
    });

    migrateOnce('v12_dmsum', function () {
        if (function_exists('dmSumRebuild')) dmSumRebuild();
    });

    migrateOnce('v13_gmnames', function () {
        if (!function_exists('gmSet')) return;
        gmSet(function (&$c) {
            foreach (['duel_win', 'rand_win'] as $k) {
                $t = (string)($c['texts'][$k] ?? '');
                if ($t === '' || !str_contains($t, '{winner}')) continue;
                $t = preg_replace('/<code>\s*\{winner\}\s*<\/code>/u', '{wname}', $t);
                $t = preg_replace('/<code>\s*\{loser\}\s*<\/code>/u',  '{lname}', $t);
                $t = str_replace(['{winner}', '{loser}'], ['{wname}', '{lname}'], $t);
                $t = str_replace(['کاربر برنده', 'کاربر بازنده'], ['برنده', 'بازنده'], $t);
                $c['texts'][$k] = $t;
            }
        });
    });

    migrateOnce('v13_dm5min', function () {
        if (!function_exists('dmSet')) return;
        dmSet(function (&$c) {
            $c['cooldown']   = 300;
            $c['level_step'] = 10000;
            if (isset($c['texts']['win']) && is_string($c['texts']['win']))
                $c['texts']['win'] = trim(preg_replace(
                    ['/\s*·?\s*پیشرفت:\s*\{progress\}/u', '/\{progress\}/u'],
                    '', $c['texts']['win']));
        });
    });

    $aMark = DATA_DIR . '/.archive_at';
    if (time() - (@filemtime($aMark) ?: 0) >= 3600) {
        @touch($aMark);
        ordersArchive(0, 800);
        maOrdersArchive(0, 800);
    }
}

if (isset($_GET['bot'])) {
    http_response_code(200);
    exit;
}

if (!webhookSecretOk()) nbDeny(401);

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);
if (is_array($update)) monForUpdate($update);

http_response_code(200);

if (is_array($update)) {
    $dupe = (isset($update['update_id']) && !seenUpdate((int)$update['update_id']))
         || !seenMessage($update);
    if ($dupe) { echo json_encode(['ok' => true]); exit; }
}

closeRequest(json_encode(['ok' => true]));

if (is_array($update)) {
    try {
        masterHandle($update);
    } catch (Throwable $e) {
        $where = basename($e->getFile()) . ':' . $e->getLine();
        error_log('[shop-bot] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (function_exists('adminAlertOnce'))
            adminAlertOnce('crash_' . md5($where),
                "🔴 <b>خطای برنامه</b>\n\n<code>" . h(get_class($e)) . ': ' .
                h(mb_substr($e->getMessage(), 0, 300)) . "</code>\n📍 <code>" . h($where) . '</code>', 600);
        $busy = '⏳ لحظه‌ای مشکل پیش آمد؛ چند ثانیه دیگر دوباره امتحان کنید.';
        if (!empty($update['callback_query']['id'])) answerCb(BOT_TOKEN, $update['callback_query']['id'], $busy, true);
        elseif (($update['message']['chat']['type'] ?? '') === 'private' && !empty($update['message']['chat']['id']))
            sendMsg(BOT_TOKEN, $update['message']['chat']['id'], $busy);
    }
}

tgDrain();
monSplit();
runBackgroundQueues();
