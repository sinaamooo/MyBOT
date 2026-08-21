<?php
/**
 * 🛡 نمای مینی‌اپ «فروش کانفیگ» — تم سایبر گرید (Cyber)
 * مشکی/نئون، شبکه پرسپکتیو متحرک، خطوط اسکن، پنل‌های زاویه‌دار و فونت مونو.
 * ساختار و استایلش کاملا جدا از مینی‌اپ خدمات تلگرام است.
 */

function maViewCfg($a, $boot) {
    $th   = $a['theme'] ?? [];
    $c1   = $th['c1'] ?? '#00FF9C';
    $c2   = $th['c2'] ?? '#00B3FF';
    $c3   = $th['c3'] ?? '#FF2E97';
    $bg   = $th['bg'] ?? '#04070A';
    $glow = !empty($th['glow']) ? '1' : '0';
    $grain= !empty($th['grain']) ? '1' : '0';

    return strtr(maTplCfg(), [
        '__C1__'    => $c1,
        '__C2__'    => $c2,
        '__C3__'    => $c3,
        '__BG__'    => $bg,
        '__GLOW__'  => $glow,
        '__GRAIN__' => $grain,
        '__TITLE__' => htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8'),
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

function maTplCfg() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title>__TITLE__</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{
  --c1:__C1__; --c2:__C2__; --c3:__C3__; --bg:__BG__;
  --ink:#DFF7EE; --dim:#6E8C83; --line:color-mix(in srgb,var(--c1) 22%,transparent);
  --panel:rgba(6,13,11,.80); --safe:env(safe-area-inset-bottom,0px);
  --mono:"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,monospace;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;height:100%}
body{
  background:var(--bg); color:var(--ink);
  font-family:Vazirmatn,system-ui,-apple-system,Tahoma,sans-serif;
  overflow-x:hidden;-webkit-font-smoothing:antialiased;
}

/* ═══ شبکه پرسپکتیو ═══ */
.floor{position:fixed;left:-50%;right:-50%;bottom:-8vh;height:62vh;z-index:0;pointer-events:none;
  perspective:220px;perspective-origin:50% 0%;opacity:.30}
.floor i{position:absolute;inset:0;transform:rotateX(76deg);transform-origin:50% 0%;
  background-image:
    repeating-linear-gradient(90deg,color-mix(in srgb,var(--c1) 55%,transparent) 0 1px,transparent 1px 62px),
    repeating-linear-gradient(0deg,color-mix(in srgb,var(--c2) 40%,transparent) 0 1px,transparent 1px 62px);
  animation:run 5.5s linear infinite}
@keyframes run{to{background-position:0 62px,0 62px}}
.dome{position:fixed;inset:0;z-index:1;pointer-events:none;
  background:
    radial-gradient(90% 50% at 50% 0%,color-mix(in srgb,var(--c2) 16%,transparent),transparent 62%),
    radial-gradient(70% 40% at 50% 100%,color-mix(in srgb,var(--c1) 14%,transparent),transparent 60%)}
.scan{position:fixed;inset:0;z-index:2;pointer-events:none;opacity:.5;
  background:repeating-linear-gradient(0deg,rgba(0,0,0,.5) 0 1px,transparent 1px 3px)}
.grain{position:fixed;inset:0;z-index:3;pointer-events:none;opacity:.06;display:none;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/></filter><rect width='140' height='140' filter='url(%23n)' opacity='.6'/></svg>")}
body.grain-on .grain{display:block}

.wrap{position:relative;z-index:6;max-width:600px;margin:0 auto;padding:0 14px calc(30px + var(--safe))}

/* ═══ نوار HUD ═══ */
.hud{margin:12px 0 14px;padding:11px 14px;display:flex;align-items:center;gap:11px;
  border:1px solid var(--line);background:var(--panel);
  clip-path:polygon(12px 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%,0 12px)}
.led{width:9px;height:9px;flex:0 0 auto;border-radius:50%;background:var(--c1);animation:blink 1.6s ease-in-out infinite}
body.glow-on .led{box-shadow:0 0 12px var(--c1)}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.28}}
.hud .sys{font-family:var(--mono);font-size:10px;color:var(--c1);letter-spacing:.5px;direction:ltr}
.hud .sp{flex:1}
.hud .clk{font-family:var(--mono);font-size:10.5px;color:var(--dim);direction:ltr}

/* ═══ سربرگ ═══ */
.head{margin-bottom:14px}
.head h1{margin:0;font-size:23px;font-weight:900;letter-spacing:-.5px;line-height:1.35;
  text-shadow:0 0 22px color-mix(in srgb,var(--c1) 45%,transparent)}
.head h1 em{font-style:normal;color:var(--c1)}
.head p{margin:5px 0 0;font-size:12px;color:var(--dim);font-family:var(--mono);direction:rtl}

/* ═══ اعتبار ═══ */
.credit{margin:0 0 13px;padding:14px 16px;position:relative;
  border:1px solid var(--line);background:var(--panel);
  backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
  clip-path:polygon(0 0,calc(100% - 16px) 0,100% 16px,100% 100%,16px 100%,0 calc(100% - 16px))}
.credit:before{content:"";position:absolute;top:0;right:0;width:36%;height:2px;background:var(--c1)}
body.glow-on .credit:before{box-shadow:0 0 12px var(--c1)}
.credit .k{font-size:10.5px;color:var(--dim);font-family:var(--mono);letter-spacing:.4px;margin-bottom:6px}
.credit .v{font-family:var(--mono);font-size:26px;font-weight:700;color:var(--c1);direction:ltr;text-align:right;
  letter-spacing:-.5px}
body.glow-on .credit .v{text-shadow:0 0 20px color-mix(in srgb,var(--c1) 55%,transparent)}
.credit .u{font-family:Vazirmatn,sans-serif;font-size:12px;color:var(--dim);margin-inline-start:5px}

.brief{margin:0 0 15px;padding:11px 13px;font-size:12px;line-height:1.9;color:#9FC4B8;
  border-inline-start:2px solid var(--c2);background:linear-gradient(90deg,color-mix(in srgb,var(--c2) 9%,transparent),transparent)}
.brief:before{content:"> ";font-family:var(--mono);color:var(--c2)}

/* ═══ جستجو ═══ */
.seek{position:relative;margin-bottom:12px}
.seek input{width:100%;padding:12px 38px 12px 12px;border:1px solid var(--line);background:rgba(0,0,0,.35);
  color:var(--ink);font-family:var(--mono);font-size:12.5px;outline:none;transition:.18s;
  clip-path:polygon(0 0,calc(100% - 10px) 0,100% 10px,100% 100%,10px 100%,0 calc(100% - 10px))}
.seek input:focus{border-color:var(--c1);background:rgba(0,0,0,.55)}
.seek span{position:absolute;top:50%;right:13px;transform:translateY(-50%);color:var(--c1);font-size:13px}

/* ═══ تب‌ها ═══ */
.tabs{display:flex;gap:7px;overflow-x:auto;padding-bottom:13px;scrollbar-width:none}
.tabs::-webkit-scrollbar{display:none}
.tab{flex:0 0 auto;padding:8px 13px;font-family:var(--mono);font-size:11.5px;font-weight:700;cursor:pointer;
  border:1px solid var(--line);background:rgba(0,0,0,.3);color:var(--dim);transition:.18s;white-space:nowrap;
  clip-path:polygon(8px 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px)}
.tab.on{color:var(--bg);background:var(--c1);border-color:var(--c1);font-weight:700}
body.glow-on .tab.on{box-shadow:0 0 18px -3px var(--c1)}

/* ═══ کارت سرویس ═══ */
.list{display:grid;gap:11px}
.node{position:relative;padding:14px;cursor:pointer;overflow:hidden;
  border:1px solid var(--line);background:var(--panel);transition:.2s;
  backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
  clip-path:polygon(0 0,calc(100% - 14px) 0,100% 14px,100% 100%,14px 100%,0 calc(100% - 14px));
  animation:slide .4s cubic-bezier(.2,.9,.3,1) backwards}
@keyframes slide{from{opacity:0;transform:translateX(-16px)}to{opacity:1;transform:none}}
.node:active{transform:scale(.985);border-color:var(--c1)}
.node:before{content:"";position:absolute;top:0;right:0;bottom:0;width:3px;background:var(--c1);opacity:.75}
.node.vip:before{background:var(--c3)}
.nrow{display:flex;align-items:flex-start;gap:11px}
.ico{width:44px;height:44px;flex:0 0 auto;display:grid;place-items:center;font-size:21px;
  border:1px solid var(--line);background:rgba(0,0,0,.4);
  clip-path:polygon(9px 0,100% 0,100% calc(100% - 9px),calc(100% - 9px) 100%,0 100%,0 9px)}
.nbody{flex:1;min-width:0}
.nbody h3{margin:0;font-size:14.5px;font-weight:800;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.nbody p{margin:4px 0 0;font-size:11.5px;color:var(--dim);line-height:1.75}
.flag{font-family:var(--mono);font-size:9px;font-weight:700;padding:2px 6px;color:var(--bg);background:var(--c3)}
.nfoot{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;
  padding-top:11px;border-top:1px dashed var(--line)}
.cost{font-family:var(--mono);font-size:16px;font-weight:700;color:var(--c1);direction:ltr}
.cost em{font-family:Vazirmatn,sans-serif;font-style:normal;font-size:10.5px;color:var(--dim);margin-inline-start:4px}
.take{padding:8px 15px;font-family:var(--mono);font-size:11.5px;font-weight:700;color:var(--bg);
  background:var(--c1);border:0;cursor:pointer;
  clip-path:polygon(7px 0,100% 0,100% calc(100% - 7px),calc(100% - 7px) 100%,0 100%,0 7px)}
body.glow-on .take{box-shadow:0 0 16px -4px var(--c1)}

.none{text-align:center;padding:44px 18px;color:var(--dim);font-size:12.5px;font-family:var(--mono)}
.none div{font-size:40px;margin-bottom:10px;opacity:.4}
.load{height:96px;border:1px solid var(--line);
  background:linear-gradient(90deg,rgba(255,255,255,.02),color-mix(in srgb,var(--c1) 8%,transparent),rgba(255,255,255,.02));
  background-size:200% 100%;animation:pan 1.2s linear infinite}
@keyframes pan{to{background-position:-200% 0}}

/* ═══ ترمینال خرید ═══ */
.mask{position:fixed;inset:0;z-index:40;background:rgba(0,4,3,.88);backdrop-filter:blur(4px);
  -webkit-backdrop-filter:blur(4px);opacity:0;pointer-events:none;transition:.25s}
.mask.on{opacity:1;pointer-events:auto}
.term{position:fixed;left:0;right:0;bottom:0;z-index:41;transform:translateY(102%);
  transition:transform .34s cubic-bezier(.2,.9,.25,1);max-height:93vh;overflow-y:auto;
  background:linear-gradient(180deg,#081310,#04070A);border-top:2px solid var(--c1);
  padding:0 0 calc(20px + var(--safe))}
body.glow-on .term{box-shadow:0 -10px 44px -10px var(--c1)}
.term.on{transform:none}
.bar{display:flex;align-items:center;gap:7px;padding:11px 14px;border-bottom:1px solid var(--line);
  position:sticky;top:0;background:#081310;z-index:2}
.bar u{width:9px;height:9px;border-radius:50%;display:block}
.bar u:nth-child(1){background:var(--c3)} .bar u:nth-child(2){background:var(--c2)} .bar u:nth-child(3){background:var(--c1)}
.bar b{margin-inline-start:8px;font-family:var(--mono);font-size:10.5px;color:var(--dim);font-weight:400;direction:ltr}
.pad{padding:16px 15px 0}

.tHead{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.tHead .ico{width:52px;height:52px;font-size:25px}
.tHead h2{margin:0;font-size:16.5px;font-weight:900}
.tHead p{margin:4px 0 0;font-size:11.5px;color:var(--dim);line-height:1.7}

.in{margin-bottom:13px}
.in label{display:block;font-family:var(--mono);font-size:11px;color:var(--c1);margin-bottom:7px}
.in label:before{content:"$ ";opacity:.6}
.in input,.in textarea{width:100%;padding:13px;border:1px solid var(--line);background:rgba(0,0,0,.5);
  color:var(--ink);font-family:var(--mono);font-size:13.5px;outline:none;transition:.18s}
.in textarea{min-height:78px;resize:vertical;font-family:Vazirmatn,sans-serif;font-size:13px}
.in input:focus,.in textarea:focus{border-color:var(--c1);background:rgba(0,0,0,.7)}
.in .tip{font-size:10.5px;color:var(--dim);margin-top:6px;line-height:1.7}

.pm{display:flex;gap:9px;align-items:center}
.pm button{width:44px;height:46px;flex:0 0 auto;border:1px solid var(--line);background:rgba(0,0,0,.5);
  color:var(--c1);font-family:var(--mono);font-size:19px;cursor:pointer;transition:.15s}
.pm button:active{background:var(--c1);color:var(--bg)}
.pm input{text-align:center;font-weight:700;font-size:16px}
.quick{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}
.quick i{padding:5px 11px;font-family:var(--mono);font-size:11px;font-style:normal;cursor:pointer;
  border:1px solid var(--line);background:rgba(0,0,0,.4);color:var(--dim);transition:.15s}
.quick i:active{background:var(--c1);color:var(--bg)}

.sum{margin:15px 0;padding:14px 15px;display:flex;justify-content:space-between;align-items:center;
  border:1px solid var(--c1);background:color-mix(in srgb,var(--c1) 9%,transparent);
  clip-path:polygon(0 0,calc(100% - 12px) 0,100% 12px,100% 100%,12px 100%,0 calc(100% - 12px))}
.sum span{font-size:11.5px;color:var(--dim);font-family:var(--mono)}
.sum b{font-family:var(--mono);font-size:20px;font-weight:700;color:var(--c1);direction:ltr}

.exec{width:100%;padding:16px;border:0;cursor:pointer;font-family:var(--mono);font-size:14px;font-weight:700;
  color:var(--bg);background:var(--c1);transition:.18s;
  clip-path:polygon(12px 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%,0 12px)}
body.glow-on .exec{box-shadow:0 0 26px -8px var(--c1)}
.exec:active{transform:scale(.985)}
.exec[disabled]{opacity:.5;cursor:default}
.abort{width:100%;margin-top:9px;padding:13px;border:1px solid var(--line);background:transparent;
  color:var(--dim);font-family:var(--mono);font-size:12.5px;cursor:pointer}

/* ═══ موفقیت ═══ */
.ok{position:fixed;inset:0;z-index:60;display:none;place-items:center;text-align:center;padding:28px;
  background-color:var(--bg);
  background-image:radial-gradient(70% 50% at 50% 45%,color-mix(in srgb,var(--c1) 20%,transparent),transparent 76%)}
.ok.on{display:grid}
.box{width:104px;height:104px;margin:0 auto 22px;display:grid;place-items:center;font-size:46px;color:var(--bg);
  background:var(--c1);animation:zoom .5s cubic-bezier(.2,1.4,.4,1) backwards;
  clip-path:polygon(18px 0,100% 0,100% calc(100% - 18px),calc(100% - 18px) 100%,0 100%,0 18px)}
body.glow-on .box{box-shadow:0 0 46px -6px var(--c1)}
@keyframes zoom{from{transform:scale(.4);opacity:0}to{transform:none;opacity:1}}
.ok h2{margin:0 0 9px;font-size:20px;font-weight:900}
.ok p{margin:0 0 22px;font-size:12.5px;color:var(--dim);line-height:1.9;max-width:300px}
.ok .ref{font-family:var(--mono);font-size:11.5px;padding:9px 15px;margin-bottom:22px;direction:ltr;
  border:1px dashed var(--line);color:var(--c1)}
.log{font-family:var(--mono);font-size:10.5px;color:var(--dim);text-align:right;direction:rtl;
  max-width:290px;margin:0 auto 22px;line-height:2}
.log b{color:var(--c1);font-weight:400}

/* ═══ هشدار ═══ */
.warn{position:fixed;top:12px;left:50%;transform:translate(-50%,-150%);z-index:80;max-width:88vw;
  padding:12px 17px;font-size:12.5px;font-weight:700;text-align:center;line-height:1.7;
  color:var(--bg);background:var(--c3);transition:transform .32s cubic-bezier(.2,1.3,.4,1);
  clip-path:polygon(10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%,0 10px)}
.warn.on{transform:translate(-50%,0)}
</style>
</head>
<body>
<div class="floor"><i></i></div>
<div class="dome"></div><div class="scan"></div><div class="grain"></div>

<div class="wrap">
  <div class="hud">
    <span class="led"></span>
    <span class="sys">SYSTEM ONLINE</span>
    <span class="sp"></span>
    <span class="clk" id="clk">--:--:--</span>
  </div>

  <div class="head">
    <h1 id="ttl">—</h1>
    <p id="sub"></p>
  </div>

  <div class="credit">
    <div class="k" id="balLbl">CREDIT</div>
    <div class="v"><span id="bal">…</span><span class="u" id="cur"></span></div>
  </div>

  <div class="brief" id="hero"></div>

  <div class="seek"><input id="q" placeholder="جستجو…"><span>⌕</span></div>
  <div class="tabs" id="tabs"></div>
  <div class="list" id="list">
    <div class="load"></div><div class="load"></div><div class="load"></div>
  </div>
</div>

<div class="mask" id="mask"></div>
<div class="term" id="term">
  <div class="bar"><u></u><u></u><u></u><b id="barPath">/order/new</b></div>
  <div class="pad">
    <div class="tHead">
      <div class="ico" id="tIco">💠</div>
      <div style="flex:1;min-width:0"><h2 id="tName">—</h2><p id="tDesc"></p></div>
    </div>
    <div id="tField"></div>
    <div class="sum"><span>TOTAL</span><b id="tSum">0</b></div>
    <button class="exec" id="tGo">EXECUTE</button>
    <button class="abort" id="tNo">ABORT</button>
  </div>
</div>

<div class="ok" id="ok">
  <div>
    <div class="box">✓</div>
    <h2 id="oTtl">ثبت شد</h2>
    <p id="oSub"></p>
    <div class="ref" id="oRef"></div>
    <div class="log">
      <div><b>[ok]</b> اعتبارسنجی سفارش</div>
      <div><b>[ok]</b> ثبت در صف پردازش</div>
      <div><b>[ok]</b> ارسال فاکتور به ربات</div>
    </div>
    <button class="exec" id="oGo" style="max-width:280px">RETURN</button>
  </div>
</div>

<div class="warn" id="warn"></div>

<script>
(function(){
"use strict";
var B = __BOOT__;
var TG = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
var $ = function(id){ return document.getElementById(id); };

if (TG) {
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.setBackgroundColor && TG.setBackgroundColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
}
function tap(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(k||'light'); }catch(e){} }
function buzz(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.notificationOccurred(k); }catch(e){} }

if (__GLOW__)  document.body.classList.add('glow-on');
if (__GRAIN__) document.body.classList.add('grain-on');

/* ── ساعت HUD ── */
(function clock(){
  function pad(n){ return n < 10 ? '0' + n : '' + n; }
  setInterval(function(){
    var d = new Date();
    $('clk').textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
  }, 1000);
})();

function fa(n){
  n = Math.round((Number(n)||0)*100)/100;
  try { return n.toLocaleString('fa-IR'); } catch(e){ return String(n); }
}
function esc(s){
  return String(s == null ? '' : s).replace(/[&<>"']/g, function(m){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}

/* ── متن‌ها ── */
$('ttl').innerHTML     = esc(B.title).replace(/\s(\S+)$/, ' <em>$1</em>');
$('sub').textContent   = B.sub || '';
$('hero').textContent  = B.hero || '';
$('cur').textContent   = B.currency;
$('balLbl').textContent= B.ui.balance;
$('q').placeholder     = B.ui.search;
$('tGo').textContent   = B.ui.submit;
$('tNo').textContent   = B.ui.close;
$('oTtl').textContent  = B.ui.done;
$('oSub').textContent  = B.ui.done_sub;
document.title = B.title;

var S = { cat:'', q:'', item:null, qty:1, busy:false, bal:0 };

function api(action, extra, ok, bad){
  var body = Object.assign({ action:action, app:B.app,
    initData: (TG && TG.initData) ? TG.initData : '' }, extra || {});
  fetch(B.api, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body)
  }).then(function(r){ return r.json().catch(function(){ return {ok:false,message:'پاسخ سرور نامعتبر بود.'}; }); })
    .then(function(j){ if (j && j.ok) ok(j); else bad(j || {}); })
    .catch(function(){ bad({ message:'ارتباط با سرور برقرار نشد.' }); });
}

var warnT;
function warn(m){
  var w = $('warn'); w.textContent = m; w.classList.add('on');
  clearTimeout(warnT); warnT = setTimeout(function(){ w.classList.remove('on'); }, 3600);
  buzz('error');
}

api('me', {}, function(j){
  S.bal = j.balance || 0;
  $('bal').textContent = fa(S.bal);
}, function(j){
  $('bal').textContent = '—';
  if (j && j.message) warn(j.message);
});

/* ── تب‌ها ── */
function drawTabs(){
  var box = $('tabs'), html = '';
  html += '<div class="tab' + (S.cat===''?' on':'') + '" data-c="">[ ' + esc(B.ui.all) + ' ]</div>';
  B.cats.forEach(function(c){
    html += '<div class="tab' + (S.cat===c.id?' on':'') + '" data-c="' + esc(c.id) + '">' +
            (c.emoji ? c.emoji + ' ' : '') + esc(c.name) + '</div>';
  });
  box.innerHTML = html;
  [].forEach.call(box.children, function(el){
    el.onclick = function(){ S.cat = el.getAttribute('data-c'); tap(); drawTabs(); drawList(); };
  });
}

function visible(){
  var q = S.q.trim().toLowerCase();
  return B.items.filter(function(i){
    if (S.cat && i.cat !== S.cat) return false;
    if (!q) return true;
    return (i.name + ' ' + i.desc + ' ' + i.badge).toLowerCase().indexOf(q) >= 0;
  });
}

function drawList(){
  var list = visible(), box = $('list');
  if (!list.length){
    box.innerHTML = '<div class="none"><div>▚</div>' + esc(B.ui.empty) + '</div>';
    return;
  }
  var html = '';
  list.forEach(function(i, n){
    html += '<div class="node' + (i.badge ? ' vip' : '') + '" data-i="' + esc(i.id) + '"' +
            ' style="animation-delay:' + Math.min(n*45, 360) + 'ms">' +
              '<div class="nrow">' +
                '<div class="ico">' + esc(i.emoji || '💠') + '</div>' +
                '<div class="nbody">' +
                  '<h3>' + esc(i.name) + (i.badge ? '<span class="flag">' + esc(i.badge) + '</span>' : '') + '</h3>' +
                  (i.desc ? '<p>' + esc(i.desc) + '</p>' : '') +
                '</div>' +
              '</div>' +
              '<div class="nfoot">' +
                '<div class="cost">' + fa(i.price) + '<em>' + esc(B.currency) +
                  (i.ask === 'qty' && i.unit ? ' / ' + esc(i.unit) : '') + '</em></div>' +
                '<button class="take">' + esc(B.ui.buy) + ' ◂</button>' +
              '</div>' +
            '</div>';
  });
  box.innerHTML = html;
  [].forEach.call(box.children, function(el){
    el.onclick = function(){ open(el.getAttribute('data-i')); };
  });
}

$('q').oninput = function(){ S.q = this.value; drawList(); };

/* ── ترمینال خرید ── */
function open(id){
  var it = null;
  for (var i=0;i<B.items.length;i++) if (B.items[i].id === id) it = B.items[i];
  if (!it) return;
  tap('medium');

  S.item = it;
  S.qty  = it.ask === 'qty' ? Math.max(1, it.min || 1) : 1;

  $('tIco').textContent  = it.emoji || '💠';
  $('tName').textContent = it.name;
  $('tDesc').textContent = it.desc || '';
  $('barPath').textContent = '/order/' + it.id;
  $('tGo').disabled = false;
  $('tGo').textContent = B.ui.submit;

  var f = $('tField'), html = '';
  if (it.ask === 'qty'){
    html += '<div class="in"><label>QTY' + (it.unit ? ' — ' + esc(it.unit) : '') + '</label>' +
            '<div class="pm">' +
              '<button type="button" data-d="-1">−</button>' +
              '<input id="fQty" type="text" inputmode="numeric" value="' + S.qty + '">' +
              '<button type="button" data-d="1">+</button>' +
            '</div>' +
            '<div class="quick">' +
              [1,2,5,10,50].map(function(m){ return '<i data-m="' + m + '">x' + m + '</i>'; }).join('') +
            '</div>' +
            '<div class="tip">حداقل ' + fa(it.min || 1) +
              (it.max > 0 ? ' · حداکثر ' + fa(it.max) : '') + '</div></div>';
  }
  if (it.ask === 'username'){
    html += '<div class="in"><label>TELEGRAM_ID</label>' +
            '<input id="fTxt" type="text" placeholder="@username" dir="ltr" style="text-align:left">' +
            '<div class="tip">آیدی عمومی حسابی که سرویس روی آن فعال می‌شود.</div></div>';
  }
  if (it.ask === 'wallet'){
    html += '<div class="in"><label>WALLET_ADDR</label>' +
            '<input id="fTxt" type="text" placeholder="UQ… / T…" dir="ltr" style="text-align:left">' +
            '<div class="tip">آدرس را کامل و بدون فاصله وارد کنید.</div></div>';
  }
  if (it.ask === 'text'){
    html += '<div class="in"><label>NOTE</label>' +
            '<textarea id="fTxt" placeholder="توضیح سفارش، لوکیشن دلخواه، تعداد کاربر…"></textarea></div>';
  }
  f.innerHTML = html;

  if (it.ask === 'qty'){
    [].forEach.call(f.querySelectorAll('[data-d]'), function(b){
      b.onclick = function(){ setQty(S.qty + Number(b.getAttribute('data-d'))); tap(); };
    });
    [].forEach.call(f.querySelectorAll('[data-m]'), function(b){
      b.onclick = function(){ setQty(S.qty * Number(b.getAttribute('data-m'))); tap(); };
    });
    $('fQty').oninput = function(){ setQty(parseFloat(this.value.replace(/[^\d.]/g,'')) || 0, true); };
  }
  total();

  $('mask').classList.add('on');
  $('term').classList.add('on');
  if (TG && TG.BackButton){ try{ TG.BackButton.show(); }catch(e){} }
}

function setQty(v, typing){
  var it = S.item; if (!it) return;
  v = Math.floor(Number(v) || 0);
  if (!typing){
    if (v < (it.min || 1)) v = it.min || 1;
    if (it.max > 0 && v > it.max) v = it.max;
  }
  S.qty = v;
  var qi = $('fQty');
  if (qi && !typing) qi.value = v;
  total();
}

function total(){
  var it = S.item; if (!it) return;
  var t = it.ask === 'qty' ? it.price * Math.max(0, S.qty) : it.price;
  $('tSum').textContent = fa(t) + ' ' + B.currency;
}

function shut(){
  $('mask').classList.remove('on');
  $('term').classList.remove('on');
  S.item = null;
  if (TG && TG.BackButton){ try{ TG.BackButton.hide(); }catch(e){} }
}
$('mask').onclick = shut;
$('tNo').onclick  = function(){ tap(); shut(); };
if (TG && TG.BackButton){ try{ TG.BackButton.onClick(shut); }catch(e){} }

$('tGo').onclick = function(){
  if (S.busy || !S.item) return;
  var it = S.item, fv = '';
  var fx = $('fTxt');
  if (fx) fv = fx.value.trim();

  if (it.ask === 'qty'){
    if (!S.qty || S.qty < (it.min || 1)) { warn('حداقل تعداد ' + fa(it.min || 1) + ' است.'); return; }
    if (it.max > 0 && S.qty > it.max)    { warn('حداکثر تعداد ' + fa(it.max) + ' است.'); return; }
  }
  if ((it.ask === 'username' || it.ask === 'wallet' || it.ask === 'text') && !fv){
    warn('لطفا فیلد بالا را پر کنید.'); return;
  }

  S.busy = true;
  this.disabled = true;
  this.textContent = B.ui.sending;
  tap('medium');

  api('order', { item: it.id, qty: S.qty, field: fv }, function(j){
    S.busy = false;
    shut();
    $('oRef').textContent = j.order || '';
    $('oSub').textContent = j.message || B.ui.done_sub;
    $('ok').classList.add('on');
    buzz('success');
  }, function(j){
    S.busy = false;
    $('tGo').disabled = false;
    $('tGo').textContent = B.ui.submit;
    warn((j && j.message) ? j.message : 'ثبت سفارش انجام نشد.');
  });
};

$('oGo').onclick = function(){ if (TG) { try{ TG.close(); }catch(e){} } else location.reload(); };

drawTabs();
drawList();
})();
</script>
</body>
</html>
HTML;
}
