<?php
// The new airdrop admin section in /panel: open it, change the crystal price, and
// confirm the new price actually drives cash-out — all through real bot callbacks.

echo "\n[P1] admin reaches the airdrop panel from the games menu\n";
mkUser(ADMIN_ID);
$GLOBALS['TG'] = [];
masterHandle(cbUpd(ADMIN_ID, 'ag_games'));
masterHandle(cbUpd(ADMIN_ID, 'adadm_home'));
ok(sent('ایردراپ') && sent('قیمتِ هر کریستال'), 'airdrop panel renders with the crystal price');

echo "\n[P2] change the crystal price from the panel to 10 toman\n";
masterHandle(cbUpd(ADMIN_ID, 'adadm_e_rate'));          // press "price" → asks for a number
masterHandle(msgUpd(ADMIN_ID, '10'));                   // admin sends 10
ok(abs(adRedeemRate() - 10.0) < 0.001, 'adRedeemRate() is now 10 (was 50): ' . adRedeemRate());
ok((float)(cfg()['airdrop']['redeem_rate'] ?? 0) == 10.0, 'persisted in cfg()[airdrop][redeem_rate]');

echo "\n[P3] the new price actually drives cash-out\n";
$U = 7001; mkUser($U, 0); doneOrder($U, 1000000);       // a real buyer so the gate passes
adUserSet($U, function (&$u) { $u['crystals'] = 5000.0; });
// day cap is 10000 toman; at 10 toman/crystal that's 1000 crystals
[$ok1, $t1] = adRedeem($U, 100);                        // 100 crystals * 10 = 1000 toman
ok($ok1 && (float)getUser($U)['balance'] == 1000.0, '100 crystals → 1000 toman at the new rate (balance=' . (float)getUser($U)['balance'] . ')');

echo "\n[P4] raise the daily cap from the panel, then a bigger cash-out works\n";
masterHandle(cbUpd(ADMIN_ID, 'adadm_e_day'));
masterHandle(msgUpd(ADMIN_ID, '999999'));
ok(adDayMax() == 999999.0, 'daily cap raised to 999,999');

echo "\n[P5] toggle cash-out OFF from the panel blocks conversion\n";
$before = adCashoutOn();
masterHandle(cbUpd(ADMIN_ID, 'adadm_con'));             // toggle
ok(adCashoutOn() === !$before, 'cash-out toggled');
if (!adCashoutOn()) {
    [$ok2, $msg2] = adRedeem($U, 100);
    ok(!$ok2, 'with cash-out off, redeem is refused: ' . $msg2);
    masterHandle(cbUpd(ADMIN_ID, 'adadm_con'));         // turn back on
} else { ok(true, '(cash-out was off; turned on)'); }

echo "\n[P6] reset restores the default price (50)\n";
masterHandle(cbUpd(ADMIN_ID, 'adadm_reset'));
ok(adRedeemRate() == 50.0, 'reset → back to default 50 (' . adRedeemRate() . ')');
ok(!isset(cfg()['airdrop']['redeem_rate']), 'override cleared from config');

echo "\n[P7] a non-admin cannot open or change it\n";
$N = 7002; mkUser($N);
$before = adRedeemRate();
masterHandle(cbUpd($N, 'adadm_home'));                  // should be denied
setState($N, 'ad_set', ['f' => 'rate']);               // even if somehow in the state
masterHandle(msgUpd($N, '1'));
ok(adRedeemRate() == $before, 'non-admin cannot change the price (still ' . adRedeemRate() . ')');

echo "\n[P8] mini-app sees the panel-set price\n";
masterHandle(cbUpd(ADMIN_ID, 'adadm_e_rate'));
masterHandle(msgUpd(ADMIN_ID, '25'));
$st = adState(ADMIN_ID);
ok(($st['redeem_rate'] ?? 0) == 25.0, 'airdrop_state reports the new rate 25 to the mini-app');
masterHandle(cbUpd(ADMIN_ID, 'adadm_reset'));
