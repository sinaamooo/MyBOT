<?php
// CLI test harness: loads the bot as a library with a fake Telegram API.
// Usage: php tests/harness.php [bot_dir] tests/t_attacks.php tests/t_regress.php ...
$BOT = (isset($argv[1]) && is_dir($argv[1])) ? $argv[1] : dirname(__DIR__) . '/numbix';
$tests = (isset($argv[1]) && is_dir($argv[1])) ? array_slice($argv, 2) : array_slice($argv, 1);
$DATA = sys_get_temp_dir() . '/nbx_test_' . getmypid() . '_' . bin2hex(random_bytes(3));
@mkdir($DATA, 0700, true);

define('BOT_TOKEN', '123456:TEST_TOKEN_abcdefghijklmnopqrstuvwxyz');
define('ADMIN_ID', 1000);
define('ADMIN_IDS', [1000]);
define('WEBHOOK_SECRET', 'test_secret_value');
define('CRON_KEY', 'cron_key_long_enough');
define('ADMIN_PANEL_PASS', 'Very$trongPass123');
define('DATA_DIR', $DATA);
define('MEMBERSHIP_LIB_ONLY', true);

$GLOBALS['TG'] = [];
$GLOBALS['MID'] = 100;
function __tgHook($token, $method, $data) {
    $GLOBALS['TG'][] = [$method, $data];
    if ($method === 'getMe') return ['ok' => true, 'result' => ['id' => 123456, 'username' => 'test_bot', 'is_bot' => true]];
    if ($method === 'getChatMember') return ['ok' => true, 'result' => ['status' => 'member']];
    return ['ok' => true, 'result' => ['message_id' => ++$GLOBALS['MID'], 'chat' => ['id' => $data['chat_id'] ?? 0]]];
}
$GLOBALS['PAY'] = null;
function __payHook($url, $headers, $body) { return is_callable($GLOBALS['PAY']) ? ($GLOBALS['PAY'])($url, $headers, $body) : null; }

require $BOT . '/bot_master_membership.php';

$GLOBALS['PASS'] = 0; $GLOBALS['FAIL'] = 0;
function ok($cond, $name) {
    if ($cond) { $GLOBALS['PASS']++; echo "  ✅ $name\n"; }
    else       { $GLOBALS['FAIL']++; echo "  ❌ $name\n"; }
}
function sent($needle) {
    foreach ($GLOBALS['TG'] as [$m, $d]) {
        $t = (string)($d['text'] ?? $d['caption'] ?? '');
        if (str_contains($t, $needle)) return true;
    }
    return false;
}
function lastText() {
    for ($i = count($GLOBALS['TG']) - 1; $i >= 0; $i--) {
        $d = $GLOBALS['TG'][$i][1];
        if (isset($d['text'])) return (string)$d['text'];
    }
    return '';
}
function msgUpd($uid, $text, $chat = null, $type = 'private', $extra = []) {
    static $n = 1;
    return ['update_id' => $n++, 'message' => array_merge([
        'message_id' => 5000 + $n, 'from' => ['id' => $uid, 'first_name' => 'U' . $uid, 'is_bot' => false],
        'chat' => ['id' => $chat ?? $uid, 'type' => $type], 'date' => time(), 'text' => $text,
    ], $extra)];
}
function cbUpd($uid, $data, $chat = null, $type = 'private', $mid = 777) {
    static $n = 1;
    return ['update_id' => 900000 + $n, 'callback_query' => [
        'id' => 'cb' . ($n++), 'from' => ['id' => $uid, 'first_name' => 'U' . $uid, 'is_bot' => false],
        'message' => ['message_id' => $mid, 'chat' => ['id' => $chat ?? $uid, 'type' => $type]], 'data' => $data,
    ]];
}
function mkUser($uid, $bal = 0) {
    touchUser($uid, 'user' . $uid, 'U' . $uid);
    mutateUser($uid, function (&$u) use ($bal) { $u['balance'] = $bal; });
}
function doneOrder($uid, $total = 0) {
    $oid = MaOrder::create($uid, '', ['id' => 'x', 'name' => 'x', 'emoji' => '☎️'], $total);
    MaOrder::set($oid, function (&$x) { $x['status'] = MaOrder::DONE; return true; });
    return $oid;
}

register_shutdown_function(function () use ($DATA) {
    echo "\nRESULT: " . $GLOBALS['PASS'] . ' passed, ' . $GLOBALS['FAIL'] . " failed\n";
    exec('rm -rf ' . escapeshellarg($DATA));
});

foreach ($tests as $f) require $f;
