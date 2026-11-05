<?php

final class Config
{
    private static array $vars = [];
    public static string $root = '';

    public static function load(string $root): void
    {
        self::$root = $root;
        $file = $root . '/.env';
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            if (strlen($v) > 1 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                $v = substr($v, 1, -1);
            }
            self::$vars[trim($k)] = $v;
        }
    }

    public static function set(string $k, string $v): void
    {
        self::$vars[$k] = $v;
    }

    public static function get(string $k, string $d = ''): string
    {
        if (isset(self::$vars[$k]) && self::$vars[$k] !== '') {
            return self::$vars[$k];
        }
        $e = getenv($k);
        return ($e !== false && $e !== '') ? $e : $d;
    }

    public static function version(): string
    {
        return trim((string)@file_get_contents(__DIR__ . '/data/version.txt')) ?: 'dev';
    }

    public static function admins(): array
    {
        $out = [];
        foreach (preg_split('/[,;\s]+/', self::get('ADMIN_IDS')) as $p) {
            if (preg_match('/^-?\d+$/', $p)) {
                $out[] = (int)$p;
            }
        }
        return $out;
    }

    public static function isAdmin(?int $id): bool
    {
        return $id !== null && in_array($id, self::admins(), true);
    }

    public static function data(string $name): array
    {
        static $cache = [];
        if (!isset($cache[$name])) {
            $f = __DIR__ . '/data/' . $name . '.json';
            $cache[$name] = self::compiled('d_' . $name, [$f], fn() => json_decode((string)@file_get_contents($f), true) ?: []);
        }
        return $cache[$name];
    }

    public static function compiled(string $key, array $sources, callable $build): array
    {
        $sig = '';
        foreach ($sources as $f) {
            $sig .= $f . ':' . @filemtime($f) . ':' . @filesize($f) . ';';
        }
        $dir = (self::$root ?: dirname(__DIR__)) . '/data/compiled';
        $file = $dir . '/' . $key . '-' . hash('crc32b', $sig . PHP_VERSION) . '.php';
        if (is_file($file)) {
            $v = @include $file;
            if (is_array($v)) {
                return $v;
            }
        }
        $v = $build();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, '<?php return ' . var_export($v, true) . ';') !== false && @rename($tmp, $file)) {
            foreach (glob($dir . '/' . $key . '-*.php') ?: [] as $old) {
                if ($old !== $file) {
                    @unlink($old);
                }
            }
        } else {
            @unlink($tmp);
        }
        return $v;
    }
}

final class DB
{
    private static ?PDO $pdo = null;
    private static array $kv = [];
    private static ?array $texts = null;

    public static function connect(?string $path = null): void
    {
        $path = $path ?: (Config::get('DB_PATH') ?: Config::$root . '/data/bot.db');
        if ($path[0] !== '/' && !preg_match('/^[A-Za-z]:/', $path)) {
            $path = Config::$root . '/' . $path;
        }
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        self::$pdo = new PDO('sqlite:' . $path);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA busy_timeout=8000');
        self::$pdo->exec('PRAGMA synchronous=NORMAL');
        self::$pdo->exec('PRAGMA temp_store=MEMORY');
        self::$pdo->exec('PRAGMA cache_size=-8000');
        self::$pdo->exec('PRAGMA mmap_size=134217728');
        $ver = (int)self::$pdo->query('PRAGMA user_version')->fetchColumn();
        if ($ver < 6) {
            self::$pdo->exec('PRAGMA journal_mode=WAL');
            self::$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS texts (k TEXT PRIMARY KEY, v TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, started INTEGER DEFAULT 0, banned INTEGER DEFAULT 0, first_seen INTEGER);
CREATE TABLE IF NOT EXISTS chats (id INTEGER PRIMARY KEY, type TEXT, title TEXT, username TEXT, added_at INTEGER, active INTEGER DEFAULT 1, settings TEXT DEFAULT '{}');
CREATE TABLE IF NOT EXISTS scams (id INTEGER PRIMARY KEY AUTOINCREMENT, target_id INTEGER, target_username TEXT, target_name TEXT, reporter_id INTEGER, reason TEXT, evidence TEXT DEFAULT '[]', status TEXT DEFAULT 'draft', origin_chat INTEGER, created_at INTEGER, decided_at INTEGER, decided_by INTEGER);
CREATE INDEX IF NOT EXISTS idx_scams_target ON scams(target_id);
CREATE INDEX IF NOT EXISTS idx_scams_username ON scams(target_username);
CREATE TABLE IF NOT EXISTS strikes (chat_id INTEGER, user_id INTEGER, kind TEXT, count INTEGER DEFAULT 0, updated_at INTEGER, PRIMARY KEY (chat_id, user_id, kind));
CREATE TABLE IF NOT EXISTS gift_snapshots (owner_id INTEGER, value_ton REAL, ts INTEGER);
CREATE TABLE IF NOT EXISTS translations (chat_id INTEGER, message_id INTEGER, requester_id INTEGER, text TEXT, used INTEGER DEFAULT 0, created_at INTEGER, PRIMARY KEY (chat_id, message_id));
CREATE TABLE IF NOT EXISTS seen_alerts (id TEXT PRIMARY KEY, ts INTEGER);
CREATE TABLE IF NOT EXISTS cache (k TEXT PRIMARY KEY, v TEXT, exp INTEGER);
CREATE TABLE IF NOT EXISTS store (k TEXT PRIMARY KEY, v TEXT, exp INTEGER);
CREATE TABLE IF NOT EXISTS states (user_id INTEGER PRIMARY KEY, data TEXT, ts INTEGER);
CREATE TABLE IF NOT EXISTS self_events (chat_id INTEGER, user_id INTEGER, ts INTEGER, points INTEGER, reason TEXT);
CREATE INDEX IF NOT EXISTS idx_self ON self_events(chat_id, user_id, ts);
CREATE TABLE IF NOT EXISTS joins (chat_id INTEGER, user_id INTEGER, ts INTEGER, msgs INTEGER DEFAULT 0, PRIMARY KEY (chat_id, user_id));
CREATE TABLE IF NOT EXISTS msglog (user_id INTEGER, chat_id INTEGER, hash TEXT, ts INTEGER);
CREATE INDEX IF NOT EXISTS idx_msglog ON msglog(user_id, ts);
CREATE TABLE IF NOT EXISTS names (user_id INTEGER PRIMARY KEY, name TEXT, ts INTEGER);
CREATE TABLE IF NOT EXISTS delete_queue (chat_id INTEGER, message_id INTEGER, at INTEGER);
CREATE TABLE IF NOT EXISTS diamonds (id INTEGER PRIMARY KEY, points REAL NOT NULL DEFAULT 0, total INTEGER NOT NULL DEFAULT 0, last INTEGER NOT NULL DEFAULT 0, jailed_until INTEGER NOT NULL DEFAULT 0);
CREATE INDEX IF NOT EXISTS idx_diamonds_points ON diamonds(points DESC);
CREATE TABLE IF NOT EXISTS games (id TEXT PRIMARY KEY, kind TEXT, status TEXT, host INTEGER, chat INTEGER, created INTEGER, data TEXT);
CREATE INDEX IF NOT EXISTS idx_games_status ON games(status, created);
CREATE INDEX IF NOT EXISTS idx_games_host ON games(host, kind, status);
CREATE TABLE IF NOT EXISTS jails (id TEXT PRIMARY KEY, chat INTEGER, target INTEGER, name TEXT, msg INTEGER, confirmed TEXT, created INTEGER);
CREATE TABLE IF NOT EXISTS members (chat INTEGER NOT NULL, uid INTEGER NOT NULL, at INTEGER NOT NULL, PRIMARY KEY (chat, uid));
CREATE INDEX IF NOT EXISTS idx_members_at ON members(at);
SQL);
            self::lean();
        }
        self::$kv = [];
        foreach (self::$pdo->query('SELECT k, v FROM kv') as $row) {
            self::$kv[$row['k']] = json_decode($row['v'], true);
        }
        self::$texts = null;
        if ((int)(self::$kv['schema_v'] ?? 0) < 2) {
            self::$pdo->prepare('DELETE FROM kv WHERE k = ?')->execute(['emoji_map']);
            unset(self::$kv['emoji_map']);
            self::kvSet('schema_v', 2);
        }
        if ((int)(self::$kv['schema_v'] ?? 0) < 3) {
            foreach (['emoji_map', 'emoji_invalid', 'emoji_checked_at'] as $k) {
                self::$pdo->prepare('DELETE FROM kv WHERE k = ?')->execute([$k]);
                unset(self::$kv[$k]);
            }
            self::$pdo->exec("DELETE FROM cache WHERE k LIKE 'fid:%' OR k LIKE 'gpc:%'");
            self::kvSet('schema_v', 3);
        }
        if ((int)(self::$kv['schema_v'] ?? 0) < 4) {
            if (is_array(self::$kv['emoji_map'] ?? null)) {
                $file = Settings::defaults()['emoji_map'] ?? [];
                self::kvSet('emoji_map', array_filter(self::$kv['emoji_map'], fn($id, $e) => (string)($file[$e] ?? '') !== (string)$id, ARRAY_FILTER_USE_BOTH));
            }
            self::kvSet('schema_v', 4);
        }
    }

    private static function lean(): void
    {
        $pdo = self::$pdo;
        $cols = fn(string $t) => array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(), 'name');
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $moved = false;
            if (in_array('username', $cols('users'), true)) {
                $pdo->exec('DROP INDEX IF EXISTS idx_users_username;
CREATE TABLE users_lean (id INTEGER PRIMARY KEY, started INTEGER DEFAULT 0, banned INTEGER DEFAULT 0, first_seen INTEGER);
INSERT INTO users_lean(id, started, banned, first_seen) SELECT id, COALESCE(started, 0), COALESCE(banned, 0), first_seen FROM users;
DROP TABLE users;
ALTER TABLE users_lean RENAME TO users;');
                $moved = true;
            }
            if (in_array('name', $cols('diamonds'), true)) {
                $pdo->exec('DROP INDEX IF EXISTS idx_diamonds_username;
DROP INDEX IF EXISTS idx_diamonds_points;
CREATE TABLE diamonds_lean (id INTEGER PRIMARY KEY, points REAL NOT NULL DEFAULT 0, total INTEGER NOT NULL DEFAULT 0, last INTEGER NOT NULL DEFAULT 0, jailed_until INTEGER NOT NULL DEFAULT 0);
INSERT INTO diamonds_lean(id, points, total, last, jailed_until) SELECT id, points, total, last, jailed_until FROM diamonds;
DROP TABLE diamonds;
ALTER TABLE diamonds_lean RENAME TO diamonds;
CREATE INDEX IF NOT EXISTS idx_diamonds_points ON diamonds(points DESC);');
                $moved = true;
            }
            $pdo->exec('PRAGMA user_version=6');
            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
        if ($moved) {
            try {
                $pdo->exec('VACUUM');
            } catch (Throwable $e) {
            }
        }
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            self::connect();
        }
        return self::$pdo;
    }

    public static function q(string $sql, array $args = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        return $st;
    }

    public static function one(string $sql, array $args = []): ?array
    {
        $r = self::q($sql, $args)->fetch();
        return $r ?: null;
    }

    public static function all(string $sql, array $args = []): array
    {
        return self::q($sql, $args)->fetchAll();
    }

    public static function kvGet(string $k, $default = null)
    {
        return array_key_exists($k, self::$kv) ? self::$kv[$k] : $default;
    }

    public static function kvHas(string $k): bool
    {
        return array_key_exists($k, self::$kv);
    }

    public static function kvSet(string $k, $v): void
    {
        self::$kv[$k] = $v;
        self::q('INSERT INTO kv(k, v) VALUES(?, ?) ON CONFLICT(k) DO UPDATE SET v=excluded.v', [$k, json_encode($v, JSON_UNESCAPED_UNICODE)]);
    }

    public static function textOverride(string $k): ?string
    {
        if (self::$texts === null) {
            self::$texts = [];
            foreach (self::all('SELECT k, v FROM texts') as $r) {
                self::$texts[$r['k']] = $r['v'];
            }
        }
        return self::$texts[$k] ?? null;
    }

    public static function setText(string $k, string $v): void
    {
        self::textOverride($k);
        self::$texts[$k] = $v;
        self::q('INSERT INTO texts(k, v) VALUES(?, ?) ON CONFLICT(k) DO UPDATE SET v=excluded.v', [$k, $v]);
    }

    public static function resetText(string $k): void
    {
        self::textOverride($k);
        unset(self::$texts[$k]);
        self::q('DELETE FROM texts WHERE k=?', [$k]);
    }

    public static function touchUser(array $u, bool $countMessage = false, bool $started = false): void
    {
        static $seen = [];
        $id = (int)($u['id'] ?? 0);
        if ($id <= 0 || isset($seen[$id]) && ($seen[$id] || !$started)) {
            return;
        }
        $row = self::one('SELECT started FROM users WHERE id=?', [$id]);
        if (!$row) {
            self::q('INSERT OR IGNORE INTO users(id, first_seen, started) VALUES(?, ?, ?)', [$id, time(), (int)$started]);
        } elseif ($started && !(int)$row['started']) {
            self::q('UPDATE users SET started=1 WHERE id=?', [$id]);
        }
        $seen[$id] = $started || (int)($row['started'] ?? 0) === 1;
    }

    public static function user(int $id): ?array
    {
        return self::one('SELECT * FROM users WHERE id=?', [$id]);
    }

    public static function isBanned(int $id): bool
    {
        $r = self::one('SELECT banned FROM users WHERE id=?', [$id]);
        return $r && (int)$r['banned'] === 1;
    }

    public static function setBanned(int $id, bool $banned): void
    {
        self::q('INSERT INTO users(id, banned, first_seen) VALUES(?, ?, ?) ON CONFLICT(id) DO UPDATE SET banned=excluded.banned',
            [$id, (int)$banned, time()]);
    }

    public static function userIds(): array
    {
        return array_map('intval', array_column(self::all('SELECT id FROM users WHERE banned=0 AND id > 0'), 'id'));
    }

    public static function stats(): array
    {
        $now = time();
        return self::one('SELECT COUNT(*) total, SUM(started=1) started, SUM(banned=1) banned, SUM(first_seen>=?) fresh FROM users', [$now - 86400]) ?? [];
    }

    public static function deleteUser(int $id): void
    {
        self::q('DELETE FROM users WHERE id=?', [$id]);
        self::q('DELETE FROM states WHERE user_id=?', [$id]);
    }

    public static function groupsPage(int $page, int $per): array
    {
        return self::all("SELECT * FROM chats WHERE active=1 AND type IN ('group','supergroup') ORDER BY added_at DESC LIMIT ? OFFSET ?", [$per, $page * $per]);
    }

    public static function chat(int $id): ?array
    {
        return self::one('SELECT * FROM chats WHERE id=?', [$id]);
    }

    public static function countUsers(): int
    {
        return (int)self::one('SELECT COUNT(*) c FROM users')['c'];
    }

    public static function seenChat(array $chat): void
    {
        if (!self::one('SELECT 1 FROM chats WHERE id=?', [$chat['id']])) {
            self::upsertChat($chat);
        }
    }

    public static function upsertChat(array $chat, bool $active = true): void
    {
        self::q('INSERT INTO chats(id, type, title, username, added_at, active) VALUES(?, ?, ?, ?, ?, ?)
                 ON CONFLICT(id) DO UPDATE SET type=excluded.type, title=excluded.title, username=excluded.username, active=excluded.active',
            [$chat['id'], $chat['type'] ?? null, $chat['title'] ?? null, $chat['username'] ?? null, time(), (int)$active]);
    }

    public static function setChatActive(int $id, bool $active): void
    {
        self::q('UPDATE chats SET active=? WHERE id=?', [(int)$active, $id]);
    }

    public static function activeGroups(): array
    {
        return self::all("SELECT * FROM chats WHERE active=1 AND type IN ('group','supergroup')");
    }

    public static function countGroups(): int
    {
        return (int)self::one("SELECT COUNT(*) c FROM chats WHERE active=1 AND type IN ('group','supergroup')")['c'];
    }

    public static function chatSettings(int $id): array
    {
        $r = self::one('SELECT settings FROM chats WHERE id=?', [$id]);
        return $r ? (json_decode($r['settings'] ?: '{}', true) ?: []) : [];
    }

    public static function setChatSetting(int $id, string $k, $v): void
    {
        $s = self::chatSettings($id);
        $s[$k] = $v;
        self::q("INSERT INTO chats(id, settings, added_at, active) VALUES(?, ?, ?, 1) ON CONFLICT(id) DO UPDATE SET settings=excluded.settings",
            [$id, json_encode($s), time()]);
    }

    public static function addStrike(int $chat, int $user, string $kind): int
    {
        $now = time();
        self::q('INSERT INTO strikes(chat_id, user_id, kind, count, updated_at) VALUES(?, ?, ?, 1, ?)
                 ON CONFLICT(chat_id, user_id, kind) DO UPDATE SET count=CASE WHEN strikes.updated_at < ? THEN 1 ELSE strikes.count + 1 END, updated_at=excluded.updated_at',
            [$chat, $user, $kind, $now, $now - 86400]);
        return (int)(self::one('SELECT count FROM strikes WHERE chat_id=? AND user_id=? AND kind=?', [$chat, $user, $kind])['count'] ?? 0);
    }

    public static function resetStrikes(int $chat, int $user, ?string $kind = null): void
    {
        if ($kind) {
            self::q('DELETE FROM strikes WHERE chat_id=? AND user_id=? AND kind=?', [$chat, $user, $kind]);
        } else {
            self::q('DELETE FROM strikes WHERE chat_id=? AND user_id=?', [$chat, $user]);
        }
    }

    public static function createScam(?int $targetId, ?string $username, ?string $name, int $reporter, ?int $origin): int
    {
        self::q("INSERT INTO scams(target_id, target_username, target_name, reporter_id, origin_chat, created_at, status) VALUES(?, ?, ?, ?, ?, ?, 'draft')",
            [$targetId, $username ? strtolower(ltrim($username, '@')) : null, $name, $reporter, $origin, time()]);
        return (int)self::pdo()->lastInsertId();
    }

    public static function scam(int $id): ?array
    {
        $r = self::one('SELECT * FROM scams WHERE id=?', [$id]);
        if ($r) {
            $r['evidence'] = json_decode($r['evidence'] ?: '[]', true) ?: [];
        }
        return $r;
    }

    public static function updateScam(int $id, array $fields): void
    {
        if (isset($fields['evidence'])) {
            $fields['evidence'] = json_encode($fields['evidence']);
        }
        $cols = implode(', ', array_map(fn($k) => "$k=?", array_keys($fields)));
        self::q("UPDATE scams SET $cols WHERE id=?", [...array_values($fields), $id]);
    }

    public static function findScammer(?int $userId, ?string $username): ?array
    {
        return self::one("SELECT * FROM scams WHERE status='approved' AND ((target_id IS NOT NULL AND target_id=?) OR (target_username IS NOT NULL AND target_username=?)) ORDER BY decided_at DESC LIMIT 1",
            [$userId ?? -1, $username ? strtolower(ltrim($username, '@')) : "\0"]);
    }

    public static function listScams(string $status, int $limit = 20): array
    {
        return self::all('SELECT * FROM scams WHERE status=? ORDER BY id DESC LIMIT ?', [$status, $limit]);
    }

    public static function lastGiftSnapshot(int $owner): ?array
    {
        return self::one('SELECT * FROM gift_snapshots WHERE owner_id=? ORDER BY ts DESC LIMIT 1', [$owner]);
    }

    public static function addGiftSnapshot(int $owner, float $value): void
    {
        self::q('INSERT INTO gift_snapshots(owner_id, value_ton, ts) VALUES(?, ?, ?)', [$owner, $value, time()]);
    }

    public static function saveTranslation(int $chat, int $msg, int $requester, string $text): void
    {
        self::q('INSERT OR REPLACE INTO translations(chat_id, message_id, requester_id, text, used, created_at) VALUES(?, ?, ?, ?, 0, ?)',
            [$chat, $msg, $requester, $text, time()]);
    }

    public static function translation(int $chat, int $msg): ?array
    {
        return self::one('SELECT * FROM translations WHERE chat_id=? AND message_id=?', [$chat, $msg]);
    }

    public static function claimTranslation(int $chat, int $msg): bool
    {
        return self::q('UPDATE translations SET used=1 WHERE chat_id=? AND message_id=? AND used=0', [$chat, $msg])->rowCount() === 1;
    }

    public static function releaseTranslation(int $chat, int $msg): void
    {
        self::q('UPDATE translations SET used=0 WHERE chat_id=? AND message_id=?', [$chat, $msg]);
    }

    public static function markAlert(string $id): bool
    {
        return self::q('INSERT OR IGNORE INTO seen_alerts(id, ts) VALUES(?, ?)', [$id, time()])->rowCount() === 1;
    }

    public static function cleanup(): void
    {
        $now = time();
        self::q('DELETE FROM seen_alerts WHERE ts < ?', [$now - 7 * 86400]);
        self::q('DELETE FROM translations WHERE created_at < ?', [$now - 7 * 86400]);
        self::q('DELETE FROM cache WHERE exp < ?', [$now]);
        self::q('DELETE FROM store WHERE exp < ?', [$now]);
        self::q('DELETE FROM self_events WHERE ts < ?', [$now - 3600]);
        self::q('DELETE FROM msglog WHERE ts < ?', [$now - 3600]);
        self::q('DELETE FROM states WHERE ts < ?', [$now - 7 * 86400]);
    }
}

final class Settings
{
    public static function defaults(): array
    {
        return Config::data('defaults')['defaults'];
    }

    public static function groupFeatures(): array
    {
        return Config::data('defaults')['group_features'];
    }

    public static function get(string $k)
    {
        return DB::kvHas($k) ? DB::kvGet($k) : (self::defaults()[$k] ?? null);
    }

    public static function set(string $k, $v): void
    {
        DB::kvSet($k, $v);
        if (in_array($k, ['emoji_map', 'emoji_invalid', 'emoji_pack'], true)) {
            UI::resetEmoji();
        }
    }

    public static function on(?int $chatId, string $k): bool
    {
        if ($chatId !== null && $chatId < 0) {
            $s = DB::chatSettings($chatId);
            if (array_key_exists($k, $s)) {
                return (bool)$s[$k];
            }
        }
        return (bool)self::get($k);
    }
}

final class Cache
{
    public static function get(string $k, &$hit = null)
    {
        $r = DB::one('SELECT v, exp FROM cache WHERE k=?', [$k]);
        if (!$r || (int)$r['exp'] < time()) {
            $hit = false;
            return null;
        }
        $hit = true;
        return json_decode($r['v'], true)['v'] ?? null;
    }

    public static function set(string $k, $v, int $ttl): void
    {
        DB::q('INSERT INTO cache(k, v, exp) VALUES(?, ?, ?) ON CONFLICT(k) DO UPDATE SET v=excluded.v, exp=excluded.exp',
            [$k, json_encode(['v' => $v], JSON_UNESCAPED_UNICODE), time() + $ttl]);
    }

    public static function forget(string $k): void
    {
        if (DB::one('SELECT 1 FROM cache WHERE k=?', [$k])) {
            DB::q('DELETE FROM cache WHERE k=?', [$k]);
        }
    }

    public static function remember(string $k, int $ttl, callable $fn)
    {
        $v = self::get($k, $hit);
        if ($hit) {
            return $v;
        }
        $v = $fn();
        self::set($k, $v, $ttl);
        return $v;
    }

    public static function throttled(string $k, int $seconds): bool
    {
        $now = time();
        $r = DB::one('SELECT exp FROM cache WHERE k=?', ['thr:' . $k]);
        if ($r && (int)$r['exp'] > $now) {
            return true;
        }
        self::set('thr:' . $k, 1, $seconds);
        return false;
    }
}

final class Perf
{
    private static float $t0 = 0.0;
    private static array $marks = [];

    public static function start(): void
    {
        self::$t0 = microtime(true);
        self::$marks = [];
    }

    public static function mark(string $k): void
    {
        if (self::$t0) {
            self::$marks[] = $k . '=' . (int)round((microtime(true) - self::$t0) * 1000);
        }
    }

    public static function flush(string $tag): void
    {
        if (!self::$t0) {
            return;
        }
        $line = date('m-d H:i:s') . " $tag " . implode(' ', self::$marks) . ' total=' . (int)round((microtime(true) - self::$t0) * 1000) . "ms\n";
        self::$t0 = 0.0;
        if (TG::$fake) {
            return;
        }
        $f = Config::$root . '/data/perf.log';
        if (@filesize($f) > 200000) {
            @file_put_contents($f, substr((string)@file_get_contents($f), -100000));
        }
        @file_put_contents($f, $line, FILE_APPEND);
    }
}

final class FileCache
{
    private static array $mem = [];
    private static array $queued = [];
    public static bool $inline = false;

    private static function path(string $k): string
    {
        $d = Config::get('CACHE_DIR') ?: Config::$root . '/data/cache';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        return $d . '/' . md5($k) . '.json';
    }

    public static function remember(string $k, int $ttl, callable $fn)
    {
        if (array_key_exists($k, self::$mem)) {
            return self::$mem[$k];
        }
        $f = self::path($k);
        if (is_file($f) && time() - (int)@filemtime($f) < $ttl) {
            $v = json_decode((string)@file_get_contents($f), true);
            if (is_array($v) && array_key_exists('v', $v)) {
                return self::$mem[$k] = $v['v'];
            }
        }
        $v = $fn();
        self::put($k, $v);
        return $v;
    }

    public static function swr(string $k, int $ttl, callable $fn)
    {
        if (array_key_exists($k, self::$mem)) {
            return self::$mem[$k];
        }
        $f = self::path($k);
        $v = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        if (!is_array($v) || !array_key_exists('v', $v)) {
            $x = $fn();
            if ($x === null) {
                return self::$mem[$k] = null;
            }
            self::put($k, $x);
            return $x;
        }
        if (time() - (int)@filemtime($f) >= $ttl) {
            if (self::$inline) {
                $x = $fn();
                if ($x) {
                    self::put($k, $x);
                    return $x;
                }
            } elseif (!isset(self::$queued[$k]) && count(self::$queued) < 6) {
                self::$queued[$k] = true;
                @touch($f);
                register_shutdown_function(function () use ($k, $fn) {
                    try {
                        $x = $fn();
                        if ($x) {
                            self::put($k, $x);
                        }
                    } catch (Throwable $e) {
                        error_log('cache refresh ' . $k . ': ' . $e->getMessage());
                    }
                });
            }
        }
        return self::$mem[$k] = $v['v'];
    }

    public static function put(string $k, $v): void
    {
        self::$mem[$k] = $v;
        $f = self::path($k);
        $tmp = $f . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode(['v' => $v], JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $f);
        }
    }

    public static function has(string $k, int $ttl): bool
    {
        if (array_key_exists($k, self::$mem)) {
            return true;
        }
        $f = self::path($k);
        return is_file($f) && time() - (int)@filemtime($f) < $ttl;
    }

    public static function forget(): void
    {
        self::$mem = [];
    }
}

final class Store
{
    public static function put($value, int $ttl = 86400): string
    {
        $k = rtrim(strtr(base64_encode(random_bytes(6)), '+/', '-_'), '=');
        DB::q('INSERT OR REPLACE INTO store(k, v, exp) VALUES(?, ?, ?)', [$k, json_encode($value, JSON_UNESCAPED_UNICODE), time() + $ttl]);
        return $k;
    }

    public static function get(string $k)
    {
        $r = DB::one('SELECT v, exp FROM store WHERE k=?', [$k]);
        if (!$r || (int)$r['exp'] < time()) {
            return null;
        }
        return json_decode($r['v'], true);
    }
}

final class State
{
    public static function get(int $uid): ?array
    {
        $r = DB::one('SELECT data FROM states WHERE user_id=?', [$uid]);
        return $r ? json_decode($r['data'], true) : null;
    }

    public static function set(int $uid, array $data): void
    {
        DB::q('INSERT OR REPLACE INTO states(user_id, data, ts) VALUES(?, ?, ?)', [$uid, json_encode($data, JSON_UNESCAPED_UNICODE), time()]);
    }

    public static function clear(int $uid): void
    {
        DB::q('DELETE FROM states WHERE user_id=?', [$uid]);
    }
}
