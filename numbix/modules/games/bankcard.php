<?php
defined('NB_ROOT') || exit;

if (!defined('BC_LIB')) define('BC_LIB', 1);


function bcFontDirs() {
    return [
        nbAsset('fonts'),
        DATA_DIR . '/fonts',
        '/usr/share/fonts/truetype/dejavu',
        '/usr/share/fonts/truetype/vazir',
        '/usr/share/fonts/TTF',
        '/usr/share/fonts',
    ];
}

function bcFontList($limit = 40) {
    static $memo = null;
    if ($memo !== null) return $memo;

    $out = []; $seen = [];
    foreach (bcFontDirs() as $d) {
        if (!is_dir($d)) continue;
        foreach ((array)glob($d . '/*.[tT][tT][fF]') as $p) {
            $bn = strtolower(basename($p));
            if (!is_readable($p) || isset($seen[$bn])) continue;
            $seen[$bn] = 1;
            $out[$p] = ['name' => basename($p), 'fa' => bcFontHasFa($p)];
            if (count($out) >= $limit) break 2;
        }
    }
    return $memo = $out;
}

function bcFontHasFa($file) {
    $cov = bcFontCoverage($file);
    if ($cov === null) return true;
    return bcHasGlyph($cov, 0x0627) && bcHasGlyph($cov, 0x06CC);
}

function bcFontPick($bold = false) {
    if (!function_exists('bkVal')) return '';
    $p = trim((string)bkVal($bold ? 'card_font_bold' : 'card_font', ''));
    return ($p !== '' && is_file($p) && is_readable($p)) ? $p : '';
}

function bcFontFromPrices($bold = false) {
    if (!function_exists('pxFont')) return '';
    $p = trim((string)pxFont($bold));
    if ($p === '' && $bold) $p = trim((string)pxFont(false));
    return ($p !== '' && is_file($p) && is_readable($p)) ? $p : '';
}

function bcFontFollowsPrices() {
    return bcFontPick(false) === '' && bcFontPick(true) === '' && bcFontFromPrices(true) !== '';
}

function bcFontBust() {
    bcFont(false, true);
    bcFont(true, true);
    if (defined('DATA_DIR')) @unlink(bcIdCachePath());
}

function bcFont($bold = false, $bust = false) {
    static $cache = [];
    $k = $bold ? 'b' : 'r';
    if ($bust) { unset($cache[$k]); return null; }
    if (isset($cache[$k])) return $cache[$k];

    $pick = bcFontPick($bold);
    if ($pick === '' && $bold) $pick = bcFontPick(false);
    if ($pick !== '') return $cache[$k] = $pick;

    $shared = bcFontFromPrices($bold);
    if ($shared !== '') return $cache[$k] = $shared;

    $names = $bold
        ? ['Vazirmatn-Bold.ttf', 'Vazir-Bold.ttf', 'IRANSansBold.ttf', 'DejaVuSans-Bold.ttf']
        : ['Vazirmatn-Regular.ttf', 'Vazir.ttf', 'IRANSans.ttf', 'DejaVuSans.ttf'];

    foreach (bcFontDirs() as $d) {
        foreach ($names as $n) {
            $p = $d . '/' . $n;
            if (is_file($p) && is_readable($p)) return $cache[$k] = $p;
        }
    }
    foreach ([nbAsset('fonts'), DATA_DIR . '/fonts'] as $d) {
        if (!is_dir($d)) continue;
        foreach ((array)glob($d . '/*.ttf') as $p) if (is_readable($p)) return $cache[$k] = $p;
    }
    return $cache[$k] = null;
}

function bcReady() {
    return function_exists('imagecreatetruecolor')
        && function_exists('imagettftext')
        && function_exists('pxBase')
        && bcFont() !== null;
}

function bcCp1252Rev() {
    static $m = null;
    if ($m !== null) return $m;
    $tbl = [0x80=>0x20AC, 0x82=>0x201A, 0x83=>0x0192, 0x84=>0x201E, 0x85=>0x2026,
            0x86=>0x2020, 0x87=>0x2021, 0x88=>0x02C6, 0x89=>0x2030, 0x8A=>0x0160,
            0x8B=>0x2039, 0x8C=>0x0152, 0x8E=>0x017D, 0x91=>0x2018, 0x92=>0x2019,
            0x93=>0x201C, 0x94=>0x201D, 0x95=>0x2022, 0x96=>0x2013, 0x97=>0x2014,
            0x98=>0x02DC, 0x99=>0x2122, 0x9A=>0x0161, 0x9B=>0x203A, 0x9C=>0x0153,
            0x9E=>0x017E, 0x9F=>0x0178];
    $m = [];
    foreach ($tbl as $byte => $cp) $m[$cp] = $byte;
    return $m;
}

function bcFixMojibake($s) {
    $s = (string)$s;
    if ($s === '' || !preg_match('//u', $s)) return $s;
    if (!preg_match('/[\x{00C0}-\x{00FF}]/u', $s)) return $s;

    $rev = bcCp1252Rev();
    $raw = '';
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $cp = bcCodepoint($ch);
        if ($cp <= 0xFF)             $raw .= chr($cp);
        elseif (isset($rev[$cp]))    $raw .= chr($rev[$cp]);
        else                         return $s;
    }
    if ($raw === '' || !preg_match('//u', $raw)) return $s;

    if (!preg_match('/\p{L}/u', $raw)) return $s;
    if (preg_match('/[\x{00C0}-\x{00FF}]/u', $raw)) return $s;
    return $raw;
}

function bcFontCoverage($file) {
    static $memo = [];
    if ($file === null || $file === '') return null;
    if (array_key_exists($file, $memo)) return $memo[$file];
    $memo[$file] = null;

    $fh = @fopen($file, 'rb');
    if (!$fh) return null;

    $u16 = function () use ($fh) { $b = fread($fh, 2); return $b === false || strlen($b) < 2 ? 0 : unpack('n', $b)[1]; };
    $u32 = function () use ($fh) { $b = fread($fh, 4); return $b === false || strlen($b) < 4 ? 0 : unpack('N', $b)[1]; };

    $u32();
    $numTables = $u16();
    fseek($fh, 6, SEEK_CUR);
    if ($numTables <= 0 || $numTables > 512) { fclose($fh); return null; }

    $cmapOff = 0;
    for ($i = 0; $i < $numTables; $i++) {
        $tag = fread($fh, 4);
        $u32();
        $off = $u32();
        $u32();
        if ($tag === 'cmap') { $cmapOff = $off; break; }
    }
    if (!$cmapOff) { fclose($fh); return null; }

    fseek($fh, $cmapOff);
    $u16();
    $nSub = $u16();
    $best = 0; $bestScore = -1;
    for ($i = 0; $i < $nSub; $i++) {
        $pid = $u16(); $eid = $u16(); $off = $u32();
        $score = ($pid === 3 && $eid === 10) ? 3 : (($pid === 3 && $eid === 1) ? 2 : ($pid === 0 ? 1 : 0));
        if ($score > $bestScore) { $bestScore = $score; $best = $cmapOff + $off; }
    }
    if (!$best) { fclose($fh); return null; }

    fseek($fh, $best);
    $fmt = $u16();
    $ranges = [];

    if ($fmt === 4) {
        $u16(); $u16();
        $segX2 = $u16();
        $seg   = intdiv($segX2, 2);
        if ($seg <= 0 || $seg > 20000) { fclose($fh); return null; }
        fseek($fh, 6, SEEK_CUR);
        $end = []; for ($i = 0; $i < $seg; $i++) $end[] = $u16();
        $u16();
        $start = []; for ($i = 0; $i < $seg; $i++) $start[] = $u16();
        for ($i = 0; $i < $seg; $i++) {
            if ($start[$i] > $end[$i] || $start[$i] === 0xFFFF) continue;
            $ranges[] = [$start[$i], min($end[$i], 0xFFFE)];
        }
    } elseif ($fmt === 12) {
        $u16(); $u32(); $u32();
        $n = $u32();
        if ($n <= 0 || $n > 200000) { fclose($fh); return null; }
        for ($i = 0; $i < $n; $i++) {
            $a = $u32(); $b = $u32(); $u32();
            if ($a <= $b) $ranges[] = [$a, $b];
        }
    } else {
        fclose($fh); return null;
    }

    fclose($fh);
    if (!$ranges) return null;
    usort($ranges, fn($x, $y) => $x[0] <=> $y[0]);
    return $memo[$file] = $ranges;
}

function bcHasGlyph($ranges, $cp) {
    if ($ranges === null) return true;
    $lo = 0; $hi = count($ranges) - 1;
    while ($lo <= $hi) {
        $mid = ($lo + $hi) >> 1;
        if ($cp < $ranges[$mid][0])      $hi = $mid - 1;
        elseif ($cp > $ranges[$mid][1])  $lo = $mid + 1;
        else                             return true;
    }
    return false;
}

function bcCleanName($name, $fallback = 'کاربر', $max = 22) {
    $s = bcFixMojibake((string)$name);

    if (!preg_match('//u', $s)) {
        $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if ($s === false) $s = '';
    }

    $s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{FE0E}\x{FE0F}\x{00AD}]/u', '', $s);
    $s = preg_replace('/[\x{0000}-\x{001F}\x{007F}]/u', ' ', $s);

    $s = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2190}-\x{2BFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}\x{1F1E6}-\x{1F1FF}\x{E000}-\x{F8FF}]/u', '', $s);

    $cov = bcFontCoverage(bcFont(true) ?: bcFont());
    if ($cov !== null && $s !== '') {
        $keep = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $cp = bcCodepoint($ch);
            if ($cp === 0 || bcHasGlyph($cov, $cp)) $keep .= $ch;
        }
        $s = $keep;
    }

    $s = trim(preg_replace('/\s+/u', ' ', $s));
    if ($s === '' || !preg_match('/[\p{L}\p{N}]/u', $s)) return $fallback;
    if (mb_strlen($s, 'UTF-8') > $max) $s = mb_substr($s, 0, $max, 'UTF-8') . '…';
    return $s;
}

function bcCodepoint($ch) {
    $b = unpack('N', mb_convert_encoding($ch, 'UCS-4BE', 'UTF-8') ?: "\0\0\0\0");
    return $b ? (int)$b[1] : 0;
}

function bcAvatar($uid) {
    $dir = DATA_DIR . '/avatars';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $path = $dir . '/' . (int)$uid . '.jpg';

    if (is_file($path) && (time() - (int)@filemtime($path)) < 86400)
        return filesize($path) > 0 ? $path : null;

    $r = tg(BOT_TOKEN, 'getUserProfilePhotos', ['user_id' => (int)$uid, 'limit' => 1], 10);
    $sizes = $r['result']['photos'][0] ?? null;
    if (!$sizes) { @touch($path); return null; }

    $pick = null;
    foreach ($sizes as $sz) {
        if ((int)($sz['file_size'] ?? 0) > 400000) continue;
        if (!$pick || (int)$sz['width'] > (int)$pick['width']) $pick = $sz;
    }
    if (!$pick) $pick = $sizes[0];

    $f = tg(BOT_TOKEN, 'getFile', ['file_id' => $pick['file_id']], 10);
    $fp = $f['result']['file_path'] ?? '';
    if ($fp === '') { @touch($path); return null; }

    $url = rtrim(TG_API_BASE, '/') . '/file/bot' . BOT_TOKEN . '/' . $fp;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8]);
    $bin = monCurl($ch, 'telegram');
    curl_close($ch);
    if (!$bin) { @touch($path); return null; }

    @file_put_contents($path, $bin);
    return filesize($path) > 0 ? $path : null;
}

function bcCardNo($uid, $stored = '') {
    $s = preg_replace('/\D/', '', (string)$stored);
    if (strlen($s) !== 16) {
        $h = preg_replace('/\D/', '', md5('numbixcard' . (int)$uid) . md5('numbix' . (int)$uid));
        $s = '5041' . substr($h . '00000000000', 0, 11);
        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $d = (int)$s[14 - $i];
            if ($i % 2 === 0) { $d *= 2; if ($d > 9) $d -= 9; }
            $sum += $d;
        }
        $s .= (string)((10 - $sum % 10) % 10);
    }
    return implode(' ', str_split($s, 4));
}

const BC_VER = 5;

function bcRender(array $info) {
    if (!bcReady()) return null;
    $fa    = (string)(bcFont(true) ?: bcFont());
    $im    = pxBase('2F72DF', '12C488');
    $S     = PX_SS;
    $white = pxCol($im, 'F5F5F7');
    $gray  = pxCol($im, '9A9AA0');
    $lat   = pxLat(600);

    $uid  = (int)($info['uid'] ?? 0);
    $un   = trim((string)($info['username'] ?? ''));
    $name = bcCleanName((string)($info['name'] ?? ''), $un !== '' ? $un : 'کاربر');

    pxAvatar($im, $info['avatar'] ?? null, 938, 172, 58, 'FFFFFF', 2, $name, '2F72DF', $fa);
    [, $tr] = pxTag($im, 232, 172, 'DIAMOND BANK', 'blue', 'l');
    pxSay($im, pxSayFit(28, $name, 896 - $tr - 30, 16, $fa), 896, 168, $white, $name, 'r', $fa);
    pxPut($im, $lat, 14.5, 896, 197, $gray, $un !== '' ? '@' . $un : 'ID ' . $uid, 'r');
    $foot = trim((string)($info['footer'] ?? ''));
    if ($foot !== '') pxSay($im, pxSayFit(14.5, $foot, 330, 11, $fa), 232, 214, $gray, $foot, 'l', $fa);

    pxSay($im, 18, 600, 256, $gray, 'موجودی بانک', 'c', $fa);
    $amt  = bcFmt($info['locked'] ?? 0);
    $size = pxFit($lat, 58, $amt, 480, 30);
    $base = 350 - (58 - $size) * 0.3;
    $w    = pxInkW($lat, $size, $amt);
    $gw   = $size * 0.62;
    $x0   = 600 - ($w + $gw + 22) / 2;
    pxGem($im, $x0 + $gw / 2, $base - pxCapH($lat, $size) / 2 + 2, $gw, '3D7BF0');
    pxPut($im, $lat, $size, $x0 + $gw + 22, $base, $white, $amt);

    imagefilledrectangle($im, 232 * $S, 412 * $S, 968 * $S, 412 * $S + 1, pxCol($im, 'FFFFFF', 0.08));
    $cols = [
        [760, 'شماره کارت', bcCardNo($uid, $info['card_no'] ?? ''), 'E4E6EB', 340],
        [468, 'سطح', (string)(int)($info['level'] ?? 1), '86EBC9', 150],
        [318, 'رتبه', !empty($info['rank']) ? '#' . (int)$info['rank'] : '—', '9CC3FF', 150],
    ];
    foreach ($cols as [$cx, $lab, $val, $hex, $room]) {
        pxSay($im, 15.5, $cx, 462, $gray, $lab, 'c', $fa);
        pxPut($im, $lat, pxFit($lat, 22, $val, $room, 13), $cx, 510, pxCol($im, $hex), $val, 'c');
    }

    pxFoot($im, 'DIAMOND BANK');
    $bytes = pxJpg($im);

    $dir = DATA_DIR . '/cards';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $out = $dir . '/card_' . $uid . '.jpg';
    $tmp = $out . '.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $bytes) === false) return null;
    @rename($tmp, $out);
    return is_file($out) ? $out : null;
}

function bcFmt($n) { return number_format((float)$n, 0, '.', ','); }

function bcIdCachePath() { return DATA_DIR . '/cards/fileids.json'; }

function bcCardKey(array $info) {
    $av = (string)($info['avatar'] ?? '');
    return md5(implode('|', [
        BC_VER,
        (int)($info['uid'] ?? 0),
        (string)($info['name'] ?? ''),
        (string)($info['username'] ?? ''),
        (string)($info['card_no'] ?? ''),
        bcFmt($info['locked'] ?? 0),
        (int)($info['level'] ?? 1),
        (int)($info['rank'] ?? 0),
        (string)($info['footer'] ?? ''),
        $av !== '' && is_file($av) ? (string)@filemtime($av) : '-',
    ]));
}

function bcIdGet($key) {
    $p = bcIdCachePath();
    if (!is_file($p)) return '';
    $j = json_decode((string)@file_get_contents($p), true);
    if (!is_array($j)) return '';
    $row = $j[$key] ?? null;
    if (!is_array($row)) return '';
    if (time() - (int)($row['t'] ?? 0) > 604800) return '';
    return (string)($row['id'] ?? '');
}

function bcIdPut($key, $fileId) {
    $p   = bcIdCachePath();
    $dir = dirname($p);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $fh = @fopen($p, 'c+');
    if (!$fh) return;
    if (!flock($fh, LOCK_EX)) { fclose($fh); return; }
    $j = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($j)) $j = [];
    $j[$key] = ['id' => (string)$fileId, 't' => time()];
    if (count($j) > 4000) {
        uasort($j, fn($a, $b) => ($b['t'] ?? 0) <=> ($a['t'] ?? 0));
        $j = array_slice($j, 0, 2000, true);
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($j, JSON_UNESCAPED_UNICODE));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

function bcSend($chatId, $caption, array $info, $kb = null, $extra = []) {
    if (!bcReady()) return false;
    $info['avatar'] = bcAvatar((int)($info['uid'] ?? 0));

    $key    = bcCardKey($info);
    $cached = bcIdGet($key);

    $data = array_merge([
        'chat_id'    => $chatId,
        'caption'    => mb_substr((string)$caption, 0, 1024),
        'parse_mode' => 'HTML',
    ], $extra);
    if ($kb) $data['reply_markup'] = is_string($kb) ? $kb : kbJson($kb);

    if ($cached !== '') {
        $data['photo'] = $cached;
        $r = tg(BOT_TOKEN, 'sendPhoto', $data, 15);
        if (!empty($r['ok'])) return true;
        if ($kb && !is_string($kb) && function_exists('isStyleError') && isStyleError($r)) {
            $data['reply_markup'] = json_encode(stripStyles($kb));
            $r = tg(BOT_TOKEN, 'sendPhoto', $data, 15);
            if (!empty($r['ok'])) return true;
        }
    }

    $file = bcRender($info);
    if (!$file || !is_file($file)) return false;

    $data['photo'] = new CURLFile($file, 'image/jpeg', 'card.jpg');
    if ($kb) $data['reply_markup'] = is_string($kb) ? $kb : kbJson($kb);

    $r = tg(BOT_TOKEN, 'sendPhoto', $data, 30);
    if (empty($r['ok']) && $kb && !is_string($kb) && function_exists('isStyleError') && isStyleError($r)) {
        $data['reply_markup'] = json_encode(stripStyles($kb));
        $r = tg(BOT_TOKEN, 'sendPhoto', $data, 30);
    }
    if (empty($r['ok'])) {
        error_log('[bankcard] sendPhoto نشد: ' . (string)($r['description'] ?? ''));
        return false;
    }

    $ph = $r['result']['photo'] ?? [];
    if (is_array($ph) && $ph) {
        $best = end($ph);
        if (!empty($best['file_id'])) bcIdPut($key, (string)$best['file_id']);
    }
    return true;
}
