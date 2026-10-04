<?php
// Attack scenarios. Each "✅" means the attack was stopped.

echo "\n[1] Airdrop: fresh account farms crystals and cashes them out\n";
$A = 2001; mkUser($A, 0);
adUserSet($A, function (&$u) { $u['crystals'] = 50000.0; $u['level'] = 30; });
[$r1, $m1] = adRedeem($A, 40000);
$bal = (float)(getUser($A)['balance'] ?? 0);
ok(!$r1 && $bal == 0.0, 'cash-out refused for an account with no purchase (balance=' . $bal . ')');
doneOrder($A, 50000);
[$r2, $m2] = adRedeem($A, 40000);
$bal = (float)(getUser($A)['balance'] ?? 0);
ok(!$r2 && $bal == 0.0, 'cash-out of 2,000,000 toman refused by daily cap (balance=' . $bal . ')');
$day = defined('AD_CASHOUT_DAY_MAX') ? AD_CASHOUT_DAY_MAX : 0;
[$r3, ] = adRedeem($A, 200);
$bal = (float)(getUser($A)['balance'] ?? 0);
ok($r3 && $bal == 10000.0, 'cash-out within cap still works (balance=' . $bal . ')');
[$r4, ] = adRedeem($A, 100);
ok(!$r4, 'second cash-out the same day refused once cap is used');
$cr = (float)(adUser($A)['crystals'] ?? 0);
ok(abs($cr - (50000 - 200)) < 0.01, 'refused cash-outs did not burn crystals (' . $cr . ')');

$F = 2020; mkUser($F, 0); doneOrder($F, 20000);
adUserSet($F, function (&$u) { $u['crystals'] = 5000.0; });
[$rf, $mf] = adRedeem($F, 100);
ok(!$rf && (float)getUser($F)['balance'] == 0.0, 'spend cap: 20% of 20,000 = 4,000 toman max, 5,000 refused (' . $mf . ')');

echo "\n[2] Airdrop coupon: other user steals / guesses a personal code\n";
$B = 2002; mkUser($B, 0); doneOrder($B, 60000);
adUserSet($B, function (&$u) { $u['crystals'] = 1000.0; });
[$cok, $cres] = adRedeemCoupon($B, 200);
$code = is_array($cres) ? (string)$cres['code'] : '';
$C = 2003; mkUser($C, 0);
[$st, , ] = cpActivate($C, $code);
ok($code !== '' && !$st, 'code ' . $code . ' cannot be activated by another user');
[$st2, , ] = cpActivate($B, $code);
ok($st2, 'owner can still use the code');

echo "\n[3] Coupon brute force in the bot\n";
$D = 2004; mkUser($D, 0);
for ($i = 0; $i < 12; $i++) { setState($D, 'coupon'); masterHandle(msgUpd($D, 'AIR' . strtoupper(bin2hex(random_bytes(5))))); }
setState($D, 'coupon'); $GLOBALS['TG'] = [];
masterHandle(msgUpd($D, 'WHATEVER'));
ok(sent('تلاش‌های اشتباه'), 'after 8 wrong codes the user is locked out for an hour');

echo "\n[4] Mine game: 10-diamond entry, cash out after one safe pick\n";
cfgSet(function (&$c) { $c['mine']['on'] = true; });
$E = 2005; mkUser($E, 0); gmAdd($E, 100);
$g = mnCreate($E, -100123, 'E', '', 10);
mnSetGame($g['id'], function (&$x) { $x['status'] = 'waiting'; $x['msg_id'] = 1; return true; });
mnCallback('mn_join_' . $g['id'], $E, -100123, 1, 'c1', ['first_name' => 'E']);
$gg = mnGet($g['id']);
$safe = (int)$gg['mine_pos'] === 1 ? 2 : 1;
mnCallback('mn_pick_' . $g['id'] . '_' . $safe, $E, -100123, 1, 'c2', ['first_name' => 'E']);
mnCallback('mn_cash_' . $g['id'], $E, -100123, 1, 'c3', ['first_name' => 'E']);
$pts = gmPoints($E);
ok($pts <= 100.0 + 1e-6, 'one safe pick no longer turns 10 into 100 (diamonds: 100 -> ' . $pts . ')');

echo "\n[5] Tic-tac-toe: losing host presses cancel mid-game\n";
cfgSet(function (&$c) { $c['games']['on'] = true; });
$H = 2006; $J = 2007; mkUser($H); mkUser($J); gmAdd($H, 1000); gmAdd($J, 1000);
$tg = gmCreate('ttt', 100, $H, -100777, 'H', '');
gmAdd($H, -100);
gmCallback('gmj_' . $tg['id'], $J, -100777, 1, 'c4', ['first_name' => 'J']);
$st = gmGet($tg['id'])['status'] ?? '';
gmCallback('gmc_' . $tg['id'], $H, -100777, 1, 'c5', ['first_name' => 'H']);
ok($st === 'playing' && gmPoints($H) < 1000 && gmPoints($J) > 1000,
   'cancel during play = forfeit (host ' . gmPoints($H) . ', joiner ' . gmPoints($J) . ')');

echo "\n[6] Tic-tac-toe: stalling until timeout\n";
$K = 2008; $L = 2009; mkUser($K); mkUser($L); gmAdd($K, 1000); gmAdd($L, 1000);
$t2 = gmCreate('ttt', 100, $K, -100778, 'K', ''); gmAdd($K, -100);
gmCallback('gmj_' . $t2['id'], $L, -100778, 1, 'c6', ['first_name' => 'L']);
gmSetGame($t2['id'], function (&$x) { $x['moved'] = time() - 10000; return true; });
gmTick(50);
ok(gmPoints($K) < 1000 && gmPoints($L) > 1000, 'player on turn who stalls loses (K ' . gmPoints($K) . ', L ' . gmPoints($L) . ')');

echo "\n[7] HTML injection through first name in diamond messages\n";
cfgSet(function (&$c) { $c['diamond']['on'] = true; $c['diamond']['group_only'] = 0; });
$M = 2010; mkUser($M);
[$msg] = dmHit($M, '<a href="https://evil.example">Claim prize</a>');
ok(!str_contains($msg, '<a href="https://evil') && $msg !== '', 'name is escaped in the reward message');

echo "\n[8] Banned user keeps playing in groups\n";
$N = 2011; mkUser($N); gmAdd($N, 1000);
mutateUser($N, function (&$u) { $u['banned'] = true; });
$before = gmPoints($N); $GLOBALS['TG'] = [];
masterHandle(msgUpd($N, 'چالش 100', -100999, 'supergroup'));
ok(gmPoints($N) == $before, 'banned user cannot start a stake game in a group');

echo "\n[9] Iranian gateway left in sandbox mode\n";
cfgSet(function (&$c) { $c['irpay'] = ['on' => true, 'provider' => 'zibal', 'sandbox' => true, 'merchant' => 'zibal', 'min' => 10000]; });
$P = 2012; mkUser($P); mutateUser($P, function (&$u) { $u['phone'] = '09121234567'; });
$GLOBALS['PAY'] = function ($url, $h, $b) {
    if (str_contains($url, '/v1/request')) return ['ok' => true, 'data' => ['result' => 100, 'trackId' => 555]];
    if (str_contains($url, '/v1/verify'))  return ['ok' => true, 'data' => ['result' => 100, 'amount' => (int)($b['amount'] ?? 0) ?: 1000000, 'refNumber' => 'r1']];
    return null;
};
[$o, $why] = function_exists('irSandboxBlocked') ? tuIranNew($P, '', 100000) : [Order::get(Order::create($P, '', 100000)), ''];
if ($o === null) { ok(true, 'user cannot even open a sandbox payment (' . $why . ')'); }
else {
    Order::set($o['id'], function (&$x) { $x['method'] = 'iran'; $x['ir'] = ['prov' => 'zibal', 'ref' => '555']; });
    payCheck(Order::get($o['id']), true);
    ok((float)(getUser($P)['balance'] ?? 0) == 0.0, 'sandbox "payment" does not credit a normal user');
}

echo "\n[10] User cancels a crypto invoice, then pays it anyway\n";
cfgSet(function (&$c) { $c['gateway'] = ['on' => true, 'provider' => 'oxapay', 'api_key' => 'MERCHANTKEY123', 'base_url' => 'https://bot.example.com/b.php', 'rate' => 60000]; });
$Q = 2013; mkUser($Q);
$oid = Order::create($Q, '', 300000);
Order::set($oid, function (&$x) { $x['method'] = 'crypto'; $x['gw'] = ['invoice' => 'TRK1', 'expires_at' => time() + 600]; });
masterHandle(cbUpd($Q, 'ocancel_' . $oid));
$GLOBALS['PAY'] = function ($url, $h, $b) {
    if (str_contains($url, 'api.oxapay.com/v1/payment/TRK1')) return ['ok' => true, 'data' => ['status' => 200, 'data' => ['status' => 'paid']]];
    return null;
};
gwPoll(10);
ok((float)(getUser($Q)['balance'] ?? 0) == 300000.0, 'late payment still credited (balance=' . (float)(getUser($Q)['balance'] ?? 0) . ')');

echo "\n[11] Duplicate IPN / poll race never double-credits\n";
gwSettle($oid); gwSettle($oid); gwPoll(10);
ok((float)(getUser($Q)['balance'] ?? 0) == 300000.0, 'still exactly 300,000 after repeats');

echo "\n[12] Non-admin stuck in an admin edit state\n";
$R = 2014; mkUser($R);
$before = (string)(cfg()['support_main']['direct']['text'] ?? '');
setState($R, 'sup_text', ['which' => 'direct']);
masterHandle(msgUpd($R, 'HACKED'));
ok((string)(cfg()['support_main']['direct']['text'] ?? '') === $before, 'support button text unchanged');

echo "\n[13] Wallet debit race: 30 parallel-looking buys with balance for 3\n";
$S = 2015; mkUser($S, 30000);
$okN = 0; for ($i = 0; $i < 30; $i++) if (maDebit($S, 10000, 'x' . $i)) $okN++;
ok($okN === 3 && (float)getUser($S)['balance'] == 0.0, 'only 3 debits succeeded, balance 0');
