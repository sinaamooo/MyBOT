<?php
// Normal flows must keep working after the fixes.

function signInit(array $fields) {
    $secret = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
    ksort($fields);
    $pairs = [];
    foreach ($fields as $k => $v) $pairs[] = $k . '=' . $v;
    $fields['hash'] = hash_hmac('sha256', implode("\n", $pairs), $secret);
    return http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
}

echo "\n[R1] Mini-app login signature\n";
$user = json_encode(['id' => 3001, 'first_name' => 'Ali'], JSON_UNESCAPED_UNICODE);
$good = signInit(['user' => $user, 'auth_date' => (string)time(), 'query_id' => 'AAH']);
$why = '';
ok((maVerifyInitData($good, $why)['id'] ?? 0) === 3001, 'valid initData accepted');
$bad = str_replace('3001', '3002', $good);
ok(maVerifyInitData($bad, $why) === null && $why === 'bad_hash', 'tampered user id rejected');
$old = signInit(['user' => $user, 'auth_date' => (string)(time() - 86400 * 3)]);
ok(maVerifyInitData($old, $why) === null && str_starts_with($why, 'expired'), 'old initData rejected');

echo "\n[R2] Crypto top-up credits exactly once\n";
cfgSet(function (&$c) { $c['gateway'] = ['on' => true, 'provider' => 'oxapay', 'api_key' => 'MERCHANTKEY123', 'base_url' => 'https://bot.example.com/b.php', 'rate' => 60000]; });
$U = 3002; mkUser($U);
$oid = Order::create($U, '', 150000);
Order::set($oid, function (&$x) { $x['method'] = 'crypto'; $x['gw'] = ['invoice' => 'TRK9', 'expires_at' => time() + 600]; });
ok(gwSettle($oid) === true, 'gateway settle succeeds');
ok((float)getUser($U)['balance'] == 150000.0, 'balance 150,000');
ok(Order::get($oid)['status'] === Order::APPROVED, 'order approved');
ok(gwSettle($oid) === false && (float)getUser($U)['balance'] == 150000.0, 'second settle is a no-op');

echo "\n[R3] Cancel of an order without an invoice still deletes it\n";
$o2 = Order::create($U, '', 50000);
Order::cancel($o2);
ok(Order::get($o2) === null, 'plain pending order deleted on cancel');

echo "\n[R4] Tic-tac-toe normal win pays the winner\n";
$X = 3003; $Y = 3004; mkUser($X); mkUser($Y); gmAdd($X, 1000); gmAdd($Y, 1000);
$g = gmCreate('ttt', 100, $X, -100555, 'X', ''); gmAdd($X, -100);
gmCallback('gmj_' . $g['id'], $Y, -100555, 1, 'k1', ['first_name' => 'Y']);
foreach ([[$X, 0], [$Y, 3], [$X, 1], [$Y, 4], [$X, 2]] as [$who, $cell])
    gmCallback('gmm_' . $g['id'] . '_' . $cell, $who, -100555, 1, 'k' . $cell, ['first_name' => 'p']);
ok(gmGet($g['id'])['status'] === 'done' && gmPoints($X) == 1080.0 && gmPoints($Y) == 900.0,
   'X wins: X=' . gmPoints($X) . ' Y=' . gmPoints($Y));

echo "\n[R5] Open game can still be cancelled by its host with a refund\n";
$g2 = gmCreate('ttt', 50, $X, -100556, 'X', ''); gmAdd($X, -50);
gmCallback('gmc_' . $g2['id'], $X, -100556, 1, 'k9', ['first_name' => 'X']);
ok(gmGet($g2['id'])['status'] === 'cancelled' && gmPoints($X) == 1080.0, 'refunded to 1080');

echo "\n[R6] Mine: bigger entries still pay a real prize\n";
cfgSet(function (&$c) { $c['mine']['on'] = true; });
ok(mnRewardFor(1000, 1) == 100.0 && mnRewardFor(100, 1) == 100.0 && mnRewardFor(10, 1) == 10.0,
   'cap: 1000->' . mnRewardFor(1000, 1) . ' 100->' . mnRewardFor(100, 1) . ' 10->' . mnRewardFor(10, 1));
ok(mnRewardFor(100, 8) <= 855 && mnRewardFor(100, 8) > 100, 'all 8 safe with entry 100 pays ' . mnRewardFor(100, 8));

echo "\n[R7] Diamond hit still works for normal names\n";
cfgSet(function (&$c) { $c['diamond']['on'] = true; });
$Z = 3005; mkUser($Z);
[$m, $won] = dmHit($Z, 'Sara & Co');
ok($won && str_contains($m, 'Sara &amp; Co'), 'reward message ok and escaped');

echo "\n[R8] Airdrop tap still works\n";
$T = 3006; mkUser($T);
$r = adTap($T, 10);
ok(is_array($r) && $r['added'] > 0, 'tap added ' . ($r['added'] ?? 0));

echo "\n[R9] Fixed coupons from the wheel still work for anyone\n";
$cp = cpMakePercent('WHL', 10, 7);
[$ok1] = cpActivate(3007, $cp['code']);
ok($ok1, 'percent coupon usable');

echo "\n[R10] Admin can still edit settings from the bot\n";
setState(ADMIN_ID, 'sup_text', ['which' => 'direct']);
mkUser(ADMIN_ID);
masterHandle(msgUpd(ADMIN_ID, 'پشتیبانی جدید'));
ok((string)(cfg()['support_main']['direct']['text'] ?? '') === 'پشتیبانی جدید', 'admin edit applied');

echo "\n[R11] Support ticket from a user reaches the admin\n";
$W = 3008; mkUser($W); $GLOBALS['TG'] = [];
setState($W, 'ticket');
masterHandle(msgUpd($W, 'سلام، شماره نیامد'));
$toAdmin = false; foreach ($GLOBALS['TG'] as [$mm, $d]) if ((string)($d['chat_id'] ?? '') === (string)ADMIN_ID) $toAdmin = true;
ok($toAdmin, 'ticket forwarded');

echo "\n[R12] NowPayments signature with nested data\n";
$d = ['payment_id' => 5, 'order_id' => 'or_x', 'payment_status' => 'finished', 'fee' => ['b' => 1, 'a' => 2]];
$sorted = $d; gwKsortDeep($sorted);
ok(json_encode($sorted) === '{"fee":{"a":2,"b":1},"order_id":"or_x","payment_id":5,"payment_status":"finished"}', 'recursive sort');
