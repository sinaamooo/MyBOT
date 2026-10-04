<?php
// Press every button reachable from /panel (admin) and /start (user); any PHP error fails.
set_error_handler(function ($no, $str, $file, $line) {
    if (!(error_reporting() & $no)) return false;
    if (str_contains($file, 'harness')) return false;
    $GLOBALS['ERRS'][] = basename($file) . ':' . $line . ' ' . $str;
    return false;
});
$GLOBALS['ERRS'] = [];

function cbsFrom($since) {
    $out = [];
    foreach (array_slice($GLOBALS['TG'], $since) as [$m, $d]) {
        $rm = $d['reply_markup'] ?? null;
        $rm = is_string($rm) ? json_decode($rm, true) : $rm;
        foreach ((array)($rm['inline_keyboard'] ?? []) as $row)
            foreach ((array)$row as $b) if (isset($b['callback_data'])) $out[] = (string)$b['callback_data'];
    }
    return $out;
}

function crawl($uid, $startText, $limit) {
    mkUser($uid, 500000);
    $since = count($GLOBALS['TG']);
    masterHandle(msgUpd($uid, $startText));
    $queue = cbsFrom($since); $seen = []; $n = 0;
    // skip buttons that wipe data or start long jobs
    $skip = '/(clr|reset|del|wipe|_x$|purge|import|clean|bcsend|broadcast|test)/i';
    while ($queue && $n < $limit) {
        $cb = array_shift($queue);
        if (isset($seen[$cb]) || preg_match($skip, $cb)) continue;
        $seen[$cb] = 1; $n++;
        $since = count($GLOBALS['TG']);
        try { masterHandle(cbUpd($uid, $cb)); }
        catch (Throwable $e) { $GLOBALS['ERRS'][] = 'cb ' . $cb . ': ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine(); }
        clearState($uid);
        foreach (cbsFrom($since) as $c) if (!isset($seen[$c])) $queue[] = $c;
    }
    return $n;
}

echo "\n[C1] Admin bot panel crawl\n";
$n = crawl(ADMIN_ID, '/panel', 600);
echo "  pressed $n admin buttons\n";
echo "\n[C2] User crawl\n";
$m = crawl(6001, '/start', 200);
echo "  pressed $m user buttons\n";
$errs = array_values(array_unique($GLOBALS['ERRS']));
foreach (array_slice($errs, 0, 25) as $e) echo "  ⚠️ $e\n";
ok(!$errs, 'no PHP errors/exceptions while pressing ' . ($n + $m) . ' buttons');
