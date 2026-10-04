<?php
defined('NB_ROOT') || exit;

function tlDefaults() {
    return [
        'on'    => true,
        'words' => 'ترجمه,translate',
        'who'   => 'owner',
        'icons' => ['btn_en' => '', 'btn_fa' => '', 'btn_ar' => '', 'btn_tr' => '', 'btn_ru' => ''],
        'btns'  => [
            'btn_en' => ['color' => 'primary'], 'btn_fa' => ['color' => 'success'], 'btn_ar' => ['color' => 'primary'],
            'btn_tr' => ['color' => 'primary'], 'btn_ru' => ['color' => 'primary'],
        ],
        'texts' => [
            'btn_en' => 'English', 'btn_fa' => 'فارسی', 'btn_ar' => 'العربية',
            'btn_tr' => 'Türkçe', 'btn_ru' => 'Русский',
            'ask'       => "🌐 <b>به چه زبانی ترجمه کنم؟</b>",
            'result'    => "🌐 <b>ترجمه به {lang}</b>\n\n<blockquote expandable>{text}</blockquote>",
            'no_text'   => "⚠️ پیامِ متنی نیست",
            'fail'      => "⚠️ ترجمه نشد",
            'not_yours' => 'این دکمه‌ها مالِ کسی است که ترجمه خواست.',
            'gone'      => 'پیامِ اصلی پاک شده.',
            'slow'      => 'کمی آرام‌تر',
        ],
    ];
}

function tlCfg() {
    $c = cfg()['translate'] ?? null;
    return is_array($c) ? array_replace_recursive(tlDefaults(), $c) : tlDefaults();
}

function tlSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['translate'] ?? null)) $c['translate'] = [];
        $fn($c['translate']);
    });
}

function tlVal($path, $default = null) {
    $v = tlCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}

function tlOn() { return !empty(tlVal('on')); }

function tlLangs() { return ['en' => 'btn_en', 'fa' => 'btn_fa', 'ar' => 'btn_ar', 'tr' => 'btn_tr', 'ru' => 'btn_ru']; }
function tlBtnKeys() { return array_values(tlLangs()); }
function tlIsButtonKey($k) { return in_array($k, tlBtnKeys(), true); }

function tlT($slug, $vars = []) {
    $t = (string)tlVal('texts.' . $slug, tlDefaults()['texts'][$slug] ?? $slug);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function tlLabels() {
    return [
        'btn_en' => 'دکمه: انگلیسی', 'btn_fa' => 'دکمه: فارسی', 'btn_ar' => 'دکمه: عربی',
        'btn_tr' => 'دکمه: ترکی', 'btn_ru' => 'دکمه: روسی',
        'ask'       => 'متنِ اصلی (بالای دکمه‌ها)',
        'result'    => 'متنِ نتیجه‌ی ترجمه',
        'no_text'   => 'ریپلای روی پیامِ بی‌متن',
        'fail'      => 'ترجمه نشد',
        'not_yours' => 'پاپ‌آپ — مالِ تو نیست',
        'gone'      => 'پاپ‌آپ — پیامِ اصلی پاک شده',
        'slow'      => 'پاپ‌آپ — زیاد زدی',
    ];
}
function tlLabel($k) { return tlLabels()[$k] ?? $k; }

function tlBtn($key, $data) {
    $b = ['callback_data' => $data];
    $color = (string)tlVal('btns.' . $key . '.color', '');
    if (isStyle($color)) $b['style'] = $color;
    return btnApplyLabel($b, tlT($key), tlVal('icons.' . $key, ''));
}

function tlKb($uid) {
    $l = tlLangs();
    $b = fn($code) => tlBtn($l[$code], 'tll_' . $code . '_' . (int)$uid);
    return inlineKb([[$b('en'), $b('fa'), $b('ar')], [$b('tr'), $b('ru')]]);
}

function tlLangName($code) {
    [$txt] = btnLabelEmoji(tlT(tlLangs()[$code] ?? 'btn_en'));
    return trim($txt);
}

function tlIsWord($t) {
    $t = mb_strtolower(trim((string)$t));
    foreach (explode(',', (string)tlVal('words', '')) as $w)
        if ($t !== '' && $t === mb_strtolower(trim($w))) return true;
    return false;
}

function tlDb() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    if (!class_exists('SQLite3') && !dbOn()) return $db = false;
    try { $db = nbRawOpen(DATA_DIR . '/translate.sqlite'); }
    catch (Throwable $e) { error_log('[translate] ' . $e->getMessage()); return $db = false; }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('CREATE TABLE IF NOT EXISTS tl_cache (k TEXT PRIMARY KEY, v TEXT NOT NULL, at INTEGER NOT NULL)');
    $db->exec('CREATE INDEX IF NOT EXISTS tl_cache_at ON tl_cache (at)');
    return $db;
}

function tlProviders() {
    return [
        'google'   => ['label' => 'گوگل (رایگان، بدونِ کلید)', 'key' => false, 'url' => false],
        'mymemory' => ['label' => 'MyMemory (رایگان، بدونِ کلید)', 'key' => false, 'url' => false],
        'libre'    => ['label' => 'LibreTranslate (آدرسِ سرور + کلیدِ اختیاری)', 'key' => true, 'url' => true],
        'gcloud'   => ['label' => 'Google Cloud Translation (کلیدِ API)', 'key' => true, 'url' => false],
        'deepl'    => ['label' => 'DeepL (کلیدِ API)', 'key' => true, 'url' => false],
        'azure'    => ['label' => 'Microsoft Translator (کلید + ناحیه)', 'key' => true, 'url' => false],
    ];
}

function tlApiCfg() {
    $d = ['primary' => 'google', 'google' => ['on' => 1], 'mymemory' => ['on' => 1, 'email' => ''],
          'libre' => ['on' => 0, 'url' => '', 'key' => ''], 'gcloud' => ['on' => 0, 'key' => ''],
          'deepl' => ['on' => 0, 'key' => ''], 'azure' => ['on' => 0, 'key' => '', 'region' => '']];
    $c = tlVal('api', []);
    return is_array($c) ? array_replace_recursive($d, $c) : $d;
}

function tlReady($p, $c = null) {
    $c = $c ?? tlApiCfg();
    $x = $c[$p] ?? [];
    if (empty($x['on']) || !isset(tlProviders()[$p])) return false;
    if ($p === 'libre') return trim((string)($x['url'] ?? '')) !== '';
    if (tlProviders()[$p]['key']) return trim((string)($x['key'] ?? '')) !== '';
    return true;
}

function tlChain() {
    $c = tlApiCfg();
    $all = array_keys(tlProviders());
    $pri = (string)($c['primary'] ?? 'google');
    $order = in_array($pri, $all, true) ? array_merge([$pri], array_diff($all, [$pri])) : $all;
    return array_values(array_filter($order, fn($p) => tlReady($p, $c)));
}

function tlEndpoint($p) {
    $k = 'TL_EP_' . strtoupper($p);
    if (defined($k)) return (string)constant($k);
    return [
        'google'   => defined('TL_API') ? TL_API : 'https://translate.googleapis.com/translate_a/single',
        'mymemory' => 'https://api.mymemory.translated.net/get',
        'gcloud'   => 'https://translation.googleapis.com/language/translate/v2',
        'deepl'    => 'https://api.deepl.com/v2/translate',
        'deepl_free' => 'https://api-free.deepl.com/v2/translate',
        'azure'    => 'https://api.cognitive.microsofttranslator.com/translate',
    ][$p] ?? '';
}

function tlDown($p, $secs = null) {
    $k = preg_replace('/[^a-z]/', '', (string)$p);
    if ($secs === null) return (int)(load('tl_down')[$k] ?? 0) > time();
    $until = $secs > 0 ? time() + (int)$secs : 0;
    if ($until === 0 && !isset(load('tl_down')[$k])) return false;
    mutate('tl_down', function (&$a) use ($k, $until) { if ($until > 0) $a[$k] = $until; else unset($a[$k]); });
    return $until > 0;
}

function tlSrcLang($text) {
    if (preg_match('/[پچژگکی]/u', $text)) return 'fa';
    if (preg_match('/\p{Arabic}/u', $text)) return 'ar';
    if (preg_match('/\p{Cyrillic}/u', $text)) return 'ru';
    if (preg_match('/[ğışçöüİĞŞÇÖÜ]/u', $text)) return 'tr';
    return 'en';
}

function tlSafeTarget($url, &$why = '') {
    $p = parse_url((string)$url);
    $host = strtolower((string)($p['host'] ?? ''));
    if (strtolower((string)($p['scheme'] ?? '')) !== 'https' && !(defined('SV_ALLOW_PRIVATE') && SV_ALLOW_PRIVATE)) { $why = 'آدرس باید https باشد'; return null; }
    if ($host === '') { $why = 'آدرس نامعتبر است'; return null; }
    if (defined('SV_ALLOW_PRIVATE') && SV_ALLOW_PRIVATE) return [];
    $port = (int)($p['port'] ?? 443);
    $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : (string)gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $why = 'این آدرس به شبکه‌ی داخلی/محلی اشاره می‌کند یا پیدا نشد';
        return null;
    }
    return [$host . ':' . $port . ':' . $ip];
}

function tlHttp($url, $body, array $headers = [], $timeout = 7, array $resolve = []) {
    $ch = curl_init($url);
    $o = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'Mozilla/5.0', CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ];
    if ($body !== null) { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = $body; }
    if ($resolve) $o[CURLOPT_RESOLVE] = $resolve;
    curl_setopt_array($ch, $o);
    $res  = monCurl($ch, 'translate');
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = $res === false ? curl_error($ch) : '';
    curl_close($ch);
    return [$code, $res === false ? '' : (string)$res, $err];
}

function tlCall($p, $text, $to) {
    $c = tlApiCfg();
    $x = (array)($c[$p] ?? []);
    $key = trim((string)($x['key'] ?? ''));
    $json = 'Content-Type: application/json';
    $form = 'Content-Type: application/x-www-form-urlencoded;charset=UTF-8';
    if ($p === 'google') {
        [$code, $res, $err] = tlHttp(tlEndpoint('google') . '?client=gtx&sl=auto&tl=' . $to . '&dt=t', 'q=' . rawurlencode($text), [$form]);
        $j = $code === 200 ? json_decode($res, true) : null;
        $out = '';
        if (is_array($j) && is_array($j[0] ?? null)) foreach ($j[0] as $seg) if (is_array($seg) && is_string($seg[0] ?? null)) $out .= $seg[0];
    } elseif ($p === 'mymemory') {
        $src = tlSrcLang($text);
        if ($src === $to) return [$text, 200, ''];
        $out = '';
        $parts = preg_split('/(?<=[\.\!\?؟\n])\s*/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $chunks = [];
        $cur = '';
        foreach ($parts as $pt) {
            if (mb_strlen($cur . ' ' . $pt) > 450 && $cur !== '') { $chunks[] = $cur; $cur = ''; }
            $cur = trim($cur . ' ' . mb_substr($pt, 0, 450));
        }
        if ($cur !== '') $chunks[] = $cur;
        $code = 0; $err = '';
        foreach (array_slice($chunks, 0, 4) as $q) {
            $u = tlEndpoint('mymemory') . '?q=' . rawurlencode($q) . '&langpair=' . $src . '|' . $to;
            if (trim((string)($x['email'] ?? '')) !== '') $u .= '&de=' . rawurlencode(trim((string)$x['email']));
            [$code, $res, $err] = tlHttp($u, null);
            $j = $code === 200 ? json_decode($res, true) : null;
            $t = (string)($j['responseData']['translatedText'] ?? '');
            $st = (int)($j['responseStatus'] ?? 0);
            if ($st !== 200 || $t === '' || stripos($t, 'MYMEMORY WARNING') !== false || stripos($t, 'LIMIT EXCEEDED') !== false) {
                $code = $st === 429 || stripos($t, 'MYMEMORY WARNING') !== false ? 429 : ($code === 200 ? 400 : $code);
                $out = '';
                break;
            }
            $out .= ($out !== '' ? ' ' : '') . html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    } elseif ($p === 'libre') {
        $url = trim((string)($x['url'] ?? ''));
        if (!preg_match('#/translate/?$#', $url)) $url = rtrim($url, '/') . '/translate';
        $why = '';
        $pin = tlSafeTarget($url, $why);
        if ($pin === null) return [null, 0, $why];
        $b = ['q' => $text, 'source' => 'auto', 'target' => $to, 'format' => 'text'];
        if ($key !== '') $b['api_key'] = $key;
        [$code, $res, $err] = tlHttp($url, json_encode($b, JSON_UNESCAPED_UNICODE), [$json], 8, $pin);
        $j = json_decode($res, true);
        $out = $code === 200 ? (string)($j['translatedText'] ?? '') : '';
        if ($out === '' && is_array($j) && !empty($j['error'])) $err = (string)$j['error'];
    } elseif ($p === 'gcloud') {
        [$code, $res, $err] = tlHttp(tlEndpoint('gcloud') . '?key=' . rawurlencode($key),
            json_encode(['q' => $text, 'target' => $to, 'format' => 'text'], JSON_UNESCAPED_UNICODE), [$json]);
        $j = json_decode($res, true);
        $out = $code === 200 ? html_entity_decode((string)($j['data']['translations'][0]['translatedText'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
        if ($out === '' && is_array($j)) $err = (string)($j['error']['message'] ?? $err);
    } elseif ($p === 'deepl') {
        $tl = ['en' => 'EN-US'][$to] ?? strtoupper($to);
        $ep = str_ends_with($key, ':fx') ? tlEndpoint('deepl_free') : tlEndpoint('deepl');
        [$code, $res, $err] = tlHttp($ep, http_build_query(['text' => $text, 'target_lang' => $tl]),
            [$form, 'Authorization: DeepL-Auth-Key ' . $key]);
        $j = json_decode($res, true);
        $out = $code === 200 ? (string)($j['translations'][0]['text'] ?? '') : '';
        if ($out === '' && is_array($j)) $err = (string)($j['message'] ?? $err);
    } elseif ($p === 'azure') {
        $h = [$json, 'Ocp-Apim-Subscription-Key: ' . $key];
        if (trim((string)($x['region'] ?? '')) !== '') $h[] = 'Ocp-Apim-Subscription-Region: ' . preg_replace('/[^a-z0-9]/i', '', (string)$x['region']);
        [$code, $res, $err] = tlHttp(tlEndpoint('azure') . '?api-version=3.0&to=' . $to, json_encode([['Text' => $text]], JSON_UNESCAPED_UNICODE), $h);
        $j = json_decode($res, true);
        $out = $code === 200 ? (string)($j[0]['translations'][0]['text'] ?? '') : '';
        if ($out === '' && is_array($j)) $err = (string)($j['error']['message'] ?? $err);
    } else {
        return [null, 0, 'unknown'];
    }
    $out = trim((string)$out);
    if ($out === '' && $err === '') $err = $code > 0 ? 'HTTP ' . $code : 'پاسخی نیامد';
    return [$out !== '' ? $out : null, $code, $err];
}

function tlTranslate($text, $to) {
    $text = mb_substr(trim((string)$text), 0, 1500);
    if ($text === '' || !isset(tlLangs()[$to])) return null;
    $key = md5($to . '|' . $text);
    $db  = tlDb();
    if ($db) {
        $st = $db->prepare('SELECT v FROM tl_cache WHERE k = :k AND at > :t');
        $st->bindValue(':k', $key, SQLITE3_TEXT);
        $st->bindValue(':t', time() - 7 * 86400, SQLITE3_INTEGER);
        $row = $st->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row) return (string)$row['v'];
    }
    $out = null;
    $t0 = microtime(true);
    foreach (tlChain() as $p) {
        if (tlDown($p)) continue;
        if (microtime(true) - $t0 > 14) break;
        [$out, $code, $err] = tlCall($p, $text, $to);
        if ($out !== null) break;
        if ($code === 0 || $code === 200 || $code === 429 || $code === 456 || $code >= 500) tlDown($p, 300);
        elseif ($code === 401 || $code === 403) tlDown($p, 1800);
        error_log('[translate] ' . $p . ': ' . mb_substr($err, 0, 160));
    }
    if ($out === null) return null;
    if ($db) {
        $st = $db->prepare('INSERT OR REPLACE INTO tl_cache (k, v, at) VALUES (:k, :v, :t)');
        $st->bindValue(':k', $key, SQLITE3_TEXT);
        $st->bindValue(':v', $out, SQLITE3_TEXT);
        $st->bindValue(':t', time(), SQLITE3_INTEGER);
        $st->execute();
        if (mt_rand(1, 200) === 1) { $__st = $db->prepare('DELETE FROM tl_cache WHERE at < :c'); if ($__st) { $__st->bindValue(':c', time() - 7 * 86400, SQLITE3_INTEGER); $__st->execute(); } }
    }
    return $out;
}

function tlTest($p) {
    $t = microtime(true);
    [$out, $code, $err] = tlCall($p, 'سلام دنیا، امروز هوا خوب است.', 'en');
    $ms = (int)((microtime(true) - $t) * 1000);
    if ($out !== null) tlDown($p, 0);
    return [$out !== null, $ms, $out !== null ? $out : ($err !== '' ? $err : 'HTTP ' . $code)];
}

function tlHandle($msg, $uid, $chatId) {
    if (!tlOn() || !tlIsWord($msg['text'] ?? '')) return false;
    $rep = $msg['reply_to_message'] ?? null;
    $src = is_array($rep) ? trim((string)($rep['text'] ?? $rep['caption'] ?? '')) : '';
    if ($src === '') {
        sendMsg(BOT_TOKEN, $chatId, tlT('no_text'), null, ['reply_to_message_id' => (int)($msg['message_id'] ?? 0), 'allow_sending_without_reply' => 'true']);
        return true;
    }
    sendMsg(BOT_TOKEN, $chatId, tlT('ask'), tlKb($uid),
        ['reply_to_message_id' => (int)$rep['message_id'], 'allow_sending_without_reply' => 'true']);
    return true;
}

function tlCallback($cb) {
    if (!preg_match('/^tll_([a-z]{2})_(\d+)$/', (string)($cb['data'] ?? ''), $m)) return false;
    $cbId = $cb['id'];
    $uid  = (int)($cb['from']['id'] ?? 0);
    $own  = (int)$m[2];
    if ($uid !== $own && tlVal('who') !== 'any' && !isAdmin($uid)) { answerCb(BOT_TOKEN, $cbId, tlT('not_yours'), true); return true; }
    $orig = $cb['message']['reply_to_message'] ?? null;
    $src  = is_array($orig) ? trim((string)($orig['text'] ?? $orig['caption'] ?? '')) : '';
    if ($src === '') { answerCb(BOT_TOKEN, $cbId, tlT('gone'), true); return true; }
    if (function_exists('maRateOk') && !maRateOk('tl', $uid, 15, 60)) { answerCb(BOT_TOKEN, $cbId, tlT('slow'), true); return true; }
    answerCb(BOT_TOKEN, $cbId, '⏳');
    $chat = $cb['message']['chat']['id'] ?? 0;
    $mid  = (int)($cb['message']['message_id'] ?? 0);
    $out  = tlTranslate($src, $m[1]);
    editMsg(BOT_TOKEN, $chat, $mid,
        $out === null ? tlT('fail') : tlT('result', ['lang' => h(tlLangName($m[1])), 'text' => emKeep(h($out))]), tlKb($own));
    return true;
}

function tlTextsCfg() {
    return [
        'title' => 'متن‌ها و دکمه‌های ترجمه',
        'keys'  => array_keys((array)tlVal('texts', [])),
        'popup' => ['not_yours', 'gone', 'slow'],
        'btns'  => tlBtnKeys(),
        'label' => 'tlLabel',
        'value' => function ($k) { return (string)tlVal('texts.' . $k, ''); },
        'cb'    => 'tlat_',
        'edit'  => 'tlats_',
        'back'  => 'tl_home',
    ];
}

function tlAdminHome($chatId, $msgId = null) {
    $words = implode('، ', array_filter(array_map('trim', explode(',', (string)tlVal('words', '')))));
    $t  = "🌐 <b>ترجمه</b>\n\n";
    $t .= 'وضعیت: ' . (tlOn() ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= '🗣 کلمه‌ها: <b>' . h($words) . "</b>\n";
    $t .= '👥 انتخابِ زبان: <b>' . (tlVal('who') === 'any' ? 'همه' : 'فقط درخواست‌کننده') . "</b>\n";
    $chain = array_map(fn($p) => explode(' (', tlProviders()[$p]['label'])[0] . (tlDown($p) ? ' ⏸' : ''), tlChain());
    $t .= '🔌 سرویس‌ها: <b>' . h($chain ? implode(' ← ', $chain) : 'هیچ‌کدام') . '</b> · پنلِ وب ← API';
    $rows = [
        [btnCb(tlOn() ? '✅ روشن' : '❌ خاموش', 'tlax', 'info')],
        [btnCb('🗣 کلمه‌ها', 'tlaw', 'admin'), btnCb('👥 چه کسی بزند', 'tlawho', 'info')],
        [btnCb('✏️ متن‌ها و دکمه‌ها', 'tlat_home', 'admin'), btnCb('🎨 رنگِ دکمه‌ها', 'tlacolors', 'admin')],
        [btnCb('👀 پیش‌نمایش', 'tlaprev', 'confirm')],
        [btnCb(UT('back'), 'ag_games', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function tlAdminColors($chatId, $msgId) {
    $t = "🎨 <b>رنگِ دکمه‌های ترجمه</b>\n\n";
    $rows = [];
    foreach (tlBtnKeys() as $k) {
        $color = (string)tlVal('btns.' . $k . '.color', 'none');
        $t .= '• ' . h(tlLabel($k)) . ': <b>' . h(styleMap()[$color] ?? $color) . "</b>\n";
        $rows[] = [btnCb(tlLabel($k), 'tlacolk_' . $k, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'tl_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function tlAdminColorPick($chatId, $msgId, $k) {
    $cur = (string)tlVal('btns.' . $k . '.color', 'none');
    $t = "🎨 رنگِ <b>" . h(tlLabel($k)) . "</b>\n\nالان: <b>" . h(styleMap()[$cur] ?? $cur) . "</b>";
    $rows = [];
    foreach (styleMap() as $sk => $sl) $rows[] = [btnCb(($sk === $cur ? '✅ ' : '') . $sl, 'tlacolv_' . $k . '_' . $sk, 'info')];
    $rows[] = [btnCb(UT('back'), 'tlacolors', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function tlAdminCallback($data, $chatId, $msgId, $cbId) {
    $d = (string)$data;
    if (!str_starts_with($d, 'tl_home') && !str_starts_with($d, 'tla')) return false;
    if ($d === 'tl_home') { answerCb(BOT_TOKEN, $cbId); tlAdminHome($chatId, $msgId); return true; }
    if (txRoute(tlTextsCfg(), $d, $chatId, $msgId, $cbId)) return true;

    if ($d === 'tlax') {
        tlSet(function (&$c) { $c['on'] = !tlOn(); });
        answerCb(BOT_TOKEN, $cbId, '✅'); tlAdminHome($chatId, $msgId); return true;
    }
    if ($d === 'tlawho') {
        tlSet(function (&$c) { $c['who'] = tlVal('who') === 'any' ? 'owner' : 'any'; });
        answerCb(BOT_TOKEN, $cbId, '✅'); tlAdminHome($chatId, $msgId); return true;
    }
    if ($d === 'tlaprev') {
        answerCb(BOT_TOKEN, $cbId);
        sendMsg(BOT_TOKEN, $chatId, tlT('ask'), tlKb(0));
        return true;
    }
    if ($d === 'tlaw') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'tl_words', []);
        sendMsg(BOT_TOKEN, $chatId,
            "🗣 کلمه‌هایی که با ریپلای ترجمه را باز می‌کنند؛ با کاما جدا کن.\n\nمثال: <code>ترجمه,translate,tr</code>\n\nالان: <code>" .
            h((string)tlVal('words', '')) . '</code>',
            inlineKb([[btnCb(UT('cancel'), 'tl_home', 'cancel')]]));
        return true;
    }
    if ($d === 'tlacolors') { answerCb(BOT_TOKEN, $cbId); tlAdminColors($chatId, $msgId); return true; }
    if (preg_match('/^tlacolk_(\w+)$/', $d, $m) && tlIsButtonKey($m[1])) {
        answerCb(BOT_TOKEN, $cbId); tlAdminColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (preg_match('/^tlacolv_(\w+)_(\w+)$/', $d, $m) && tlIsButtonKey($m[1]) && isset(styleMap()[$m[2]])) {
        tlSet(function (&$c) use ($m) {
            if (!is_array($c['btns'][$m[1]] ?? null)) $c['btns'][$m[1]] = [];
            $c['btns'][$m[1]]['color'] = $m[2];
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); tlAdminColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (str_starts_with($d, 'tlats_')) {
        $k = substr($d, 6);
        if (!isset(tlLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'tl_text', ['k' => $k]);
        $back = inlineKb([[btnCb(UT('back'), 'tlat_home', 'cancel')]]);
        $cur  = (string)tlVal('texts.' . $k, '');
        if (tlIsButtonKey($k)) {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ <b>" . h(tlLabel($k)) . "</b>\n\n" .
                'الان: <code>' . h(strip_tags($cur)) . '</code>', $back);
        } else {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ <b>" . h(tlLabel($k)) . "</b>\n\n" .
                ($k === 'result' ? "<code>{lang}</code> <code>{text}</code>\n\n" : '') .
                "الان:\n" . $cur, $back);
        }
        return true;
    }
    return false;
}

function tlStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'tl_')) return false;
    if (!isAdmin($uid)) return false;
    $back = inlineKb([[btnCb('🌐 ترجمه', 'tl_home', 'admin')]]);
    $text = trim((string)($msg['text'] ?? ''));

    if ($action === 'tl_words') {
        $w = implode(',', array_slice(array_values(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 20),
            preg_split('/[,،\n]+/u', $text)), fn($x) => $x !== '')), 0, 8));
        if ($w === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ کلمه‌ای نیست'); return true; }
        tlSet(function (&$c) use ($w) { $c['words'] = $w; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ کلمه‌ها ثبت شد.', $back);
        return true;
    }

    if ($action === 'tl_text') {
        $k = (string)((getState($uid)['data'] ?? [])['k'] ?? '');
        if (!isset(tlLabels()[$k])) { clearState($uid); return true; }
        if (tlIsButtonKey($k)) {
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
            $ids  = customEmojiIds($msg);
            $icon = $ids ? (string)$ids[0] : '';
            if ($icon !== '') {
                $clean = textWithoutCustomEmoji($msg);
                $text  = $clean !== '' ? $clean : $text;
            }
            tlSet(function (&$c) use ($k, $text, $icon) {
                $c['texts'][$k] = mb_substr($text, 0, 40);
                $c['icons'][$k] = $icon;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                '✅ ذخیره شد' . ($icon !== '' ? ' — ایموجیِ پریمیوم رویِ دکمه نشست.' : '.') . "\n\nاین‌طور دیده می‌شود:",
                inlineKb([[tlBtn($k, 'tlnop')], [btnCb('🌐 ترجمه', 'tl_home', 'admin')]]));
            return true;
        }
        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
        tlSet(function (&$c) use ($k, $html) { $c['texts'][$k] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.', $back);
        return true;
    }
    return false;
}
