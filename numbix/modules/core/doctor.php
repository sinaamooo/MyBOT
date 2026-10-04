<?php
defined('NB_ROOT') || exit;

function drFa($n, $d = 0) {
    $s = number_format((float)$n, $d, '.', ',');
    if ($d > 0) $s = rtrim(rtrim($s, '0'), '.');
    return strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', ',' => '٬', '.' => '٫']);
}

function drMs($ms) {
    $ms = (int)$ms;
    return $ms >= 1000 ? drFa($ms / 1000, 1) . ' ثانیه' : drFa($ms) . ' میلی‌ثانیه';
}

function drAgo($t) {
    $s = max(0, time() - (int)$t);
    if ($s < 90) return drFa($s) . ' ثانیه پیش';
    if ($s < 5400) return drFa(round($s / 60)) . ' دقیقه پیش';
    if ($s < 172800) return drFa(round($s / 3600)) . ' ساعت پیش';
    return drFa(round($s / 86400)) . ' روز پیش';
}

function drBotUrl() {
    return function_exists('maBaseUrl') ? (string)maBaseUrl() : '';
}

function drWebhook($force = false) {
    $c = function_exists('maCacheGet') ? maCacheGet('mon_wh', 0) : null;
    if (!$force && is_array($c) && (time() - (int)($c['at'] ?? 0) < 600 || monNoNet())) return $c;
    if (!$force && monNoNet()) return ['ok' => false, 'code' => -1, 'desc' => '', 'info' => [], 'at' => 0];
    $r = tg(BOT_TOKEN, 'getWebhookInfo', [], 8);
    $c = ['ok' => !empty($r['ok']), 'code' => (int)($r['error_code'] ?? 0), 'desc' => monMask((string)($r['description'] ?? '')),
          'info' => is_array($r['result'] ?? null) ? $r['result'] : [], 'at' => time()];
    if (isset($c['info']['url'])) $c['info']['url'] = monMask((string)$c['info']['url']);
    if (isset($c['info']['last_error_message'])) $c['info']['last_error_message'] = monMask((string)$c['info']['last_error_message']);
    if (function_exists('maCachePut')) maCachePut('mon_wh', $c);
    return $c;
}

function drLeak($force = false) {
    $c = function_exists('maCacheGet') ? maCacheGet('mon_leak', 0) : null;
    if (!$force && is_array($c)) return $c;
    if (!$force) return null;
    $base = drBotUrl();
    $c = ['at' => time(), 'open' => [], 'checked' => false];
    if ($base !== '') {
        $dir = basename(rtrim(DATA_DIR, '/'));
        $root = preg_replace('#/[^/]+$#', '', $base);
        $any = false;
        foreach (['config.json', 'users.sqlite', 'php_errors.log'] as $f) {
            [$body, $err] = maHttpRaw($root . '/' . $dir . '/' . $f, 6);
            if ($body === null) { if (str_starts_with((string)$err, 'کد پاسخ')) $any = true; continue; }
            $any = true;
            if (is_string($body) && strlen($body) > 2 && (str_starts_with(ltrim($body), '{') || str_starts_with($body, 'SQLite format 3') || str_contains($body, 'PHP ')))
                $c['open'][] = $dir . '/' . $f;
        }
        [$body, $err] = maHttpRaw($root . '/config.local.php', 6);
        if ($body !== null || str_starts_with((string)$err, 'کد پاسخ')) $any = true;
        if (str_contains((string)$body, 'BOT_TOKEN')) $c['open'][] = 'config.local.php';
        $c['checked'] = $any;
    }
    if (function_exists('maCachePut')) maCachePut('mon_leak', $c);
    return $c;
}

function drAdd(array &$out, $k, $sev, $grp, $icon, $title, $facts, $impact, array $steps, $link = '', $act = '') {
    $out[$k] = ['k' => $k, 'sev' => $sev, 'grp' => $grp, 'icon' => $icon, 'title' => $title, 'facts' => $facts,
                'impact' => $impact, 'steps' => array_values($steps), 'link' => $link, 'act' => $act];
}

function drSvcInfo($k) {
    $m = [
        'telegram'  => ['تلگرام', 'ربات نمی‌تواند پیام بفرستد یا جواب بدهد.', 'api.telegram.org'],
        'numprov'   => [function_exists('numProvName') ? numProvName() : 'فروشنده‌ی شماره', 'خریدِ شماره‌ی مجازی و گرفتنِ کد کار نمی‌کند.', ''],
        'smm_a'     => [function_exists('svPanelName') ? svPanelName('a') : 'پنلِ خدمات ۱', 'سفارش‌های ممبر/فالوورِ این پنل ثبت نمی‌شود (اگر پنلِ دوم وصل باشد، از آن استفاده می‌شود).', ''],
        'smm_b'     => [function_exists('svPanelName') ? svPanelName('b') : 'پنلِ خدمات ۲', 'سفارش‌های ممبر/فالوورِ این پنل ثبت نمی‌شود (اگر پنلِ اول وصل باشد، از آن استفاده می‌شود).', ''],
        'prices'    => ['منابعِ قیمت', 'قیمت‌های لحظه‌ای در گروه‌ها کهنه می‌ماند.', ''],
        'gateway'   => ['درگاهِ پرداخت', 'شارژ با درگاهِ رمزارز انجام نمی‌شود.', ''],
        'translate' => ['سرویسِ ترجمه', 'ترجمه در گروه‌ها کار نمی‌کند.', 'translate.googleapis.com'],
        'web'       => ['وب', 'یک درخواستِ اینترنتیِ جانبی انجام نشد.', ''],
    ];
    return $m[$k] ?? [$k, '', ''];
}

function drSvcWhere($k) {
    if ($k === 'numprov') return ['پنلِ وب ← API و اتصال‌ها ← فروشنده‌ی شماره مجازی', 'admin_panel.php?tab=apis#api-num'];
    if ($k === 'smm_a' || $k === 'smm_b') return ['پنلِ وب ← API و اتصال‌ها ← پنل‌های خدمات', 'admin_panel.php?tab=apis#api-svc'];
    if ($k === 'gateway') return ['پنلِ وب ← تنظیمات ← درگاه پرداخت', 'admin_panel.php?tab=settings&s=gw'];
    if ($k === 'prices') return ['پنلِ ربات ← 💹 قیمت لحظه‌ای', ''];
    return ['پنلِ وب ← API و اتصال‌ها', 'admin_panel.php?tab=apis'];
}

function drCurlWhy($c) {
    return ['c6' => 'نامِ دامنه پیدا نشد (DNS)', 'c7' => 'سرور اتصال را رد کرد', 'c28' => 'زمانِ انتظار تمام شد', 'c35' => 'خطای SSL',
            'c60' => 'گواهیِ SSL نامعتبر', 'c52' => 'سرور جوابِ خالی داد', 'c56' => 'اتصال وسطِ کار قطع شد', 'c5' => 'پروکسی پیدا نشد'][$c] ?? 'خطای اتصال (' . $c . ')';
}

function drFileOf($msg) {
    if (preg_match('~ in (\S+\.php)(?: on line |:)(\d+)~', (string)$msg, $m)) return [basename($m[1]), (int)$m[2], $m[1]];
    return ['', 0, ''];
}

function drPlanetName(array $snap, $k) {
    foreach ($snap['planets'] as $p) if ($p['k'] === $k) return $p['name'];
    return $k;
}

function drActName($a) {
    if ($a === '' || $a === '—') return '—';
    if (str_starts_with($a, 'cb_')) return 'دکمه‌ی ' . substr($a, 3);
    if (str_starts_with($a, 'page_')) return 'صفحه‌ی مینی‌اپِ ' . substr($a, 5);
    if (str_starts_with($a, 'web_')) return 'پنلِ وب (' . substr($a, 4) . ')';
    return ['cmd' => 'دستورها', 'text' => 'پیامِ متنی', 'photo' => 'عکس', 'bg' => 'کارهای پس از پاسخ', 'cron' => 'کران', 'ipn' => 'تاییدِ پرداختِ رمزارز',
            'irpay' => 'بازگشت از درگاهِ ایرانی', 'flood' => 'اسپمِ ردشده'][$a] ?? 'درخواستِ ' . $a;
}

function drDiagnose(array $snap) {
    $F = [];
    $now = time();
    $sys = $snap['system'];
    $bot = drBotUrl();

    if (!defined('WEBHOOK_SECRET') || WEBHOOK_SECRET === '')
        drAdd($F, 'wh_secret', 'crit', 'tg', 'lock', 'رمزِ وبهوک (WEBHOOK_SECRET) تعریف نشده',
            'در config.local.php مقداری برای WEBHOOK_SECRET نیست؛ ربات بدونِ آن هیچ پیامی از تلگرام را قبول نمی‌کند.',
            'ربات کاملاً از کار افتاده است.',
            ['فایلِ config.local.php را در هاست باز کن.',
             "این خط را اضافه کن (به‌جای xxxx یک رشته‌ی تصادفیِ ۴۰ حرفی از حروف و عدد بگذار):\ndefine('WEBHOOK_SECRET', 'xxxx');",
             'ذخیره کن و در همین صفحه «🔧 ثبتِ دوباره‌ی وبهوک» را بزن.'], '', 'webhook');

    if ($bot === '' || !preg_match('#^https://#i', $bot))
        drAdd($F, 'base_url', 'crit', 'cfg', 'link', 'آدرسِ عمومیِ ربات (https) ثبت نشده',
            $bot === '' ? 'ربات نمی‌داند آدرسِ اینترنتیِ خودش چیست.' : 'آدرسِ ثبت‌شده https نیست: ' . $bot,
            'دکمه‌ی مینی‌اپ‌ها نشان داده نمی‌شود و درگاه‌های پرداخت برنمی‌گردند.',
            ['پنلِ وب ← API و اتصال‌ها ← ۱. آدرسِ عمومیِ ربات را باز کن.',
             'آدرسِ کاملِ فایلِ ربات را با https بنویس؛ مثلاً https://site.com/numbix/bot_master_membership.php',
             'ذخیره کن، بعد «🔧 ثبتِ دوباره‌ی وبهوک» را بزن.'], 'admin_panel.php?tab=apis#api-base');

    $wh = drWebhook();
    $tgCodes = [];
    foreach ($snap['services'] as $x) if ($x['k'] === 'telegram') $tgCodes = (array)$x['codes'];
    $tgNet = 0; $tgNetWhy = '';
    foreach ($tgCodes as $c => $n) if ($c[0] === 'c') { $tgNet += $n; $tgNetWhy = drCurlWhy($c); }
    if (!$wh['ok'] && in_array((int)$wh['code'], [401, 404], true) || ($tgCodes['h401'] ?? 0) + ($tgCodes['h404'] ?? 0) > 3)
        drAdd($F, 'tg_token', 'crit', 'tg', 'key', 'توکنِ ربات باطل یا اشتباه است',
            'تلگرام توکن را نمی‌شناسد' . ($wh['desc'] !== '' ? ' (' . $wh['desc'] . ')' : '') . '.',
            'ربات هیچ پیامی نمی‌فرستد و نمی‌گیرد.',
            ['در تلگرام به @BotFather برو ← /mybots ← ربات ← API Token.',
             'اگر قبلاً «Revoke» زده‌ای، توکنِ تازه را کپی کن.',
             'در هاست فایلِ config.local.php را باز کن و مقدارِ BOT_TOKEN را با توکنِ تازه عوض کن.',
             'در همین صفحه «🔧 ثبتِ دوباره‌ی وبهوک» را بزن.'], '', 'webhook');
    elseif (!$wh['ok'] && (int)$wh['code'] === 0 || $tgNet > 2)
        drAdd($F, 'tg_net', 'crit', 'tg', 'plug', 'هاست به سرورِ تلگرام وصل نمی‌شود',
            ($tgNet ? drFa($tgNet) . ' تلاشِ ناموفق در ۱۵ دقیقه — ' . $tgNetWhy : 'getWebhookInfo جوابی نگرفت') . '.',
            'ربات نمی‌تواند جواب بدهد؛ پیام‌ها در صفِ تلگرام می‌مانند.',
            ['اگر قطعیِ کوتاه بوده، خودش درست می‌شود؛ ۵ دقیقه بعد «🔁 بررسیِ دوباره» را بزن.',
             'اگر هاست در ایران است، api.telegram.org از آن‌جا بسته است؛ ربات باید روی هاستِ خارج از ایران باشد.',
             'از پشتیبانیِ هاست بپرس اتصالِ خروجی به api.telegram.org روی پورتِ ۴۴۳ باز است.'], '', 'recheck');

    if ($wh['ok']) {
        $info = $wh['info'];
        $url = (string)($info['url'] ?? '');
        $pend = (int)($info['pending_update_count'] ?? 0);
        if ($url === '' && defined('WEBHOOK_SECRET') && WEBHOOK_SECRET !== '')
            drAdd($F, 'wh_none', 'crit', 'tg', 'webhook', 'وبهوک ثبت نشده — ربات پیامی نمی‌گیرد',
                'در تلگرام هیچ آدرسی برای فرستادنِ پیام‌ها به ربات ثبت نیست.',
                'کاربرها /start می‌زنند و ربات جواب نمی‌دهد.',
                ['دکمه‌ی «🔧 ثبتِ دوباره‌ی وبهوک» را همین‌جا بزن.',
                 'اگر خطا داد، آدرسِ عمومی را در پنلِ وب ← API و اتصال‌ها درست کن (باید https باشد) و دوباره بزن.'], '', 'webhook');
        elseif ($url !== '' && $bot !== '' && strtok($url, '?') !== strtok($bot, '?'))
            drAdd($F, 'wh_url', 'warn', 'tg', 'webhook', 'وبهوک روی آدرسِ دیگری ثبت شده',
                'ثبت‌شده در تلگرام: ' . $url . "\nآدرسِ عمومیِ ربات: " . $bot,
                'اگر آدرسِ قدیمی دیگر کار نکند، ربات پیامی نمی‌گیرد؛ اگر کار می‌کند، فقط مینی‌اپ و ربات روی دو آدرسِ متفاوت‌اند.',
                ['اگر آدرسِ عمومی درست است، «🔧 ثبتِ دوباره‌ی وبهوک» را بزن تا تلگرام هم همان را بشناسد.',
                 'اگر آدرسِ تلگرام درست است، آدرسِ عمومی را در پنلِ وب ← API و اتصال‌ها اصلاح کن.'], 'admin_panel.php?tab=apis#api-base', 'webhook');
        if ($pend > 100)
            drAdd($F, 'wh_backlog', $pend > 500 ? 'crit' : 'warn', 'tg', 'inbox', 'صفِ پیام‌های تلگرام عقب افتاده: ' . drFa($pend) . ' پیامِ منتظر',
                'تلگرام ' . drFa($pend) . ' پیام برای ربات نگه داشته که هنوز تحویل نشده.',
                'کاربرها جواب را با تأخیر می‌گیرند.',
                ['بخش‌های زرد و قرمزِ کهکشان را ببین؛ معمولاً یک بخشِ کند علتِ عقب افتادن است (پایین‌تر جدا گفته شده).',
                 'اگر همه سبزند، سرورِ هاست زیرِ بار است: سی‌پنل ← Resource Usage را ببین یا منابعِ بیشتر بگیر.',
                 'اگر این پیام‌ها قدیمی و بی‌اهمیت‌اند، «🔧 ثبتِ دوباره‌ی وبهوک» صف را خالی می‌کند.'], '', 'webhook');
        $le = (int)($info['last_error_date'] ?? 0);
        $lm = (string)($info['last_error_message'] ?? '');
        if ($le > 0 && $now - $le < 1800 && $lm !== '') {
            $fact = 'آخرین خطای تلگرام ' . drAgo($le) . ': ' . $lm;
            if (preg_match('/SSL|certificate/i', $lm))
                drAdd($F, 'wh_ssl', 'crit', 'tg', 'lock', 'گواهیِ SSLِ دامنه مشکل دارد؛ تلگرام نمی‌تواند پیام برساند', $fact,
                    'پیام‌های کاربرها به ربات نمی‌رسد.',
                    ['سی‌پنل ← SSL/TLS Status ← برای دامنه‌ی ربات «Run AutoSSL» را بزن (یا گواهیِ Let\'s Encrypt را تمدید کن).',
                     'آدرسِ ربات را در مرورگر باز کن؛ قفلِ کنارِ آدرس باید سالم باشد.',
                     '«🔧 ثبتِ دوباره‌ی وبهوک» را بزن.'], '', 'webhook');
            elseif (preg_match('/Wrong response from the webhook: (\d{3})/i', $lm, $m)) {
                $code = (int)$m[1];
                if ($code === 401 || $code === 403)
                    drAdd($F, 'wh_401', 'crit', 'tg', 'lock', 'رمزِ وبهوک با تلگرام نمی‌خواند (' . drFa($code) . ')', $fact,
                        'تلگرام پیام می‌فرستد ولی ربات آن را رد می‌کند.',
                        ['«🔧 ثبتِ دوباره‌ی وبهوک» را بزن تا رمزِ فعلیِ config.local.php به تلگرام داده شود.'], '', 'webhook');
                elseif ($code === 404)
                    drAdd($F, 'wh_404', 'crit', 'tg', 'webhook', 'فایلِ ربات در آدرسِ وبهوک پیدا نمی‌شود (۴۰۴)', $fact . "\nآدرسِ وبهوک: " . ($url ?: '—'),
                        'پیام‌های کاربرها به ربات نمی‌رسد.',
                        ['فایلِ bot_master_membership.php باید دقیقاً در همین آدرس روی هاست باشد.',
                         'اگر پوشه را جابه‌جا کرده‌ای، آدرسِ عمومی را در پنلِ وب درست کن و «🔧 ثبتِ دوباره‌ی وبهوک» را بزن.'], 'admin_panel.php?tab=apis#api-base', 'webhook');
                elseif ($code >= 500)
                    drAdd($F, 'wh_5xx', 'crit', 'tg', 'bug', 'ربات به تلگرام خطای ' . drFa($code) . ' داد', $fact,
                        'بعضی پیام‌ها بی‌جواب می‌مانند.',
                        ['بخشِ «باگ‌ها و لاگ‌ها» را ببین؛ آخرین خطای جدی معمولاً همین علت است (اگر بود، جدا در همین فهرست آمده).',
                         'اگر تازه به‌روزرسانی کرده‌ای، مطمئن شو پوشه‌ی modules کامل آپلود شده.',
                         'در سی‌پنل ← Errors را هم نگاه کن.']);
                else
                    drAdd($F, 'wh_err', 'warn', 'tg', 'webhook', 'تلگرام موقعِ رساندنِ پیام جوابِ نادرست گرفت (' . drFa($code) . ')', $fact,
                        'بعضی پیام‌ها ممکن است دوباره فرستاده شوند یا دیر برسند.', ['اگر تکرار شد، «🔧 ثبتِ دوباره‌ی وبهوک» را بزن.'], '', 'webhook');
            } elseif (preg_match('/timed? ?out|Connection|resolve|refused|reset/i', $lm))
                drAdd($F, 'wh_conn', 'crit', 'tg', 'plug', 'تلگرام به سرورِ ربات نمی‌رسد یا دیر جواب می‌گیرد', $fact,
                    'پیام‌های کاربرها با تأخیر یا اصلاً نمی‌رسد.',
                    ['آدرسِ ربات را از بیرون (با اینترنتِ گوشی) باز کن؛ باید پیامِ «ربات سالم است» بیاید.',
                     'اگر سایت کند است، بخش‌های کندِ کهکشان را ببین.',
                     'از پشتیبانیِ هاست بپرس فایروال یا Cloudflare جلوی آی‌پی‌های تلگرام (149.154.160.0/20 و 91.108.4.0/22) را نگرفته باشد.']);
            else
                drAdd($F, 'wh_err', 'warn', 'tg', 'webhook', 'تلگرام موقعِ رساندنِ پیام خطا گرفت', $fact,
                    'بعضی پیام‌ها ممکن است دیر برسند.', ['اگر بیش از چند دقیقه تکرار شد، «🔧 ثبتِ دوباره‌ی وبهوک» را بزن.'], '', 'webhook');
        }
    }
    if (($tgCodes['h429'] ?? 0) > 5)
        drAdd($F, 'tg_flood', 'warn', 'tg', 'gauge', 'تلگرام ارسال‌ها را موقتاً محدود کرده (۴۲۹)',
            drFa($tgCodes['h429']) . ' بار در ۱۵ دقیقه‌ی اخیر تلگرام گفت «آهسته‌تر».',
            'بعضی پیام‌ها چند ثانیه دیرتر می‌رسند.',
            ['اگر پیامِ همگانی در جریان است، طبیعی است؛ ربات خودش صبر می‌کند و ادامه می‌دهد.',
             'اگر نه، احتمالاً یک گروه خیلی شلوغ است؛ سپرِ گروه‌ها خودکار جلویش را می‌گیرد.',
             'فقط اگر بیش از یک ساعت ادامه داشت نیاز به بررسی دارد.']);

    $logs = (array)$snap['logs'];
    $seen = [];
    foreach ($logs as $l) {
        if ((int)$l['last'] > 0 && $now - (int)$l['last'] > 3600) continue;
        $msg = (string)$l['msg'];
        [$file, $line] = drFileOf($msg);
        $where = $file !== '' ? $file . ' (خط ' . drFa($line) . ')' : '—';
        $fact = drFa($l['n']) . ' بار، آخرین بار ' . drAgo($l['last']) . "\n" . $msg;
        if (preg_match('/Allowed memory size/i', $msg)) {
            drAdd($F, 'php_mem', 'crit', 'php', 'cpu', 'حافظه‌ی PHP کم آمد', $fact, 'همان درخواست نیمه‌کاره ماند و کاربر جواب نگرفت.',
                ['سی‌پنل ← Select PHP Version ← Options ← memory_limit را روی 256M (یا بیشتر) بگذار.',
                 'اگر این گزینه نیست، از پشتیبانیِ هاست بخواه memory_limit را ۲۵۶ مگابایت کنند.']);
        } elseif (preg_match('/Maximum execution time/i', $msg)) {
            drAdd($F, 'php_time', 'warn', 'php', 'clock', 'یک کار بیشتر از زمانِ مجازِ PHP طول کشید', $fact . "\nمحل: " . $where,
                'همان کار نیمه‌کاره ماند (اگر کرون یا پیامِ همگانی بوده، دورِ بعد ادامه پیدا می‌کند).',
                ['اگر فقط گاهی پیش می‌آید، نیازی به کار نیست.',
                 'اگر مدام تکرار شد: سی‌پنل ← Select PHP Version ← Options ← max_execution_time را ۶۰ یا بیشتر کن.']);
        } elseif (preg_match('/Parse error|syntax error/i', $msg)) {
            drAdd($F, 'php_parse_' . md5($file), 'crit', 'php', 'bug', 'فایلِ ' . ($file ?: 'ربات') . ' ناقص یا خراب آپلود شده', $fact,
                'بخشی که از این فایل استفاده می‌کند کار نمی‌کند.',
                ['همین فایل را از بسته‌ی همین نسخه دوباره آپلود کن و روی قبلی بریز.',
                 'فایل را با ویرایشگرِ سی‌پنل باز و ذخیره نکن؛ مستقیم آپلود کن.']);
        } elseif (preg_match('/Call to undefined function|Class "?[\w\\\\]+"? not found|Failed opening required|failed to open stream: No such file/i', $msg)) {
            drAdd($F, 'php_missing', 'crit', 'php', 'layers', 'یک فایلِ ربات روی هاست نیست یا از نسخه‌ی قدیمی مانده', $fact,
                'بخشی از ربات کار نمی‌کند.',
                ['کلِ پوشه‌ی modules و پوشه‌ی assets و فایل‌های کنارِ bot_master_membership.php را از بسته‌ی همین نسخه دوباره آپلود کن.',
                 'به پوشه‌ی data_master دست نزن.']);
        } elseif (preg_match('/database is locked|disk I\/O error|SQLITE_BUSY/i', $msg)) {
            drAdd($F, 'db_lock', 'warn', 'disk', 'disk', 'دیتابیس چند لحظه قفل ماند', $fact,
                'همان درخواست‌ها چند ثانیه دیرتر جواب گرفتند.',
                ['معمولاً از کندیِ دیسکِ هاست است؛ اگر هر روز تکرار شد، هاست با دیسکِ NVMe/SSD بگیر.',
                 'تست: پنلِ ربات ← 🩺 چکاپِ بخش‌ها ← تستِ دیسک.']);
        } elseif (preg_match('/Permission denied|Read-only file system/i', $msg)) {
            drAdd($F, 'disk_perm', 'crit', 'disk', 'disk', 'ربات نمی‌تواند در پوشه‌ی داده بنویسد', $fact,
                'سفارش‌ها، موجودی‌ها و تنظیمات ذخیره نمی‌شوند.',
                ['سی‌پنل ← File Manager ← روی پوشه‌ی data_master راست‌کلیک ← Change Permissions ← 755 (اگر نشد 775).',
                 'فایل‌های داخلش باید 644 باشند.']);
        } elseif (preg_match('/No space left/i', $msg)) {
            drAdd($F, 'disk_full', 'crit', 'disk', 'disk', 'دیسکِ هاست پر شده', $fact, 'هیچ داده‌ای ذخیره نمی‌شود.',
                ['سی‌پنل ← Disk Usage را باز کن و پوشه‌های بزرگ را پیدا کن.', 'بکاپ‌های قدیمی و فایلِ error_log کنارِ اسکریپت‌ها را پاک کن.', 'به data_master دست نزن.']);
        } elseif ($l['lvl'] === 'fatal') {
            $sig = md5(preg_replace('/\d+/', '#', $msg));
            if (isset($seen[$sig])) continue;
            $seen[$sig] = 1;
            drAdd($F, 'php_fatal_' . substr($sig, 0, 10), 'crit', 'php', 'bug', 'خطای جدیِ PHP در ' . ($file ?: 'ربات'), $fact . "\nمحل: " . $where,
                'همان کاری که خطا داد نیمه‌کاره ماند.',
                ['اگر بعد از به‌روزرسانی شروع شد، فایلِ ' . ($file ?: 'مربوط') . ' را از بسته‌ی همین نسخه دوباره آپلود کن.',
                 'اگر ادامه داشت، «📋 کپیِ گزارش» را بزن و متن را برای پشتیبانِ فنی بفرست؛ اطلاعاتِ محرمانه داخلش پوشانده شده.']);
        }
    }
    $warnN = 0; $warnTop = '';
    foreach ($logs as $l) if ($l['lvl'] === 'warn' && (int)$l['last'] > 0 && $now - (int)$l['last'] < 3600) { $warnN += (int)$l['n']; if ($warnTop === '') $warnTop = (string)$l['msg']; }
    if ($warnN >= 30)
        drAdd($F, 'php_warn', 'info', 'php', 'bug', drFa($warnN) . ' هشدارِ PHP در یک ساعتِ اخیر', 'پرتکرارترین: ' . $warnTop,
            'هشدارها ربات را متوقف نمی‌کنند.', ['اگر بخشی هم کند یا قرمز است، احتمالاً به همین مربوط است؛ «📋 کپیِ گزارش» را برای پشتیبانِ فنی بفرست.']);

    $slowSvc = [];
    foreach ($snap['services'] as $x) if (in_array($x['st'], ['slow', 'down'], true)) $slowSvc[] = $x['name'];
    $load = $sys['load'][0] ?? null;
    $cause = $slowSvc ? 'در همین زمان «' . implode('، ', $slowSvc) . '» کند یا خراب بوده؛ به احتمالِ زیاد علت همین است.'
           : ($load !== null && $load > 4 ? 'بارِ سرور بالاست (' . drFa($load, 2) . ')؛ سرورِ هاست زیرِ فشار است.' : 'علتِ بیرونی دیده نشد.');
    foreach ($snap['planets'] as $p) {
        if (in_array($p['k'], ['shield', 'other'], true) || $p['n15'] <= 0) continue;
        $act = $p['act'] !== '' ? "\nکندترین کار: " . drActName($p['act']) . ' (' . drMs($p['act_p95']) . ')' : '';
        if ($p['hang'] > 0)
            drAdd($F, 'sec_hang_' . $p['k'], 'crit', 'sec', 'clock', 'بخشِ «' . $p['name'] . '» هنگ کرد',
                drFa($p['hang']) . ' درخواست در ۱۵ دقیقه‌ی اخیر تا آخرِ زمانِ مجازِ PHP طول کشید.' . $act . "\n" . $cause,
                'کاربرهای این بخش جوابی نگرفتند.',
                ['اگر علت سرویسِ بیرونی است، معمولاً خودش درست می‌شود؛ ۱۵ دقیقه بعد دوباره نگاه کن.',
                 'اگر تکرار شد: سی‌پنل ← Select PHP Version ← Options ← max_execution_time را ۶۰ کن.',
                 'اگر بارِ سرور بالاست، از هاست منابعِ بیشتر بگیر یا OPcache را روشن کن.']);
        elseif ($p['fatal'] > 0)
            drAdd($F, 'sec_fatal_' . $p['k'], 'crit', 'sec', 'bug', 'بخشِ «' . $p['name'] . '» خطای جدی داد',
                drFa($p['fatal']) . ' درخواست در ۱۵ دقیقه‌ی اخیر با خطای جدی تمام شد.' . $act,
                'کاربرهای این بخش جوابی نگرفتند.',
                ['متنِ دقیقِ خطا در همین فهرست (خطای جدیِ PHP) یا در «باگ‌ها و لاگ‌ها» آمده.',
                 'اگر بعد از به‌روزرسانی شروع شد، فایل‌های همان نسخه را کامل دوباره آپلود کن.']);
        elseif ($p['st'] === 'slow' && $p['k'] !== 'cron')
            drAdd($F, 'sec_slow_' . $p['k'], 'warn', 'sec', 'gauge', 'بخشِ «' . $p['name'] . '» کند شده',
                '۹۵٪ درخواست‌های ۱۵ دقیقه‌ی اخیر تا ' . drMs($p['p95']) . ' طول کشید؛ حالتِ معمولِ ۲۴ ساعت: ' . drMs($p['base']) . '. بیشترین: ' . drMs($p['tmax']) . '.' . $act . "\n" . $cause,
                'کاربرهای این بخش دیرتر جواب می‌گیرند.',
                ['اگر علت سرویسِ بیرونی است، معمولاً خودش برطرف می‌شود.',
                 'اگر بارِ سرور بالاست یا OPcache خاموش است، اول همان را درست کن (اگر بود، جدا در همین فهرست آمده).',
                 'کندترین کارهای این بخش در جدولِ «کندترین کارها» آمده.']);
        elseif ($p['n15'] >= 10 && $p['err'] / max(1, $p['n15']) > 0.2)
            drAdd($F, 'sec_err_' . $p['k'], 'warn', 'sec', 'bug', 'در بخشِ «' . $p['name'] . '» خطا زیاد است',
                drFa($p['err']) . ' از ' . drFa($p['n15']) . ' درخواست در ۱۵ دقیقه‌ی اخیر هشدار یا خطا داشت.' . $act,
                'ممکن است بعضی کاربرها نتیجه‌ی ناقص ببینند.', ['متنِ هشدارها در «باگ‌ها و لاگ‌ها» آمده.']);
    }

    foreach ($snap['services'] as $x) {
        if ($x['k'] === 'telegram' || $x['k'] === 'web') continue;
        [$name, $impact, $host] = drSvcInfo($x['k']);
        [$whereTxt, $whereLink] = drSvcWhere($x['k']);
        $codes = (array)$x['codes'];
        $net = 0; $netWhy = ''; $auth = 0; $s5 = 0; $rl = 0;
        foreach ($codes as $c => $n) {
            if ($c[0] === 'c') { $net += $n; $netWhy = drCurlWhy($c); }
            elseif ($c === 'h401' || $c === 'h403') $auth += $n;
            elseif ($c === 'h429') $rl += $n;
            elseif ($c[0] === 'h' && (int)substr($c, 1) >= 500) $s5 += $n;
        }
        if ($auth > 0)
            drAdd($F, 'x_key_' . $x['k'], 'crit', 'ext', 'key', 'کلیدِ «' . $name . '» پذیرفته نمی‌شود',
                drFa($auth) . ' درخواست در ۱۵ دقیقه‌ی اخیر با «دسترسی ممنوع» (۴۰۱/۴۰۳) رد شد.', $impact,
                ['به سایتِ ' . $name . ' برو و کلیدِ API تازه بساز (یا مطمئن شو حساب مسدود نشده).',
                 $whereTxt . ' ← کلیدِ تازه را بگذار و ذخیره کن.', 'بعد «🧪 تست»ِ همان بخش را بزن.'], $whereLink);
        elseif ($net > 2)
            drAdd($F, 'x_net_' . $x['k'], $x['k'] === 'numprov' || $x['k'] === 'gateway' ? 'crit' : 'warn', 'ext', 'plug', 'هاست به «' . $name . '» وصل نمی‌شود',
                drFa($net) . ' تلاشِ ناموفق در ۱۵ دقیقه‌ی اخیر — ' . $netWhy . '.', $impact,
                ['اگر فقط چند دقیقه بوده، معمولاً قطعیِ موقتِ خودِ سرویس است.',
                 'اگر ادامه داشت، از پشتیبانیِ هاست بپرس اتصالِ خروجی به ' . ($host ?: 'این سرویس') . ' باز است.',
                 'اگر هاست در ایران است، بیشترِ این سرویس‌ها از آن‌جا در دسترس نیستند.']);
        elseif ($s5 > 2)
            drAdd($F, 'x_5xx_' . $x['k'], 'warn', 'ext', 'server', '«' . $name . '» خطای سرور می‌دهد (مشکل از طرفِ خودشان)',
                drFa($s5) . ' جوابِ ۵xx در ۱۵ دقیقه‌ی اخیر.', $impact,
                ['کاری از دستِ ربات برنمی‌آید؛ معمولاً تا چند دقیقه یا چند ساعت درست می‌شود.', 'اگر طولانی شد، به پشتیبانیِ ' . $name . ' پیام بده.']);
        elseif ($rl > 2)
            drAdd($F, 'x_429_' . $x['k'], 'warn', 'ext', 'gauge', '«' . $name . '» درخواست‌های ربات را محدود کرده',
                drFa($rl) . ' بار «Too Many Requests» در ۱۵ دقیقه‌ی اخیر.', $impact,
                ['معمولاً بعد از چند دقیقه خودش آزاد می‌شود.', 'اگر تکرار شد، از ' . $name . ' بخواه سقفِ درخواستِ حسابت را بالا ببرد.']);
        elseif ($x['st'] === 'slow' && $x['n15'] >= 3)
            drAdd($F, 'x_slow_' . $x['k'], 'warn', 'ext', 'gauge', '«' . $name . '» کند جواب می‌دهد',
                '۹۵٪ جواب‌ها تا ' . drMs($x['p95']) . '؛ حالتِ معمول: ' . drMs($x['base']) . '.', 'بخش‌هایی که به آن وابسته‌اند کندتر می‌شوند.',
                ['معمولاً از طرفِ خودِ سرویس است و برطرف می‌شود.', 'اگر هر روز تکرار شد، با پشتیبانیِ ' . $name . ' یا هاست صحبت کن.']);
    }

    foreach ($snap['balances'] as $b) {
        [$name, $impact] = drSvcInfo($b['k']);
        $name = $b['name'] ?: $name;
        $cur = trim($b['cur'] . '');
        $charge = $b['k'] === 'numprov'
            ? (function_exists('numProv') && numProv() === 'numberland' ? ['به سایتِ نامبرلند برو و حساب را شارژ کن.'] : ['به 5sim.net برو ← Top up balance ← حساب را شارژ کن.'])
            : ['به سایتِ پنلِ «' . $name . '» برو ← Add funds ← حساب را شارژ کن.'];
        $charge[] = 'در همین صفحه «🔄 موجودی‌ها» را بزن تا وضعیت تازه شود.';
        if ($b['err'] === '' && $b['bal'] <= 0)
            drAdd($F, 'bal_' . $b['k'], 'crit', 'money', 'coins', 'موجودیِ «' . $name . '» تمام شده', 'موجودی: ' . drFa($b['bal'], 2) . ' ' . $cur . ' (به‌روز: ' . drAgo($b['at']) . ')', $impact, $charge);
        elseif ($b['funds60'] > 0)
            drAdd($F, 'funds_' . $b['k'], 'crit', 'money', 'coins', '«' . $name . '» سفارش را به‌خاطرِ کمبودِ موجودی رد کرد',
                drFa($b['funds60']) . ' بار در یک ساعتِ اخیر. موجودیِ خوانده‌شده: ' . drFa($b['bal'], 2) . ' ' . $cur, $impact, $charge);
        elseif ($b['funds24'] > 0)
            drAdd($F, 'funds24_' . $b['k'], 'warn', 'money', 'coins', 'موجودیِ «' . $name . '» امروز کم آمده بود',
                drFa($b['funds24']) . ' سفارش در ۲۴ ساعتِ اخیر به‌خاطرِ کمبودِ موجودی رد شد. موجودیِ فعلی: ' . drFa($b['bal'], 2) . ' ' . $cur,
                'اگر دوباره کم بیاید، مشتری‌ها خطای خرید می‌بینند.', $charge);
        elseif ($b['err'] !== '')
            drAdd($F, 'balerr_' . $b['k'], 'warn', 'money', 'coins', 'موجودیِ «' . $name . '» خوانده نشد', 'جوابِ فروشنده: ' . $b['err'],
                'اگر مشکل از کلید باشد، خرید هم انجام نمی‌شود.', [drSvcWhere($b['k'])[0] . ' ← «🧪 تست» را بزن و نتیجه را ببین.'], drSvcWhere($b['k'])[1]);
    }

    if (!empty($sys['disk_total'])) {
        $free = (int)$sys['disk_free']; $used = ($sys['disk_total'] - $free) / $sys['disk_total'] * 100;
        $sev = ($free < 300 * 1048576 || $used >= 97) ? 'crit' : (($free < 1536 * 1048576 || $used >= 90) ? 'warn' : '');
        if ($sev !== '')
            drAdd($F, 'disk_low', $sev, 'disk', 'disk', 'فضای دیسکِ هاست کم است', drFa($used) . '٪ پر — فقط ' . drFa($free / 1073741824, 2) . ' گیگابایت آزاد مانده.',
                'اگر پر شود، هیچ سفارش و موجودی‌ای ذخیره نمی‌شود.',
                ['سی‌پنل ← Disk Usage را باز کن و پوشه‌های بزرگ را پیدا کن (معمولاً بکاپ‌های قدیمی یا فایلِ error_log).',
                 'آن‌ها را پاک کن؛ به data_master دست نزن (ربات سفارش‌های قدیمی را خودش بایگانی می‌کند).']);
    }
    if (empty($sys['writable']))
        drAdd($F, 'disk_perm', 'crit', 'disk', 'disk', 'ربات نمی‌تواند در پوشه‌ی داده بنویسد', 'پوشه‌ی data_master قابلِ نوشتن نیست.',
            'سفارش‌ها، موجودی‌ها و تنظیمات ذخیره نمی‌شوند.',
            ['سی‌پنل ← File Manager ← روی data_master راست‌کلیک ← Change Permissions ← 755 (اگر نشد 775).']);

    if (empty($sys['opcache']['on']))
        drAdd($F, 'opcache', 'warn', 'php', 'cpu', 'OPcache خاموش است — ربات چند برابر کندتر از توانش کار می‌کند',
            'افزونه‌ی OPcache در PHPِ هاست روشن نیست.', 'زیرِ بارِ زیاد، جواب‌ها دیرتر می‌رسد.',
            ['سی‌پنل ← Select PHP Version ← Extensions ← تیکِ opcache را بزن ← Save.', 'اگر گزینه نیست، از پشتیبانیِ هاست بخواه OPcache را روشن کنند.']);
    $ml = (string)$sys['mem_limit'];
    $mlb = $ml === '-1' ? PHP_INT_MAX : (int)$ml * (['g' => 1073741824, 'm' => 1048576, 'k' => 1024][strtolower(substr($ml, -1))] ?? 1);
    if ($mlb < 128 * 1048576)
        drAdd($F, 'php_memlimit', 'warn', 'php', 'cpu', 'حافظه‌ی PHP کم تنظیم شده (' . $ml . ')', 'memory_limit کمتر از ۱۲۸ مگابایت است.',
            'ساختِ کارت‌ها و کارهای سنگین ممکن است نیمه‌کاره بماند.', ['سی‌پنل ← Select PHP Version ← Options ← memory_limit = 256M.']);

    $cronAt = (int)@filemtime(DATA_DIR . '/.cron_at');
    $active = 0;
    foreach ($snap['planets'] as $p) $active += $p['n24'];
    if ($active > 20 && ($cronAt === 0 || $now - $cronAt > 1200)) {
        $cmd = $bot !== '' ? 'curl -s "' . $bot . '?cron=‹CRON_KEY›" > /dev/null' : '';
        drAdd($F, $cronAt ? 'cron_stale' : 'cron_none', 'warn', 'cfg', 'clock',
            $cronAt ? 'کرون‌جاب ' . drAgo($cronAt) . ' آخرین بار اجرا شد' : 'کرون‌جاب تنظیم نشده',
            $cronAt ? 'کرون باید هر دقیقه اجرا شود.' : 'هیچ‌وقت اجرای کرون دیده نشده.',
            'وقتی کسی به ربات پیام نمی‌دهد، کارهای زمان‌دار عقب می‌افتند: پیگیریِ کدِ شماره، لغو و برگشتِ پول، سفارش‌های خدمات، چالشِ روزانه و پیامِ همگانی.',
            array_values(array_filter(['سی‌پنل ← Cron Jobs ← Common Settings: Once Per Minute (* * * * *).',
             $cmd !== '' ? "در Command این را بگذار و به‌جای ‹CRON_KEY› مقدارِ CRON_KEY را از config.local.php بنویس:\n" . $cmd : 'در Command آدرسِ ربات را با ?cron= و مقدارِ CRON_KEYِ config.local.php با curl صدا بزن.',
             'Add New Cron Job را بزن؛ بعد از یک دقیقه این مورد خودش برطرف می‌شود.'])));
    }

    foreach (['setup.php', 'health.php'] as $f)
        if (is_file(NB_ROOT . '/' . $f)) {
            drAdd($F, 'setup_files', 'warn', 'safe', 'shield', 'فایل‌های راه‌اندازی هنوز روی هاست است',
                'setup.php یا health.php کنارِ فایل‌های ربات مانده.', 'هر کس آدرسشان را حدس بزند، صفحه‌ی ورود یا گزارشِ هاست را می‌بیند.',
                ['در سی‌پنل ← File Manager فایل‌های setup.php و health.php را (کنارِ bot_master_membership.php) پاک کن.']);
            break;
        }
    $pass = defined('ADMIN_PANEL_PASS') ? (string)ADMIN_PANEL_PASS : (string)getenv('ADMIN_PANEL_PASS');
    if ($pass !== '' && (strlen($pass) < 12 || !preg_match('/[^A-Za-z0-9]/', $pass)))
        drAdd($F, 'weak_pass', 'warn', 'safe', 'lock', 'رمزِ پنلِ وب ضعیف است', 'رمز کوتاه‌تر از ۱۲ نویسه است یا علامت ندارد.',
            'از پنل می‌شود به موجودیِ کاربرها و کلیدها رسید.',
            ["در config.local.php مقدارِ ADMIN_PANEL_PASS را به رمزی دست‌کم ۱۲ نویسه با حرف، عدد و علامت (مثلِ - یا #) عوض کن."]);
    $leak = drLeak();
    if (is_array($leak) && !empty($leak['open']))
        drAdd($F, 'leak', 'crit', 'safe', 'shield', 'فایل‌های محرمانه از بیرون خوانده می‌شوند', 'از اینترنت باز شد: ' . implode('، ', $leak['open']) . ' (بررسی: ' . drAgo($leak['at']) . ')',
            'هر کس آدرس را بداند، توکن، کلیدها یا اطلاعاتِ کاربرها را می‌بیند.',
            ['اگر سرور nginx است، به کانفیگ اضافه کن:' . "\nlocation ~ /" . basename(rtrim(DATA_DIR, '/')) . '/ { deny all; return 404; }',
             'اگر آپاچی است و باز هم باز مانده، از پشتیبانیِ هاست بخواه AllowOverride را روشن کنند.',
             'راهِ مطمئن‌تر: پوشه‌ی داده را بیرون از public_html ببر و در config.local.php مسیرش را با DATA_DIR بده.',
             'بعد «🔁 بررسیِ دوباره» را بزن.'], '', 'recheck');

    $sh = $snap['shield'];
    if ($sh['b15'] > 300)
        drAdd($F, 'attack', 'info', 'safe', 'shield', 'حمله‌ی درخواستِ جعلی در جریان است — همه رد شدند',
            drFa($sh['b15']) . ' درخواستِ بدونِ امضای تلگرام در ۱۵ دقیقه‌ی اخیر همان اول رد شد.',
            'اثری روی کاربرها ندارد؛ فقط مصرفِ هاست کمی بالا می‌رود.',
            ['کاری لازم نیست؛ سپر خودکار رد می‌کند.',
             'اگر هاست کند شد، دامنه را پشتِ Cloudflare ببر و «Under Attack» را روشن کن (آدرسِ bot_master_membership.php را از آن مستثنا کن).']);
    foreach ($snap['planets'] as $p)
        if ($p['k'] === 'shield' && $p['n15'] > 60)
            drAdd($F, 'flood', 'info', 'safe', 'shield', drFa($p['n15']) . ' پیامِ اسپم از کاربرها نادیده گرفته شد',
                'کاربرهایی که بیش از ۳۰ پیام در ۱۵ ثانیه فرستادند موقتاً بی‌جواب ماندند.', 'کاربرهای عادی اثری نمی‌بینند.', ['کاری لازم نیست.']);

    if (function_exists('numReady')) {
        if (!numReady())
            drAdd($F, 'num_off', 'warn', 'shop', 'sim', 'فروشِ شماره‌ی مجازی خاموش است', 'فروشنده‌ی شماره وصل نیست یا «فروشِ شماره روشن باشد» خاموش است.',
                'هیچ شماره‌ای فروخته نمی‌شود.', ['پنلِ وب ← API و اتصال‌ها ← فروشنده‌ی شماره مجازی ← کلید را بگذار و تیکِ «فروشِ شماره روشن باشد» را بزن ← ذخیره ← 🧪 تست.'],
                'admin_panel.php?tab=apis#api-num');
        elseif (function_exists('maItems') && !array_filter(maItems(), fn($i) => !empty($i['on'])))
            drAdd($F, 'num_empty', 'warn', 'shop', 'sim', 'هیچ شماره‌ای برای فروش روشن نیست', 'فهرستِ کشورها و قیمت‌ها خالی است یا همه خاموش‌اند.',
                'مینی‌اپِ شماره خالی دیده می‌شود.', ['پنلِ وب ← کشورها و قیمت‌ها ← «📥 وارد کردن و به‌روزرسانی» را بزن.'], 'admin_panel.php?tab=numbers');
    }
    $cfg = function_exists('cfg') ? cfg() : [];
    $irOn = function_exists('irOn') && irOn();
    $gwOn = function_exists('gwOn') && gwOn();
    if (!$irOn && !$gwOn)
        drAdd($F, 'topup_none', 'warn', 'shop', 'card', 'هیچ روشِ شارژِ کیف پول فعال نیست', 'درگاهِ ایرانی و رمزارز هر دو خاموش یا ناقص‌اند.',
            'کاربرها نمی‌توانند کیف پول را شارژ کنند.', ['پنلِ وب ← تنظیمات ← درگاه پرداخت ← دست‌کم یکی از دو درگاه را با کلیدش پر کن ← ذخیره ← تستِ اتصال.'],
            'admin_panel.php?tab=settings&s=gw');
    if (!empty($cfg['gateway']['on']) && !$gwOn)
        drAdd($F, 'gw_incomplete', 'warn', 'shop', 'card', 'درگاهِ رمزارز روشن است ولی تنظیمش ناقص است', 'کلیدِ API یا آدرسِ عمومی کم است.',
            'گزینه‌ی رمزارز به کاربرها نشان داده نمی‌شود.', ['پنلِ وب ← تنظیمات ← درگاه پرداخت ← کلید را بگذار ← ذخیره ← 🧪 تستِ اتصال.'], 'admin_panel.php?tab=settings&s=gw');
    if (!empty($cfg['irpay']['on']) && !$irOn)
        drAdd($F, 'ir_incomplete', 'warn', 'shop', 'card', 'درگاهِ ایرانی روشن است ولی تنظیمش ناقص است', 'مرچنت یا آدرسِ عمومی کم است.',
            'گزینه‌ی درگاهِ ایرانی به کاربرها نشان داده نمی‌شود.', ['پنلِ وب ← تنظیمات ← درگاه پرداخت ← درگاهِ ایرانی ← مرچنت را بگذار ← ذخیره ← 🧪 تست.'],
            'admin_panel.php?tab=settings&s=gw');

    $q = $snap['queues'];
    if ($q['svc_check'] > 0)
        drAdd($F, 'svc_check', 'info', 'shop', 'layers', drFa($q['svc_check']) . ' سفارشِ خدمات منتظرِ بررسیِ توست',
            'پنلِ خدمات وضعیتِ این سفارش‌ها را مبهم برگردانده.', 'تا بررسی نشوند، برگشتِ پول یا تکمیلشان انجام نمی‌شود.',
            ['پنلِ وب ← سفارش‌های خدمات ← «نیاز به بررسی» ← هرکدام را ببین و تکلیفش را روشن کن.'], 'admin_panel.php?tab=svorders&st=check');
    if ($q['kyc'] > 0)
        drAdd($F, 'kyc', 'info', 'shop', 'users', drFa($q['kyc']) . ' احراز هویت منتظرِ تایید است', 'کاربرها مدارکشان را فرستاده‌اند.',
            'تا تایید نشوند، این کاربرها نمی‌توانند مبلغِ بالا با درگاهِ ایرانی بپردازند.', ['پنلِ ربات ← 💳 پرداخت ← 🪪 احراز هویت‌ها.']);
    $bc = function_exists('load') ? load('broadcast', true) : null;
    if (is_array($bc) && empty($bc['done']) && !empty($bc['total']) && $now - (int)($bc['at'] ?? $now) > 21600)
        drAdd($F, 'bc_stuck', 'warn', 'cfg', 'inbox', 'پیامِ همگانی بیش از ۶ ساعت است تمام نشده',
            'فرستاده‌شده: ' . drFa($bc['sent'] ?? 0) . ' از ' . drFa($bc['total']) . ' (شروع: ' . drAgo($bc['at'] ?? $now) . ')',
            'بقیه‌ی کاربرها پیام را نگرفته‌اند.', ['معمولاً یعنی کرون‌جاب کار نمی‌کند (اگر بود، جدا در همین فهرست آمده).', 'با هر پیامی که به ربات برسد هم کمی جلو می‌رود.']);

    $sevW = ['crit' => 0, 'warn' => 1, 'info' => 2];
    $F = array_values($F);
    usort($F, fn($a, $b) => $sevW[$a['sev']] <=> $sevW[$b['sev']]);
    $nc = count(array_filter($F, fn($f) => $f['sev'] === 'crit'));
    $nw = count(array_filter($F, fn($f) => $f['sev'] === 'warn'));
    $ni = count($F) - $nc - $nw;
    $groups = ['tg' => 'اتصال به تلگرام و وبهوک', 'php' => 'خطاها و تنظیماتِ PHP', 'sec' => 'سرعت و سلامتِ بخش‌ها', 'ext' => 'سرویس‌های بیرونی',
               'money' => 'موجودیِ فروشنده‌ها', 'disk' => 'دیسک و دیتابیس', 'cfg' => 'آدرس، کرون و صف‌ها', 'shop' => 'فروش و پرداخت', 'safe' => 'امنیت'];
    $checks = [];
    foreach ($groups as $g => $label) {
        $worst = 'ok';
        foreach ($F as $f) if ($f['grp'] === $g && $f['sev'] !== 'info') { $worst = $f['sev'] === 'crit' ? 'down' : ($worst === 'down' ? 'down' : 'slow'); }
        $checks[] = ['g' => $g, 'name' => $label, 'st' => $worst];
    }
    return [
        'score' => max(0, 100 - 25 * $nc - 8 * $nw - 1 * $ni),
        'crit' => $nc, 'warn' => $nw, 'info' => $ni,
        'findings' => $F,
        'checks' => $checks,
        'wh' => ['ok' => $wh['ok'], 'url' => (string)($wh['info']['url'] ?? ''), 'pending' => (int)($wh['info']['pending_update_count'] ?? 0), 'at' => $wh['at']],
        'cron_at' => $cronAt,
    ];
}

function drAlertText(array $f) {
    $mark = $f['sev'] === 'crit' ? '🔴' : '🟠';
    $t = "🩺 <b>دستیارِ عیب‌یاب</b>\n\n" . $mark . ' <b>' . h($f['title']) . "</b>\n" . h(monMask($f['facts'])) . "\n\n";
    if ($f['impact'] !== '') $t .= '<b>اثر:</b> ' . h($f['impact']) . "\n\n";
    $t .= "<b>چه کنم:</b>\n";
    foreach ($f['steps'] as $i => $s) $t .= drFa($i + 1) . '. ' . h(monMask($s)) . "\n";
    return $t . "\n🛰 جزئیات و دکمه‌ی رفع: پنلِ وب ← رصدِ زنده";
}

function drTick() {
    $mark = DATA_DIR . '/mon/.dr_at';
    if (!is_dir(DATA_DIR . '/mon') || time() - (@filemtime($mark) ?: 0) < 300) return;
    @touch($mark);
    $since = DATA_DIR . '/mon/.dr_since';
    if (!is_file($since)) { @touch($since); return; }
    if (time() - (int)@filemtime($since) < 600) return;
    try {
        drWebhook(true);
        if (time() - (int)(maCacheAt('mon_leak') ?: 0) > 43200) drLeak(true);
        $d = monSnapshot()['doctor'];
        $prev = (array)(maCacheGet('dr_active', 0) ?: []);
        $now = [];
        foreach ($d['findings'] as $f) {
            if ($f['sev'] === 'info') continue;
            $now[$f['k']] = $f['title'];
            if ($f['sev'] === 'crit') adminAlertOnce('dr_' . $f['k'], drAlertText($f), 1800);
            elseif ($f['sev'] === 'warn') adminAlertOnce('dr_' . $f['k'], drAlertText($f), 21600);
        }
        foreach ($prev as $k => $title)
            if (!isset($now[$k]) && function_exists('chTechAlert'))
                chTechAlert("🩺 <b>دستیارِ عیب‌یاب</b>\n\n✅ برطرف شد: <b>" . h($title) . '</b>');
        maCachePut('dr_active', $now);
    } catch (Throwable $e) {
        error_log('[doctor] ' . monMask($e->getMessage()));
    }
}

function drFixWebhook() {
    $url = drBotUrl();
    if ($url === '' || !preg_match('#^https://#i', $url)) return [false, 'اول آدرسِ عمومیِ https را در پنلِ وب ← API و اتصال‌ها ثبت کن.'];
    if (!defined('WEBHOOK_SECRET') || WEBHOOK_SECRET === '') return [false, 'اول WEBHOOK_SECRET را در config.local.php تعریف کن.'];
    $wh = drWebhook(true);
    $drop = (int)($wh['info']['pending_update_count'] ?? 0) > 500;
    $r = tg(BOT_TOKEN, 'setWebhook', [
        'url' => $url,
        'secret_token' => WEBHOOK_SECRET,
        'allowed_updates' => json_encode(['message', 'callback_query', 'my_chat_member']),
        'drop_pending_updates' => $drop ? 'true' : 'false',
        'max_connections' => nbHookMax(),
    ], 10);
    if (empty($r['ok'])) return [false, 'تلگرام قبول نکرد: ' . monMask((string)($r['description'] ?? '—'))];
    if (function_exists('maCachePut')) maCachePut('menu_sig', '');
    if (function_exists('maMenuSync')) maMenuSync();
    drWebhook(true);
    return [true, 'وبهوک روی ' . $url . ' ثبت شد' . ($drop ? ' و پیام‌های قدیمیِ صف پاک شد.' : '.')];
}
