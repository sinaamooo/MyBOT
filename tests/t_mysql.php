<?php
// Validate the SQLite->MySQL translation layer (NbMy in db.php) against the exact
// SQL the user-data path runs. A live MariaDB server cannot be started in this
// sandbox, so this exercises the translator (the only place user data could break
// on MySQL) as a pure function via reflection.

if (!class_exists('NbMy')) { echo "  ❌ NbMy class missing\n"; $GLOBALS['FAIL']++; return; }

// NOTE: dbCreds() is process-cached, so prefix is whatever was first resolved (empty
// here). Tables are still stem-scoped and backticked, which is the isolation that matters.
$pdo = new PDO('sqlite::memory:');
function nbmy($stem) { global $pdo; return new NbMy($pdo, $stem); }

$ref = function (NbMy $o, $method, ...$args) {
    $m = new ReflectionMethod($o, $method);
    $m->setAccessible(true);
    return $m->invoke($o, ...$args);
};
function setImm(NbMy $o, $v) {
    $p = new ReflectionProperty($o, 'imm'); $p->setAccessible(true); $p->setValue($o, $v);
    $p2 = new ReflectionProperty($o, 'inTx'); $p2->setAccessible(true); $p2->setValue($o, $v);
}

echo "\n[M1] users table create → MySQL\n";
$u = nbmy('users');
$sql = $ref($u, 'trCreateTable', 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, data TEXT NOT NULL)');
echo "  $sql\n";
ok((bool)preg_match('/`[a-z0-9_]*users__users`/', $sql), 'table name is stem-scoped and backticked');
ok(str_contains($sql, 'ENGINE=InnoDB') && str_contains($sql, 'utf8mb4'), 'InnoDB + utf8mb4');
ok(stripos($sql, '`id`') !== false && stripos($sql, 'PRIMARY KEY') !== false, 'id stays primary key');

echo "\n[M2] wallet_tx: AUTOINCREMENT + idem unique (double-credit guard)\n";
$sql = $ref($u, 'trCreateTable', 'CREATE TABLE IF NOT EXISTS wallet_tx (id INTEGER PRIMARY KEY AUTOINCREMENT, uid INTEGER NOT NULL, delta INTEGER NOT NULL, bal INTEGER NOT NULL, kind TEXT NOT NULL, ref TEXT NOT NULL, idem TEXT, at INTEGER NOT NULL)');
echo "  $sql\n";
ok(stripos($sql, 'AUTO_INCREMENT') !== false && stripos($sql, 'AUTOINCREMENT ') === false, 'AUTOINCREMENT → AUTO_INCREMENT');
$idx = $ref($u, 'trCreateIndex', 'CREATE UNIQUE INDEX IF NOT EXISTS wallet_tx_idem ON wallet_tx (idem)');
echo "  $idx\n";
ok(stripos($idx, 'UNIQUE INDEX') !== false && (bool)preg_match('/`[a-z0-9_]*users__wallet_tx`/', $idx), 'UNIQUE index on idem is created (stops double credit)');
ok(preg_match('/`idem`\(\d+\)/', $idx), 'TEXT idem gets a prefix length so UNIQUE works on MySQL');

echo "\n[M3] mutateUser row lock: BEGIN IMMEDIATE makes SELECT lock the row\n";
$u2 = nbmy('users'); setImm($u2, true);
$sel = $ref($u2, 'tr', 'SELECT data FROM users WHERE id = :id');
echo "  $sel\n";
ok((bool)preg_match('/`[a-z0-9_]*users__users`/', $sel) && stripos($sel, 'FOR UPDATE') !== false,
   'SELECT under BEGIN IMMEDIATE becomes ... FOR UPDATE (this is what stops the balance race)');
setImm($u2, false);
$sel2 = $ref($u2, 'tr', 'SELECT data FROM users WHERE id = :id');
ok(stripos($sel2, 'FOR UPDATE') === false, 'plain read does NOT lock');

echo "\n[M4] INSERT OR REPLACE / IGNORE\n";
$r = $ref($u, 'tr', 'INSERT OR REPLACE INTO users (id, data) VALUES (:id, :data)');
echo "  $r\n";
ok((bool)preg_match('/REPLACE INTO `[a-z0-9_]*users__users`/i', $r), 'INSERT OR REPLACE → REPLACE');
$r2 = $ref(nbmy('num_acts'), 'tr', "INSERT OR IGNORE INTO num_acts (id, uid, status, created, data) VALUES (:id, 0, 'buying', :c, :d)");
ok((bool)preg_match('/INSERT IGNORE INTO `[a-z0-9_]*num_acts__num_acts`/i', $r2), 'INSERT OR IGNORE → INSERT IGNORE');

echo "\n[M5] json_extract in WHERE (panel search, ban filter)\n";
$j = $ref($u, 'tr', "SELECT id, data FROM users WHERE lower(json_extract(data,'\$.username')) = :u");
echo "  $j\n";
ok(stripos($j, 'JSON_UNQUOTE(JSON_EXTRACT(data') !== false, 'json_extract → JSON_UNQUOTE(JSON_EXTRACT())');
$ban = $ref($u, 'tr', "SELECT COUNT(*) FROM users WHERE COALESCE(json_extract(data,'\$.banned'), 0) NOT IN (1, '1', 'true')");
ok(stripos($ban, 'JSON_UNQUOTE(JSON_EXTRACT') !== false, 'ban filter translates');

echo "\n[M6] CAST and upsert (shield/rate counters use ON CONFLICT)\n";
$c = $ref($u, 'tr', "SELECT CAST(json_extract(data,'\$.referrer') AS INTEGER) AS r FROM users");
ok(stripos($c, 'AS SIGNED') !== false, 'CAST AS INTEGER → AS SIGNED');
$up = $ref(nbmy('shield'), 'tr', 'INSERT INTO f (k, w, n) VALUES (:k, :w, 1) ON CONFLICT(k) DO UPDATE SET n = CASE WHEN f.w = excluded.w THEN f.n + 1 ELSE 1 END, w = excluded.w');
echo "  $up\n";
ok(stripos($up, 'ON DUPLICATE KEY UPDATE') !== false && stripos($up, 'VALUES(w)') !== false && stripos($up, 'excluded.') === false,
   'ON CONFLICT DO UPDATE / excluded.x → ON DUPLICATE KEY UPDATE / VALUES(x)');

echo "\n[M7] orders unique id, services svo table, coupons owner column\n";
$o = $ref(nbmy('orders'), 'trCreateTable', 'CREATE TABLE IF NOT EXISTS orders (id TEXT PRIMARY KEY, user_id INTEGER NOT NULL, type TEXT NOT NULL, status TEXT NOT NULL, created_at TEXT NOT NULL, data TEXT NOT NULL)');
ok(stripos($o, '`id`') !== false && stripos($o, 'PRIMARY KEY') !== false, 'orders id TEXT primary key preserved');
$cp = $ref(nbmy('coupons'), 'trCreateTable', "CREATE TABLE IF NOT EXISTS coupons (code TEXT PRIMARY KEY, kind TEXT NOT NULL DEFAULT 'percent', value REAL NOT NULL DEFAULT 0, max_uses INTEGER NOT NULL DEFAULT 0, used INTEGER NOT NULL DEFAULT 0, per_user_limit INTEGER NOT NULL DEFAULT 1, min_total REAL NOT NULL DEFAULT 0, max_discount REAL NOT NULL DEFAULT 0, expires_at INTEGER NOT NULL DEFAULT 0, on_flag INTEGER NOT NULL DEFAULT 1, created_at INTEGER NOT NULL DEFAULT 0, owner INTEGER NOT NULL DEFAULT 0)");
ok(stripos($cp, '`owner`') !== false, 'coupons owner column carried to MySQL');

echo "\n[M8] migration mapping covers every user-data table\n";
$need = ['users' => ['users','ref_log','wallet_tx'], 'orders' => ['orders','orders_old'], 'coupons' => ['coupons','coupon_redemptions','coupon_active']];
$allok = true;
foreach ($need as $stem => $tabs) { $have = nbStemTables($stem); foreach ($tabs as $t) if (!in_array($t, $have, true)) { $allok = false; echo "   missing $stem/$t\n"; } }
ok($allok, 'users+wallet_tx+ref_log, orders, coupons all in the MySQL migration map');


echo "\n[M9] fail-closed: MySQL on but unreachable → no silent data split\n";
// Self-contained: build a temp data dir whose db.php points MySQL at a dead port,
// then load the bot in a fresh PHP process (dbCreds() is process-cached, so a
// sub-process is the only honest way to test a different DB config).
$td = sys_get_temp_dir() . '/nbx_fc_' . bin2hex(random_bytes(4));
@mkdir($td, 0700, true);
file_put_contents($td . '/db.php', "<?php\nreturn ['on'=>1,'host'=>'127.0.0.1','port'=>59999,'name'=>'x','user'=>'x','pass'=>'x','prefix'=>'','charset'=>'utf8mb4'];\n");
$bot = defined('NB_ROOT') ? NB_ROOT : dirname(__DIR__) . '/numbix';
$sub = $td . '/run.php';
file_put_contents($sub,
    "<?php\ndefine('BOT_TOKEN','123456:TEST');define('ADMIN_ID',1);define('ADMIN_IDS',[1]);" .
    "define('WEBHOOK_SECRET','s');define('DATA_DIR','" . $td . "');define('MEMBERSHIP_LIB_ONLY',true);\n" .
    "require " . var_export($bot . '/bot_master_membership.php', true) . ";\n" .
    "\$ok = null; try { \$ok = addBalance(123, 1000, 'topup', 'x', 'idem1'); } catch (Throwable \$e) {}\n" .
    "echo 'dbOn=' . (dbOn()?'1':'0') . ' credit=' . var_export(\$ok, true) .\n" .
    "     ' localsqlite=' . (is_file('" . $td . "/users.sqlite') ? 'YES' : 'no');\n");
$out = (string)shell_exec('php ' . escapeshellarg($sub) . ' 2>/dev/null');
echo "  $out\n";
@array_map('unlink', (array)glob($td . '/*')); @rmdir($td);
ok(str_contains($out, 'dbOn=1'), 'MySQL is the configured store');
ok(str_contains($out, 'localsqlite=no'), 'NO local users.sqlite created while MySQL is down (balances never split)');
ok(str_contains($out, 'credit=false'), 'a credit while MySQL is down fails cleanly (returns false), so nothing is written to the wrong store');
