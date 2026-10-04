<?php
defined('NB_ROOT') || exit;

if (!defined('QZ_LIB')) define('QZ_LIB', 1);


function qzDefaults() {
    return [
        'on'        => true,
        'min_members' => 10,
        'hour'      => 21,
        'minute'    => 0,
        'tz_offset' => 12600,
        'duration'  => 3600,
        'reward'    => 500,
        'reward2'   => 0,
        'reward3'   => 0,
        'one_try'   => 1,
        'pin'       => 0,
        'reveal'    => 1,

        'icons' => ['btn_o1' => '', 'btn_o2' => '', 'btn_o3' => ''],
        'btns'  => [
            'btn_o1' => ['color' => 'primary'],
            'btn_o2' => ['color' => 'primary'],
            'btn_o3' => ['color' => 'primary'],
        ],

        'texts' => [
            'btn_o1' => '1️⃣ گزینه یک',
            'btn_o2' => '2️⃣ گزینه دو',
            'btn_o3' => '3️⃣ گزینه سه',

            'card' => "🧠 <b>چالش روزانه</b>\n\n" .
                      "{question}\n\n" .
                      "1️⃣ {o1}\n2️⃣ {o2}\n3️⃣ {o3}\n\n" .
                      "🏆 جایزه‌ی نفرِ اول: <b>{reward}</b> الماس\n" .
                      "⏳ مهلت: <b>{mins}</b> دقیقه",

            'won'  => "🏆 <b>چالش تمام شد</b>\n\n" .
                      "{question}\n\n" .
                      "✅ جوابِ درست: <b>{answer}</b>\n\n" .
                      "🥇 برنده: {winner}\n💎 جایزه: <b>{reward}</b> الماس\n" .
                      "⚡️ در <b>{secs}</b> ثانیه",

            'timeout' => "⌛️ <b>مهلتِ چالش تمام شد</b>\n\n" .
                         "{question}\n\n" .
                         "✅ جوابِ درست: <b>{answer}</b>\n\n" .
                         "😔 کسی درست جواب نداد.",

            'runner_up' => "\n🥈 {name} — <b>{amount}</b> الماس",
            'third'     => "\n🥉 {name} — <b>{amount}</b> الماس",

            'pop_right'  => '✅ درست بود! جایزه‌ات ثبت شد.',
            'pop_wrong'  => '❌ اشتباه بود.',
            'pop_late'   => '⌛️ دیر رسیدی — این چالش تمام شده.',
            'pop_twice'  => '🔒 قبلا جواب داده‌ای.',
            'pop_closed' => '🏁 این چالش بسته شده.',
            'pop_daily'  => '🏅 امروز یک‌بار برنده شده‌ای — فردا دوباره شانست را امتحان کن!',
        ],
    ];
}

function qzCfg() {
    $c = cfg()['quiz'] ?? null;
    return is_array($c) ? array_replace_recursive(qzDefaults(), $c) : qzDefaults();
}
function qzSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['quiz'] ?? null)) $c['quiz'] = qzDefaults();
        $fn($c['quiz']);
    });
}
function qzVal($path, $default = null) {
    $v = qzCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}
function qzOn() { return !empty(qzVal('on')); }

function qzBtnKeys() { return ['btn_o1', 'btn_o2', 'btn_o3']; }
function qzIsButtonKey($slug) { return in_array($slug, qzBtnKeys(), true); }

function qzT($slug, $vars = []) {
    $t = (string)qzVal('texts.' . $slug, qzDefaults()['texts'][$slug] ?? $slug);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function qzBtn($key, $vars, $data) {
    $b = ['callback_data' => $data];
    $color = (string)qzVal('btns.' . $key . '.color', '');
    if (function_exists('isStyle') && isStyle($color)) $b['style'] = $color;
    $b = function_exists('btnApplyLabel')
        ? btnApplyLabel($b, qzT($key, $vars), qzVal('icons.' . $key, ''))
        : ['text' => strip_tags((string)(qzT($key, $vars)))] + $b;
    return $b;
}

function qzNum($n) { return number_format((float)$n, 0, '.', ','); }

function qzNowLocal() { return time() + (int)qzVal('tz_offset', 12600); }
function qzTodayKey()  { return gmdate('Y-m-d', qzNowLocal()); }

function qzLabels() {
    return [
        'btn_o1' => 'دکمه: گزینه یک', 'btn_o2' => 'دکمه: گزینه دو', 'btn_o3' => 'دکمه: گزینه سه',
        'card'      => 'کارتِ چالش (لحظه‌ی ارسال)',
        'won'       => 'پایان — یک نفر برد',
        'timeout'   => 'پایان — مهلت تمام شد',
        'runner_up' => 'خطِ نفرِ دوم',
        'third'     => 'خطِ نفرِ سوم',
        'pop_right'  => 'پاپ‌آپ — جوابِ درست',
        'pop_wrong'  => 'پاپ‌آپ — جوابِ غلط',
        'pop_late'   => 'پاپ‌آپ — دیر رسیدی',
        'pop_twice'  => 'پاپ‌آپ — قبلا جواب داده‌ای',
        'pop_closed' => 'پاپ‌آپ — چالش بسته است',
        'pop_daily'  => 'پاپ‌آپ — امروز قبلا برنده شده',
    ];
}
function qzLabel($k) { return qzLabels()[$k] ?? $k; }


function quizDbPath() { return DATA_DIR . '/quiz.sqlite'; }

function quizDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;

    $dir = dirname(quizDbPath());
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    try {
        $db = nbRawOpen(quizDbPath());
    } catch (Throwable $e) {
        error_log('[quiz] quiz.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS quiz_bank (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        q TEXT NOT NULL, o1 TEXT NOT NULL, o2 TEXT NOT NULL, o3 TEXT NOT NULL,
        correct INTEGER NOT NULL, cat TEXT DEFAULT "", used_at INTEGER DEFAULT 0)');
    $db->exec('CREATE INDEX IF NOT EXISTS quiz_bank_used ON quiz_bank (used_at)');
    $db->exec('CREATE TABLE IF NOT EXISTS quiz_rounds (
        id TEXT PRIMARY KEY, day TEXT, data TEXT, status TEXT, ends INTEGER)');
    $db->exec('CREATE INDEX IF NOT EXISTS quiz_rounds_day ON quiz_rounds (day)');
    $db->exec('CREATE INDEX IF NOT EXISTS quiz_rounds_status ON quiz_rounds (status)');
    $db->exec('CREATE TABLE IF NOT EXISTS quiz_groups (
        chat INTEGER PRIMARY KEY, title TEXT DEFAULT "", added INTEGER DEFAULT 0, dead_at INTEGER DEFAULT 0)');
    $db->exec('CREATE TABLE IF NOT EXISTS quiz_sent (
        chat INTEGER NOT NULL, day TEXT NOT NULL, st TEXT DEFAULT "", PRIMARY KEY (chat, day))');
    $db->exec('CREATE TABLE IF NOT EXISTS quiz_wins (
        uid INTEGER NOT NULL, day TEXT NOT NULL, PRIMARY KEY (uid, day))');
    $db->exec('CREATE INDEX IF NOT EXISTS quiz_sent_day ON quiz_sent (day, st)');
    $db->exec('CREATE INDEX IF NOT EXISTS quiz_groups_live ON quiz_groups (dead_at, added)');
    qzSeedBank($db);
    return $db;
}

function qzBankCount($onlyFresh = false) {
    $db = quizDb();
    if (!$db) return 0;
    $sql = 'SELECT COUNT(*) c FROM quiz_bank' . ($onlyFresh ? ' WHERE used_at = 0' : '');
    $r = $db->query($sql)->fetchArray(SQLITE3_ASSOC);
    return (int)($r['c'] ?? 0);
}

function qzPickQuestion() {
    $db = quizDb();
    if ($db && qzBankCount() > 0 && mt_rand(1, 3) > 1) {
        if (qzBankCount(true) === 0) $db->exec('UPDATE quiz_bank SET used_at = 0');
        $row = $db->query('SELECT * FROM quiz_bank WHERE used_at = 0 ORDER BY RANDOM() LIMIT 1')
                  ->fetchArray(SQLITE3_ASSOC);
        if ($row) return $row;
    }
    return qzMakeQuestion();
}

function qzFa($n) {
    return strtr((string)$n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                              '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function qzNear($ans, $spread) {
    $out = [];
    while (count($out) < 2) {
        $v = $ans + mt_rand(1, max(2, (int)$spread)) * (mt_rand(0, 1) ? 1 : -1);
        if ($v > 0 && $v !== $ans && !in_array($v, $out, true)) $out[] = $v;
    }
    return $out;
}

function qzCapitals() {
    return [
        'فرانسه' => 'پاریس', 'آلمان' => 'برلین', 'ایتالیا' => 'رم', 'اسپانیا' => 'مادرید',
        'پرتغال' => 'لیسبون', 'انگلستان' => 'لندن', 'روسیه' => 'مسکو', 'ترکیه' => 'آنکارا',
        'عراق' => 'بغداد', 'افغانستان' => 'کابل', 'پاکستان' => 'اسلام‌آباد', 'هند' => 'دهلی نو',
        'چین' => 'پکن', 'ژاپن' => 'توکیو', 'کره جنوبی' => 'سئول', 'مصر' => 'قاهره',
        'عربستان سعودی' => 'ریاض', 'امارات' => 'ابوظبی', 'قطر' => 'دوحه', 'عمان' => 'مسقط',
        'سوریه' => 'دمشق', 'لبنان' => 'بیروت', 'اردن' => 'امان', 'آذربایجان' => 'باکو',
        'ارمنستان' => 'ایروان', 'گرجستان' => 'تفلیس', 'ترکمنستان' => 'عشق‌آباد', 'ازبکستان' => 'تاشکند',
        'تاجیکستان' => 'دوشنبه', 'قزاقستان' => 'آستانه', 'قرقیزستان' => 'بیشکک', 'کانادا' => 'اتاوا',
        'آمریکا' => 'واشنگتن', 'مکزیک' => 'مکزیکوسیتی', 'برزیل' => 'برازیلیا', 'آرژانتین' => 'بوئنوس آیرس',
        'شیلی' => 'سانتیاگو', 'پرو' => 'لیما', 'کلمبیا' => 'بوگوتا', 'استرالیا' => 'کانبرا',
        'نیوزیلند' => 'ولینگتون', 'اندونزی' => 'جاکارتا', 'مالزی' => 'کوالالامپور', 'تایلند' => 'بانکوک',
        'ویتنام' => 'هانوی', 'فیلیپین' => 'مانیل', 'سوئد' => 'استکهلم', 'نروژ' => 'اسلو',
        'فنلاند' => 'هلسینکی', 'دانمارک' => 'کپنهاگ', 'هلند' => 'آمستردام', 'بلژیک' => 'بروکسل',
        'سوئیس' => 'برن', 'اتریش' => 'وین', 'لهستان' => 'ورشو', 'یونان' => 'آتن',
        'اوکراین' => 'کی‌یف', 'مجارستان' => 'بوداپست', 'چک' => 'پراگ', 'ایرلند' => 'دوبلین',
        'نیجریه' => 'ابوجا', 'کنیا' => 'نایروبی', 'مراکش' => 'رباط', 'بنگلادش' => 'داکا',
    ];
}

function qzMonies() {
    return [
        'ژاپن' => 'ین', 'انگلستان' => 'پوند', 'روسیه' => 'روبل', 'هند' => 'روپیه', 'چین' => 'یوان',
        'ترکیه' => 'لیر', 'امارات' => 'درهم', 'آمریکا' => 'دلار', 'کره جنوبی' => 'وون',
        'سوئیس' => 'فرانک', 'برزیل' => 'رئال', 'آفریقای جنوبی' => 'رند', 'لهستان' => 'زلوتی',
        'تایلند' => 'بات', 'ویتنام' => 'دانگ', 'مکزیک' => 'پزو', 'مالزی' => 'رینگیت',
        'سوئد' => 'کرون', 'آذربایجان' => 'منات', 'ارمنستان' => 'درام', 'گرجستان' => 'لاری',
        'عراق' => 'دینار', 'افغانستان' => 'افغانی', 'اوکراین' => 'گریونا', 'مجارستان' => 'فورینت',
        'عربستان سعودی' => 'ریال',
    ];
}

function qzMakeQuestion() {
    $k = mt_rand(1, 10);
    if ($k <= 5) {
        $t = mt_rand(1, 6);
        if ($t === 1)     { $a = mt_rand(12, 99); $b = mt_rand(12, 99); $ans = $a + $b; $sp = 10;
                            $q = 'حاصلِ ' . qzFa($a) . ' + ' . qzFa($b) . ' چند می‌شود؟'; }
        elseif ($t === 2) { $a = mt_rand(60, 199); $b = mt_rand(11, $a - 11); $ans = $a - $b; $sp = 10;
                            $q = 'حاصلِ ' . qzFa($a) . ' − ' . qzFa($b) . ' چند می‌شود؟'; }
        elseif ($t === 3) { $a = mt_rand(4, 19); $b = mt_rand(4, 19); $ans = $a * $b; $sp = max($a, $b);
                            $q = 'حاصلِ ' . qzFa($a) . ' × ' . qzFa($b) . ' چند می‌شود؟'; }
        elseif ($t === 4) { $b = mt_rand(3, 12); $ans = mt_rand(4, 25); $a = $ans * $b; $sp = 4;
                            $q = 'حاصلِ ' . qzFa($a) . ' ÷ ' . qzFa($b) . ' چند می‌شود؟'; }
        elseif ($t === 5) { $p = [10, 20, 25, 50, 75][mt_rand(0, 4)]; $base = mt_rand(2, 40) * 20;
                            $ans = intdiv($base * $p, 100); $sp = max(5, intdiv($ans, 4));
                            $q = qzFa($p) . ' درصدِ ' . qzFa($base) . ' چند می‌شود؟'; }
        else              { $s = mt_rand(1, 20); $d = mt_rand(2, 9); $ans = $s + 4 * $d; $sp = $d;
                            $q = 'عددِ بعدیِ این دنباله چیست؟' . "\n" .
                                 implode('، ', array_map('qzFa', [$s, $s + $d, $s + 2 * $d, $s + 3 * $d])) . '، ؟'; }
        $o = array_map('qzFa', array_merge([$ans], qzNear($ans, $sp)));
        return ['id' => 0, 'q' => $q, 'o1' => $o[0], 'o2' => $o[1], 'o3' => $o[2], 'correct' => 1, 'cat' => 'ریاضی'];
    }
    $cap   = $k <= 8;
    $pairs = $cap ? qzCapitals() : qzMonies();
    $keys  = array_keys($pairs);
    $c     = $keys[mt_rand(0, count($keys) - 1)];
    $ans   = $pairs[$c];
    $pool  = array_values(array_unique(array_diff(array_values($pairs), [$ans])));
    shuffle($pool);
    return [
        'id' => 0, 'q' => $cap ? "پایتختِ {$c} کدام شهر است؟" : "واحدِ پولِ {$c} چیست؟",
        'o1' => $ans, 'o2' => $pool[0], 'o3' => $pool[1], 'correct' => 1,
        'cat' => $cap ? 'پایتخت‌ها' : 'واحد پول',
    ];
}

function qzShuffle($q) {
    $o = [(string)$q['o1'], (string)$q['o2'], (string)$q['o3']];
    $right = $o[max(1, min(3, (int)$q['correct'])) - 1];
    shuffle($o);
    return [$o, (int)array_search($right, $o, true) + 1];
}

function qzMarkUsed($qid) {
    $db = quizDb();
    if (!$db) return;
    $st = $db->prepare('UPDATE quiz_bank SET used_at = :t WHERE id = :id');
    $st->bindValue(':t', time(), SQLITE3_INTEGER);
    $st->bindValue(':id', (int)$qid, SQLITE3_INTEGER);
    $st->execute();
}

function qzBankAdd($q, $o1, $o2, $o3, $correct, $cat = '') {
    $db = quizDb();
    if (!$db) return false;
    $st = $db->prepare('INSERT INTO quiz_bank (q, o1, o2, o3, correct, cat, used_at)
                        VALUES (:q, :a, :b, :c, :k, :cat, 0)');
    foreach ([':q' => $q, ':a' => $o1, ':b' => $o2, ':c' => $o3, ':cat' => $cat] as $k => $v)
        $st->bindValue($k, (string)$v, SQLITE3_TEXT);
    $st->bindValue(':k', max(1, min(3, (int)$correct)), SQLITE3_INTEGER);
    return (bool)$st->execute();
}

function qzBankDelete($id) {
    $db = quizDb();
    if (!$db) return false;
    $st = $db->prepare('DELETE FROM quiz_bank WHERE id = :id');
    $st->bindValue(':id', (int)$id, SQLITE3_INTEGER);
    return (bool)$st->execute();
}

function qzBankPage($page = 0, $per = 8) {
    $db = quizDb();
    if (!$db) return [];
    $st = $db->prepare('SELECT * FROM quiz_bank ORDER BY id LIMIT :l OFFSET :o');
    $st->bindValue(':l', max(1, (int)$per), SQLITE3_INTEGER);
    $st->bindValue(':o', max(0, (int)$page * (int)$per), SQLITE3_INTEGER);
    $out = []; $res = $st->execute();
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $out[] = $r;
    return $out;
}


function qzRoundGet($id) {
    $db = quizDb();
    if (!$db) return null;
    $st = $db->prepare('SELECT data FROM quiz_rounds WHERE id = :id');
    $st->bindValue(':id', (string)$id, SQLITE3_TEXT);
    $row = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function qzRoundCreate(array $d) {
    $db = quizDb();
    if (!$db) return null;
    $id = 'q' . bin2hex(random_bytes(5));
    $d['id'] = $id;
    $st = $db->prepare('INSERT INTO quiz_rounds (id, day, data, status, ends)
                        VALUES (:id, :day, :data, :st, :ends)');
    $st->bindValue(':id', $id, SQLITE3_TEXT);
    $st->bindValue(':day', (string)($d['day'] ?? ''), SQLITE3_TEXT);
    $st->bindValue(':data', json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
    $st->bindValue(':st', (string)($d['status'] ?? 'open'), SQLITE3_TEXT);
    $st->bindValue(':ends', (int)($d['ends'] ?? 0), SQLITE3_INTEGER);
    $st->execute();
    return $id;
}

function qzRoundSet($id, callable $fn) {
    $db = quizDb();
    if (!$db) return null;
    $id = (string)$id;
    if (!@$db->exec('BEGIN IMMEDIATE')) return false;
    try {
        $st = $db->prepare('SELECT data FROM quiz_rounds WHERE id = :id');
        $st->bindValue(':id', $id, SQLITE3_TEXT);
        $row = $st->execute()->fetchArray(SQLITE3_ASSOC);
        $d = $row ? json_decode($row['data'], true) : null;
        if (!is_array($d)) { $db->exec('ROLLBACK'); return false; }

        $result = $fn($d);

        $up = $db->prepare('UPDATE quiz_rounds SET data = :data, status = :st, ends = :ends WHERE id = :id');
        $up->bindValue(':id', $id, SQLITE3_TEXT);
        $up->bindValue(':data', json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $up->bindValue(':st', (string)($d['status'] ?? 'open'), SQLITE3_TEXT);
        $up->bindValue(':ends', (int)($d['ends'] ?? 0), SQLITE3_INTEGER);
        if (!$up->execute()) throw new RuntimeException('write');
        if (!@$db->exec('COMMIT')) throw new RuntimeException('commit');
        return $result;
    } catch (Throwable $e) {
        @$db->exec('ROLLBACK');
        error_log('[quiz] qzRoundSet خطا: ' . $e->getMessage());
        return false;
    }
}

function qzSentCount($day) {
    $db = quizDb();
    if (!$db) return 0;
    $st = $db->prepare("SELECT COUNT(*) c FROM quiz_sent WHERE day = :d AND st = 'ok'");
    $st->bindValue(':d', (string)$day, SQLITE3_TEXT);
    $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
    return (int)($r['c'] ?? 0);
}

function qzGroupCount() {
    $db = quizDb();
    if (!$db) return 0;
    $r = $db->query('SELECT COUNT(*) c FROM quiz_groups WHERE dead_at = 0')->fetchArray(SQLITE3_ASSOC);
    return (int)($r['c'] ?? 0);
}

function qzGroupPut($chat, $title = '', $dead = false) {
    $chat = (int)$chat;
    $db = quizDb();
    if ($chat >= 0 || !$db) return;
    $st = $db->prepare('INSERT OR IGNORE INTO quiz_groups (chat, title, added, dead_at) VALUES (:c, :t, :a, :d)');
    $st->bindValue(':c', $chat, SQLITE3_INTEGER);
    $st->bindValue(':t', (string)$title, SQLITE3_TEXT);
    $st->bindValue(':a', time(), SQLITE3_INTEGER);
    $st->bindValue(':d', $dead ? time() : 0, SQLITE3_INTEGER);
    $st->execute();
    if ($db->changes()) return;
    $up = $db->prepare('UPDATE quiz_groups SET dead_at = :d, title = CASE WHEN :t = "" THEN title ELSE :t END WHERE chat = :c');
    $up->bindValue(':c', $chat, SQLITE3_INTEGER);
    $up->bindValue(':t', (string)$title, SQLITE3_TEXT);
    $up->bindValue(':d', $dead ? time() : 0, SQLITE3_INTEGER);
    $up->execute();
}

function qzGroupMember($m) {
    $chat = (array)($m['chat'] ?? []);
    if (!in_array($chat['type'] ?? '', ['group', 'supergroup'], true)) return;
    $s  = (array)($m['new_chat_member'] ?? []);
    $st = (string)($s['status'] ?? '');
    $in = in_array($st, ['member', 'administrator', 'creator'], true)
       || ($st === 'restricted' && !empty($s['is_member']) && !empty($s['can_send_messages']));
    qzGroupPut($chat['id'] ?? 0, (string)($chat['title'] ?? ''), !$in);
}

function qzGroupMigrate($from, $to) {
    qzGroupPut($from, '', true);
    qzGroupPut($to);
}

function qzGroupSync() {
    $mark  = DATA_DIR . '/.qz_gsync';
    $since = (int)(@filemtime($mark) ?: 0);
    if ($since && time() - $since < 300) return;
    @touch($mark);
    if (!function_exists('tpDb') || !($tp = tpDb()) || !($db = quizDb())) return;
    $st = $tp->prepare('SELECT chat, MAX(at) m FROM members WHERE at >= :s GROUP BY chat');
    $st->bindValue(':s', max(0, $since - 120), SQLITE3_INTEGER);
    $res  = $st->execute();
    $seen = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) if ((int)$row['chat'] < 0) $seen[(int)$row['chat']] = (int)$row['m'];
    if (!$seen) return;
    $ins = $db->prepare('INSERT OR IGNORE INTO quiz_groups (chat, title, added, dead_at) VALUES (:c, "", :a, 0)');
    $rev = $db->prepare('UPDATE quiz_groups SET dead_at = 0 WHERE chat = :c AND dead_at > 0 AND dead_at < :m');
    $db->exec('BEGIN');
    foreach ($seen as $c => $m) {
        $ins->bindValue(':c', $c, SQLITE3_INTEGER);
        $ins->bindValue(':a', time(), SQLITE3_INTEGER);
        $ins->execute();
        $ins->reset();
        $rev->bindValue(':c', $c, SQLITE3_INTEGER);
        $rev->bindValue(':m', $m, SQLITE3_INTEGER);
        $rev->execute();
        $rev->reset();
    }
    $db->exec('COMMIT');
}

function qzAdoptOldChat() {
    $old = trim((string)(cfg()['quiz']['chat_id'] ?? ''));
    if ($old === '') return;
    if (!preg_match('/^-\d+$/', $old)) $old = (string)(tg(BOT_TOKEN, 'getChat', ['chat_id' => $old], 8)['result']['id'] ?? '');
    if ($old !== '') qzGroupPut((int)$old);
    qzSet(function (&$c) { unset($c['chat_id']); });
}

function qzWonToday($uid, $day) {
    $db = quizDb();
    if (!$db) return false;
    $st = $db->prepare('SELECT 1 FROM quiz_wins WHERE uid = :u AND day = :d');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':d', (string)$day, SQLITE3_TEXT);
    return (bool)$st->execute()->fetchArray(SQLITE3_NUM);
}

function qzWinMark($uid, $day) {
    $db = quizDb();
    if (!$db) return;
    $st = $db->prepare('INSERT OR IGNORE INTO quiz_wins (uid, day) VALUES (:u, :d)');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':d', (string)$day, SQLITE3_TEXT);
    $st->execute();
}

function qzGc() {
    $mark = DATA_DIR . '/.qz_gc';
    if (time() - (@filemtime($mark) ?: 0) < 86400) return;
    @touch($mark);
    $db = quizDb();
    if (!$db) return;
    $__st = $db->prepare("DELETE FROM quiz_rounds WHERE status = 'closed' AND ends < :c");
    if ($__st) { $__st->bindValue(':c', time() - 30 * 86400, SQLITE3_INTEGER); $__st->execute(); }
    $cut = gmdate('Y-m-d', qzNowLocal() - 30 * 86400);
    foreach (['quiz_sent', 'quiz_wins'] as $t) {
        $st = $db->prepare("DELETE FROM {$t} WHERE day < :d");
        $st->bindValue(':d', $cut, SQLITE3_TEXT);
        $st->execute();
    }
}


function qzSeedBank($db) {
    $r = $db->query('SELECT COUNT(*) c FROM quiz_bank')->fetchArray(SQLITE3_ASSOC);
    if ((int)($r['c'] ?? 0) > 0) return;

    $rows = [
        ['بلندترین قله‌ی ایران کدام است؟', 'دماوند', 'سبلان', 'زردکوه', 1, 'جغرافیا'],
        ['بزرگ‌ترین جزیره‌ی ایران در خلیج فارس کدام است؟', 'کیش', 'قشم', 'هرمز', 2, 'جغرافیا'],
        ['پایتخت ژاپن کدام شهر است؟', 'اوساکا', 'کیوتو', 'توکیو', 3, 'جغرافیا'],
        ['طولانی‌ترین رود جهان کدام است؟', 'نیل', 'آمازون', 'میسی‌سی‌پی', 1, 'جغرافیا'],
        ['کدام کشور بیشترین جمعیت جهان را دارد؟', 'چین', 'هند', 'آمریکا', 2, 'جغرافیا'],
        ['دریاچه‌ی ارومیه در کدام قسمت ایران است؟', 'شمال شرق', 'شمال غرب', 'جنوب', 2, 'جغرافیا'],
        ['بزرگ‌ترین اقیانوس جهان کدام است؟', 'اطلس', 'هند', 'آرام', 3, 'جغرافیا'],
        ['کویر لوت در کدام استان‌ها گسترده شده است؟', 'کرمان و سیستان', 'یزد و فارس', 'قم و مرکزی', 1, 'جغرافیا'],
        ['پایتخت استرالیا کدام شهر است؟', 'سیدنی', 'ملبورن', 'کانبرا', 3, 'جغرافیا'],
        ['کدام کشور با ایران مرز آبی در دریای خزر ندارد؟', 'ترکمنستان', 'ارمنستان', 'قزاقستان', 2, 'جغرافیا'],
        ['بزرگ‌ترین صحرای گرم جهان کدام است؟', 'صحرای بزرگ آفریقا', 'گبی', 'کالاهاری', 1, 'جغرافیا'],
        ['شهر اصفهان در کنار کدام رودخانه بنا شده است؟', 'کارون', 'زاینده‌رود', 'سفیدرود', 2, 'جغرافیا'],
        ['کدام قاره بیشترین تعداد کشور را دارد؟', 'آسیا', 'اروپا', 'آفریقا', 3, 'جغرافیا'],
        ['تنگه‌ی هرمز کدام دو آب را به هم وصل می‌کند؟', 'خلیج فارس و دریای عمان', 'خزر و سیاه', 'سرخ و مدیترانه', 1, 'جغرافیا'],
        ['مرتفع‌ترین قله‌ی جهان کدام است؟', 'K2', 'اورست', 'کلیمانجارو', 2, 'جغرافیا'],

        ['کوروش بزرگ بنیان‌گذار کدام سلسله بود؟', 'اشکانیان', 'ساسانیان', 'هخامنشیان', 3, 'تاریخ'],
        ['تخت جمشید در نزدیکی کدام شهر امروزی است؟', 'شیراز', 'کرمان', 'همدان', 1, 'تاریخ'],
        ['کدام سلسله پس از هخامنشیان بر ایران حکومت کرد؟', 'ساسانیان', 'سلوکیان', 'صفویان', 2, 'تاریخ'],
        ['شاه اسماعیل بنیان‌گذار کدام سلسله بود؟', 'قاجار', 'افشار', 'صفوی', 3, 'تاریخ'],
        ['نادرشاه از کدام سلسله بود؟', 'افشاریه', 'زندیه', 'قاجاریه', 1, 'تاریخ'],
        ['پایتخت ایران در دوره‌ی صفویه (اوج) کدام شهر بود؟', 'تبریز', 'اصفهان', 'قزوین', 2, 'تاریخ'],
        ['کریم‌خان زند خود را چه می‌نامید؟', 'شاهنشاه', 'سلطان', 'وکیل‌الرعایا', 3, 'تاریخ'],
        ['انقلاب مشروطه‌ی ایران در چه دوره‌ای رخ داد؟', 'قاجار', 'پهلوی', 'صفوی', 1, 'تاریخ'],
        ['داریوش بزرگ کدام بنا را ساخت؟', 'چغازنبیل', 'تخت جمشید', 'ارگ بم', 2, 'تاریخ'],
        ['کتیبه‌ی بیستون به دستور چه کسی نوشته شد؟', 'کوروش', 'خشایارشا', 'داریوش بزرگ', 3, 'تاریخ'],

        ['شاهنامه سروده‌ی کیست؟', 'فردوسی', 'نظامی', 'سعدی', 1, 'ادبیات'],
        ['«بنی‌آدم اعضای یک پیکرند» از کیست؟', 'حافظ', 'سعدی', 'مولوی', 2, 'ادبیات'],
        ['مثنوی معنوی اثر کیست؟', 'عطار', 'سنایی', 'مولوی', 3, 'ادبیات'],
        ['دیوان حافظ بیشتر شامل چه قالبی است؟', 'غزل', 'قصیده', 'رباعی', 1, 'ادبیات'],
        ['رباعیات مشهور از کدام شاعر و ریاضی‌دان است؟', 'ابن‌سینا', 'خیام', 'رازی', 2, 'ادبیات'],
        ['«لیلی و مجنون» اثر کدام شاعر است؟', 'فردوسی', 'جامی', 'نظامی گنجوی', 3, 'ادبیات'],
        ['گلستان و بوستان از کیست؟', 'سعدی', 'حافظ', 'رودکی', 1, 'ادبیات'],
        ['«بوف کور» نوشته‌ی کیست؟', 'جلال آل‌احمد', 'صادق هدایت', 'بزرگ علوی', 2, 'ادبیات'],
        ['منطق‌الطیر اثر کیست؟', 'مولوی', 'سنایی', 'عطار نیشابوری', 3, 'ادبیات'],
        ['پدر شعر فارسی چه کسی خوانده می‌شود؟', 'رودکی', 'فردوسی', 'ناصرخسرو', 1, 'ادبیات'],
        ['نیما یوشیج بنیان‌گذار چه سبکی است؟', 'سبک هندی', 'شعر نو', 'سبک خراسانی', 2, 'ادبیات'],
        ['«سووشون» رمانی از کیست؟', 'زویا پیرزاد', 'گلی ترقی', 'سیمین دانشور', 3, 'ادبیات'],

        ['آب در فشار معمولی در چند درجه‌ی سانتی‌گراد می‌جوشد؟', '۱۰۰', '۹۰', '۱۲۰', 1, 'علوم'],
        ['کدام سیاره به «سیاره‌ی سرخ» معروف است؟', 'زهره', 'مریخ', 'مشتری', 2, 'علوم'],
        ['نماد شیمیایی طلا چیست؟', 'Ag', 'Fe', 'Au', 3, 'علوم'],
        ['بدن انسان چند دنده دارد؟', '۲۴', '۲۰', '۳۰', 1, 'علوم'],
        ['بزرگ‌ترین سیاره‌ی منظومه‌ی شمسی کدام است؟', 'زحل', 'مشتری', 'نپتون', 2, 'علوم'],
        ['گیاهان در فرایند فتوسنتز چه گازی آزاد می‌کنند؟', 'دی‌اکسید کربن', 'نیتروژن', 'اکسیژن', 3, 'علوم'],
        ['سرعت نور در خلأ حدوداً چقدر است؟', '۳۰۰٬۰۰۰ کیلومتر بر ثانیه', '۳۰٬۰۰۰ کیلومتر بر ثانیه', '۳٬۰۰۰ کیلومتر بر ثانیه', 1, 'علوم'],
        ['کدام عضو بدن خون را در بدن پمپاژ می‌کند؟', 'کبد', 'قلب', 'کلیه', 2, 'علوم'],
        ['نماد شیمیایی آب چیست؟', 'CO2', 'O2', 'H2O', 3, 'علوم'],
        ['کدام دانشمند نظریه‌ی نسبیت را مطرح کرد؟', 'انیشتین', 'نیوتن', 'گالیله', 1, 'علوم'],
        ['ماه چقدر طول می‌کشد تا یک بار به دور زمین بگردد؟', 'حدود ۷ روز', 'حدود ۲۷ روز', 'حدود ۹۰ روز', 2, 'علوم'],
        ['سخت‌ترین ماده‌ی طبیعی شناخته‌شده چیست؟', 'گرانیت', 'فولاد', 'الماس', 3, 'علوم'],
        ['خون در بدن انسان توسط چه چیزی اکسیژن حمل می‌کند؟', 'هموگلوبین', 'انسولین', 'کلسترول', 1, 'علوم'],
        ['کدام ویتامین با نور خورشید در بدن ساخته می‌شود؟', 'ویتامین C', 'ویتامین D', 'ویتامین B12', 2, 'علوم'],
        ['واحد اندازه‌گیری جریان الکتریکی چیست؟', 'ولت', 'وات', 'آمپر', 3, 'علوم'],

        ['مجموع زوایای داخلی یک مثلث چند درجه است؟', '۱۸۰', '۳۶۰', '۹۰', 1, 'ریاضی'],
        ['۱۵ درصد از ۲۰۰ چند می‌شود؟', '۲۰', '۳۰', '۴۰', 2, 'ریاضی'],
        ['جذر ۱۴۴ چند است؟', '۱۴', '۱۳', '۱۲', 3, 'ریاضی'],
        ['یک دایره چند درجه است؟', '۳۶۰', '۱۸۰', '۲۷۰', 1, 'ریاضی'],
        ['حاصل ۷ × ۸ چند می‌شود؟', '۴۹', '۵۶', '۶۴', 2, 'ریاضی'],
        ['کدام عدد اول است؟', '۹', '۱۵', '۱۷', 3, 'ریاضی'],
        ['مجموع زوایای داخلی یک چهارضلعی چند درجه است؟', '۳۶۰', '۱۸۰', '۵۴۰', 1, 'ریاضی'],
        ['۲ به توان ۱۰ چند می‌شود؟', '۵۱۲', '۱۰۲۴', '۲۰۴۸', 2, 'ریاضی'],
        ['اگر یک عدد را بر خودش تقسیم کنیم (به‌جز صفر) نتیجه چند است؟', 'صفر', 'خود عدد', 'یک', 3, 'ریاضی'],
        ['حاصل ۱۰۰ ÷ ۴ چند است؟', '۲۵', '۲۰', '۵۰', 1, 'ریاضی'],

        ['هر تیم فوتبال در زمین چند بازیکن دارد؟', '۱۰', '۱۱', '۱۲', 2, 'ورزش'],
        ['جام جهانی فوتبال هر چند سال یک‌بار برگزار می‌شود؟', '۲ سال', '۳ سال', '۴ سال', 3, 'ورزش'],
        ['والیبال در هر تیم چند بازیکن داخل زمین دارد؟', '۶', '۵', '۷', 1, 'ورزش'],
        ['کدام کشور بیشترین قهرمانی جام جهانی فوتبال را دارد؟', 'آلمان', 'برزیل', 'ایتالیا', 2, 'ورزش'],
        ['هر تیم بسکتبال چند بازیکن در زمین دارد؟', '۶', '۷', '۵', 3, 'ورزش'],
        ['ورزش ملی و باستانی ایران چیست؟', 'کشتی', 'فوتبال', 'شنا', 1, 'ورزش'],
        ['المپیک تابستانی هر چند سال یک‌بار برگزار می‌شود؟', '۲ سال', '۴ سال', '۵ سال', 2, 'ورزش'],
        ['یک ست والیبال معمولاً تا چند امتیاز است؟', '۲۱', '۲۰', '۲۵', 3, 'ورزش'],
        ['در شطرنج کدام مهره بیشترین قدرت حرکت را دارد؟', 'وزیر', 'رخ', 'اسب', 1, 'ورزش'],
        ['یک نیمه‌ی فوتبال چند دقیقه است؟', '۳۰', '۴۵', '۶۰', 2, 'ورزش'],

        ['HTML مخفف چیست؟', 'HyperText Markup Language', 'High Tech Modern Language', 'Home Tool Markup Language', 1, 'فناوری'],
        ['کدام شرکت سیستم‌عامل اندروید را توسعه می‌دهد؟', 'اپل', 'گوگل', 'مایکروسافت', 2, 'فناوری'],
        ['واحد اندازه‌گیری حافظه که از گیگابایت بزرگ‌تر است چیست؟', 'مگابایت', 'کیلوبایت', 'ترابایت', 3, 'فناوری'],
        ['CPU در کامپیوتر چه نقشی دارد؟', 'پردازش داده', 'ذخیره‌سازی دائمی', 'نمایش تصویر', 1, 'فناوری'],
        ['بنیان‌گذار مایکروسافت چه کسی است؟', 'استیو جابز', 'بیل گیتس', 'مارک زاکربرگ', 2, 'فناوری'],
        ['کدام‌یک یک زبان برنامه‌نویسی است؟', 'HTTP', 'HTML', 'Python', 3, 'فناوری'],
        ['Wi-Fi برای چه کاری استفاده می‌شود؟', 'اتصال بی‌سیم به شبکه', 'ذخیره‌ی فایل', 'چاپ اسناد', 1, 'فناوری'],
        ['یک بایت چند بیت است؟', '۴', '۸', '۱۶', 2, 'فناوری'],
        ['کدام‌یک یک مرورگر وب است؟', 'Photoshop', 'Excel', 'Firefox', 3, 'فناوری'],
        ['تلگرام در چه سالی راه‌اندازی شد؟', '۲۰۱۳', '۲۰۱۰', '۲۰۱۶', 1, 'فناوری'],
        ['RAM چه نوع حافظه‌ای است؟', 'حافظه‌ی دائمی', 'حافظه‌ی موقت', 'حافظه‌ی نوری', 2, 'فناوری'],
        ['بیت‌کوین چه نوع دارایی‌ای است؟', 'سهام', 'اوراق قرضه', 'ارز دیجیتال', 3, 'فناوری'],

        ['رنگ‌های پرچم ایران به ترتیب از بالا چیست؟', 'سبز، سفید، قرمز', 'قرمز، سفید، سبز', 'سفید، سبز، قرمز', 1, 'عمومی'],
        ['هفته چند روز است؟', '۵', '۷', '۱۰', 2, 'عمومی'],
        ['سال کبیسه چند روز دارد؟', '۳۶۴', '۳۶۵', '۳۶۶', 3, 'عمومی'],
        ['نوروز در چه فصلی آغاز می‌شود؟', 'بهار', 'تابستان', 'پاییز', 1, 'عمومی'],
        ['کدام حیوان بزرگ‌ترین پستاندار جهان است؟', 'فیل', 'نهنگ آبی', 'زرافه', 2, 'عمومی'],
        ['رنگ حاصل از ترکیب آبی و زرد چیست؟', 'نارنجی', 'بنفش', 'سبز', 3, 'عمومی'],
        ['یک سال شمسی چند ماه دارد؟', '۱۲', '۱۰', '۱۳', 1, 'عمومی'],
        ['کدام‌یک از فلزات در دمای اتاق مایع است؟', 'سرب', 'جیوه', 'مس', 2, 'عمومی'],
        ['سریع‌ترین حیوان خشکی کدام است؟', 'شیر', 'اسب', 'یوزپلنگ', 3, 'عمومی'],
        ['شش ماه اول سال شمسی هرکدام چند روز است؟', '۳۱', '۳۰', '۲۹', 1, 'عمومی'],
        ['عسل را کدام حشره تولید می‌کند؟', 'مورچه', 'زنبور', 'پروانه', 2, 'عمومی'],
        ['کدام‌یک میوه نیست؟', 'انبه', 'آناناس', 'هویج', 3, 'عمومی'],
        ['پرچم ژاپن چه شکلی در وسط دارد؟', 'دایره‌ی قرمز', 'ستاره‌ی سفید', 'مثلث آبی', 1, 'عمومی'],
        ['بزرگ‌ترین اندام داخلی بدن انسان کدام است؟', 'قلب', 'کبد', 'شش', 2, 'عمومی'],
        ['کدام ماه شمسی ۲۹ یا ۳۰ روز دارد؟', 'فروردین', 'مهر', 'اسفند', 3, 'عمومی'],
        ['چند ثانیه در یک ساعت وجود دارد؟', '۳۶۰۰', '۶۰۰', '۸۶۴۰۰', 1, 'عمومی'],
        ['کدام‌یک گاز است؟', 'جیوه', 'هلیوم', 'گرافیت', 2, 'عمومی'],
        ['رنگین‌کمان چند رنگ اصلی دارد؟', '۵', '۶', '۷', 3, 'عمومی'],
    ];

    $st = $db->prepare('INSERT INTO quiz_bank (q, o1, o2, o3, correct, cat, used_at)
                        VALUES (:q, :a, :b, :c, :k, :cat, 0)');
    $db->exec('BEGIN');
    foreach ($rows as [$q, $a, $b, $c, $k, $cat]) {
        $st->bindValue(':q', $q, SQLITE3_TEXT);
        $st->bindValue(':a', $a, SQLITE3_TEXT);
        $st->bindValue(':b', $b, SQLITE3_TEXT);
        $st->bindValue(':c', $c, SQLITE3_TEXT);
        $st->bindValue(':k', (int)$k, SQLITE3_INTEGER);
        $st->bindValue(':cat', $cat, SQLITE3_TEXT);
        $st->execute();
        $st->reset();
    }
    $db->exec('COMMIT');
}


function qzKb($id) {
    return inlineKb([
        [qzBtn('btn_o1', [], 'qz_a_' . $id . '_1')],
        [qzBtn('btn_o2', [], 'qz_a_' . $id . '_2'), qzBtn('btn_o3', [], 'qz_a_' . $id . '_3')],
    ]);
}

function qzCardText($r) {
    $left = max(0, (int)$r['ends'] - time());
    return qzT('card', [
        'question' => h((string)$r['q']),
        'o1' => h((string)$r['o'][0]), 'o2' => h((string)$r['o'][1]), 'o3' => h((string)$r['o'][2]),
        'reward' => qzNum($r['reward'] ?? 0),
        'mins'   => max(1, (int)ceil($left / 60)),
    ]);
}

function qzUserTag($id, $name) {
    $n = trim((string)$name);
    if ($n === '') $n = 'کاربر';
    return '<a href="tg://user?id=' . (int)$id . '">' . h($n) . '</a>';
}

function qzEndText($r) {
    $correct = (int)$r['correct'];
    $answer  = (string)($r['o'][$correct - 1] ?? '');
    if (empty(qzVal('reveal', 1))) $answer = '؟';
    $vars = ['question' => h((string)$r['q']), 'answer' => h($answer)];

    if (empty($r['winner'])) return qzT('timeout', $vars);

    $w = $r['winner'];
    $t = qzT('won', $vars + [
        'winner' => qzUserTag($w['id'], $w['name']),
        'reward' => qzNum($w['prize']),
        'secs'   => max(0, (int)$w['at'] - (int)$r['started']),
    ]);
    foreach (['runner_up' => $r['second'] ?? null, 'third' => $r['third'] ?? null] as $slug => $p) {
        if (!$p) continue;
        $t .= qzT($slug, ['name' => qzUserTag($p['id'], $p['name']), 'amount' => qzNum($p['prize'])]);
    }
    return $t;
}


function qzFail($chat, $r) {
    $to = (int)($r['parameters']['migrate_to_chat_id'] ?? 0);
    if ($to) { qzGroupMigrate($chat, $to); return 'moved'; }
    $code = (int)($r['error_code'] ?? 0);
    if ($code === 429) return 'wait';
    $desc = (string)($r['description'] ?? '');
    if ($code === 403 || ($code === 400 && preg_match('/chat not found|rights|forbidden|deactivated|kicked|not a member|upgraded/i', $desc))) {
        qzGroupPut($chat, '', true);
        return 'dead';
    }
    if ($code === 400) {
        if (function_exists('adminAlertOnce'))
            adminAlertOnce('qz_send_400', "🧠 <b>چالش روزانه در یک گروه فرستاده نشد</b>\n\n<code>" . h(mb_substr($desc, 0, 300)) . '</code>', 86400);
        return 'error';
    }
    return 'retry';
}

function qzSendTo($chat, $day) {
    $q = qzPickQuestion();
    [$o, $correct] = qzShuffle($q);
    $dur = max(60, (int)qzVal('duration', 3600));
    $r = [
        'day'     => $day,
        'qid'     => (int)($q['id'] ?? 0),
        'q'       => (string)$q['q'],
        'o'       => $o,
        'correct' => $correct,
        'cat'     => (string)($q['cat'] ?? ''),
        'chat'    => (int)$chat,
        'msg'     => 0,
        'status'  => 'open',
        'started' => time(),
        'ends'    => time() + $dur,
        'reward'  => (float)qzVal('reward', 500),
        'tried'   => [],
        'winner'  => null,
    ];
    $id = qzRoundCreate($r);
    if (!$id) return 'retry';
    $r['id'] = $id;
    if (!empty($q['id'])) qzMarkUsed($q['id']);

    $res = sendMsg(BOT_TOKEN, $chat, qzCardText($r), qzKb($id));
    $mid = (int)($res['result']['message_id'] ?? 0);
    if (!$mid) {
        qzRoundSet($id, function (&$x) { $x['status'] = 'closed'; return true; });
        return qzFail($chat, $res);
    }
    qzRoundSet($id, function (&$x) use ($mid) { $x['msg'] = $mid; return true; });
    if (!empty(qzVal('pin'))) tg(BOT_TOKEN, 'pinChatMessage',
        ['chat_id' => $chat, 'message_id' => $mid, 'disable_notification' => 'true']);
    return 'ok';
}

function qzSendDaily($force = false, $limit = 10) {
    if (!qzOn()) return 0;
    $day = qzTodayKey();
    if (!$force && (string)@file_get_contents(DATA_DIR . '/.qz_force') !== $day) {
        $local   = qzNowLocal();
        $nowMin  = (int)gmdate('G', $local) * 60 + (int)gmdate('i', $local);
        $wantMin = max(0, min(23, (int)qzVal('hour', 21))) * 60 + max(0, min(59, (int)qzVal('minute', 0)));
        if ($nowMin < $wantMin || $nowMin - $wantMin > 180) return 0;
    }
    $db = quizDb();
    if (!$db) return 0;
    qzGroupSync();

    $st = $db->prepare('SELECT chat FROM quiz_groups WHERE dead_at = 0
                        AND chat NOT IN (SELECT chat FROM quiz_sent WHERE day = :d) LIMIT :l');
    $st->bindValue(':d', $day, SQLITE3_TEXT);
    $st->bindValue(':l', max(1, (int)$limit), SQLITE3_INTEGER);
    $chats = [];
    $res = $st->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) $chats[] = (int)$row['chat'];
    if (!$chats) return 0;

    $min = max(0, (int)qzVal('min_members', 10));
    $cnt = [];
    if ($min > 0) {
        $req = [];
        foreach ($chats as $c) $req[$c] = ['chat_id' => $c];
        $cnt = tgMulti(BOT_TOKEN, 'getChatMemberCount', $req, 8);
    }

    $claim = $db->prepare('INSERT OR IGNORE INTO quiz_sent (chat, day, st) VALUES (:c, :d, "")');
    $mark  = $db->prepare('UPDATE quiz_sent SET st = :s WHERE chat = :c AND day = :d');
    $drop  = $db->prepare('DELETE FROM quiz_sent WHERE chat = :c AND day = :d');
    $n = 0;
    foreach ($chats as $c) {
        $claim->bindValue(':c', $c, SQLITE3_INTEGER);
        $claim->bindValue(':d', $day, SQLITE3_TEXT);
        $claim->execute();
        $claim->reset();
        if (!$db->changes()) continue;

        $s = 'ok';
        if ($min > 0) {
            $m = $cnt[$c] ?? ['ok' => false];
            if (empty($m['ok']))                 $s = qzFail($c, $m);
            elseif ((int)$m['result'] < $min)    $s = 'small';
        }
        if ($s === 'ok') $s = qzSendTo($c, $day);

        if ($s === 'retry' || $s === 'wait') {
            $drop->bindValue(':c', $c, SQLITE3_INTEGER);
            $drop->bindValue(':d', $day, SQLITE3_TEXT);
            $drop->execute();
            $drop->reset();
            if ($s === 'wait') break;
            continue;
        }
        $mark->bindValue(':s', $s, SQLITE3_TEXT);
        $mark->bindValue(':c', $c, SQLITE3_INTEGER);
        $mark->bindValue(':d', $day, SQLITE3_TEXT);
        $mark->execute();
        $mark->reset();
        if ($s === 'ok') $n++;
    }
    return $n;
}

function qzTick($limit = 20) {
    $db = quizDb();
    if (!$db) return 0;
    $n = 0;

    $ids = [];
    $res = $db->query("SELECT id FROM quiz_rounds WHERE status = 'open' AND ends < " . time()
                      . ' LIMIT ' . max(1, (int)$limit));
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) $ids[] = (string)$row['id'];

    foreach ($ids as $id) {
        $done = qzRoundSet($id, function (&$r) {
            if (($r['status'] ?? '') !== 'open') return false;
            $r['status'] = 'closed';
            return true;
        });
        if ($done !== true) continue;
        $n++;
        $r = qzRoundGet($id);
        if ($r && (int)($r['msg'] ?? 0) && !empty($r['chat']))
            editMsg(BOT_TOKEN, $r['chat'], (int)$r['msg'], qzEndText($r), null);
    }

    $n += qzSendDaily(false, $limit);
    qzGc();
    return $n;
}


function qzCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if (!preg_match('/^qz_a_(\w+)_([123])$/', (string)$data, $m)) return false;
    $id   = $m[1];
    $pick = (int)$m[2];
    $name = trim((string)($from['first_name'] ?? ''));

    $r = qzRoundGet($id);
    if (!$r) { answerCb(BOT_TOKEN, $cbId, qzT('pop_closed'), true); return true; }
    if (($r['status'] ?? '') !== 'open' || time() > (int)$r['ends']) {
        answerCb(BOT_TOKEN, $cbId, qzT('pop_late'), true);
        return true;
    }

    $out = qzRoundSet($id, function (&$r) use ($uid, $pick, $name) {
        if (($r['status'] ?? '') !== 'open') return ['s' => 'closed'];
        if (time() > (int)$r['ends'])        return ['s' => 'late'];

        $tried = (array)($r['tried'] ?? []);
        if (!empty(qzVal('one_try')) && isset($tried[(string)$uid])) return ['s' => 'twice'];
        $tried[(string)$uid] = $pick;
        $r['tried'] = $tried;

        if ($pick !== (int)$r['correct']) return ['s' => 'wrong'];

        $prize = 0.0;
        $slot  = '';
        if (empty($r['winner']))      { $slot = 'winner'; $prize = (float)($r['reward'] ?? 0); }
        elseif (empty($r['second']))  { $slot = 'second'; $prize = (float)qzVal('reward2', 0); }
        elseif (empty($r['third']))   { $slot = 'third';  $prize = (float)qzVal('reward3', 0); }
        else return ['s' => 'right_late'];

        if ($slot !== 'winner' && $prize <= 0) return ['s' => 'right_late'];
        if (qzWonToday((int)$uid, (string)($r['day'] ?? ''))) return ['s' => 'daily'];
        qzWinMark((int)$uid, (string)($r['day'] ?? ''));

        $r[$slot] = ['id' => (int)$uid, 'name' => $name, 'prize' => $prize, 'at' => time()];

        $needs2 = (float)qzVal('reward2', 0) > 0;
        $needs3 = (float)qzVal('reward3', 0) > 0;
        if (!$needs2 || (!empty($r['second']) && (!$needs3 || !empty($r['third']))))
            $r['status'] = 'closed';

        return ['s' => 'right', 'slot' => $slot, 'prize' => $prize, 'closed' => ($r['status'] === 'closed')];
    });

    if (!is_array($out)) { answerCb(BOT_TOKEN, $cbId, qzT('pop_closed'), true); return true; }

    switch ($out['s']) {
        case 'twice':  answerCb(BOT_TOKEN, $cbId, qzT('pop_twice'), true);  return true;
        case 'late':   answerCb(BOT_TOKEN, $cbId, qzT('pop_late'), true);   return true;
        case 'closed': answerCb(BOT_TOKEN, $cbId, qzT('pop_closed'), true); return true;
        case 'wrong':  answerCb(BOT_TOKEN, $cbId, qzT('pop_wrong'), true);  return true;
        case 'daily':  answerCb(BOT_TOKEN, $cbId, qzT('pop_daily'), true);  return true;
        case 'right_late':
            answerCb(BOT_TOKEN, $cbId, qzT('pop_late'), true);
            return true;
    }

    if ($out['prize'] > 0 && function_exists('gmAdd'))
        gmAdd((int)$uid, (float)$out['prize'], $name, (string)($from['username'] ?? ''));

    answerCb(BOT_TOKEN, $cbId, qzT('pop_right'), true);

    $fresh = qzRoundGet($id);
    if ($fresh && (int)($fresh['msg'] ?? 0)) {
        $txt = ($fresh['status'] ?? '') === 'closed' ? qzEndText($fresh) : qzCardText($fresh);
        $kb  = ($fresh['status'] ?? '') === 'closed' ? null : qzKb($id);
        editMsg(BOT_TOKEN, $fresh['chat'], (int)$fresh['msg'], $txt, $kb);
        if (($fresh['status'] ?? '') === 'closed' && !empty(qzVal('pin')))
            tg(BOT_TOKEN, 'unpinChatMessage', ['chat_id' => $fresh['chat'], 'message_id' => (int)$fresh['msg']]);
    }
    return true;
}


function qzAdminHome($chatId, $msgId = null) {
    $c   = qzCfg();
    $day = qzTodayKey();

    $t  = "🧠 <b>چالش روزانه</b>\n\n";
    $t .= 'وضعیت: ' . (qzOn() ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= '👥 گروه‌ها: <b>' . qzGroupCount() . "</b>\n";
    $t .= '👤 حداقل اعضای گروه: <b>' . (int)$c['min_members'] . "</b>\n";
    $t .= sprintf("⏰ ساعتِ ارسال: <b>%02d:%02d</b>  (آفست %+.1f ساعت)\n",
        (int)$c['hour'], (int)$c['minute'], (int)$c['tz_offset'] / 3600);
    $t .= '⏳ مهلتِ پاسخ: <b>' . max(1, (int)round((int)$c['duration'] / 60)) . "</b> دقیقه\n";
    $t .= '🏆 جایزه: <b>' . qzNum($c['reward']) . '</b> الماس';
    if ((float)$c['reward2'] > 0) $t .= ' · دوم <b>' . qzNum($c['reward2']) . '</b>';
    if ((float)$c['reward3'] > 0) $t .= ' · سوم <b>' . qzNum($c['reward3']) . '</b>';
    $t .= "\n";
    $t .= '📚 بانکِ سوال: <b>' . qzBankCount() . "</b>\n";
    $t .= '📅 ارسالِ امروز: <b>' . qzSentCount($day) . '</b> گروه';

    $rows = [
        [btnCb(qzOn() ? '✅ روشن' : '❌ خاموش', 'qzax', 'info')],
        [btnCb('👤 حداقل اعضا', 'qza_min', 'admin'), btnCb('⏰ ساعتِ ارسال', 'qza_time', 'admin')],
        [btnCb('🏆 جایزه‌ها', 'qza_reward', 'admin'), btnCb('⏳ مهلتِ پاسخ', 'qza_dur', 'admin')],
        [btnCb('📚 بانکِ سوال', 'qzb_0', 'admin'), btnCb('➕ سوال تازه', 'qza_add', 'admin')],
        [btnCb('✏️ متن‌ها و دکمه‌ها', 'qzat_home', 'admin'), btnCb('🎨 رنگِ دکمه‌ها', 'qzacolors', 'admin')],
        [btnCb($c['one_try'] ? '🔒 هر نفر یک جواب: روشن' : '🔓 هر نفر یک جواب: خاموش', 'qzatry', 'info')],
        [btnCb($c['pin'] ? '📌 سنجاق: روشن' : '📌 سنجاق: خاموش', 'qzapin', 'info')],
        [btnCb(!empty($c['reveal']) ? '🙈 نمایشِ جوابِ درست: روشن' : '🙈 نمایشِ جوابِ درست: خاموش', 'qzarev', 'info')],
        [btnCb('🚀 همین حالا در همه‌ی گروه‌ها بفرست', 'qzanow', 'confirm')],
        [btnCb(UT('back'), 'ag_games', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function qzTextsCfg() {
    return [
        'title' => 'متن‌های چالش روزانه',
        'keys'  => array_keys((array)qzVal('texts', [])),
        'popup' => ['pop_right', 'pop_wrong', 'pop_late', 'pop_twice', 'pop_closed', 'pop_daily'],
        'btns'  => qzBtnKeys(),
        'label' => 'qzLabel',
        'value' => function ($k) { return (string)qzVal('texts.' . $k, ''); },
        'cb'    => 'qzat_',
        'edit'  => 'qzats_',
        'back'  => 'qz_home',
    ];
}

function qzAdminColors($chatId, $msgId) {
    $t = "🎨 <b>رنگِ دکمه‌های چالش</b>\n\n";
    $rows = [];
    foreach (qzBtnKeys() as $k) {
        $color = (string)qzVal('btns.' . $k . '.color', 'none');
        $t .= '• ' . h(qzLabel($k)) . ': <b>' . h(styleMap()[$color] ?? $color) . "</b>\n";
        $rows[] = [btnCb(qzLabel($k), 'qzacolk_' . $k, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'qz_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function qzAdminColorPick($chatId, $msgId, $k) {
    $cur = (string)qzVal('btns.' . $k . '.color', 'none');
    $t = "🎨 رنگِ <b>" . h(qzLabel($k)) . "</b>\n\nالان: <b>" . h(styleMap()[$cur] ?? $cur) . "</b>";
    $rows = [];
    foreach (styleMap() as $sk => $sl) $rows[] = [btnCb(($sk === $cur ? '✅ ' : '') . $sl, 'qzacolv_' . $k . '_' . $sk, 'info')];
    $rows[] = [btnCb(UT('back'), 'qzacolors', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function qzAdminBank($chatId, $msgId, $page = 0) {
    $per  = 8;
    $tot  = max(1, (int)ceil(max(1, qzBankCount()) / $per));
    $page = max(0, min($tot - 1, (int)$page));
    $rows_ = qzBankPage($page, $per);

    $t  = "📚 <b>بانکِ سوال</b> — صفحه " . ($page + 1) . " از {$tot}\n";
    $t .= 'مجموع: <b>' . qzBankCount() . '</b> سوال · پرسیده‌نشده: <b>' . qzBankCount(true) . "</b>\n\n";
    if (!$rows_) $t .= "هنوز سوالی نیست. با «➕ سوال تازه» اضافه کنید.\n";

    $kb = [];
    foreach ($rows_ as $q) {
        $mark = (int)$q['used_at'] > 0 ? '🔁' : '🆕';
        $t .= $mark . ' <b>#' . (int)$q['id'] . '</b> ' . h(mb_substr((string)$q['q'], 0, 52)) . "\n";
        $t .= '     ✅ ' . h((string)$q['o' . (int)$q['correct']])
            . ($q['cat'] !== '' ? ' · <i>' . h((string)$q['cat']) . '</i>' : '') . "\n";
        $kb[] = [btnCb('🗑 حذفِ #' . (int)$q['id'], 'qzbd_' . (int)$q['id'], 'danger')];
    }
    $nav = [];
    if ($page > 0)        $nav[] = btnCb('◀️', 'qzb_' . ($page - 1), 'nav');
    if ($page < $tot - 1) $nav[] = btnCb('▶️', 'qzb_' . ($page + 1), 'nav');
    if ($nav) $kb[] = $nav;
    $kb[] = [btnCb('➕ سوال تازه', 'qza_add', 'admin')];
    $kb[] = [btnCb(UT('back'), 'qz_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($kb));
}

function qzAdminCallback($data, $chatId, $msgId, $cbId) {
    $d = (string)$data;
    if (!str_starts_with($d, 'qz_home') && !str_starts_with($d, 'qza') && !str_starts_with($d, 'qzb')) return false;

    if ($d === 'qz_home') { answerCb(BOT_TOKEN, $cbId); qzAdminHome($chatId, $msgId); return true; }

    if (txRoute(qzTextsCfg(), $d, $chatId, $msgId, $cbId)) return true;

    if ($d === 'qzax') {
        qzSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); qzAdminHome($chatId, $msgId); return true;
    }
    if ($d === 'qzatry') {
        qzSet(function (&$c) { $c['one_try'] = empty($c['one_try']) ? 1 : 0; });
        answerCb(BOT_TOKEN, $cbId, '✅'); qzAdminHome($chatId, $msgId); return true;
    }
    if ($d === 'qzapin') {
        qzSet(function (&$c) { $c['pin'] = empty($c['pin']) ? 1 : 0; });
        answerCb(BOT_TOKEN, $cbId, '✅'); qzAdminHome($chatId, $msgId); return true;
    }
    if ($d === 'qzarev') {
        qzSet(function (&$c) { $c['reveal'] = empty($c['reveal']) ? 1 : 0; });
        answerCb(BOT_TOKEN, $cbId, '✅'); qzAdminHome($chatId, $msgId); return true;
    }
    if ($d === 'qzanow') {
        if (!qzOn()) { answerCb(BOT_TOKEN, $cbId, '❌ چالش خاموش است', true); return true; }
        @file_put_contents(DATA_DIR . '/.qz_force', qzTodayKey(), LOCK_EX);
        @unlink(DATA_DIR . '/.qz_gsync');
        $n    = qzSendDaily(true, 10);
        $left = max(0, qzGroupCount() - qzSentCount(qzTodayKey()));
        answerCb(BOT_TOKEN, $cbId, $n
            ? '🚀 ارسال به ' . $n . ' گروه' . ($left ? ' · در صف: ' . $left : '')
            : '⚠️ گروهی برای ارسال نیست', true);
        qzAdminHome($chatId, $msgId);
        return true;
    }

    if ($d === 'qzacolors') { answerCb(BOT_TOKEN, $cbId); qzAdminColors($chatId, $msgId); return true; }
    if (preg_match('/^qzacolk_(\w+)$/', $d, $m) && in_array($m[1], qzBtnKeys(), true)) {
        answerCb(BOT_TOKEN, $cbId); qzAdminColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (preg_match('/^qzacolv_(\w+)_(\w+)$/', $d, $m) && in_array($m[1], qzBtnKeys(), true) && isset(styleMap()[$m[2]])) {
        qzSet(function (&$c) use ($m) {
            if (!is_array($c['btns'][$m[1]] ?? null)) $c['btns'][$m[1]] = [];
            $c['btns'][$m[1]]['color'] = $m[2];
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); qzAdminColorPick($chatId, $msgId, $m[1]); return true;
    }

    if (str_starts_with($d, 'qzats_')) {
        $k = substr($d, strlen('qzats_'));
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'qz_text', ['k' => $k]);
        $cur  = (string)qzVal('texts.' . $k, '');
        $back = inlineKb([[btnCb(UT('back'), 'qzat_home', 'cancel')]]);
        if (qzIsButtonKey($k)) {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ <b>" . h(qzLabel($k)) . "</b>\n\n" .
                "الان: <code>" . h($cur) . "</code>", $back);
        } else {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ <b>" . h(qzLabel($k)) . "</b>\n\n" .
                "جای‌گذاری‌ها ({question}، {o1}، {reward}، {winner}، ...) را دست‌نخورده نگه دار.\n\n" .
                "الان:\n" . $cur, $back);
        }
        return true;
    }

    if (preg_match('/^qzb_(\d+)$/', $d, $m)) { answerCb(BOT_TOKEN, $cbId); qzAdminBank($chatId, $msgId, (int)$m[1]); return true; }
    if (preg_match('/^qzbd_(\d+)$/', $d, $m)) {
        qzBankDelete((int)$m[1]);
        answerCb(BOT_TOKEN, $cbId, '🗑 حذف شد');
        qzAdminBank($chatId, $msgId, 0);
        return true;
    }

    $qc = qzCfg();
    $asks = [
        'qza_min'    => ['qz_min',    "👤 <b>حداقل اعضای گروه</b>\n\nفعلی: <code>" . (int)$qc['min_members'] . "</code>"],
        'qza_time'   => ['qz_time',   "⏰ <b>ساعتِ ارسال</b>\n\nفعلی: <code>" . sprintf('%02d:%02d', (int)$qc['hour'], (int)$qc['minute']) . "</code>"],
        'qza_reward' => ['qz_reward', "🏆 <b>جایزه‌ها</b>\n\nفعلی: <code>" . trim(qzNum($qc['reward']) . ' ' . qzNum($qc['reward2']) . ' ' . qzNum($qc['reward3'])) . "</code>"],
        'qza_dur'    => ['qz_dur',    "⏳ <b>مهلتِ پاسخ (دقیقه)</b>\n\nفعلی: <code>" . max(1, (int)round((int)$qc['duration'] / 60)) . "</code>"],
        'qza_add'    => ['qz_add',    "➕ <b>سوالِ تازه</b>\n\n<code>سوال\nگزینه ۱\nگزینه ۲\nگزینه ۳\nشماره‌ی گزینه‌ی درست\nدسته</code>"],
    ];
    if (isset($asks[$d])) {
        [$act, $ask] = $asks[$d];
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnCb('انصراف', 'qz_home', 'cancel')]]));
        return true;
    }

    return false;
}

function qzStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'qz_')) return false;
    if (!isAdmin($uid)) return false;

    $raw  = (string)($msg['text'] ?? '');
    $text = trim($raw);
    $back = inlineKb([[btnCb('🧠 چالش روزانه', 'qz_home', 'admin')]]);
    $done = function ($m = '✅ ذخیره شد.') use ($uid, $chatId, $back) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, $back);
        return true;
    };

    if ($action === 'qz_min') {
        $t2 = trim(norm_fa_digits($text));
        if (!preg_match('/^\d{1,6}$/', $t2)) { sendMsg(BOT_TOKEN, $chatId, '❌ عدد نامعتبر'); return true; }
        $v = (int)$t2;
        qzSet(function (&$c) use ($v) { $c['min_members'] = $v; });
        return $done('✅ حداقل اعضا: <b>' . $v . '</b>');
    }

    if ($action === 'qz_time') {
        $t2 = norm_fa_digits($text);
        if (!preg_match('/^(\d{1,2})\s*[:._]\s*(\d{1,2})$/', $t2, $m)) {
            sendMsg(BOT_TOKEN, $chatId, '❌ قالب: <code>21:00</code>');
            return true;
        }
        $h = max(0, min(23, (int)$m[1]));
        $i = max(0, min(59, (int)$m[2]));
        qzSet(function (&$c) use ($h, $i) { $c['hour'] = $h; $c['minute'] = $i; });
        return $done(sprintf('✅ ساعتِ ارسال: <b>%02d:%02d</b>', $h, $i));
    }

    if ($action === 'qz_reward') {
        $p = preg_split('/\s+/', norm_fa_digits($text), -1, PREG_SPLIT_NO_EMPTY);
        if (!$p || !is_numeric($p[0]) || (float)$p[0] < 0) {
            sendMsg(BOT_TOKEN, $chatId, '❌ قالب: <code>500 200 100</code>');
            return true;
        }
        $a = (float)$p[0];
        $b = isset($p[1]) && is_numeric($p[1]) ? max(0, (float)$p[1]) : 0.0;
        $c3 = isset($p[2]) && is_numeric($p[2]) ? max(0, (float)$p[2]) : 0.0;
        qzSet(function (&$c) use ($a, $b, $c3) { $c['reward'] = $a; $c['reward2'] = $b; $c['reward3'] = $c3; });
        return $done('✅ جایزه‌ها ثبت شد.');
    }

    if ($action === 'qz_dur') {
        $v = (float)norm_fa_digits($text);
        if ($v <= 0) { sendMsg(BOT_TOKEN, $chatId, '❌ عدد نامعتبر'); return true; }
        $sec = (int)round($v * 60);
        qzSet(function (&$c) use ($sec) { $c['duration'] = max(60, $sec); });
        return $done('✅ مهلت: <b>' . max(1, (int)round($sec / 60)) . '</b> دقیقه');
    }

    if ($action === 'qz_add') {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), fn($l) => $l !== ''));
        if (count($lines) < 5) {
            sendMsg(BOT_TOKEN, $chatId, '❌ ۵ خط لازم است');
            return true;
        }
        $correct = (int)norm_fa_digits($lines[4]);
        if ($correct < 1 || $correct > 3) {
            sendMsg(BOT_TOKEN, $chatId, '❌ خطِ پنجم: ۱ تا ۳');
            return true;
        }
        $cat = $lines[5] ?? '';
        if (!qzBankAdd($lines[0], $lines[1], $lines[2], $lines[3], $correct, $cat)) {
            sendMsg(BOT_TOKEN, $chatId, '❌ دیتابیس در دسترس نیست');
            return true;
        }
        return $done('✅ سوال اضافه شد · بانک: <b>' . qzBankCount() . '</b>');
    }

    if ($action === 'qz_text') {
        $k = (string)((getState($uid)['data'] ?? [])['k'] ?? '');
        if ($k === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ چیزی برای ذخیره نیست.'); return true; }

        if (qzIsButtonKey($k)) {
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
            $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
            $icon = $ids ? (string)$ids[0] : '';
            if ($icon !== '' && function_exists('textWithoutCustomEmoji')) {
                $clean = textWithoutCustomEmoji($msg);
                if ($clean !== '') $text = $clean;
            }
            qzSet(function (&$c) use ($k, $text, $icon) {
                $c['texts'][$k] = $text;
                if (!isset($c['icons']) || !is_array($c['icons'])) $c['icons'] = [];
                $c['icons'][$k] = $icon;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                '✅ ذخیره شد' . ($icon !== '' ? ' — ایموجیِ پریمیوم هم رویِ دکمه نشست.' : '.') . "\n\nاین‌طور دیده می‌شود:",
                inlineKb([[qzBtn($k, [], 'qz_nop')]]));
            sendMsg(BOT_TOKEN, $chatId, '👆', $back);
            return true;
        }

        $html = function_exists('msgHtml') ? msgHtml($msg) : $text;
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
        qzSet(function (&$c) use ($k, $html) { $c['texts'][$k] = $html; });
        return $done();
    }

    return false;
}
