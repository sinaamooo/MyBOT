<?php
echo "\n[S] Card render speed\n";
pxSet(function (&$c) { $c['card']['on'] = true; });
echo "  ready: " . (pxCardReady() ? 'yes' : 'no: ' . pxCardWhy()) . "\n";
$t = microtime(true); $b = refCardBytes('https://t.me/test_bot?start=ref123', 123); $a = microtime(true) - $t;
$t = microtime(true); $b2 = refCardBytes('https://t.me/test_bot?start=ref124', 124); $c = microtime(true) - $t;
echo sprintf("  invite card: first %.0f ms (draws + caches background), next %.0f ms, %d KB\n", $a * 1000, $c * 1000, strlen($b2) / 1024);
if (function_exists('bcRender')) {
    $t = microtime(true); $img = bcRender(['uid' => 77, 'name' => 'Sara', 'username' => 'sara', 'card_no' => '', 'locked' => 12345, 'level' => 3, 'rank' => 0, 'footer' => 'x']); $d = microtime(true) - $t;
    echo sprintf("  bank card: %.0f ms, %d KB\n", $d * 1000, strlen((string)$img) / 1024);
}
ok(strlen((string)$b2) > 1000 && $c < 1.5, 'invite card renders in under 1.5 s after warm-up');
