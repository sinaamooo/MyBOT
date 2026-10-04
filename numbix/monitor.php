<?php
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

if (is_file(__DIR__ . '/config.local.php')) require_once __DIR__ . '/config.local.php';
define('MEMBERSHIP_LIB_ONLY', true);
if (isset($_GET['api'])) define('PANEL_PASSIVE', true);
require_once __DIR__ . '/bot_master_membership.php';
monSkip();
require_once nbMod('panel_auth');
session_write_close();

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $msg = null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); echo '{"ok":false}'; exit; }
        $do = (string)($_POST['do'] ?? '');
        try {
            if ($do === 'bal') { monBalances(true); $msg = [true, 'موجودی‌ها تازه شد.']; }
            elseif ($do === 'recheck') { drWebhook(true); drLeak(true); monBalances(true); $msg = [true, 'همه‌چیز از نو بررسی شد.']; }
            elseif ($do === 'webhook') $msg = drFixWebhook();
        } catch (Throwable $e) {
            error_log('[monitor] ' . monMask($e->getMessage()));
            $msg = [false, 'انجام نشد: ' . $e->getMessage()];
        }
    }
    $cf = rtrim(DATA_DIR, '/') . '/.mon_snap.json';
    if ($msg === null && is_file($cf) && time() - (int)@filemtime($cf) < 3 && ($raw = @file_get_contents($cf)) !== false && $raw !== '') {
        echo $raw;
        exit;
    }
    try {
        monNoNet(true);
        $out = ['ok' => true] + monSnapshot();
        monNoNet(false);
        if ($msg) $out['msg'] = ['ok' => (bool)$msg[0], 'text' => monMask((string)$msg[1])];
        $raw = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($msg === null) @file_put_contents($cf, $raw, LOCK_EX);
        echo $raw;
    } catch (Throwable $e) {
        monNoNet(false);
        error_log('[monitor] ' . monMask($e->getMessage()));
        http_response_code(500);
        echo '{"ok":false}';
        exit;
    }
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
    ignore_user_abort(true);
    try { monBalances(); drWebhook(); } catch (Throwable $e) { error_log('[monitor] ' . monMask($e->getMessage())); }
    exit;
}

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-$nonce'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
       "font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark"><meta name="robots" content="noindex, nofollow">
<link rel="icon" href="data:,">
<title>رصدِ زنده — نامبیکس</title>
<style>
<?= panelFontCss() ?>
:root{
  --page:#04060c;--ink:#f4f6fb;--ink-2:#bcc5d8;--muted:#7f8aa0;--grid:#1c2335;--line:rgba(255,255,255,.09);--line-2:rgba(255,255,255,.16);
  --good:#0ca30c;--warn:#fab219;--crit:#d03b3b;--idle:#5b6478;--accent:#3987e5;--r:20px;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--page);color:var(--ink)}
body{font-family:'Vazirmatn','Vazir',Tahoma,system-ui,'Segoe UI',sans-serif;font-size:14px;line-height:1.75;-webkit-font-smoothing:antialiased;min-height:100vh;overflow-x:hidden}
html{background:var(--page)}body{background:transparent}
.sky{position:fixed;inset:0;z-index:-1;pointer-events:none;overflow:hidden;contain:strict;background:
  radial-gradient(1100px 700px at 88% -12%,rgba(124,108,255,.24),transparent 60%),
  radial-gradient(800px 600px at -5% 18%,rgba(34,184,230,.15),transparent 60%),
  radial-gradient(1000px 800px at 50% 125%,rgba(219,70,160,.11),transparent 60%),linear-gradient(180deg,#05060f,#070a1a 52%,#04050c)}
.sky::before,.sky::after{content:'';position:absolute;inset:0;will-change:opacity;animation:tw 6s ease-in-out infinite alternate}
.sky::before{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='520' height='520'%3E%3Ccircle cx='168' cy='78' r='0.5' fill='%23bfe6ff' opacity='0.40'/%3E%3Ccircle cx='49' cy='303' r='0.8' fill='%23cfd8ff' opacity='0.37'/%3E%3Ccircle cx='217' cy='125' r='1.1' fill='%23bfe6ff' opacity='0.39'/%3E%3Ccircle cx='64' cy='116' r='0.5' fill='%23cfd8ff' opacity='0.73'/%3E%3Ccircle cx='26' cy='115' r='0.7' fill='%23fff' opacity='0.54'/%3E%3Ccircle cx='281' cy='297' r='0.7' fill='%23bfe6ff' opacity='0.42'/%3E%3Ccircle cx='332' cy='194' r='0.6' fill='%23bfe6ff' opacity='0.72'/%3E%3Ccircle cx='107' cy='354' r='1.1' fill='%23cfd8ff' opacity='0.86'/%3E%3Ccircle cx='304' cy='236' r='0.9' fill='%23fff' opacity='0.51'/%3E%3Ccircle cx='363' cy='127' r='0.9' fill='%23fff' opacity='0.69'/%3E%3Ccircle cx='379' cy='150' r='0.6' fill='%23cfd8ff' opacity='0.43'/%3E%3Ccircle cx='86' cy='178' r='1.3' fill='%23ffd9f3' opacity='0.62'/%3E%3Ccircle cx='40' cy='290' r='1.0' fill='%23fff' opacity='0.57'/%3E%3Ccircle cx='309' cy='302' r='1.3' fill='%23fff' opacity='0.39'/%3E%3Ccircle cx='491' cy='247' r='0.6' fill='%23ffd9f3' opacity='0.39'/%3E%3Ccircle cx='161' cy='301' r='1.3' fill='%23cfd8ff' opacity='0.53'/%3E%3Ccircle cx='461' cy='180' r='1.3' fill='%23bfe6ff' opacity='0.58'/%3E%3Ccircle cx='61' cy='31' r='0.9' fill='%23fff' opacity='0.43'/%3E%3Ccircle cx='207' cy='477' r='1.3' fill='%23cfd8ff' opacity='0.40'/%3E%3Ccircle cx='209' cy='144' r='0.7' fill='%23bfe6ff' opacity='0.88'/%3E%3Ccircle cx='145' cy='216' r='1.0' fill='%23cfd8ff' opacity='0.79'/%3E%3Ccircle cx='498' cy='78' r='0.7' fill='%23ffd9f3' opacity='0.45'/%3E%3Ccircle cx='121' cy='252' r='0.7' fill='%23fff' opacity='0.52'/%3E%3Ccircle cx='76' cy='278' r='1.0' fill='%23ffd9f3' opacity='0.97'/%3E%3Ccircle cx='447' cy='494' r='0.5' fill='%23ffd9f3' opacity='0.65'/%3E%3Ccircle cx='415' cy='204' r='1.1' fill='%23cfd8ff' opacity='0.61'/%3E%3Ccircle cx='330' cy='32' r='0.6' fill='%23cfd8ff' opacity='0.99'/%3E%3Ccircle cx='84' cy='177' r='0.5' fill='%23bfe6ff' opacity='0.42'/%3E%3Ccircle cx='79' cy='53' r='1.0' fill='%23fff' opacity='0.75'/%3E%3Ccircle cx='455' cy='319' r='0.7' fill='%23fff' opacity='0.76'/%3E%3Ccircle cx='313' cy='247' r='0.6' fill='%23cfd8ff' opacity='0.90'/%3E%3Ccircle cx='250' cy='162' r='0.7' fill='%23fff' opacity='0.42'/%3E%3Ccircle cx='385' cy='249' r='0.7' fill='%23fff' opacity='0.69'/%3E%3Ccircle cx='495' cy='275' r='0.7' fill='%23fff' opacity='0.80'/%3E%3Ccircle cx='394' cy='155' r='0.6' fill='%23fff' opacity='0.80'/%3E%3Ccircle cx='270' cy='472' r='1.0' fill='%23bfe6ff' opacity='0.85'/%3E%3Ccircle cx='282' cy='261' r='0.8' fill='%23fff' opacity='0.75'/%3E%3Ccircle cx='419' cy='426' r='0.8' fill='%23cfd8ff' opacity='0.48'/%3E%3Ccircle cx='185' cy='15' r='0.5' fill='%23cfd8ff' opacity='0.86'/%3E%3Ccircle cx='135' cy='360' r='1.0' fill='%23ffd9f3' opacity='0.64'/%3E%3Ccircle cx='514' cy='497' r='1.0' fill='%23fff' opacity='0.40'/%3E%3Ccircle cx='118' cy='102' r='0.8' fill='%23bfe6ff' opacity='0.66'/%3E%3Ccircle cx='437' cy='249' r='1.0' fill='%23fff' opacity='0.87'/%3E%3Ccircle cx='434' cy='62' r='1.1' fill='%23fff' opacity='0.86'/%3E%3Ccircle cx='249' cy='93' r='1.0' fill='%23ffd9f3' opacity='0.41'/%3E%3Ccircle cx='206' cy='209' r='0.6' fill='%23fff' opacity='0.82'/%3E%3Ccircle cx='516' cy='14' r='1.3' fill='%23fff' opacity='0.87'/%3E%3Ccircle cx='318' cy='310' r='1.3' fill='%23fff' opacity='0.78'/%3E%3Ccircle cx='81' cy='285' r='0.5' fill='%23ffd9f3' opacity='0.36'/%3E%3Ccircle cx='338' cy='274' r='0.7' fill='%23fff' opacity='0.63'/%3E%3Ccircle cx='430' cy='110' r='0.9' fill='%23bfe6ff' opacity='0.49'/%3E%3Ccircle cx='125' cy='305' r='0.9' fill='%23fff' opacity='0.70'/%3E%3Ccircle cx='32' cy='385' r='1.3' fill='%23bfe6ff' opacity='0.78'/%3E%3Ccircle cx='219' cy='477' r='0.7' fill='%23bfe6ff' opacity='0.70'/%3E%3Ccircle cx='265' cy='454' r='0.7' fill='%23fff' opacity='0.75'/%3E%3Ccircle cx='90' cy='246' r='0.6' fill='%23fff' opacity='0.71'/%3E%3Ccircle cx='355' cy='276' r='1.3' fill='%23fff' opacity='0.86'/%3E%3Ccircle cx='459' cy='30' r='0.8' fill='%23fff' opacity='0.53'/%3E%3Ccircle cx='264' cy='292' r='0.6' fill='%23bfe6ff' opacity='0.64'/%3E%3Ccircle cx='506' cy='315' r='0.8' fill='%23cfd8ff' opacity='0.80'/%3E%3Ccircle cx='264' cy='420' r='0.8' fill='%23fff' opacity='0.80'/%3E%3Ccircle cx='480' cy='464' r='0.8' fill='%23fff' opacity='0.90'/%3E%3Ccircle cx='217' cy='204' r='1.0' fill='%23fff' opacity='0.40'/%3E%3Ccircle cx='223' cy='111' r='0.9' fill='%23fff' opacity='0.86'/%3E%3Ccircle cx='489' cy='335' r='1.0' fill='%23fff' opacity='0.44'/%3E%3Ccircle cx='503' cy='114' r='0.6' fill='%23cfd8ff' opacity='0.61'/%3E%3Ccircle cx='85' cy='347' r='0.8' fill='%23cfd8ff' opacity='0.45'/%3E%3Ccircle cx='517' cy='210' r='1.1' fill='%23fff' opacity='0.48'/%3E%3Ccircle cx='48' cy='190' r='1.0' fill='%23cfd8ff' opacity='0.71'/%3E%3Ccircle cx='366' cy='200' r='0.9' fill='%23fff' opacity='0.68'/%3E%3C/svg%3E");background-size:520px 520px}
.sky::after{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='860' height='860'%3E%3Ccircle cx='389' cy='481' r='2.0' fill='%23bfe6ff' opacity='0.64'/%3E%3Ccircle cx='163' cy='691' r='2.0' fill='%23fff' opacity='0.76'/%3E%3Ccircle cx='81' cy='261' r='0.9' fill='%23ffd9f3' opacity='0.70'/%3E%3Ccircle cx='546' cy='512' r='1.7' fill='%23ffd9f3' opacity='0.98'/%3E%3Ccircle cx='635' cy='559' r='0.8' fill='%23fff' opacity='0.89'/%3E%3Ccircle cx='51' cy='164' r='1.2' fill='%23cfd8ff' opacity='0.74'/%3E%3Ccircle cx='281' cy='508' r='1.2' fill='%23ffd9f3' opacity='0.69'/%3E%3Ccircle cx='253' cy='4' r='0.9' fill='%23fff' opacity='0.65'/%3E%3Ccircle cx='350' cy='474' r='0.9' fill='%23fff' opacity='0.81'/%3E%3Ccircle cx='652' cy='441' r='0.8' fill='%23fff' opacity='0.40'/%3E%3Ccircle cx='344' cy='728' r='1.7' fill='%23fff' opacity='0.39'/%3E%3Ccircle cx='729' cy='0' r='1.2' fill='%23fff' opacity='0.95'/%3E%3Ccircle cx='404' cy='843' r='1.7' fill='%23bfe6ff' opacity='0.62'/%3E%3Ccircle cx='541' cy='670' r='1.4' fill='%23fff' opacity='0.57'/%3E%3Ccircle cx='286' cy='829' r='0.9' fill='%23ffd9f3' opacity='0.44'/%3E%3Ccircle cx='87' cy='52' r='2.0' fill='%23bfe6ff' opacity='0.47'/%3E%3Ccircle cx='162' cy='438' r='1.0' fill='%23cfd8ff' opacity='0.62'/%3E%3Ccircle cx='100' cy='362' r='1.2' fill='%23bfe6ff' opacity='0.35'/%3E%3Ccircle cx='262' cy='761' r='1.2' fill='%23bfe6ff' opacity='0.47'/%3E%3Ccircle cx='552' cy='86' r='1.0' fill='%23fff' opacity='0.49'/%3E%3Ccircle cx='8' cy='525' r='1.4' fill='%23fff' opacity='0.60'/%3E%3Ccircle cx='78' cy='501' r='1.2' fill='%23fff' opacity='0.36'/%3E%3Ccircle cx='320' cy='390' r='2.0' fill='%23fff' opacity='0.89'/%3E%3Ccircle cx='745' cy='157' r='1.0' fill='%23fff' opacity='0.55'/%3E%3Ccircle cx='703' cy='215' r='1.2' fill='%23ffd9f3' opacity='0.45'/%3E%3Ccircle cx='809' cy='169' r='1.7' fill='%23bfe6ff' opacity='0.92'/%3E%3Ccircle cx='68' cy='41' r='0.9' fill='%23fff' opacity='0.38'/%3E%3Ccircle cx='205' cy='606' r='1.4' fill='%23bfe6ff' opacity='0.62'/%3E%3Ccircle cx='422' cy='447' r='0.9' fill='%23cfd8ff' opacity='0.43'/%3E%3Ccircle cx='481' cy='733' r='0.9' fill='%23fff' opacity='0.53'/%3E%3Ccircle cx='644' cy='59' r='1.7' fill='%23fff' opacity='0.64'/%3E%3Ccircle cx='40' cy='242' r='1.0' fill='%23fff' opacity='0.41'/%3E%3Ccircle cx='766' cy='843' r='1.0' fill='%23fff' opacity='0.73'/%3E%3Ccircle cx='408' cy='307' r='1.4' fill='%23fff' opacity='0.98'/%3E%3C/svg%3E");background-size:860px 860px;animation-duration:9s;animation-delay:-4s}
@keyframes tw{from{opacity:.3}to{opacity:.85}}
a{color:#9cc3f5;text-decoration:none}
<?= panelGlassCss() ?>
.top{position:sticky;top:0;z-index:30;display:flex;align-items:center;gap:10px;padding:10px 20px;
  background:rgba(6,8,20,.94);border-bottom:1px solid var(--line)}
.top::after{content:'';position:absolute;inset-inline:0;bottom:-1px;height:1px;background:linear-gradient(90deg,#7c6cff,#4f7bff 55%,#22b8e6);opacity:.55}
.brand{display:flex;align-items:center;gap:10px;font-weight:900;font-size:16px;white-space:nowrap}
.sp{flex:1}
.live{display:inline-flex;align-items:center;gap:8px;padding:5px 12px;border-radius:999px;border:1px solid var(--line-2);background:rgba(255,255,255,.04);font-size:12.5px;color:var(--ink-2);white-space:nowrap}
.live i{width:8px;height:8px;border-radius:50%;background:var(--good);animation:pulse 1.8s infinite}
.live.off i{background:var(--crit);animation:none}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(12,163,12,.55)}70%{box-shadow:0 0 0 9px rgba(12,163,12,0)}100%{box-shadow:0 0 0 0 rgba(12,163,12,0)}}
.btn{display:inline-flex;align-items:center;gap:8px;padding:5px 12px 5px 6px;border-radius:13px;border:1px solid var(--line-2);
  background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02));color:var(--ink);font:inherit;font-size:12.5px;font-weight:800;cursor:pointer;white-space:nowrap;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.12)}
.btn:hover{background:linear-gradient(180deg,rgba(255,255,255,.13),rgba(255,255,255,.04))}
.btn[disabled]{opacity:.5;cursor:wait}
.btn .gi{width:26px;height:26px;border-radius:9px}.btn .gi svg{width:14px;height:14px}
.wrap{max-width:1560px;margin:0 auto;padding:18px 20px 44px}
.card{position:relative;background:linear-gradient(180deg,rgba(20,25,52,.86),rgba(11,14,32,.9));border:1px solid rgba(150,160,255,.13);border-radius:var(--r);
  box-shadow:0 24px 60px -34px rgba(0,0,0,.95),inset 0 1px 0 rgba(255,255,255,.06);min-width:0}
.card h2{display:flex;align-items:center;gap:10px;font-size:15px;font-weight:900;padding:14px 16px 0}
.card h2 small{font-weight:600;color:var(--muted);font-size:12px;margin-inline-start:auto}
.card .body{padding:12px 16px 16px}
.mb{margin-bottom:14px}
.doc-head{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:18px;align-items:center;padding:18px}
.ring{position:relative;width:112px;height:112px}
.ring svg{width:112px;height:112px;transform:rotate(-90deg)}
.ring circle{fill:none;stroke-width:10;stroke-linecap:round}
.ring .rt{stroke:rgba(255,255,255,.08)}
.ring .rv{stroke:var(--good);transition:stroke-dasharray .8s ease,stroke .3s}
.ring .rn{position:absolute;inset:0;display:grid;place-items:center;text-align:center;line-height:1.1}
.ring .rn b{font-size:30px;font-weight:900}.ring .rn small{display:block;color:var(--muted);font-size:11px;font-weight:700}
.doc-t h2{padding:0;font-size:18px}
.doc-t p{color:var(--ink-2);margin:4px 0 10px;font-weight:700}
.checks{display:flex;flex-wrap:wrap;gap:6px}
.chk{display:inline-flex;align-items:center;gap:6px;padding:3px 10px 3px 8px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid var(--line-2);background:rgba(255,255,255,.03);color:var(--ink-2)}
.chk b{width:18px;height:18px;border-radius:50%;display:grid;place-items:center;font-size:11px;color:#04060c}
.chk.ok b{background:var(--good)}.chk.slow b{background:var(--warn)}.chk.down b{background:var(--crit);color:#fff}
.doc-a{display:flex;flex-direction:column;gap:8px}
.finds{display:flex;flex-direction:column;gap:8px;padding:0 18px 18px}
.fd{border-radius:16px;border:1px solid var(--line);background:rgba(255,255,255,.025);overflow:hidden}
.fd.crit{border-color:rgba(208,59,59,.45);background:linear-gradient(90deg,rgba(208,59,59,.10),rgba(255,255,255,.02) 60%)}
.fd.warn{border-color:rgba(250,178,25,.35);background:linear-gradient(90deg,rgba(250,178,25,.07),rgba(255,255,255,.02) 60%)}
.fh{display:flex;align-items:center;gap:12px;width:100%;padding:10px 12px;background:none;border:0;color:inherit;font:inherit;text-align:right;cursor:pointer}
.fh .tt{flex:1;min-width:0;font-weight:800;font-size:14px}
.fh .cv{width:20px;height:20px;flex:none;fill:none;stroke:var(--muted);stroke-width:2;transition:transform .2s}
.fd.open .fh .cv{transform:rotate(180deg)}
.fb{display:none;padding:0 14px 14px 14px;margin-inline-start:46px}
.fd.open .fb{display:block}
.facts{white-space:pre-wrap;word-break:break-word;color:var(--ink-2);font-size:13px;background:rgba(0,0,0,.22);border:1px solid var(--line);border-radius:12px;padding:9px 12px}
.imp{margin-top:8px;font-size:13px;color:var(--ink-2)}.imp b{color:var(--ink)}
.steps{margin:10px 0 0;padding-inline-start:22px;font-size:13.5px}
.steps li{margin:4px 0;white-space:pre-wrap;word-break:break-word}
.steps li::marker{color:#9cc3f5;font-weight:900}
.fa{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.allok{display:flex;align-items:center;gap:12px;margin:0 18px 18px;padding:14px;border-radius:16px;border:1px solid rgba(12,163,12,.35);background:linear-gradient(90deg,rgba(12,163,12,.10),rgba(255,255,255,.02));font-weight:800}
.pill{display:inline-flex;align-items:center;gap:5px;padding:1px 10px;border-radius:999px;font-size:11.5px;font-weight:800;border:1px solid;white-space:nowrap}
.pill.ok{color:#7ee07e;border-color:rgba(12,163,12,.45);background:rgba(12,163,12,.12)}
.pill.slow{color:#ffd270;border-color:rgba(250,178,25,.45);background:rgba(250,178,25,.12)}
.pill.down{color:#ff9a9a;border-color:rgba(208,59,59,.55);background:rgba(208,59,59,.16)}
.pill.idle{color:var(--ink-2);border-color:var(--line-2);background:rgba(255,255,255,.04)}
.pill.info{color:#9cc3f5;border-color:rgba(57,135,229,.45);background:rgba(57,135,229,.12)}
.galaxy{height:clamp(560px,74vh,880px);overflow:hidden}
.galaxy canvas{position:absolute;inset:0;width:100%;height:100%;display:block;border-radius:var(--r);touch-action:pan-y}
.ghead{position:absolute;top:14px;inset-inline:16px;display:flex;align-items:center;gap:10px;z-index:2;pointer-events:none}
.ghead b{font-size:16px;font-weight:900;white-space:nowrap;text-shadow:0 2px 12px rgba(0,0,0,.8)}
.ghead .pill{min-width:0;overflow:hidden;text-overflow:ellipsis}
.ghead .btn{pointer-events:auto;margin-inline-start:auto}
.legend{position:absolute;bottom:12px;inset-inline:16px;display:flex;flex-wrap:wrap;gap:6px 14px;z-index:2;font-size:12px;color:var(--ink-2);pointer-events:none;text-shadow:0 1px 6px rgba(0,0,0,.9)}
.legend span{display:inline-flex;align-items:center;gap:6px}
.dot{width:10px;height:10px;border-radius:50%;display:inline-block;flex:none}
.tip{position:absolute;z-index:5;pointer-events:none;min-width:210px;max-width:270px;padding:11px 13px;border-radius:14px;
  background:rgba(10,13,32,.96);border:1px solid var(--line-2);
  box-shadow:0 20px 44px -14px rgba(0,0,0,.95);font-size:12.5px;line-height:1.8;opacity:0;transform:translateY(4px);transition:opacity .14s,transform .14s}
.tip.on{opacity:1;transform:none}
.tip .t{display:flex;align-items:center;gap:8px;font-weight:900;font-size:13.5px;margin-bottom:6px}
.tip .r{display:flex;justify-content:space-between;gap:12px;color:var(--ink-2)}.tip .r b{color:var(--ink);font-weight:800}
.kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
.kpi{padding:14px 16px;overflow:hidden}
.kpi .h{display:flex;align-items:center;gap:10px;color:var(--ink-2);font-size:12.5px;font-weight:800}
.kpi .v{font-size:28px;font-weight:900;line-height:1.25;margin-top:8px;letter-spacing:-.3px}
.kpi .s{color:var(--muted);font-size:12px;min-height:20px}
.kpi svg.sp{display:block;width:100%;height:30px;margin-top:6px}
.g3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.g2{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:14px}
.list{display:flex;flex-direction:column;gap:2px}
.row{display:grid;grid-template-columns:minmax(0,1.6fr) auto minmax(56px,.8fr) auto;align-items:center;gap:10px;padding:7px 8px;border-radius:13px;cursor:pointer;border:1px solid transparent}
.row:hover,.row.sel{background:rgba(255,255,255,.05);border-color:var(--line)}
.row .n{display:flex;align-items:center;gap:10px;min-width:0;font-weight:800}
.row .n span:last-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.row .m{color:var(--ink-2);font-size:12px;white-space:nowrap}
.row svg.sp{width:100%;height:24px;display:block}
.tw{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:12.5px}
th{color:var(--muted);font-weight:700;text-align:right;padding:6px 8px;border-bottom:1px solid var(--grid);white-space:nowrap}
td{padding:7px 8px;border-bottom:1px solid rgba(255,255,255,.04);white-space:nowrap}
td.num,th.num{text-align:left;font-variant-numeric:tabular-nums}
.bal{display:flex;flex-direction:column;gap:10px}
.b{padding:12px;border-radius:16px;border:1px solid var(--line);background:rgba(255,255,255,.03)}
.b .h{display:flex;align-items:center;gap:10px;font-weight:800}.b .h .pill{margin-inline-start:auto}
.b .v{font-size:22px;font-weight:900;margin:4px 0 0}
.b .s{color:var(--muted);font-size:12px}
.tiles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.tile{padding:10px 12px;border-radius:16px;border:1px solid var(--line);background:rgba(255,255,255,.03)}
.tile .l{color:var(--ink-2);font-size:12px;font-weight:700}.tile .v{font-size:20px;font-weight:900}
.meter{height:8px;border-radius:8px;background:rgba(57,135,229,.18);overflow:hidden;margin-top:6px}
.meter i{display:block;height:100%;border-radius:8px;background:var(--accent)}
.meter.warn{background:rgba(250,178,25,.18)}.meter.warn i{background:var(--warn)}
.meter.crit{background:rgba(208,59,59,.2)}.meter.crit i{background:var(--crit)}
.kv{display:grid;grid-template-columns:auto minmax(0,1fr);gap:4px 12px;font-size:12.5px}
.kv dt{color:var(--muted)}.kv dd{text-align:left;font-weight:700;overflow:hidden;text-overflow:ellipsis;unicode-bidi:plaintext}
.logs{display:flex;flex-direction:column;gap:6px;max-height:460px;overflow:auto}
.log{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:10px;align-items:start;padding:9px 11px;border-radius:13px;border:1px solid var(--line);background:rgba(255,255,255,.025)}
.log code{direction:ltr;text-align:left;display:block;white-space:pre-wrap;word-break:break-word;font:12px/1.65 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:var(--ink-2)}
.log .c{color:var(--muted);font-size:11.5px;text-align:left;white-space:nowrap}
.chips{display:flex;gap:6px;margin-inline-start:auto}
.chip{padding:2px 10px;border-radius:999px;border:1px solid var(--line-2);background:transparent;color:var(--ink-2);font:inherit;font-size:11.5px;cursor:pointer}
.chip.on{background:rgba(57,135,229,.16);border-color:rgba(57,135,229,.6);color:var(--ink)}
.empty{color:var(--muted);font-size:13px;padding:14px 4px;text-align:center}
.why{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.note{color:var(--muted);font-size:12px;margin-top:10px}
.stale{opacity:.6;transition:opacity .3s}
.toast{position:fixed;bottom:22px;left:50%;transform:translate(-50%,20px);z-index:50;padding:11px 18px;border-radius:14px;max-width:min(92vw,560px);
  background:rgba(12,16,38,.96);border:1px solid var(--line-2);
  font-weight:800;font-size:13.5px;opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;box-shadow:0 20px 50px -20px #000}
.toast.on{opacity:1;transform:translate(-50%,0)}
.toast.bad{border-color:rgba(208,59,59,.6)}
@media (max-width:1180px){.kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.g3{grid-template-columns:repeat(2,minmax(0,1fr))}.g2{grid-template-columns:minmax(0,1fr)}}
@media (max-width:760px){
  .top{padding:10px 16px;flex-wrap:wrap}.top .btn span.tx{display:none}.wrap{padding:14px 16px 32px}
  .doc-head{grid-template-columns:auto minmax(0,1fr);gap:14px;padding:14px}.doc-a{grid-column:1/-1;flex-direction:row;flex-wrap:wrap}
  .ring,.ring svg{width:88px;height:88px}.ring .rn b{font-size:24px}
  .finds{padding:0 12px 14px}.fb{margin-inline-start:0}
  .kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.kpi .v{font-size:23px}
  .g3{grid-template-columns:minmax(0,1fr)}.galaxy{height:clamp(470px,64vh,560px)}.ghead{inset-inline:12px;gap:8px}.ghead .btn span.tx{display:none}.ghead .btn{padding:4px}
  .row{grid-template-columns:minmax(0,1fr) auto auto}.row svg.sp{display:none}
}
</style></head>
<body>
<div class="sky" aria-hidden="true"></div>
<header class="top">
  <div class="brand"><?= mi('radar', 'blue') ?><span>رصدِ زنده · نامبیکس</span></div>
  <span class="live" id="live"><i></i><span id="liveT">در حالِ اتصال…</span></span>
  <div class="sp"></div>
  <button class="btn" id="balBtn" type="button"><?= mi('coins', 'cyan') ?><span class="tx">موجودی‌ها</span></button>
  <a class="btn" href="admin_panel.php"><?= mi('gear', 'violet') ?><span class="tx">پنلِ تنظیمات</span></a>
  <a class="btn" href="admin_panel.php?logout=1"><?= mi('logout', 'pink') ?><span class="tx">خروج</span></a>
</header>

<main class="wrap" id="app">
  <section class="card mb" id="doc">
    <div class="doc-head">
      <div class="ring" role="img" aria-label="امتیازِ سلامت"><svg viewBox="0 0 120 120"><circle class="rt" cx="60" cy="60" r="52"/><circle class="rv" id="ringV" cx="60" cy="60" r="52" stroke-dasharray="0 327"/></svg>
        <div class="rn"><div><b id="score">—</b><small>سلامت از ۱۰۰</small></div></div></div>
      <div class="doc-t"><h2><?= mi('steth', 'cyan', 'lg') ?> دستیارِ عیب‌یاب</h2><p id="docSum">در حالِ بررسیِ همه‌ی بخش‌ها…</p><div class="checks" id="checks"></div></div>
      <div class="doc-a">
        <button class="btn" id="reBtn" type="button"><?= mi('refresh', 'blue') ?><span>بررسیِ دوباره</span></button>
        <button class="btn" id="cpBtn" type="button"><?= mi('copy', 'violet') ?><span>کپیِ گزارش</span></button>
      </div>
    </div>
    <div class="finds" id="finds"></div>
  </section>

  <section class="card galaxy mb" id="gal">
    <canvas id="cv" role="img" aria-label="نقشه‌ی کهکشانیِ بخش‌های ربات؛ همان داده در جدولِ «وضعیتِ بخش‌ها» هست"></canvas>
    <div class="ghead"><?= mi('globe', 'violet') ?><b>کهکشانِ ربات</b><span class="pill idle" id="gNow">—</span>
      <button class="btn" id="pzBtn" type="button" aria-pressed="false"><?= mi('pause', 'blue') ?><span class="tx">توقف</span></button></div>
    <div class="legend">
      <span><i class="dot" style="background:var(--good)"></i>✓ سالم</span>
      <span><i class="dot" style="background:var(--warn)"></i>! کند</span>
      <span><i class="dot" style="background:var(--crit)"></i>✕ هنگ / خطا</span>
      <span><i class="dot" style="background:var(--idle)"></i>بی‌کار</span>
      <span>اندازه = ترافیک · ◆ = سرویسِ بیرونی · حلقه‌ی دورِ خورشید = سپر</span>
    </div>
    <div class="tip" id="tip" role="status"></div>
  </section>

  <section class="kpis mb">
    <div class="card kpi"><div class="h"><?= mi('bolt', 'blue', 'sm') ?>درخواست در دقیقه</div><div class="v" id="kRpm">—</div><div class="s">میانگینِ ۱۵ دقیقه‌ی اخیر</div><svg class="sp" id="kRpmG" viewBox="0 0 120 30" preserveAspectRatio="none" aria-hidden="true"></svg></div>
    <div class="card kpi"><div class="h"><?= mi('bug', 'pink', 'sm') ?>خطا در ۱۵ دقیقه</div><div class="v" id="kErr">—</div><div class="s" id="kErrS"></div></div>
    <div class="card kpi"><div class="h"><?= mi('gauge', 'violet', 'sm') ?>کندترین بخش (p95)</div><div class="v" id="kSlow">—</div><div class="s" id="kSlowS"></div></div>
    <div class="card kpi"><div class="h"><?= mi('shield', 'cyan', 'sm') ?>حمله و اسپمِ ردشده</div><div class="v" id="kBlk">—</div><div class="s" id="kBlkS">۱۵ دقیقه‌ی اخیر</div><svg class="sp" id="kBlkG" viewBox="0 0 120 30" preserveAspectRatio="none" aria-hidden="true"></svg></div>
    <div class="card kpi"><div class="h"><?= mi('users', 'blue', 'sm') ?>کاربرانِ ربات</div><div class="v" id="kUsr">—</div><div class="s" id="kUsrS"></div></div>
  </section>

  <section class="g3 mb">
    <div class="card"><h2><?= mi('layers', 'violet', 'sm') ?>وضعیتِ بخش‌ها <small>۱۵ دقیقه‌ی اخیر</small></h2><div class="body"><div class="list" id="plist"></div></div></div>
    <div class="card"><h2><?= mi('satellite', 'cyan', 'sm') ?>سرویس‌های بیرونی <small>p95 · ۱۵ دقیقه</small></h2><div class="body"><div class="list" id="xlist"></div></div></div>
    <div class="card"><h2><?= mi('coins', 'blue', 'sm') ?>بودجه و موجودی</h2><div class="body"><div class="bal" id="bal"></div></div></div>
  </section>

  <section class="g3 mb">
    <div class="card"><h2><?= mi('inbox', 'pink', 'sm') ?>صف‌ها</h2><div class="body"><div class="tiles" id="queues"></div></div></div>
    <div class="card"><h2><?= mi('shield', 'cyan', 'sm') ?>سپرِ امنیتی</h2><div class="body" id="shield"></div></div>
    <div class="card"><h2><?= mi('server', 'violet', 'sm') ?>سرور</h2><div class="body" id="sys"></div></div>
  </section>

  <section class="card mb"><h2><?= mi('gauge', 'pink', 'sm') ?>کندترین کارها <small>یک ساعتِ اخیر</small></h2>
    <div class="body tw"><table><thead><tr><th>بخش</th><th>کار</th><th class="num">تعداد</th><th class="num">میانگین</th><th class="num">p95</th><th class="num">بیشترین</th><th class="num">خطا</th></tr></thead><tbody id="slow"></tbody></table></div></section>

  <section class="card"><h2><?= mi('bug', 'crit', 'sm') ?>باگ‌ها و لاگ‌ها
    <span class="chips"><button class="chip on" type="button" data-f="all">همه</button><button class="chip" type="button" data-f="bad">فقط خطاها</button></span></h2>
    <div class="body"><div class="logs" id="logs"></div></div></section>
</main>
<div class="toast" id="toast" role="status"></div>

<script nonce="<?= h($nonce) ?>">
(function () {
  'use strict';
  var CSRF = <?= json_encode($CSRF) ?>;
  var IC = <?= json_encode(mIcons(), JSON_UNESCAPED_SLASHES) ?>;
  var ST = {ok: ['سالم', '✓', '#0ca30c'], slow: ['کند', '!', '#fab219'], down: ['هنگ / خطا', '✕', '#d03b3b'], idle: ['بی‌کار', '·', '#5b6478']};
  var SEV = {crit: ['فوری', 'down', 'crit'], warn: ['مهم', 'slow', 'warn'], info: ['دانستنی', 'info', 'blue']};
  var PIC = {numbers: 'sim', services: 'puzzle', wallet: 'wallet', games: 'game', tools: 'chart', groups: 'users', menu: 'chat', support: 'headset',
    airdrop: 'gift', admin: 'crown', cron: 'clock', media: 'image', shield: 'shield', other: 'spark'};
  var XIC = {telegram: 'plane', numprov: 'sim', smm_a: 'satellite', smm_b: 'satellite', prices: 'chart', gateway: 'card', translate: 'globe', web: 'link'};
  var TINT = ['blue', 'violet', 'cyan', 'pink'];
  var SHORT = {numbers: 'شماره مجازی', services: 'خدمات', wallet: 'کیف پول', games: 'بازی‌ها', tools: 'قیمت و ترجمه', groups: 'گروه‌ها', menu: 'منو و پیوی',
    support: 'پشتیبانی', airdrop: 'ایردراپ', admin: 'مدیریت', cron: 'پس‌زمینه', media: 'تصاویر', other: 'سایر'};
  var WHY = {secret: 'درخواستِ جعلی (بدونِ امضای تلگرام)', probe: 'بازدید و اسکنِ ناشناس', size: 'درخواستِ بیش‌ازحد بزرگ'};
  var INNER = ['menu', 'numbers', 'services', 'wallet', 'support', 'airdrop'];
  var MIDDLE = ['groups', 'games', 'tools', 'media', 'admin', 'cron', 'other'];
  var reduce = !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
  var D = null, sel = null, logF = 'all', failN = 0, OPEN = {}, SEEN = {};

  var $ = function (id) { return document.getElementById(id); };
  var fa = function (n, d) { return Number(n || 0).toLocaleString('fa-IR', {maximumFractionDigits: d == null ? 0 : d}); };
  var ms = function (v) { v = +v || 0; return v >= 1000 ? fa(v / 1000, 1) + ' ثانیه' : fa(v) + ' میلی‌ثانیه'; };
  var bytes = function (b) { b = +b || 0; var u = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'], i = 0; while (b >= 1024 && i < 4) { b /= 1024; i++; } return fa(b, i ? 1 : 0) + ' ' + u[i]; };
  var ago = function (t) { if (!t) return '—'; var s = Math.max(0, Math.round(Date.now() / 1000 - t)); return s < 60 ? fa(s) + ' ثانیه پیش' : s < 3600 ? fa(Math.round(s / 60)) + ' دقیقه پیش' : s < 86400 ? fa(Math.round(s / 3600)) + ' ساعت پیش' : fa(Math.round(s / 86400)) + ' روز پیش'; };
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function gi(name, tint, size) {
    var s = el('span', 'gi t-' + (tint || 'blue') + (size ? ' ' + size : '')); s.setAttribute('aria-hidden', 'true');
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); svg.setAttribute('viewBox', '0 0 24 24');
    var p = document.createElementNS('http://www.w3.org/2000/svg', 'path'); p.setAttribute('d', IC[name] || IC.spark);
    svg.appendChild(p); s.appendChild(svg); return s;
  }
  function pill(st, extra) { var s = ST[st] || ST.idle; return el('span', 'pill ' + st, s[1] + ' ' + (extra || s[0])); }
  function tintOf(k) { var i = Object.keys(PIC).indexOf(k); return TINT[(i < 0 ? 0 : i) % 4]; }
  function actName(a) {
    if (!a || a === '—') return '—';
    if (a.indexOf('cb_') === 0) return 'دکمه‌ی ' + a.slice(3);
    if (a.indexOf('page_') === 0) return 'صفحه‌ی ' + a.slice(5);
    if (a.indexOf('web_') === 0) return 'پنلِ وب: ' + a.slice(4);
    return ({cmd: 'دستور', text: 'پیامِ متنی', photo: 'عکس', file: 'فایل', member: 'عضو شدن/رفتن', flood: 'اسپمِ ردشده', ipflood: 'سیلِ درخواست از یک IP',
      bg: 'کارهای پس از پاسخ', cron: 'کران', ipn: 'تاییدِ درگاهِ رمزارز', irpay: 'بازگشت از درگاهِ ایرانی', avatar: 'عکسِ پروفایل', review: 'عکسِ نظر'})[a] || a;
  }
  function spark(svg, series, color) {
    while (svg.firstChild) svg.removeChild(svg.firstChild);
    var n = series.length, max = Math.max.apply(null, series.concat([1])), ns = 'http://www.w3.org/2000/svg', pts = [];
    for (var i = 0; i < n; i++) pts.push((i / (n - 1) * 120).toFixed(1) + ',' + (28 - series[i] / max * 24).toFixed(1));
    var area = document.createElementNS(ns, 'polygon'); area.setAttribute('points', '0,30 ' + pts.join(' ') + ' 120,30'); area.setAttribute('fill', color); area.setAttribute('fill-opacity', '.1');
    var line = document.createElementNS(ns, 'polyline'); line.setAttribute('points', pts.join(' ')); line.setAttribute('fill', 'none'); line.setAttribute('stroke', '#8a93a8');
    line.setAttribute('stroke-width', '2'); line.setAttribute('vector-effect', 'non-scaling-stroke'); line.setAttribute('stroke-linejoin', 'round'); line.setAttribute('stroke-linecap', 'round');
    var last = pts[n - 1].split(','), dot = document.createElementNS(ns, 'circle'); dot.setAttribute('cx', last[0]); dot.setAttribute('cy', last[1]); dot.setAttribute('r', '3'); dot.setAttribute('fill', color);
    svg.appendChild(area); svg.appendChild(line); svg.appendChild(dot);
  }
  function sparkSvg(series, color) { var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); s.setAttribute('class', 'sp'); s.setAttribute('viewBox', '0 0 120 30'); s.setAttribute('preserveAspectRatio', 'none'); s.setAttribute('aria-hidden', 'true'); spark(s, series, color); return s; }
  var tt = null;
  function toast(text, bad) { var t = $('toast'); t.textContent = text; t.className = 'toast on' + (bad ? ' bad' : ''); clearTimeout(tt); tt = setTimeout(function () { t.className = 'toast' + (bad ? ' bad' : ''); }, 4200); }

  function renderDoc() {
    var d = D.doctor; if (!d) return;
    var worst = d.crit ? 'crit' : d.warn ? 'warn' : 'good', C = 2 * Math.PI * 52, rv = $('ringV');
    rv.setAttribute('stroke-dasharray', (C * d.score / 100).toFixed(1) + ' ' + C.toFixed(1));
    rv.style.stroke = worst === 'crit' ? 'var(--crit)' : worst === 'warn' ? 'var(--warn)' : 'var(--good)';
    rv.style.color = rv.style.stroke;
    $('score').textContent = fa(d.score);
    $('docSum').textContent = (d.crit + d.warn) === 0 ? '✓ همه‌چیز سالم است — ' + fa(d.checks.length) + ' بخش بررسی شد' + (d.info ? ' · ' + fa(d.info) + ' نکته‌ی دانستنی' : '')
      : (d.crit ? fa(d.crit) + ' مشکلِ فوری' : '') + (d.crit && d.warn ? ' · ' : '') + (d.warn ? fa(d.warn) + ' مشکلِ مهم' : '') + (d.info ? ' · ' + fa(d.info) + ' دانستنی' : '') + ' — راه‌حلِ هرکدام زیرش آمده';
    var ch = $('checks'); ch.textContent = '';
    d.checks.forEach(function (c) { var s = el('span', 'chk ' + c.st); s.appendChild(el('b', null, c.st === 'ok' ? '✓' : c.st === 'slow' ? '!' : '✕')); s.appendChild(document.createTextNode(c.name)); ch.appendChild(s); });
    var fl = $('finds'); fl.textContent = '';
    if (!d.findings.length) {
      var ok = el('div', 'allok'); ok.appendChild(gi('check', 'good')); ok.appendChild(el('span', null, 'هیچ مشکلی پیدا نشد. اگر چیزی خراب شود، همین‌جا با راه‌حلِ مو به مو نوشته می‌شود و مشکل‌های فوری در تلگرام هم خبر داده می‌شوند.'));
      fl.appendChild(ok); return;
    }
    d.findings.forEach(function (f) {
      if (!SEEN[f.k]) { SEEN[f.k] = 1; if (f.sev === 'crit') OPEN[f.k] = 1; }
      var w = el('div', 'fd ' + f.sev + (OPEN[f.k] ? ' open' : '')), h = el('button', 'fh'); h.type = 'button'; h.setAttribute('aria-expanded', OPEN[f.k] ? 'true' : 'false');
      h.appendChild(gi(f.icon, SEV[f.sev][2], 'sm')); h.appendChild(el('span', 'tt', f.title)); h.appendChild(el('span', 'pill ' + SEV[f.sev][1], SEV[f.sev][0]));
      var cv = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); cv.setAttribute('class', 'cv'); cv.setAttribute('viewBox', '0 0 24 24');
      var cp = document.createElementNS('http://www.w3.org/2000/svg', 'path'); cp.setAttribute('d', IC.chev); cv.appendChild(cp); h.appendChild(cv);
      h.addEventListener('click', function () { OPEN[f.k] = !OPEN[f.k]; w.classList.toggle('open', !!OPEN[f.k]); h.setAttribute('aria-expanded', OPEN[f.k] ? 'true' : 'false'); });
      var b = el('div', 'fb');
      b.appendChild(el('div', 'facts', f.facts));
      if (f.impact) { var im = el('div', 'imp'); im.appendChild(el('b', null, 'اثر: ')); im.appendChild(document.createTextNode(f.impact)); b.appendChild(im); }
      var ol = el('ol', 'steps'); f.steps.forEach(function (s) { ol.appendChild(el('li', null, s)); }); b.appendChild(ol);
      var a = el('div', 'fa');
      if (f.act) { var ab = el('button', 'btn'); ab.type = 'button'; ab.dataset.act = f.act; ab.appendChild(gi(f.act === 'webhook' ? 'wrench' : 'refresh', f.act === 'webhook' ? 'good' : 'blue'));
        ab.appendChild(el('span', null, f.act === 'webhook' ? 'ثبتِ دوباره‌ی وبهوک' : 'بررسیِ دوباره')); a.appendChild(ab); }
      if (f.link) { var lk = el('a', 'btn'); lk.href = f.link; lk.appendChild(gi('arrow', 'violet')); lk.appendChild(el('span', null, 'رفتن به همان تنظیم')); a.appendChild(lk); }
      if (a.childNodes.length) b.appendChild(a);
      w.appendChild(h); w.appendChild(b); fl.appendChild(w);
    });
  }

  function render() {
    renderDoc();
    var P = D.planets, totRpm = 0, totErr = 0, sum = new Array(60).fill(0), slowest = null;
    P.forEach(function (p) {
      if (p.k === 'shield') return;
      totRpm += p.rpm; totErr += p.err;
      p.series.forEach(function (v, i) { sum[i] += v; });
      if (p.n15 > 0 && (!slowest || p.p95 > slowest.p95)) slowest = p;
    });
    $('kRpm').textContent = fa(totRpm, 1); spark($('kRpmG'), sum, '#3987e5');
    $('kErr').textContent = fa(totErr);
    var hang = P.reduce(function (a, p) { return a + p.hang; }, 0);
    $('kErrS').textContent = hang ? fa(hang) + ' درخواستِ هنگ‌کرده' : 'هیچ درخواستی هنگ نکرده';
    $('kSlow').textContent = slowest ? ms(slowest.p95) : '—';
    $('kSlowS').textContent = slowest ? slowest.name : 'ترافیکی نیست';
    var sh = P.filter(function (p) { return p.k === 'shield'; })[0] || {n15: 0, n24: 0, series: []};
    $('kBlk').textContent = fa(D.shield.b15 + sh.n15);
    spark($('kBlkG'), D.shield.blocked.map(function (v, i) { return v + (sh.series[i] || 0); }), '#fab219');
    $('kBlkS').textContent = '۲۴ ساعت: ' + fa(D.shield.b24 + (sh.n24 || 0));
    $('kUsr').textContent = D.system.users == null ? '—' : fa(D.system.users);
    $('kUsrS').textContent = 'PHP ' + D.system.php;
    var worst = P.some(function (p) { return p.st === 'down'; }) ? 'down' : P.some(function (p) { return p.st === 'slow' && p.k !== 'shield'; }) ? 'slow' : 'ok';
    var g = $('gNow'); g.className = 'pill ' + worst; g.textContent = ST[worst][1] + ' ' + (worst === 'ok' ? 'همه‌ی بخش‌ها سالم‌اند' : worst === 'slow' ? 'بخشی کند شده' : 'بخشی هنگ کرده یا خطا می‌دهد');

    var pl = $('plist'); pl.textContent = '';
    P.slice().sort(function (a, b) { var o = {down: 0, slow: 1, ok: 2, idle: 3}; return (o[a.st] - o[b.st]) || (b.n15 - a.n15); }).forEach(function (p) {
      var r = el('div', 'row' + (sel === p.k ? ' sel' : '')); r.tabIndex = 0; r.dataset.k = p.k;
      var n = el('div', 'n'); n.appendChild(gi(PIC[p.k] || 'spark', tintOf(p.k), 'sm')); n.appendChild(el('span', null, p.name)); r.appendChild(n);
      r.appendChild(pill(p.st)); r.appendChild(sparkSvg(p.series, ST[p.st][2]));
      r.appendChild(el('div', 'm', p.n15 ? fa(p.rpm, 1) + '/د · ' + ms(p.p95) : (p.n24 ? '۲۴ساعت: ' + fa(p.n24) : '—')));
      r.title = p.name + ' — ' + ST[p.st][0] + ' · خطا: ' + fa(p.err) + ' · هنگ: ' + fa(p.hang) + ' · بیشترین: ' + ms(p.tmax) + ' · معمولِ ۲۴ساعت: ' + ms(p.base);
      pl.appendChild(r);
    });
    var xl = $('xlist'); xl.textContent = '';
    if (!D.services.length) xl.appendChild(el('div', 'empty', 'هنوز تماسی با سرویسِ بیرونی ثبت نشده.'));
    D.services.forEach(function (x, i) {
      var r = el('div', 'row'), n = el('div', 'n'); n.appendChild(gi(XIC[x.k] || 'link', TINT[(i + 2) % 4], 'sm')); n.appendChild(el('span', null, x.name)); r.appendChild(n);
      r.appendChild(pill(x.st)); r.appendChild(sparkSvg(x.series, ST[x.st][2]));
      r.appendChild(el('div', 'm', x.n15 ? ms(x.p95) + (x.err ? ' · ' + fa(x.err) + ' خطا' : '') : 'بی‌کار')); xl.appendChild(r);
    });
    var bl = $('bal'); bl.textContent = '';
    if (!D.balances.length) bl.appendChild(el('div', 'empty', 'فروشنده‌ای وصل نیست — پنلِ تنظیمات ← API و اتصال‌ها.'));
    D.balances.forEach(function (b) {
      var c = el('div', 'b'), h = el('div', 'h'); h.appendChild(gi(b.k === 'numprov' ? 'sim' : 'satellite', b.k === 'numprov' ? 'blue' : 'violet', 'sm')); h.appendChild(el('span', null, b.name));
      h.appendChild(pill(b.st, b.st === 'down' ? 'تمام شده / کم' : b.st === 'slow' ? 'نیاز به توجه' : 'کافی')); c.appendChild(h);
      c.appendChild(el('div', 'v', b.err ? '—' : fa(b.bal, 2) + ' ' + (b.cur || '')));
      c.appendChild(el('div', 's', b.err ? 'خطا: ' + b.err : (b.funds24 ? 'کمبودِ موجودی در ۲۴ ساعت: ' + fa(b.funds24) + ' بار · ' : '') + 'به‌روز: ' + ago(b.at)));
      bl.appendChild(c);
    });
    var q = D.queues, qt = $('queues'); qt.textContent = '';
    [['منتظرِ کدِ شماره', q.num_wait], ['فاکتورِ شارژِ باز', q.topup_open], ['خدماتِ در حالِ انجام', q.svc_run], ['نیاز به بررسی', q.svc_check], ['احراز هویتِ منتظر', q.kyc]].forEach(function (t) {
      var d = el('div', 'tile'); d.appendChild(el('div', 'l', t[0])); d.appendChild(el('div', 'v', fa(t[1]))); qt.appendChild(d);
    });
    var bc = el('div', 'tile'); bc.appendChild(el('div', 'l', 'پیامِ همگانی'));
    if (q.bc) { var pc = Math.min(100, (q.bc.sent + q.bc.fail) / Math.max(1, q.bc.total) * 100); bc.appendChild(el('div', 'v', fa(pc) + '٪'));
      var m = el('div', 'meter'), mi2 = el('i'); mi2.style.width = pc + '%'; m.appendChild(mi2); bc.appendChild(m); } else bc.appendChild(el('div', 'v', 'خالی'));
    qt.appendChild(bc);
    var sb = $('slow'); sb.textContent = ''; var pm = {}; P.forEach(function (p) { pm[p.k] = p; });
    if (!D.slow.length) { var tr0 = el('tr'), td0 = el('td', 'empty', 'هنوز داده‌ای نیست.'); td0.colSpan = 7; tr0.appendChild(td0); sb.appendChild(tr0); }
    D.slow.forEach(function (s) { var tr = el('tr'), p = pm[s.p] || {name: s.p}; tr.appendChild(el('td', null, p.name)); tr.appendChild(el('td', null, actName(s.a)));
      [fa(s.n), ms(s.avg), ms(s.p95), ms(s.tmax), fa(s.err)].forEach(function (v) { tr.appendChild(el('td', 'num', v)); }); sb.appendChild(tr); });
    var sd = $('shield'); sd.textContent = ''; var dl = el('dl', 'kv');
    [['ردشده در ۱۵ دقیقه', fa(D.shield.b15)], ['ردشده در ۲۴ ساعت', fa(D.shield.b24)], ['اسپمِ کاربر (۱۵ دقیقه)', fa(sh.n15)]].forEach(function (k) { dl.appendChild(el('dt', null, k[0])); dl.appendChild(el('dd', null, k[1])); });
    sd.appendChild(dl); var why = el('div', 'why');
    Object.keys(D.shield.why).forEach(function (k) { why.appendChild(el('span', 'pill slow', (WHY[k] || k) + ': ' + fa(D.shield.why[k]))); });
    if (!why.childNodes.length) why.appendChild(el('span', 'pill ok', '✓ حمله‌ای در ۱۵ دقیقه‌ی اخیر نیست'));
    sd.appendChild(why);
    sd.appendChild(el('p', 'note', 'هر درخواستی بدونِ امضای مخفیِ تلگرام همان اول رد می‌شود. هر کاربر در پیوی حداکثر ۳۰ پیام در ۱۵ ثانیه.'));
    var lg = $('logs'); lg.textContent = '';
    var L = D.logs.filter(function (l) { return logF === 'all' || l.lvl !== 'info'; });
    if (!L.length) lg.appendChild(el('div', 'empty', '✓ لاگِ خطایی نیست.'));
    L.forEach(function (l) { var r = el('div', 'log');
      r.appendChild(el('span', 'pill ' + (l.lvl === 'fatal' ? 'down' : l.lvl === 'warn' ? 'slow' : 'idle'), l.lvl === 'fatal' ? '✕ خطای جدی' : l.lvl === 'warn' ? '! هشدار' : '· پیام'));
      r.appendChild(el('code', null, l.msg)); r.appendChild(el('span', 'c', '×' + fa(l.n) + ' · ' + ago(l.last))); lg.appendChild(r); });
    var y = D.system, sy = $('sys'); sy.textContent = '';
    if (y.disk_total) { var used = (y.disk_total - y.disk_free) / y.disk_total * 100, cls = used > 95 ? 'crit' : used > 85 ? 'warn' : '';
      var dt = el('div', 'tile'); dt.appendChild(el('div', 'l', 'فضای دیسک')); dt.appendChild(el('div', 'v', fa(used) + '٪ پر'));
      var mm = el('div', 'meter ' + cls), ii = el('i'); ii.style.width = used + '%'; mm.appendChild(ii); dt.appendChild(mm); dt.appendChild(el('div', 'l', 'آزاد: ' + bytes(y.disk_free))); sy.appendChild(dt); }
    var kv = el('dl', 'kv'); kv.style.marginTop = '10px';
    var rows = [['نسخه‌ی PHP', y.php], ['OPcache', y.opcache ? (y.opcache.on ? 'روشن · ' + fa(y.opcache.hit, 1) + '٪' : 'خاموش') : 'نیست'],
      ['حافظه', String(y.mem_limit) === '-1' ? 'نامحدود' : y.mem_limit], ['مهلتِ اجرا', y.max_exec ? fa(y.max_exec) + ' ثانیه' : 'نامحدود'], ['حجمِ داده', bytes(y.data_size)],
      ['بارِ سرور', y.load ? y.load.map(function (v) { return fa(v, 2); }).join(' / ') : '—'], ['پوشه‌ی داده', y.writable ? '✓ قابلِ نوشتن' : '✕ قفل است'],
      ['کرون‌جاب', D.doctor && D.doctor.cron_at ? ago(D.doctor.cron_at) : 'دیده نشده']];
    Object.keys(y.dbs || {}).forEach(function (k) { rows.push([k, bytes(y.dbs[k])]); });
    rows.forEach(function (k) { kv.appendChild(el('dt', null, k[0])); kv.appendChild(el('dd', null, String(k[1]))); });
    sy.appendChild(kv);
    syncBodies();
  }

  var G = {cv: $('cv'), W: 0, H: 0, dpr: 1, bg: null, tw: [], bodies: {}, parts: [], sprites: {}, running: false, paused: false, vis: true, hover: null, t0: performance.now(), q: 1, core: null, sh: 0};
  G.ctx = G.cv.getContext('2d');
  try { G.paused = localStorage.getItem('mon_pause') === '1'; } catch (e) {}
  function geo() { var rx = Math.max(130, G.W / 2 - (G.W < 700 ? 34 : 80)), ry = Math.max(110, Math.min(rx * (G.H > G.W ? 1.15 : 0.52), G.H / 2 - (G.W < 700 ? 64 : 74))); return {cx: G.W / 2, cy: G.H / 2 + 12, rx: rx, ry: ry, R: Math.min(rx, ry)}; }
  function hexA(h, a) { var n = parseInt(h.slice(1), 16); return 'rgba(' + (n >> 16) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')'; }
  function iconPath(name) { G.pc = G.pc || {}; if (!G.pc[name]) G.pc[name] = new Path2D(IC[name] || IC.spark); return G.pc[name]; }
  function buildBg() {
    var c = document.createElement('canvas'); c.width = Math.round(G.W * G.dpr); c.height = Math.round(G.H * G.dpr);
    var x = c.getContext('2d'); x.scale(G.dpr, G.dpr); var g = geo();
    var bg = x.createLinearGradient(0, 0, 0, G.H); bg.addColorStop(0, '#070b18'); bg.addColorStop(1, '#03050b'); x.fillStyle = bg; x.fillRect(0, 0, G.W, G.H);
    [[g.cx - g.rx * .55, g.cy - g.ry * .6, g.rx * .9, 'rgba(139,124,246,.16)'], [g.cx + g.rx * .6, g.cy + g.ry * .5, g.rx * .8, 'rgba(34,184,207,.10)'], [g.cx, g.cy, g.rx * 1.05, 'rgba(57,135,229,.14)']].forEach(function (n) {
      var r = x.createRadialGradient(n[0], n[1], 0, n[0], n[1], n[2]); r.addColorStop(0, n[3]); r.addColorStop(1, 'rgba(0,0,0,0)'); x.fillStyle = r; x.fillRect(0, 0, G.W, G.H); });
    x.save(); x.translate(g.cx, g.cy); x.rotate(-0.22); var band = x.createRadialGradient(0, 0, 0, 0, 0, g.rx * 1.2); band.addColorStop(0, 'rgba(200,215,255,.10)'); band.addColorStop(1, 'rgba(200,215,255,0)');
    x.scale(1, 0.18); x.fillStyle = band; x.beginPath(); x.arc(0, 0, g.rx * 1.2, 0, 6.2832); x.fill(); x.restore();
    var n = Math.round(G.W * G.H / 1500);
    for (var i = 0; i < n; i++) { var sx = Math.random() * G.W, sy = Math.random() * G.H, s = Math.random(); x.globalAlpha = .25 + Math.random() * .6; x.fillStyle = s > .97 ? '#cfe0ff' : '#ffffff';
      x.beginPath(); x.arc(sx, sy, s > .985 ? 1.6 : s > .9 ? 1 : .55, 0, 6.2832); x.fill(); }
    x.globalAlpha = 1;
    [.40, .68, .94].forEach(function (k, i) { x.strokeStyle = 'rgba(160,190,255,' + (i === 2 ? .09 : .12) + ')'; x.lineWidth = 1; x.beginPath(); x.ellipse(g.cx, g.cy, g.rx * k, g.ry * k, 0, 0, 6.2832); x.stroke(); });
    G.bg = c; G.tw = [];
    for (var j = 0; j < 46; j++) G.tw.push([Math.random() * G.W, Math.random() * G.H, Math.random() * 6.28, .6 + Math.random() * 1.1]);
    G.sprites = {}; G.core = null;
  }
  function resize() {
    var r = G.cv.getBoundingClientRect(); if (r.width < 10 || r.height < 10) return;
    G.W = r.width; G.H = r.height; G.dpr = Math.min(G.W < 700 ? 1.5 : 2, window.devicePixelRatio || 1);
    G.cv.width = Math.round(G.W * G.dpr); G.cv.height = Math.round(G.H * G.dpr); buildBg();
    if (!G.running) draw(performance.now());
  }
  function sprite(kind, st, r, icon) {
    var key = kind + st + r + icon; if (G.sprites[key]) return G.sprites[key];
    var col = ST[st][2], pad = Math.ceil(r * 1.6), S = (r + pad) * 2, c = document.createElement('canvas'); c.width = Math.round(S * G.dpr); c.height = Math.round(S * G.dpr);
    var x = c.getContext('2d'); x.scale(G.dpr, G.dpr); var m = S / 2;
    if (st !== 'idle') { var gl = x.createRadialGradient(m, m, r * .6, m, m, r + pad); gl.addColorStop(0, hexA(col, st === 'ok' ? .34 : .55)); gl.addColorStop(1, hexA(col, 0)); x.fillStyle = gl; x.beginPath(); x.arc(m, m, r + pad, 0, 6.2832); x.fill(); }
    if (kind === 'svc') {
      x.save(); x.translate(m, m); x.rotate(Math.PI / 4); var s = r * 1.5, gd = x.createLinearGradient(-s / 2, -s / 2, s / 2, s / 2);
      gd.addColorStop(0, hexA(col, .95)); gd.addColorStop(1, hexA(col, .55)); x.fillStyle = gd; x.beginPath(); x.roundRect ? x.roundRect(-s / 2, -s / 2, s, s, 4) : x.rect(-s / 2, -s / 2, s, s); x.fill();
      x.strokeStyle = 'rgba(255,255,255,.55)'; x.lineWidth = 1.2; x.stroke(); x.restore();
    } else {
      var sp = x.createRadialGradient(m - r * .38, m - r * .42, r * .08, m, m, r); sp.addColorStop(0, '#ffffff'); sp.addColorStop(.22, hexA(col, 1)); sp.addColorStop(.85, hexA(col, .62)); sp.addColorStop(1, hexA(col, .4));
      x.fillStyle = sp; x.beginPath(); x.arc(m, m, r, 0, 6.2832); x.fill();
      x.strokeStyle = 'rgba(255,255,255,.45)'; x.lineWidth = 1.2; x.beginPath(); x.arc(m, m, r - .6, 0, 6.2832); x.stroke();
      var sh = x.createLinearGradient(m, m - r, m, m); sh.addColorStop(0, 'rgba(255,255,255,.42)'); sh.addColorStop(1, 'rgba(255,255,255,0)');
      x.fillStyle = sh; x.beginPath(); x.ellipse(m, m - r * .42, r * .66, r * .4, 0, 0, 6.2832); x.fill();
    }
    var isz = kind === 'svc' ? r * 1.15 : r * 1.12; x.save(); x.translate(m - isz / 2, m - isz / 2); x.scale(isz / 24, isz / 24);
    x.strokeStyle = '#ffffff'; x.lineWidth = 2.1; x.lineCap = 'round'; x.lineJoin = 'round'; x.shadowColor = 'rgba(0,0,0,.45)'; x.shadowBlur = 3; x.stroke(iconPath(icon)); x.restore();
    G.sprites[key] = {c: c, S: S}; return G.sprites[key];
  }
  function coreSprite(R) {
    if (G.core && G.core.R === R) return G.core;
    var S = R * 7, c = document.createElement('canvas'); c.width = Math.round(S * G.dpr); c.height = Math.round(S * G.dpr);
    var x = c.getContext('2d'); x.scale(G.dpr, G.dpr); var m = S / 2;
    var gl = x.createRadialGradient(m, m, R * .5, m, m, S / 2); gl.addColorStop(0, 'rgba(120,170,255,.55)'); gl.addColorStop(.35, 'rgba(90,120,255,.16)'); gl.addColorStop(1, 'rgba(0,0,0,0)');
    x.fillStyle = gl; x.fillRect(0, 0, S, S);
    var cg = x.createRadialGradient(m - R * .3, m - R * .35, R * .1, m, m, R); cg.addColorStop(0, '#ffffff'); cg.addColorStop(.35, '#cfe2ff'); cg.addColorStop(.75, '#3987e5'); cg.addColorStop(1, '#1b3a7a');
    x.fillStyle = cg; x.beginPath(); x.arc(m, m, R, 0, 6.2832); x.fill();
    x.save(); var isz = R * 1.05; x.translate(m - isz / 2, m - isz / 2); x.scale(isz / 24, isz / 24); x.strokeStyle = 'rgba(4,10,30,.85)'; x.lineWidth = 2; x.lineCap = 'round'; x.lineJoin = 'round'; x.stroke(iconPath('bot')); x.restore();
    G.core = {c: c, S: S, R: R}; return G.core;
  }
  function syncBodies() {
    var nb = {}, maxN = 1;
    D.planets.forEach(function (p) { if (p.k !== 'shield') maxN = Math.max(maxN, p.n15); });
    function place(list, ring, w, ph) {
      var ks = list.filter(function (k) { return D.planets.some(function (p) { return p.k === k && (k !== 'other' || p.n24 > 0); }); });
      ks.forEach(function (k, i) {
        var p = D.planets.filter(function (x) { return x.k === k; })[0], b = G.bodies[k] || {a: ph + i / ks.length * Math.PI * 2};
        b.k = k; b.p = p; b.ring = ring; b.w = w; b.kind = 'planet'; b.icon = PIC[k] || 'spark'; b.label = SHORT[k] || p.name;
        b.r = Math.round(G.W < 700 ? (p.n15 > 0 ? 11 + 9 * Math.sqrt(p.n15 / maxN) : 10) : (p.n15 > 0 ? 14 + 14 * Math.sqrt(p.n15 / maxN) : 12)); nb[k] = b;
      });
    }
    place(INNER, .40, .085, 0); place(MIDDLE, .68, -.05, .45);
    D.services.forEach(function (x, i) { var k = 'x_' + x.k, b = G.bodies[k] || {a: 1.2 + i / Math.max(1, D.services.length) * Math.PI * 2};
      b.k = k; b.p = x; b.ring = .94; b.w = .026; b.kind = 'svc'; b.icon = XIC[x.k] || 'link'; b.label = x.name; b.r = G.W < 700 ? 9 : 11; nb[k] = b; });
    G.sh = D.shield.b15 + ((D.planets.filter(function (p) { return p.k === 'shield'; })[0] || {}).n15 || 0);
    G.bodies = nb; if (!G.running && G.vis) draw(performance.now());
  }
  function posOf(b, t, g) { var a = b.a + t * b.w * (reduce ? .45 : 1), d = (Math.sin(a) + 1) / 2; return {x: g.cx + Math.cos(a) * g.rx * b.ring, y: g.cy + Math.sin(a) * g.ry * b.ring, s: .78 + .4 * d, d: d}; }
  function draw(now) {
    var x = G.ctx, t = (now - G.t0) / 1000, g = geo(); if (!G.bg) return;
    x.setTransform(1, 0, 0, 1, 0, 0); x.drawImage(G.bg, 0, 0); x.setTransform(G.dpr, 0, 0, G.dpr, 0, 0);
    if (G.q > .5) for (var i = 0; i < G.tw.length; i++) { var s = G.tw[i], a = .25 + .75 * Math.pow(Math.sin(t * s[3] + s[2]), 2); x.globalAlpha = a; x.fillStyle = '#eaf1ff'; x.fillRect(s[0], s[1], 1.6, 1.6); }
    x.globalAlpha = 1;
    if (!D) return;
    var list = Object.keys(G.bodies).map(function (k) { var b = G.bodies[k], p = posOf(b, t, g); b.x = p.x; b.y = p.y; b.s = p.s; return b; });
    list.forEach(function (b) { var col = ST[b.p.st][2]; x.strokeStyle = hexA(col, b.kind === 'svc' ? .20 : .12); x.lineWidth = b.kind === 'svc' ? 1 + Math.min(3, Math.log((b.p.n15 || 0) + 1) / 2) : 1;
      x.beginPath(); x.moveTo(g.cx, g.cy); x.lineTo(b.x, b.y); x.stroke(); });
    if (!reduce && G.q > .4) list.forEach(function (b) {
      if (b.p.n15 > 0 && Math.random() < Math.min(.3, b.p.rpm / 30 + .03) * G.q) G.parts.push({b: b.k, f: 0, v: .006 + Math.random() * .009, e: 0});
      if (b.p.err > 0 && Math.random() < Math.min(.09, b.p.err / 150 + .012) * G.q) G.parts.push({b: b.k, f: 1, v: .009, e: 1, an: Math.random() * 6.28});
    });
    var back = list.filter(function (b) { return b.y < g.cy; }).sort(function (a, c) { return a.y - c.y; }), front = list.filter(function (b) { return b.y >= g.cy; }).sort(function (a, c) { return a.y - c.y; });
    back.forEach(function (b) { body(x, b); });
    var R = Math.max(26, g.R * .2), cs = coreSprite(Math.round(R)), pulse = 1 + .035 * Math.sin(t * 2);
    x.save(); x.translate(g.cx, g.cy); x.rotate(t * .12);
    for (var k = 0; k < 12; k++) { x.rotate(Math.PI / 6); x.fillStyle = 'rgba(150,190,255,' + (.05 + .04 * Math.sin(t * 1.7 + k)) + ')'; x.beginPath(); x.moveTo(-3, R * 1.05); x.lineTo(0, R * (1.9 + .25 * Math.sin(t + k))); x.lineTo(3, R * 1.05); x.fill(); }
    x.restore();
    x.drawImage(cs.c, g.cx - cs.S * pulse / 2, g.cy - cs.S * pulse / 2, cs.S * pulse, cs.S * pulse);
    var sh = G.sh, shc = sh > 0 ? '#fab219' : '#3987e5';
    x.strokeStyle = hexA(shc, sh > 0 ? .5 + .3 * Math.sin(t * 4) : .32); x.lineWidth = sh > 0 ? 2 + Math.min(4, Math.log(sh + 1)) : 1.5;
    x.beginPath(); x.ellipse(g.cx, g.cy, R * 1.75, R * 1.75 * .82, 0, 0, 6.2832); x.stroke();
    for (var j = G.parts.length - 1; j >= 0; j--) {
      var q = G.parts[j], bb = G.bodies[q.b]; if (!bb || bb.x == null) { G.parts.splice(j, 1); continue; }
      if (!q.e) { q.f += q.v; if (q.f >= 1) { G.parts.splice(j, 1); continue; }
        var px = g.cx + (bb.x - g.cx) * q.f, py = g.cy + (bb.y - g.cy) * q.f; x.fillStyle = 'rgba(170,205,255,' + (.95 - q.f * .5) + ')'; x.beginPath(); x.arc(px, py, 1.7, 0, 6.2832); x.fill();
      } else { q.f -= q.v; if (q.f <= 0) { G.parts.splice(j, 1); continue; }
        var dd = (1 - q.f) * 54, ex = bb.x + Math.cos(q.an) * dd, ey = bb.y + Math.sin(q.an) * dd; x.strokeStyle = 'rgba(232,80,80,' + q.f + ')'; x.lineWidth = 2; x.lineCap = 'round';
        x.beginPath(); x.moveTo(ex, ey); x.lineTo(ex - Math.cos(q.an) * 10, ey - Math.sin(q.an) * 10); x.stroke(); }
    }
    if (G.parts.length > 500 * G.q) G.parts.splice(0, G.parts.length - Math.round(500 * G.q));
    front.forEach(function (b) { body(x, b); });
  }
  function body(x, b) {
    var p = b.p, r = b.r, sp = sprite(b.kind, p.st, r, b.icon), sz = sp.S * b.s, on = G.hover === b.k || sel === b.k;
    x.globalAlpha = .55 + .45 * b.s; x.drawImage(sp.c, b.x - sz / 2, b.y - sz / 2, sz, sz); x.globalAlpha = 1;
    if (on) { x.strokeStyle = 'rgba(255,255,255,.9)'; x.lineWidth = 1.5; x.beginPath(); x.arc(b.x, b.y, r * b.s + 6, 0, 6.2832); x.stroke(); }
    var small = G.W < 700, fy = b.y + r * b.s + 6;
    x.textAlign = 'center'; x.textBaseline = 'top'; x.font = '800 ' + (small ? 11 : 12.5) + 'px Vazirmatn,Tahoma,sans-serif';
    var sub = ST[p.st][1] + ' ' + ST[p.st][0] + (p.n15 ? ' · ' + fa(p.rpm, 1) + '/د' : ''), show = !small || on || (p.st !== 'ok' && p.st !== 'idle');
    if (b.lw == null || b.lt !== b.label + sub) { b.lt = b.label + sub; b.lw = x.measureText(b.label).width; x.font = '600 ' + (small ? 10 : 11) + 'px Vazirmatn,Tahoma,sans-serif'; b.lw = Math.max(b.lw, x.measureText(sub).width); x.font = '800 ' + (small ? 11 : 12.5) + 'px Vazirmatn,Tahoma,sans-serif'; }
    var tx = Math.min(G.W - b.lw / 2 - 6, Math.max(b.lw / 2 + 6, b.x));
    x.fillStyle = 'rgba(0,0,0,.55)'; x.fillText(b.label, tx + 1, fy + 1); x.fillStyle = '#f4f6fb'; x.fillText(b.label, tx, fy);
    if (!show) return;
    x.font = '600 ' + (small ? 10 : 11) + 'px Vazirmatn,Tahoma,sans-serif'; x.fillStyle = '#a7b1c6';
    x.fillText(sub, tx, fy + (small ? 15 : 17));
  }
  var lastDraw = 0, acc = 0, accN = 0;
  function frame(now) {
    if (!G.running) return;
    requestAnimationFrame(frame);
    var fr = G.W < 700 ? 41 : 33;
    if (lastDraw && now - lastDraw < fr - 3) return;
    lastDraw = now;
    var c0 = performance.now(); draw(now); var cost = performance.now() - c0;
    acc += cost; accN++;
    if (accN >= 45) { var avg = acc / accN; G.q = avg > 14 ? Math.max(.35, G.q - .25) : avg < 7 ? Math.min(1, G.q + .25) : G.q; acc = 0; accN = 0; }
  }
  function start() { if (G.running || G.paused || !G.vis || document.hidden) return; G.running = true; lastDraw = 0; requestAnimationFrame(frame); }
  function stop() { G.running = false; }
  function setPause(p) {
    G.paused = p; try { localStorage.setItem('mon_pause', p ? '1' : '0'); } catch (e) {}
    var b = $('pzBtn'); b.setAttribute('aria-pressed', p ? 'true' : 'false'); b.replaceChildren(gi(p ? 'play' : 'pause', p ? 'good' : 'blue'), el('span', 'tx', p ? 'حرکت' : 'توقف'));
    if (p) stop(); else start();
  }
  function hit(cx, cy) {
    var r = G.cv.getBoundingClientRect(), x = cx - r.left, y = cy - r.top, best = null, bd = 1e9;
    Object.keys(G.bodies).forEach(function (k) { var b = G.bodies[k]; if (b.x == null) return; var d = Math.hypot(b.x - x, b.y - y); if (d < Math.max(b.r * (b.s || 1) + 12, 20) && d < bd) { bd = d; best = k; } });
    return [best, x, y];
  }
  function showTip(k, x, y) {
    var tip = $('tip'); if (!k || !G.bodies[k]) { tip.classList.remove('on'); return; }
    var b = G.bodies[k], p = b.p; tip.textContent = '';
    var tt2 = el('div', 't'); tt2.appendChild(gi(b.icon, b.kind === 'svc' ? 'cyan' : tintOf(b.k), 'sm')); tt2.appendChild(el('span', null, p.name)); tip.appendChild(tt2);
    tip.appendChild(pill(p.st));
    [['p95 (۱۵ دقیقه)', ms(p.p95)], ['معمولِ ۲۴ ساعت', ms(p.base)], ['بیشترین', ms(p.tmax)], ['در دقیقه', fa(p.rpm, 1)], ['۱۵ دقیقه', fa(p.n15)], ['خطا', fa(p.err)]]
      .concat(p.hang != null ? [['هنگ', fa(p.hang)]] : []).concat(p.act ? [['کندترین کار', actName(p.act)]] : []).forEach(function (rw) {
        var d = el('div', 'r'); d.appendChild(el('span', null, rw[0])); d.appendChild(el('b', null, rw[1])); tip.appendChild(d); });
    var gw = $('gal').clientWidth, gh = $('gal').clientHeight;
    tip.style.left = Math.min(gw - 280, Math.max(8, x + 16)) + 'px'; tip.style.top = Math.min(gh - 230, Math.max(48, y - 20)) + 'px'; tip.classList.add('on');
  }
  G.cv.addEventListener('pointermove', function (ev) { if (ev.pointerType !== 'mouse') return; var h = hit(ev.clientX, ev.clientY); G.hover = h[0]; showTip(h[0], h[1], h[2]); G.cv.style.cursor = h[0] ? 'pointer' : 'default'; if (!G.running) draw(performance.now()); });
  G.cv.addEventListener('pointerleave', function () { G.hover = null; showTip(null); });
  G.cv.addEventListener('pointerdown', function (ev) {
    var h = hit(ev.clientX, ev.clientY);
    if (ev.pointerType !== 'mouse') { G.hover = h[0]; showTip(h[0], h[1], h[2]); }
    if (h[0] && h[0].indexOf('x_') !== 0) { sel = h[0]; render(); var r = document.querySelector('.row[data-k="' + sel + '"]'); if (r && ev.pointerType === 'mouse') r.scrollIntoView({block: 'nearest', behavior: 'smooth'}); }
    if (!G.running) draw(performance.now());
  });
  $('plist').addEventListener('click', function (ev) { var r = ev.target.closest('.row'); if (!r) return; sel = sel === r.dataset.k ? null : r.dataset.k; render(); });
  $('plist').addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { var r = ev.target.closest('.row'); if (r) { sel = r.dataset.k; render(); } } });
  document.querySelectorAll('.chip').forEach(function (c) { c.addEventListener('click', function () { logF = c.dataset.f; document.querySelectorAll('.chip').forEach(function (x) { x.classList.toggle('on', x === c); }); if (D) render(); }); });
  document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else start(); });
  $('pzBtn').addEventListener('click', function () { setPause(!G.paused); });
  if (window.ResizeObserver) new ResizeObserver(function () { resize(); }).observe(G.cv); else window.addEventListener('resize', resize);
  if (window.IntersectionObserver) new IntersectionObserver(function (es) { G.vis = es[es.length - 1].isIntersecting; if (G.vis) start(); else stop(); }).observe($('gal'));

  function setLive(ok) {
    $('live').classList.toggle('off', !ok);
    $('liveT').textContent = ok ? 'زنده · ' + new Date().toLocaleTimeString('fa-IR') : 'اتصال قطع شد — دوباره تلاش می‌کنم';
    $('app').classList.toggle('stale', !ok);
  }
  var busy = null;
  function load(body) {
    if (busy && !body) return busy;
    var opt = {cache: 'no-store', credentials: 'same-origin', headers: {'Accept': 'application/json'}};
    if (body) { opt.method = 'POST'; opt.body = body; }
    var pr = fetch('monitor.php?api=1', opt).then(function (r) {
      if ((r.headers.get('content-type') || '').indexOf('application/json') < 0) { location.reload(); throw new Error('auth'); }
      return r.json();
    }).then(function (j) { if (!j.ok) throw new Error('bad'); D = j; failN = 0; setLive(true); render(); if (j.msg) toast(j.msg.text, !j.msg.ok); });
    var done = function () { if (busy === pr) busy = null; };
    busy = pr; pr.then(done, done); return pr;
  }
  var waitT = null;
  function loop() {
    clearTimeout(waitT);
    if (document.hidden) { waitT = setTimeout(loop, 15000); return; }
    load().catch(function () { failN++; setLive(false); }).then(function () { clearTimeout(waitT); waitT = setTimeout(loop, failN ? Math.min(30000, 5000 * failN) : 5000); });
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) loop(); });
  function post(doWhat, btn) {
    if (btn) btn.disabled = true;
    var f = new FormData(); f.append('csrf', CSRF); f.append('do', doWhat);
    return load(f).catch(function () { toast('انجام نشد — اتصال را بررسی کن.', true); }).then(function () { if (btn) btn.disabled = false; });
  }
  $('balBtn').addEventListener('click', function () { post('bal', this); });
  $('reBtn').addEventListener('click', function () { post('recheck', this); });
  $('finds').addEventListener('click', function (ev) { var b = ev.target.closest('[data-act]'); if (b) post(b.dataset.act, b); });
  $('cpBtn').addEventListener('click', function () {
    if (!D || !D.doctor) return;
    var d = D.doctor, L = ['گزارشِ دستیارِ عیب‌یاب — نامبیکس', 'زمان: ' + new Date().toLocaleString('fa-IR'), 'سلامت: ' + d.score + ' از ۱۰۰ (' + d.crit + ' فوری، ' + d.warn + ' مهم، ' + d.info + ' دانستنی)', ''];
    d.findings.forEach(function (f) { L.push('[' + SEV[f.sev][0] + '] ' + f.title); L.push('  واقعیت: ' + f.facts.replace(/\n/g, '\n  ')); if (f.impact) L.push('  اثر: ' + f.impact);
      L.push('  چه کنم:'); f.steps.forEach(function (s, i) { L.push('   ' + (i + 1) + ') ' + s.replace(/\n/g, '\n      ')); }); L.push(''); });
    d.checks.forEach(function (c) { L.push((c.st === 'ok' ? '✓ ' : c.st === 'slow' ? '! ' : '✕ ') + c.name); });
    var y = D.system; L.push('', 'PHP ' + y.php + ' · OPcache ' + (y.opcache && y.opcache.on ? 'روشن' : 'خاموش') + ' · حافظه ' + y.mem_limit + ' · دیسکِ آزاد ' + bytes(y.disk_free));
    var text = L.join('\n'), done = function () { toast('گزارش کپی شد؛ اطلاعاتِ محرمانه داخلش پوشانده شده.'); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, fallback); else fallback();
    function fallback() { var ta = el('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) { toast('کپی نشد.', true); } ta.remove(); }
  });
  setPause(G.paused); resize(); loop(); start();
})();
</script>
</body></html>
