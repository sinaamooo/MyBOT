<?php
defined('NB_ROOT') || exit;

function emCore() {
    return '[\x{1F000}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{2712}\x{2714}\x{2716}-\x{27BF}\x{2B00}-\x{2BFF}\x{2300}-\x{23FF}'
         . '\x{2194}-\x{2199}\x{21A9}\x{21AA}\x{2934}\x{2935}\x{3030}\x{303D}\x{3297}\x{3299}\x{203C}\x{2049}\x{2139}\x{24C2}'
         . '\x{25AA}\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}]';
}

function emRe() {
    static $re = null;
    if ($re !== null) return $re;
    $c = emCore();
    $mod = '(?:[\x{FE0E}\x{FE0F}]|[\x{1F3FB}-\x{1F3FF}]|[\x{E0020}-\x{E007F}])*';
    $re = '/(?:[0-9#*][\x{FE0F}]?\x{20E3}|' . $c . $mod . '(?:\x{200D}' . $c . $mod . ')*|[\x{FE0F}\x{FE0E}\x{200D}\x{20E3}])/u';
    return $re;
}

function emKey($e) {
    return str_replace(["\u{FE0F}", "\u{FE0E}"], '', (string)$e);
}

function emHas($s) {
    return is_string($s) && $s !== '' && preg_match(emRe(), $s) === 1;
}

function emMap($fresh = false) {
    static $map = null;
    if ($map !== null && !$fresh) return $map;
    $f = DATA_DIR . '/config.json';
    clearstatcache(true, $f);
    $mt = (string)(@md5_file($f) ?: (int)@filemtime($f));
    $cf = DATA_DIR . '/emap.json';
    $cache = @json_decode((string)@file_get_contents($cf), true);
    if (is_array($cache) && (string)($cache['m'] ?? '') === $mt && is_array($cache['map'] ?? null)) return $map = $cache['map'];
    $cnt = [];
    $walk = function ($v) use (&$walk, &$cnt) {
        if (is_array($v)) { foreach ($v as $x) $walk($x); return; }
        if (!is_string($v) || stripos($v, 'tg-emoji') === false) return;
        if (!preg_match_all('#<tg-emoji\s+emoji-id\s*=\s*["\']?(\d+)["\']?\s*>(.*?)</tg-emoji>#isu', $v, $mm, PREG_SET_ORDER)) return;
        foreach ($mm as $m) {
            $k = emKey(trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($k === '' || !emHas($k)) continue;
            $cnt[$k][$m[1]] = ($cnt[$k][$m[1]] ?? 0) + 1;
        }
    };
    $c = @json_decode((string)@file_get_contents($f), true);
    if (!is_array($c)) $c = function_exists('cfg') ? cfg() : [];
    $walk($c);
    $out = [];
    foreach ($cnt as $k => $ids) { arsort($ids); $out[$k] = (string)array_key_first($ids); }
    foreach ((array)($c['premium_map'] ?? []) as $k => $id) {
        $k = emKey(trim((string)$k));
        if ($k !== '' && ctype_digit((string)$id)) $out[$k] = (string)$id;
    }
    @file_put_contents($cf, json_encode(['m' => $mt, 'map' => $out], JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $map = $out;
}

function emIdOf($e) {
    $m = emMap();
    return (string)($m[emKey($e)] ?? '');
}

function emKeep($s) {
    return "\u{E000}" . (string)$s . "\u{E001}";
}

function emSign($e) {
    static $t = ['✅' => '✓', '✔' => '✓', '☑' => '✓', '🟢' => '✓', '❌' => '✕', '✖' => '✕', '❎' => '✕', '🔴' => '✕',
                 '⛔' => '✕', '🚫' => '✕', '⚪' => '○', '⏸' => '‖', '🔁' => '↻', '🆕' => '+'];
    return $t[emKey($e)] ?? '';
}

function emDrop($m, $sign = true) {
    $e = rtrim($m[0], " \u{00A0}");
    $tail = substr($m[0], strlen($e));
    if (preg_match('/^[0-9#*][\x{FE0F}]?\x{20E3}$/u', $e)) return $e[0] . $tail;
    $s = $sign ? emSign($e) : '';
    return $s !== '' ? $s . $tail : '';
}

function emPlain($s, $sign = true) {
    $s = (string)$s;
    if ($s === '' || !emHas($s)) return $s;
    $out = preg_replace_callback(emSpRe(), fn($m) => emDrop($m, $sign), $s);
    return $out === null ? $s : $out;
}

function emSpRe() {
    static $re = null;
    if ($re === null) $re = substr(emRe(), 0, -2) . '[ \x{00A0}]?/u';
    return $re;
}

function emTidy($s) {
    return preg_replace('/^[ \x{00A0}\t]+|[ \x{00A0}\t]+$/mu', '', (string)$s);
}

function emNode($t, $html, $atStart = false, $atEnd = false) {
    if (!emHas($t)) return $t;
    $lines = explode("\n", $t);
    $n = count($lines);
    $out = [];
    foreach ($lines as $i => $ln) {
        if (!emHas($ln)) { $out[] = $ln; continue; }
        $new = preg_replace_callback(emSpRe(), function ($m) use ($html) {
            $e = rtrim($m[0], " \u{00A0}");
            if ($html && ($id = emIdOf($e)) !== '') return '<tg-emoji emoji-id="' . $id . '">' . $e . '</tg-emoji>' . substr($m[0], strlen($e));
            return emDrop($m);
        }, $ln);
        if ($new === null) { $out[] = $ln; continue; }
        if ($i > 0 || $atStart) $new = preg_replace('/^[ \x{00A0}\t]+/u', '', $new);
        if ($i < $n - 1 || $atEnd) $new = preg_replace('/[ \x{00A0}\t]+$/u', '', $new);
        if (trim($new) === '' && trim($ln) !== '' && $i > 0 && $i < $n - 1) continue;
        $out[] = $new;
    }
    return implode("\n", $out);
}

function emText($s, $html = true) {
    if (!is_string($s) || $s === '') return $s;
    $keep = [];
    if (strpos($s, "\u{E000}") !== false) {
        $s = preg_replace_callback('/\x{E000}(.*?)\x{E001}/su', function ($m) use (&$keep) {
            $keep[] = $m[1];
            return "\u{E002}" . (count($keep) - 1) . "\u{E003}";
        }, $s);
        $s = str_replace(["\u{E000}", "\u{E001}"], '', $s);
    }
    if (!$html) {
        $out = emHas($s) ? emNode($s, false, true, true) : $s;
    } else {
        $parts = preg_split('#(<tg-emoji\b[^>]*>.*?</tg-emoji>|<(?:code|pre)\b[^>]*>.*?</(?:code|pre)>|<[^>]+>)#isu', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) return emPlain($s);
        $out = '';
        $last = count($parts) - 1;
        foreach ($parts as $i => $p) {
            if ($i % 2 === 0) { $out .= emNode($p, true, $i === 0, $i === $last); continue; }
            if (stripos($p, '<tg-emoji') === 0) { $out .= $p; continue; }
            if (preg_match('#^<(code|pre)\b#i', $p)) { $out .= emPlain($p); continue; }
            $out .= $p;
        }
    }
    if ($keep) $out = preg_replace_callback('/\x{E002}(\d+)\x{E003}/u', fn($m) => $keep[(int)$m[1]] ?? '', $out);
    return $out;
}

function emBtn(array $b) {
    if (!isset($b['text']) || !is_string($b['text'])) return $b;
    $t = $b['text'];
    if (!emHas($t)) return $b;
    $first = preg_match(emRe(), $t, $m) ? $m[0] : '';
    if (trim((string)($b['icon_custom_emoji_id'] ?? '')) === '' && $first !== '' && ($id = emIdOf($first)) !== '')
        $b['icon_custom_emoji_id'] = $id;
    if (trim((string)($b['icon_custom_emoji_id'] ?? '')) !== '') {
        $clean = trim(emPlain($t, false));
        $b['text'] = $clean !== '' ? $clean : (defined('BTN_BLANK') ? BTN_BLANK : "\u{2060}");
        return $b;
    }
    $clean = trim(emPlain($t));
    $b['text'] = $clean !== '' ? $clean : '•';
    return $b;
}

function emMarkup($rm) {
    $isStr = is_string($rm);
    $j = $isStr ? json_decode($rm, true) : $rm;
    if (!is_array($j)) return $rm;
    $hit = false;
    foreach (['inline_keyboard', 'keyboard'] as $k) {
        if (empty($j[$k]) || !is_array($j[$k])) continue;
        foreach ($j[$k] as $i => $row) {
            if (!is_array($row)) continue;
            foreach ($row as $x => $btn) {
                if (!is_array($btn) || !emHas($btn['text'] ?? '')) continue;
                $j[$k][$i][$x] = emBtn($btn);
                $hit = true;
            }
        }
    }
    if (isset($j['input_field_placeholder']) && emHas($j['input_field_placeholder'])) {
        $j['input_field_placeholder'] = emTidy(emPlain($j['input_field_placeholder']));
        $hit = true;
    }
    if (!$hit) return $rm;
    return $isStr ? json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $j;
}

function emOut($method, array $data) {
    $html = strtoupper((string)($data['parse_mode'] ?? '')) === 'HTML';
    foreach (['text', 'caption'] as $k)
        if (isset($data[$k]) && is_string($data[$k])) $data[$k] = emText($data[$k], $html && $method !== 'answerCallbackQuery');
    if (isset($data['reply_markup'])) $data['reply_markup'] = emMarkup($data['reply_markup']);
    if (isset($data['media']) && is_string($data['media']) && emHas($data['media'])) {
        $md = json_decode($data['media'], true);
        if (is_array($md) && isset($md['caption'])) {
            $md['caption'] = emText((string)$md['caption'], strtoupper((string)($md['parse_mode'] ?? '')) === 'HTML');
            $data['media'] = json_encode($md, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    if (isset($data['menu_button']) && is_string($data['menu_button']) && emHas($data['menu_button'])) {
        $mb = json_decode($data['menu_button'], true);
        if (is_array($mb) && isset($mb['text'])) {
            $mb['text'] = trim(emTidy(emPlain((string)$mb['text']))) ?: 'Open';
            $data['menu_button'] = json_encode($mb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    if ($method === 'setMyCommands' && isset($data['commands']) && is_string($data['commands']) && emHas($data['commands'])) {
        $cm = json_decode($data['commands'], true);
        if (is_array($cm)) {
            foreach ($cm as &$c) if (isset($c['description'])) $c['description'] = trim(emTidy(emPlain((string)$c['description']))) ?: (string)($c['command'] ?? '');
            unset($c);
            $data['commands'] = json_encode($cm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    return $data;
}
