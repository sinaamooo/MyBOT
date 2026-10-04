<?php
defined('NB_ROOT') || exit;

function dbCredsPath() {
    $env = (string)getenv('NUMBIX_DB_FILE');
    if ($env !== '') return $env;
    return (defined('DATA_DIR') ? DATA_DIR : sys_get_temp_dir()) . '/db.php';
}

function dbCreds() {
    static $c = null;
    if ($c !== null) return $c;
    $c = [];
    $f = dbCredsPath();
    if (is_file($f)) {
        $r = @include $f;
        if (is_array($r)) $c = $r;
    }
    foreach (['host', 'name', 'user', 'pass', 'prefix', 'charset'] as $k)
        if (!isset($c[$k])) $c[$k] = '';
    $c['port'] = (int)($c['port'] ?? 0) ?: 3306;
    $c['on']   = !empty($c['on']);
    if ($c['charset'] === '') $c['charset'] = 'utf8mb4';
    return $c;
}

function dbCredsFresh() {
    $f = dbCredsPath();
    if (is_file($f)) { $r = @include $f; if (is_array($r)) return $r; }
    return [];
}

function dbConfigured($c = null) {
    $c = $c ?? dbCreds();
    return trim((string)$c['host']) !== '' && trim((string)$c['name']) !== '' && trim((string)$c['user']) !== '';
}

function dbOn() {
    $c = dbCreds();
    return $c['on'] && dbConfigured($c);
}

function dbDsn($c) {
    $host = trim((string)$c['host']);
    $port = (int)($c['port'] ?? 3306) ?: 3306;
    $name = trim((string)$c['name']);
    $cs   = preg_replace('/[^A-Za-z0-9_]/', '', (string)($c['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';
    return 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=' . $cs;
}

function dbSaveCreds(array $in) {
    $cur = dbCredsFresh() + dbCreds();
    $c = [
        'on'      => !empty($in['on']) ? 1 : 0,
        'host'    => trim((string)($in['host'] ?? $cur['host'])),
        'port'    => (int)($in['port'] ?? $cur['port']) ?: 3306,
        'name'    => trim((string)($in['name'] ?? $cur['name'])),
        'user'    => trim((string)($in['user'] ?? $cur['user'])),
        'pass'    => array_key_exists('pass', $in) && $in['pass'] !== '' ? (string)$in['pass'] : (string)$cur['pass'],
        'prefix'  => preg_replace('/[^A-Za-z0-9_]/', '', (string)($in['prefix'] ?? $cur['prefix'])),
        'charset' => 'utf8mb4',
    ];
    $f = dbCredsPath();
    $body = "<?php\n// فایلِ اتصالِ دیتابیس — از پنلِ وب ساخته می‌شود. این فایل را روی گیت‌هاب نگذارید.\nreturn " .
            var_export($c, true) . ";\n";
    $tmp = $f . '.tmp' . bin2hex(random_bytes(3));
    if (@file_put_contents($tmp, $body, LOCK_EX) === false) return [false, 'نوشتنِ فایلِ دیتابیس ناموفق بود — پوشه‌ی داده اجازه‌ی نوشتن ندارد.'];
    @chmod($tmp, 0600);
    if (!@rename($tmp, $f)) { @unlink($tmp); return [false, 'ذخیره‌ی فایلِ دیتابیس ناموفق بود.']; }
    @chmod($f, 0600);
    return [true, ''];
}

function dbPing(array $c) {
    if (!dbConfigured($c)) return [false, 'آدرس، نام یا کاربرِ دیتابیس خالی است.'];
    if (!extension_loaded('pdo_mysql')) return [false, 'افزونه‌ی pdo_mysql روی سرور نصب نیست.'];
    try {
        $pdo = new PDO(dbDsn($c), (string)$c['user'], (string)$c['pass'], [
            PDO::ATTR_TIMEOUT            => 6,
            PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $v = $pdo->query('SELECT VERSION()')->fetchColumn();
        return [true, 'اتصال برقرار شد — MySQL ' . preg_replace('/[^0-9.].*$/', '', (string)$v)];
    } catch (Throwable $e) {
        $m = $e->getMessage();
        if (stripos($m, 'Access denied') !== false)      $m = 'نام کاربری یا رمزِ دیتابیس درست نیست.';
        elseif (stripos($m, 'Unknown database') !== false) $m = 'دیتابیسی با این نام پیدا نشد.';
        elseif (stripos($m, 'getaddrinfo') !== false || stripos($m, 'No such host') !== false || stripos($m, 'refused') !== false
                || stripos($m, 'timed out') !== false || stripos($m, '2002') !== false || stripos($m, '2005') !== false)
            $m = 'به آدرسِ دیتابیس نمی‌شود وصل شد (host/port را چک کنید).';
        else $m = mb_substr($m, 0, 160);
        return [false, $m];
    }
}

function dbConn() {
    static $pdo = false;
    if ($pdo !== false) return $pdo;
    $pdo = null;
    if (!dbOn() || !extension_loaded('pdo_mysql')) return null;
    $c = dbCreds();
    try {
        $pdo = new PDO(dbDsn($c), (string)$c['user'], (string)$c['pass'], [
            PDO::ATTR_TIMEOUT            => 6,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (Throwable $e) {
        error_log('[db] اتصال به MySQL ناموفق: ' . $e->getMessage());
        $pdo = null;
        if (function_exists('adminAlertOnce'))
            adminAlertOnce('db_down', "🔴 <b>اتصال به دیتابیسِ MySQL برقرار نشد</b>\n\n<code>" .
                h(mb_substr($e->getMessage(), 0, 200)) . "</code>\n\nتا رفعِ آن، ربات روی ذخیره‌سازیِ فایلی کار می‌کند.", 1800);
    }
    return $pdo;
}

function dbReady() {
    return dbConn() !== null;
}

function dbStatusText() {
    $c = dbCreds();
    if (!dbConfigured($c)) return ['off', 'تنظیم نشده — ربات روی فایل (SQLite) کار می‌کند'];
    if (!$c['on'])         return ['off', 'ذخیره شده ولی خاموش است'];
    return dbReady() ? ['on', 'متصل است'] : ['err', 'تنظیم شده ولی اتصال برقرار نیست'];
}

function dbPanelCard($csrf, $secOpen = '') {
    $c = dbCreds();
    [$st, $msg] = dbStatusText();
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $badge = $st === 'on' ? '<span class="badge green">متصل</span>'
           : ($st === 'err' ? '<span class="badge red">قطع</span>' : '<span class="badge">فایل</span>');
    ob_start(); ?>
  <details class="card psec" id="db-conn" data-s="db"<?= $secOpen ?>><summary><h2>🗄️ دیتابیس (MySQL) <?= $badge ?></h2></summary><div class="body">
    <div class="note" style="margin-bottom:12px">
      اطلاعاتِ دیتابیسِ MySQL را (از سی‌پنل) این‌جا بگذارید تا داده‌ها از حالتِ فایل خارج و روی دیتابیس ذخیره شوند.
      رمز فقط ذخیره می‌شود و هیچ‌وقت این‌جا نمایش داده نمی‌شود. متن‌های ربات همچنان داخلِ <code>config.json</code> می‌مانند.
    </div>
    <p class="muted">وضعیت: <b><?= $e($msg) ?></b></p>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <input type="hidden" name="tab" value="settings">
      <input type="hidden" name="ret" value="s=db">
      <label>میزبان (Host)</label>
      <input name="db_host" value="<?= $e($c['host']) ?>" placeholder="localhost" dir="ltr" autocomplete="off">
      <label>پورت</label>
      <input name="db_port" value="<?= $e($c['port']) ?>" placeholder="3306" dir="ltr" inputmode="numeric" autocomplete="off">
      <label>نامِ دیتابیس</label>
      <input name="db_name" value="<?= $e($c['name']) ?>" placeholder="cpaneluser_numbix" dir="ltr" autocomplete="off">
      <label>کاربرِ دیتابیس</label>
      <input name="db_user" value="<?= $e($c['user']) ?>" placeholder="cpaneluser_numbix" dir="ltr" autocomplete="off">
      <label>رمزِ دیتابیس <?= $c['pass'] !== '' ? '<span class="muted">(ذخیره شده — خالی بگذارید تا عوض نشود)</span>' : '' ?></label>
      <input type="password" name="db_pass" value="" placeholder="<?= $c['pass'] !== '' ? '••••••••' : 'رمز' ?>" dir="ltr" autocomplete="new-password">
      <label>پیشوندِ جدول‌ها (اختیاری)</label>
      <input name="db_prefix" value="<?= $e($c['prefix']) ?>" placeholder="nx_" dir="ltr" autocomplete="off">
      <label style="display:flex;align-items:center;gap:8px;margin-top:8px">
        <input type="checkbox" name="db_on" value="1" <?= $c['on'] ? 'checked' : '' ?> style="width:auto">
        روشن‌کردنِ ذخیره‌سازی روی این دیتابیس
      </label>
      <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
        <button class="btn" name="action" value="save_db">ذخیره</button>
        <button class="btn ghost" name="action" value="test_db">تستِ اتصال</button>
      </div>
    </form>
    <div class="note" style="margin-top:14px">
      <b>انتقال و بکاپ</b><br>
      «انتقالِ داده‌ها» همهٔ دیتای فعلی (کاربران، موجودی، سفارش‌ها، شماره‌ها، بازی‌ها و…) را از فایل به همین دیتابیسِ MySQL کپی می‌کند و تعدادِ ردیف‌ها را می‌سنجد. قبل از انتقال حتماً یک «بکاپ» بگیرید.
    </div>
    <form method="post" autocomplete="off" style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <input type="hidden" name="tab" value="settings">
      <input type="hidden" name="ret" value="s=db">
      <button class="btn" name="action" value="backup_db">دانلودِ بکاپ</button>
      <button class="btn ghost" name="action" value="migrate_db" <?= $st === 'on' ? '' : 'disabled title="اول اتصالِ MySQL را روشن و تست کنید"' ?> onclick="return confirm('همهٔ داده‌ها به دیتابیسِ MySQL منتقل شوند؟ (داده‌های قبلیِ این جدول‌ها در MySQL جایگزین می‌شوند)')">انتقالِ داده‌ها به دیتابیس</button>
    </form>
  </div></details>
<?php
    return ob_get_clean();
}

function nbStemTables($stem) {
    static $map = [
        'users'        => ['users', 'ref_log', 'wallet_tx'],
        'states'       => ['states'],
        'orders'       => ['orders', 'orders_old'],
        'seen'         => ['seen'],
        'cache'        => ['kv'],
        'ma_orders'    => ['orders', 'orders_old'],
        'support'      => ['msgs', 'seen'],
        'translate'    => ['tl_cache'],
        'coupons'      => ['coupons', 'coupon_redemptions', 'coupon_active'],
        'services'     => ['svc', 'svo'],
        'num_acts'     => ['num_acts'],
        'shield'       => ['f'],
        'monitor'      => ['s', 'x', 'ev', 'xc'],
        'wheel'        => ['wh_spins', 'wh_days', 'wh_posts', 'wh_tix'],
        'top_members'  => ['members'],
        'diamond_users' => ['diamond_users'],
        'bank_users'   => ['bank_users'],
        'quiz'         => ['quiz_bank', 'quiz_rounds', 'quiz_groups', 'quiz_sent', 'quiz_wins'],
        'games'        => ['games'],
        'mine_games'   => ['mine_games'],
        'airdrop'      => ['airdrop_users'],
    ];
    return $map[$stem] ?? [];
}

function nbStem($path) {
    $b = basename((string)$path);
    $b = preg_replace('/\.sqlite3?$/i', '', $b);
    $b = preg_replace('/[^A-Za-z0-9_]/', '_', $b);
    return $b !== '' ? strtolower($b) : 'db';
}

function nbTablePrefix($stem) {
    $c = dbCreds();
    $up = preg_replace('/[^A-Za-z0-9_]/', '', (string)$c['prefix']);
    return $up . $stem . '__';
}

function nbDownMarker() {
    return (defined('DATA_DIR') ? DATA_DIR : sys_get_temp_dir()) . '/.db_down';
}

function nbStemPdo($stem) {
    static $pool = [], $down = false;
    if ($down) return null;
    if (array_key_exists($stem, $pool)) return $pool[$stem];
    $pool[$stem] = null;
    if (!dbOn() || !extension_loaded('pdo_mysql')) return null;
    $mk = nbDownMarker();
    $ts = @filemtime($mk);
    if ($ts && (time() - $ts) < 15) { $down = true; return null; }
    $c = dbCreds();
    try {
        $pdo = new PDO(dbDsn($c), (string)$c['user'], (string)$c['pass'], [
            PDO::ATTR_TIMEOUT            => 4,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ]);
        $pdo->exec("SET sql_mode = 'NO_ENGINE_SUBSTITUTION'");
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
        $pdo->exec("SET SESSION innodb_lock_wait_timeout = 10");
        $pool[$stem] = $pdo;
        if ($ts) @unlink($mk);
    } catch (Throwable $e) {
        $down = true;
        @touch($mk);
        error_log('[db] اتصال stem ' . $stem . ' ناموفق: ' . $e->getMessage());
        if (function_exists('adminAlertOnce'))
            adminAlertOnce('db_down', "🔴 <b>اتصال به دیتابیسِ MySQL برقرار نشد</b>\n\n<code>" .
                (function_exists('h') ? h(mb_substr($e->getMessage(), 0, 200)) : mb_substr($e->getMessage(), 0, 200)) .
                "</code>\n\nعملیاتِ مالی/کاربری تا رفعِ اتصال متوقف می‌شوند (fail-closed) تا داده ناهماهنگ نشود.", 1800);
    }
    return $pool[$stem];
}

function nbCacheStems() {
    return ['cache' => 1, 'translate' => 1, 'monitor' => 1, 'shield' => 1, 'seen' => 1];
}

function nbRawOpen($path) {
    $stem  = nbStem($path);
    $cache = nbCacheStems();
    if (isset($cache[$stem])) return new SQLite3($path);
    if (dbOn()) {
        $pdo = nbStemPdo($stem);
        if ($pdo instanceof PDO) return new NbMy($pdo, $stem);
        throw new RuntimeException('db_unavailable: عملیات برای جلوگیری از ناهماهنگیِ داده انجام نشد.');
    }
    return new SQLite3($path);
}

class NbMy {
    private $pdo;
    private $stem;
    private $prefix;
    private $tables  = [];
    private $cols    = [];
    private $rowidTbl = ['ref_log' => 1, 'members' => 1];
    private $inTx = false;
    private $imm  = false;
    private $changes = 0;

    public function __construct(PDO $pdo, $stem) {
        $this->pdo    = $pdo;
        $this->stem   = $stem;
        $this->prefix = nbTablePrefix($stem);
        foreach (nbStemTables($stem) as $t) $this->tables[strtolower($t)] = 1;
    }

    public function pdo() { return $this->pdo; }
    public function prefix() { return $this->prefix; }
    public function tables() { return array_keys($this->tables); }

    public function busyTimeout($ms) { return true; }
    public function close() { return true; }
    public function lastErrorMsg() { $e = $this->pdo->errorInfo(); return (string)($e[2] ?? ''); }
    public function lastInsertRowID() { return (int)$this->pdo->lastInsertId(); }
    public function changes() { return (int)$this->changes; }

    public function exec($sql) {
        $t = trim($sql);
        $kw = strtoupper(preg_replace('/\s+/', ' ', substr($t, 0, 24)));
        if (strncmp($kw, 'PRAGMA', 6) === 0) return true;
        if (strncmp($kw, 'VACUUM', 6) === 0) return true;
        if (strncmp($kw, 'BEGIN IMMEDIATE', 15) === 0) return $this->beginTx(true);
        if (strncmp($kw, 'BEGIN TRANSACTION', 17) === 0 || $kw === 'BEGIN' || strncmp($kw, 'BEGIN ', 6) === 0) return $this->beginTx(false);
        if ($kw === 'COMMIT' || strncmp($kw, 'COMMIT', 6) === 0 || $kw === 'END' || strncmp($kw, 'END ', 4) === 0) return $this->commitTx();
        if ($kw === 'ROLLBACK' || strncmp($kw, 'ROLLBACK', 8) === 0) return $this->rollbackTx();
        $q = $this->tr($t);
        if ($q === '') return true;
        try {
            $this->changes = (int)$this->pdo->exec($q);
            return true;
        } catch (Throwable $e) {
            $code = (string)$e->getCode();
            if ($code === '42S01' || strpos($e->getMessage(), '1061') !== false || strpos($e->getMessage(), '1050') !== false) return true;
            error_log('[db exec] ' . $e->getMessage() . ' | ' . substr($q, 0, 160));
            return false;
        }
    }

    public function query($sql) {
        $t = trim($sql);
        if (preg_match('/^PRAGMA\s+table_info\s*\(\s*([A-Za-z_][A-Za-z0-9_]*)\s*\)/i', $t, $pm))
            return new NbMyResult(null, $this->pragmaTableInfo($pm[1]));
        $q = $this->tr($t);
        if ($q === '') return false;
        try {
            $st = $this->pdo->query($q);
            $this->changes = (int)$st->rowCount();
            return new NbMyResult($st);
        } catch (Throwable $e) {
            error_log('[db query] ' . $e->getMessage() . ' | ' . substr($q, 0, 160));
            return false;
        }
    }

    private function pragmaTableInfo($table) {
        $full = $this->prefix . strtolower(preg_replace('/[^A-Za-z0-9_]/', '', $table));
        $rows = [];
        try {
            $st = $this->pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, ORDINAL_POSITION
                FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
            $st->execute([$full]);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $name = (string)$r['COLUMN_NAME'];
                if ($name === '_nbid') continue;
                $rows[] = [
                    'cid'        => (int)$r['ORDINAL_POSITION'] - 1,
                    'name'       => $name,
                    'type'       => strtoupper((string)$r['DATA_TYPE']),
                    'notnull'    => $r['IS_NULLABLE'] === 'NO' ? 1 : 0,
                    'dflt_value' => $r['COLUMN_DEFAULT'],
                    'pk'         => $r['COLUMN_KEY'] === 'PRI' ? 1 : 0,
                ];
            }
        } catch (Throwable $e) { error_log('[db pragma] ' . $e->getMessage()); }
        return $rows;
    }

    public function querySingle($sql, $entireRow = false) {
        $res = $this->query($sql);
        if ($res === false) return $entireRow ? [] : null;
        $row = $res->fetchArray($entireRow ? SQLITE3_ASSOC : SQLITE3_NUM);
        $res->finalize();
        if ($entireRow) return $row === false ? [] : $row;
        return $row === false ? null : ($row[0] ?? null);
    }

    public function prepare($sql) {
        $q = $this->tr(trim($sql));
        try {
            $st = $this->pdo->prepare($q);
            return new NbMyStmt($st, $this);
        } catch (Throwable $e) {
            error_log('[db prepare] ' . $e->getMessage() . ' | ' . substr($q, 0, 160));
            return false;
        }
    }

    public function noteChanges($n) { $this->changes = (int)$n; }
    public function inImmediate() { return $this->imm; }

    private function beginTx($imm) {
        if ($this->inTx) return false;
        try { $this->pdo->beginTransaction(); $this->inTx = true; $this->imm = $imm; return true; }
        catch (Throwable $e) { return false; }
    }
    private function commitTx() {
        if (!$this->inTx) return true;
        try { $this->pdo->commit(); } catch (Throwable $e) { $this->inTx = false; $this->imm = false; return false; }
        $this->inTx = false; $this->imm = false; return true;
    }
    private function rollbackTx() {
        if (!$this->inTx) return true;
        try { $this->pdo->rollBack(); } catch (Throwable $e) {}
        $this->inTx = false; $this->imm = false; return true;
    }

    private function tr($sql) {
        $u = ltrim($sql);
        $head = strtoupper(substr($u, 0, 16));
        if (strncmp($head, 'CREATE TABLE', 12) === 0) return $this->trCreateTable($u);
        if (strncmp($head, 'CREATE INDEX', 12) === 0 || strncmp($head, 'CREATE UNIQUE', 13) === 0) return $this->trCreateIndex($u);
        if (strncmp($head, 'ALTER TABLE', 11) === 0) return $this->trAlter($u);

        $s = $sql;
        $s = preg_replace('/\bINSERT\s+OR\s+REPLACE\b/i', 'REPLACE', $s);
        $s = preg_replace('/\bINSERT\s+OR\s+IGNORE\b/i', 'INSERT IGNORE', $s);
        $s = $this->trUpsert($s);
        $s = preg_replace_callback('/json_extract\s*\(\s*([A-Za-z_][A-Za-z0-9_]*)\s*,\s*(\'[^\']*\'|"[^"]*")\s*\)/i',
            function ($m) { return 'JSON_UNQUOTE(JSON_EXTRACT(' . $m[1] . ', ' . $m[2] . '))'; }, $s);
        $s = preg_replace('/\bAS\s+INTEGER\b/i', 'AS SIGNED', $s);
        $s = preg_replace('/\bAS\s+REAL\b/i', 'AS DOUBLE', $s);
        $s = preg_replace('/\bRANDOM\s*\(\s*\)/i', 'RAND()', $s);
        $s = preg_replace('/\browid\b/i', '_nbid', $s);
        $s = $this->trMinMax($s);
        $s = $this->trStripQual($s);
        $s = $this->trAliasInWhere($s);
        $s = $this->trTables($s);
        $s = $this->trSelfRef($s);
        if ($this->imm) $s = $this->trForUpdate($s);
        return $s;
    }

    private function trCreateTable($sql) {
        $noRowid = (bool)preg_match('/WITHOUT\s+ROWID/i', $sql);
        $sql = preg_replace('/\)\s*WITHOUT\s+ROWID\s*;?\s*$/i', ')', $sql);
        if (!preg_match('/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*\((.*)\)\s*;?\s*$/is', trim($sql), $m)) {
            return $this->trTables($sql);
        }
        $ine   = $m[1] ? 'IF NOT EXISTS ' : '';
        $table = $m[2];
        $body  = $m[3];
        $full  = $this->prefix . $table;
        $items = nbSplitTop($body);

        $pk = [];
        foreach ($items as $it) {
            $it = trim($it);
            if (preg_match('/^PRIMARY\s+KEY\s*\(([^)]*)\)/i', $it, $pm)) {
                foreach (explode(',', $pm[1]) as $c) { $c = trim($c); if ($c !== '') $pk[strtolower($c)] = 1; }
            }
        }
        foreach ($items as $it) {
            $it = trim($it);
            if (preg_match('/^(PRIMARY\s+KEY|UNIQUE|CHECK|FOREIGN|CONSTRAINT)\b/i', $it)) continue;
            if (preg_match('/^(\S+)\s+/', $it, $cm) && preg_match('/\bPRIMARY\s+KEY\b/i', $it)) {
                $pk[strtolower(trim($cm[1], '`"'))] = 1;
            }
        }

        $colsOut = [];
        $colType = [];
        $hasInlineAutoPk = false;
        foreach ($items as $it) {
            $it = trim($it);
            if ($it === '') continue;
            if (preg_match('/^(PRIMARY\s+KEY|UNIQUE|CHECK|FOREIGN|CONSTRAINT)\b/i', $it)) {
                if (preg_match('/^PRIMARY\s+KEY\s*\(([^)]*)\)/i', $it, $pm2)) {
                    $cols = array_map(function ($c) { return trim($c); }, explode(',', $pm2[1]));
                    $colsOut['__pk__'] = 'PRIMARY KEY (' . implode(', ', $cols) . ')';
                }
                continue;
            }
            if (!preg_match('/^(\S+)\s+(.*)$/s', $it, $cm)) { continue; }
            $name = trim($cm[1], '`"');
            $rest = $cm[2];
            $isPk = isset($pk[strtolower($name)]);
            [$rest2, $base] = nbMapColType($rest, $isPk);
            $rest2 = preg_replace('/\bAUTOINCREMENT\b/i', 'AUTO_INCREMENT', $rest2);
            if (preg_match('/\bPRIMARY\s+KEY\b/i', $rest2) && preg_match('/\bAUTO_INCREMENT\b/i', $rest2)) $hasInlineAutoPk = true;
            $colType[strtolower($name)] = $base;
            $colsOut[strtolower($name)] = '`' . $name . '` ' . trim($rest2);
        }

        $needRowid = isset($this->rowidTbl[strtolower($table)]) || empty($pk);
        $hasPk = !empty($pk) || isset($colsOut['__pk__']) || $hasInlineAutoPk;
        $pkClause = isset($colsOut['__pk__']) ? $colsOut['__pk__'] : null;
        unset($colsOut['__pk__']);

        $lines = array_values($colsOut);
        if ($needRowid && !isset($colType['_nbid'])) {
            if (!$hasPk) {
                array_unshift($lines, '`_nbid` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY');
            } else {
                $lines[] = '`_nbid` BIGINT NOT NULL AUTO_INCREMENT';
                $lines[] = 'UNIQUE KEY `_nbk` (`_nbid`)';
            }
            $colType['_nbid'] = 'BIGINT';
        }
        if ($pkClause !== null) $lines[] = $pkClause;

        $this->tables[strtolower($table)] = 1;
        $this->cols[strtolower($table)]   = $colType;

        return 'CREATE TABLE ' . $ine . '`' . $full . '` (' . implode(', ', $lines) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    }

    private function trCreateIndex($sql) {
        if (!preg_match('/^CREATE\s+(UNIQUE\s+)?INDEX\s+(IF\s+NOT\s+EXISTS\s+)?([A-Za-z_][A-Za-z0-9_]*)\s+ON\s+([A-Za-z_][A-Za-z0-9_]*)\s*\((.*?)\)\s*(WHERE\b.*)?;?\s*$/is', trim($sql), $m)) {
            return '';
        }
        $uniq = $m[1] ? 'UNIQUE ' : '';
        $name = $m[3];
        $tbl  = strtolower($m[4]);
        $cols = trim($m[5]);
        if (strpos($cols, '(') !== false) return '';
        $full = $this->prefix . $m[4];
        $ct   = $this->cols[$tbl] ?? [];
        $parts = [];
        foreach (explode(',', $cols) as $c) {
            $c = trim($c);
            if ($c === '') continue;
            $dir = '';
            if (preg_match('/\s+(ASC|DESC)$/i', $c, $dm)) { $dir = ' ' . strtoupper($dm[1]); $c = preg_replace('/\s+(ASC|DESC)$/i', '', $c); }
            $cn = strtolower(trim($c, '`"'));
            $t  = $ct[$cn] ?? '';
            $plen = ($t === 'LONGTEXT' || $t === 'TEXT' || $t === 'LONGBLOB') ? '(190)' : '';
            $parts[] = '`' . trim($c, '`"') . '`' . $plen . $dir;
        }
        return 'CREATE ' . $uniq . 'INDEX `' . $name . '` ON `' . $full . '` (' . implode(', ', $parts) . ')';
    }

    private function trUpsert($s) {
        if (stripos($s, 'ON CONFLICT') === false) return $s;
        $s = preg_replace('/\bON\s+CONFLICT\s*\([^)]*\)\s+DO\s+UPDATE\s+SET\b/i', 'ON DUPLICATE KEY UPDATE', $s);
        $s = preg_replace('/\bON\s+CONFLICT\b.*?\bDO\s+NOTHING\b/is', '', $s);
        $s = preg_replace_callback('/\bexcluded\.([A-Za-z_][A-Za-z0-9_]*)/i', function ($m) { return 'VALUES(' . $m[1] . ')'; }, $s);
        return $s;
    }

    private function trMinMax($s) {
        foreach (['MAX' => 'GREATEST', 'MIN' => 'LEAST'] as $fn => $rep) {
            $out = '';
            $i = 0; $len = strlen($s);
            while ($i < $len) {
                if (preg_match('/\b' . $fn . '\s*\(/iA', substr($s, $i), $mm)) {
                    $open = $i + strlen($mm[0]) - 1;
                    $depth = 0; $j = $open; $comma = false;
                    for (; $j < $len; $j++) {
                        $ch = $s[$j];
                        if ($ch === '(') $depth++;
                        elseif ($ch === ')') { $depth--; if ($depth === 0) break; }
                        elseif ($ch === ',' && $depth === 1) $comma = true;
                    }
                    if ($comma && $j < $len) {
                        $inner = substr($s, $open, $j - $open + 1);
                        $out .= $rep . $inner;
                        $i = $j + 1;
                        continue;
                    }
                }
                $out .= $s[$i];
                $i++;
            }
            $s = $out;
        }
        return $s;
    }

    private function trAliasInWhere($s) {
        if (!preg_match('/^\s*SELECT\b/i', $s)) return $s;
        if (!preg_match('/\bWHERE\b/i', $s)) return $s;
        if (!preg_match('/^\s*SELECT\b(.*?)\bFROM\b/is', $s, $sm)) return $s;
        $list = $sm[1];
        $pairs = [];
        foreach (nbSplitTop($list) as $item) {
            if (preg_match('/^(.*)\s+AS\s+([A-Za-z_][A-Za-z0-9_]*)\s*$/is', trim($item), $am)) {
                $pairs[$am[2]] = trim($am[1]);
            }
        }
        if (!$pairs) return $s;
        return preg_replace_callback('/\bWHERE\b(.*?)(\bGROUP\s+BY\b|\bHAVING\b|\bORDER\s+BY\b|\bLIMIT\b|$)/is',
            function ($wm) use ($pairs) {
                $where = $wm[1];
                foreach ($pairs as $alias => $expr) {
                    $where = preg_replace('/\b' . preg_quote($alias, '/') . '\b/', '(' . $expr . ')', $where);
                }
                return 'WHERE' . $where . $wm[2];
            }, $s, 1);
    }

    private function trTables($s) {
        if (!$this->tables) return $s;
        $names = array_keys($this->tables);
        usort($names, function ($a, $b) { return strlen($b) - strlen($a); });
        $alt = implode('|', array_map('preg_quote', $names));
        $pfx = $this->prefix;
        return preg_replace_callback('/\b(FROM|JOIN|INTO|UPDATE|TABLE)\s+(' . $alt . ')\b/i',
            function ($m) use ($pfx) { return $m[1] . ' `' . $pfx . strtolower($m[2]) . '`'; }, $s);
    }

    private function trSelfRef($s) {
        $head = strtoupper(ltrim($s));
        $target = null;
        if (preg_match('/^(?:REPLACE|INSERT(?:\s+IGNORE)?)\s+INTO\s+`([^`]+)`/i', $s, $m)) $target = $m[1];
        elseif (preg_match('/^UPDATE\s+`([^`]+)`/i', $s, $m)) $target = $m[1];
        elseif (preg_match('/^DELETE\s+FROM\s+`([^`]+)`/i', $s, $m)) $target = $m[1];
        if ($target === null) return $s;
        $k = 0;
        $re = '/\(\s*(SELECT\b[^()]*\bFROM\s+`' . preg_quote($target, '/') . '`[^()]*)\)/i';
        return preg_replace_callback($re, function ($m) use (&$k) {
            return '(SELECT * FROM (' . $m[1] . ') AS _nbself' . ($k++) . ')';
        }, $s);
    }

    private function trStripQual($s) {
        if (!$this->tables) return $s;
        $names = array_keys($this->tables);
        usort($names, function ($a, $b) { return strlen($b) - strlen($a); });
        $alt = implode('|', array_map('preg_quote', $names));
        return preg_replace('/\b(' . $alt . ')\.(?=[A-Za-z_])/i', '', $s);
    }

    private function trAlter($sql) {
        if (!preg_match('/^ALTER\s+TABLE\s+([A-Za-z_][A-Za-z0-9_]*)\s+ADD\s+(COLUMN\s+)?([A-Za-z_][A-Za-z0-9_]*)\s+(.*?)\s*;?\s*$/is', trim($sql), $m)) {
            return $this->trTables($sql);
        }
        $full = $this->prefix . strtolower($m[1]);
        $col  = $m[3];
        [$rest, $base] = nbMapColType($m[4], false);
        if (isset($this->cols[strtolower($m[1])])) $this->cols[strtolower($m[1])][strtolower($col)] = $base;
        return 'ALTER TABLE `' . $full . '` ADD COLUMN `' . $col . '` ' . trim($rest);
    }

    private function trForUpdate($s) {
        if (!preg_match('/^\s*SELECT\b/i', $s)) return $s;
        if (preg_match('/\bFOR\s+UPDATE\b/i', $s) || preg_match('/\bLOCK\s+IN\s+SHARE\b/i', $s)) return $s;
        if (preg_match('/\bGROUP\s+BY\b/i', $s)) return $s;
        if (!preg_match('/\bFROM\b/i', $s)) return $s;
        return rtrim(rtrim($s), ';') . ' FOR UPDATE';
    }
}

class NbMyStmt {
    private $st;
    private $owner;
    private $binds = [];
    public function __construct(PDOStatement $st, NbMy $owner) { $this->st = $st; $this->owner = $owner; }
    public function bindValue($param, $value, $type = SQLITE3_TEXT) {
        $p = is_int($param) ? $param : (strncmp($param, ':', 1) === 0 ? $param : ':' . $param);
        if ($value === null) { $this->binds[$p] = [null, PDO::PARAM_NULL]; return true; }
        if ($type === SQLITE3_INTEGER) { $this->binds[$p] = [(int)$value, PDO::PARAM_INT]; return true; }
        $this->binds[$p] = [(string)$value, PDO::PARAM_STR];
        return true;
    }
    public function bindParam($param, &$value, $type = SQLITE3_TEXT) { return $this->bindValue($param, $value, $type); }
    public function reset() { try { $this->st->closeCursor(); } catch (Throwable $e) {} return true; }
    public function clear() { $this->binds = []; return true; }
    public function close() { return true; }
    public function execute() {
        try {
            try { $this->st->closeCursor(); } catch (Throwable $e) {}
            foreach ($this->binds as $p => $bv) $this->st->bindValue($p, $bv[0], $bv[1]);
            $this->st->execute();
            $this->owner->noteChanges($this->st->rowCount());
            return new NbMyResult($this->st);
        } catch (Throwable $e) {
            error_log('[db execute] ' . $e->getMessage());
            return false;
        }
    }
}

class NbMyResult {
    private $st;
    private $rows;
    private $pos = 0;
    private $done = false;
    public function __construct($st, $rows = null) { $this->st = $st; $this->rows = $rows; }
    public function fetchArray($mode = SQLITE3_BOTH) {
        if ($this->rows !== null) {
            if ($this->pos >= count($this->rows)) return false;
            $r = $this->rows[$this->pos++];
            if ($mode === SQLITE3_NUM) return array_values($r);
            if ($mode === SQLITE3_ASSOC) return $r;
            return array_merge($r, array_values($r));
        }
        if ($this->done || !$this->st) return false;
        $m = $mode === SQLITE3_ASSOC ? PDO::FETCH_ASSOC : ($mode === SQLITE3_NUM ? PDO::FETCH_NUM : PDO::FETCH_BOTH);
        try { $r = $this->st->fetch($m); } catch (Throwable $e) { $r = false; }
        if ($r === false) { $this->done = true; return false; }
        return $r;
    }
    public function numColumns() { if ($this->rows !== null) return $this->rows ? count($this->rows[0]) : 0; try { return $this->st->columnCount(); } catch (Throwable $e) { return 0; } }
    public function finalize() { if ($this->st) { try { $this->st->closeCursor(); } catch (Throwable $e) {} } return true; }
    public function reset() { $this->done = false; $this->pos = 0; return true; }
}

function nbSplitTop($s) {
    $out = []; $buf = ''; $depth = 0; $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $ch = $s[$i];
        if ($ch === '(') { $depth++; $buf .= $ch; }
        elseif ($ch === ')') { $depth--; $buf .= $ch; }
        elseif ($ch === ',' && $depth === 0) { $out[] = $buf; $buf = ''; }
        else $buf .= $ch;
    }
    if (trim($buf) !== '') $out[] = $buf;
    return $out;
}

function nbMapColType($rest, $isPk) {
    if (preg_match('/^(INTEGER|INT|BIGINT)\b/i', $rest)) {
        return [preg_replace('/^(INTEGER|INT|BIGINT)\b/i', 'BIGINT', $rest, 1), 'BIGINT'];
    }
    if (preg_match('/^(REAL|DOUBLE|FLOAT)\b/i', $rest)) {
        return [preg_replace('/^(REAL|DOUBLE|FLOAT)\b/i', 'DOUBLE', $rest, 1), 'DOUBLE'];
    }
    if (preg_match('/^(NUMERIC|DECIMAL)\b/i', $rest)) {
        return [preg_replace('/^(NUMERIC|DECIMAL)\b/i', 'DECIMAL(30,10)', $rest, 1), 'DECIMAL'];
    }
    if (preg_match('/^TEXT\b/i', $rest)) {
        if ($isPk) return [preg_replace('/^TEXT\b/i', 'VARCHAR(190)', $rest, 1), 'VARCHAR'];
        return [preg_replace('/^TEXT\b/i', 'LONGTEXT', $rest, 1), 'LONGTEXT'];
    }
    if (preg_match('/^BLOB\b/i', $rest)) {
        return [preg_replace('/^BLOB\b/i', 'LONGBLOB', $rest, 1), 'LONGBLOB'];
    }
    return [$rest, 'LONGTEXT'];
}

function nbAllStems() {
    return ['users', 'states', 'orders', 'seen', 'cache', 'ma_orders', 'support', 'translate',
            'coupons', 'services', 'num_acts', 'shield', 'monitor', 'wheel', 'top_members',
            'diamond_users', 'bank_users', 'quiz', 'games', 'mine_games', 'airdrop'];
}

function nbStemSqlitePath($stem) {
    return rtrim(DATA_DIR, '/') . '/' . $stem . '.sqlite';
}

function nbMigrateToMysql() {
    if (!extension_loaded('pdo_mysql')) return [false, 'افزونه‌ی pdo_mysql نصب نیست.', []];
    if (!class_exists('SQLite3')) return [false, 'افزونه‌ی SQLite3 برای خواندنِ فایل‌های فعلی لازم است.', []];
    $c = dbCreds();
    if (!dbConfigured($c)) return [false, 'اطلاعاتِ دیتابیس هنوز وارد نشده.', []];
    $report = [];
    $okAll = true;
    $cacheStems = nbCacheStems();
    foreach (nbAllStems() as $stem) {
        if (isset($cacheStems[$stem])) continue;
        $path = nbStemSqlitePath($stem);
        if (!is_file($path)) continue;
        $pdo = nbStemPdo($stem);
        if (!($pdo instanceof PDO)) { $okAll = false; $report[] = ['stem' => $stem, 'table' => '-', 'src' => 0, 'dst' => 0, 'ok' => false, 'msg' => 'اتصال برقرار نشد']; continue; }
        try { $src = new SQLite3($path); $src->busyTimeout(8000); }
        catch (Throwable $e) { $okAll = false; $report[] = ['stem' => $stem, 'table' => '-', 'src' => 0, 'dst' => 0, 'ok' => false, 'msg' => 'فایل باز نشد']; continue; }
        $nb = new NbMy($pdo, $stem);

        $tables = []; $indexes = [];
        $mr = $src->query("SELECT type, name, sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%'");
        while ($mr && ($row = $mr->fetchArray(SQLITE3_ASSOC))) {
            if ($row['type'] === 'table') $tables[$row['name']] = $row['sql'];
            elseif ($row['type'] === 'index') $indexes[] = $row['sql'];
        }
        foreach ($tables as $t => $sql) $nb->exec($sql);
        foreach ($indexes as $sql) $nb->exec($sql);

        foreach ($tables as $t => $_) {
            $cols = [];
            $ci = $src->query('PRAGMA table_info(' . $t . ')');
            while ($ci && ($cr = $ci->fetchArray(SQLITE3_ASSOC))) $cols[] = (string)$cr['name'];
            if (!$cols) continue;
            $full = nbTablePrefix($stem) . strtolower($t);
            $srcN = (int)$src->querySingle('SELECT COUNT(*) FROM "' . $t . '"');
            try { $pdo->exec('TRUNCATE TABLE `' . $full . '`'); } catch (Throwable $e) {}
            $collist = '`' . implode('`,`', $cols) . '`';
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $ins = $pdo->prepare('REPLACE INTO `' . $full . '` (' . $collist . ') VALUES (' . $ph . ')');
            $copied = 0;
            $pdo->beginTransaction();
            $rr = $src->query('SELECT ' . $collist . ' FROM "' . $t . '"');
            while ($rr && ($drow = $rr->fetchArray(SQLITE3_NUM)) !== false) {
                try { $ins->execute($drow); $copied++; } catch (Throwable $e) {}
                if ($copied % 2000 === 0) { $pdo->commit(); $pdo->beginTransaction(); }
            }
            $pdo->commit();
            $dstN = 0;
            try { $dstN = (int)$pdo->query('SELECT COUNT(*) FROM `' . $full . '`')->fetchColumn(); } catch (Throwable $e) {}
            $rowOk = $dstN >= $srcN;
            if (!$rowOk) $okAll = false;
            $report[] = ['stem' => $stem, 'table' => $t, 'src' => $srcN, 'dst' => $dstN, 'ok' => $rowOk, 'msg' => $rowOk ? '' : 'تعداد نخواند'];
        }
        $src->close();
    }
    return [$okAll, $okAll ? 'انتقال کامل شد.' : 'انتقال با هشدار تمام شد — ردیف‌ها را بررسی کنید.', $report];
}

function nbBackupFile() {
    $dir = rtrim(DATA_DIR, '/') . '/backups';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $stamp = date('Ymd_His');
    if (dbOn() && dbReady()) {
        $file = $dir . '/numbix_mysql_' . $stamp . '.sql';
        $fh = @fopen($file, 'wb');
        if (!$fh) return [false, '', '', 'نوشتنِ فایلِ بکاپ ممکن نشد.'];
        fwrite($fh, "-- Numbix MySQL backup " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach (nbAllStems() as $stem) {
            $pdo = nbStemPdo($stem);
            if (!($pdo instanceof PDO)) continue;
            foreach (nbStemTables($stem) as $t) {
                $full = nbTablePrefix($stem) . strtolower($t);
                try {
                    $cr = $pdo->query('SHOW CREATE TABLE `' . $full . '`')->fetch(PDO::FETCH_NUM);
                    if (!$cr) continue;
                } catch (Throwable $e) { continue; }
                fwrite($fh, "DROP TABLE IF EXISTS `$full`;\n" . $cr[1] . ";\n");
                $rs = $pdo->query('SELECT * FROM `' . $full . '`');
                $rows = 0;
                while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
                    $cols = '`' . implode('`,`', array_keys($row)) . '`';
                    $vals = implode(',', array_map(function ($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote((string)$v); }, array_values($row)));
                    fwrite($fh, "INSERT INTO `$full` ($cols) VALUES ($vals);\n");
                    $rows++;
                }
                fwrite($fh, "\n");
            }
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        @chmod($file, 0600);
        return [true, $file, basename($file), ''];
    }

    if (!class_exists('ZipArchive')) return [false, '', '', 'افزونه‌ی ZipArchive برای بکاپِ فایلی لازم است.'];
    $file = $dir . '/numbix_sqlite_' . $stamp . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return [false, '', '', 'ساختِ فایلِ زیپ ممکن نشد.'];
    $n = 0;
    foreach (nbAllStems() as $stem) {
        foreach ([nbStemSqlitePath($stem), nbStemSqlitePath($stem) . '-wal', nbStemSqlitePath($stem) . '-shm'] as $f)
            if (is_file($f)) { $zip->addFile($f, basename($f)); $n++; }
    }
    $zip->close();
    @chmod($file, 0600);
    if ($n === 0) { @unlink($file); return [false, '', '', 'هیچ فایلِ دیتابیسی برای بکاپ پیدا نشد.']; }
    return [true, $file, basename($file), ''];
}
