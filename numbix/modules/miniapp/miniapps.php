<?php
defined('NB_ROOT') || exit;

require_once nbMod('miniapp_view');


if (!defined('MA_ACTIVE_MAX')) define('MA_ACTIVE_MAX', 3);

function maDefaultConfig() {
    return [
        'base_url'     => '',
        'on'           => true,
        'title'        => 'نامبیکس | Numbix',
        'menu'         => 'Open',
        'tagline'      => 'شماره‌ی مجازی تلگرام — کد در چند ثانیه',
        'note'         => 'اگر تا پایان مهلت کدی نرسد، مبلغ خودکار به کیف پول شما برمی‌گردد.',
        'sup'          => ['on' => 1, 'chat' => '', 'thread' => 0],
        'init_max_age' => 900,
        'rate_ip'      => 3000,
        'rate_user'    => 40,
        'trust_proxy'  => false,
        'splash'       => 8,
        'cats'         => [],
        'items'        => [],
    ];
}

function maMemo($set = false, $val = null) {
    static $m = null;
    if ($set === 'drop') { $m = null; return null; }
    if ($set) return $m = $val;
    return $m;
}

function maCfg() {
    $hit = maMemo();
    if ($hit !== null) return $hit;
    $d = maDefaultConfig();
    $s = cfg()['miniapps'] ?? null;
    if (!is_array($s)) return maMemo(true, $d);
    $out = array_replace($d, array_intersect_key($s, $d));
    $out['sup'] = array_replace($d['sup'], is_array($s['sup'] ?? null) ? $s['sup'] : []);
    foreach (['cats', 'items'] as $k) $out[$k] = is_array($s[$k] ?? null) ? array_values($s[$k]) : [];
    return maMemo(true, $out);
}

function maForget() {
    maMemo('drop');
}

function maSetRoot(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['miniapps'] ?? null)) $c['miniapps'] = maDefaultConfig();
        $fn($c['miniapps']);
    });
}

function maCats()  { return maCfg()['cats']; }

function maSplashSec() {
    return max(0, min(20, (int)(maCfg()['splash'] ?? 8)));
}
function maItems() { return maCfg()['items']; }

function maFindCat($cid) {
    foreach (maCats() as $c) if ((string)($c['id'] ?? '') === (string)$cid) return $c;
    return null;
}

function maFindItem($iid) {
    foreach (maItems() as $i) if ((string)($i['id'] ?? '') === (string)$iid) return $i;
    return null;
}

function maItemPrice($item) {
    return maMoney((float)($item['price'] ?? 0));
}

function maMoney($v) { return (float)ceil((float)$v - 1e-9); }

function maItemTitle($item) {
    $c = maFindCat((string)($item['cat'] ?? ''));
    $country = trim((string)($c['name'] ?? ''));
    $op = trim((string)($item['name'] ?? ''));
    if ($country === '') return $op;
    return $op === '' ? $country : $country . ' · ' . $op;
}

function maCatalogPublic() {
    $sold = maSoldByCat();
    $live = [];
    foreach (maCats() as $c) if (!empty($c['on']) && (string)($c['id'] ?? '') !== '') $live[(string)$c['id']] = 1;
    $n = $from = [];
    $items = [];
    foreach (maItems() as $i) {
        if (empty($i['on'])) continue;
        $p = maItemPrice($i);
        $c = (string)($i['cat'] ?? '');
        if ($p <= 0 || !isset($live[$c])) continue;
        $n[$c] = ($n[$c] ?? 0) + 1;
        if (!isset($from[$c]) || $p < $from[$c]) $from[$c] = $p;
        $items[] = [
            'i' => (string)$i['id'],
            'c' => $c,
            'o' => (string)($i['name'] ?? ''),
            'p' => $p,
            'b' => (string)($i['badge'] ?? ''),
            'r' => (int)($i['order'] ?? 999),
            'pr' => (string)($i['pr'] ?? '') ?: (function_exists('numProdOfSid') ? numProdOfSid((string)($i['svc'] ?? '')) : 'telegram'),
        ];
        if (!empty($i['hot'])) $items[count($items) - 1]['h'] = 1;
    }

    $cats = [];
    $pos = 0;
    foreach (maCats() as $c) {
        $id = (string)($c['id'] ?? '');
        $pos++;
        if ($id === '' || empty($c['on']) || empty($n[$id])) continue;
        $em = trim((string)($c['emoji'] ?? ''));
        if (($em === '' || $em === '🌍') && function_exists('numFlagFa'))
            $em = numFlagFa((string)($c['code'] ?? ''), (string)($c['name'] ?? ''));
        $cats[] = [
            'id'   => $id,
            'name' => (string)($c['name'] ?? ''),
            'e'    => $em !== '' ? $em : '🌍',
            'n'    => (int)$n[$id],
            'from' => (float)$from[$id],
            'sold' => (int)($sold[$id] ?? 0),
            'r'    => $pos,
            'en'   => (string)($c['code'] ?? ''),
        ];
    }

    usort($items, fn($x, $y) => [$x['c'], $x['p'], $x['r']] <=> [$y['c'], $y['p'], $y['r']]);
    foreach ($items as &$it) unset($it['r']);
    unset($it);

    return ['cats' => $cats, 'items' => $items];
}

function maSoldByCat() {
    $hit = maCacheGet('sold_by_cat', 300);
    if (is_array($hit)) return $hit;
    $stale = maCacheGet('sold_by_cat', 0);
    if (is_array($stale)) {
        maAfterResponse('sold_by_cat', function () {
            $fp = @fopen(DATA_DIR . '/.sold_by_cat.lock', 'c');
            if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) { if ($fp) fclose($fp); return; }
            try { if (!is_array(maCacheGet('sold_by_cat', 300, true))) maSoldByCatBuild(); }
            finally { flock($fp, LOCK_UN); fclose($fp); }
        });
        return $stale;
    }
    return maSoldByCatBuild();
}

function maSoldByCatBuild() {
    $byItem = [];
    $db = maOrdersDb();
    if ($db) {
        $res = $db->query("SELECT json_extract(data,'\$.item_id') AS iid, COUNT(*) AS n FROM orders " .
                          "WHERE status = 'done' GROUP BY iid ORDER BY n DESC LIMIT 400");
        if ($res) while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            if ((string)$row['iid'] !== '') $byItem[(string)$row['iid']] = (int)$row['n'];
        }
    }
    $out = [];
    if ($byItem) foreach (maItems() as $i) {
        $k = (string)($i['id'] ?? '');
        if (isset($byItem[$k])) $out[(string)($i['cat'] ?? '')] = ($out[(string)($i['cat'] ?? '')] ?? 0) + $byItem[$k];
    }
    maCachePut('sold_by_cat', $out);
    return $out;
}


function maNum($v) {
    if (is_int($v) || is_float($v)) return (float)$v;
    $v = norm_fa_digits((string)$v);
    $v = str_replace([',', '،', '٬', '_', ' ', "\u{00A0}", "\u{200C}", "\u{200F}"], '', $v);
    $v = trim($v);
    return is_numeric($v) ? (float)$v : 0.0;
}

function maHttp($url, $method = 'GET', $headersRaw = '', $body = '', $timeout = 8, $svc = 'web') {
    $url = trim((string)$url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) return [null, 'آدرس نامعتبر'];
    if (function_exists('ssrfSafeUrl') && !ssrfSafeUrl($url, $ssrfWhy)) return [null, 'آدرس رد شد: ' . $ssrfWhy];

    $headers = [];
    foreach (preg_split('/\r?\n/', (string)$headersRaw) as $line) {
        $line = trim($line);
        if ($line !== '' && str_contains($line, ':')) $headers[] = $line;
    }

    $ch = curl_init($url);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
    ];
    if (strtoupper($method) === 'POST') {
        $opt[CURLOPT_POST] = true;
        $opt[CURLOPT_POSTFIELDS] = (string)$body;
        $hasType = false;
        foreach ($headers as $h) if (stripos($h, 'content-type:') === 0) { $hasType = true; break; }
        if (!$hasType) $headers[] = 'Content-Type: application/json';
    }
    if ($headers) $opt[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opt);

    $res  = monCurl($ch, $svc);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) return [null, 'اتصال برقرار نشد: ' . $err];
    if ($code < 200 || $code >= 300) {
        $why = trim(preg_replace('/\s+/u', ' ', (string)$res));
        return [null, 'کد پاسخ ' . $code . ($why !== '' ? ' — ' . mb_substr($why, 0, 400) : '')];
    }

    $j = json_decode((string)$res, true);
    if (!is_array($j)) return [null, 'پاسخ JSON نبود: ' . mb_substr((string)$res, 0, 120)];
    return [$j, ''];
}

function maHttpRaw($url, $timeout = 12, $svc = 'web') {
    $url = trim((string)$url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) return [null, 'آدرس نامعتبر'];
    if (function_exists('ssrfSafeUrl') && !ssrfSafeUrl($url, $ssrfWhy)) return [null, 'آدرس رد شد: ' . $ssrfWhy];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
    ]);
    $res  = monCurl($ch, $svc);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false) return [null, 'اتصال برقرار نشد: ' . $err];
    if ($code < 200 || $code >= 300) return [null, 'کد پاسخ ' . $code];
    return [(string)$res, ''];
}

function maJsonPath($data, $path) {
    $path = trim((string)$path);
    if ($path === '') return $data;
    foreach (explode('.', $path) as $seg) {
        if ($seg === '') continue;
        if (is_array($data) && array_key_exists($seg, $data)) { $data = $data[$seg]; continue; }
        if (is_array($data) && ctype_digit($seg) && array_key_exists((int)$seg, $data)) { $data = $data[(int)$seg]; continue; }
        return null;
    }
    return $data;
}

function maKv() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    if (!class_exists('SQLite3') && !dbOn()) return $db = false;
    try { $db = nbRawOpen(DATA_DIR . '/cache.sqlite'); }
    catch (Throwable $e) { error_log('[cache] باز نشد: ' . $e->getMessage()); return $db = false; }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT NOT NULL, at INTEGER NOT NULL) WITHOUT ROWID');
    $db->exec('CREATE INDEX IF NOT EXISTS kv_at ON kv (at)');
    $old = dataPath('ma_cache');
    if (is_file($old) && !is_file(DATA_DIR . '/.ma_cache_moved')) {
        $arr = json_decode((string)@file_get_contents($old), true);
        if (is_array($arr) && $arr) {
            $db->exec('BEGIN IMMEDIATE');
            $st = $db->prepare('INSERT OR IGNORE INTO kv (k, v, at) VALUES (:k, :v, :t)');
            foreach ($arr as $k => $x) {
                if (!is_array($x) || !array_key_exists('v', $x)) continue;
                $j = json_encode($x['v'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($j === false) continue;
                $st->bindValue(':k', (string)$k, SQLITE3_TEXT);
                $st->bindValue(':v', $j, SQLITE3_TEXT);
                $st->bindValue(':t', (int)($x['at'] ?? 0), SQLITE3_INTEGER);
                $st->execute();
                $st->reset();
            }
            $db->exec('COMMIT');
        }
        @touch(DATA_DIR . '/.ma_cache_moved');
        @file_put_contents($old, '{}', LOCK_EX);
    }
    return $db;
}

function maKvMemo($key, $row = false) {
    static $m = [];
    if ($key === null) { $m = []; return null; }
    if ($row !== false) { $m[$key] = $row; return $row; }
    return array_key_exists($key, $m) ? $m[$key] : false;
}

function maCacheRow($key, $fresh = false) {
    $key = (string)$key;
    if (!$fresh && ($hit = maKvMemo($key)) !== false) return $hit;
    $db = maKv();
    if (!$db) return null;
    $st = $db->prepare('SELECT v, at FROM kv WHERE k = :k');
    if (!$st) return null;
    $st->bindValue(':k', $key, SQLITE3_TEXT);
    $r = @$st->execute();
    $row = $r ? $r->fetchArray(SQLITE3_NUM) : false;
    return maKvMemo($key, $row ? [json_decode((string)$row[0], true), (int)$row[1]] : null);
}

function maCacheGet($key, $ttl, $fresh = false) {
    $x = maCacheRow($key, $fresh);
    if ($x === null) return null;
    if ($ttl > 0 && (time() - $x[1]) > $ttl) return null;
    return $x[0];
}

function maCacheAt($key) {
    $x = maCacheRow($key);
    return $x === null ? 0 : (int)$x[1];
}

function maCachePut($key, $value) {
    $key = (string)$key;
    $j = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $db = maKv();
    if ($j === false || !$db) return;
    $now = time();
    $st = $db->prepare('INSERT OR REPLACE INTO kv (k, v, at) VALUES (:k, :v, :t)');
    if (!$st) return;
    $st->bindValue(':k', $key, SQLITE3_TEXT);
    $st->bindValue(':v', $j, SQLITE3_TEXT);
    $st->bindValue(':t', $now, SQLITE3_INTEGER);
    if (@$st->execute()) maKvMemo($key, [json_decode($j, true), $now]);
}

function maCacheDrop($prefix) {
    $db = maKv();
    if (!$db) return 0;
    maKvMemo(null);
    $st = $db->prepare("DELETE FROM kv WHERE substr(k, 1, :n) = :p");
    $st->bindValue(':n', strlen((string)$prefix), SQLITE3_INTEGER);
    $st->bindValue(':p', (string)$prefix, SQLITE3_TEXT);
    @$st->execute();
    return $db->changes();
}

function maCachePrune() {
    $db = maKv();
    if (!$db) return;
    maKvMemo(null);
    $st = $db->prepare("DELETE FROM kv WHERE k = 'gr_list' OR at < :c");
    if ($st) { $st->bindValue(':c', time() - 259200, SQLITE3_INTEGER); @$st->execute(); }
}

function maAfterResponse($key, callable $fn) {
    static $jobs = null;
    if ($jobs === null) {
        $jobs = [];
        register_shutdown_function(function () use (&$jobs) {
            if (!$jobs) return;
            ignore_user_abort(true);
            if (function_exists('fastcgi_finish_request'))       @fastcgi_finish_request();
            elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
            foreach ($jobs as $k => $job) {
                try { $job(); } catch (Throwable $e) { error_log('[maAfterResponse/' . $k . '] ' . $e->getMessage()); }
            }
            $jobs = [];
        });
    }
    $jobs[(string)$key] = $fn;
}

function maNoNet($on = null) {
    static $flag = false;
    if ($on !== null) $flag = (bool)$on;
    return $flag;
}

function maLiveUrl() {
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    $self = trim((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($host === '' || $self === '') return '';
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) return '';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
          || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
          || strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
          || strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https';
    return ($https ? 'https' : 'http') . '://' . $host . str_replace('\\', '/', $self);
}

function maLiveBotUrl() {
    $live = maLiveUrl();
    if ($live === '') return '';
    return preg_replace('#^http://#i', 'https://', preg_replace('#/[^/]*$#', '/bot_master_membership.php', $live));
}

function maBaseUrl() {
    $u = trim((string)(maCfg()['base_url'] ?? ''));
    if ($u === '') $u = trim((string)(cfg()['gateway']['base_url'] ?? ''));
    if ($u === '') $u = maLiveBotUrl();
    return rtrim($u, '/');
}

function maHealUrls($force = false) {
    $live = maLiveBotUrl();
    if ($live === '') return [];

    $fixed = [];
    $cur   = trim((string)(maCfg()['base_url'] ?? ''));

    $same = false;
    if ($cur !== '') {
        $a = parse_url(rtrim($cur, '/'));
        $b = parse_url($live);
        $same = (($a['host'] ?? '') === ($b['host'] ?? ''))
             && (($a['path'] ?? '') === ($b['path'] ?? ''))
             && strtolower((string)($a['scheme'] ?? '')) === 'https';
        if (!$force && ($a['host'] ?? '') !== ($b['host'] ?? '')) return [];
    }

    if ($cur === '' || !$same) {
        maSetRoot(function (&$m) use ($live) { $m['base_url'] = rtrim($live, '/'); });
        $fixed[] = 'مینی‌اپ';
    }

    $g = trim((string)(cfg()['gateway']['base_url'] ?? ''));
    if ($g !== '') {
        $p1 = parse_url(rtrim($g, '/')); $p2 = parse_url($live);
        if (($p1['host'] ?? '') === ($p2['host'] ?? '') && ($p1['path'] ?? '') !== ($p2['path'] ?? '')) {
            cfgSet(function (&$c) use ($live) { $c['gateway']['base_url'] = rtrim($live, '/'); });
            $fixed[] = 'درگاه پرداخت';
        }
    }

    return $fixed;
}

function maViewVer() {
    static $v = null;
    if ($v !== null) return $v;
    $t = 0;
    foreach (['miniapp_view', 'miniapps', 'numbers', 'airdrop', 'coupons',
              'services', 'miniapp_view_tgs', 'miniapp_view_igs', 'wheel', 'miniapp_view_wheel'] as $f) {
        $m = @filemtime(nbMod($f));
        if ($m && $m > $t) $t = $m;
    }
    return $v = substr(base_convert((string)($t ?: time()), 10, 36), -6);
}

function maUrl($page = '') {
    $b = maBaseUrl();
    if ($b === '' || !preg_match('#^https://#i', $b)) return '';
    $page = preg_replace('/[^a-z]/', '', (string)$page);
    return $b . (str_contains($b, '?') ? '&' : '?') . 'app=num&v=' . maViewVer()
             . ($page !== '' ? '&p=' . $page : '');
}

function maReady() {
    return !empty(maCfg()['on']) && maUrl() !== '';
}

function maWebAppBtn($label, $page = '', $role = 'buy') {
    $u = maUrl($page);
    if ($u === '') return null;
    $b = ['text' => $label, 'web_app' => ['url' => $u]];
    $st = gs($role);
    if (isStyle($st)) $b['style'] = $st;
    return $b;
}

function maMenuSync() {
    $u   = maReady() ? maUrl() : '';
    $t   = mb_substr(trim((string)maCfg()['menu']) ?: 'Open', 0, 24);
    $sig = md5($u . '|' . $t . '|' . strtok(BOT_TOKEN, ':'));
    if (maCacheGet('menu_sig', 21600) === $sig) return true;
    $b = $u !== '' ? ['type' => 'web_app', 'text' => $t, 'web_app' => ['url' => $u]] : ['type' => 'default'];
    $r = tg(BOT_TOKEN, 'setChatMenuButton',
            ['menu_button' => json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], 8);
    if (!empty($r['ok'])) maCachePut('menu_sig', $sig);
    return !empty($r['ok']);
}

class MaOrder
{
    const PENDING = 'pending';
    const PAID    = 'paid';
    const DONE    = 'done';
    const REJECT  = 'rejected';

    public static function get($id) {
        $db = maOrdersDb();
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

    public static function create($uid, $uname, $item, $total, array $extra = []) {
        $id = 'ma_' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(3));
        $o = array_merge([
            'id' => $id, 'app' => 'num',
            'user_id' => (int)$uid, 'username' => (string)$uname,
            'item_id' => (string)($item['id'] ?? ''), 'item_name' => maItemTitle($item),
            'item_emoji' => (string)($item['emoji'] ?? '☎️'),
            'unit_price' => (float)($item['price'] ?? 0),
            'qty' => 1, 'total' => (float)$total,
            'currency' => 'تومان',
            'status' => self::PENDING, 'pay' => '',
            'created_at' => nowStr(), 'decided_at' => null, 'delivered_at' => null,
        ], $extra);
        $db = maOrdersDb();
        if ($db) {
            $stmt = $db->prepare('INSERT INTO orders (id, user_id, app, status, created_at, data) VALUES (:id, :uid, :app, :status, :created, :data)');
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $stmt->bindValue(':uid', (int)$uid, SQLITE3_INTEGER);
            $stmt->bindValue(':app', 'num', SQLITE3_TEXT);
            $stmt->bindValue(':status', self::PENDING, SQLITE3_TEXT);
            $stmt->bindValue(':created', $o['created_at'], SQLITE3_TEXT);
            $stmt->bindValue(':data', json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $stmt->execute();
        }
        return $id;
    }

    public static function remove($id) {
        $db = maOrdersDb();
        if (!$db) return;
        $stmt = $db->prepare('DELETE FROM orders WHERE id = :id');
        $stmt->bindValue(':id', (string)$id, SQLITE3_TEXT);
        $stmt->execute();
    }

    public static function set($id, callable $fn) {
        $db = maOrdersDb();
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

            $up = $db->prepare('UPDATE orders SET user_id = :uid, app = :app, status = :status, created_at = :created, data = :data WHERE id = :id');
            $up->bindValue(':id', $id, SQLITE3_TEXT);
            $up->bindValue(':uid', (int)($o['user_id'] ?? 0), SQLITE3_INTEGER);
            $up->bindValue(':app', (string)($o['app'] ?? 'num'), SQLITE3_TEXT);
            $up->bindValue(':status', (string)($o['status'] ?? ''), SQLITE3_TEXT);
            $up->bindValue(':created', (string)($o['created_at'] ?? ''), SQLITE3_TEXT);
            $up->bindValue(':data', json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            if (!$up->execute()) throw new RuntimeException('write');
            if (!@$db->exec('COMMIT')) throw new RuntimeException('commit');
            return $r === null ? true : $r;
        } catch (Throwable $e) {
            @$db->exec('ROLLBACK');
            error_log('[shop-bot] MaOrder::set خطا: ' . $e->getMessage());
            return false;
        }
    }

    public static function forUser($uid, $limit = 10) {
        $db = maOrdersDb();
        if (!$db) return [];
        $stmt = $db->prepare("SELECT data FROM orders WHERE user_id = :uid AND app = 'num' ORDER BY created_at DESC LIMIT :lim");
        $stmt->bindValue(':uid', (int)$uid, SQLITE3_INTEGER);
        $stmt->bindValue(':lim', max(0, (int)$limit), SQLITE3_INTEGER);
        $out = [];
        $res = $stmt->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $d = json_decode($row['data'], true);
            if (is_array($d)) $out[] = $d;
        }
        return $out;
    }

    public static function page($status, $q, $offset, $limit) {
        $db = maOrdersDb();
        if (!$db) return [[], 0];
        $where = ["app = 'num'"];
        if ($status !== '') $where[] = 'status = :st';
        if ($q !== '') $where[] = "(id LIKE :q ESCAPE '\\' OR CAST(user_id AS TEXT) = :qe OR data LIKE :q ESCAPE '\\')";
        $w = ' WHERE ' . implode(' AND ', $where);
        $bind = function ($stmt) use ($status, $q) {
            if ($status !== '') $stmt->bindValue(':st', $status, SQLITE3_TEXT);
            if ($q !== '') {
                $stmt->bindValue(':q', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%', SQLITE3_TEXT);
                $stmt->bindValue(':qe', $q, SQLITE3_TEXT);
            }
        };
        $c = $db->prepare('SELECT COUNT(*) AS n FROM orders' . $w);
        $bind($c);
        $total = (int)(($c->execute()->fetchArray(SQLITE3_ASSOC))['n'] ?? 0);
        $s = $db->prepare('SELECT data FROM orders' . $w . ' ORDER BY created_at DESC LIMIT :lim OFFSET :off');
        $bind($s);
        $s->bindValue(':lim', max(1, (int)$limit), SQLITE3_INTEGER);
        $s->bindValue(':off', max(0, (int)$offset), SQLITE3_INTEGER);
        $out = [];
        $res = $s->execute();
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $d = json_decode($row['data'], true);
            if (is_array($d)) $out[] = $d;
        }
        return [$out, $total];
    }

    public static function countBy($status) {
        $db = maOrdersDb();
        if (!$db) return 0;
        $stmt = $db->prepare("SELECT COUNT(*) c FROM orders WHERE app = 'num' AND status = :status");
        $stmt->bindValue(':status', (string)$status, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return (int)($row['c'] ?? 0);
    }

    public static function doneCount($uid) {
        $db = maOrdersDb();
        if (!$db) return 0;
        $stmt = $db->prepare("SELECT COUNT(*) c FROM orders WHERE user_id = :u AND +app = 'num' AND +status = :s");
        $stmt->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
        $stmt->bindValue(':s', self::DONE, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return (int)($row['c'] ?? 0);
    }

    public static function statusLabel($s) {
        return [
            self::PENDING => '⏳ در حال ثبت',
            self::PAID    => '📲 منتظر کد',
            self::DONE    => '✅ کد تحویل شد',
            self::REJECT  => '↩️ برگشت وجه',
        ][$s] ?? '—';
    }
}

function maServe($key) {
    if ((string)($_GET['tgWebAppStartParam'] ?? '') === 'wheel' && function_exists('whServe')) whServe();
    if ($key === 'pay' && function_exists('payServe')) payServe();
    if (function_exists('svServe') && function_exists('svAppOfKey') && svAppOfKey($key) !== '') svServe($key);
    if ($key === 'wheel' && function_exists('whServe')) whServe();
    if (!in_array($key, ['num', 'unified', 'tg', 'react', 'shop'], true)) { http_response_code(404); echo 'not found'; exit; }
    if (!maReady()) { http_response_code(200); header('Content-Type: text/html; charset=utf-8'); echo maClosedPage(); exit; }
    $html = maView(maBoot());
    maSecurityHeaders();
    $tag = substr(hash('sha256', $html), 0, 32);
    header('ETag: W/"' . $tag . '"');
    if (strpos((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), $tag) !== false) { http_response_code(304); exit; }
    maEmit($html);
    exit;
}

function maClosedPage() {
    $t = h((string)(maCfg()['title'] ?? 'نامبیکس | Numbix'));
    return "<!doctype html><html lang=\"fa\" dir=\"rtl\"><head><meta charset=\"utf-8\">" .
           "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>{$t}</title>" .
           "<style>body{background:#000;color:#fff;font-family:system-ui,Tahoma;display:grid;" .
           "place-items:center;height:100vh;margin:0;text-align:center}" .
           "b{display:block;font-size:20px;margin:14px 0 6px}p{opacity:.65;margin:0}</style></head><body><div>" .
           "<div style=\"font-size:54px\">🔒</div><b>{$t}</b><p>موقتا بسته است</p>" .
           "</div></body></html>";
}

function maHealQuick() {
    static $done = false;
    if ($done) return [];
    $done = true;
    $live = maLiveBotUrl();
    if ($live === '') return [];
    $cur = trim((string)(maCfg()['base_url'] ?? ''));
    if ($cur !== '') {
        $a = parse_url(rtrim($cur, '/'));
        $b = parse_url($live);
        if (strcasecmp((string)($a['host'] ?? ''), (string)($b['host'] ?? '')) !== 0) return [];
        if (($a['path'] ?? '') === ($b['path'] ?? '') && strtolower((string)($a['scheme'] ?? '')) === 'https') return [];
    }
    $f = maHealUrls();
    if ($f) error_log('[heal-urls] ' . implode(', ', $f));
    return $f;
}

function maOrdersDbPath() { return DATA_DIR . '/ma_orders.sqlite'; }

function maOrdersDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;

    $path = maOrdersDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fresh = !is_file($path);

    try {
        $db = nbRawOpen($path);
    } catch (Throwable $e) {
        error_log('[shop-bot] ma_orders.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS orders (
        id TEXT PRIMARY KEY, user_id INTEGER NOT NULL, app TEXT NOT NULL,
        status TEXT NOT NULL, created_at TEXT NOT NULL, data TEXT NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_ma_orders_user   ON orders(user_id, created_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_ma_orders_status ON orders(status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_ma_orders_feed   ON orders(status, app, created_at)');
    $db->exec('CREATE TABLE IF NOT EXISTS orders_old (id TEXT PRIMARY KEY, data TEXT NOT NULL)');

    if ($fresh) maOrdersImportFromJson($db);
    return $db;
}

function maOrdersImportFromJson($db) {
    $map = ['ma_orders' => 'orders', 'ma_orders_old' => 'orders_old'];
    foreach ($map as $file => $table) {
        $old = dataPath($file);
        if (!is_file($old)) continue;
        $raw = @file_get_contents($old);
        $arr = $raw ? json_decode($raw, true) : null;
        if (is_array($arr) && $arr) {
            $db->exec('BEGIN');
            if ($table === 'orders') {
                $stmt = $db->prepare('INSERT OR REPLACE INTO orders (id, user_id, app, status, created_at, data) VALUES (:id, :uid, :app, :status, :created, :data)');
                foreach ($arr as $k => $v) {
                    if ($k === '' || !is_array($v)) continue;
                    $stmt->bindValue(':id', (string)$k, SQLITE3_TEXT);
                    $stmt->bindValue(':uid', (int)($v['user_id'] ?? 0), SQLITE3_INTEGER);
                    $stmt->bindValue(':app', (string)($v['app'] ?? ''), SQLITE3_TEXT);
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

function maOrdersArchive($days = 0, $limit = 4000) {
    $days = $days > 0 ? $days : (int)(cfg()['orders_keep_days'] ?? 14);
    if ($days <= 0) return 0;
    $cut = time() - $days * 86400;

    $db = maOrdersDb();
    if (!$db) return 0;

    $stmt = $db->prepare('SELECT id, data FROM orders WHERE (status = :s1 OR status = :s2) AND created_at < :c');
    $stmt->bindValue(':s1', MaOrder::DONE, SQLITE3_TEXT);
    $stmt->bindValue(':s2', MaOrder::REJECT, SQLITE3_TEXT);
    $stmt->bindValue(':c', date('Y-m-d H:i:s', $cut), SQLITE3_TEXT);
    $res = $stmt->execute();

    $moved = [];
    while (count($moved) < $limit && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
        $o = json_decode($row['data'], true);
        if (!is_array($o)) continue;
        $when = strtotime((string)(($o['delivered_at'] ?? '') ?: ($o['decided_at'] ?? '') ?: ($o['created_at'] ?? ''))) ?: 0;
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

function maVerifyInitData($initData, &$reason = null, $maxAge = 0) {
    $reason = '';
    $initData = (string)$initData;

    if ($initData === '')            { $reason = 'empty';    return null; }
    if (strlen($initData) > 8192)    { $reason = 'too_big';  return null; }

    $q = [];
    foreach (explode('&', $initData) as $part) {
        if ($part === '') continue;
        $eq = strpos($part, '=');
        if ($eq === false) continue;
        $q[urldecode(substr($part, 0, $eq))] = urldecode(substr($part, $eq + 1));
    }

    if (empty($q['hash']))           { $reason = 'no_hash';  return null; }
    if (empty($q['user']))           { $reason = 'no_user';  return null; }

    $hash = (string)$q['hash'];
    unset($q['hash']);

    $secret = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
    $mkCheck = function ($fields) {
        ksort($fields);
        $pairs = [];
        foreach ($fields as $k => $v) $pairs[] = $k . '=' . $v;
        return implode("\n", $pairs);
    };

    $withSig = $q;
    $noSig   = $q; unset($noSig['signature']);

    $matched = '';
    foreach (['بدون signature' => $noSig, 'با signature' => $withSig] as $how => $fields) {
        if (hash_equals(hash_hmac('sha256', $mkCheck($fields), $secret), $hash)) { $matched = $how; break; }
        if ($noSig === $withSig) break;
    }
    if ($matched === '') { $reason = 'bad_hash'; return null; }

    if ($maxAge <= 0) $maxAge = (int)(maCfg()['init_max_age'] ?? 900);
    if (empty($q['auth_date']) || !ctype_digit((string)$q['auth_date'])) { $reason = 'no_date'; return null; }
    if ($maxAge > 0) {
        $age = time() - (int)$q['auth_date'];
        if ($age > $maxAge)   { $reason = 'expired:' . $age;  return null; }
        if ($age < -86400)    { $reason = 'clock_skew:' . $age; return null; }
    }

    $user = json_decode((string)$q['user'], true);
    if (!is_array($user) || empty($user['id'])) { $reason = 'bad_user'; return null; }

    return $user;
}

function maAuthReasonText($reason) {
    $r = (string)$reason;
    if (str_starts_with($r, 'expired:')) {
        $sec = (int)substr($r, 8);
        return 'داده ورود منقضی شده (' . round($sec / 60) . ' دقیقه قدیمی‌تر). ' .
               'اگر تازه باز کردید، یعنی ساعت سرور جلو است.';
    }
    if (str_starts_with($r, 'clock_skew:')) {
        $sec = (int)substr($r, 11);
        return 'ساعت سرور ' . round(abs($sec) / 60) . ' دقیقه عقب است — آن را درست کنید.';
    }
    return [
        'empty'    => 'مینی‌اپ بدون اطلاعات ورود باز شده. آن را از دکمه داخل ربات باز کنید، نه از مرورگر.',
        'no_hash'  => 'امضای تلگرام در داده ورود نبود.',
        'no_user'  => 'اطلاعات کاربر در داده ورود نبود. مینی‌اپ را از چت خصوصی ربات باز کنید.',
        'bad_hash' => maBadHashText(),
        'bad_user' => 'اطلاعات کاربر خوانده نشد.',
        'too_big'  => 'داده ورود بیش از حد بزرگ بود.',
    ][$r] ?? 'اعتبارسنجی ناموفق بود.';
}

function maBadHashText() {
    $un = maCacheGet('selfbot', 3600);
    if ($un === null) {
        $me = tg(BOT_TOKEN, 'getMe', []);
        $un = !empty($me['result']['username']) ? '@' . $me['result']['username'] : '';
        maCachePut('selfbot', $un);
    }
    $t = 'امضای تلگرام نخواند.' . "\n\n" .
         'یعنی توکنی که در فایل ربات گذاشته‌اید، مال رباتی نیست که این دکمه را ساخته.';
    if ($un !== '') {
        $t .= "\n\n" . 'توکن داخل فایل مال ربات ' . $un . ' است.' . "\n" .
              'اگر این همان رباتی نیست که الان داخلش هستید، توکن را عوض کنید:' . "\n" .
              'خط ۲۰ فایل bot_master_membership.php';
    }
    $t .= "\n\n" . 'اگر تازه از @BotFather توکن را Revoke کرده‌اید، توکن تازه را در فایل بگذارید و وبهوک را دوباره ست کنید.';
    return $t;
}

if (!defined('MA_RATE_SHARDS')) define('MA_RATE_SHARDS', 16);

function maClientIp() {
    $real = (string)($_SERVER['REMOTE_ADDR'] ?? '0');
    if (empty(maCfg()['trust_proxy'])) return $real;

    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
        $v = trim((string)($_SERVER[$h] ?? ''));
        if ($v === '') continue;
        $v = trim(explode(',', $v)[0]);
        if (filter_var($v, FILTER_VALIDATE_IP)) return $v;
    }
    return $real;
}

function maShardOf($key, $shards = MA_RATE_SHARDS) {
    return hexdec(substr(md5((string)$key), 0, 4)) % $shards;
}

function maRateFile($k) {
    return 'ma_rate_' . maShardOf($k);
}

function maRatePeek($bucket, $id, $limit, $win) {
    $k   = $bucket . ':' . $id;
    $win = max(1, (int)$win);
    $cur = load(maRateFile($k), true)[$k] ?? null;
    if (!is_array($cur) || !isset($cur['w'])) return false;
    $now = time();
    $w0  = $now - $now % $win;
    if ((int)$cur['w'] === $w0) $est = (int)$cur['p'] * (1 - ($now - $w0) / $win) + (int)$cur['n'];
    elseif ((int)$cur['w'] === $w0 - $win) $est = (int)$cur['n'] * (1 - ($now - $w0) / $win);
    else $est = 0;
    return $est >= $limit;
}

function maRateOk($bucket, $id, $limit, $win) {
    $ok = true;
    $k  = $bucket . ':' . $id;
    $win = max(1, (int)$win);
    mutate(maRateFile($k), function (&$a) use ($k, $limit, $win, &$ok) {
        $now = time();
        $cur = $a[$k] ?? null;
        $w0  = $now - $now % $win;
        if (!is_array($cur) || !isset($cur['w'])) $cur = ['w' => $w0, 'n' => 0, 'p' => 0];
        if ((int)$cur['w'] !== $w0) {
            $cur = ['w' => $w0, 'n' => 0, 'p' => (int)$cur['w'] === $w0 - $win ? (int)$cur['n'] : 0];
        }
        $est = (int)$cur['p'] * (1 - ($now - $w0) / $win) + (int)$cur['n'];
        if ($est >= $limit) $ok = false;
        else $cur['n'] = (int)$cur['n'] + 1;
        $a[$k] = $cur;

        if (count($a) > 200) {
            foreach ($a as $kk => $vv) {
                $last = is_array($vv) ? (int)($vv['w'] ?? (isset($vv[0]) ? max($vv) : 0)) : 0;
                if (($now - $last) > 3600) unset($a[$kk]);
            }
        }
    });
    return $ok;
}

function maNonceOk($initData, $max = 15) {
    $sig = substr(hash('sha256', (string)$initData), 0, 32);
    $ok  = true;
    mutate('ma_nonce_' . maShardOf($sig), function (&$a) use ($sig, $max, &$ok) {
        $now = time();
        foreach ($a as $k => $v) {
            $last = is_array($v) ? (int)($v['at'] ?? 0) : (int)$v;
            if (($now - $last) > 7200) unset($a[$k]);
        }
        $cur = is_array($a[$sig] ?? null) ? $a[$sig] : ['n' => 0, 'at' => $now];
        if ((int)$cur['n'] >= $max) { $ok = false; return; }
        $a[$sig] = ['n' => (int)$cur['n'] + 1, 'at' => $now];
    });
    return $ok;
}

function maDuplicateOrder($uid, $app, $itemId, $qty, $field, $win = 45) {
    $sig = hash('sha256', $uid . '|' . $app . '|' . $itemId . '|' . $qty . '|' . $field);
    $dup = false;
    mutate('ma_dup_' . maShardOf($sig), function (&$a) use ($sig, $win, &$dup) {
        $now = time();
        foreach ($a as $k => $t) if (($now - (int)$t) > 600) unset($a[$k]);
        if (isset($a[$sig]) && ($now - (int)$a[$sig]) < $win) { $dup = true; return; }
        $a[$sig] = $now;
    });
    return $dup;
}

function maSecurityHeaders() {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header(
        "Content-Security-Policy: " .
        "default-src 'none'; " .
        "script-src 'self' 'unsafe-inline' https://telegram.org https://*.telegram.org; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com data:; " .
        "img-src 'self' data: https://t.me https://*.telegram.org https://*.telesco.pe; " .
        "connect-src 'self'; " .
        "base-uri 'none'; form-action 'none'; " .
        "frame-ancestors https://web.telegram.org https://*.telegram.org; " .
        "object-src 'none'"
    );
}

function maAvatarSig($uid, $day = null) {
    $day = $day === null ? (int)floor(time() / 86400) : (int)$day;
    return substr(hash_hmac('sha256', 'av|' . (int)$uid . '|' . $day, BOT_TOKEN), 0, 20);
}

function maAvatarUrl($uid) {
    $b = maBaseUrl();
    if ($b === '') return '';
    return $b . (str_contains($b, '?') ? '&' : '?')
         . 'maav=' . (int)$uid . '&s=' . maAvatarSig($uid);
}

function maServeAvatar() {
    $uid = (int)($_GET['maav'] ?? 0);
    $sig = (string)($_GET['s'] ?? '');
    $day = (int)floor(time() / 86400);
    $ok = $uid > 0 && (hash_equals(maAvatarSig($uid, $day), $sig)
                    || hash_equals(maAvatarSig($uid, $day - 1), $sig));
    if (!$ok) { http_response_code(403); exit; }

    $p = function_exists('bcAvatar') ? bcAvatar($uid) : null;
    if (!$p || !is_file($p) || filesize($p) < 64) { http_response_code(204); exit; }
    $p = maAvatarThumb($p, (int)$uid);

    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($p));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($p);
    exit;
}

function maAvatarThumb($src, $uid) {
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) return $src;
    $th = dirname($src) . '/' . (int)$uid . '_t.jpg';
    $mt = (int)@filemtime($src);
    if (is_file($th) && (int)@filemtime($th) >= $mt && filesize($th) > 64) return $th;
    $im = @imagecreatefromstring((string)@file_get_contents($src));
    if (!$im) return $src;
    $w = imagesx($im); $h = imagesy($im); $sq = min($w, $h);
    if ($sq <= 256) { imagedestroy($im); return $src; }
    $dst = imagecreatetruecolor(256, 256);
    imagecopyresampled($dst, $im, 0, 0, intdiv($w - $sq, 2), intdiv($h - $sq, 2), 256, 256, $sq, $sq);
    $tmp = $th . '.' . getmypid() . '.tmp';
    $ok = @imagejpeg($dst, $tmp, 86);
    imagedestroy($dst); imagedestroy($im);
    if (!$ok || !@rename($tmp, $th)) { @unlink($tmp); return $src; }
    @touch($th, max($mt, time()));
    return $th;
}

function maGzipOk() {
    if (headers_sent() || !function_exists('gzencode')) return false;
    $z = strtolower(trim((string)ini_get('zlib.output_compression')));
    if ($z !== '' && $z !== '0' && $z !== 'off') return false;
    foreach (ob_list_handlers() as $h) if (stripos($h, 'gz') !== false || stripos($h, 'zlib') !== false) return false;
    return (bool)preg_match('/\bgzip\b/i', (string)($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''));
}

function maEmit($body) {
    $body = (string)$body;
    if (strlen($body) > 1024 && maGzipOk()) {
        $gz = gzencode($body, 5);
        if ($gz !== false) {
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
            header('Content-Length: ' . strlen($gz));
            echo $gz;
            return;
        }
    }
    echo $body;
}

function maApiOut($data, $code = 200, ?callable $after = null) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($after === null) { maEmit($body); exit; }

    ignore_user_abort(true);
    if (!headers_sent()) {
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
    }
    echo $body;
    if (function_exists('fastcgi_finish_request'))       fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
    else { while (ob_get_level() > 0) @ob_end_flush(); @flush(); }
    monSplit();

    try { $after(); } catch (Throwable $e) { error_log('[maApiOut/after] ' . $e->getMessage()); }
    exit;
}

function maApiUrl() {
    $b = maBaseUrl();
    if ($b === '') return '';
    return $b . (str_contains($b, '?') ? '&' : '?') . 'mapi=1';
}

function maSupportLink() {
    $d = trim((string)(cfg()['support_main']['direct']['value'] ?? ''));
    if ($d !== '') return preg_match('~^https?://~i', $d) ? $d : 'https://t.me/' . ltrim($d, '@');
    return '';
}

function faVarFontCss($dir, $prefix = 'assets/fonts/') {
    $out = '';
    $subs = [
        ['Vazirmatn.woff2',       'U+0600-06FF,U+0750-077F,U+0870-088E,U+0890-0891,U+0897-08E1,U+08E3-08FF,U+200C-200E,U+2010-2011,U+204F,U+2E41,U+FB50-FDFF,U+FE70-FE74,U+FE76-FEFC'],
        ['Vazirmatn-Latin.woff2', 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD'],
    ];
    foreach ($subs as [$f, $range]) {
        if (!is_file($dir . '/' . $f)) continue;
        $out .= "@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100 900;"
              . "font-display:swap;src:url('" . $prefix . rawurlencode($f) . "') format('woff2');"
              . "unicode-range:" . $range . "}\n";
    }
    return $out;
}

function maFontCss() {
    static $memo = null;
    if ($memo !== null) return $memo;

    $dir = nbAsset('fonts');

    $var = faVarFontCss($dir);
    if ($var !== '') return $memo = $var;

    $out   = '';
    $faces = [
        400 => ['Vazirmatn-Regular', 'Vazirmatn', 'Vazir'],
        500 => ['Vazirmatn-Medium'],
        700 => ['Vazirmatn-Bold', 'Vazir-Bold'],
        800 => ['Vazirmatn-ExtraBold'],
        900 => ['Vazirmatn-Black'],
    ];
    foreach ($faces as $w => $names) {
        foreach ($names as $n) {
            foreach (['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype'] as $ext => $fmt) {
                if (!is_file($dir . '/' . $n . '.' . $ext)) continue;
                $out .= "@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:{$w};"
                      . "font-display:swap;src:url('assets/fonts/" . rawurlencode($n . '.' . $ext) . "') format('{$fmt}')}\n";
                continue 3;
            }
        }
    }
    return $memo = $out;
}

function maDebit($userId, $amount, $ref = '') {
    $amount = round((float)$amount, 2);
    if ($amount <= 0) return false;

    return (bool)mutateUser($userId, function (&$user) use ($amount, $userId, $ref) {
        if ($user === null) return false;
        $bal = round((float)($user['balance'] ?? 0), 2);
        if ($bal + 0.001 < $amount) return false;
        $old = (float)($user['balance'] ?? 0);
        $user['balance'] = round($bal - $amount, 2);
        walletTx($userId, $old, $user['balance'], 'purchase', $ref);
        return true;
    });
}

function maOpenKb($page = '', $label = null) {
    $b = maWebAppBtn($label ?? UT('open_app'), $page, 'link');
    if (!$b) return null;
    if ($label === null) {
        if ($st = UC('open_app')) $b['style'] = $st;
        $ic = (string)UI('open_app');
        if ($ic !== '') $b['icon_custom_emoji_id'] = $ic;
    }
    return inlineKb([[$b]]);
}

function maRefund($userId, $amount, $note = '', $ref = '') {
    $amount = round((float)$amount, 2);
    if ($amount <= 0) return;
    addBalance($userId, $amount, 'refund', $ref, $ref !== '' ? 'refund:' . $ref : null);
    $txt = "💰 <b>مبلغ به کیف پول شما برگشت</b>\n\n" .
           '➕ ' . fmtNum($amount) . " تومان\n" .
           ($note !== '' ? '📝 ' . h($note) : '');
    maNoteAdd($userId, $txt);
}

function maRefundOrder($oid, $note) {
    $did = false;
    MaOrder::set($oid, function (&$x) use (&$did) {
        if (!empty($x['refunded']) || ($x['status'] ?? '') === MaOrder::DONE) return false;
        $x['refunded']   = true;
        $x['status']     = MaOrder::REJECT;
        $x['decided_at'] = nowStr();
        $did = true;
        return true;
    });
    if (!$did) return false;

    $o = MaOrder::get($oid);
    if (!$o) return true;
    $uid = (int)($o['user_id'] ?? 0);
    if ($uid > 0 && (float)($o['total'] ?? 0) > 0) maRefund($uid, (float)$o['total'], $note, (string)$oid);
    elseif ($uid > 0) maNoteAdd($uid, "↩️ <b>سفارش بسته شد</b>\n\n📝 " . h($note), $oid);
    if (trim((string)($o['coupon'] ?? '')) !== '' && function_exists('cpGiveBack'))
        cpGiveBack($uid, (string)$o['coupon'], $oid);
    if ((float)($o['gift'] ?? 0) > 0 && function_exists('dmGiftRestore'))
        dmGiftRestore($uid, (float)$o['gift']);
    return true;
}

function maOrderFail($oid, $err) {
    MaOrder::set($oid, function (&$x) use ($err) { $x['last_error'] = (string)$err; });
    maRefundOrder($oid, 'شماره در دسترس نبود');
    if ($err !== '' && function_exists('adminAlertOnce'))
        adminAlertOnce('numfail_' . substr(md5($err), 0, 10),
            "☎️ <b>گرفتنِ شماره ناموفق بود — پول کاربر برگشت</b>\n\n<code>" . h(mb_substr($err, 0, 300)) . "</code>\n\n" .
            "اگر تکرار شد، موجودی و اتصالِ فروشنده را در /panel ← ☎️ شماره مجازی بررسی کنید.", 900);
}

function maOrderDelivered($oid, $first) {
    $o   = MaOrder::get($oid);
    $act = function_exists('numGet') ? numGet($oid) : null;
    if (!$o || !$act) return;
    $uid = (int)$o['user_id'];

    $txt = ($first ? "✅ <b>کد شما رسید</b>\n\n" : "🔁 <b>کد تازه رسید</b>\n\n") .
           '📦 ' . h((string)($o['item_name'] ?? '')) . "\n" .
           '☎️ <code>' . h((string)($act['phone'] ?? '')) . "</code>\n" .
           '🔑 <code>' . h((string)($act['code'] ?? '')) . "</code>\n" .
           '🧾 <code>' . h((string)$oid) . '</code>';
    maNoteAdd($uid, $txt, $oid);
    if (!$first) return;

    $kb = maOpenKb('orders');
    $rows = $kb ? $kb['inline_keyboard'] : [];
    if (empty($o['review']) && ($rate = maRateRow($uid, (string)$oid))) $rows[] = $rate;
    MaOrder::set($oid, function (&$x) use ($txt) { $x['done_msg'] = $txt; });
    sendMsg(BOT_TOKEN, $uid, $txt, $rows ? inlineKb($rows) : null);

    $paid = (float)($o['total'] ?? 0);
    if ($paid > 0) payReferralCommission($uid, $paid);
    mutateUser($uid, function (&$user) {
        if ($user !== null) $user['approved_orders'] = (int)($user['approved_orders'] ?? 0) + 1;
    });
    chBuy($uid, (string)($o['username'] ?? ''),
          trim((string)($o['item_emoji'] ?? '') . ' ' . (string)($o['item_name'] ?? '')), $paid, (string)$oid,
          (string)($act['phone'] ?? ''));
}

function maBuy($uid, $uname, $itemId, $seen = 0.0, $gift = 0.0) {
    if (empty(maCfg()['on']) || !function_exists('numReady') || !numReady())
        return [false, 'closed', 'فروش شماره موقتا بسته است — کمی بعد دوباره امتحان کنید.', []];

    $item = maFindItem($itemId);
    $cat  = $item ? maFindCat((string)($item['cat'] ?? '')) : null;
    if (!$item || empty($item['on']) || !$cat || empty($cat['on']))
        return [false, 'bad_item', 'این شماره دیگر موجود نیست.', []];

    $price = maItemPrice($item);
    if ($price <= 0) return [false, 'bad_price', 'قیمت این شماره هنوز تنظیم نشده است.', []];
    if ($seen > 0 && abs($seen - $price) > max(1.0, $price * 0.005))
        return [false, 'price_changed', 'قیمت این شماره همین الان به‌روز شد — دوباره نگاه کنید.', ['price' => $price]];

    if (numActiveCount($uid) >= MA_ACTIVE_MAX)
        return [false, 'too_many', 'الان ' . MA_ACTIVE_MAX . ' شماره‌ی باز دارید. اول کدِ آن‌ها را بگیرید یا لغوشان کنید.', []];

    $total = $gift > 0 ? 0.0 : $price;
    $discount = 0.0; $code = '';
    if ($gift <= 0 && function_exists('cpActiveFor')) {
        [$code, $discount] = cpActiveFor($uid, $total);
        $total = maMoney(max(0, $total - $discount));
    }

    if (maDuplicateOrder($uid, 'num', (string)$itemId, 1, $code, 15))
        return [false, 'duplicate', 'همین شماره چند لحظه پیش ثبت شد — در «شماره‌های من» ببینید.', []];

    $bal = (float)(getUser($uid)['balance'] ?? 0);
    if ($total > 0 && $bal + 0.001 < $total)
        return [false, 'no_balance', 'موجودی کافی نیست. ' . fmtNum(maMoney($total - $bal)) . ' تومان کم دارید.',
                ['balance' => $bal, 'need' => maMoney($total - $bal), 'total' => $total]];

    $oid = MaOrder::create($uid, $uname, $item, $total,
        ['coupon' => $code, 'discount' => $discount, 'gift' => (float)$gift]);

    if ($total > 0 && !maDebit($uid, $total, (string)$oid)) {
        MaOrder::remove($oid);
        $bal = (float)(getUser($uid)['balance'] ?? 0);
        return [false, 'no_balance', 'موجودی کافی نیست.', ['balance' => $bal, 'need' => maMoney($total - $bal), 'total' => $total]];
    }

    if ($code !== '' && $discount > 0 && !cpActiveUse($uid, $code, $oid, $discount)) {
        if ($total > 0) addBalance($uid, $total, 'rollback', (string)$oid, 'rollback:' . $oid);
        MaOrder::remove($oid);
        return [false, 'bad_coupon', 'کدِ تخفیف مصرف شده است', ['coupon' => null]];
    }

    MaOrder::set($oid, function (&$x) use ($gift) {
        $x['status']     = MaOrder::PAID;
        $x['pay']        = $gift > 0 ? 'diamond' : 'wallet';
        $x['decided_at'] = nowStr();
    });

    [$ok, $err] = numBuy(MaOrder::get($oid));
    if (!$ok) {
        maOrderFail($oid, $err);
        return [false, 'unavailable',
                'این شماره الان در دسترس نیست' . ($total > 0 ? ' و مبلغ به کیف پولتان برگشت' : '') .
                ' — کشور یا اپراتور دیگری امتحان کنید.',
                ['balance' => (float)(getUser($uid)['balance'] ?? 0)]];
    }

    $act = numGet($oid);
    if ($act) maNoteAdd($uid,
        "☎️ <b>شماره‌ی شما آماده است</b>\n\n" .
        '📱 <code>' . h((string)($act['phone'] ?? '')) . "</code>\n\n" .
        'این شماره را در تلگرام وارد کنید؛ کد همین‌جا و در ربات می‌آید.', $oid);

    return [true, '', '', ['order' => $oid, 'row' => maNumRow(MaOrder::get($oid)),
                           'balance' => (float)(getUser($uid)['balance'] ?? 0),
                           'coupon' => function_exists('cpActivePublic') ? cpActivePublic($uid) : null]];
}

function maGrantNumber($uid, $uname, $itemId, $cost) {
    $cost = max(0.01, (float)$cost);
    [$ok, $err, $msg, $data] = maBuy($uid, $uname, $itemId, 0.0, $cost);
    if (!$ok) {
        if ($err !== 'unavailable' && function_exists('dmGiftRestore')) dmGiftRestore($uid, $cost);
        return [false, $msg];
    }
    $oid = (string)$data['order'];
    $row = (array)($data['row'] ?? []);
    sendMsg(BOT_TOKEN, $uid,
        "🎁 <b>شماره‌ی هدیه‌ی شما آماده است</b>\n\n" .
        '📦 ' . h((string)($row['name'] ?? '')) . "\n" .
        '☎️ <code>' . h((string)($row['phone'] ?? '')) . "</code>\n\n" .
        'این شماره را در تلگرام وارد کنید؛ کد همین‌جا و در مینی‌اپ می‌آید.',
        maOpenKb('live'));
    return [true, $oid];
}

function maFeed($limit = 12) {
    $list = maCacheGet('feed', 20);
    if (!is_array($list)) {
        $list = [];
        $db = maOrdersDb();
        if ($db) {
            $st = $db->prepare("SELECT data FROM orders WHERE app = 'num' AND status = 'done' ORDER BY created_at DESC LIMIT :n");
            $st->bindValue(':n', max(1, min(30, (int)$limit)), SQLITE3_INTEGER);
            $res = $st->execute();
            while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                $o = json_decode((string)$row['data'], true);
                if (!is_array($o)) continue;
                $c = maFindCat((string)(maFindItem((string)($o['item_id'] ?? ''))['cat'] ?? ''));
                $list[] = [
                    'e'  => (string)($o['item_emoji'] ?? '☎️'),
                    'c'  => (string)($c['name'] ?? explode(' · ', (string)($o['item_name'] ?? ''))[0]),
                    'at' => strtotime((string)(($o['delivered_at'] ?? '') ?: ($o['created_at'] ?? ''))) ?: time(),
                ];
            }
        }
        maCachePut('feed', $list);
    }
    $now = time();
    return array_map(fn($x) => ['e' => $x['e'], 'c' => $x['c'], 't' => max(0, $now - (int)$x['at'])], $list);
}

function maRevDb() {
    static $ready = false;
    $db = maOrdersDb();
    if (!$db) return null;
    if (!$ready) {
        $db->exec('CREATE TABLE IF NOT EXISTS reviews (
            oid TEXT PRIMARY KEY, uid INTEGER NOT NULL, rating INTEGER NOT NULL, txt TEXT NOT NULL DEFAULT "",
            name TEXT NOT NULL DEFAULT "", e TEXT NOT NULL DEFAULT "", at INTEGER NOT NULL, hidden INTEGER NOT NULL DEFAULT 0)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_reviews_at ON reviews(hidden, at)');
        $cols = [];
        $res = $db->query('PRAGMA table_info(reviews)');
        while ($res && ($x = $res->fetchArray(SQLITE3_ASSOC))) $cols[(string)$x['name']] = true;
        if (!isset($cols['pic'])) $db->exec('ALTER TABLE reviews ADD COLUMN pic INTEGER NOT NULL DEFAULT 0');
        $ready = true;
    }
    return $db;
}

function maReviewAdd($uid, $oid, $rating, $txt, $fname, $pic = true) {
    $o = MaOrder::get((string)$oid);
    if (!$o || (int)($o['user_id'] ?? 0) !== (int)$uid || ($o['status'] ?? '') !== MaOrder::DONE)
        return [false, 'فقط برای شماره‌ی تحویل‌شده می‌شود نظر داد.'];
    $db = maRevDb();
    if (!$db) return [false, 'الان ذخیره نمی‌شود — کمی بعد امتحان کنید.'];
    $rating = max(1, min(5, (int)$rating));
    $txt  = maRevClean($txt);
    $hide = maRevSpam($txt) ? 1 : 0;
    $name = $pic ? maRevName($fname) : '';
    $st = $db->prepare('INSERT OR REPLACE INTO reviews (oid, uid, rating, txt, name, e, at, hidden, pic) VALUES (:o, :u, :r, :t, :n, :e, :a, :h, :p)');
    $st->bindValue(':o', (string)$oid, SQLITE3_TEXT);
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':r', $rating, SQLITE3_INTEGER);
    $st->bindValue(':t', $txt, SQLITE3_TEXT);
    $st->bindValue(':n', $name, SQLITE3_TEXT);
    $st->bindValue(':e', (string)($o['item_emoji'] ?? ''), SQLITE3_TEXT);
    $st->bindValue(':a', time(), SQLITE3_INTEGER);
    $st->bindValue(':h', $hide, SQLITE3_INTEGER);
    $st->bindValue(':p', $name !== '' ? 1 : 0, SQLITE3_INTEGER);
    $st->execute();
    MaOrder::set((string)$oid, function (&$x) use ($rating) { $x['review'] = $rating; return true; });
    maCachePut('reviews', null);
    return [true, ''];
}

function maRevClean($txt) {
    return mb_substr(trim((string)preg_replace('/\s+/u', ' ', strip_tags((string)$txt))), 0, 160);
}

function maRevSpam($txt) {
    return (bool)preg_match('~(https?://|t\.me/|telegram\.me|www\.|@[A-Za-z0-9_]{4,})~i', (string)$txt);
}

function maRevName($fname) {
    $n = trim((string)preg_replace('/[^\p{L}\p{M}\s]+/u', ' ', strip_tags((string)$fname)));
    $n = (string)(preg_split('/\s+/u', $n)[0] ?? '');
    return mb_substr($n, 0, 12);
}

function maRevKey($oid) {
    return substr(hash_hmac('sha256', 'rv|' . (string)$oid, BOT_TOKEN), 0, 16);
}

function maReviewsData() {
    $c = maCacheGet('reviews', 120);
    if (is_array($c)) return $c;
    $c = ['n' => 0, 'avg' => 0, 'list' => []];
    if ($db = maRevDb()) {
        $r = $db->query('SELECT COUNT(*) n, AVG(rating) a FROM reviews WHERE hidden = 0')->fetchArray(SQLITE3_ASSOC);
        $c['n']   = (int)($r['n'] ?? 0);
        $c['avg'] = round((float)($r['a'] ?? 0), 1);
        $res = $db->query("SELECT oid, uid, rating, txt, name, e, at, pic FROM reviews WHERE hidden = 0 AND txt <> '' ORDER BY at DESC LIMIT 20");
        while ($res && ($x = $res->fetchArray(SQLITE3_ASSOC)))
            $c['list'][] = ['r' => (int)$x['rating'], 'x' => (string)$x['txt'], 'n' => (string)$x['name'], 'e' => (string)$x['e'],
                            'at' => (int)$x['at'], 'u' => (int)$x['uid'], 'k' => maRevKey($x['oid']),
                            'p' => (int)$x['pic'] === 1 && (string)$x['name'] !== '' ? 1 : 0];
    }
    maCachePut('reviews', $c);
    return $c;
}

function maReviews() {
    $c    = maReviewsData();
    $now  = time();
    $base = maBaseUrl();
    $c['list'] = array_map(fn($x) => [
        'r' => $x['r'], 'x' => $x['x'], 'n' => $x['n'] !== '' ? $x['n'] : 'خریدار', 'e' => $x['e'],
        't' => max(0, $now - $x['at']),
        'p' => !empty($x['p']) && $base !== '' ? $base . (str_contains($base, '?') ? '&' : '?') . 'marp=' . $x['k'] : '',
    ], $c['list']);
    return $c;
}

function maServeReviewPhoto() {
    $k   = (string)($_GET['marp'] ?? '');
    $uid = 0;
    if (preg_match('/^[a-f0-9]{16}$/', $k))
        foreach (maReviewsData()['list'] as $x)
            if (!empty($x['p']) && hash_equals((string)$x['k'], $k)) { $uid = (int)$x['u']; break; }
    if ($uid <= 0) { http_response_code(404); exit; }
    $p = function_exists('bcAvatar') ? bcAvatar($uid) : null;
    if (!$p || !is_file($p) || filesize($p) < 64) { http_response_code(204); exit; }
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($p));
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($p);
    exit;
}

function maReviewPatch($uid, $oid, array $set) {
    $db = maRevDb();
    if (!$db || !$set) return false;
    $sql = [];
    foreach (array_keys($set) as $f) $sql[] = $f . ' = :' . $f;
    $st = $db->prepare('UPDATE reviews SET ' . implode(', ', $sql) . ' WHERE oid = :o AND uid = :u');
    foreach ($set as $f => $v) $st->bindValue(':' . $f, $v, is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    $st->bindValue(':o', (string)$oid, SQLITE3_TEXT);
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->execute();
    maCachePut('reviews', null);
    return $db->changes() > 0;
}

function maRateStars($n) {
    return str_repeat('⭐', max(1, min(5, (int)$n)));
}

function maRateRow($uid, $oid) {
    $last = (int)(getUser($uid)['rv_ask'] ?? 0);
    if (time() - $last < 172800) return null;
    mutateUser($uid, function (&$u) { if ($u !== null) $u['rv_ask'] = time(); });
    $row = [];
    foreach ([5 => '۵', 4 => '۴', 3 => '۳', 2 => '۲', 1 => '۱'] as $n => $fa) $row[] = btnCb('⭐ ' . $fa, 'marv_' . $oid . '_' . $n, 'info');
    return $row;
}

function maRateHead($oid) {
    $o = MaOrder::get($oid);
    $h = trim((string)($o['done_msg'] ?? ''));
    return $h !== '' ? $h . "\n\n" : '';
}

function maRateScreen($chatId, $msgId, $oid, $n, $name) {
    $t = maRateHead($oid) . "<b>امتیاز " . maRateStars($n) . "</b>\n" .
         ($name !== '' ? '👤 ' . h($name) : '🙈 بی‌نام') . "\n✍️ نظر (اختیاری)";
    $rows = [];
    if ($name !== '') $rows[] = [btnCb('🙈 بی‌نام', 'marv_' . $oid . '_anon', 'info')];
    $rows[] = [btnCb('✅ تمام', 'marv_' . $oid . '_ok', 'confirm')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function maRateCallback($data, $uid, $chatId, $msgId, $cbId) {
    if (!preg_match('/^marv_([A-Za-z0-9_]{4,40})_([1-5]|ok|anon)$/', $data, $m)) { answerCb(BOT_TOKEN, $cbId); return true; }
    [, $oid, $v] = $m;
    $o = MaOrder::get($oid);
    if (!$o || (int)($o['user_id'] ?? 0) !== (int)$uid) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return true; }
    if ($v === 'ok') {
        clearState($uid);
        answerCb(BOT_TOKEN, $cbId, '🙏');
        editMsg(BOT_TOKEN, $chatId, $msgId, maRateHead($oid) . "<b>امتیاز " . maRateStars((int)($o['review'] ?? 5)) . "</b>", maOpenKb('orders'));
        return true;
    }
    if ($v === 'anon') {
        maReviewPatch($uid, $oid, ['name' => '', 'pic' => 0]);
        answerCb(BOT_TOKEN, $cbId, '🙈 بی‌نام شد');
        maRateScreen($chatId, $msgId, $oid, (int)($o['review'] ?? 5), '');
        return true;
    }
    if (!empty($o['review'])) { answerCb(BOT_TOKEN, $cbId, 'برای این خرید قبلا امتیاز داده‌ای 🙏', true); return true; }
    $name = maRevName((string)(getUser($uid)['first_name'] ?? ''));
    [$ok, $err] = maReviewAdd($uid, $oid, (int)$v, '', $name);
    if (!$ok) { answerCb(BOT_TOKEN, $cbId, $err, true); return true; }
    setState($uid, 'marv', ['oid' => $oid]);
    answerCb(BOT_TOKEN, $cbId, '⭐ ثبت شد');
    maRateScreen($chatId, $msgId, $oid, (int)$v, $name);
    return true;
}

function maRateText($sd, $msg, $uid, $chatId) {
    $oid = (string)($sd['oid'] ?? '');
    $raw = trim((string)($msg['text'] ?? ''));
    if ($raw !== '' && $raw[0] === '/') { clearState($uid); return false; }
    $txt = maRevClean($raw);
    if ($txt === '') { sendMsg(BOT_TOKEN, $chatId, "✍️ متن"); return true; }
    clearState($uid);
    $ok = maReviewPatch($uid, $oid, ['txt' => $txt, 'hidden' => maRevSpam($txt) ? 1 : 0, 'at' => time()]);
    sendMsg(BOT_TOKEN, $chatId, $ok ? "🙏 <b>نظرت ثبت شد</b>" : "⚠️ نظر ذخیره نشد.", maOpenKb('orders'));
    return true;
}

function maReviewHide($oid) {
    $db = maRevDb();
    if (!$db) return false;
    $st = $db->prepare('UPDATE reviews SET hidden = 1 WHERE oid = :o');
    $st->bindValue(':o', (string)$oid, SQLITE3_TEXT);
    $st->execute();
    maCachePut('reviews', null);
    return $db->changes() > 0;
}

function maReviewsAdmin($limit = 8) {
    $out = [];
    if ($db = maRevDb()) {
        $st = $db->prepare('SELECT oid, uid, rating, txt, name, at FROM reviews WHERE hidden = 0 ORDER BY at DESC LIMIT :l');
        $st->bindValue(':l', max(1, (int)$limit), SQLITE3_INTEGER);
        $res = $st->execute();
        while ($res && ($x = $res->fetchArray(SQLITE3_ASSOC))) $out[] = $x;
    }
    return $out;
}

function maTrust() {
    $c = maCacheGet('trust', 600);
    if (is_array($c)) return $c;
    $c = ['done' => MaOrder::countBy(MaOrder::DONE)];
    maCachePut('trust', $c);
    return $c;
}

function maBoot() {
    $c   = maCfg();
    $cat = maCatalogPublic();
    $ref = cfg()['referral'] ?? [];
    return [
        'title'   => (string)$c['title'],
        'tagline' => (string)$c['tagline'],
        'note'    => (string)$c['note'],
        'cats'    => $cat['cats'],
        'items'   => $cat['items'],
        'api'     => maApiUrl(),
        'sup'     => maSupportLink(),
        'supform' => maSupOn() ? 1 : 0,
        'ref'     => ['on' => !empty($ref['on']) ? 1 : 0, 'pct' => (float)($ref['percent'] ?? 0)],
        'bot'     => (string)botUsername(),
        'wait'    => function_exists('numVal') ? (int)numVal('wait', 900) : 900,
        'amax'    => MA_ACTIVE_MAX,
        'spl'     => maSplashSec(),
    ];
}

if (!defined('MA_NOTE_KEEP')) define('MA_NOTE_KEEP', 40);
if (!defined('MA_NOTE_TTL'))  define('MA_NOTE_TTL', 2592000);

function maNoteKey($uid) { return 'num_' . (int)$uid; }

function maNoteFile($key) { return 'ma_notes_' . maShardOf($key); }

function maNoteAdd($uid, $html, $order = '') {
    $uid = (int)$uid;
    if ($uid <= 0) return '';
    [$emoji, $title, $body, $copy] = maNoteParse($html);
    if ($title === '' && $body === '') return '';

    $id  = 'n' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(2));
    $key = maNoteKey($uid);
    mutate(maNoteFile($key), function (&$d) use ($key, $id, $emoji, $title, $body, $copy, $order) {
        if (!is_array($d[$key] ?? null)) $d[$key] = ['seen_id' => '', 'list' => []];

        $last = $d[$key]['list'][0] ?? null;
        if (is_array($last) && (string)($last['h'] ?? '') === $title
            && (string)($last['b'] ?? '') === $body
            && time() - (int)($last['t'] ?? 0) < 60) return;

        array_unshift($d[$key]['list'], [
            'id' => $id, 't' => time(), 'e' => $emoji, 'h' => $title,
            'b' => $body, 'c' => $copy, 'o' => (string)$order,
        ]);
        $d[$key]['list'] = array_slice($d[$key]['list'], 0, MA_NOTE_KEEP);

        if (count($d) > 400) {
            $cut = time() - MA_NOTE_TTL;
            foreach ($d as $k => $v)
                if ((int)($v['list'][0]['t'] ?? 0) < $cut) unset($d[$k]);
        }
    });
    return $id;
}

function maNotes($uid, $limit = 40) {
    $key = maNoteKey($uid);
    $box = load(maNoteFile($key))[$key] ?? null;
    if (!is_array($box)) return [[], 0];
    $all  = (array)($box['list'] ?? []);
    $mark = (string)($box['seen_id'] ?? '');

    $n = 0;
    foreach ($all as $x) {
        if ((string)($x['id'] ?? '') === $mark) break;
        $n++;
    }
    return [array_slice($all, 0, max(1, (int)$limit)), min($n, count($all))];
}

function maNotesSeen($uid) {
    $key = maNoteKey($uid);
    mutate(maNoteFile($key), function (&$d) use ($key) {
        if (!is_array($d[$key] ?? null) || empty($d[$key]['list'])) return false;
        $d[$key]['seen_id'] = (string)($d[$key]['list'][0]['id'] ?? '');
        return true;
    });
}

function maNumRow($o, $now = null) {
    $now = $now ?? time();
    $act = function_exists('numGet') ? numGet((string)$o['id']) : null;
    $row = [
        'id'    => (string)$o['id'],
        'name'  => (string)($o['item_name'] ?? ''),
        'e'     => (string)($o['item_emoji'] ?? '☎️'),
        'total' => (float)($o['total'] ?? 0),
        'at'    => strtotime((string)($o['created_at'] ?? '')) ?: $now,
        'st'    => (string)($o['status'] ?? ''),
        'ref'   => !empty($o['refunded']) ? 1 : 0,
        'rv'    => (int)($o['review'] ?? 0),
    ];
    if ($act) {
        $wait = numWaitFor($act);
        $left = max(0, $wait - ($now - (int)($act['created'] ?? $now)));
        $row['phone']  = (string)($act['phone'] ?? '');
        $row['code']   = (string)($act['code'] ?? '');
        $row['nst']    = (string)($act['status'] ?? '');
        $row['left']   = ($row['nst'] === 'waiting' || numCanRepeat($act, $left)) ? $left : 0;
        $row['wait']   = $wait;
        $row['repeat'] = numCanRepeat($act, $left) ? 1 : 0;
    }
    return $row;
}

function maOrdersFor($uid, $limit = 30) {
    $now = time();
    return array_map(fn($o) => maNumRow($o, $now), MaOrder::forUser($uid, $limit));
}

function maLiveFor($uid) {
    $out = [];
    foreach (function_exists('numOpenFor') ? numOpenFor($uid) : [] as $act) {
        $oid = (string)$act['order'];
        if (($act['status'] ?? '') === 'waiting') numState($oid);
        $o = MaOrder::get($oid);
        if ($o) $out[] = maNumRow($o);
    }
    return $out;
}

function maOwnOrder($uid, $oid) {
    $oid = trim((string)$oid);
    if ($oid === '' || !preg_match('/^ma_[a-z0-9]{6,40}$/', $oid)) return null;
    $o = MaOrder::get($oid);
    return ($o && (int)($o['user_id'] ?? 0) === (int)$uid) ? $o : null;
}

function maApi() {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
        maApiOut(['ok' => false, 'error' => 'bad_method'], 405);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768)
        maApiOut(['ok' => false, 'error' => 'too_large'], 413);

    $ip = maClientIp();
    if (!maRateOk('ip', $ip, (int)(maCfg()['rate_ip'] ?? 3000), 60))
        maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'درخواست‌ها زیاد است، کمی صبر کنید.'], 429);

    $raw  = file_get_contents('php://input', false, null, 0, 32768);
    $body = json_decode((string)$raw, true);
    if (!is_array($body)) $body = $_POST;

    $action = (string)($body['action'] ?? '');
    monForApi($action);

    $initData = (string)($body['initData'] ?? '');
    $reason = '';
    $user = maVerifyInitData($initData, $reason);
    if (!$user) {
        maApiOut(['ok' => false, 'error' => 'unauthorized', 'reason' => $reason,
                  'message' => maAuthReasonText($reason)], 401);
    }

    $uid = (int)$user['id'];
    if (!maRateOk('u', $uid, (int)(maCfg()['rate_user'] ?? 40), 60))
        maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'درخواست‌ها زیاد است، کمی صبر کنید.'], 429);

    $uname = (string)($user['username'] ?? '');
    $fname = (string)($user['first_name'] ?? '');
    touchUser($uid, $uname, $fname);

    $u = getUser($uid);
    if ($u && !empty($u['banned'])) maApiOut(['ok' => false, 'error' => 'banned', 'message' => 'دسترسی شما مسدود است.'], 403);
    $bal = fn() => (float)(getUser($uid)['balance'] ?? 0);

    if ($action === 'me') {
        [, $unread] = maNotes($uid, 1);
        $uu = getUser($uid) ?: [];
        maApiOut([
            'ok'      => true,
            'balance' => $bal(),
            'avatar'  => maAvatarUrl($uid),
            'live'    => maLiveFor($uid),
            'done'    => MaOrder::doneCount($uid),
            'notes'   => $unread,
            'sup'     => maSupState($uid),
            'coupon'  => function_exists('cpActivePublic') ? cpActivePublic($uid) : null,
            'ref'     => [
                'n'       => countReferrals($uid),
                'earned'  => (float)($uu['ref_earned'] ?? 0),
                'pending' => (float)($uu['ref_pending'] ?? 0),
            ],
        ]);
    }

    if ($action === 'live') {
        maApiOut(['ok' => true, 'live' => maLiveFor($uid), 'balance' => $bal()]);
    }

    if (str_starts_with($action, 'pay_') && function_exists('payApi')) {
        if (function_exists('maNoNet')) maNoNet(false);
        payApi($action, $uid, $uname, $body);
    }

    if ($action === 'feed') {
        maApiOut(['ok' => true, 'list' => maFeed(12), 'rev' => maReviews(), 'trust' => maTrust()]);
    }
    if ($action === 'review') {
        if (!maRateOk('rev', $uid, 6, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی صبر کن.'], 429);
        $roid = (string)($body['order'] ?? '');
        [$rok, $rmsg] = maReviewAdd($uid, $roid, (int)($body['r'] ?? 0), (string)($body['x'] ?? ''), $fname, empty($body['a']));
        if (!$rok) maApiOut(['ok' => false, 'error' => 'bad_review', 'message' => $rmsg], 400);
        maApiOut(['ok' => true, 'row' => maNumRow(MaOrder::get($roid))]);
    }

    if ($action === 'buy') {
        if (!maRateOk('ord', $uid, 6, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited',
                      'message' => 'خریدهای پشت‌سرهم زیاد شد. یک دقیقه صبر کنید.'], 429);
        if (!maNonceOk($initData))
            maApiOut(['ok' => false, 'error' => 'replay',
                      'message' => 'سقف خرید این نشست پر شد. مینی‌اپ را ببندید و دوباره باز کنید.'], 409);
        if (!isAdmin($uid) && function_exists('masterJoinMissing') && ($miss = masterJoinMissing($uid))) {
            $names = [];
            foreach ($miss as $m) $names[] = (string)($m['title'] ?? '');
            maApiOut(['ok' => false, 'error' => 'join_required',
                      'message' => "برای خرید، اول در کانال‌های زیر عضو شوید:\n" . implode('، ', $names)], 403);
        }
        [$ok, $err, $msg, $data] = maBuy($uid, $uname, (string)($body['item'] ?? ''), maNum($body['seen'] ?? 0));
        if (!$ok) {
            $code = ['no_balance' => 402, 'price_changed' => 409, 'duplicate' => 409, 'too_many' => 409,
                     'closed' => 503, 'unavailable' => 409][$err] ?? 400;
            maApiOut(['ok' => false, 'error' => $err, 'message' => $msg] + $data, $code);
        }
        maApiOut(['ok' => true] + $data);
    }

    if ($action === 'num') {
        $o = maOwnOrder($uid, $body['order'] ?? '');
        if (!$o) maApiOut(['ok' => false, 'error' => 'not_found', 'message' => 'این سفارش پیدا نشد.'], 404);
        $force = !empty($body['check']);
        if ($force && !maRateOk('nchk', $uid, 10, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی صبر کنید، خودکار هم بررسی می‌شود.'], 429);
        if (function_exists('numGet') && numGet((string)$o['id'])) numState((string)$o['id'], $force);
        maApiOut(['ok' => true, 'row' => maNumRow(MaOrder::get((string)$o['id'])), 'balance' => $bal()]);
    }

    if ($action === 'num_cancel') {
        $o = maOwnOrder($uid, $body['order'] ?? '');
        $act = $o ? numGet((string)$o['id']) : null;
        if (!$act) maApiOut(['ok' => false, 'error' => 'not_found', 'message' => 'این شماره پیدا نشد.'], 404);
        if (!maRateOk('ncan', $uid, 6, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی صبر کنید.'], 429);
        numState((string)$o['id'], true);
        $act = numGet((string)$o['id']);
        if (($act['status'] ?? '') !== 'waiting')
            maApiOut(['ok' => false, 'error' => 'closed', 'message' => ($act['status'] ?? '') === 'done'
                ? 'کد همین الان رسید — دیگر لغو نمی‌شود.' : 'این شماره دیگر باز نیست.',
                'row' => maNumRow(MaOrder::get((string)$o['id']))], 409);
        [$cok, $cerr] = numFinish((string)$o['id'], 'cancel');
        if (!$cok) maApiOut(['ok' => false, 'error' => 'closed', 'message' => $cerr], 409);
        maApiOut(['ok' => true, 'row' => maNumRow(MaOrder::get((string)$o['id'])), 'balance' => $bal()]);
    }

    if ($action === 'num_repeat') {
        $o = maOwnOrder($uid, $body['order'] ?? '');
        if (!$o || !numGet((string)$o['id'])) maApiOut(['ok' => false, 'error' => 'not_found', 'message' => 'این شماره پیدا نشد.'], 404);
        if (!maRateOk('nrep', $uid, 6, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی صبر کنید.'], 429);
        [$rok, $rerr] = numRepeat((string)$o['id']);
        if (!$rok) maApiOut(['ok' => false, 'error' => 'repeat_failed', 'message' => $rerr,
                             'row' => maNumRow(MaOrder::get((string)$o['id']))], 409);
        maApiOut(['ok' => true, 'row' => maNumRow(MaOrder::get((string)$o['id']))]);
    }

    if ($action === 'orders') {
        maApiOut(['ok' => true, 'list' => maOrdersFor($uid, 30), 'balance' => $bal()]);
    }

    if ($action === 'topup_bot') {
        if (!maRateOk('tubot', $uid, 4, 120))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'درخواست‌ها زیاد است'], 429);
        if (!function_exists('startTopup')) maApiOut(['ok' => false, 'error' => 'off'], 503);
        maApiOut(['ok' => true], 200, function () use ($uid) {
            $old = (int)slotGet($uid, 'wallet');
            if ($old) delMsg(BOT_TOKEN, $uid, $old);
            slotClear($uid, 'wallet');
            startTopup($uid, $uid);
        });
    }

    if ($action === 'notes') {
        [$list, $n] = maNotes($uid, 40);
        maApiOut(['ok' => true, 'list' => $list, 'n' => $n], 200,
            $n > 0 ? function () use ($uid) { maNotesSeen($uid); } : null);
    }

    if ($action === 'support_send') {
        if (!maRateOk('sup', $uid, 4, 600))
            maApiOut(['ok' => false, 'error' => 'rate_limited',
                      'message' => 'در ده دقیقه‌ی گذشته چند تیکت فرستاده‌اید؛ کمی صبر کنید.'], 429);
        [$sok, $serr] = maSupSend($uid, $uname,
            (string)($body['name'] ?? ''), (string)($body['order'] ?? ''), (string)($body['text'] ?? ''));
        if (!$sok) maApiOut(['ok' => false, 'error' => 'sup_failed', 'message' => $serr], 400);
        $ord = trim(mb_substr((string)($body['order'] ?? ''), 0, 40));
        maSupLog($uid, 'u', ($ord !== '' ? '🧾 ' . $ord . "\n" : '') . trim(mb_substr((string)($body['text'] ?? ''), 0, 900)));
        maApiOut(['ok' => true, 'msgs' => maSupThread($uid, 0)]);
    }

    if ($action === 'sup_thread') {
        $after = max(0, (int)($body['after'] ?? 0));
        $msgs  = maSupThread($uid, $after, $after > 0 ? 60 : 100);
        $last  = 0;
        foreach ($msgs as $m) if (!$m['me'] && $m['id'] > $last) $last = $m['id'];
        if ($last) maSupSeen($uid, $last);
        maApiOut(['ok' => true, 'msgs' => $msgs]);
    }

    if ($action === 'sup_state') {
        maApiOut(['ok' => true] + maSupState($uid));
    }

    if ($action === 'sup_msg') {
        if (!maRateOk('supq', $uid, 4, 8) || !maRateOk('supm', $uid, 30, 600))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'درخواست‌ها زیاد است'], 429);
        $text = trim(mb_substr((string)($body['text'] ?? ''), 0, 900));
        if ($text === '') maApiOut(['ok' => false, 'error' => 'empty', 'message' => 'پیام خالی است.'], 400);
        [$sok, $serr] = maSupSend($uid, $uname, $fname, '', $text, true);
        if (!$sok) maApiOut(['ok' => false, 'error' => 'sup_failed', 'message' => $serr], 400);
        $id = maSupLog($uid, 'u', $text);
        maApiOut(['ok' => true, 'msg' => ['id' => $id, 'me' => true, 't' => $text, 'at' => time()]]);
    }

    if (str_starts_with($action, 'airdrop_') && function_exists('adOn') && !adOn())
        maApiOut(['ok' => false, 'error' => 'off', 'message' => 'ایردراپ فعلا بسته است.'], 503);
    if ($action === 'airdrop_state') {
        maApiOut(['ok' => true] + adState($uid, $fname, $uname));
    }
    if ($action === 'airdrop_tap') {
        if (!maRateOk('adtap', $uid, 40, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی آرام‌تر!'], 429);
        $tr = adTap($uid, (int)($body['n'] ?? 0));
        if (!$tr) maApiOut(['ok' => false, 'error' => 'tap_failed', 'message' => 'ثبت نشد'], 500);
        maApiOut(['ok' => true] + $tr);
    }
    if ($action === 'airdrop_leaderboard') {
        maApiOut(['ok' => true, 'list' => adLeaderboard(50), 'me' => $uid]);
    }
    if ($action === 'airdrop_referral') {
        maApiOut(['ok' => true] + adReferralInfo($uid));
    }
    if ($action === 'airdrop_mission_claim') {
        if (!maRateOk('adcl', $uid, 20, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی صبر کن.'], 429);
        [$mok, $mres] = adClaimMission($uid, (string)($body['id'] ?? ''));
        if (!$mok) maApiOut(['ok' => false, 'error' => 'not_ready', 'message' => (string)$mres], 400);
        maApiOut(['ok' => true, 'reward' => $mres, 'state' => adState($uid)]);
    }
    if ($action === 'airdrop_redeem') {
        if (!maRateOk('adrd', $uid, 10, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی صبر کن.'], 429);
        [$rok, $rres] = adRedeem($uid, maNum($body['amount'] ?? 0));
        if (!$rok) maApiOut(['ok' => false, 'error' => 'bad_amount', 'message' => (string)$rres], 400);
        maApiOut(['ok' => true, 'toman' => $rres, 'balance' => $bal(), 'state' => adState($uid)]);
    }
    if ($action === 'airdrop_boost') {
        if (!maRateOk('adbst', $uid, 10, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی صبر کن.'], 429);
        [$bok, $bres] = adBuyBoost($uid);
        if (!$bok) maApiOut(['ok' => false, 'error' => 'boost_failed', 'message' => (string)$bres], 400);
        maApiOut(['ok' => true, 'boost_n' => $bres, 'balance' => $bal(), 'state' => adState($uid)]);
    }
    if ($action === 'airdrop_history') {
        maApiOut(['ok' => true, 'list' => adRedeemLog($uid, 20)]);
    }
    if ($action === 'airdrop_tour_done') {
        maApiOut(['ok' => true], 200, function () use ($uid) { adTourSeen($uid); });
    }
    if ($action === 'airdrop_coupon') {
        if (!maRateOk('adcp', $uid, 10, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی صبر کن.'], 429);
        [$cok, $cres] = adRedeemCoupon($uid, maNum($body['amount'] ?? 0));
        if (!$cok) maApiOut(['ok' => false, 'error' => 'bad_amount', 'message' => (string)$cres], 400);
        maApiOut(['ok' => true] + (array)$cres + ['state' => adState($uid)]);
    }
    if ($action === 'airdrop_coupons') {
        maApiOut(['ok' => true, 'list' => adCouponLog($uid, 20)]);
    }

    if (str_starts_with($action, 'sv_') && function_exists('svApiAction'))
        svApiAction($action, $body, $uid, $uname, $initData);
    if (str_starts_with($action, 'wh_') && function_exists('whApiAction'))
        whApiAction($action, $body, $uid, $uname, $fname);

    maApiOut(['ok' => false, 'error' => 'unknown_action'], 400);
}

function maNoteParse($html) {
    $s = (string)$html;

    $copy = [];
    if (preg_match_all('#<code>(.*?)</code>#su', $s, $m))
        foreach ($m[1] as $c) {
            $c = trim(html_entity_decode(strip_tags($c), ENT_QUOTES, 'UTF-8'));
            if ($c !== '') $copy[] = $c;
        }

    $txt = preg_replace('#<br\s*/?>#i', "\n", $s);
    $txt = html_entity_decode(strip_tags((string)$txt), ENT_QUOTES, 'UTF-8');
    $txt = trim(preg_replace("/\n{3,}/", "\n\n", (string)$txt));

    $lines = preg_split("/\n/", $txt);
    $title = trim((string)array_shift($lines));
    $body  = trim(implode("\n", $lines));

    $emoji = '';
    if (preg_match('/^(\X)\s+(.*)$/u', $title, $m) &&
        preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]/u', $m[1])) {
        $emoji = $m[1];
        $title = trim($m[2]);
    }
    return [$emoji, mb_substr($title, 0, 90), mb_substr($body, 0, 600), array_slice($copy, 0, 3)];
}

function maSupChat() {
    $s = maCfg()['sup'] ?? [];
    if (!empty($s['on'])) {
        $chat = trim((string)($s['chat'] ?? ''));
        if ($chat !== '') return $chat;
    }
    $aid = defined('ADMIN_ID') ? (int)ADMIN_ID : 0;
    return $aid > 0 ? (string)$aid : '';
}

function maSupOn() {
    return maSupChat() !== '';
}

function maSupSend($uid, $uname, $name, $order, $text, $followup = false, $copy = null) {
    $s = maCfg()['sup'] ?? [];
    $chat = maSupChat();
    if ($chat === '') return [false, 'پشتیبانی فعلا از این راه در دسترس نیست.'];

    $name  = trim(mb_substr((string)$name, 0, 60));
    $order = trim(mb_substr((string)$order, 0, 40));
    $text  = trim(mb_substr((string)$text, 0, 900));
    if ($text === '' && !$copy) return [false, 'توضیحات خالی است'];

    $t  = $followup ? "↩️ <b>پیامِ تازه از کاربر</b>\n\n" : "☎️ <b>تیکتِ پشتیبانی</b>\n\n";
    if ($name !== '')  $t .= "👤 نام: " . emKeep(h($name)) . "\n";
    if ($order !== '') $t .= "🧾 کد سفارش: <code>" . h($order) . "</code>\n";
    $t .= "🆔 کاربر: <code>" . (int)$uid . "</code>"
        . ($uname !== '' ? ' · @' . h($uname) : '') . "\n";
    $t .= "🕘 " . h(nowStr());
    if ($text !== '') $t .= "\n\n<blockquote>" . emKeep(h($text)) . "</blockquote>";

    $extra = [];
    $th = (trim((string)($s['chat'] ?? '')) !== '' && !empty($s['on'])) ? (int)($s['thread'] ?? 0) : 0;
    if ($th > 0) $extra['message_thread_id'] = $th;
    $kb = inlineKb([[btnCb('💬 پاسخ به کاربر', 'reply_' . (int)$uid, 'admin')]]);

    $aid = defined('ADMIN_ID') ? (string)(int)ADMIN_ID : '';
    $r = ($chat !== $aid && ($dead = chatDown($chat))) ? $dead : sendMsg(BOT_TOKEN, $chat, $t, $kb, $extra);
    if (empty($r['ok']) && $th > 0 && !tgChatDead($r)) { $extra = []; $r = sendMsg(BOT_TOKEN, $chat, $t, $kb); }
    if ($chat !== $aid && empty($dead)) chatDown($chat, $r);
    if (empty($r['ok']) && $chat !== $aid && tgChatDead($r)) {
        if (function_exists('adminAlertOnce'))
            adminAlertOnce('sup_chat_dead', "🎧 <b>تیکت به گروهِ پشتیبانی نرفت</b>\n\nگروه: <code>" . h($chat) . "</code>\n" . tgWhy($r) .
                "\n\nتا درست شود، تیکت‌ها به پیویِ مدیر می‌آیند.\nپنلِ وب ← پشتیبانی", 3600);
        $chat = $aid;
        $extra = [];
        $r = $chat !== '' && function_exists('notifyAdmins') && notifyAdmins($t, $kb) ? ['ok' => true, 'result' => ['message_id' => 0]] : $r;
        $copy = is_array($copy) ? $copy : null;
        if (!empty($r['ok']) && $copy) {
            foreach (ADMIN_IDS as $a) tg(BOT_TOKEN, 'copyMessage', ['chat_id' => $a, 'from_chat_id' => $copy[0], 'message_id' => (int)$copy[1]], 15);
            $copy = null;
        }
    }
    if (empty($r['ok'])) {
        error_log('[masup] ' . (string)($r['description'] ?? ''));
        return [false, 'ارسال نشد. لطفا دوباره تلاش کنید.'];
    }
    if (is_array($copy) && !empty($copy[0]) && !empty($copy[1])) {
        tg(BOT_TOKEN, 'copyMessage', array_merge(['chat_id' => $chat, 'from_chat_id' => $copy[0],
            'message_id' => (int)$copy[1], 'reply_to_message_id' => (int)($r['result']['message_id'] ?? 0)], $extra), 15);
    }
    return [true, ''];
}

function maSupDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;
    try {
        $db = nbRawOpen(rtrim(DATA_DIR, '/') . '/support.sqlite');
    } catch (Throwable $e) {
        error_log('[supdb] ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS msgs (id INTEGER PRIMARY KEY AUTOINCREMENT, uid INTEGER NOT NULL,
        dir TEXT NOT NULL, body TEXT NOT NULL, at INTEGER NOT NULL)');
    $db->exec('CREATE INDEX IF NOT EXISTS msgs_uid ON msgs (uid, id)');
    $db->exec('CREATE TABLE IF NOT EXISTS seen (uid INTEGER PRIMARY KEY, sid INTEGER NOT NULL)');
    return $db;
}

function maSupLog($uid, $dir, $body) {
    $uid  = (int)$uid;
    $body = trim(mb_substr((string)$body, 0, 1500));
    if ($uid <= 0 || $body === '' || !in_array($dir, ['u', 's'], true)) return 0;
    $db = maSupDb();
    if (!$db) return 0;
    $st = $db->prepare('INSERT INTO msgs (uid, dir, body, at) VALUES (:u, :d, :b, :t)');
    $st->bindValue(':u', $uid, SQLITE3_INTEGER);
    $st->bindValue(':d', $dir, SQLITE3_TEXT);
    $st->bindValue(':b', $body, SQLITE3_TEXT);
    $st->bindValue(':t', time(), SQLITE3_INTEGER);
    if (!$st->execute()) return 0;
    $id = (int)$db->lastInsertRowID();
    if ($id % 40 === 0) {
        $pr = $db->prepare('DELETE FROM msgs WHERE uid = :u AND id <= COALESCE(
            (SELECT id FROM msgs WHERE uid = :u ORDER BY id DESC LIMIT 1 OFFSET 300), 0)');
        $pr->bindValue(':u', $uid, SQLITE3_INTEGER);
        $pr->execute();
    }
    return $id;
}

function maSupThread($uid, $after = 0, $limit = 100) {
    $db = maSupDb();
    if (!$db) return [];
    $st = $db->prepare('SELECT id, dir, body, at FROM msgs WHERE uid = :u AND id > :a ORDER BY id DESC LIMIT :n');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':a', max(0, (int)$after), SQLITE3_INTEGER);
    $st->bindValue(':n', max(1, min(150, (int)$limit)), SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC)))
        $out[] = ['id' => (int)$r['id'], 'me' => $r['dir'] === 'u', 't' => (string)$r['body'], 'at' => (int)$r['at']];
    return array_reverse($out);
}

function maSupSeen($uid, $sid) {
    $db = maSupDb();
    if (!$db || (int)$sid <= 0) return;
    $st = $db->prepare('INSERT OR REPLACE INTO seen (uid, sid) VALUES (:u,
        MAX(:s, COALESCE((SELECT sid FROM seen WHERE uid = :u), 0)))');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':s', (int)$sid, SQLITE3_INTEGER);
    $st->execute();
}

function maSupState($uid) {
    $db = maSupDb();
    if (!$db) return ['has' => false, 'unread' => 0];
    $st = $db->prepare("SELECT COUNT(*) AS n,
        SUM(CASE WHEN dir = 's' AND id > COALESCE((SELECT sid FROM seen WHERE uid = :u), 0) THEN 1 ELSE 0 END) AS un
        FROM msgs WHERE uid = :u");
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $r = $st->execute();
    $row = $r ? $r->fetchArray(SQLITE3_ASSOC) : null;
    return ['has' => (int)($row['n'] ?? 0) > 0, 'unread' => (int)($row['un'] ?? 0)];
}

function maSupKind($m) {
    foreach (['photo' => 'عکس', 'voice' => 'ویس', 'video' => 'ویدیو', 'video_note' => 'ویدیو', 'audio' => 'صوت',
              'document' => 'فایل', 'animation' => 'گیف', 'sticker' => 'استیکر'] as $k => $lbl)
        if (isset($m[$k])) return $lbl;
    return 'پیوست';
}

function maSupIsGroup($chat) {
    $c = maCfg()['sup'] ?? [];
    $want = trim((string)($c['chat'] ?? ''));
    if (empty($c['on']) || $want === '' || !is_array($chat)) return false;
    if ($want === (string)($chat['id'] ?? '')) return true;
    $un = (string)($chat['username'] ?? '');
    return $un !== '' && strcasecmp(ltrim($want, '@'), $un) === 0;
}

function maSupStaff($chatId, $uid) {
    static $memo = [];
    if (isAdmin($uid)) return true;
    $k = $chatId . ':' . (int)$uid;
    if (!isset($memo[$k])) {
        $r = tg(BOT_TOKEN, 'getChatMember', ['chat_id' => $chatId, 'user_id' => (int)$uid], 8);
        $memo[$k] = in_array((string)($r['result']['status'] ?? ''), ['creator', 'administrator'], true);
    }
    return $memo[$k];
}

function maSupFromBot($m) {
    return is_array($m) && !empty($m['from']['is_bot'])
        && (string)($m['from']['id'] ?? '') === (string)strtok(BOT_TOKEN, ':');
}

function maSupTarget($m) {
    if (!is_array($m)) return 0;
    foreach ((array)($m['reply_markup']['inline_keyboard'] ?? []) as $row)
        foreach ((array)$row as $b)
            if (preg_match('/^reply_(\d+)$/', (string)($b['callback_data'] ?? ''), $x)) return (int)$x[1];
    $t = (string)($m['text'] ?? '');
    if (preg_match('/^↩️ پاسخ به کاربر (\d{3,20})/u', $t, $x)) return (int)$x[1];
    return 0;
}

function maSupReply($msg) {
    $chat = $msg['chat'] ?? [];
    $cid  = $chat['id'] ?? null;
    $uid  = (int)($msg['from']['id'] ?? 0);
    $mid  = (int)($msg['message_id'] ?? 0);
    $rt   = $msg['reply_to_message'] ?? null;
    if ($cid === null || !$uid || !maSupFromBot($rt)) return false;
    $to = maSupTarget($rt);
    if ($to <= 0) return false;
    $private = ($chat['type'] ?? '') === 'private';
    if ($private) {
        if (!isAdmin($uid)) return false;
        $st = getState($uid);
        if (($st['action'] ?? '') === 'reply_user') clearState($uid);
    } else {
        if (!maSupIsGroup($chat)) return false;
        $anon = (string)($msg['sender_chat']['id'] ?? '') === (string)$cid;
        if (!$anon && !maSupStaff($cid, $uid)) return true;
    }

    [$r, $logged] = maSupDeliver($to, $msg);

    if (empty($r['ok'])) {
        sendMsg(BOT_TOKEN, $cid, ($logged ? '⚠️ پیوی نرسید، ولی در چتِ مینی‌اپ دیده می‌شود: ' : '❌ به کاربر نرسید: ')
            . h((string)($r['description'] ?? '')), null, ['reply_to_message_id' => $mid]);
        if ($logged) maSupMarkAnswered($cid, $rt, $to);
        return true;
    }
    if (!$private && str_starts_with((string)($rt['text'] ?? ''), '↩️ پاسخ به کاربر')) delMsg(BOT_TOKEN, $cid, (int)$rt['message_id']);
    $rx = tg(BOT_TOKEN, 'setMessageReaction', ['chat_id' => $cid, 'message_id' => $mid,
        'reaction' => json_encode([['type' => 'emoji', 'emoji' => '👍']])], 6);
    if (empty($rx['ok'])) sendMsg(BOT_TOKEN, $cid, '✅ به کاربر رسید.', null, ['reply_to_message_id' => $mid]);
    maSupMarkAnswered($cid, $rt, $to);
    return true;
}

function maSupDeliver($to, $msg) {
    $cid  = $msg['chat']['id'] ?? null;
    $mid  = (int)($msg['message_id'] ?? 0);
    $head = "💬 <b>پاسخ پشتیبانی</b>";
    $kb = null;
    if (isset($msg['text'])) {
        $body = msgHtml($msg);
        $r = trim($body) === '' ? ['ok' => false, 'description' => 'پیام خالی است'] : sendMsg(BOT_TOKEN, $to, $head . "\n\n" . emKeep($body), $kb);
        $log = (string)$msg['text'];
    } else {
        $r = sendMsg(BOT_TOKEN, $to, $head, $kb);
        if (!empty($r['ok'])) $r = tg(BOT_TOKEN, 'copyMessage', ['chat_id' => $to, 'from_chat_id' => $cid, 'message_id' => $mid], 15);
        $cap = trim((string)($msg['caption'] ?? ''));
        $log = '📎 ' . maSupKind($msg) . ($cap !== '' ? ' — ' . $cap : '') . ' (در پیویِ ربات فرستاده شد)';
    }
    return [$r, maSupLog($to, 's', $log)];
}

function maSupMarkAnswered($cid, $rt, $to) {
    $has = false;
    foreach ((array)($rt['reply_markup']['inline_keyboard'] ?? []) as $row)
        foreach ((array)$row as $b) {
            if (str_starts_with((string)($b['text'] ?? ''), '✅')) return;
            if (str_starts_with((string)($b['callback_data'] ?? ''), 'reply_')) $has = true;
        }
    if (!$has) return;
    tg(BOT_TOKEN, 'editMessageReplyMarkup', ['chat_id' => $cid, 'message_id' => (int)$rt['message_id'],
        'reply_markup' => kbJson(inlineKb([[btnCb('✅ پاسخ داده شد · پاسخِ دوباره', 'reply_' . (int)$to, 'admin')]]))], 8);
}

function maSupPrompt($cb, $uid, $cbId) {
    $m    = $cb['message'] ?? [];
    $chat = $m['chat'] ?? [];
    if (!maSupIsGroup($chat)) return false;
    $to = (int)substr((string)($cb['data'] ?? ''), 6);
    if ($to <= 0) { answerCb(BOT_TOKEN, $cbId); return true; }
    if (!maSupStaff($chat['id'], $uid)) { answerCb(BOT_TOKEN, $cbId, '🔒 فقط ادمین‌های گروه می‌توانند پاسخ بدهند.', true); return true; }
    answerCb(BOT_TOKEN, $cbId);
    sendMsg(BOT_TOKEN, $chat['id'], "↩️ پاسخ به کاربر <code>{$to}</code>\n\nروی همین پیام ریپلای کنید؛ متن، عکس، ویس یا فایل مستقیم به کاربر می‌رسد.",
        json_encode(['force_reply' => true, 'input_field_placeholder' => 'پاسخ به کاربر…']),
        ['reply_to_message_id' => (int)($m['message_id'] ?? 0)]);
    return true;
}

function maSupFollowUp($msg, $uid, $uname, $fname, $chatId) {
    $rt = $msg['reply_to_message'] ?? null;
    if (!maSupFromBot($rt) || !str_starts_with(trim((string)($rt['text'] ?? '')), '💬 پاسخ پشتیبانی')) return false;
    if (!maSupOn()) return false;
    $txt  = trim((string)($msg['text'] ?? $msg['caption'] ?? ''));
    $copy = isset($msg['text']) ? null : [$chatId, (int)($msg['message_id'] ?? 0)];
    [$ok, $err] = maSupSend($uid, $uname, $fname, '', $txt, true, $copy);
    if ($ok) maSupLog($uid, 'u', $txt !== '' ? $txt : '📎 ' . maSupKind($msg));
    sendMsg(BOT_TOKEN, $chatId, $ok ? '✅ پیامتان به پشتیبانی رسید.' : '⚠️ ' . h($err), null,
        ['reply_to_message_id' => (int)($msg['message_id'] ?? 0)]);
    return true;
}

function maCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin) {
    if (str_starts_with($data, 'marv_')) return maRateCallback($data, $uid, $chatId, $msgId, $cbId);
    if (!str_starts_with($data, 'maadm')) return false;
    if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
    return maAdminCallback($data, $uid, $chatId, $msgId, $cbId);
}

function maStateHandle($action, $sd, $msg, $uid, $chatId) {
    if ($action === 'marv') return maRateText($sd, $msg, $uid, $chatId);
    if (!str_starts_with($action, 'ma_')) return false;
    if (!isAdmin($uid)) { clearState($uid); return true; }
    return maAdminState($action, $msg, $uid, $chatId);
}

function maAdmHome($chatId, $msgId = null) {
    $c    = maCfg();
    $base = maBaseUrl();
    $cats = count(array_filter(maCats(), fn($x) => !empty($x['on'])));
    $nums = count(array_filter(maItems(), fn($x) => !empty($x['on'])));

    $t  = "📱 <b>مینی‌اپ شماره مجازی</b>\n\n";
    $t .= 'وضعیت: ' . (!empty($c['on']) ? '✅ باز' : '❌ بسته') . "\n";
    $t .= '🔗 آدرس: ' . ($base !== '' ? '<code>' . h($base) . '</code>' : '<b>ثبت نشده</b>') . "\n";
    $t .= '🏷 عنوان: ' . h((string)$c['title']) . "\n";
    $t .= '✨ شعار: ' . h((string)$c['tagline']) . "\n";
    $t .= '💡 یادداشت خرید: ' . h(mb_substr((string)$c['note'], 0, 70)) . "\n";
    $t .= '🔘 دکمه‌ی منوی ربات: <b>' . h((string)$c['menu']) . "</b>\n";
    $t .= '🖼 لوگو: ' . (maLogoPath() !== '' ? '✅ ثبت شده' : '— پیش‌فرض') . "\n";
    $t .= '⏳ صفحه‌ی لودینگ: <b>' . maSplashSec() . "</b> ثانیه\n\n";
    $t .= "🌍 کشور فعال: <b>{$cats}</b> · ☎️ شماره‌ی فعال: <b>{$nums}</b>\n";
    $t .= '✅ تحویل‌شده: <b>' . fmtNum(MaOrder::countBy(MaOrder::DONE)) . '</b> · 📲 منتظر کد: <b>' .
          fmtNum(MaOrder::countBy(MaOrder::PAID)) . "</b>\n";
    if ($base === '')
        $t .= "\n⚠️ تا آدرس ثبت نشود، دکمه‌ی مینی‌اپ نمایش داده نمی‌شود.\n" .
              "آدرس در <b>پنلِ وب ← API و اتصال‌ها ← آدرسِ عمومی</b> ثبت می‌شود.";
    if ($nums === 0)
        $t .= "\n⚠️ هنوز شماره‌ای برای فروش نیست — <b>پنلِ وب ← کشورها و قیمت‌ها ← 📥 وارد کردن</b>.";

    $rows = [
        [btnCb(!empty($c['on']) ? '❌ بستن مینی‌اپ' : '✅ باز کردن مینی‌اپ', 'maadm_tog', 'info')],
        [btnCb('🏷 عنوان', 'maadm_txt_title', 'admin'), btnCb('✨ شعار', 'maadm_txt_tagline', 'admin')],
        [btnCb('💡 یادداشت خرید', 'maadm_txt_note', 'admin'), btnCb('🔘 متن دکمه‌ی منو', 'maadm_txt_menu', 'admin')],
        [btnCb('📌 دکمه‌ی Open کنار اسم ربات', 'maadm_main', 'info')],
        [btnCb('🖼 لوگو', 'maadm_logo', 'admin'), btnCb('⏳ صفحه‌ی لودینگ', 'maadm_spl', 'admin')],
        [btnCb('☎️ تیکتِ پشتیبانی', 'maadm_sup', 'admin')],
        [btnCb('🧩 مینی‌اپ‌های خدمات تلگرام و اینستاگرام', 'svadm', 'confirm')],
        [btnCb('🧾 آخرین سفارش‌ها', 'maadm_orders', 'admin'), btnCb('⭐ نظرات خریداران', 'maadm_rev', 'admin')],
        [btnCb('☎️ فروشنده، قیمت و کشورها', 'num_home', 'confirm')],
    ];
    if ($open = maWebAppBtn('🧪 باز کردنِ مینی‌اپ', '', 'confirm')) $rows[] = [$open];
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function maAdmSup($chatId, $msgId = null) {
    $c    = maCfg()['sup'] ?? [];
    $chat = trim((string)($c['chat'] ?? ''));
    $th   = (int)($c['thread'] ?? 0);
    $on   = !empty($c['on']) && $chat !== '';

    $t  = "☎️ <b>تیکت‌های پشتیبانیِ مینی‌اپ</b>\n\n";
    $t .= $on
        ? '📢 گروه: <code>' . h($chat) . '</code>' . ($th > 0 ? ' · 🧵 تاپیک <b>' . $th . '</b>' : '') . "\n"
        : "📥 تیکت‌ها به پیویِ مدیر می‌آیند.\n";
    $t .= "\n⚙️ پنلِ وب ← پشتیبانی";

    $rows = [
        [btnCb('🧪 ارسالِ آزمایشی', 'maadm_suptest', 'confirm')],
        [btnCb(UT('back'), 'maadm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function maAdmLogo($chatId, $msgId = null) {
    $p  = maLogoPath();
    $on = $p !== '';

    $text  = "🖼 <b>لوگوی صفحه‌ی شروع</b>\n\n";
    $text .= 'وضعیت: ' . ($on ? '✅ ثبت شده' : '— لوگوی پیش‌فرض') . "\n";
    if ($on) $text .= 'حجم: <code>' . (int)round(filesize($p) / 1024) . " KB</code>\n";
    $text .= "\n🖼 ۵۱۲×۵۱۲";

    $rows = [[btnCb($on ? '♻️ تعویض لوگو' : '📤 فرستادن لوگو', 'maadm_logoset', 'confirm')]];
    if ($on) $rows[] = [btnCb('🗑 حذف لوگو', 'maadm_logodel', 'reject')];
    $rows[] = [btnCb(UT('back'), 'maadm_home', 'nav')];

    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($rows));
}

function maAdmOrders($chatId, $msgId) {
    [$list] = MaOrder::page('', '', 0, 15);
    $t = "🧾 <b>آخرین سفارش‌های شماره</b>\n\n";
    if (!$list) $t .= 'هنوز سفارشی نیست.';
    foreach ($list as $o) {
        $act = function_exists('numGet') ? numGet((string)$o['id']) : null;
        $t .= MaOrder::statusLabel((string)$o['status']) . ' · ' . h((string)$o['item_name']) . "\n";
        $t .= '   💰 ' . fmtNum($o['total']) . ' · 👤 <code>' . (int)$o['user_id'] . '</code>' .
              ($act && !empty($act['phone']) ? ' · ☎️ <code>' . h((string)$act['phone']) . '</code>' : '') . "\n";
    }
    $rows = [
        [btnCb('📋 شماره‌های باز', 'numopen', 'reject')],
        [btnCb('🔄 تازه‌سازی', 'maadm_orders', 'info')],
        [btnCb(UT('back'), 'maadm_home', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 4000), inlineKb($rows));
}

function maAdmReviews($chatId, $msgId) {
    $st = maReviews();
    $t  = "⭐ <b>نظرات خریداران</b>\n\n";
    $t .= 'میانگین: <b>' . ($st['n'] ? $st['avg'] : '—') . '</b> از ۵ · تعداد: <b>' . fmtNum($st['n']) . "</b>\n";
    $t .= "\n";
    $rows = [];
    $list = maReviewsAdmin(8);
    if (!$list) $t .= 'هنوز نظری ثبت نشده.';
    foreach ($list as $i => $r) {
        $t .= ($i + 1) . '. ' . str_repeat('⭐', (int)$r['rating']) . ' — ' . ((string)$r['name'] !== '' ? h((string)$r['name']) . ' ' : '') .
              '<code>' . (int)$r['uid'] . "</code>\n" .
              ($r['txt'] !== '' ? '<i>' . emKeep(h(mb_substr((string)$r['txt'], 0, 120))) . "</i>\n" : '') . "\n";
        $rows[] = [btnCb('🙈 پنهان کردنِ نظر ' . ($i + 1), 'maadm_rvh_' . $r['oid'], 'reject')];
    }
    $rows[] = [btnCb(UT('back'), 'maadm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 4000), inlineKb($rows));
}

function maAdmMain($chatId, $msgId) {
    $u  = maUrl();
    $t  = "📌 <b>دکمه‌ی «Open» کنارِ اسمِ ربات</b>\n\n";
    $t .= "@BotFather ← <code>/mybots</code> ← <b>Bot Settings</b> ← <b>Configure Mini App</b> ← <b>Enable Mini App</b>\n\n";
    $t .= $u !== '' ? 'Web App URL:\n<code>' . h($u) . '</code>' : '⚠️ آدرسِ عمومی ثبت نشده';
    $rows = [];
    if ($u !== '') $rows[] = [btnUrl('🤖 رفتن به BotFather', 'https://t.me/BotFather', 'link')];
    $rows[] = [btnCb(UT('back'), 'maadm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function maTextLabels() {
    return [
        'title'   => ['🏷 عنوانِ مینی‌اپ', 40],
        'tagline' => ['✨ شعار', 90],
        'note'    => ['💡 یادداشتِ زیرِ خرید', 240],
        'menu'    => ['🔘 متنِ دکمه‌ی منوی ربات', 24],
    ];
}

function maAdminCallback($data, $uid, $chatId, $msgId, $cbId) {
    if ($data === 'maadm_home')   { answerCb(BOT_TOKEN, $cbId); clearState($uid); maAdmHome($chatId, $msgId); return true; }
    if ($data === 'maadm_sup')    { answerCb(BOT_TOKEN, $cbId); maAdmSup($chatId, $msgId); return true; }
    if ($data === 'maadm_logo')   { answerCb(BOT_TOKEN, $cbId); clearState($uid); maAdmLogo($chatId, $msgId); return true; }
    if ($data === 'maadm_orders') { answerCb(BOT_TOKEN, $cbId); maAdmOrders($chatId, $msgId); return true; }
    if ($data === 'maadm_rev')    { answerCb(BOT_TOKEN, $cbId); maAdmReviews($chatId, $msgId); return true; }
    if (str_starts_with($data, 'maadm_rvh_')) {
        maReviewHide(substr($data, 10));
        answerCb(BOT_TOKEN, $cbId, '🙈 پنهان شد');
        maAdmReviews($chatId, $msgId);
        return true;
    }

    if ($data === 'maadm_tog') {
        maSetRoot(function (&$m) { $m['on'] = empty($m['on']); });
        maMenuSync();
        answerCb(BOT_TOKEN, $cbId, !empty(maCfg()['on']) ? '✅ باز شد' : '❌ بسته شد');
        maAdmHome($chatId, $msgId);
        return true;
    }

    if ($data === 'maadm_suptest') {
        [$ok, $err] = maSupSend($uid, '', 'تستِ ادمین', '—', 'این یک پیامِ آزمایشی از پنل است.');
        answerCb(BOT_TOKEN, $cbId, $ok ? '✅ فرستاده شد' : '❌ ' . $err, true);
        return true;
    }

    if ($data === 'maadm_spl') {
        answerCb(BOT_TOKEN, $cbId);
        maAskState($uid, $chatId, 'ma_spl', [], '⏳ <b>صفحه‌ی لودینگِ مینی‌اپ</b>',
            "۰–۲۰ ثانیه\n\nالان: <b>" . maSplashSec() . '</b>', 'maadm_home');
        return true;
    }

    if ($data === 'maadm_logoset') {
        answerCb(BOT_TOKEN, $cbId);
        maAskState($uid, $chatId, 'ma_logo', [], '🖼 <b>لوگو</b>',
            "PNG/JPG · ۵۱۲×۵۱۲", 'maadm_logo');
        return true;
    }
    if ($data === 'maadm_logodel') {
        maLogoClear();
        answerCb(BOT_TOKEN, $cbId, '🗑 پاک شد');
        maAdmLogo($chatId, $msgId);
        return true;
    }

    if ($data === 'maadm_main') { answerCb(BOT_TOKEN, $cbId); maAdmMain($chatId, $msgId); return true; }

    if (preg_match('/^maadm_txt_(title|tagline|note|menu)$/', $data, $m)) {
        [$lbl, $max] = maTextLabels()[$m[1]];
        answerCb(BOT_TOKEN, $cbId);
        maAskState($uid, $chatId, 'ma_text', ['k' => $m[1]], '<b>' . $lbl . '</b>',
            "حداکثر {$max} حرف · <code>-</code>\n\nالان: <code>" . h((string)maCfg()[$m[1]]) . "</code>");
        return true;
    }

    answerCb(BOT_TOKEN, $cbId);
    return true;
}

function maAdminState($action, $msg, $uid, $chatId) {
    $plain = trim((string)($msg['text'] ?? ''));
    $back  = inlineKb([[btnCb('📱 مینی‌اپ', 'maadm_home', 'admin')]]);

    if ($action === 'ma_spl') {
        $d = norm_fa_digits($plain);
        if (!preg_match('/^\d{1,2}$/', $d) || (int)$d > 20) { sendMsg(BOT_TOKEN, $chatId, '⚠️ ۰ تا ۲۰'); return true; }
        $v = (int)$d;
        maSetRoot(function (&$m) use ($v) { $m['splash'] = $v; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ صفحه‌ی لودینگ: <b>' . $v . '</b> ثانیه', $back);
        return true;
    }

    if ($action === 'ma_logo') {
        $fid = '';
        if (!empty($msg['photo'])) $fid = (string)$msg['photo'][count($msg['photo']) - 1]['file_id'];
        elseif (!empty($msg['document']) && str_starts_with((string)($msg['document']['mime_type'] ?? ''), 'image/'))
            $fid = (string)$msg['document']['file_id'];
        if ($fid === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ عکس لازم است'); return true; }
        [$ok, $err] = maLogoSave($fid);
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $ok ? '✅ لوگو ثبت شد.' : '❌ ' . h($err),
            inlineKb([[btnCb('🖼 لوگو', 'maadm_logo', 'admin')]]));
        return true;
    }

    if ($action === 'ma_text') {
        $k = (string)((getState($uid)['data'] ?? [])['k'] ?? '');
        if (!isset(maTextLabels()[$k])) { clearState($uid); return true; }
        $max = maTextLabels()[$k][1];
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی است.'); return true; }
        $v = ($plain === '-' || $plain === '—') ? (string)maDefaultConfig()[$k] : mb_substr($plain, 0, $max);
        maSetRoot(function (&$m) use ($k, $v) { $m[$k] = $v; });
        if ($k === 'menu') maMenuSync();
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد: <b>' . h($v) . '</b>', $back);
        return true;
    }

    clearState($uid);
    return true;
}

function maSplashLogoHtml() {
    $uri = maLogoDataUri();
    if ($uri === '') return '';
    return '<img class="splash-logo" src="' . htmlspecialchars($uri, ENT_QUOTES, 'UTF-8') .
           '" width="104" height="104" alt="" draggable="false">';
}

function maLogoPath() {
    if (!defined('DATA_DIR')) return '';
    foreach (['png', 'jpg'] as $ext) {
        $p = DATA_DIR . '/ma_logo.' . $ext;
        if (is_file($p) && filesize($p) > 0) return $p;
    }
    return '';
}

function maLogoDataUri() {
    static $memo = [null, ''];

    $p  = maLogoPath();
    $ck = $p === '' ? '' : $p . ':' . (int)@filemtime($p);
    if ($memo[0] === $ck) return $memo[1];
    $memo[0] = $ck;

    if ($p === '') return $memo[1] = '';

    if (filesize($p) > 400000) return $memo[1] = '';

    $bin = @file_get_contents($p);
    if ($bin === false || $bin === '') return $memo[1] = '';

    $mime = (substr($p, -4) === '.png') ? 'image/png' : 'image/jpeg';
    return $memo[1] = 'data:' . $mime . ';base64,' . base64_encode($bin);
}

function maLogoSave($fileId) {
    if (!defined('DATA_DIR')) return [false, 'مسیرِ داده تعریف نشده.'];
    $f  = tg(BOT_TOKEN, 'getFile', ['file_id' => (string)$fileId], 10);
    $fp = (string)($f['result']['file_path'] ?? '');
    if ($fp === '') return [false, 'فایل از تلگرام گرفته نشد.'];

    $url = rtrim(TG_API_BASE, '/') . '/file/bot' . BOT_TOKEN . '/' . $fp;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8]);
    $bin = monCurl($ch, 'telegram');
    curl_close($ch);
    if (!$bin) return [false, 'دانلود عکس نشد.'];

    maLogoClear();

    if (!function_exists('imagecreatefromstring')) {
        if (strlen($bin) > 400000) return [false, 'عکس بزرگ‌تر از ۴۰۰ کیلوبایت است'];
        $ext = (substr($bin, 0, 8) === "\x89PNG\r\n\x1a\n") ? 'png' : 'jpg';
        return @file_put_contents(DATA_DIR . '/ma_logo.' . $ext, $bin)
            ? [true, ''] : [false, 'ذخیره نشد.'];
    }

    $src = @imagecreatefromstring($bin);
    if (!$src) return [false, 'این فایل عکس نیست.'];

    $sw = imagesx($src); $sh = imagesy($src);
    $side = min($sw, $sh);
    $sx = (int)(($sw - $side) / 2);
    $sy = (int)(($sh - $side) / 2);

    $out = imagecreatetruecolor(208, 208);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagealphablending($out, true);
    imagecopyresampled($out, $src, 0, 0, $sx, $sy, 208, 208, $side, $side);
    imagedestroy($src);

    $ok = @imagepng($out, DATA_DIR . '/ma_logo.png', 8);
    imagedestroy($out);
    return $ok ? [true, ''] : [false, 'ذخیره نشد.'];
}

function maLogoClear() {
    if (!defined('DATA_DIR')) return;
    foreach (['png', 'jpg'] as $ext) @unlink(DATA_DIR . '/ma_logo.' . $ext);
}

function maAskState($uid, $chatId, $action, $data, $title, $hint = '', $back = null) {
    setState($uid, $action, $data);
    $rows = [];
    if ($back) $rows[] = [btnCb(UT('cancel'), $back, 'cancel')];
    else       $rows[] = [btnCb(UT('cancel'), 'cancel', 'cancel')];
    sendMsg(BOT_TOKEN, $chatId, $title . ($hint !== '' ? "\n\n" . $hint : ''), inlineKb($rows));
}

function norm_fa_digits($s) {
    return strtr((string)$s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}
