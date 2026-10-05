<?php
/**
 * Simulates Telegram messages (nothing is sent, synthetic market data, Gemini off):
 *   php tools/selftest.php
 */
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$config = app_config();
$config['gemini']['api_key'] = '';
$config['market']['demo'] = true;
$today = (int) date('w');
$dbFile = sys_get_temp_dir() . '/bot_test_' . getmypid() . '.sqlite';
@unlink($dbFile);
$db = new App\Storage($dbFile);
$tg = new App\Telegram\Client('x', true);

$uid = 1;
$dm = function (string $text, int $user = 5550001, array $extra = []) use (&$uid) {
    return ['update_id' => $uid++, 'message' => array_merge([
        'message_id' => 100 + $uid,
        'from' => ['id' => $user, 'is_bot' => false, 'first_name' => 'Ali', 'username' => 'ali'],
        'chat' => ['id' => -1009999, 'type' => 'supergroup', 'is_direct_messages' => true, 'title' => 'DM'],
        'direct_messages_topic' => ['topic_id' => 77, 'user' => ['id' => $user]],
        'text' => $text,
    ], $extra)];
};
$pm = function (string $text, int $user) use (&$uid) { return ['update_id' => $uid++, 'message' => [
    'message_id' => 500 + $uid, 'from' => ['id' => $user, 'is_bot' => false, 'first_name' => 'Admin'],
    'chat' => ['id' => $user, 'type' => 'private'], 'text' => $text]]; };

$run = function (string $label, array $update, array $cfg) use ($tg, $db) {
    $tg->sent = [];
    (new App\Bot($cfg, $tg, $db))->handle($update);
    echo "== $label\n";
    foreach ($tg->sent as [$m, $p]) {
        $txt = $p['text'] ?? $p['caption'] ?? ($p['message_id'] ?? '');
        echo "  -> $m chat=" . ($p["chat_id"] ?? "") . " topic=" . ($p['direct_messages_topic_id'] ?? '-') . ' | ' . str_replace("\n", ' ⏎ ', mb_substr(strip_tags((string) $txt), 0, 150)) . "\n";
    }
    if (!$tg->sent) echo "  (no reply)\n";
};

$open = $config; $open['analysis_days'] = [$today];
$closed = $config; $closed['analysis_days'] = [($today + 1) % 7, ($today + 2) % 7];

$run('DM hello (ignored, left for admin)', $dm('سلام وقت بخیر'), $open);
$run('DM analysis #1', $dm('تحلیل BTC'), $open);
$u = $dm('eth 1h'); $run('DM analysis #2', $u, $open);
$run('duplicate update (ignored)', $u, $open);
$run('DM analysis #3 (quota)', $dm('تحلیل SOL روزانه'), $open);
$run('DM quota question', $dm('سهمیه'), $open);
$run('DM unknown coin', $dm('تحلیل XYZQ', 5550002), $open);
$run('DM on closed day', $dm('تحلیل BTC', 5550003), $closed);
$run('DM from channel (ignored)', $dm('تحلیل BTC', 5550004, ['sender_chat' => ['id' => -100123, 'type' => 'channel']]), $open);
$admin = $config['admin_ids'][0];
$run('admin /stats', $pm('/stats', $admin), $closed);
$run('admin /addquota', $pm('/addquota 5550001 1', $admin), $closed);
$run('DM analysis after bonus', $dm('تحلیل doge'), $open);
$run('admin /brain', $pm('/brain همیشه تارگت‌ها را روی نقدینگی بگذار', $admin), $closed);
$run('admin /brain show', $pm('/brain', $admin), $closed);
$run('admin /test on closed day', $pm('/test PEPE 1h', $admin), $closed);
$run('admin /off then user', $pm('/off', $admin), $open);
$run('user while paused', $dm('تحلیل BTC', 5550005), $open);
$run('admin /on', $pm('/on', $admin), $open);

// ---- panel + channel publishing
$cb = function (string $data, int $user) use (&$uid) {
    return ['update_id' => $uid++, 'callback_query' => ['id' => 'cb' . $uid, 'from' => ['id' => $user], 'data' => $data,
        'message' => ['message_id' => 900, 'chat' => ['id' => $user, 'type' => 'private']]]];
};
$run('admin /panel', $pm('/panel', $admin), $open);
$run('panel: set channel button', $cb('an:channel', $admin), $open);
$tg->fake = [
    'getChat' => ['id' => -1001234567890, 'type' => 'channel', 'title' => 'Signals', 'username' => 'signals_ch'],
    'getChatMember' => ['status' => 'administrator', 'can_post_messages' => true],
];
$run('admin sends @signals_ch', $pm('@signals_ch', $admin), $open);
$tg->fake = [];
$run('panel: quota +1', $cb('an:quota:1', $admin), $open);
$run('panel: days view', $cb('an:days', $admin), $open);
$run('panel: toggle today', $cb('an:day:' . $today, $admin), $open);
$run('panel: toggle today back on', $cb('an:day:' . $today, $admin), $open);
$run('non-admin callback (ignored)', $cb('an:pause', 5550001), $open);
$run('DM analysis -> published to channel', $dm('تحلیل ETH', 5550010), $open);
$run('panel: DM notice off', $cb('an:dm', $admin), $open);
$run('DM analysis, notice off', $dm('تحلیل SOL', 5550011), $open);
$run('panel: remove channel', $cb('an:channel_clear', $admin), $open);
$run('DM analysis -> back to DM', $dm('تحلیل BNB', 5550012), $open);

// ---- welcome + editable texts
$pmUser = function (string $text, int $user, array $entities = []) use (&$uid) {
    return ['update_id' => $uid++, 'message' => ['message_id' => 700 + $uid, 'from' => ['id' => $user, 'is_bot' => false, 'first_name' => 'Sara'],
        'chat' => ['id' => $user, 'type' => 'private'], 'text' => $text, 'entities' => $entities]];
};
$run('user /start (welcome)', $pmUser('/start', 5550020), $open);
$run('panel: texts list', $cb('an:texts', $admin), $open);
$run('panel: edit welcome', $cb('an:text:welcome', $admin), $open);
// "🔥 خوش آمدی {name}" with a premium emoji (2 UTF-16 units) and bold name part
$run('admin sends new welcome', $pmUser("🔥 خوش آمدی {name}", $admin, [
    ['type' => 'custom_emoji', 'offset' => 0, 'length' => 2, 'custom_emoji_id' => '5368324170671202286'],
    ['type' => 'bold', 'offset' => 3, 'length' => 15],
]), $open);
$run('user /start (custom welcome)', $pmUser('/start', 5550021), $open);
$run('panel: edit caption', $cb('an:text:caption', $admin), $open);
$cap = "{side_icon} #{base} | {tf}\nورود {entry_low} - {entry_high}\nاستاپ {stop}\nتارگت {tp1}\n{reasons}";
$run('admin sends caption with quote', $pmUser($cap, $admin, [
    ['type' => 'bold', 'offset' => 12, 'length' => 7],
    ['type' => 'blockquote', 'offset' => mb_strlen($cap) - 9, 'length' => 9],
]), $open);
$run('DM analysis with custom caption', $dm('تحلیل ADA', 5550022), $open);
$run('panel: reset caption', $cb('an:text_reset:caption', $admin), $open);
@unlink($dbFile);
