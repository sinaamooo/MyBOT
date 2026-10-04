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
    return AD_BASE_RATE * max(1, (int)$level) * (1 + AD_BOOST_STEP * max(0, (int)$boostN));
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
        'redeem_rate'=> AD_REDEEM_RATE,
        'redeem_min' => AD_REDEEM_MIN,
        'coupon_min' => AD_COUPON_MIN,
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


function adRedeem($uid, $amount) {
    $amount = round((float)$amount, 4);
    if ($amount < AD_REDEEM_MIN) return [false, 'حداقل ' . (int)AD_REDEEM_MIN . ' کریستال لازم است.'];

    $ok = false; $toman = 0.0;
    adUserSet($uid, function (&$u) use ($amount, &$ok, &$toman) {
        if ($u['crystals'] + 0.001 < $amount) return;
        $u['crystals'] = round($u['crystals'] - $amount, 4);
        $toman = round($amount * AD_REDEEM_RATE, 2);
        $ok = true;
        $log = (array)($u['data']['redeem_log'] ?? []);
        array_unshift($log, ['t' => time(), 'crystals' => $amount, 'toman' => $toman]);
        $u['data']['redeem_log'] = array_slice($log, 0, 20);
    });
    if (!$ok) return [false, 'کریستال کافی نیست.'];
    if (function_exists('addBalance')) addBalance($uid, $toman, 'airdrop', 'redeem');
    return [true, $toman];
}

function adRedeemCoupon($uid, $amount) {
    if (!function_exists('cpUpsert'))
        return [false, 'بخشِ کدِ تخفیف روی سرور نصب نیست.'];

    $amount = round((float)$amount, 4);
    if ($amount < AD_COUPON_MIN)
        return [false, 'حداقل ' . (int)AD_COUPON_MIN . ' کریستال لازم است.'];

    $toman = round($amount * AD_REDEEM_RATE, 0);
    if ($toman <= 0) return [false, 'مبلغِ کد صفر می‌شود.'];

    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $try = 'AIR' . strtoupper(bin2hex(random_bytes(3)));
        if (cpGet($try) === null) { $code = $try; break; }
    }
    if ($code === '') return [false, 'ساختِ کد ناموفق بود؛ دوباره امتحان کنید.'];

    $ok = false;
    adUserSet($uid, function (&$u) use ($amount, &$ok) {
        if ($u['crystals'] + 0.001 < $amount) return;
        $u['crystals'] = round($u['crystals'] - $amount, 4);
        $ok = true;
    });
    if (!$ok) return [false, 'کریستال کافی نیست.'];

    $exp = time() + AD_COUPON_DAYS * 86400;
    $made = cpUpsert($code, [
        'kind' => 'fixed', 'value' => $toman,
        'max_uses' => 1, 'used' => 0, 'per_user_limit' => 1,
        'min_total' => $toman,
        'max_discount' => $toman,
        'expires_at' => $exp, 'on_flag' => 1, 'created_at' => time(),
    ]);
    if (!$made) {
        adUserSet($uid, function (&$u) use ($amount) {
            $u['crystals'] = round($u['crystals'] + $amount, 4);
        });
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
