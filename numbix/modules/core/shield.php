<?php
defined('NB_ROOT') || exit;

function shDb() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    try {
        $db = nbRawOpen(DATA_DIR . '/shield.sqlite');
        $db->busyTimeout(2000);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA synchronous=NORMAL');
        $db->exec('CREATE TABLE IF NOT EXISTS f (k TEXT PRIMARY KEY, w INTEGER NOT NULL, n INTEGER NOT NULL) WITHOUT ROWID');
    } catch (Throwable $e) {
        error_log('[shield] ' . $e->getMessage());
        $db = false;
        return null;
    }
    return $db;
}

function shHit($key, $limit, $win) {
    $db = shDb();
    if (!$db) return true;
    $now = time();
    $w = $now - $now % max(1, (int)$win);
    $up = $db->prepare('INSERT INTO f (k, w, n) VALUES (:k, :w, 1) ON CONFLICT(k) DO UPDATE SET
                        n = CASE WHEN f.w = excluded.w THEN f.n + 1 ELSE 1 END, w = excluded.w');
    $get = $db->prepare('SELECT n FROM f WHERE k = :k');
    if (!$up || !$get) return true;
    $up->bindValue(':k', (string)$key, SQLITE3_TEXT);
    $up->bindValue(':w', $w, SQLITE3_INTEGER);
    if (!@$up->execute()) return true;
    $get->bindValue(':k', (string)$key, SQLITE3_TEXT);
    $r = @$get->execute();
    $row = $r ? $r->fetchArray(SQLITE3_NUM) : null;
    return !$row || (int)$row[0] <= (int)$limit;
}

function shPrune() {
    $db = shDb();
    if ($db) { $st = $db->prepare('DELETE FROM f WHERE w < :c'); if ($st) { $st->bindValue(':c', time() - 3600, SQLITE3_INTEGER); @$st->execute(); } }
}

function shGuard($update) {
    $uid = (int)($update['callback_query']['from']['id'] ?? ($update['message']['from']['id'] ?? 0));
    $ct  = (string)($update['callback_query']['message']['chat']['type'] ?? ($update['message']['chat']['type'] ?? ''));
    if ($uid <= 0 || $ct !== 'private' || isAdmin($uid)) return true;
    if (shHit('pm:' . $uid, 30, 15)) return true;
    monSection('shield', 'flood');
    return false;
}
