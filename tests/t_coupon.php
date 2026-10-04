<?php
// Admin coupon maker in /panel: build a code with a set count, confirm users can
// use it up to that count, then toggle/delete — all through real bot callbacks.

function lastCode() {
    // find the newest admin (owner=0) coupon
    $db = cpDb(); $r = $db->query("SELECT code FROM coupons WHERE owner = 0 ORDER BY created_at DESC LIMIT 1");
    $row = $r ? $r->fetchArray(SQLITE3_ASSOC) : null; return $row ? (string)$row['code'] : '';
}

echo "\n[C1] admin opens the coupon panel\n";
mkUser(ADMIN_ID);
$GLOBALS['TG'] = [];
masterHandle(cbUpd(ADMIN_ID, 'cpadm_home'));
ok(sent('کدهای تخفیف'), 'coupon panel renders');

echo "\n[C2] build a 20% code usable 3 times total\n";
masterHandle(cbUpd(ADMIN_ID, 'cpadm_new'));
masterHandle(cbUpd(ADMIN_ID, 'cpn_val'));  masterHandle(msgUpd(ADMIN_ID, '20'));   // 20%
masterHandle(cbUpd(ADMIN_ID, 'cpn_cnt'));  masterHandle(msgUpd(ADMIN_ID, '3'));    // 3 uses total
masterHandle(cbUpd(ADMIN_ID, 'cpn_peru')); masterHandle(msgUpd(ADMIN_ID, '1'));    // 1 per user
$GLOBALS['TG'] = [];
masterHandle(cbUpd(ADMIN_ID, 'cpn_make'));
$code = lastCode();
$c = cpGet($code);
ok($code !== '' && $c['kind'] === 'percent' && (float)$c['value'] == 20.0 && (int)$c['max_uses'] == 3 && (int)$c['owner'] === 0,
   "code $code created: 20%, max_uses=" . (int)$c['max_uses'] . ", owner=0");
ok(sent($code), 'the generated code is shown to the admin');

echo "\n[C3] three different users can redeem it, the 4th cannot\n";
$good = 0;
for ($i = 1; $i <= 4; $i++) {
    $u = 8100 + $i; mkUser($u, 0);
    [$ok1] = cpActivate($u, $code);
    // simulate consuming one use (a purchase) so max_uses counts down
    if ($ok1) { if (cpRedeem($code, $u, 'ord' . $u, 1000)) $good++; }
}
ok($good === 3, "exactly 3 redemptions allowed by the count limit (got $good)");
ok((int)cpGet($code)['used'] === 3, 'used counter = 3');

echo "\n[C4] same user cannot use it twice (per-user = 1)\n";
$u = 8201; mkUser($u, 0);
// fresh code with 10 uses but 1 per user
masterHandle(cbUpd(ADMIN_ID, 'cpadm_new'));
masterHandle(cbUpd(ADMIN_ID, 'cpn_val')); masterHandle(msgUpd(ADMIN_ID, '15'));
masterHandle(cbUpd(ADMIN_ID, 'cpn_cnt')); masterHandle(msgUpd(ADMIN_ID, '10'));
masterHandle(cbUpd(ADMIN_ID, 'cpn_peru')); masterHandle(msgUpd(ADMIN_ID, '1'));
masterHandle(cbUpd(ADMIN_ID, 'cpn_make'));
$code2 = lastCode();
cpActivate($u, $code2); cpRedeem($code2, $u, 'o1', 1000);
[$ok2] = cpValidate($code2, $u, 1000);   // second attempt by same user
ok(!$ok2, 'per-user limit blocks the same user from reusing');

echo "\n[C5] custom code + fixed amount\n";
masterHandle(cbUpd(ADMIN_ID, 'cpadm_new'));
masterHandle(cbUpd(ADMIN_ID, 'cpn_type'));   // → fixed
masterHandle(cbUpd(ADMIN_ID, 'cpn_val'));  masterHandle(msgUpd(ADMIN_ID, '5000'));  // 5000 toman
masterHandle(cbUpd(ADMIN_ID, 'cpn_code')); masterHandle(msgUpd(ADMIN_ID, 'EID1404'));
masterHandle(cbUpd(ADMIN_ID, 'cpn_make'));
$cc = cpGet('EID1404');
ok($cc && $cc['kind'] === 'fixed' && (float)$cc['value'] == 5000.0, 'custom code EID1404 = 5000 toman fixed');

echo "\n[C6] toggle off then on, and delete\n";
masterHandle(cbUpd(ADMIN_ID, 'cpadm_t_EID1404'));
ok(empty(cpGet('EID1404')['on_flag']), 'toggled off');
masterHandle(cbUpd(ADMIN_ID, 'cpadm_t_EID1404'));
ok(!empty(cpGet('EID1404')['on_flag']), 'toggled back on');
masterHandle(cbUpd(ADMIN_ID, 'cpadm_d_EID1404'));
ok(cpGet('EID1404') === null, 'deleted');

echo "\n[C7] bad input is rejected, no junk code made\n";
$before = count(cpAdminList(100));
masterHandle(cbUpd(ADMIN_ID, 'cpadm_new'));
// make with value still 0 → should refuse
$GLOBALS['TG'] = [];
masterHandle(cbUpd(ADMIN_ID, 'cpn_make'));
ok(count(cpAdminList(100)) === $before, 'make with value 0 creates nothing');

echo "\n[C8] non-admin cannot create a code\n";
$N = 8300; mkUser($N);
$before = count(cpAdminList(100));
masterHandle(cbUpd($N, 'cpadm_new'));
setState($N, 'cp_in', cpDraftDefault() + ['ask' => 'val', 'value' => 50]);
masterHandle(msgUpd($N, '50'));
masterHandle(cbUpd($N, 'cpn_make'));
ok(count(cpAdminList(100)) === $before, 'non-admin made no coupon');
