<?php

final class Diamonds
{
    const COOLDOWN = 300;
    const BASE = 56.74;
    const RATIO = 1.2336;
    const MIN = 20;
    const CAP = 1000000000;
    const STEP = 10000;
    const JAIL_WORDS = ['میو', 'هاپ'];
    const JAIL_NEED = 3;
    const JAIL_SECS = 3600;

    public static function get(int $uid): ?array
    {
        return DB::one('SELECT * FROM diamonds WHERE id=?', [$uid]);
    }

    public static function points(int $uid): float
    {
        return (float)(self::get($uid)['points'] ?? 0);
    }

    private static function ensure(int $uid, string $name = '', string $uname = ''): void
    {
        DB::q('INSERT OR IGNORE INTO diamonds(id, points, total, last, jailed_until) VALUES(?, 0, 0, 0, 0)', [$uid]);
    }

    public static function names(array $ids): array
    {
        $out = [];
        $need = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $c = Cache::get("nm:$id", $hit);
            if ($hit && is_array($c)) {
                $out[$id] = $c;
            } else {
                $need[$id] = ['getChat', ['chat_id' => $id]];
            }
        }
        foreach ($need ? TG::multi($need) : [] as $id => $r) {
            $v = ['name' => is_array($r) ? UI::fullName($r) : '', 'username' => is_array($r) ? ($r['username'] ?? null) : null];
            Cache::set("nm:$id", $v, is_array($r) ? 86400 : 3600);
            $out[$id] = $v;
        }
        return $out;
    }

    public static function add(int $uid, float $delta, string $name = '', string $uname = ''): bool
    {
        self::ensure($uid, $name, $uname);
        if ($delta < 0) {
            return DB::q('UPDATE diamonds SET points=ROUND(points + CAST(? AS REAL), 2) WHERE id=? AND points + CAST(? AS REAL) >= -0.000001', [$delta, $uid, $delta])->rowCount() === 1;
        }
        DB::q('UPDATE diamonds SET points=ROUND(points + CAST(? AS REAL), 2) WHERE id=?', [$delta, $uid]);
        return true;
    }

    public static function adjust(int $uid, float $delta): float
    {
        self::ensure($uid);
        DB::q('UPDATE diamonds SET points=MAX(0, ROUND(points + CAST(? AS REAL), 2)) WHERE id=?', [$delta, $uid]);
        return self::points($uid);
    }

    public static function level(float $points): int
    {
        return min(1000, (int)floor(max(0.0, $points) / self::STEP) + 1);
    }

    public static function nextAt(int $level): int
    {
        return $level >= 1000 ? 0 : $level * self::STEP;
    }

    public static function reward(int $level): int
    {
        $max = self::BASE * pow(self::RATIO, max(0, $level - 1));
        if (!is_finite($max) || $max > self::CAP) {
            $max = self::CAP;
        }
        $max = (int)$max;
        if ($max <= self::MIN) {
            return self::MIN;
        }
        if ($level >= 43) {
            $mid = max(self::MIN + 1, (int)($max * 0.60));
            return random_int(1, 100) <= 60 ? random_int(self::MIN, $mid) : random_int($mid, $max);
        }
        return random_int(self::MIN, $max);
    }

    private static function name(array $u): string
    {
        return UI::esc(UI::fullName($u));
    }

    public static function hit(array $m): void
    {
        $u = $m['from'];
        $uid = (int)$u['id'];
        $now = time();
        self::ensure($uid);
        self::touch((int)$m['chat']['id'], $uid);
        Cache::set("nm:$uid", ['name' => UI::fullName($u), 'username' => $u['username'] ?? null], 86400);
        $row = self::get($uid);
        $jail = (int)$row['jailed_until'] - $now;
        if ($jail > 0) {
            Bot::reply($m, UI::t('dm_jail_wait', ['name' => self::name($u), 'm' => intdiv($jail, 60), 's' => $jail % 60]), null);
            return;
        }
        $pdo = DB::pdo();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $row = self::get($uid);
            $left = self::COOLDOWN - ($now - (int)$row['last']);
            if ($left > 0) {
                $pdo->exec('COMMIT');
                if (!Cache::throttled("dmw:$uid", 20)) {
                    Bot::reply($m, UI::t('dm_wait', ['name' => self::name($u), 'm' => intdiv($left, 60), 's' => $left % 60]), null);
                }
                return;
            }
            $level = self::level((float)$row['points']);
            $reward = self::reward($level);
            $points = round((float)$row['points'] + $reward, 2);
            $total = (int)$row['total'] + 1;
            DB::q('UPDATE diamonds SET points=?, total=?, last=? WHERE id=?', [$points, $total, $now, $uid]);
            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
        $new = self::level($points);
        Bot::reply($m, UI::t('dm_win', ['name' => self::name($u), 'reward' => Fmt::int($reward), 'points' => Fmt::int($points), 'level' => $new,
            'total' => Fmt::int($total), 'new_level' => $new > $level ? $new : null]));
    }

    public static function meText(array $u): string
    {
        $row = self::get((int)$u['id']);
        if (!$row || ((float)$row['points'] <= 0 && (int)$row['total'] === 0)) {
            return UI::t('dm_me_none');
        }
        $level = self::level((float)$row['points']);
        $next = self::nextAt($level);
        return UI::t('dm_me', ['name' => UI::esc(UI::fullName($u)), 'points' => Fmt::int($row['points']), 'level' => $level,
            'total' => Fmt::int($row['total']), 'left' => $next > 0 ? Fmt::int(max(0, $next - (float)$row['points'])) : '—']);
    }

    public static function topText(): string
    {
        $rows = DB::all('SELECT id, points FROM diamonds WHERE points > 0 ORDER BY points DESC LIMIT 10');
        if (!$rows) {
            return UI::t('dm_top_none');
        }
        $nm = self::names(array_column($rows, 'id'));
        $lines = [];
        foreach ($rows as $i => $r) {
            $lines[] = UI::fill(UI::template('dm_top_line'), ['medal' => ['👑', '💎', '🏅'][$i] ?? '🔹', 'rank' => $i + 1, 'name' => UI::esc(($nm[(int)$r['id']]['name'] ?? '') !== '' ? $nm[(int)$r['id']]['name'] : UI::l('کاربر') . ' ' . $r['id']),
                'points' => Fmt::int($r['points']), 'level' => self::level((float)$r['points'])]);
        }
        return UI::t('dm_top', ['lines' => implode("\n", $lines)]);
    }

    public static function touch(int $chat, int $uid): void
    {
        static $seen = [];
        if ($chat >= 0 || $uid <= 0 || isset($seen["$chat:$uid"])) {
            return;
        }
        $seen["$chat:$uid"] = true;
        DB::q('INSERT OR REPLACE INTO members(chat, uid, at) VALUES(?, ?, ?)', [$chat, $uid, time()]);
    }

    public static function rankOf(int $uid): array
    {
        $pts = self::points($uid);
        if ($pts <= 0) {
            return [0, 0.0];
        }
        $k = "rk:$uid";
        $c = Cache::get($k);
        if (is_array($c) && (float)$c[1] === $pts) {
            return [(int)$c[0], $pts];
        }
        $rank = (int)(DB::one('SELECT COUNT(*) n FROM diamonds WHERE points > ?', [$pts])['n'] ?? 0) + 1;
        Cache::set($k, [$rank, $pts], 20);
        return [$rank, $pts];
    }

    private static function avatars(array $ids): array
    {
        $dir = Config::$root . '/data/cards';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $out = [];
        $need = [];
        foreach ($ids as $id) {
            $f = "$dir/av_$id.jpg";
            if (is_file($f) && time() - (int)@filemtime($f) < 86400) {
                $b = (string)@file_get_contents($f);
                $out[$id] = strlen($b) > 100 ? $b : null;
                continue;
            }
            $need[$id] = ['getUserProfilePhotos', ['user_id' => $id, 'limit' => 1]];
        }
        if (!$need) {
            return $out;
        }
        $files = [];
        foreach (TG::multi($need) as $id => $r) {
            $sizes = $r['photos'][0] ?? [];
            $pick = null;
            foreach ($sizes as $sz) {
                if (!$pick || (($sz['width'] ?? 0) >= 160 && ($sz['width'] ?? 0) < ($pick['width'] ?? 9999))) {
                    $pick = $sz;
                }
            }
            if ($pick) {
                $files[$id] = ['getFile', ['file_id' => $pick['file_id']]];
            } else {
                @file_put_contents("$dir/av_$id.jpg", '');
                $out[$id] = null;
            }
        }
        $dl = [];
        foreach ($files ? TG::multi($files) : [] as $id => $r) {
            if (!empty($r['file_path'])) {
                $dl[$id] = ['url' => TG::base() . '/file/bot' . Config::get('BOT_TOKEN') . '/' . $r['file_path'], 'timeout' => 6,
                    'proxy' => Config::get('TELEGRAM_PROXY') ?: Config::get('PROXY'), 'headers' => ['Accept' => '*/*']];
            }
        }
        foreach ($dl ? Http::multi($dl) : [] as $id => $r) {
            $b = $r['status'] === 200 ? $r['body'] : '';
            @file_put_contents("$dir/av_$id.jpg", $b);
            $out[$id] = strlen($b) > 100 ? $b : null;
        }
        return $out;
    }

    private static function tag(array $r): string
    {
        return !empty($r['username']) ? '@' . UI::esc($r['username']) : UI::esc(($r['name'] ?? '') !== '' ? $r['name'] : UI::l('کاربر') . ' ' . $r['id']);
    }

    private static function lines(array $rows): string
    {
        $out = [];
        foreach (array_values($rows) as $i => $r) {
            $out[] = UI::fill(UI::template('dm_board_line'), ['medal' => ['🏆', '🏅', '🎖'][$i] ?? '🔹', 'rank' => $i + 1, 'name' => self::tag($r), 'points' => Fmt::int($r['points'])]);
        }
        return implode("\n", $out);
    }

    public static function board(array $m): void
    {
        Tools::react($m, 'board');
        self::boardReply($m);
    }

    private static function boardReply(array $m): void
    {
        $chat = (int)$m['chat']['id'];
        $uid = (int)($m['from']['id'] ?? 0);
        $priv = Bot::isPrivate($m);
        $global = DB::all("SELECT d.id, d.points FROM diamonds d WHERE d.points > 0 ORDER BY d.points DESC LIMIT 3");
        if (!$global) {
            Bot::reply($m, UI::t('dm_top_none'), null);
            return;
        }
        $group = $priv ? [] : DB::all("SELECT d.id, d.points FROM members mb JOIN diamonds d ON d.id = mb.uid WHERE mb.chat = ? AND d.points > 0 ORDER BY d.points DESC LIMIT 5", [$chat]);
        $nm = self::names(array_merge(array_column($global, 'id'), array_column($group, 'id')));
        foreach ([&$global, &$group] as &$list) {
            foreach ($list as &$r) {
                $r += $nm[(int)$r['id']] ?? ['name' => '', 'username' => null];
            }
            unset($r);
        }
        unset($list);
        [$rank, $pts] = self::rankOf($uid);
        $me = $rank > 0 ? UI::fill(UI::template('dm_board_me'), ['rank' => Fmt::int($rank), 'points' => Fmt::int($pts)]) : UI::template('dm_board_me_none');
        $card = PriceCard::ready();
        $caption = UI::t('dm_board', [
            'global' => $card ? null : UI::fill(UI::template('dm_board_global'), ['lines' => self::lines($global)]),
            'group' => $priv ? null : ($group ? UI::fill(UI::template('dm_board_group'), ['lines' => self::lines($group)]) : UI::template('dm_board_group_none')),
            'me' => $me,
        ]);
        if (!$card) {
            Bot::reply($m, $caption);
            return;
        }
        $sig = 'dmb:' . md5(json_encode(array_map(fn($r) => [$r['id'], (int)$r['points'], $r['name'], $r['username']], $global)) . TG::username() . UI::$lang);
        $extra = !$priv ? ['reply_parameters' => Bot::replyParams($m)] : [];
        $fid = Cache::get($sig);
        if ($fid && TG::photo($chat, $fid, $caption, UI::footer(), $extra) !== null) {
            return;
        }
        TG::api('sendChatAction', ['chat_id' => $chat, 'action' => 'upload_photo']);
        $av = self::avatars(array_map(fn($r) => (int)$r['id'], $global));
        foreach ($global as &$r) {
            $r['avatar'] = $av[(int)$r['id']] ?? null;
        }
        unset($r);
        $jpg = PriceCard::leaderboard($global, TG::username());
        if (!$jpg) {
            Bot::reply($m, $caption);
            return;
        }
        $sent = TG::photo($chat, ['__bytes' => $jpg, 'name' => 'top.jpg', 'mime' => 'image/jpeg'], $caption, UI::footer(), $extra);
        $ph = $sent['photo'] ?? [];
        if ($ph && !empty(end($ph)['file_id'])) {
            Cache::set($sig, end($ph)['file_id'], 3600);
        }
    }

    public static function account(array $m): void
    {
        $u = $m['from'];
        $uid = (int)$u['id'];
        $row = self::get($uid);
        $pts = (float)($row['points'] ?? 0);
        $kb = UI::kb([[UI::button('btn_dm_balance', ['cb' => "gb:$uid"], ['points' => Fmt::int($pts)]),
            UI::button('btn_dm_level', ['cb' => "gb:$uid"], ['level' => self::level($pts)])]]);
        Bot::reply($m, UI::t('dm_account', ['name' => self::name($u)]), $kb);
    }

    public static function accountCb(array $cq, string $arg): void
    {
        $uid = (int)$arg;
        $row = self::get($uid);
        $pts = (float)($row['points'] ?? 0);
        [$rank] = self::rankOf($uid);
        TG::answer($cq['id'], UI::plain('dm_account_alert', ['points' => Fmt::int($pts), 'level' => self::level($pts), 'rank' => $rank ? Fmt::int($rank) : '—',
            'total' => Fmt::int($row['total'] ?? 0)]), true);
    }

    private static function jailKb(string $id): array
    {
        $b = UI::button('btn_jail', ['cb' => "gj:$id"]);
        return UI::kb(array_chunk(array_fill(0, self::JAIL_NEED, $b), 2));
    }

    public static function jailStart(array $m): void
    {
        $u = $m['from'];
        $chat = (int)$m['chat']['id'];
        $live = DB::one('SELECT COUNT(*) c, SUM(target=?) mine FROM jails WHERE chat=? AND created>?', [$u['id'], $chat, time() - 600]);
        if ((int)($live['mine'] ?? 0) > 0 || (int)($live['c'] ?? 0) >= 5) {
            return;
        }
        $id = bin2hex(random_bytes(4));
        DB::q('INSERT INTO jails(id, chat, target, name, msg, confirmed, created) VALUES(?, ?, ?, ?, 0, ?, ?)', [$id, $chat, $u['id'], UI::fullName($u), '[]', time()]);
        $sent = Bot::send($m, UI::t('dm_jail_hit', ['name' => self::name($u), 'need' => self::JAIL_NEED]), self::jailKb($id));
        if (!empty($sent['message_id'])) {
            DB::q('UPDATE jails SET msg=? WHERE id=?', [$sent['message_id'], $id]);
        }
    }

    public static function jailCb(array $cq, string $id): void
    {
        $uid = (int)$cq['from']['id'];
        $j = DB::one('SELECT * FROM jails WHERE id=?', [$id]);
        if (!$j || time() - (int)$j['created'] > 3600) {
            TG::answer($cq['id'], UI::l('این دیگر معتبر نیست.'), true);
            return;
        }
        if ((int)$j['target'] === $uid) {
            TG::answer($cq['id'], UI::plain('dm_jail_self'), true);
            return;
        }
        $conf = json_decode($j['confirmed'], true) ?: [];
        if (in_array($uid, $conf, true)) {
            TG::answer($cq['id'], UI::plain('dm_jail_twice'), true);
            return;
        }
        $conf[] = $uid;
        TG::answer($cq['id'], '✅');
        $name = UI::esc($j['name']);
        if (count($conf) >= self::JAIL_NEED) {
            DB::q('DELETE FROM jails WHERE id=?', [$id]);
            self::ensure((int)$j['target']);
            DB::q('UPDATE diamonds SET jailed_until=? WHERE id=?', [time() + self::JAIL_SECS, $j['target']]);
            TG::edit((int)$j['chat'], (int)$j['msg'], UI::t('dm_jail_done', ['name' => $name, 'dur' => Fmt::duration(intdiv(self::JAIL_SECS, 60))]), null);
            return;
        }
        DB::q('UPDATE jails SET confirmed=? WHERE id=?', [json_encode($conf), $id]);
        TG::edit((int)$j['chat'], (int)$j['msg'], UI::t('dm_jail_prog', ['name' => $name, 'got' => count($conf), 'need' => self::JAIL_NEED]), self::jailKb($id));
    }
}

final class Games
{
    const WORDS_DIAMOND = ['الماس', 'diamond', 'алмаз'];
    const WORDS_ME = ['الماس من', 'امتیاز من', 'امتیازم', 'حساب الماس', 'موجودی', 'موجودی من', 'my diamonds', 'мои алмазы'];
    const WORDS_TOP = ['برترین ها', 'برترین', 'برترینها', 'رتبه بندی', 'رتبه', 'لیدربورد', 'لیدر بورد', 'لیدربرد', 'لیدر برد', 'لیدبورد', 'leaderboard', 'leader board', 'top', 'تاپ',
        'تاپ الماسی', 'تاپ الماس', 'برترین های الماسی', 'برترین های الماس', 'top diamond', 'تاپ لیست', 'تاپلیست', 'топ', 'рейтинг', 'лидеры'];
    const WORDS_ACCOUNT = ['حساب', 'حساب من', 'balance', 'баланс'];
    const DOOZ = ['دوز', 'xo', 'крестики'];
    const MINE = ['مین', 'mine', 'мины'];
    const DOOZ_MIN = 10;
    const DOOZ_MAX = 1000000000;
    const TAX = 10;
    const OPEN_MAX = 2;
    const EXPIRE = 180;
    const MINE_MIN = 10;
    const MINE_MAX = 1000000;
    const MINE_REWARDS = [100, 150, 250, 400, 600, 900, 1300, 1900];
    const MINE_EDGE = 5;
    const MINE_PROTECT = 3;
    const MINE_WAIT = 60;
    const MINE_TIMEOUT = 1800;

    private static function norm(string $t): string
    {
        $t = str_replace(["\u{200C}", "\u{200F}", "\u{200E}", 'ي', 'ك'], [' ', '', '', 'ی', 'ک'], Fmt::en($t));
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $t)));
    }

    private static function amount(string $t, array $words): ?float
    {
        $t = trim(Fmt::en($t));
        foreach ($words as $w) {
            $q = preg_quote($w, '/');
            if (preg_match('/^' . $q . '\s+([\d,٬]+)$/u', $t, $mm) || preg_match('/^([\d,٬]+)\s+' . $q . '$/u', $t, $mm)) {
                $n = (float)str_replace([',', '٬'], '', $mm[1]);
                return $n > 0 ? $n : null;
            }
        }
        return null;
    }

    public static function text(array $m): bool
    {
        $text = trim($m['text'] ?? '');
        if ($text === '' || mb_strlen($text) > 40 || empty($m['from'])) {
            return false;
        }
        $t = self::norm($text);
        $priv = Bot::isPrivate($m);
        $hit = in_array($t, self::WORDS_DIAMOND, true);
        $jail = in_array($t, self::norms(Diamonds::JAIL_WORDS), true);
        $dooz = self::amount($text, self::DOOZ);
        $mine = self::amount($text, self::MINE);
        $bareDooz = in_array($t, self::DOOZ, true);
        $bareMine = in_array($t, self::MINE, true);
        if ($priv && ($hit || $jail || $dooz || $mine || $bareDooz || $bareMine)) {
            Bot::reply($m, UI::t('gm_group_only'), null);
            return true;
        }
        if (in_array($t, self::norms(self::WORDS_ME), true)) {
            Bot::reply($m, Diamonds::meText($m['from']), null);
            return true;
        }
        if (in_array($t, self::norms(self::WORDS_TOP), true)) {
            Diamonds::board($m);
            return true;
        }
        if (in_array($t, self::norms(self::WORDS_ACCOUNT), true)) {
            Diamonds::account($m);
            return true;
        }
        if ($priv) {
            return false;
        }
        if ($hit) {
            Diamonds::hit($m);
            return true;
        }
        if ($jail) {
            Diamonds::jailStart($m);
            return true;
        }
        if ($bareDooz) {
            Bot::reply($m, UI::t('gm_dooz_how', ['min' => Fmt::int(self::DOOZ_MIN), 'max' => Fmt::int(self::DOOZ_MAX)]), null);
            return true;
        }
        if ($bareMine) {
            Bot::reply($m, UI::t('gm_mine_how', ['min' => Fmt::int(self::MINE_MIN), 'max' => Fmt::int(self::MINE_MAX)]), null);
            return true;
        }
        if ($dooz) {
            self::doozCreate($m, $dooz);
            return true;
        }
        if ($mine) {
            self::mineCreate($m, $mine);
            return true;
        }
        return false;
    }

    private static function norms(array $w): array
    {
        return array_map(fn($x) => self::norm($x), $w);
    }

    private static function put(array $g): void
    {
        DB::q('INSERT OR REPLACE INTO games(id, kind, status, host, chat, created, data) VALUES(?, ?, ?, ?, ?, ?, ?)',
            [$g['id'], $g['kind'], $g['status'], $g['host'], $g['chat'], $g['created'], json_encode($g, JSON_UNESCAPED_UNICODE)]);
    }

    public static function load(string $id): ?array
    {
        $r = DB::one('SELECT data FROM games WHERE id=?', [$id]);
        return $r ? json_decode($r['data'], true) : null;
    }

    private static function change(string $id, callable $fn)
    {
        $pdo = DB::pdo();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $g = self::load($id);
            if (!$g) {
                $pdo->exec('COMMIT');
                return null;
            }
            $res = $fn($g);
            if ($res !== false) {
                self::put($g);
            }
            $pdo->exec('COMMIT');
            return $res;
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    private static function player(array $u, int $mark): array
    {
        return ['id' => (int)$u['id'], 'name' => UI::fullName($u), 'uname' => (string)($u['username'] ?? ''), 'mark' => $mark];
    }

    private static function pname(array $p): string
    {
        $dot = ($p['mark'] ?? 1) === 1 ? '🔴' : '🟢';
        return $dot . ' ' . (!empty($p['uname']) ? '@' . UI::esc($p['uname']) : UI::esc($p['name'] ?? (string)$p['id']));
    }

    private static function prize(float $stake): array
    {
        $pot = $stake * 2;
        $tax = floor($pot * self::TAX / 100);
        return [$pot - $tax, $tax];
    }

    private static function doozText(array $g): string
    {
        [$prize, $tax] = self::prize((float)$g['stake']);
        $ps = array_values($g['players']);
        if ($g['status'] === 'open') {
            return UI::t('gm_dooz_open', ['stake' => Fmt::int($g['stake']), 'host' => self::pname($ps[0]), 'prize' => Fmt::int($prize), 'tax' => Fmt::int($tax)]);
        }
        return UI::t('gm_dooz_turn', ['stake' => Fmt::int($g['stake']), 'p1' => self::pname($ps[0]), 'p2' => self::pname($ps[1] ?? ['id' => 0, 'mark' => 2]),
            'prize' => Fmt::int($prize), 'turn' => self::pname($g['players'][(string)$g['turn']] ?? $ps[0])]);
    }

    private static function doozKb(array $g): ?array
    {
        if ($g['status'] === 'open') {
            return UI::kb([[UI::button('btn_gm_join', ['cb' => 'gd:j:' . $g['id']]), UI::button('btn_gm_cancel', ['cb' => 'gd:c:' . $g['id']])]]);
        }
        if ($g['status'] !== 'playing') {
            return null;
        }
        $rows = [];
        for ($r = 0; $r < 3; $r++) {
            $line = [];
            for ($c = 0; $c < 3; $c++) {
                $i = $r * 3 + $c;
                $v = (int)$g['board'][$i];
                $b = ['text' => $v === 0 ? '·' : ($v === 1 ? '🔴' : '🟢'), 'callback_data' => 'gd:m:' . $g['id'] . ':' . $i];
                if ($v !== 0) {
                    $b['style'] = $v === 1 ? 'danger' : 'success';
                }
                $line[] = $b;
            }
            $rows[] = $line;
        }
        $rows[] = [UI::button('btn_gm_cancel', ['cb' => 'gd:c:' . $g['id']])];
        return UI::kb($rows);
    }

    private static function show(array $g, ?array $m = null): void
    {
        if (empty($g['msg'])) {
            $sent = $m ? Bot::send($m, self::doozText($g), self::doozKb($g)) : TG::send($g['chat'], self::doozText($g), self::doozKb($g));
            if (!empty($sent['message_id'])) {
                self::change($g['id'], function (&$x) use ($sent) {
                    $x['msg'] = (int)$sent['message_id'];
                });
            }
            return;
        }
        TG::edit($g['chat'], (int)$g['msg'], self::doozText($g), self::doozKb($g));
    }

    private static function doozCreate(array $m, float $stake): void
    {
        $u = $m['from'];
        $uid = (int)$u['id'];
        if ($stake < self::DOOZ_MIN || $stake > self::DOOZ_MAX) {
            Bot::reply($m, UI::t('gm_bad_stake', ['min' => Fmt::int(self::DOOZ_MIN), 'max' => Fmt::int(self::DOOZ_MAX)]), null);
            return;
        }
        $open = (int)DB::one("SELECT COUNT(*) c FROM games WHERE kind='dooz' AND host=? AND status IN ('open','playing')", [$uid])['c'];
        if ($open >= self::OPEN_MAX) {
            Bot::reply($m, UI::t('gm_open_max', ['n' => Fmt::int($open)]), null);
            return;
        }
        if (!Diamonds::add($uid, -$stake, UI::fullName($u), (string)($u['username'] ?? ''))) {
            Bot::reply($m, UI::t('gm_low', ['points' => Fmt::int(Diamonds::points($uid)), 'need' => Fmt::int($stake)]), null);
            return;
        }
        $g = ['id' => bin2hex(random_bytes(5)), 'kind' => 'dooz', 'chat' => (int)$m['chat']['id'], 'msg' => 0, 'host' => $uid, 'stake' => $stake,
            'players' => [(string)$uid => self::player($u, 1)], 'board' => array_fill(0, 9, 0), 'turn' => $uid, 'status' => 'open', 'created' => time(), 'moved' => time()];
        self::put($g);
        self::show($g, $m);
    }

    private static function line(array $b): int
    {
        foreach ([[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8], [0, 4, 8], [2, 4, 6]] as [$x, $y, $z]) {
            if ($b[$x] !== 0 && $b[$x] === $b[$y] && $b[$y] === $b[$z]) {
                return $b[$x];
            }
        }
        return 0;
    }

    private static function finish(array $g, int $winner, int $loser): void
    {
        [$prize] = self::prize((float)$g['stake']);
        $ok = self::change($g['id'], function (&$x) use ($winner, $loser) {
            if (!in_array($x['status'], ['open', 'playing'], true)) {
                return false;
            }
            $x['status'] = 'done';
            $x['winner'] = $winner;
            $x['loser'] = $loser;
            return true;
        });
        if ($ok !== true) {
            return;
        }
        $w = $g['players'][(string)$winner] ?? ['id' => $winner, 'mark' => 1];
        $l = $g['players'][(string)$loser] ?? null;
        Diamonds::add($winner, $prize, $w['name'] ?? '', $w['uname'] ?? '');
        $text = UI::t('gm_dooz_win', ['wname' => self::pname($w), 'lname' => $l ? self::pname($l) : '—', 'prize' => Fmt::int($prize)]);
        $kb = UI::kb(array_values(array_filter([[UI::button('btn_gm_wbal', ['cb' => "gd:b:$winner"])], $loser ? [UI::button('btn_gm_lbal', ['cb' => "gd:b:$loser"])] : null])));
        !empty($g['msg']) ? TG::edit($g['chat'], (int)$g['msg'], $text, $kb) : TG::send($g['chat'], $text, $kb);
    }

    private static function refund(array $g, string $text, array $only = ['open', 'playing']): void
    {
        $back = self::change($g['id'], function (&$x) use ($only) {
            if (!in_array($x['status'], $only, true)) {
                return false;
            }
            $x['status'] = 'cancelled';
            return ['players' => $x['players'], 'stake' => (float)$x['stake']];
        });
        if (!is_array($back)) {
            return;
        }
        foreach ($back['players'] as $p) {
            Diamonds::add((int)$p['id'], $back['stake']);
        }
        !empty($g['msg']) ? TG::edit($g['chat'], (int)$g['msg'], $text, null) : TG::send($g['chat'], $text);
    }

    public static function doozCb(array $cq, string $arg): void
    {
        $p = explode(':', $arg);
        $act = $p[0] ?? '';
        $uid = (int)$cq['from']['id'];
        if ($act === 'n') {
            TG::answer($cq['id']);
            return;
        }
        if ($act === 'b') {
            $who = (int)($p[1] ?? 0);
            $row = Diamonds::get($who);
            TG::answer($cq['id'], UI::plain('gm_bal_pop', ['name' => ($row['name'] ?? '') ?: (string)$who, 'points' => Fmt::int($row['points'] ?? 0)]), true);
            return;
        }
        $g = self::load($p[1] ?? '');
        if (!$g || !in_array($g['status'], ['open', 'playing'], true)) {
            TG::answer($cq['id'], UI::plain('gm_gone'), true);
            return;
        }
        $u = $cq['from'];
        if ($act === 'c') {
            if ($g['status'] === 'playing') {
                if (!isset($g['players'][(string)$uid])) {
                    TG::answer($cq['id'], UI::plain('gm_not_yours'), true);
                    return;
                }
                $ids = array_map('intval', array_keys($g['players']));
                TG::answer($cq['id'], '🏳️');
                self::finish($g, $ids[0] === $uid ? $ids[1] : $ids[0], $uid);
                return;
            }
            if ((int)$g['host'] !== $uid) {
                TG::answer($cq['id'], UI::plain('gm_not_yours'), true);
                return;
            }
            TG::answer($cq['id'], '❌');
            self::refund($g, UI::t('gm_cancelled'), ['open']);
            return;
        }
        if ($act === 'j') {
            if (isset($g['players'][(string)$uid])) {
                TG::answer($cq['id'], UI::plain('gm_already'), true);
                return;
            }
            if (count($g['players']) >= 2) {
                TG::answer($cq['id'], UI::plain('gm_gone'), true);
                return;
            }
            if (!Diamonds::add($uid, -(float)$g['stake'], UI::fullName($u), (string)($u['username'] ?? ''))) {
                TG::answer($cq['id'], UI::plain('gm_low', ['points' => Fmt::int(Diamonds::points($uid)), 'need' => Fmt::int($g['stake'])]), true);
                return;
            }
            $joined = self::change($g['id'], function (&$x) use ($u, $uid) {
                if ($x['status'] !== 'open' || isset($x['players'][(string)$uid]) || count($x['players']) >= 2) {
                    return false;
                }
                $x['players'][(string)$uid] = self::player($u, 2);
                $x['status'] = 'playing';
                $x['turn'] = (int)$x['host'];
                $x['moved'] = time();
                return true;
            });
            if ($joined !== true) {
                Diamonds::add($uid, (float)$g['stake']);
                TG::answer($cq['id'], UI::plain('gm_gone'), true);
                return;
            }
            TG::answer($cq['id'], '✅');
            self::show(self::load($g['id']));
            return;
        }
        if ($act === 'm') {
            $cell = (int)($p[2] ?? -1);
            if ($g['status'] !== 'playing' || $cell < 0 || $cell > 8) {
                TG::answer($cq['id'], UI::plain('gm_gone'), true);
                return;
            }
            if (!isset($g['players'][(string)$uid])) {
                TG::answer($cq['id'], UI::plain('gm_not_yours'), true);
                return;
            }
            if ((int)$g['turn'] !== $uid) {
                TG::answer($cq['id'], UI::plain('gm_not_turn'), true);
                return;
            }
            $ids = array_map('intval', array_keys($g['players']));
            $mark = (int)$g['players'][(string)$uid]['mark'];
            $next = $ids[0] === $uid ? $ids[1] : $ids[0];
            $moved = self::change($g['id'], function (&$x) use ($cell, $mark, $next, $uid) {
                if ((int)$x['turn'] !== $uid || (int)$x['board'][$cell] !== 0 || $x['status'] !== 'playing') {
                    return false;
                }
                $x['board'][$cell] = $mark;
                $x['turn'] = $next;
                $x['moved'] = time();
                return true;
            });
            if ($moved !== true) {
                TG::answer($cq['id'], UI::plain('gm_taken'), true);
                return;
            }
            TG::answer($cq['id']);
            $g = self::load($g['id']);
            $w = self::line(array_map('intval', $g['board']));
            if ($w !== 0) {
                $winner = $uid;
                self::finish($g, $winner, $next);
                return;
            }
            if (!in_array(0, array_map('intval', $g['board']), true)) {
                self::refund($g, UI::t('gm_dooz_draw'));
                return;
            }
            self::show($g);
        }
    }

    private static function mineReward(float $entry, int $k): float
    {
        $k = max(0, min(8, $k));
        if ($k === 0) {
            return 0.0;
        }
        $cum = array_sum(array_slice(self::MINE_REWARDS, 0, $k));
        $fair = $entry * (1 - self::MINE_EDGE / 100) * 9 / (9 - $k);
        return (float)floor(min($cum, $fair));
    }

    private static function mineRows(array $g, bool $reveal = false): array
    {
        $rows = [];
        for ($r = 0; $r < 3; $r++) {
            $row = [];
            for ($c = 1; $c <= 3; $c++) {
                $pos = $r * 3 + $c;
                if ($reveal && $pos === (int)$g['mine']) {
                    $row[] = ['text' => '🔥', 'callback_data' => 'gn:n', 'style' => 'danger'];
                } elseif (in_array($pos, $g['selected'], true)) {
                    $row[] = ['text' => '💎', 'callback_data' => 'gn:n', 'style' => 'success'];
                } elseif ($reveal) {
                    $row[] = ['text' => '▫️', 'callback_data' => 'gn:n'];
                } elseif ($g['status'] === 'waiting') {
                    $row[] = ['text' => '🔒', 'callback_data' => 'gn:w:' . $g['id']];
                } else {
                    $row[] = ['text' => '⬛️', 'callback_data' => 'gn:p:' . $g['id'] . ':' . $pos];
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private static function mineKb(array $g): array
    {
        $rows = array_merge([[UI::button('btn_mine_field', ['cb' => 'gn:n'])]], self::mineRows($g));
        if ($g['status'] === 'waiting') {
            $rows[] = [UI::button('btn_gm_cancel', ['cb' => 'gn:x:' . $g['id']]), UI::button('btn_gm_join', ['cb' => 'gn:j:' . $g['id']])];
        } elseif ((int)$g['safe'] >= 1) {
            $rows[] = [UI::button('btn_mine_cash', ['cb' => 'gn:c:' . $g['id']])];
        }
        return UI::kb($rows);
    }

    private static function mineText(array $g): string
    {
        if ($g['status'] === 'waiting') {
            return UI::t('gm_mine_preview', ['entry' => Fmt::int($g['entry'])]);
        }
        $picks = (int)$g['safe'];
        $entry = (float)$g['entry'];
        $reward = (float)$g['reward'];
        $next = $picks < 8 ? max(0, self::mineReward($entry, $picks + 1) - $reward) : 0;
        return UI::t('gm_mine_active', ['name' => UI::esc($g['name']), 'picks' => $picks, 'progress' => str_repeat('🟢', $picks) . str_repeat('🔲', 8 - $picks),
            'entry' => Fmt::int($entry), 'reward' => Fmt::int($reward), 'mult' => $entry > 0 ? round($reward / $entry, 2) : 0,
            'shield' => $picks >= self::MINE_PROTECT ? UI::plain('gm_shield_on') : UI::plain('gm_shield_off', ['n' => self::MINE_PROTECT - $picks]), 'next' => Fmt::int($next)]);
    }

    private static function mineCreate(array $m, float $entry): void
    {
        $u = $m['from'];
        $uid = (int)$u['id'];
        if ($entry < self::MINE_MIN || $entry > self::MINE_MAX) {
            Bot::reply($m, UI::t('gm_mine_bad', ['min' => Fmt::int(self::MINE_MIN), 'max' => Fmt::int(self::MINE_MAX)]), null);
            return;
        }
        if ((int)DB::one("SELECT COUNT(*) c FROM games WHERE kind='mine' AND host=? AND status IN ('waiting','active')", [$uid])['c'] > 0) {
            Bot::reply($m, UI::t('gm_mine_busy'), null);
            return;
        }
        if (Diamonds::points($uid) < $entry) {
            Bot::reply($m, UI::t('gm_mine_low', ['need' => Fmt::int($entry), 'wallet' => Fmt::int(Diamonds::points($uid))]), null);
            return;
        }
        $g = ['id' => bin2hex(random_bytes(6)), 'kind' => 'mine', 'chat' => (int)$m['chat']['id'], 'msg' => 0, 'host' => $uid, 'name' => UI::fullName($u),
            'uname' => (string)($u['username'] ?? ''), 'entry' => $entry, 'mine' => random_int(1, 9), 'selected' => [], 'safe' => 0, 'reward' => 0.0,
            'status' => 'waiting', 'created' => time()];
        self::put($g);
        $sent = Bot::send($m, self::mineText($g), self::mineKb($g));
        if (!empty($sent['message_id'])) {
            self::change($g['id'], function (&$x) use ($sent) {
                $x['msg'] = (int)$sent['message_id'];
            });
        }
    }

    public static function mineCb(array $cq, string $arg): void
    {
        $p = explode(':', $arg);
        $act = $p[0] ?? '';
        if ($act === 'n') {
            TG::answer($cq['id']);
            return;
        }
        $uid = (int)$cq['from']['id'];
        $chat = $cq['message']['chat']['id'] ?? 0;
        $mid = (int)($cq['message']['message_id'] ?? 0);
        $g = self::load($p[1] ?? '');
        if (!$g) {
            TG::answer($cq['id'], UI::plain('gm_gone'), true);
            return;
        }
        if ((int)$g['host'] !== $uid) {
            TG::answer($cq['id'], UI::plain('gm_not_yours'), true);
            return;
        }
        if ($act === 'w') {
            TG::answer($cq['id'], UI::plain('gm_mine_need_join'), true);
            return;
        }
        if ($act === 'x') {
            $ok = self::change($g['id'], function (&$x) {
                if ($x['status'] !== 'waiting') {
                    return false;
                }
                $x['status'] = 'cancelled';
                return true;
            });
            TG::answer($cq['id'], $ok === true ? null : UI::plain('gm_gone'), $ok !== true);
            if ($ok === true) {
                TG::edit($chat, $mid, UI::t('gm_mine_cancelled'), null);
            }
            return;
        }
        if ($act === 'j') {
            if ($g['status'] !== 'waiting') {
                TG::answer($cq['id'], UI::plain('gm_gone'), true);
                return;
            }
            if (!Diamonds::add($uid, -(float)$g['entry'])) {
                TG::answer($cq['id'], UI::plain('gm_mine_pop_low', ['need' => Fmt::int($g['entry'])]), true);
                return;
            }
            $ok = self::change($g['id'], function (&$x) {
                if ($x['status'] !== 'waiting') {
                    return false;
                }
                $x['status'] = 'active';
                $x['joined'] = time();
                return true;
            });
            if ($ok !== true) {
                Diamonds::add($uid, (float)$g['entry']);
                TG::answer($cq['id'], UI::plain('gm_gone'), true);
                return;
            }
            TG::answer($cq['id'], '🎮');
            $g = self::load($g['id']);
            TG::edit($chat, $mid, self::mineText($g), self::mineKb($g));
            return;
        }
        if ($act === 'c') {
            $out = self::change($g['id'], function (&$x) {
                if ($x['status'] !== 'active' || (int)$x['safe'] < 1) {
                    return false;
                }
                $x['status'] = 'cashed';
                return (float)$x['reward'];
            });
            if ($out === false || $out === null) {
                TG::answer($cq['id'], UI::plain('gm_gone'), true);
                return;
            }
            Diamonds::add($uid, (float)$out);
            TG::answer($cq['id'], '🏆');
            TG::edit($chat, $mid, UI::t('gm_mine_cashout', ['reward' => Fmt::int($out), 'wallet' => Fmt::int(Diamonds::points($uid))]), UI::kb(self::mineRows(self::load($g['id']), true)));
            return;
        }
        if ($act === 'p') {
            $pos = (int)($p[2] ?? 0);
            if ($pos < 1 || $pos > 9) {
                TG::answer($cq['id']);
                return;
            }
            $out = self::change($g['id'], function (&$x) use ($pos) {
                if ($x['status'] !== 'active') {
                    return ['err' => 'gone'];
                }
                if (in_array($pos, $x['selected'], true)) {
                    return ['err' => 'already'];
                }
                if ($pos === (int)$x['mine']) {
                    $x['status'] = (int)$x['safe'] >= self::MINE_PROTECT ? 'protected' : 'lost';
                    return ['tier' => $x['status'], 'picks' => (int)$x['safe']];
                }
                $x['selected'][] = $pos;
                $x['safe'] = (int)$x['safe'] + 1;
                $x['reward'] = self::mineReward((float)$x['entry'], (int)$x['safe']);
                if ((int)$x['safe'] >= 8) {
                    $x['status'] = 'won';
                    return ['tier' => 'won', 'reward' => $x['reward']];
                }
                return ['tier' => 'safe'];
            });
            if (!is_array($out) || isset($out['err'])) {
                TG::answer($cq['id'], UI::plain(($out['err'] ?? '') === 'already' ? 'gm_mine_already' : 'gm_gone'), true);
                return;
            }
            $g = self::load($g['id']);
            if ($out['tier'] === 'safe') {
                TG::answer($cq['id'], '💎');
                TG::edit($chat, $mid, self::mineText($g), self::mineKb($g));
                return;
            }
            $kb = UI::kb(self::mineRows($g, true));
            if ($out['tier'] === 'won') {
                Diamonds::add($uid, (float)$out['reward']);
                TG::answer($cq['id'], '🏆');
                TG::edit($chat, $mid, UI::t('gm_mine_cashout', ['reward' => Fmt::int($out['reward']), 'wallet' => Fmt::int(Diamonds::points($uid))]), $kb);
                return;
            }
            TG::answer($cq['id'], $out['tier'] === 'lost' ? '🔥' : '🛡', $out['tier'] === 'lost');
            TG::edit($chat, $mid, UI::t($out['tier'] === 'lost' ? 'gm_mine_lost' : 'gm_mine_protected', ['picks' => $out['picks'], 'entry' => Fmt::int($g['entry'])]), $kb);
        }
    }

    public static function tick(): void
    {
        $now = time();
        $was = UI::$lang;
        foreach (DB::all("SELECT data FROM games WHERE status IN ('open','playing','waiting','active') AND created < ? LIMIT 50", [$now - 60]) as $r) {
            $g = json_decode($r['data'], true);
            if (!$g) {
                continue;
            }
            Lang::use($g['chat'] ?? 0);
            if ($g['kind'] === 'dooz') {
                if ($g['status'] === 'playing' && $now - (int)($g['moved'] ?? $g['created']) >= self::EXPIRE) {
                    $ids = array_map('intval', array_keys($g['players']));
                    $turn = (int)$g['turn'];
                    count($ids) === 2 && in_array($turn, $ids, true) ? self::finish($g, $ids[0] === $turn ? $ids[1] : $ids[0], $turn) : self::refund($g, UI::t('gm_idle'));
                } elseif ($g['status'] === 'open' && $now - (int)$g['created'] >= self::EXPIRE) {
                    self::refund($g, UI::t('gm_expired'));
                }
                continue;
            }
            $limit = $g['status'] === 'waiting' ? self::MINE_WAIT : self::MINE_TIMEOUT;
            if (in_array($g['status'], ['waiting', 'active'], true) && $now - (int)$g['created'] >= $limit) {
                $done = self::change($g['id'], function (&$x) {
                    if (!in_array($x['status'], ['waiting', 'active'], true)) {
                        return false;
                    }
                    $x['status'] = 'expired';
                    return true;
                });
                if ($done === true && !empty($g['msg'])) {
                    TG::edit($g['chat'], (int)$g['msg'], UI::t('gm_mine_expired'), null);
                }
            }
        }
        UI::$lang = $was;
        DB::q("DELETE FROM games WHERE status NOT IN ('open','playing','waiting','active') AND created < ?", [$now - 3 * 86400]);
        DB::q('DELETE FROM jails WHERE created < ?', [$now - 3600]);
    }
}
