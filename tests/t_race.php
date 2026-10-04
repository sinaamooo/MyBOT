<?php
// Anti-cheat under a real race: many processes fire the SAME coupon for the SAME
// user at the exact same instant. The per-user limit and the global cap must still
// hold — a user must never be able to redeem a code more times than allowed, no
// matter how the requests are timed. (No faking: these are separate OS processes
// hitting the same coupons.sqlite on disk through the real cpRedeem() path.)

$ref     = new ReflectionFunction('cpDb');
$BOTDIR  = dirname(dirname(dirname($ref->getFileName())));   // …/numbix
$WORKER  = __DIR__ . '/_race_worker.php';

function raceFire($botdir, $worker, $code, $uid, $n) {
    $at = microtime(true) + 0.40;                 // everyone starts together
    $procs = [];
    for ($i = 0; $i < $n; $i++) {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker) . ' '
             . escapeshellarg($botdir) . ' ' . escapeshellarg(DATA_DIR) . ' '
             . escapeshellarg($code) . ' ' . escapeshellarg((string)$uid) . ' '
             . escapeshellarg('ord_' . $uid . '_' . $i);
        $env = ['RACE_AT' => sprintf('%.6f', $at)] + $_ENV + getenv();
        $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (is_resource($p)) $procs[] = [$p, $pipes];
    }
    $success = 0;
    foreach ($procs as [$p, $pipes]) {
        @fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
        @fclose($pipes[1]); @fclose($pipes[2]);
        if (proc_close($p) === 0) $success++;
    }
    return $success;
}

echo "\n[R1] per-user limit holds under 10 concurrent fires (same user)\n";
cpDb() && cpDelete('RACEP');
cpUpsert('RACEP', ['kind' => 'percent', 'value' => 10, 'max_uses' => 100, 'used' => 0,
    'per_user_limit' => 1, 'min_total' => 0, 'max_discount' => 0, 'expires_at' => 0, 'on_flag' => 1,
    'created_at' => time(), 'owner' => 0]);
$win = raceFire($BOTDIR, $WORKER, 'RACEP', 9001, 10);
ok($win === 1, "exactly 1 of 10 concurrent redemptions won (got $win)");
ok((int)cpGet('RACEP')['used'] === 1, 'used counter = 1 (no double count)');

echo "\n[R2] global cap holds under a race (max_uses=3, 12 fires, per_user=0)\n";
cpDelete('RACEG');
cpUpsert('RACEG', ['kind' => 'percent', 'value' => 10, 'max_uses' => 3, 'used' => 0,
    'per_user_limit' => 0, 'min_total' => 0, 'max_discount' => 0, 'expires_at' => 0, 'on_flag' => 1,
    'created_at' => time(), 'owner' => 0]);
$win2 = raceFire($BOTDIR, $WORKER, 'RACEG', 9002, 12);
ok($win2 === 3, "exactly 3 of 12 concurrent redemptions won (got $win2)");
ok((int)cpGet('RACEG')['used'] === 3, 'used counter = 3 (never over the cap)');

echo "\n[R3] two users, per-user=1 each, 6 fires apiece → each gets exactly 1\n";
cpDelete('RACE2');
cpUpsert('RACE2', ['kind' => 'percent', 'value' => 10, 'max_uses' => 100, 'used' => 0,
    'per_user_limit' => 1, 'min_total' => 0, 'max_discount' => 0, 'expires_at' => 0, 'on_flag' => 1,
    'created_at' => time(), 'owner' => 0]);
$a = raceFire($BOTDIR, $WORKER, 'RACE2', 9101, 6);
$b = raceFire($BOTDIR, $WORKER, 'RACE2', 9102, 6);
ok($a === 1 && $b === 1, "each user won exactly once (A=$a, B=$b)");
ok((int)cpGet('RACE2')['used'] === 2, 'used counter = 2 across the two users');
