<?php
require __DIR__ . '/../src/bootstrap.php';
$info = json_decode(file_get_contents($argv[1]), true);
Config::set('BOT_TOKEN', '42:TOKEN');
Config::set('API_ID', '12345');
Config::set('API_HASH', 'HASH');
Config::set('DB_PATH', sys_get_temp_dir() . '/mt_test_' . getmypid() . '.db');
DB::connect();
MTProto::$addr = [2 => ['127.0.0.1', $info['ports']['2']], 4 => ['127.0.0.1', $info['ports']['4']]];
MTProto::$rsa = ['n' => hex2bin($info['n']), 'e' => hex2bin($info['e']), 'fp' => $info['fp']];
$t = microtime(true);
$u = MTProto::resolve('tofan');
echo 'resolve ', round((microtime(true) - $t) * 1000), "ms ", json_encode($u), "\n";
$t = microtime(true);
$g = MTProto::gifts($u);
echo 'gifts ', round((microtime(true) - $t) * 1000), "ms ", json_encode($g, JSON_UNESCAPED_UNICODE), "\n";
var_dump(MTProto::resolve('nobody_here'));
$s = DB::kvGet('mtproto');
echo 'state dc=', $s['dc'], ' keys=', implode(',', array_keys($s['keys'])), ' bot4=', json_encode($s['keys'][4]['bot'] ?? null), "\n";
