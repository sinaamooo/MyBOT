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
            $cache[$name] = json_decode(file_get_contents(__DIR__ . '/data/' . $name . '.json'), true) ?: [];
        }
        return $cache[$name];
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
        self::$pdo->exec('PRAGMA journal_mode=WAL');
        self::$pdo->exec('PRAGMA synchronous=NORMAL');
        self::$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS texts (k TEXT PRIMARY KEY, v TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT, first_name TEXT, last_name TEXT, is_premium INTEGER DEFAULT 0, first_seen INTEGER, last_seen INTEGER, msg_count INTEGER DEFAULT 0, xp INTEGER DEFAULT 0, balance REAL DEFAULT 0, started INTEGER DEFAULT 0, banned INTEGER DEFAULT 0);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
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
SQL);
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
        $now = time();
        $c = (int)$countMessage;
        self::q(
            'INSERT INTO users(id, username, first_name, last_name, is_premium, first_seen, last_seen, msg_count, xp, started)
             VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(id) DO UPDATE SET username=excluded.username, first_name=excluded.first_name, last_name=excluded.last_name,
             is_premium=excluded.is_premium, last_seen=excluded.last_seen, msg_count=users.msg_count + ?, xp=users.xp + ?,
             started=MAX(users.started, excluded.started)',
            [$u['id'], isset($u['username']) ? strtolower($u['username']) : null, $u['first_name'] ?? null, $u['last_name'] ?? null,
             (int)!empty($u['is_premium']), $now, $now, $c, $c, (int)$started, $c, $c]
        );
    }

    public static function user(int $id): ?array
    {
        return self::one('SELECT * FROM users WHERE id=?', [$id]);
    }

    public static function userByUsername(string $username): ?array
    {
        return self::one('SELECT * FROM users WHERE username=? ORDER BY last_seen DESC LIMIT 1', [strtolower(ltrim($username, '@'))]);
    }

    public static function isBanned(int $id): bool
    {
        $r = self::one('SELECT banned FROM users WHERE id=?', [$id]);
        return $r && (int)$r['banned'] === 1;
    }

    public static function setBanned(int $id, bool $banned): void
    {
        self::q('INSERT INTO users(id, banned, first_seen, last_seen) VALUES(?, ?, ?, ?) ON CONFLICT(id) DO UPDATE SET banned=excluded.banned',
            [$id, (int)$banned, time(), time()]);
    }

    public static function addBalance(int $id, float $amount): float
    {
        self::q('INSERT INTO users(id, balance, first_seen, last_seen) VALUES(?, ?, ?, ?) ON CONFLICT(id) DO UPDATE SET balance=users.balance + ?',
            [$id, $amount, time(), time(), $amount]);
        return (float)(self::user($id)['balance'] ?? 0);
    }

    public static function userIds(): array
    {
        return array_map('intval', array_column(self::all('SELECT id FROM users WHERE started=1 AND banned=0'), 'id'));
    }

    public static function countUsers(): int
    {
        return (int)self::one('SELECT COUNT(*) c FROM users')['c'];
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
        if (in_array($k, ['emoji_map', 'emoji_invalid'], true)) {
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
        DB::q('DELETE FROM cache WHERE k=?', [$k]);
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
