<?php

final class Hls
{
    const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    const MAX = 48 * 1048576;

    private static function get(string $url, int $timeout = 10): ?string
    {
        $r = Http::request(['url' => $url, 'timeout' => $timeout, 'headers' => ['User-Agent' => self::UA, 'Referer' => 'https://www.pinterest.com/']]);
        return $r['status'] >= 200 && $r['status'] < 300 && $r['body'] !== '' ? $r['body'] : null;
    }

    private static function abs(string $base, string $uri): string
    {
        if (preg_match('~^https?://~i', $uri)) {
            return $uri;
        }
        $p = parse_url($base);
        $root = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($uri, '/')) {
            return $root . $uri;
        }
        $dir = preg_replace('~/[^/]*$~', '/', $p['path'] ?? '/');
        return $root . $dir . $uri;
    }

    private static function attrs(string $line): array
    {
        preg_match_all('/([A-Z0-9-]+)=("[^"]*"|[^,]*)/', $line, $mm, PREG_SET_ORDER);
        $out = [];
        foreach ($mm as $m) {
            $out[$m[1]] = trim($m[2], '"');
        }
        return $out;
    }

    private static function variants(string $text, string $base): array
    {
        $lines = preg_split('/\r?\n/', $text);
        $out = [];
        $audio = [];
        foreach ($lines as $i => $l) {
            if (str_starts_with($l, '#EXT-X-MEDIA:')) {
                $a = self::attrs(substr($l, 13));
                if (($a['TYPE'] ?? '') === 'AUDIO' && !empty($a['URI'])) {
                    $audio[$a['GROUP-ID'] ?? ''][] = ['uri' => self::abs($base, $a['URI']), 'default' => ($a['DEFAULT'] ?? '') === 'YES'];
                }
            } elseif (str_starts_with($l, '#EXT-X-STREAM-INF:')) {
                $a = self::attrs(substr($l, 18));
                for ($j = $i + 1; $j < count($lines) && (trim($lines[$j]) === '' || $lines[$j][0] === '#'); $j++) {
                }
                if (!isset($lines[$j])) {
                    continue;
                }
                [$w, $h] = array_map('intval', explode('x', ($a['RESOLUTION'] ?? '0x0') . 'x0'));
                $out[] = ['uri' => self::abs($base, trim($lines[$j])), 'bw' => (int)($a['BANDWIDTH'] ?? 0), 'w' => $w, 'h' => $h, 'audio' => $a['AUDIO'] ?? null];
            }
        }
        foreach ($out as &$v) {
            $g = $audio[$v['audio'] ?? ''] ?? [];
            usort($g, fn($x, $y) => $y['default'] <=> $x['default']);
            $v['audio'] = $g[0]['uri'] ?? null;
        }
        unset($v);
        usort($out, fn($x, $y) => [$y['h'] <= 1080, $y['bw']] <=> [$x['h'] <= 1080, $x['bw']]);
        return $out;
    }

    private static function segments(string $text, string $base): array
    {
        $segs = [];
        $key = null;
        $map = null;
        $seq = 0;
        $dur = 0.0;
        $len = 0.0;
        $range = null;
        $prevEnd = 0;
        foreach (preg_split('/\r?\n/', $text) as $l) {
            $l = trim($l);
            if ($l === '') {
                continue;
            }
            if (str_starts_with($l, '#EXT-X-MEDIA-SEQUENCE:')) {
                $seq = (int)substr($l, 22);
            } elseif (str_starts_with($l, '#EXT-X-KEY:')) {
                $a = self::attrs(substr($l, 11));
                $key = ($a['METHOD'] ?? 'NONE') === 'NONE' ? null : ['method' => $a['METHOD'], 'uri' => isset($a['URI']) ? self::abs($base, $a['URI']) : null,
                    'iv' => isset($a['IV']) ? hex2bin(str_pad(preg_replace('/^0x/i', '', $a['IV']), 32, '0', STR_PAD_LEFT)) : null];
            } elseif (str_starts_with($l, '#EXT-X-MAP:')) {
                $a = self::attrs(substr($l, 11));
                $map = isset($a['URI']) ? self::abs($base, $a['URI']) : null;
            } elseif (str_starts_with($l, '#EXTINF:')) {
                $len = (float)substr($l, 8);
            } elseif (str_starts_with($l, '#EXT-X-BYTERANGE:')) {
                [$n, $o] = explode('@', substr($l, 17)) + [1 => null];
                $range = [$o !== null ? (int)$o : $prevEnd, (int)$n];
                $prevEnd = $range[0] + $range[1];
            } elseif ($l[0] !== '#') {
                $segs[] = ['uri' => self::abs($base, $l), 'key' => $key, 'seq' => $seq + count($segs), 'range' => $range];
                $dur += $len;
                $range = null;
            }
        }
        return ['segs' => $segs, 'map' => $map, 'duration' => $dur];
    }

    private static function download(array $list): ?string
    {
        $keys = [];
        $out = [];
        foreach (array_chunk($list, 8, true) as $batch) {
            $reqs = [];
            foreach ($batch as $i => $s) {
                $h = ['User-Agent' => self::UA, 'Referer' => 'https://www.pinterest.com/'];
                if ($s['range']) {
                    $h['Range'] = 'bytes=' . $s['range'][0] . '-' . ($s['range'][0] + $s['range'][1] - 1);
                }
                $reqs[$i] = ['url' => $s['uri'], 'timeout' => 30, 'headers' => $h];
            }
            foreach (Http::multi($reqs, 40) as $i => $r) {
                if ($r['status'] === 0 || $r['status'] >= 300 || $r['body'] === '') {
                    $r = Http::request($reqs[$i]);
                    if ($r['status'] === 0 || $r['status'] >= 300 || $r['body'] === '') {
                        return null;
                    }
                }
                $body = $r['body'];
                $s = $list[$i];
                if ($s['range'] && $r['status'] === 200 && strlen($body) > $s['range'][1]) {
                    $body = substr($body, $s['range'][0], $s['range'][1]);
                }
                if ($s['key']) {
                    if ($s['key']['method'] !== 'AES-128' || !$s['key']['uri']) {
                        return null;
                    }
                    $k = $keys[$s['key']['uri']] ??= self::get($s['key']['uri']);
                    if (!$k || strlen($k) !== 16) {
                        return null;
                    }
                    $body = openssl_decrypt($body, 'aes-128-cbc', $k, OPENSSL_RAW_DATA, $s['key']['iv'] ?? str_pad(pack('J', $s['seq']), 16, "\0", STR_PAD_LEFT));
                    if ($body === false) {
                        return null;
                    }
                }
                $out[$i] = $body;
                if (array_sum(array_map('strlen', $out)) > self::MAX * 2) {
                    return null;
                }
            }
        }
        ksort($out);
        return implode('', $out);
    }

    private static function media(string $url, ?string $text = null): ?array
    {
        $text ??= self::get($url);
        if (!$text || !str_starts_with(ltrim($text), '#EXTM3U')) {
            return null;
        }
        $p = self::segments($text, $url);
        if (!$p['segs']) {
            return null;
        }
        $data = self::download($p['segs']);
        if ($data === null) {
            return null;
        }
        if ($p['map']) {
            $init = self::get($p['map'], 20);
            return $init === null ? null : ['fmp4' => $init . $data, 'duration' => $p['duration']];
        }
        return ['ts' => $data, 'duration' => $p['duration']];
    }

    public static function fetch(string $url): ?array
    {
        $text = self::get($url);
        if (!$text || !str_starts_with(ltrim($text), '#EXTM3U')) {
            return null;
        }
        $vars = str_contains($text, '#EXT-X-STREAM-INF') ? self::variants($text, $url) : [['uri' => $url, 'bw' => 0, 'w' => 0, 'h' => 0, 'audio' => null]];
        foreach ($vars as $v) {
            $pl = $v['uri'] === $url ? $text : self::get($v['uri']);
            if (!$pl) {
                continue;
            }
            $dur = self::segments($pl, $v['uri'])['duration'];
            if ($v['bw'] && $dur && $v['bw'] * $dur / 8 > self::MAX && $v !== end($vars)) {
                continue;
            }
            $video = self::media($v['uri'], $pl);
            if (!$video) {
                continue;
            }
            if (isset($video['fmp4'])) {
                return strlen($video['fmp4']) <= self::MAX ? ['bytes' => $video['fmp4'], 'width' => $v['w'], 'height' => $v['h'], 'duration' => (int)round($video['duration'])] : null;
            }
            $audio = $v['audio'] ? self::media($v['audio']) : null;
            $mp4 = Mp4::fromTs($video['ts'], $audio['ts'] ?? null) ?? Mp4::ffmpeg($video['ts'], $audio['ts'] ?? null);
            if ($mp4 && strlen($mp4['bytes']) <= self::MAX) {
                return $mp4;
            }
        }
        return null;
    }
}

final class Mp4
{
    private static function box(string $type, string ...$parts): string
    {
        $body = implode('', $parts);
        return pack('N', 8 + strlen($body)) . $type . $body;
    }

    private static function full(string $type, int $version, int $flags, string ...$parts): string
    {
        return self::box($type, chr($version) . substr(pack('N', $flags), 1), ...$parts);
    }

    private static function pts(string $d, int $o): int
    {
        return ((ord($d[$o]) >> 1) & 7) << 30 | ord($d[$o + 1]) << 22 | (ord($d[$o + 2]) >> 1) << 15 | ord($d[$o + 3]) << 7 | ord($d[$o + 4]) >> 1;
    }

    private static function demux(string $ts): array
    {
        $len = strlen($ts);
        $i = 0;
        while ($i + 376 < $len && !($ts[$i] === "\x47" && $ts[$i + 188] === "\x47")) {
            $i++;
        }
        $pmt = -1;
        $types = [];
        $buf = [];
        $pes = [];
        $flush = function (int $pid) use (&$buf, &$pes) {
            if (($buf[$pid] ?? '') !== '') {
                $pes[$pid][] = $buf[$pid];
            }
            $buf[$pid] = '';
        };
        for (; $i + 188 <= $len; $i += 188) {
            if ($ts[$i] !== "\x47") {
                $n = strpos($ts, "\x47", $i + 1);
                if ($n === false) {
                    break;
                }
                $i = $n - 188;
                continue;
            }
            $b1 = ord($ts[$i + 1]);
            $pid = (($b1 & 0x1F) << 8) | ord($ts[$i + 2]);
            $afc = (ord($ts[$i + 3]) >> 4) & 3;
            $p = $i + 4;
            if ($afc & 2) {
                $p += 1 + ord($ts[$p]);
            }
            if (!($afc & 1) || $p >= $i + 188) {
                continue;
            }
            $start = ($b1 & 0x40) !== 0;
            if ($pid === 0 || $pid === $pmt) {
                if (!$start) {
                    continue;
                }
                $sec = substr($ts, $p + 1 + ord($ts[$p]), $i + 188 - $p);
                $sl = ((ord($sec[1] ?? "\0") & 0x0F) << 8) | ord($sec[2] ?? "\0");
                $end = min(strlen($sec), 3 + $sl - 4);
                if ($pid === 0) {
                    for ($o = 8; $o + 4 <= $end; $o += 4) {
                        if ((ord($sec[$o]) << 8 | ord($sec[$o + 1])) !== 0) {
                            $pmt = ((ord($sec[$o + 2]) & 0x1F) << 8) | ord($sec[$o + 3]);
                            break;
                        }
                    }
                } else {
                    $o = 12 + (((ord($sec[10]) & 0x0F) << 8) | ord($sec[11]));
                    while ($o + 5 <= $end) {
                        $types[((ord($sec[$o + 1]) & 0x1F) << 8) | ord($sec[$o + 2])] = ord($sec[$o]);
                        $o += 5 + (((ord($sec[$o + 3]) & 0x0F) << 8) | ord($sec[$o + 4]));
                    }
                }
                continue;
            }
            if (!isset($types[$pid])) {
                continue;
            }
            if ($start) {
                $flush($pid);
            }
            if ($start || ($buf[$pid] ?? '') !== '') {
                $buf[$pid] = ($buf[$pid] ?? '') . substr($ts, $p, $i + 188 - $p);
            }
        }
        foreach (array_keys($buf) as $pid) {
            $flush($pid);
        }
        $out = ['video' => [], 'audio' => ''];
        $apts = null;
        foreach ($types as $pid => $type) {
            foreach ($pes[$pid] ?? [] as $d) {
                if (strlen($d) < 9 || substr($d, 0, 3) !== "\0\0\1") {
                    continue;
                }
                $fl = ord($d[7]);
                $pts = $fl & 0x80 ? self::pts($d, 9) : null;
                $dts = $fl & 0x40 ? self::pts($d, 14) : $pts;
                $data = substr($d, 9 + ord($d[8]));
                if ($type === 0x1B) {
                    $out['video'][] = [$pts, $dts, $data];
                } elseif ($type === 0x0F) {
                    $apts ??= $pts;
                    $out['audio'] .= $data;
                }
            }
        }
        $out['apts'] = $apts;
        return $out;
    }

    private static function nals(string $d): array
    {
        $out = [];
        $pos = [];
        $o = 0;
        while (($o = strpos($d, "\0\0\1", $o)) !== false) {
            $pos[] = $o + 3;
            $o += 3;
        }
        foreach ($pos as $k => $s) {
            $e = isset($pos[$k + 1]) ? $pos[$k + 1] - 3 : strlen($d);
            $n = rtrim(substr($d, $s, $e - $s), "\0");
            if ($n !== '') {
                $out[] = $n;
            }
        }
        return $out;
    }

    private static function sps(string $sps): array
    {
        $b = str_replace("\0\0\3", "\0\0", substr($sps, 1));
        $bits = '';
        foreach (str_split($b) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $p = 0;
        $u = function (int $n) use (&$p, $bits) {
            $v = bindec(substr($bits, $p, $n) ?: '0');
            $p += $n;
            return (int)$v;
        };
        $ue = function () use (&$p, $bits, $u) {
            $z = 0;
            while ($p < strlen($bits) && $bits[$p] === '0') {
                $z++;
                $p++;
            }
            $p++;
            return (1 << $z) - 1 + ($z ? $u($z) : 0);
        };
        $se = function () use ($ue) {
            $k = $ue();
            return $k & 1 ? ($k + 1) >> 1 : -($k >> 1);
        };
        $profile = $u(8);
        $u(16);
        $ue();
        $chroma = 1;
        $bl = 0;
        $bc = 0;
        if (in_array($profile, [100, 110, 122, 244, 44, 83, 86, 118, 128, 138, 139, 134, 135], true)) {
            $chroma = $ue();
            if ($chroma === 3) {
                $u(1);
            }
            $bl = $ue();
            $bc = $ue();
            $u(1);
            if ($u(1)) {
                for ($i = 0; $i < ($chroma !== 3 ? 8 : 12); $i++) {
                    if ($u(1)) {
                        $last = 8;
                        $next = 8;
                        for ($j = 0; $j < ($i < 6 ? 16 : 64); $j++) {
                            if ($next !== 0) {
                                $next = ($last + $se() + 256) % 256;
                            }
                            $last = $next === 0 ? $last : $next;
                        }
                    }
                }
            }
        }
        $ue();
        $poc = $ue();
        if ($poc === 0) {
            $ue();
        } elseif ($poc === 1) {
            $u(1);
            $se();
            $se();
            $n = $ue();
            for ($i = 0; $i < $n; $i++) {
                $se();
            }
        }
        $ue();
        $u(1);
        $w = $ue() + 1;
        $h = $ue() + 1;
        $frame = $u(1);
        if (!$frame) {
            $u(1);
        }
        $u(1);
        $cl = $cr = $ct = $cb = 0;
        if ($u(1)) {
            $cl = $ue();
            $cr = $ue();
            $ct = $ue();
            $cb = $ue();
        }
        $cx = $chroma === 0 || $chroma === 3 ? 1 : 2;
        $cy = ($chroma === 1 ? 2 : 1) * (2 - $frame);
        return ['w' => $w * 16 - ($cl + $cr) * $cx, 'h' => (2 - $frame) * $h * 16 - ($ct + $cb) * $cy, 'chroma' => $chroma, 'bl' => $bl, 'bc' => $bc, 'profile' => $profile];
    }

    private static function runs(array $vals): array
    {
        $out = [];
        foreach ($vals as $v) {
            if ($out && end($out)[1] === $v) {
                $out[array_key_last($out)][0]++;
            } else {
                $out[] = [1, $v];
            }
        }
        return $out;
    }

    private static function table(string $type, array $runs): string
    {
        $s = pack('N', count($runs));
        foreach ($runs as [$n, $v]) {
            $s .= pack('NN', $n, $v & 0xFFFFFFFF);
        }
        return self::full($type, 0, 0, $s);
    }

    private static function desc(int $tag, string $body): string
    {
        return chr($tag) . chr(strlen($body)) . $body;
    }

    private static function stbl(string $stsd, array $t, array $offsets): string
    {
        $parts = [$stsd, self::table('stts', self::runs($t['dur']))];
        if (!empty($t['cto']) && max($t['cto']) > 0) {
            $parts[] = self::table('ctts', self::runs($t['cto']));
        }
        if (isset($t['key']) && count($t['key']) < count($t['size'])) {
            $parts[] = self::full('stss', 0, 0, pack('N', count($t['key'])) . pack('N*', ...$t['key']));
        }
        $stsc = [];
        foreach ($t['chunks'] as $c => $n) {
            if (!$stsc || end($stsc)[1] !== $n) {
                $stsc[] = [$c + 1, $n];
            }
        }
        $sc = pack('N', count($stsc));
        foreach ($stsc as [$f, $n]) {
            $sc .= pack('NNN', $f, $n, 1);
        }
        $parts[] = self::full('stsc', 0, 0, $sc);
        $parts[] = self::full('stsz', 0, 0, pack('NN', 0, count($t['size'])) . pack('N*', ...$t['size']));
        $parts[] = self::full('stco', 0, 0, pack('N', count($offsets)) . pack('N*', ...$offsets));
        return self::box('stbl', ...$parts);
    }

    private static function trak(int $id, array $t, array $offsets, int $movieDur): string
    {
        $video = $t['kind'] === 'video';
        $matrix = pack('N9', 0x10000, 0, 0, 0, 0x10000, 0, 0, 0, 0x40000000);
        $tkhd = self::full('tkhd', 0, 3, pack('NNNNN', 0, 0, $id, 0, $movieDur) . str_repeat("\0", 8) . pack('nnnn', 0, 0, $video ? 0 : 0x0100, 0) . $matrix
            . pack('NN', ($video ? $t['w'] : 0) << 16, ($video ? $t['h'] : 0) << 16));
        $el = [];
        if ($t['delay'] > 0) {
            $el[] = pack('NNN', (int)round($t['delay'] * 1000 / 90000), 0xFFFFFFFF, 0x10000);
        }
        $el[] = pack('NNN', (int)round(array_sum($t['dur']) * 1000 / $t['scale']), $t['start'], 0x10000);
        $edts = self::box('edts', self::full('elst', 0, 0, pack('N', count($el)) . implode('', $el)));
        $mdhd = self::full('mdhd', 0, 0, pack('NNNN', 0, 0, $t['scale'], array_sum($t['dur'])) . pack('nn', 0x55C4, 0));
        $hdlr = self::full('hdlr', 0, 0, pack('N', 0) . ($video ? 'vide' : 'soun') . str_repeat("\0", 12) . ($video ? 'VideoHandler' : 'SoundHandler') . "\0");
        $head = $video ? self::full('vmhd', 0, 1, str_repeat("\0", 8)) : self::full('smhd', 0, 0, str_repeat("\0", 4));
        $dinf = self::box('dinf', self::full('dref', 0, 0, pack('N', 1), self::full('url ', 0, 1)));
        if ($video) {
            $s = $t['sps'];
            $avcc = "\x01" . $s[1] . $s[2] . $s[3] . "\xFF\xE1" . pack('n', strlen($s)) . $s . "\x01" . pack('n', strlen($t['pps'])) . $t['pps'];
            if (!in_array($t['profile'], [66, 77, 88], true)) {
                $avcc .= chr(0xFC | $t['chroma']) . chr(0xF8 | $t['bl']) . chr(0xF8 | $t['bc']) . "\0";
            }
            $entry = self::box('avc1', str_repeat("\0", 6) . pack('n', 1) . str_repeat("\0", 16) . pack('nnNNNn', $t['w'], $t['h'], 0x480000, 0x480000, 0, 1)
                . str_repeat("\0", 32) . pack('nn', 0x18, 0xFFFF), self::box('avcC', $avcc));
        } else {
            $asc = pack('n', ($t['obj'] << 11) | ($t['sfi'] << 7) | ($t['ch'] << 3));
            $dec = self::desc(0x04, "\x40\x15\0\0\0" . pack('NN', 0, 0) . self::desc(0x05, $asc));
            $esds = self::full('esds', 0, 0, self::desc(0x03, pack('n', 0) . "\0" . $dec . self::desc(0x06, "\x02")));
            $entry = self::box('mp4a', str_repeat("\0", 6) . pack('n', 1) . str_repeat("\0", 8) . pack('nnnn', $t['ch'], 16, 0, 0) . pack('N', ($t['scale'] & 0xFFFF) << 16), $esds);
        }
        $stsd = self::full('stsd', 0, 0, pack('N', 1), $entry);
        return self::box('trak', $tkhd, $edts, self::box('mdia', $mdhd, $hdlr, self::box('minf', $head, $dinf, self::stbl($stsd, $t, $offsets))));
    }

    public static function fromTs(string $ts, ?string $audioTs = null): ?array
    {
        $d = self::demux($ts);
        $a = $audioTs !== null ? self::demux($audioTs) : null;
        if ($a && $a['audio'] !== '') {
            $d['audio'] = $a['audio'];
            $d['apts'] = $a['apts'];
        }
        $sps = $pps = null;
        $v = ['kind' => 'video', 'scale' => 90000, 'samples' => [], 'size' => [], 'key' => [], 'dts' => [], 'pts' => []];
        foreach ($d['video'] as [$pts, $dts, $data]) {
            $sample = '';
            $key = false;
            $slice = false;
            foreach (self::nals($data) as $n) {
                $t = ord($n[0]) & 0x1F;
                if ($t === 7) {
                    $sps ??= $n;
                } elseif ($t === 8) {
                    $pps ??= $n;
                } elseif ($t !== 9) {
                    $sample .= pack('N', strlen($n)) . $n;
                    $key = $key || $t === 5;
                    $slice = $slice || $t === 1 || $t === 5;
                }
            }
            if ($sample === '') {
                continue;
            }
            if ($pts === null || !$slice) {
                if ($v['samples']) {
                    $v['samples'][array_key_last($v['samples'])] .= $sample;
                }
                continue;
            }
            $v['samples'][] = $sample;
            $v['dts'][] = $dts;
            $v['pts'][] = $pts;
            if ($key) {
                $v['key'][] = count($v['samples']);
            }
        }
        if (!$sps || !$pps || !$v['samples']) {
            return null;
        }
        $info = self::sps($sps);
        if ($info['w'] <= 0 || $info['h'] <= 0) {
            return null;
        }
        $v += ['sps' => $sps, 'pps' => $pps, 'w' => $info['w'], 'h' => $info['h'], 'chroma' => $info['chroma'], 'bl' => $info['bl'], 'bc' => $info['bc'], 'profile' => $info['profile']];
        if (!$v['key']) {
            $v['key'] = [1];
        }
        $n = count($v['samples']);
        $dts0 = $v['dts'][0];
        $v['dur'] = [];
        for ($k = 0; $k < $n; $k++) {
            $v['dur'][] = $k + 1 < $n ? max(1, $v['dts'][$k + 1] - $v['dts'][$k]) : ($v['dur'] ? end($v['dur']) : 3000);
        }
        $v['cto'] = array_map(fn($p, $q) => max(0, $p - $q), $v['pts'], $v['dts']);
        $v['size'] = array_map('strlen', $v['samples']);
        $vpts0 = min($v['pts']);
        $tracks = [$v];
        $au = null;
        if ($d['audio'] !== '') {
            $au = ['kind' => 'audio', 'samples' => [], 'size' => []];
            $s = $d['audio'];
            $o = 0;
            $l = strlen($s);
            while ($o + 7 <= $l) {
                if (ord($s[$o]) !== 0xFF || (ord($s[$o + 1]) & 0xF6) !== 0xF0) {
                    $o++;
                    continue;
                }
                $b2 = ord($s[$o + 2]);
                $b3 = ord($s[$o + 3]);
                $fl = (($b3 & 3) << 11) | (ord($s[$o + 4]) << 3) | (ord($s[$o + 5]) >> 5);
                $hl = ord($s[$o + 1]) & 1 ? 7 : 9;
                if ($fl <= $hl || $o + $fl > $l) {
                    break;
                }
                if (!isset($au['obj'])) {
                    $au['obj'] = (($b2 >> 6) & 3) + 1;
                    $au['sfi'] = ($b2 >> 2) & 0xF;
                    $au['ch'] = (($b2 & 1) << 2) | ($b3 >> 6);
                }
                $au['samples'][] = substr($s, $o + $hl, $fl - $hl);
                $o += $fl;
            }
            $rates = [96000, 88200, 64000, 48000, 44100, 32000, 24000, 22050, 16000, 12000, 11025, 8000, 7350];
            if ($au['samples'] && isset($rates[$au['sfi']]) && $au['ch'] > 0) {
                $au['scale'] = $rates[$au['sfi']];
                $au['size'] = array_map('strlen', $au['samples']);
                $au['dur'] = array_fill(0, count($au['samples']), 1024);
                $tracks[] = &$au;
            } else {
                $au = null;
            }
        }
        $base = $au && $d['apts'] !== null ? min($vpts0, $d['apts']) : $vpts0;
        $tracks[0]['delay'] = $vpts0 - $base;
        $tracks[0]['start'] = $vpts0 - $dts0;
        if ($au) {
            $tracks[1]['delay'] = ($d['apts'] ?? $base) - $base;
            $tracks[1]['start'] = 0;
        }
        $times = [];
        foreach ($tracks as $ti => $t) {
            $acc = 0;
            foreach ($t['dur'] as $k => $du) {
                $times[] = [($t['delay'] + $acc * 90000 / $t['scale']) / 90000, $ti, $k];
                $acc += $du;
            }
        }
        usort($times, fn($x, $y) => $x[0] <=> $y[0] ?: $x[1] <=> $y[1]);
        $chunks = [];
        foreach ($times as [$tm, $ti, $k]) {
            $last = $chunks ? array_key_last($chunks) : null;
            if ($last !== null && $chunks[$last]['t'] === $ti && $tm - $chunks[$last]['at'] < 0.5) {
                $chunks[$last]['s'][] = $k;
            } else {
                $chunks[] = ['t' => $ti, 'at' => $tm, 's' => [$k]];
            }
        }
        foreach ($tracks as $ti => &$t) {
            $t['chunks'] = [];
            foreach ($chunks as $c) {
                if ($c['t'] === $ti) {
                    $t['chunks'][] = count($c['s']);
                }
            }
        }
        unset($t);
        $movieDur = 0;
        foreach ($tracks as $t) {
            $movieDur = max($movieDur, (int)round(($t['delay'] / 90000 + array_sum($t['dur']) / $t['scale']) * 1000));
        }
        $ftyp = self::box('ftyp', 'isom' . pack('N', 512) . 'isomiso2avc1mp41');
        $build = function (array $offs) use ($tracks, $movieDur) {
            $mvhd = self::full('mvhd', 0, 0, pack('NNNN', 0, 0, 1000, $movieDur) . pack('Nn', 0x10000, 0x0100) . str_repeat("\0", 10)
                . pack('N9', 0x10000, 0, 0, 0, 0x10000, 0, 0, 0, 0x40000000) . str_repeat("\0", 24) . pack('N', count($tracks) + 1));
            $traks = '';
            foreach ($tracks as $ti => $t) {
                $traks .= self::trak($ti + 1, $t, $offs[$ti], $movieDur);
            }
            return self::box('moov', $mvhd, $traks);
        };
        $zero = array_map(fn($t) => array_fill(0, count($t['chunks']), 0), $tracks);
        $moovLen = strlen($build($zero));
        $mdatSize = 8;
        foreach ($tracks as $t) {
            $mdatSize += array_sum($t['size']);
        }
        if ($mdatSize > 0xFFFFFFFF) {
            return null;
        }
        $pos = strlen($ftyp) + $moovLen + 8;
        $offs = array_map(fn() => [], $tracks);
        $mdat = [];
        foreach ($chunks as $c) {
            $offs[$c['t']][] = $pos;
            foreach ($c['s'] as $k) {
                $mdat[] = $tracks[$c['t']]['samples'][$k];
                $pos += $tracks[$c['t']]['size'][$k];
            }
        }
        $moov = $build($offs);
        if (strlen($moov) !== $moovLen) {
            return null;
        }
        return ['bytes' => $ftyp . $moov . pack('N', $mdatSize) . 'mdat' . implode('', $mdat), 'width' => $v['w'], 'height' => $v['h'], 'duration' => (int)round($movieDur / 1000)];
    }

    public static function ffmpeg(string $ts, ?string $audioTs = null): ?array
    {
        if (!function_exists('proc_open') || in_array('proc_open', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
            return null;
        }
        $bin = null;
        foreach (array_filter([Config::get('FFMPEG'), '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/bin/ffmpeg']) as $c) {
            if (@is_executable($c)) {
                $bin = $c;
                break;
            }
        }
        if (!$bin) {
            return null;
        }
        $dir = sys_get_temp_dir();
        $in = tempnam($dir, 'hv');
        $ain = $audioTs !== null ? tempnam($dir, 'ha') : null;
        $out = tempnam($dir, 'hm') . '.mp4';
        file_put_contents($in, $ts);
        $cmd = [$bin, '-y', '-loglevel', 'error', '-f', 'mpegts', '-i', $in];
        if ($ain) {
            file_put_contents($ain, $audioTs);
            array_push($cmd, '-f', 'mpegts', '-i', $ain, '-map', '0:v:0', '-map', '1:a:0?');
        }
        array_push($cmd, '-c', 'copy', '-bsf:a', 'aac_adtstoasc', '-movflags', '+faststart', $out);
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($p)) {
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            proc_close($p);
        }
        $bytes = @file_get_contents($out);
        foreach (array_filter([$in, $ain, $out, substr($out, 0, -4)]) as $f) {
            @unlink($f);
        }
        return $bytes ? ['bytes' => $bytes, 'width' => 0, 'height' => 0, 'duration' => 0] : null;
    }
}
