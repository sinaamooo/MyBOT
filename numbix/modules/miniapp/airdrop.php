<?php
defined('NB_ROOT') || exit;

define('AD_BASE_RATE', 30.0);
define('AD_XP_PER_LEVEL', 300.0);
define('AD_MAX_LEVEL', 50);
define('AD_MAX_BUFFER_HOURS', 24);
define('AD_SEASON_DAYS', 120);
define('AD_REDEEM_RATE', 50.0);
define('AD_REDEEM_MIN', 100.0);
define('AD_COUPON_MIN', 200.0);
define('AD_COUPON_DAYS', 30);
define('AD_BOOST_PRICE', 100.0);
define('AD_BOOST_STEP', 0.20);
define('AD_BOOST_MAX', 25);
define('AD_TOUR_VER', 'v1');
define('AD_TAP_MAX', 200);
define('AD_TAP_REGEN', 3.0);
define('AD_TAP_DAY', 1000);
define('AD_TAP_RATE', 12);

// Anti-abuse limits for turning free crystals into real wallet money / coupons.
// Override any of these in config.local.php (define before the bot is loaded).
if (!defined('AD_ON'))              define('AD_ON', true);       // whole airdrop section
if (!defined('AD_CASHOUT_ON'))      define('AD_CASHOUT_ON', true); // crystals -> wallet / coupon
if (!defined('AD_CASHOUT_NEED_BUY')) define('AD_CASHOUT_NEED_BUY', 1); // delivered orders needed before any cash-out
if (!defined('AD_CASHOUT_DAY_MAX')) define('AD_CASHOUT_DAY_MAX', 10000.0);  // toman per user per day (0 = no limit)
if (!defined('AD_CASHOUT_SPEND_PCT')) define('AD_CASHOUT_SPEND_PCT', 20.0); // lifetime cash-out <= this % of what the user really paid (0 = off)
if (!defined('AD_CASHOUT_ALL_DAY_MAX')) define('AD_CASHOUT_ALL_DAY_MAX', 300000.0); // toman for all users per day (0 = no limit)

// ── Admin-editable airdrop settings (from /panel) ────────────────────────────
// Values live in cfg()['airdrop']; the constants above are the defaults/fallbacks,
// and a define() in config.local.php still wins over the default when nothing is
// set in the panel. So precedence is: panel value > config.local define > constant.
function adCfg() { $c = cfg()['airdrop'] ?? null; return is_array($c) ? $c : []; }
function adSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['airdrop'] ?? null)) $c['airdrop'] = [];
        $fn($c['airdrop']);
    });
}
function adVal($k, $d = null) { $c = adCfg(); return array_key_exists($k, $c) ? $c[$k] : $d; }

function adOn()          { return (int)adVal('on', AD_ON ? 1 : 0) === 1; }
function adCashoutOn()   { return (int)adVal('cashout_on', AD_CASHOUT_ON ? 1 : 0) === 1; }
function adNeedBuy()     { return max(0, (int)adVal('cashout_need_buy', AD_CASHOUT_NEED_BUY)); }
function adDayMax()      { return max(0.0, (float)adVal('cashout_day_max', AD_CASHOUT_DAY_MAX)); }
function adSpendPct()    { return max(0.0, min(1000.0, (float)adVal('cashout_spend_pct', AD_CASHOUT_SPEND_PCT))); }
function adAllDayMax()   { return max(0.0, (float)adVal('cashout_all_day_max', AD_CASHOUT_ALL_DAY_MAX)); }
function adRedeemRate()  { return max(0.0, (float)adVal('redeem_rate', AD_REDEEM_RATE)); }
function adRedeemMin()   { return max(1.0, (float)adVal('redeem_min', AD_REDEEM_MIN)); }
function adCouponMin()   { return max(1.0, (float)adVal('coupon_min', AD_COUPON_MIN)); }
function adBaseRate()    { return max(0.0, (float)adVal('base_rate', AD_BASE_RATE)); }

function adDbPath() { return DATA_DIR . '/airdrop.sqlite'; }

function adDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;

    $path = adDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    try {
        $db = nbRawOpen($path);
    } catch (Throwable $e) {
        error_log('[airdrop] airdrop.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS airdrop_users (
        id INTEGER PRIMARY KEY,
        crystals REAL NOT NULL DEFAULT 0,
        level INTEGER NOT NULL DEFAULT 1,
        xp REAL NOT NULL DEFAULT 0,
        last_tick INTEGER NOT NULL DEFAULT 0,
        season INTEGER NOT NULL DEFAULT 1,
        data TEXT NOT NULL DEFAULT \'{}\'
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_airdrop_crystals ON airdrop_users(season, crystals DESC)');
    return $db;
}

function adSeasonMeta() {
    $db = adDb();
    $fallback = ['season' => 1, 'start' => time(), 'end' => time() + AD_SEASON_DAYS * 86400];
    if (!$db) return $fallback;

    $row = $db->querySingle('SELECT data FROM airdrop_users WHERE id = 0', true);
    if ($row) {
        $d = json_decode((string)($row['data'] ?? ''), true);
        if (is_array($d) && isset($d['season'], $d['start'], $d['end'])) return $d;
    }
    $stmt = $db->prepare('INSERT OR REPLACE INTO airdrop_users (id, crystals, level, xp, last_tick, season, data) VALUES (0,0,0,0,0,0,:d)');
    $stmt->bindValue(':d', json_encode($fallback), SQLITE3_TEXT);
    $stmt->execute();
    return $fallback;
}

function adXpForLevel($level) { return AD_XP_PER_LEVEL * max(1, (int)$level); }
function adBoostN($u) { return max(0, min(AD_BOOST_MAX, (int)($u['data']['boost_n'] ?? 0))); }
function adRate($level, $boostN = 0) {
    return adBaseRate() * max(1, (int)$level) * (1 + AD_BOOST_STEP * max(0, (int)$boostN));
}

function adGain(&$u, $amount) {
    $u['crystals'] = round($u['crystals'] + $amount, 4);
    $u['xp'] = round($u['xp'] + $amount, 4);
    while ($u['level'] < AD_MAX_LEVEL && $u['xp'] >= adXpForLevel($u['level'])) {
        $u['xp'] -= adXpForLevel($u['level']);
        $u['level']++;
    }
}

function adTapValue($u) { return round(adRate($u['level'], adBoostN($u)) / 300, 2); }

function adEnergy($u, $now) {
    if (!isset($u['data']['en'])) return (float)AD_TAP_MAX;
    return min((float)AD_TAP_MAX, (float)$u['data']['en'] + max(0.0, $now - (float)($u['data']['en_t'] ?? $now)) / AD_TAP_REGEN);
}

function adTapsLeft($u) {
    return ($u['data']['tap_day'] ?? '') === gmdate('Y-m-d') ? max(0, AD_TAP_DAY - (int)($u['data']['tap_n'] ?? 0)) : AD_TAP_DAY;
}

function adTapInfo($u) {
    $rate = adRate($u['level'], adBoostN($u));
    $pend = $rate * min(max(0, time() - (int)$u['last_tick']), AD_MAX_BUFFER_HOURS * 3600) / 3600.0;
    return [
        'crystals'   => round($u['crystals'] + $pend, 4),
        'level'      => (int)$u['level'],
        'xp'         => $u['xp'],
        'xp_need'    => adXpForLevel($u['level']),
        'rate'       => $rate,
        'energy'     => round(adEnergy($u, microtime(true)), 2),
        'energy_max' => AD_TAP_MAX,
        'regen'      => AD_TAP_REGEN,
        'tap_value'  => adTapValue($u),
        'tap_left'   => adTapsLeft($u),
        'tap_day'    => AD_TAP_DAY,
    ];
}

function adTap($uid, $n) {
    $n = max(0, min(120, (int)$n));
    $got = 0;
    $u = adUserSet($uid, function (&$u) use ($n, &$got) {
        $now = microtime(true);
        $en  = adEnergy($u, $now);
        $day = gmdate('Y-m-d');
        if (($u['data']['tap_day'] ?? '') !== $day) { $u['data']['tap_day'] = $day; $u['data']['tap_n'] = 0; }
        $burst = (int)floor(min(30.0, max(0.0, $now - (float)($u['data']['tap_t'] ?? 0))) * AD_TAP_RATE) + AD_TAP_RATE;
        $got   = max(0, min($n, (int)floor($en), $burst, AD_TAP_DAY - (int)$u['data']['tap_n']));
        if ($got > 0) adGain($u, $got * adTapValue($u));
        $u['data']['en']    = round($en - $got, 3);
        $u['data']['en_t']  = $now;
        $u['data']['tap_t'] = $now;
        $u['data']['tap_n'] = (int)$u['data']['tap_n'] + $got;
        return $u;
    });
    return $u ? adTapInfo($u) + ['added' => $got] : null;
}

function adUser($uid) {
    $db = adDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT crystals, level, xp, last_tick, season, data FROM airdrop_users WHERE id = :id');
    $stmt->bindValue(':id', (int)$uid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode((string)($row['data'] ?? ''), true);
    return [
        'crystals'  => (float)$row['crystals'],
        'level'     => (int)$row['level'],
        'xp'        => (float)$row['xp'],
        'last_tick' => (int)$row['last_tick'],
        'season'    => (int)$row['season'],
        'data'      => is_array($d) ? $d : [],
    ];
}

function adUserSet($uid, callable $fn) {
    $db = adDb();
    if (!$db) return null;
    $id = (int)$uid;
    if ($id <= 0) return null;

    if (!@$db->exec('BEGIN IMMEDIATE')) return null;
    try {
        $stmt = $db->prepare('SELECT crystals, level, xp, last_tick, season, data FROM airdrop_users WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $season = adSeasonMeta()['season'];
        if ($row) {
            $d = json_decode((string)($row['data'] ?? ''), true);
            $u = [
                'crystals' => (float)$row['crystals'], 'level' => (int)$row['level'],
                'xp' => (float)$row['xp'], 'last_tick' => (int)$row['last_tick'],
                'season' => (int)$row['season'], 'data' => is_array($d) ? $d : [],
            ];
        } else {
            $u = ['crystals' => 0.0, 'level' => 1, 'xp' => 0.0, 'last_tick' => time(), 'season' => $season, 'data' => []];
        }

        $result = $fn($u);

        $up = $db->prepare('INSERT OR REPLACE INTO airdrop_users (id, crystals, level, xp, last_tick, season, data) VALUES (:id,:c,:l,:x,:t,:s,:d)');
        $up->bindValue(':id', $id, SQLITE3_INTEGER);
        $up->bindValue(':c', (float)$u['crystals'], SQLITE3_FLOAT);
        $up->bindValue(':l', (int)$u['level'], SQLITE3_INTEGER);
        $up->bindValue(':x', (float)$u['xp'], SQLITE3_FLOAT);
        $up->bindValue(':t', (int)$u['last_tick'], SQLITE3_INTEGER);
        $up->bindValue(':s', (int)$u['season'], SQLITE3_INTEGER);
        $up->bindValue(':d', json_encode($u['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        if (!$up->execute()) throw new RuntimeException('write');
        if (!@$db->exec('COMMIT')) throw new RuntimeException('commit');
    } catch (Throwable $e) {
        @$db->exec('ROLLBACK');
        error_log('[airdrop] adUserSet خطا: ' . $e->getMessage());
        return null;
    }
    return $result;
}

function adTick($uid, $name = '', $username = '') {
    return adUserSet($uid, function (&$u) use ($name, $username) {
        $now = time();
        $season = adSeasonMeta()['season'];
        if ((int)$u['season'] !== (int)$season) {
            $u['data']['prev_seasons'][(string)$u['season']] = ['crystals' => $u['crystals'], 'level' => $u['level']];
            $u['crystals'] = 0.0; $u['level'] = 1; $u['xp'] = 0.0; $u['season'] = $season;
        }
        $elapsed = max(0, $now - (int)$u['last_tick']);
        $elapsed = min($elapsed, AD_MAX_BUFFER_HOURS * 3600);
        if ($elapsed > 0) adGain($u, adRate($u['level'], adBoostN($u)) * ($elapsed / 3600.0));
        $u['last_tick'] = $now;
        if ($name !== '')     $u['data']['name'] = mb_substr($name, 0, 40);
        if ($username !== '') $u['data']['username'] = mb_substr(ltrim($username, '@'), 0, 40);
        adTouchStreak($u);
        return $u;
    });
}

function adCrystalsNow($uid) {
    $u = adUser($uid);
    if (!$u || (int)$u['season'] !== (int)adSeasonMeta()['season']) return 0.0;
    $el = min(max(0, time() - (int)$u['last_tick']), AD_MAX_BUFFER_HOURS * 3600);
    return (float)$u['crystals'] + adRate($u['level'], adBoostN($u)) * ($el / 3600.0);
}

function adTouchStreak(&$u) {
    $today = gmdate('Y-m-d');
    $last = (string)($u['data']['streak_date'] ?? '');
    if ($last === $today) return;
    if ($last === gmdate('Y-m-d', strtotime('-1 day'))) {
        $u['data']['streak'] = (int)($u['data']['streak'] ?? 0) + 1;
    } else {
        $u['data']['streak'] = 1;
    }
    $u['data']['streak_date'] = $today;
}

function adState($uid, $name = '', $username = '') {
    $u = adTick($uid, $name, $username);
    if (!$u) $u = ['crystals' => 0.0, 'level' => 1, 'xp' => 0.0, 'last_tick' => time(), 'season' => 1, 'data' => []];
    $meta = adSeasonMeta();
    $boostN = adBoostN($u);
    $rate   = adRate($u['level'], $boostN);
    $need = adXpForLevel($u['level']);
    return [
        'crystals'   => $u['crystals'],
        'level'      => $u['level'],
        'xp'         => $u['xp'],
        'xp_need'    => $need,
        'rate'       => $rate,
        'tick_secs'  => $rate > 0 ? round(3600.0 / $rate) : 0,
        'boost_n'    => $boostN,
        'boost_max'  => AD_BOOST_MAX,
        'boost_price'=> AD_BOOST_PRICE,
        'boost_step' => AD_BOOST_STEP,
        'season'     => $meta['season'],
        'season_end' => $meta['end'],
        'missions'   => adMissions($uid, $u),
        'redeem_rate'=> adRedeemRate(),
        'redeem_min' => adRedeemMin(),
        'coupon_min' => adCouponMin(),
        'coupon_days'=> AD_COUPON_DAYS,
        'streak'         => (int)($u['data']['streak'] ?? 0),
        'wallet_balance' => function_exists('getUser') ? (float)(getUser($uid)['balance'] ?? 0) : 0.0,
        'tour_seen'      => ((string)($u['data']['tour'] ?? '') === AD_TOUR_VER) ? 1 : 0,
        'energy'         => round(adEnergy($u, microtime(true)), 2),
        'energy_max'     => AD_TAP_MAX,
        'regen'          => AD_TAP_REGEN,
        'tap_value'      => adTapValue($u),
        'tap_left'       => adTapsLeft($u),
        'tap_day'        => AD_TAP_DAY,
        'bank'           => adBankInfo($uid),
        'cashout_note'   => adCashoutGate($uid),
        'cashout_left'   => is_finite($cl = min(adDayLeft($u), adSpendLeft($uid, $u))) ? floor($cl) : -1,
    ];
}

function adBankInfo($uid) {
    if (!function_exists('bkOn') || !bkOn() || !function_exists('gmPoints')) return null;
    $vault = (float)bkVaultOf($uid);
    return [
        'wallet' => (float)gmPoints($uid),
        'vault'  => $vault,
        'rate'   => (float)bkRate($vault),
        'level'  => (int)bkLevel($vault),
    ];
}

function adTourSeen($uid) {
    adUserSet($uid, function (&$u) {
        $u['data']['tour'] = AD_TOUR_VER;
    });
    return true;
}


function adLeaderboard($limit = 50) {
    $limit = max(1, min(100, (int)$limit));
    $ck = 'ad_leader_' . $limit;
    $cached = maCacheGet($ck, 20);
    if (is_array($cached)) return $cached;

    $db = adDb();
    if (!$db) return [];
    $season = adSeasonMeta()['season'];
    $stmt = $db->prepare('SELECT id, crystals, data FROM airdrop_users WHERE id != 0 AND season = :s ORDER BY crystals DESC LIMIT :n');
    $stmt->bindValue(':s', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':n', $limit, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $out = [];
    $rank = 1;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode((string)($row['data'] ?? ''), true);
        $d = is_array($d) ? $d : [];
        $out[] = [
            'rank'     => $rank++,
            'uid'      => (int)$row['id'],
            'crystals' => (float)$row['crystals'],
            'name'     => (string)($d['name'] ?? ''),
            'username' => (string)($d['username'] ?? ''),
        ];
    }
    maCachePut($ck, $out);
    return $out;
}

function adTopReferrers($limit = 50) {
    $limit = max(1, min(100, (int)$limit));
    $ck = 'ad_refs_' . $limit;
    $cached = maCacheGet($ck, 45);
    if (is_array($cached)) return $cached;

    if (!function_exists('usersDb')) return [];
    $db = usersDb();
    if (!$db) return [];
    $stmt = $db->prepare(
        "SELECT id, json_extract(data,'$.ref_count') AS rc, data FROM users " .
        "WHERE json_extract(data,'$.ref_count') > 0 ORDER BY rc DESC LIMIT :n"
    );
    $stmt->bindValue(':n', $limit, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $out = [];
    $rank = 1;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode((string)($row['data'] ?? ''), true);
        $d = is_array($d) ? $d : [];
        $name = trim((string)($d['first_name'] ?? '') . ' ' . (string)($d['last_name'] ?? ''));
        $out[] = [
            'rank'     => $rank++,
            'uid'      => (int)$row['id'],
            'count'    => (int)$row['rc'],
            'name'     => $name,
            'username' => (string)($d['username'] ?? ''),
        ];
    }
    maCachePut($ck, $out);
    return $out;
}

function adReferralInfo($uid) {
    $u = function_exists('getUser') ? getUser($uid) : null;
    return [
        'link'       => function_exists('refInviteLink') ? refInviteLink($uid) : '',
        'ref_count'  => (int)($u['ref_count'] ?? 0),
        'ref_earned' => (float)($u['ref_earned'] ?? 0),
        'ref_pending'=> (float)($u['ref_pending'] ?? 0),
        'top'        => adTopReferrers(20),
    ];
}


function adMissionDefs() {
    return [
        ['id' => 'daily',  'name' => 'حضور روزانه',        'reward' => 20,  'kind' => 'daily'],
        ['id' => 'ref1',   'name' => 'دعوت اولین دوست',    'reward' => 60,  'kind' => 'ref',   'need' => 1],
        ['id' => 'ref5',   'name' => 'دعوت ۵ دوست',        'reward' => 300, 'kind' => 'ref',   'need' => 5],
        ['id' => 'order1', 'name' => 'اولین شماره‌ی مجازی', 'reward' => 80,  'kind' => 'order', 'need' => 1],
    ];
}

function adMissions($uid, $u = null) {
    if ($u === null) $u = adUser($uid) ?: ['data' => []];
    $data = $u['data'] ?? [];
    $claimed = (array)($data['claimed'] ?? []);
    $refCount = function_exists('countReferrals') ? countReferrals($uid) : 0;
    $orderCount = class_exists('MaOrder') ? MaOrder::doneCount($uid) : 0;
    $today = gmdate('Y-m-d');

    $out = [];
    foreach (adMissionDefs() as $m) {
        $done = false; $progress = 0; $need = (int)($m['need'] ?? 1);
        if ($m['kind'] === 'daily') {
            $done = true;
            $progress = 1; $need = 1;
        } elseif ($m['kind'] === 'ref') {
            $progress = $refCount; $done = $refCount >= $need;
        } elseif ($m['kind'] === 'order') {
            $progress = $orderCount; $done = $orderCount >= $need;
        }
        $isClaimed = !empty($claimed[$m['id']]) && ($m['kind'] !== 'daily' || $claimed[$m['id']] === $today);
        $out[] = [
            'id' => $m['id'], 'name' => $m['name'], 'reward' => $m['reward'],
            'progress' => min($progress, $need), 'need' => $need,
            'ready' => $done && !$isClaimed, 'claimed' => $isClaimed,
        ];
    }
    return $out;
}

function adClaimMission($uid, $missionId) {
    $defs = [];
    foreach (adMissionDefs() as $m) $defs[$m['id']] = $m;
    if (!isset($defs[$missionId])) return [false, 'ماموریت پیدا نشد.'];

    $reward = 0; $ok = false; $msg = '';
    adUserSet($uid, function (&$u) use ($uid, $missionId, $defs, &$reward, &$ok, &$msg) {
        $m = $defs[$missionId];
        $claimed = (array)($u['data']['claimed'] ?? []);
        $today = gmdate('Y-m-d');

        if ($m['kind'] === 'daily') {
            if (($claimed[$missionId] ?? '') === $today) { $msg = 'امروز قبلا گرفته‌ای.'; return; }
        } elseif (!empty($claimed[$missionId])) { $msg = 'قبلا دریافت شده.'; return; }

        $done = false;
        if ($m['kind'] === 'daily') $done = true;
        elseif ($m['kind'] === 'ref') $done = (function_exists('countReferrals') ? countReferrals($uid) : 0) >= (int)$m['need'];
        elseif ($m['kind'] === 'order') $done = (class_exists('MaOrder') ? MaOrder::doneCount($uid) : 0) >= (int)$m['need'];
        if (!$done) { $msg = 'هنوز شرایطش کامل نشده.'; return; }

        $reward = (float)$m['reward'];
        adGain($u, $reward);
        $claimed[$missionId] = $m['kind'] === 'daily' ? $today : true;
        $u['data']['claimed'] = $claimed;
        $ok = true;
    });
    return $ok ? [true, $reward] : [false, $msg ?: 'دریافت انجام نشد.'];
}


// Free crystals are only convertible to real value by real customers, and only
// up to a daily cap per user and for everyone together. Without this, any number
// of fresh Telegram accounts could mine crystals and drain the wallet.
function adCashoutGate($uid) {
    if (!adOn() || !adCashoutOn()) return 'تبدیلِ کریستال فعلا بسته است.';
    $need = adNeedBuy();
    if ($need > 0) {
        $done = class_exists('MaOrder') ? MaOrder::doneCount($uid) : 0;
        if ($done < $need && function_exists('svPaidCount')) $done += svPaidCount($uid);
        if ($done < $need)
            return 'برای تبدیلِ کریستال باید دست‌کم ' . $need . ' خریدِ تحویل‌شده داشته باشید.';
    }
    return '';
}

// What the user really paid for delivered numbers and finished services.
function adSpent($uid) {
    $sum = 0.0;
    if (function_exists('maOrdersDb') && ($db = maOrdersDb())) {
        $st = $db->prepare("SELECT data FROM orders WHERE user_id = :u AND status = 'done'");
        $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
        $res = $st->execute();
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) {
            $o = json_decode((string)$r['data'], true);
            if (is_array($o) && ($o['pay'] ?? '') !== 'diamond') $sum += max(0.0, (float)($o['total'] ?? 0));
        }
    }
    if (function_exists('svDb') && ($db = svDb())) {
        $st = $db->prepare("SELECT SUM(total - refunded) FROM svo WHERE uid = :u AND status IN ('done', 'partial')");
        $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
        $res = $st->execute();
        $row = $res ? $res->fetchArray(SQLITE3_NUM) : null;
        $sum += max(0.0, (float)($row[0] ?? 0));
    }
    return $sum;
}

function adSpendLeft($uid, $u) {
    if (adSpendPct() <= 0) return INF;
    return max(0.0, adSpent($uid) * adSpendPct() / 100 - (float)($u['data']['co_total'] ?? 0));
}

function adDayLeft($u) {
    if (adDayMax() <= 0) return INF;
    $used = (($u['data']['co_day'] ?? '') === gmdate('Y-m-d')) ? (float)($u['data']['co_sum'] ?? 0) : 0.0;
    return max(0.0, adDayMax() - $used);
}

function adDayNote(&$u, $toman) {
    $day = gmdate('Y-m-d');
    if (($u['data']['co_day'] ?? '') !== $day) { $u['data']['co_day'] = $day; $u['data']['co_sum'] = 0.0; }
    $u['data']['co_sum'] = round((float)$u['data']['co_sum'] + (float)$toman, 2);
    $u['data']['co_total'] = max(0.0, round((float)($u['data']['co_total'] ?? 0) + (float)$toman, 2));
}

function adCapErr($uid, $u, $toman) {
    if ($toman > adDayLeft($u) + 0.001)
        return 'سقفِ تبدیلِ روزانه‌ی شما ' . fmtNum(adDayMax()) . ' تومان است؛ امروز ' .
               fmtNum(floor(adDayLeft($u))) . ' تومان دیگر می‌شود.';
    $left = adSpendLeft($uid, $u);
    if ($toman > $left + 0.001)
        return 'تبدیلِ کریستال تا ' . fmtNum(adSpendPct()) . '٪ِ خریدهای شما مجاز است؛ الان ' .
               fmtNum(floor($left)) . ' تومان دیگر می‌شود. با خریدِ بیشتر، سقف بالا می‌رود.';
    return '';
}

function adGlobalTake($toman) {
    if (adAllDayMax() <= 0) return true;
    $ok = false;
    mutate('ad_cashout_day', function (&$a) use ($toman, &$ok) {
        $day = gmdate('Y-m-d');
        if (($a['day'] ?? '') !== $day) $a = ['day' => $day, 'sum' => 0.0];
        if ((float)$a['sum'] + (float)$toman > adAllDayMax() + 0.001) return;
        $a['sum'] = round((float)$a['sum'] + (float)$toman, 2);
        $ok = true;
    });
    return $ok;
}

function adGlobalGiveBack($toman) {
    if (adAllDayMax() <= 0) return;
    mutate('ad_cashout_day', function (&$a) use ($toman) {
        if (($a['day'] ?? '') === gmdate('Y-m-d')) $a['sum'] = max(0.0, round((float)$a['sum'] - (float)$toman, 2));
    });
}

function adRedeem($uid, $amount) {
    $amount = round((float)$amount, 4);
    if (!is_finite($amount) || $amount < adRedeemMin()) return [false, 'حداقل ' . (int)adRedeemMin() . ' کریستال لازم است.'];
    if (($why = adCashoutGate($uid)) !== '') return [false, $why];

    $toman = round($amount * adRedeemRate(), 2);
    if (!adGlobalTake($toman)) return [false, 'سقفِ تبدیلِ امروز پر شده؛ فردا دوباره امتحان کنید.'];

    $ok = false; $err = 'کریستال کافی نیست.';
    adUserSet($uid, function (&$u) use ($uid, $amount, $toman, &$ok, &$err) {
        if ($u['crystals'] + 0.001 < $amount) return;
        if (($e = adCapErr($uid, $u, $toman)) !== '') { $err = $e; return; }
        $u['crystals'] = round($u['crystals'] - $amount, 4);
        adDayNote($u, $toman);
        $ok = true;
        $log = (array)($u['data']['redeem_log'] ?? []);
        array_unshift($log, ['t' => time(), 'crystals' => $amount, 'toman' => $toman]);
        $u['data']['redeem_log'] = array_slice($log, 0, 20);
    });
    if (!$ok) { adGlobalGiveBack($toman); return [false, $err]; }
    $idem = 'airdrop:' . (int)$uid . ':' . bin2hex(random_bytes(8));
    if (!function_exists('addBalance') || !addBalance($uid, $toman, 'airdrop', 'redeem', $idem)) {
        adUserSet($uid, function (&$u) use ($amount, $toman) {
            $u['crystals'] = round($u['crystals'] + $amount, 4);
            adDayNote($u, -$toman);
        });
        adGlobalGiveBack($toman);
        return [false, 'الان انجام نشد؛ کریستال‌ها برگشت. کمی بعد دوباره امتحان کنید.'];
    }
    return [true, $toman];
}

function adRedeemCoupon($uid, $amount) {
    if (!function_exists('cpUpsert'))
        return [false, 'بخشِ کدِ تخفیف روی سرور نصب نیست.'];

    $amount = round((float)$amount, 4);
    if (!is_finite($amount) || $amount < adCouponMin())
        return [false, 'حداقل ' . (int)adCouponMin() . ' کریستال لازم است.'];
    if (($why = adCashoutGate($uid)) !== '') return [false, $why];

    $toman = round($amount * adRedeemRate(), 0);
    if ($toman <= 0) return [false, 'مبلغِ کد صفر می‌شود.'];

    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $try = 'AIR' . strtoupper(bin2hex(random_bytes(5)));
        if (cpGet($try) === null) { $code = $try; break; }
    }
    if ($code === '') return [false, 'ساختِ کد ناموفق بود؛ دوباره امتحان کنید.'];
    if (!adGlobalTake($toman)) return [false, 'سقفِ تبدیلِ امروز پر شده؛ فردا دوباره امتحان کنید.'];

    $ok = false; $err = 'کریستال کافی نیست.';
    adUserSet($uid, function (&$u) use ($uid, $amount, $toman, &$ok, &$err) {
        if ($u['crystals'] + 0.001 < $amount) return;
        if (($e = adCapErr($uid, $u, $toman)) !== '') { $err = $e; return; }
        $u['crystals'] = round($u['crystals'] - $amount, 4);
        adDayNote($u, $toman);
        $ok = true;
    });
    if (!$ok) { adGlobalGiveBack($toman); return [false, $err]; }

    $exp = time() + AD_COUPON_DAYS * 86400;
    $made = cpUpsert($code, [
        'kind' => 'fixed', 'value' => $toman,
        'max_uses' => 1, 'used' => 0, 'per_user_limit' => 1,
        'min_total' => $toman,
        'max_discount' => $toman,
        'expires_at' => $exp, 'on_flag' => 1, 'created_at' => time(),
        'owner' => (int)$uid,
    ]);
    if (!$made) {
        adUserSet($uid, function (&$u) use ($amount, $toman) {
            $u['crystals'] = round($u['crystals'] + $amount, 4);
            adDayNote($u, -$toman);
        });
        adGlobalGiveBack($toman);
        return [false, 'کد ساخته نشد؛ کریستال برگشت.'];
    }

    adUserSet($uid, function (&$u) use ($amount, $toman, $code, $exp) {
        $log = (array)($u['data']['coupon_log'] ?? []);
        array_unshift($log, ['t' => time(), 'crystals' => $amount,
                             'toman' => $toman, 'code' => $code, 'exp' => $exp]);
        $u['data']['coupon_log'] = array_slice($log, 0, 20);
    });

    return [true, ['code' => $code, 'toman' => $toman, 'expires_at' => $exp]];
}

function adCouponLog($uid, $limit = 20) {
    $u   = adUser($uid);
    $log = (array)($u['data']['coupon_log'] ?? []);
    $out = [];
    foreach (array_slice($log, 0, max(1, min(20, (int)$limit))) as $r) {
        $c = function_exists('cpGet') ? cpGet((string)($r['code'] ?? '')) : null;
        $used = $c ? ((int)$c['used'] > 0) : false;
        $dead = $used || !$c || empty($c['on_flag'])
             || ((int)($r['exp'] ?? 0) > 0 && time() > (int)$r['exp']);
        $out[] = $r + ['used' => $used, 'active' => !$dead];
    }
    return $out;
}

function adRedeemLog($uid, $limit = 20) {
    $u = adUser($uid);
    $log = (array)($u['data']['redeem_log'] ?? []);
    return array_slice($log, 0, max(1, min(20, (int)$limit)));
}


function adBuyBoost($uid) {
    if (!function_exists('debitBalance') || !function_exists('addBalance'))
        return [false, 'خرید در دسترس نیست.'];

    $u = adUser($uid);
    if ($u && adBoostN($u) >= AD_BOOST_MAX) return [false, 'به سقفِ افزایشِ سرعت رسیده‌ای.'];
    if (!debitBalance($uid, AD_BOOST_PRICE, 'purchase', 'boost')) return [false, 'موجودی کیف‌پول کافی نیست.'];

    $n = 0; $atCap = false;
    adUserSet($uid, function (&$u) use (&$n, &$atCap) {
        $cur = adBoostN($u);
        if ($cur >= AD_BOOST_MAX) { $atCap = true; $n = $cur; return; }
        $n = $cur + 1;
        $u['data']['boost_n'] = $n;
    });
    if ($atCap) {
        addBalance($uid, AD_BOOST_PRICE, 'rollback', 'boost');
        return [false, 'به سقفِ افزایشِ سرعت رسیده‌ای.'];
    }
    return [true, $n];
}


// ─────────────────────────────────────────────────────────────────────────────
//  Admin panel for the airdrop section (reachable from /panel ← 🎮 بازی‌ها ← 🎁 ایردراپ)
// ─────────────────────────────────────────────────────────────────────────────

function adAdminHome($chatId, $msgId = null) {
    $rate = adRedeemRate();
    $t  = "🎁 <b>ایردراپ (کریستال)</b>\n\n";
    $t .= 'وضعیتِ کلی: ' . (adOn() ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= 'تبدیلِ کریستال به پول: ' . (adCashoutOn() ? '✅ روشن' : '❌ خاموش') . "\n\n";
    $t .= '💱 قیمتِ هر کریستال: <b>' . rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . "</b> تومان\n";
    $t .= '🔽 حداقل برای تبدیل به پول: <b>' . fmtNum(adRedeemMin()) . "</b> کریستال\n";
    $t .= '🏷 حداقل برای کدِ تخفیف: <b>' . fmtNum(adCouponMin()) . "</b> کریستال\n\n";
    $t .= "<b>محدودیت‌های ضدِ تقلب</b>\n";
    $t .= '🛒 حداقل خریدِ لازم پیش از تبدیل: <b>' . adNeedBuy() . "</b>\n";
    $t .= '👤 سقفِ روزانه‌ی هر کاربر: <b>' . (adDayMax() > 0 ? fmtNum(adDayMax()) . ' تومان' : 'بی‌سقف') . "</b>\n";
    $t .= '🌍 سقفِ روزانه‌ی همه: <b>' . (adAllDayMax() > 0 ? fmtNum(adAllDayMax()) . ' تومان' : 'بی‌سقف') . "</b>\n";
    $t .= '📊 سقفِ کل = درصدی از خریدِ کاربر: <b>' . (adSpendPct() > 0 ? rtrim(rtrim(number_format(adSpendPct(), 2, '.', ''), '0'), '.') . '٪' : 'بی‌سقف') . "</b>\n\n";
    $t .= '⛏ سرعتِ پایه‌ی جمع‌شدن (سطح ۱): <b>' . rtrim(rtrim(number_format(adBaseRate(), 2, '.', ''), '0'), '.') . "</b> در ساعت\n\n";
    $t .= "💡 هر کریستال الان <b>" . rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.') . "</b> تومان می‌ارزد.";

    $rows = [
        [btnCb(adOn() ? '✅ ایردراپ روشن' : '❌ ایردراپ خاموش', 'adadm_on', 'info'),
         btnCb(adCashoutOn() ? '✅ تبدیل روشن' : '❌ تبدیل خاموش', 'adadm_con', 'info')],
        [btnCb('💱 قیمتِ هر کریستال', 'adadm_e_rate', 'admin')],
        [btnCb('🔽 حداقلِ تبدیل', 'adadm_e_rmin', 'admin'), btnCb('🏷 حداقلِ کدِ تخفیف', 'adadm_e_cmin', 'admin')],
        [btnCb('🛒 حداقل خریدِ لازم', 'adadm_e_need', 'admin')],
        [btnCb('👤 سقفِ روزانه‌ی هر کاربر', 'adadm_e_day', 'admin')],
        [btnCb('🌍 سقفِ روزانه‌ی همه', 'adadm_e_all', 'admin')],
        [btnCb('📊 درصدِ سقفِ کل', 'adadm_e_pct', 'admin'), btnCb('⛏ سرعتِ جمع‌شدن', 'adadm_e_base', 'admin')],
        [btnCb('♻️ بازگردانی به پیش‌فرض', 'adadm_reset', 'reject')],
        [btnCb(UT('back'), 'ag_games', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

// field key => [state, prompt, kind]  (kind: 'float' | 'int' | 'pct')
function adAdmFields() {
    return [
        'rate' => ['redeem_rate',       "💱 قیمتِ هر کریستال به تومان را بفرست (مثلاً <code>50</code>؛ <code>0</code> یعنی تبدیل عملاً بی‌ارزش).", 'float'],
        'rmin' => ['redeem_min',        "🔽 حداقل کریستال برای تبدیل به پول را بفرست (مثلاً <code>100</code>).", 'float'],
        'cmin' => ['coupon_min',        "🏷 حداقل کریستال برای ساختِ کدِ تخفیف را بفرست (مثلاً <code>200</code>).", 'float'],
        'need' => ['cashout_need_buy',  "🛒 چند خریدِ تحویل‌شده لازم باشد تا کاربر بتواند تبدیل کند؟ (<code>0</code> = بدونِ شرط).", 'int'],
        'day'  => ['cashout_day_max',   "👤 سقفِ تبدیلِ روزانه‌ی هر کاربر به تومان (<code>0</code> = بی‌سقف).", 'float'],
        'all'  => ['cashout_all_day_max',"🌍 سقفِ تبدیلِ روزانه‌ی همه‌ی کاربران به تومان (<code>0</code> = بی‌سقف).", 'float'],
        'pct'  => ['cashout_spend_pct', "📊 کلِ تبدیل‌های هر کاربر حداکثر چند درصدِ خریدِ واقعی‌اش باشد؟ (<code>0</code> = بی‌سقف).", 'pct'],
        'base' => ['base_rate',         "⛏ سرعتِ پایه‌ی جمع‌شدنِ کریستال در ساعت (سطح ۱) را بفرست (مثلاً <code>30</code>).", 'float'],
    ];
}

function adAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'adadm')) return false;
    $uid = admStateUid($chatId);

    if ($data === 'adadm_home') { answerCb(BOT_TOKEN, $cbId); clearState($uid); adAdminHome($chatId, $msgId); return true; }

    if ($data === 'adadm_on') {
        $now = !adOn();
        adSet(function (&$c) use ($now) { $c['on'] = $now ? 1 : 0; });
        answerCb(BOT_TOKEN, $cbId, $now ? '✅ روشن' : '❌ خاموش');
        adAdminHome($chatId, $msgId);
        return true;
    }
    if ($data === 'adadm_con') {
        $now = !adCashoutOn();
        adSet(function (&$c) use ($now) { $c['cashout_on'] = $now ? 1 : 0; });
        answerCb(BOT_TOKEN, $cbId, $now ? '✅ روشن' : '❌ خاموش');
        adAdminHome($chatId, $msgId);
        return true;
    }
    if ($data === 'adadm_reset') {
        adSet(function (&$c) {
            foreach (['on','cashout_on','redeem_rate','redeem_min','coupon_min','cashout_need_buy',
                      'cashout_day_max','cashout_all_day_max','cashout_spend_pct','base_rate'] as $k) unset($c[$k]);
        });
        answerCb(BOT_TOKEN, $cbId, '♻️ به پیش‌فرض برگشت');
        adAdminHome($chatId, $msgId);
        return true;
    }
    if (preg_match('/^adadm_e_(\w+)$/', (string)$data, $m) && isset(adAdmFields()[$m[1]])) {
        [$key, $prompt, ] = adAdmFields()[$m[1]];
        answerCb(BOT_TOKEN, $cbId);
        setState($uid, 'ad_set', ['f' => $m[1]]);
        sendMsg(BOT_TOKEN, $chatId, $prompt, inlineKb([[btnCb(UT('cancel'), 'adadm_home', 'cancel')]]));
        return true;
    }

    answerCb(BOT_TOKEN, $cbId);
    return true;
}

function adStateHandle($action, $msg, $uid, $chatId) {
    if ($action !== 'ad_set') return false;
    if (!isAdmin($uid)) { clearState($uid); return true; }
    $sd = (array)(getState($uid)['data'] ?? []);
    $f  = (string)($sd['f'] ?? '');
    $fields = adAdmFields();
    if (!isset($fields[$f])) { clearState($uid); return true; }
    [$key, , $kind] = $fields[$f];

    $raw = trim((string)($msg['text'] ?? ''));
    $num = function_exists('maNum') ? maNum($raw) : (float)preg_replace('/[^0-9.]/', '', $raw);
    $back = inlineKb([[btnCb('🎁 ایردراپ', 'adadm_home', 'admin')]]);

    if ($raw === '' || !is_finite($num) || $num < 0) {
        sendMsg(BOT_TOKEN, $chatId, '⚠️ یک عددِ معتبر بفرست.', $back);
        return true;
    }
    if ($kind === 'int') $num = (int)round($num);
    if ($kind === 'pct') $num = max(0.0, min(1000.0, (float)$num));

    adSet(function (&$c) use ($key, $num) { $c[$key] = $num; });
    clearState($uid);
    sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.', $back);
    adAdminHome($chatId, 0);
    return true;
}
