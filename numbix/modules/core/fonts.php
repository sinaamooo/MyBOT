<?php
defined('NB_ROOT') || exit;

function fntList() {
    if (function_exists('pxFontList')) return pxFontList();
    if (function_exists('bcFontList')) return bcFontList();
    return [];
}

function fntHasFa($path) {
    if (function_exists('pxFontHasFa')) return pxFontHasFa($path);
    if (function_exists('bcFontHasFa')) return bcFontHasFa($path);
    return true;
}

function fntCurrent($which) {
    if ($which === 'px') return function_exists('pxFont') ? (string)pxFont(true) : '';
    return function_exists('bcFont') ? (string)(bcFont(true) ?: '') : '';
}

function fntBoldOf($p) {
    foreach (['-Bold', 'Bold', '-bold'] as $suf) {
        $c = preg_replace('/(-?(Regular|regular))?\.ttf$/i', $suf . '.ttf', $p);
        if ($c !== $p && is_file($c)) return $c;
    }
    return $p;
}

function fntApply($which, $path) {
    if ($path === '' || !is_file($path)) return false;
    $bold = fntBoldOf($path);

    if (($which === 'px' || $which === 'both') && function_exists('pxSet')) {
        pxSet(function (&$c) use ($path, $bold) { $c['card']['font'] = $path; $c['card']['font_bold'] = $bold; });
        if (function_exists('pxFontCacheBust')) pxFontCacheBust();
    }
    if (($which === 'bk' || $which === 'both') && function_exists('bkSet')) {
        bkSet(function (&$c) use ($path, $bold) { $c['card_font'] = $path; $c['card_font_bold'] = $bold; });
        if (function_exists('bcFontBust')) bcFontBust();
    }
    return true;
}

function fntReset($which) {
    if (($which === 'px' || $which === 'both') && function_exists('pxSet')) {
        pxSet(function (&$c) { $c['card']['font'] = ''; $c['card']['font_bold'] = ''; });
        if (function_exists('pxFontCacheBust')) pxFontCacheBust();
    }
    if (($which === 'bk' || $which === 'both') && function_exists('bkSet')) {
        bkSet(function (&$c) { $c['card_font'] = ''; $c['card_font_bold'] = ''; });
        if (function_exists('bcFontBust')) bcFontBust();
    }
}

function fntLabel($which) {
    $p = fntCurrent($which);
    if ($p === '') return '🔴 پیدا نشد';
    $n = basename($p);
    if ($which === 'bk' && function_exists('bcFontFollowsPrices') && bcFontFollowsPrices())
        return $n . ' (🔗 از بخشِ قیمت)';
    return $n;
}


function fntHome($chatId, $msgId = null) {
    $t  = "🔤 <b>فونت‌ها</b>\n\n";
    $t .= "ربات دو جا تصویر می‌سازد و هر دو فونت لازم دارند:\n\n";
    $t .= "💹 <b>کارتِ قیمت</b>\n   <code>" . h(fntLabel('px')) . "</code>\n\n";
    $t .= "🏦 <b>کارتِ بانک</b>\n   <code>" . h(fntLabel('bk')) . "</code>\n\n";
    $t .= "🔗 اگر برایِ بانک فونتِ جدا انتخاب نکنی، خودش همان فونتِ\n";
    $t .= "بخشِ قیمت را برمی‌دارد و هر دو کارت هم‌شکل می‌مانند.";

    $n = count(fntList());
    $rows = [
        [btnCb('💹 فونتِ کارتِ قیمت', 'fnt_px', 'admin')],
        [btnCb('🏦 فونتِ کارتِ بانک', 'fnt_bk', 'admin')],
        [btnCb('🎯 یک فونت برایِ هر دو', 'fnt_both', 'confirm')],
        [btnCb('📤 فرستادنِ فونتِ تازه', 'fnt_up', 'confirm')],
        [btnCb('👀 نمونه‌ی هر دو کارت', 'fnt_prev', 'info')],
        [btnCb('🧹 برگشتِ هر دو به پیش‌فرض', 'fnt_clr', 'reject')],
        [btnCb(UT('back'), 'ag_look', 'nav')],
    ];
    if ($n === 0) {
        $t .= "\n\n🔴 هیچ فایلِ <code>.ttf</code> ای رویِ سرور پیدا نشد";
    }
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function fntPick($chatId, $msgId, $which) {
    $titles = ['px' => '💹 کارتِ قیمت', 'bk' => '🏦 کارتِ بانک', 'both' => '🎯 هر دو کارت'];
    $cur    = $which === 'both' ? '' : fntCurrent($which);
    $list   = fntList();

    $t  = "🔤 <b>فونت برایِ " . ($titles[$which] ?? '') . "</b>\n\n";
    if ($which !== 'both') $t .= 'الان: <code>' . h(fntLabel($which)) . "</code>\n\n";
    $t .= "<b>فا</b> یعنی آن فونت حرفِ فارسی دارد.\n";
    $t .= "فونتِ بدونِ فارسی، کارت را پر از مربعِ خالی می‌کند.";

    $rows = []; $i = 0;
    foreach ($list as $p => $m) {
        $on = ($p === $cur) ? '✅ ' : '';
        $rows[] = [btnCb($on . mb_substr($m['name'], 0, 32) . (!empty($m['fa']) ? ' · فا' : ''),
                         'fntv_' . $which . '_' . $i, 'info')];
        if (++$i >= 20) break;
    }
    if (!$rows) $t .= "\n\n🔴 هیچ فونتی پیدا نشد.";

    $rows[] = [btnCb('📤 فرستادنِ فونتِ تازه', 'fnt_up', 'confirm')];
    $rows[] = [btnCb(UT('back'), 'fnt_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function fntPreview($chatId) {
    $sent = 0;
    if (function_exists('pxSampleCard') && function_exists('pxSendPhoto') && function_exists('pxCardReady') && pxCardReady()) {
        $png = pxSampleCard();
        if ($png !== null) { pxSendPhoto($chatId, $png, '💹 نمونه‌ی کارتِ قیمت'); $sent++; }
    }
    if (function_exists('bcSend') && function_exists('bcReady') && bcReady()) {
        $ok = bcSend($chatId, '🏦 نمونه‌ی کارتِ بانک', [
            'name' => 'محمدرضا حسینی', 'username' => 'numbix', 'uid' => 1,
            'locked' => 1234567, 'level' => 3,
        ]);
        if ($ok) $sent++;
    }
    if ($sent === 0)
        sendMsg(BOT_TOKEN, $chatId, '⚠️ هیچ نمونه‌ای ساخته نشد — GD یا فونت رویِ این هاست نیست.',
            inlineKb([[btnCb('🔤 فونت‌ها', 'fnt_home', 'admin')]]));
}


function fntCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'fnt')) return false;

    if ($data === 'fnt_home') { answerCb(BOT_TOKEN, $cbId); fntHome($chatId, $msgId); return true; }

    foreach (['fnt_px' => 'px', 'fnt_bk' => 'bk', 'fnt_both' => 'both'] as $cb => $which) {
        if ($data === $cb) { answerCb(BOT_TOKEN, $cbId); fntPick($chatId, $msgId, $which); return true; }
    }

    if (preg_match('/^fntv_(px|bk|both)_(\d+)$/', $data, $m)) {
        $paths = array_keys(fntList());
        $p = $paths[(int)$m[2]] ?? '';
        if ($p === '') { answerCb(BOT_TOKEN, $cbId, '❌ پیدا نشد', true); return true; }

        fntApply($m[1], $p);
        $warn = fntHasFa($p) ? '' : ' ⚠️ بدونِ فارسی';
        answerCb(BOT_TOKEN, $cbId, '✅ ' . basename($p) . $warn, !fntHasFa($p));
        if ($m[1] === 'both') fntHome($chatId, $msgId);
        else                  fntPick($chatId, $msgId, $m[1]);
        return true;
    }

    if ($data === 'fnt_clr') {
        fntReset('both');
        answerCb(BOT_TOKEN, $cbId, '🧹 هر دو به پیش‌فرض');
        fntHome($chatId, $msgId);
        return true;
    }

    if ($data === 'fnt_up') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'fnt_font', []);
        sendMsg(BOT_TOKEN, $chatId,
            "🔤 <b>فایلِ فونت</b> · <code>.ttf</code>",
            inlineKb([[btnCb('انصراف', 'fnt_home', 'cancel')]]));
        return true;
    }

    if ($data === 'fnt_prev') {
        answerCb(BOT_TOKEN, $cbId, '🖼 نمونه‌ها…');
        fntPreview($chatId);
        return true;
    }

    return false;
}

function fntState($action, $sd, $msg, $uid, $chatId) {
    if ($action !== 'fnt_font') return false;
    if (!isAdmin($uid)) { clearState($uid); return true; }

    $back = inlineKb([[btnCb('🔤 فونت‌ها', 'fnt_home', 'admin')]]);
    $doc  = $msg['document'] ?? null;
    if (!$doc) { sendMsg(BOT_TOKEN, $chatId, '⚠️ فایلِ <code>.ttf</code> لازم است', $back); return true; }

    $name = (string)($doc['file_name'] ?? 'font.ttf');
    if (!preg_match('/\.ttf$/i', $name)) {
        sendMsg(BOT_TOKEN, $chatId, '⚠️ فقط <code>.ttf</code>.', $back); return true;
    }
    if ((int)($doc['file_size'] ?? 0) > 12 * 1024 * 1024) {
        sendMsg(BOT_TOKEN, $chatId, '⚠️ بیشتر از ۱۲ مگابایت نشود.', $back); return true;
    }

    $r    = tg(BOT_TOKEN, 'getFile', ['file_id' => (string)$doc['file_id']], 15);
    $path = (string)($r['result']['file_path'] ?? '');
    if ($path === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ فایل از تلگرام گرفته نشد.', $back); return true; }

    $ch = curl_init(rtrim(TG_API_BASE, '/') . '/file/bot' . BOT_TOKEN . '/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8]);
    $bytes = monCurl($ch, 'telegram');
    curl_close($ch);
    if (!is_string($bytes) || strlen($bytes) < 1000) {
        sendMsg(BOT_TOKEN, $chatId, '⚠️ دانلودِ فونت نشد.', $back); return true;
    }

    $dir = rtrim(DATA_DIR, '/') . '/fonts';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $dst = $dir . '/' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    if (@file_put_contents($dst, $bytes) === false) {
        sendMsg(BOT_TOKEN, $chatId, '⚠️ نوشتن نشد — پوشه‌ی داده اجازه‌ی نوشتن ندارد.', $back); return true;
    }

    if (function_exists('pxFontCacheBust')) pxFontCacheBust();
    clearState($uid);

    $fa = fntHasFa($dst);
    sendMsg(BOT_TOKEN, $chatId,
        '✅ ذخیره شد: <code>' . h(basename($dst)) . '</code>' .
        ($fa ? '' : "\n\n⚠️ این فونت حرفِ فارسی ندارد"),
        inlineKb([
            [btnCb('💹 کارتِ قیمت', 'fnt_px', 'admin'), btnCb('🏦 کارتِ بانک', 'fnt_bk', 'admin')],
            [btnCb('🎯 هر دو', 'fnt_both', 'confirm')],
            [btnCb('🔤 فونت‌ها', 'fnt_home', 'nav')],
        ]));
    return true;
}
