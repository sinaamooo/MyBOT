<?php
// One redemption attempt in its own process, sharing the test DATA_DIR on disk.
// Exit 0 = the coupon was redeemed, 1 = refused (cap/limit), 2 = bootstrap error.
// Used by t_race.php to prove the per-user and global caps hold under a real race.
error_reporting(E_ERROR | E_PARSE);
[$self, $botdir, $data, $code, $uid, $oid] = array_pad($argv, 6, '');

define('BOT_TOKEN', '123456:TEST_TOKEN_abcdefghijklmnopqrstuvwxyz');
define('ADMIN_ID', 1000);
define('ADMIN_IDS', [1000]);
define('WEBHOOK_SECRET', 'test_secret_value');
define('CRON_KEY', 'cron_key_long_enough');
define('ADMIN_PANEL_PASS', 'Very$trongPass123');
define('DATA_DIR', $data);
define('MEMBERSHIP_LIB_ONLY', true);

function __tgHook($t, $m, $d) { return ['ok' => true, 'result' => ['message_id' => 1]]; }
function __payHook($u, $h, $b) { return null; }

try {
    require $botdir . '/bot_master_membership.php';
} catch (Throwable $e) {
    fwrite(STDERR, 'bootstrap: ' . $e->getMessage() . "\n");
    exit(2);
}

// Line up on a shared start time so the processes truly collide on the lock.
$start = (float)getenv('RACE_AT');
if ($start > 0) { $now = microtime(true); if ($start > $now) usleep((int)(($start - $now) * 1e6)); }

$ok = cpRedeem($code, (int)$uid, (string)$oid, 1000.0);
exit($ok ? 0 : 1);
