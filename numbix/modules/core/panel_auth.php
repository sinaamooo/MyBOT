<?php
defined('NB_ROOT') || exit;

if (!defined('ADMIN_PASSWORD'))
    define('ADMIN_PASSWORD', defined('ADMIN_PANEL_PASS')
        ? (string)ADMIN_PANEL_PASS
        : (string)getenv('ADMIN_PANEL_PASS'));

if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', (string)getenv('ADMIN_EMAIL'));
if (!defined('ADMIN_PHONE')) define('ADMIN_PHONE', (string)getenv('ADMIN_PHONE'));

$__hashOk = (defined('ADMIN_PASSWORD_HASH') && strlen((string)ADMIN_PASSWORD_HASH) >= 20)
         || strlen((string)getenv('ADMIN_PASSWORD_HASH')) >= 20;
if (!$__hashOk && strlen(ADMIN_PASSWORD) < 6) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("رمز پنل تنظیم نشده است.\n\n" .
         "کنار همین فایل در config.local.php بنویسید:\n\n" .
         "define('ADMIN_PANEL_PASS', 'رمز شما');\n");
}
unset($__hashOk);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_name('mybot_panel');
session_start();
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

function mIcons() {
    return [
        'bot'     => 'M7 7h10a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-6a3 3 0 0 1 3-3zM12 3v4M9 12.5v1M15 12.5v1',
        'sim'     => 'M8 3h6l4 4v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM9 11h6v6H9zM12 11v6M9 14h6',
        'puzzle'  => 'M10 4a2 2 0 1 1 4 0v2h4v4h-2a2 2 0 1 0 0 4h2v4h-4v-2a2 2 0 1 0-4 0v2H6v-4h2a2 2 0 1 0 0-4H6V6h4z',
        'wallet'  => 'M4 7h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zM4 7l11-3v3M16 13.5h2',
        'game'    => 'M7 9h10a4 4 0 0 1 4 4v1a3 3 0 0 1-5.2 2L14 14h-4l-1.8 2A3 3 0 0 1 3 14v-1a4 4 0 0 1 4-4zM8 11.5v3M6.5 13h3M15.5 12.5h.01M17.5 14h.01',
        'chart'   => 'M4 19h16M7 16v-5M11 16V7M15 16v-6M19 16V5',
        'users'   => 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM3 19a6 6 0 0 1 12 0M16 11a3 3 0 1 0-1-5.8M21 19a5 5 0 0 0-4-4.9',
        'chat'    => 'M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-7l-5 4v-4H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zM8 10h8M8 13h5',
        'headset' => 'M4 14v-2a8 8 0 0 1 16 0v2M4 14h3v5H5a1 1 0 0 1-1-1zM20 14h-3v5h2a1 1 0 0 0 1-1zM17 19a4 4 0 0 1-4 2',
        'gift'    => 'M4 9h16v4H4zM6 13v7h12v-7M12 9v11M12 9c-1.5-3-5-4-5-1.5S10 9 12 9zM12 9c1.5-3 5-4 5-1.5S14 9 12 9z',
        'crown'   => 'M4 8l4 4 4-7 4 7 4-4-2 11H6zM6 19h12',
        'clock'   => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
        'image'   => 'M4 5h16v14H4zM4 16l5-5 4 4 2-2 5 5M15.5 9.5h.01',
        'shield'  => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM9 12l2 2 4-4',
        'spark'   => 'M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5L18 18M18 6l-2.5 2.5M8.5 15.5L6 18',
        'plane'   => 'M21 4L3 11l6 2 2 6 3-4 5 4zM9 13l12-9',
        'coins'   => 'M9 9c3.3 0 6-1.1 6-2.5S12.3 4 9 4 3 5.1 3 6.5 5.7 9 9 9zM3 6.5v4C3 11.9 5.7 13 9 13M3 10.5v4C3 15.9 5.7 17 9 17M15 13c3.3 0 6-1.1 6-2.5S18.3 8 15 8M15 13c-3.3 0-6-1.1-6-2.5M9 10.5v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-4M9 14.5v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-4',
        'satellite' => 'M13 11l7-7M15 3l6 6M4 20l5-5M8.5 9.5l6 6M6 12a6 6 0 0 0 6 6',
        'inbox'   => 'M4 13l2-8h12l2 8v6H4zM4 13h5l1 2h4l1-2h5',
        'gauge'   => 'M4 18a8 8 0 1 1 16 0M12 18l4-6M8 18h.01M16 18h.01',
        'bug'     => 'M9 7a3 3 0 0 1 6 0v1H9zM7 9h10v5a5 5 0 0 1-10 0zM12 9v10M3 13h4M17 13h4M4 8l3 2M20 8l-3 2M4 19l3-2M20 19l-3-2',
        'server'  => 'M4 4h16v6H4zM4 14h16v6H4zM8 7h.01M8 17h.01M12 7h4M12 17h4',
        'disk'    => 'M4 7c0-1.7 3.6-3 8-3s8 1.3 8 3v10c0 1.7-3.6 3-8 3s-8-1.3-8-3zM4 7c0 1.7 3.6 3 8 3s8-1.3 8-3M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3',
        'cpu'     => 'M8 8h8v8H8zM5 5h14v14H5zM9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3',
        'key'     => 'M14 10a4 4 0 1 0-4.5 4L4 19.5V21h3v-2h2v-2h2l1.5-1.5A4 4 0 0 0 14 10zM16 8h.01',
        'lock'    => 'M6 11h12v9H6zM8 11V8a4 4 0 0 1 8 0v3M12 15v2',
        'plug'    => 'M9 3v5M15 3v5M6 8h12v4a6 6 0 0 1-12 0zM12 18v3',
        'link'    => 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
        'webhook' => 'M12 8a3 3 0 1 0-2.6-1.5L6 13M15 15h7M6 18a3 3 0 1 0 2.6-4.5H14M18 18a3 3 0 1 0-1.5-5.6L13 6.5',
        'card'    => 'M3 6h18v12H3zM3 10h18M7 15h4',
        'layers'  => 'M12 3l9 5-9 5-9-5zM3 13l9 5 9-5',
        'steth'   => 'M6 3v6a5 5 0 0 0 10 0V3M6 3H4M16 3h2M11 14v2a5 5 0 0 0 10 0v-3M21 13a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
        'refresh' => 'M20 11a8 8 0 0 0-14.9-3.5M4 4v4h4M4 13a8 8 0 0 0 14.9 3.5M20 20v-4h-4',
        'gear'    => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19 12l2-1-1-3-2 .2-1.3-1.4.2-2-3-1-1 2h-1.8l-1-2-3 1 .2 2L6.9 7.2 5 7l-1 3 2 1v2l-2 1 1 3 2-.2 1.3 1.4-.2 2 3 1 1-2h1.8l1 2 3-1-.2-2 1.3-1.4 2 .2 1-3-2-1z',
        'logout'  => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
        'copy'    => 'M9 9h11v11H9zM5 15H4V4h11v1',
        'wrench'  => 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9z',
        'check'   => 'M5 12l5 5 9-10',
        'alert'   => 'M12 3l10 18H2zM12 10v5M12 18h.01',
        'info'    => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 11v6M12 7.5h.01',
        'radar'   => 'M12 12l6-6M12 21a9 9 0 1 1 9-9M12 17a5 5 0 1 1 5-5',
        'globe'   => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18',
        'play'    => 'M7 5l12 7-12 7z',
        'pause'   => 'M8 5v14M16 5v14',
        'arrow'   => 'M15 6l-6 6 6 6',
        'chev'    => 'M6 9l6 6 6-6',
        'bolt'    => 'M13 3L5 13h6l-1 8 8-10h-6z',
        'x'       => 'M6 6l12 12M18 6L6 18',
        'flask'   => 'M9 3h6M10 3v6L5 18a2 2 0 0 0 1.8 3h10.4a2 2 0 0 0 1.8-3L14 9V3M7.5 15h9',
        'download'=> 'M12 4v11M7 10l5 5 5-5M5 20h14',
        'ban'     => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM5.6 5.6l12.8 12.8',
        'receipt' => 'M6 3h12v18l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5L6 21zM9 8h6M9 12h6M9 16h3',
        'search'  => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-4-4',
        'target'  => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM12 12h.01',
        'palette' => 'M12 3a9 9 0 0 0 0 18 2 2 0 0 0 1.5-3.3 2 2 0 0 1 1.5-3.3h2.3A4.7 4.7 0 0 0 21 9.8C21 6 17 3 12 3zM7.5 11h.01M9.5 7h.01M14.5 7h.01M17 11h.01',
        'gem'     => 'M6 3h12l3 6-9 12L3 9zM3 9h18M9 3l3 6 3-6M12 9v12',
        'folder'  => 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
        'user'    => 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0',
        'compass' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM15.5 8.5l-2 5-5 2 2-5z',
        'swap'    => 'M7 7h13l-3-3M17 17H4l3 3',
        'camera'  => 'M4 8h3l2-3h6l2 3h3v11H4zM12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'shuffle' => 'M3 7h4l10 10h4M3 17h4l3-3M14 10l3-3h4M18 4l3 3-3 3M18 14l3 3-3 3',
        'type'    => 'M4 7V5h16v2M12 5v14M9 19h6',
        'poll'    => 'M5 21V11M12 21V5M19 21v-7M3 21h18',
        'pencil'  => 'M4 20l4-1 11-11-3-3L5 16zM14 6l3 3',
        'eye'     => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
        'eyeoff'  => 'M3 3l18 18M10.6 6.1A10.6 10.6 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-3.1 3.9M6.6 7.6A17 17 0 0 0 2 12s3.5 6 10 6a9.7 9.7 0 0 0 4.4-1',
        'list'    => 'M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01',
        'ticket'  => 'M3 6h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4zM14 6v12',
        'store'   => 'M4 9l1.5-5h13L20 9M4 9v11h16V9M4 9a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0M10 20v-6h4v6',
        'calc'    => 'M6 3h12v18H6zM9 7h6M9 11h.01M12 11h.01M15 11h.01M9 14h.01M12 14h.01M15 14h.01M9 17h.01M12 17h.01M15 17h.01',
        'flame'   => 'M12 21c-4 0-7-3-7-7 0-3 2-5 3-7 1 2 2 3 3 3 0-3 2-5 4-7 0 4 4 6 4 11 0 4-3 7-7 7z',
        'scale'   => 'M12 4v16M8 20h8M5 8l-3 6a3 3 0 0 0 6 0zM19 8l-3 6a3 3 0 0 0 6 0zM5 8h14',
        'bank'    => 'M3 10l9-6 9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20h18',
        'id'      => 'M3 5h18v14H3zM8.5 13a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM5 17a3.5 3.5 0 0 1 7 0M14 9h4M14 12h4',
        'mail'    => 'M3 6h18v12H3zM3 7l9 6 9-6',
        'phone'   => 'M7 3h10v18H7zM11 18h2',
        'megaphone' => 'M3 11v3h3l7 4V7l-7 4zM16 9a4 4 0 0 1 0 6M6 14l1 5h2.5l-1-5',
        'bag'     => 'M5 8h14l-1 13H6zM9 8V6a3 3 0 0 1 6 0v2',
        'rocket'  => 'M5 15c-1 1-2 4-2 6 2 0 5-1 6-2M14 4c3-1 6-1 6-1s0 3-1 6l-7 7-5-5zM15 9h.01M8 11l-3 1-1 3 3-1M13 16l-1 3-3 1 1-3',
        'undo'    => 'M9 14L4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3',
        'dot'     => 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'traffic' => 'M8 2h8v20H8zM12 7h.01M12 12h.01M12 17h.01',
        'trash'   => 'M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v6M14 11v6',
        'box'     => 'M3 8l9-5 9 5v8l-9 5-9-5zM3 8l9 5 9-5M12 13v8',
    ];
}

function mi($name, $tint = 'blue', $cls = '') {
    $p = mIcons()[$name] ?? '';
    return '<span class="gi t-' . $tint . ($cls !== '' ? ' ' . $cls : '') . '" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="' . $p . '"/></svg></span>';
}

function panelGlassCss() {
    return '.gi{--t:#3987e5;position:relative;flex:none;display:inline-grid;place-items:center;width:34px;height:34px;border-radius:11px;isolation:isolate;'
         . 'background:linear-gradient(150deg,color-mix(in srgb,var(--t) 46%,transparent),color-mix(in srgb,var(--t) 10%,rgba(255,255,255,.03)) 72%);'
         . 'border:1px solid rgba(255,255,255,.17);'
         . 'box-shadow:inset 0 1px 0 rgba(255,255,255,.28),inset 0 -8px 14px rgba(0,0,0,.22)}'
         . ".gi::before{content:'';position:absolute;inset:1px 1px 52% 1px;border-radius:10px 10px 8px 8px;z-index:-1;"
         . 'background:linear-gradient(180deg,rgba(255,255,255,.26),rgba(255,255,255,0))}'
         . '.gi svg{width:18px;height:18px;fill:none;stroke:#fff;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}'
         . '.gi.sm{width:28px;height:28px;border-radius:9px}.gi.sm svg{width:15px;height:15px}'
         . '.gi.lg{width:44px;height:44px;border-radius:14px}.gi.lg svg{width:22px;height:22px}'
         . '.gi.in{width:1.7em;height:1.7em;border-radius:.55em;vertical-align:middle;margin-inline-end:.35em;box-shadow:inset 0 1px 0 rgba(255,255,255,.3),inset 0 -6px 12px rgba(0,0,0,.25)}'
         . '.gi.in::before{border-radius:.5em .5em .4em .4em}.gi.in svg{width:58%;height:58%}'
         . '.t-blue{--t:#3987e5}.t-violet{--t:#8b7cf6}.t-cyan{--t:#22b8cf}.t-pink{--t:#d55181}.t-good{--t:#0ca30c}.t-warn{--t:#e09a0c}.t-crit{--t:#d03b3b}.t-idle{--t:#5b6478}';
}

function panelEmojiIcons() {
    return [
        '✅' => ['check', 'good'], '✔' => ['check', 'good'], '☑' => ['check', 'good'], '❌' => ['x', 'crit'], '✖' => ['x', 'crit'], '❎' => ['x', 'crit'],
        '☎' => ['sim', 'blue'], '📞' => ['headset', 'blue'], '📲' => ['phone', 'blue'], '📱' => ['phone', 'blue'],
        '📥' => ['download', 'blue'], '📤' => ['download', 'violet'], '🧪' => ['flask', 'violet'], '🧩' => ['puzzle', 'violet'],
        '💳' => ['card', 'blue'], '⛔' => ['ban', 'crit'], '🚫' => ['ban', 'crit'], '🌍' => ['globe', 'cyan'], '🌐' => ['globe', 'cyan'],
        '⚠' => ['alert', 'warn'], '🚨' => ['alert', 'crit'], '✋' => ['alert', 'warn'], '⏳' => ['clock', 'warn'], '⌛' => ['clock', 'warn'],
        '🕘' => ['clock', 'blue'], '⏱' => ['clock', 'blue'], '🧾' => ['receipt', 'cyan'], '🔎' => ['search', 'blue'], '🔍' => ['search', 'blue'],
        '📈' => ['chart', 'good'], '📊' => ['chart', 'blue'], '💹' => ['chart', 'good'], '🔴' => ['dot', 'crit'], '🟡' => ['dot', 'warn'],
        '🟢' => ['dot', 'good'], '⚪' => ['dot', 'idle'], '🚀' => ['rocket', 'violet'], '💰' => ['coins', 'warn'], '💵' => ['coins', 'good'],
        '👥' => ['users', 'blue'], '👤' => ['user', 'blue'], '🎯' => ['target', 'pink'], '🎨' => ['palette', 'pink'],
        '📡' => ['satellite', 'cyan'], '🛰' => ['satellite', 'cyan'], '💎' => ['gem', 'cyan'], '💠' => ['gem', 'cyan'],
        '🔑' => ['key', 'warn'], '🗂' => ['folder', 'idle'], '📁' => ['folder', 'idle'], '🎁' => ['gift', 'pink'], '💬' => ['chat', 'blue'],
        '🧭' => ['compass', 'cyan'], '💱' => ['swap', 'good'], '✈' => ['plane', 'blue'], '📸' => ['camera', 'pink'], '🔀' => ['shuffle', 'violet'],
        '❔' => ['info', 'idle'], '❓' => ['info', 'idle'], 'ℹ' => ['info', 'blue'], '🔤' => ['type', 'violet'], '🗳' => ['poll', 'blue'],
        '♻' => ['refresh', 'good'], '🔄' => ['refresh', 'blue'], '🔁' => ['refresh', 'blue'], '🩺' => ['steth', 'good'], '✏' => ['pencil', 'violet'],
        '👁' => ['eye', 'blue'], '🙈' => ['eyeoff', 'idle'], '🚦' => ['traffic', 'warn'], '🔗' => ['link', 'blue'], '📋' => ['list', 'blue'],
        '🎟' => ['ticket', 'pink'], '🎫' => ['ticket', 'pink'], '🏷' => ['ticket', 'violet'], '🏪' => ['store', 'violet'], '🧮' => ['calc', 'violet'],
        '🔥' => ['flame', 'warn'], '⚖' => ['scale', 'violet'], '🏆' => ['crown', 'warn'], '👑' => ['crown', 'warn'], '⚙' => ['gear', 'idle'],
        '🛠' => ['wrench', 'idle'], '🔧' => ['wrench', 'idle'], '🤖' => ['bot', 'blue'], '⚡' => ['bolt', 'warn'], '🛡' => ['shield', 'good'],
        '💾' => ['disk', 'blue'], '🗄' => ['server', 'blue'], '🏦' => ['bank', 'blue'], '🪪' => ['id', 'blue'], '🆔' => ['id', 'blue'],
        '📩' => ['mail', 'blue'], '📨' => ['mail', 'blue'], '🔐' => ['lock', 'violet'], '🔒' => ['lock', 'violet'], '🔓' => ['lock', 'idle'],
        '📢' => ['megaphone', 'pink'], '📣' => ['megaphone', 'pink'], '🎮' => ['game', 'violet'], '🖼' => ['image', 'pink'], '🛍' => ['bag', 'pink'],
        '↩' => ['undo', 'idle'], '🗑' => ['trash', 'crit'], '🧹' => ['trash', 'idle'], '📦' => ['box', 'cyan'], '🎉' => ['spark', 'pink'],
        '✨' => ['spark', 'violet'], '⭐' => ['spark', 'warn'], '🌟' => ['spark', 'warn'], '🐞' => ['bug', 'crit'], '🔌' => ['plug', 'idle'],
        '🧠' => ['cpu', 'violet'], '🖥' => ['server', 'blue'], '📶' => ['radar', 'cyan'], '🌀' => ['refresh', 'violet'],
    ];
}

function panelGlassText($t, $strip = false) {
    if ($t === '' || !function_exists('emRe') || !emHas($t)) return $t;
    $map = panelEmojiIcons();
    $out = preg_replace_callback(emSpRe(), function ($m) use ($map, $strip) {
        $e = rtrim($m[0], " \u{00A0}");
        if (preg_match('/^[\x{1F1E6}-\x{1F1FF}]/u', $e)) return $m[0];
        if ($strip) return '';
        [$ic, $tint] = $map[emKey($e)] ?? ['spark', 'idle'];
        return mi($ic, $tint, 'in');
    }, $t);
    return $out === null ? $t : $out;
}

function panelGlass($html) {
    if (!is_string($html) || $html === '' || !function_exists('emRe')) return $html;
    $keep = [];
    $html = preg_replace_callback('#<(script|style|textarea|code|pre|option|title)\b[^>]*>.*?</\1\s*>#is', function ($m) use (&$keep) {
        $tag = strtolower($m[1]);
        $keep[] = in_array($tag, ['option', 'title'], true) ? panelGlassText($m[0], true) : $m[0];
        return "\u{E010}" . (count($keep) - 1) . "\u{E011}";
    }, $html);
    $parts = preg_split('#(<[^>]*>|\x{E010}\d+\x{E011})#u', (string)$html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return $html;
    foreach ($parts as $i => $p) if ($i % 2 === 0 && $p !== '') $parts[$i] = panelGlassText($p);
    $html = implode('', $parts);
    return preg_replace_callback('/\x{E010}(\d+)\x{E011}/u', fn($m) => $keep[(int)$m[1]] ?? '', $html);
}

function panelFontCss() {
    $dir  = nbAsset('fonts');

    if (function_exists('faVarFontCss')) {
        $var = faVarFontCss($dir);
        if ($var !== '') return $var;
    }

    $out  = '';
    $faces = [
        400 => ['Vazirmatn-Regular', 'Vazirmatn', 'Vazir'],
        600 => ['Vazirmatn-SemiBold', 'Vazirmatn-Medium'],
        700 => ['Vazirmatn-Bold', 'Vazir-Bold'],
        800 => ['Vazirmatn-ExtraBold', 'Vazirmatn-Black'],
    ];
    foreach ($faces as $w => $names) {
        foreach ($names as $n) {
            foreach (['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype'] as $ext => $fmt) {
                $f = $dir . '/' . $n . '.' . $ext;
                if (!is_file($f)) continue;
                $out .= "@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:{$w};"
                      . "font-display:swap;src:url('assets/fonts/" . rawurlencode($n . '.' . $ext) . "') format('{$fmt}')}\n";
                continue 3;
            }
        }
    }
    return $out;
}

function renderLogin($error, $otp = false) { ?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" media="print" onload="this.media='all'"
      href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;800;900&display=swap">
<link rel="icon" href="data:,">
<title>ورود — پنل مدیریت</title><style>
<?= panelFontCss() ?>
*{box-sizing:border-box;margin:0;padding:0}
html{background:#05060f}
body{min-height:100vh;display:grid;place-items:center;padding:20px;color:#eef0ff;
font-family:'Vazirmatn','Vazir',Tahoma,system-ui,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased}
.sky{position:fixed;inset:0;z-index:-1;pointer-events:none;overflow:hidden;contain:strict;
background:radial-gradient(760px 560px at 85% -6%,rgba(124,108,255,.28),transparent 62%),
  radial-gradient(680px 520px at 8% 12%,rgba(34,184,230,.18),transparent 60%),
  radial-gradient(820px 640px at 50% 110%,rgba(219,70,160,.14),transparent 62%),
  linear-gradient(180deg,#05060f,#070a1a 55%,#04050c)}
.sky::before,.sky::after{content:'';position:absolute;inset:0;will-change:opacity;animation:tw 6s ease-in-out infinite alternate}
.sky::before{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='520' height='520'%3E%3Ccircle cx='168' cy='78' r='0.5' fill='%23bfe6ff' opacity='0.40'/%3E%3Ccircle cx='49' cy='303' r='0.8' fill='%23cfd8ff' opacity='0.37'/%3E%3Ccircle cx='217' cy='125' r='1.1' fill='%23bfe6ff' opacity='0.39'/%3E%3Ccircle cx='64' cy='116' r='0.5' fill='%23cfd8ff' opacity='0.73'/%3E%3Ccircle cx='26' cy='115' r='0.7' fill='%23fff' opacity='0.54'/%3E%3Ccircle cx='281' cy='297' r='0.7' fill='%23bfe6ff' opacity='0.42'/%3E%3Ccircle cx='332' cy='194' r='0.6' fill='%23bfe6ff' opacity='0.72'/%3E%3Ccircle cx='107' cy='354' r='1.1' fill='%23cfd8ff' opacity='0.86'/%3E%3Ccircle cx='304' cy='236' r='0.9' fill='%23fff' opacity='0.51'/%3E%3Ccircle cx='363' cy='127' r='0.9' fill='%23fff' opacity='0.69'/%3E%3Ccircle cx='379' cy='150' r='0.6' fill='%23cfd8ff' opacity='0.43'/%3E%3Ccircle cx='86' cy='178' r='1.3' fill='%23ffd9f3' opacity='0.62'/%3E%3Ccircle cx='40' cy='290' r='1.0' fill='%23fff' opacity='0.57'/%3E%3Ccircle cx='309' cy='302' r='1.3' fill='%23fff' opacity='0.39'/%3E%3Ccircle cx='491' cy='247' r='0.6' fill='%23ffd9f3' opacity='0.39'/%3E%3Ccircle cx='161' cy='301' r='1.3' fill='%23cfd8ff' opacity='0.53'/%3E%3Ccircle cx='461' cy='180' r='1.3' fill='%23bfe6ff' opacity='0.58'/%3E%3Ccircle cx='61' cy='31' r='0.9' fill='%23fff' opacity='0.43'/%3E%3Ccircle cx='207' cy='477' r='1.3' fill='%23cfd8ff' opacity='0.40'/%3E%3Ccircle cx='209' cy='144' r='0.7' fill='%23bfe6ff' opacity='0.88'/%3E%3Ccircle cx='145' cy='216' r='1.0' fill='%23cfd8ff' opacity='0.79'/%3E%3Ccircle cx='498' cy='78' r='0.7' fill='%23ffd9f3' opacity='0.45'/%3E%3Ccircle cx='121' cy='252' r='0.7' fill='%23fff' opacity='0.52'/%3E%3Ccircle cx='76' cy='278' r='1.0' fill='%23ffd9f3' opacity='0.97'/%3E%3Ccircle cx='447' cy='494' r='0.5' fill='%23ffd9f3' opacity='0.65'/%3E%3Ccircle cx='415' cy='204' r='1.1' fill='%23cfd8ff' opacity='0.61'/%3E%3Ccircle cx='330' cy='32' r='0.6' fill='%23cfd8ff' opacity='0.99'/%3E%3Ccircle cx='84' cy='177' r='0.5' fill='%23bfe6ff' opacity='0.42'/%3E%3Ccircle cx='79' cy='53' r='1.0' fill='%23fff' opacity='0.75'/%3E%3Ccircle cx='455' cy='319' r='0.7' fill='%23fff' opacity='0.76'/%3E%3Ccircle cx='313' cy='247' r='0.6' fill='%23cfd8ff' opacity='0.90'/%3E%3Ccircle cx='250' cy='162' r='0.7' fill='%23fff' opacity='0.42'/%3E%3Ccircle cx='385' cy='249' r='0.7' fill='%23fff' opacity='0.69'/%3E%3Ccircle cx='495' cy='275' r='0.7' fill='%23fff' opacity='0.80'/%3E%3Ccircle cx='394' cy='155' r='0.6' fill='%23fff' opacity='0.80'/%3E%3Ccircle cx='270' cy='472' r='1.0' fill='%23bfe6ff' opacity='0.85'/%3E%3Ccircle cx='282' cy='261' r='0.8' fill='%23fff' opacity='0.75'/%3E%3Ccircle cx='419' cy='426' r='0.8' fill='%23cfd8ff' opacity='0.48'/%3E%3Ccircle cx='185' cy='15' r='0.5' fill='%23cfd8ff' opacity='0.86'/%3E%3Ccircle cx='135' cy='360' r='1.0' fill='%23ffd9f3' opacity='0.64'/%3E%3Ccircle cx='514' cy='497' r='1.0' fill='%23fff' opacity='0.40'/%3E%3Ccircle cx='118' cy='102' r='0.8' fill='%23bfe6ff' opacity='0.66'/%3E%3Ccircle cx='437' cy='249' r='1.0' fill='%23fff' opacity='0.87'/%3E%3Ccircle cx='434' cy='62' r='1.1' fill='%23fff' opacity='0.86'/%3E%3Ccircle cx='249' cy='93' r='1.0' fill='%23ffd9f3' opacity='0.41'/%3E%3Ccircle cx='206' cy='209' r='0.6' fill='%23fff' opacity='0.82'/%3E%3Ccircle cx='516' cy='14' r='1.3' fill='%23fff' opacity='0.87'/%3E%3Ccircle cx='318' cy='310' r='1.3' fill='%23fff' opacity='0.78'/%3E%3Ccircle cx='81' cy='285' r='0.5' fill='%23ffd9f3' opacity='0.36'/%3E%3Ccircle cx='338' cy='274' r='0.7' fill='%23fff' opacity='0.63'/%3E%3Ccircle cx='430' cy='110' r='0.9' fill='%23bfe6ff' opacity='0.49'/%3E%3Ccircle cx='125' cy='305' r='0.9' fill='%23fff' opacity='0.70'/%3E%3Ccircle cx='32' cy='385' r='1.3' fill='%23bfe6ff' opacity='0.78'/%3E%3Ccircle cx='219' cy='477' r='0.7' fill='%23bfe6ff' opacity='0.70'/%3E%3Ccircle cx='265' cy='454' r='0.7' fill='%23fff' opacity='0.75'/%3E%3Ccircle cx='90' cy='246' r='0.6' fill='%23fff' opacity='0.71'/%3E%3Ccircle cx='355' cy='276' r='1.3' fill='%23fff' opacity='0.86'/%3E%3Ccircle cx='459' cy='30' r='0.8' fill='%23fff' opacity='0.53'/%3E%3Ccircle cx='264' cy='292' r='0.6' fill='%23bfe6ff' opacity='0.64'/%3E%3Ccircle cx='506' cy='315' r='0.8' fill='%23cfd8ff' opacity='0.80'/%3E%3Ccircle cx='264' cy='420' r='0.8' fill='%23fff' opacity='0.80'/%3E%3Ccircle cx='480' cy='464' r='0.8' fill='%23fff' opacity='0.90'/%3E%3Ccircle cx='217' cy='204' r='1.0' fill='%23fff' opacity='0.40'/%3E%3Ccircle cx='223' cy='111' r='0.9' fill='%23fff' opacity='0.86'/%3E%3Ccircle cx='489' cy='335' r='1.0' fill='%23fff' opacity='0.44'/%3E%3Ccircle cx='503' cy='114' r='0.6' fill='%23cfd8ff' opacity='0.61'/%3E%3Ccircle cx='85' cy='347' r='0.8' fill='%23cfd8ff' opacity='0.45'/%3E%3Ccircle cx='517' cy='210' r='1.1' fill='%23fff' opacity='0.48'/%3E%3Ccircle cx='48' cy='190' r='1.0' fill='%23cfd8ff' opacity='0.71'/%3E%3Ccircle cx='366' cy='200' r='0.9' fill='%23fff' opacity='0.68'/%3E%3C/svg%3E");background-size:520px 520px}
.sky::after{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='860' height='860'%3E%3Ccircle cx='389' cy='481' r='2.0' fill='%23bfe6ff' opacity='0.64'/%3E%3Ccircle cx='163' cy='691' r='2.0' fill='%23fff' opacity='0.76'/%3E%3Ccircle cx='81' cy='261' r='0.9' fill='%23ffd9f3' opacity='0.70'/%3E%3Ccircle cx='546' cy='512' r='1.7' fill='%23ffd9f3' opacity='0.98'/%3E%3Ccircle cx='635' cy='559' r='0.8' fill='%23fff' opacity='0.89'/%3E%3Ccircle cx='51' cy='164' r='1.2' fill='%23cfd8ff' opacity='0.74'/%3E%3Ccircle cx='281' cy='508' r='1.2' fill='%23ffd9f3' opacity='0.69'/%3E%3Ccircle cx='253' cy='4' r='0.9' fill='%23fff' opacity='0.65'/%3E%3Ccircle cx='350' cy='474' r='0.9' fill='%23fff' opacity='0.81'/%3E%3Ccircle cx='652' cy='441' r='0.8' fill='%23fff' opacity='0.40'/%3E%3Ccircle cx='344' cy='728' r='1.7' fill='%23fff' opacity='0.39'/%3E%3Ccircle cx='729' cy='0' r='1.2' fill='%23fff' opacity='0.95'/%3E%3Ccircle cx='404' cy='843' r='1.7' fill='%23bfe6ff' opacity='0.62'/%3E%3Ccircle cx='541' cy='670' r='1.4' fill='%23fff' opacity='0.57'/%3E%3Ccircle cx='286' cy='829' r='0.9' fill='%23ffd9f3' opacity='0.44'/%3E%3Ccircle cx='87' cy='52' r='2.0' fill='%23bfe6ff' opacity='0.47'/%3E%3Ccircle cx='162' cy='438' r='1.0' fill='%23cfd8ff' opacity='0.62'/%3E%3Ccircle cx='100' cy='362' r='1.2' fill='%23bfe6ff' opacity='0.35'/%3E%3Ccircle cx='262' cy='761' r='1.2' fill='%23bfe6ff' opacity='0.47'/%3E%3Ccircle cx='552' cy='86' r='1.0' fill='%23fff' opacity='0.49'/%3E%3Ccircle cx='8' cy='525' r='1.4' fill='%23fff' opacity='0.60'/%3E%3Ccircle cx='78' cy='501' r='1.2' fill='%23fff' opacity='0.36'/%3E%3Ccircle cx='320' cy='390' r='2.0' fill='%23fff' opacity='0.89'/%3E%3Ccircle cx='745' cy='157' r='1.0' fill='%23fff' opacity='0.55'/%3E%3Ccircle cx='703' cy='215' r='1.2' fill='%23ffd9f3' opacity='0.45'/%3E%3Ccircle cx='809' cy='169' r='1.7' fill='%23bfe6ff' opacity='0.92'/%3E%3Ccircle cx='68' cy='41' r='0.9' fill='%23fff' opacity='0.38'/%3E%3Ccircle cx='205' cy='606' r='1.4' fill='%23bfe6ff' opacity='0.62'/%3E%3Ccircle cx='422' cy='447' r='0.9' fill='%23cfd8ff' opacity='0.43'/%3E%3Ccircle cx='481' cy='733' r='0.9' fill='%23fff' opacity='0.53'/%3E%3Ccircle cx='644' cy='59' r='1.7' fill='%23fff' opacity='0.64'/%3E%3Ccircle cx='40' cy='242' r='1.0' fill='%23fff' opacity='0.41'/%3E%3Ccircle cx='766' cy='843' r='1.0' fill='%23fff' opacity='0.73'/%3E%3Ccircle cx='408' cy='307' r='1.4' fill='%23fff' opacity='0.98'/%3E%3C/svg%3E");background-size:860px 860px;animation-duration:9s;animation-delay:-4s}
@keyframes tw{from{opacity:.3}to{opacity:.9}}
.card{width:100%;max-width:380px;text-align:center;padding:40px 30px;border-radius:22px;background:rgba(14,18,42,.9);
border:1px solid rgba(150,160,255,.18);box-shadow:0 30px 70px -30px rgba(0,0,0,.95),inset 0 1px 0 rgba(255,255,255,.07)}
h1{font-size:27px;margin-bottom:6px;font-weight:900;letter-spacing:-.4px;
background:linear-gradient(90deg,#fff,#c9c2ff 50%,#7fdcf5);-webkit-background-clip:text;background-clip:text;color:transparent}
p.sub{color:#a6aed0;font-size:14px;font-weight:800;margin-bottom:24px}
input{width:100%;padding:14px 16px;border:1px solid rgba(150,160,255,.18);border-radius:13px;font-size:16px;font-weight:800;letter-spacing:.5px;
font-family:inherit;margin-bottom:14px;text-align:center;color:#eef0ff;background:#0a0f22}
input::placeholder{color:#7d86ab}
input:focus{outline:none;border-color:#7c6cff;box-shadow:0 0 0 3px rgba(124,108,255,.2)}
button{width:100%;padding:14px;border:0;border-radius:13px;background:linear-gradient(135deg,#7c6cff,#4f7bff 55%,#22b8e6);color:#fff;
font-size:17px;font-weight:900;cursor:pointer;font-family:inherit;box-shadow:0 12px 26px -14px rgba(124,108,255,.95),inset 0 1px 0 rgba(255,255,255,.2)}
button:hover{filter:brightness(1.1)}
button.eye{width:auto;padding:0 4px 14px;border:0;background:none;box-shadow:none;color:#a6aed0;font-size:13px;font-weight:600}
.err{background:rgba(242,84,107,.14);color:#ffb3bf;border:1px solid rgba(242,84,107,.4);padding:10px;border-radius:12px;font-size:13px;margin-bottom:14px}
@media(prefers-reduced-motion:reduce){.sky::before,.sky::after{animation:none}}
</style></head><body>
<div class="sky" aria-hidden="true"></div>
<form class="card" method="post">
  <div style="font-size:44px">👑</div><h1>پنل مدیریت</h1><p class="sub">نامبیکس | Numbix — ربات خدمات مجازی</p>
  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <?php if ($otp): ?>
  <p class="sub" style="margin-bottom:16px">کدِ تاییدِ ۶ رقمی به تلگرامِ مدیر فرستاده شد.</p>
  <input type="text" name="otp" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="کدِ تایید" dir="ltr"
         autocomplete="one-time-code" autocapitalize="off" autocorrect="off" spellcheck="false" autofocus required>
  <button type="submit">تایید و ورود</button>
  <?php else: ?>
  <input type="email" name="email" placeholder="ایمیلِ مدیر" dir="ltr" autocomplete="username"
         autocapitalize="off" autocorrect="off" spellcheck="false" autofocus required>
  <input type="text" name="phone" inputmode="tel" placeholder="شماره‌ی موبایل" dir="ltr" autocomplete="tel"
         autocapitalize="off" autocorrect="off" spellcheck="false" required>
  <input id="pw" type="password" name="password" placeholder="رمز عبور" dir="ltr" autocomplete="current-password"
         autocapitalize="off" autocorrect="off" spellcheck="false" required>
  <button type="button" class="eye" onclick="var p=document.getElementById('pw');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'👁 نمایش رمز':'🙈 پنهان کردن رمز'">👁 نمایش رمز</button>
  <button type="submit">ورود</button>
  <?php endif; ?>
</form></body></html>
<?php }

if (isset($_GET['logout'])) {
    $_SESSION = []; session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
}

if (!empty($_SESSION['logged_in'])) {
    $seen = (int)($_SESSION['seen'] ?? 0);
    if ($seen > 0 && time() - $seen > PANEL_IDLE_SECONDS) {
        $_SESSION = []; session_destroy(); session_start();
    } elseif (!defined('PANEL_PASSIVE')) {
        $_SESSION['seen'] = time();
    }
}

if (empty($_SESSION['logged_in'])) {
    $err = '';
    $method = ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $left = panelLockLeft();
    $otpStage = !empty($_SESSION['otp_stage']) && is_array($_SESSION['otp'] ?? null);
    $panelDone = function () {
        panelClearFails();
        unset($_SESSION['otp'], $_SESSION['otp_stage']);
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['seen'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
    };

    if ($left > 0) {
        $err = 'به‌خاطر تلاش‌های ناموفق، ورود تا ' . ceil($left / 60) . ' دقیقه دیگر بسته است.';
        unset($_SESSION['otp_stage'], $_SESSION['otp']);
        $otpStage = false;
    } elseif ($method === 'POST' && $otpStage && isset($_POST['otp'])) {
        if (panelOtpOk($_POST['otp'])) $panelDone();
        panelNoteFail();
        usleep(300000);
        $left = panelLockLeft();
        if (empty($_SESSION['otp'])) { unset($_SESSION['otp_stage']); $otpStage = false; }
        $err = $left > 0 ? 'کد اشتباه بود؛ ورود چند دقیقه بسته شد.'
                         : ($otpStage ? 'کدِ تایید اشتباه است.' : 'کدِ تایید منقضی شد؛ دوباره وارد شوید.');
    } elseif ($method === 'POST' && isset($_POST['password'])) {
        if (panelPassVerify($_POST['password']) && panelEmailMatch($_POST['email'] ?? '') && panelPhoneMatch($_POST['phone'] ?? '')) {
            if (panelOtpAvailable()) {
                if (panelOtpSend()) { $_SESSION['otp_stage'] = true; renderLogin('', true); exit; }
                $err = 'ارسالِ کدِ تایید به تلگرام ناموفق بود؛ چند لحظه بعد دوباره تلاش کنید.';
            } else {
                $panelDone();
            }
        } else {
            panelNoteFail();
            usleep(400000);
            $left = panelLockLeft();
            $err = $left > 0
                ? 'اطلاعات اشتباه بود. ورود تا ' . ceil($left / 60) . ' دقیقه دیگر بسته شد.'
                : 'ایمیل، شماره یا رمز درست نیست.';
        }
    }
    renderLogin($err, $otpStage && $left <= 0); exit;
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];
