<?php
/**
 * 🛡 نمای مینی‌اپ «فروش کانفیگ» — تم سایبر گرید (Cyber)
 *
 * بازنویسی‌شده برای سرعت روی موبایل:
 *   • هیچ backdrop-filter روی کارت‌ها نیست (گران‌ترین افکت روی وب‌ویو موبایل)
 *   • انیمیشن پس‌زمینه فقط transform است (روی GPU، بدون repaint)
 *   • لیست با هر حرفِ جستجو از نو ساخته نمی‌شود — فیلتر روی همان گره‌ها اعمال می‌شود
 *   • انیمیشن ورود فقط بار اول اجرا می‌شود، نه با هر فیلتر
 *   • سه سطح افکت (کامل / سبک / خاموش) + احترام به prefers-reduced-motion
 *
 * ساختار و استایلش کاملا جدا از مینی‌اپ خدمات تلگرام است.
 */

function maViewCfg($a, $boot) {
    $th   = $a['theme'] ?? [];
    return strtr(maTplCfg(), [
        '__C1__'    => $th['c1'] ?? '#00FF9C',
        '__C2__'    => $th['c2'] ?? '#00B3FF',
        '__C3__'    => $th['c3'] ?? '#FF2E97',
        '__BG__'    => $th['bg'] ?? '#04070A',
        '__FX__'    => (string)maFxLevel($th),
        '__TITLE__' => htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8'),
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

/** سطح افکت: ۲ کامل · ۱ سبک · ۰ خاموش */
function maFxLevel($th) {
    if (isset($th['fx'])) return max(0, min(2, (int)$th['fx']));
    return !empty($th['glow']) ? 2 : 1;      // سازگاری با تنظیم قدیمی
}

function maTplCfg() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="referrer" content="no-referrer">
<title>__TITLE__</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{
  --c1:__C1__; --c2:__C2__; --c3:__C3__; --bg:__BG__;
  --ink:#DFF7EE; --dim:#7A9A90; --line:color-mix(in srgb,var(--c1) 24%,transparent);
  --panel:#0A1512; --panel2:#0D1B17;
  --safe:env(safe-area-inset-bottom,0px);
  --mono:"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,monospace;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;height:100%}
body{
  background:var(--bg); color:var(--ink);
  font-family:Vazirmatn,system-ui,-apple-system,Tahoma,sans-serif;
  overflow-x:hidden;-webkit-font-smoothing:antialiased;
  overscroll-behavior-y:contain;
}

/* ═══ پس‌زمینه — ثابت و ارزان ═══
   شبکه یک لایه ثابت است (بدون انیمیشن)، و فقط یک نوار نور
   با transform روی آن حرکت می‌کند؛ transform روی GPU اجرا می‌شود
   و برخلاف background-position باعث repaint نمی‌شود. */
.bg{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}
.grid{position:absolute;left:-25%;right:-25%;bottom:0;height:52vh;opacity:.22;
  background-image:
    repeating-linear-gradient(90deg,var(--c1) 0 1px,transparent 1px 64px),
    repeating-linear-gradient(0deg,var(--c2) 0 1px,transparent 1px 64px);
  transform:perspective(200px) rotateX(74deg);transform-origin:50% 0}
.beam{position:absolute;left:0;right:0;height:34vh;top:-34vh;
  background:linear-gradient(180deg,transparent,color-mix(in srgb,var(--c1) 14%,transparent),transparent);
  will-change:transform}
.glow{position:absolute;inset:0;
  background:
    radial-gradient(85% 45% at 50% 0%,color-mix(in srgb,var(--c2) 15%,transparent),transparent 60%),
    radial-gradient(65% 38% at 50% 100%,color-mix(in srgb,var(--c1) 13%,transparent),transparent 58%)}

body.fx2 .beam{animation:sweep 7s linear infinite}
@keyframes sweep{to{transform:translate3d(0,160vh,0)}}
body.fx0 .grid,body.fx0 .glow{display:none}
body.fx0 .beam,body.fx1 .beam{display:none}
@media (prefers-reduced-motion:reduce){ .beam{display:none!important} }

.wrap{position:relative;z-index:5;max-width:600px;margin:0 auto;padding:0 14px calc(30px + var(--safe))}

/* ═══ نوار HUD ═══ */
.hud{margin:12px 0 14px;padding:11px 14px;display:flex;align-items:center;gap:11px;
  border:1px solid var(--line);background:var(--panel);
  clip-path:polygon(12px 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%,0 12px)}
.led{width:9px;height:9px;flex:0 0 auto;border-radius:50%;background:var(--c1)}
body.fx2 .led{animation:blink 1.8s ease-in-out infinite;box-shadow:0 0 10px var(--c1)}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.hud .sys{font-family:var(--mono);font-size:10px;color:var(--c1);letter-spacing:.5px;direction:ltr}
.hud .sp{flex:1}
.hud .clk{font-family:var(--mono);font-size:10.5px;color:var(--dim);direction:ltr}

/* ═══ سربرگ ═══ */
.head{margin-bottom:14px}
.head h1{margin:0;font-size:23px;font-weight:900;letter-spacing:-.5px;line-height:1.35}
body.fx2 .head h1{text-shadow:0 0 20px color-mix(in srgb,var(--c1) 40%,transparent)}
.head h1 em{font-style:normal;color:var(--c1)}
.head p{margin:5px 0 0;font-size:12px;color:var(--dim);font-family:var(--mono)}

/* ═══ اعتبار ═══ */
.credit{margin:0 0 13px;padding:14px 16px;position:relative;
  border:1px solid var(--line);background:var(--panel);
  clip-path:polygon(0 0,calc(100% - 16px) 0,100% 16px,100% 100%,16px 100%,0 calc(100% - 16px))}
.credit:before{content:"";position:absolute;top:0;right:0;width:36%;height:2px;background:var(--c1)}
.credit .k{font-size:10.5px;color:var(--dim);font-family:var(--mono);letter-spacing:.4px;margin-bottom:6px}
.credit .v{font-family:var(--mono);font-size:26px;font-weight:700;color:var(--c1);
  direction:ltr;text-align:right;letter-spacing:-.5px}
.credit .u{font-family:Vazirmatn,sans-serif;font-size:12px;color:var(--dim);margin-inline-start:5px}

.brief{margin:0 0 15px;padding:11px 13px;font-size:12px;line-height:1.9;color:#A8CCC0;
  border-inline-start:2px solid var(--c2);
  background:linear-gradient(90deg,color-mix(in srgb,var(--c2) 10%,transparent),transparent)}
.brief:before{content:"> ";font-family:var(--mono);color:var(--c2)}

/* ═══ جستجو ═══ */
.seek{position:relative;margin-bottom:12px}
.seek input{width:100%;padding:12px 38px 12px 12px;border:1px solid var(--line);background:#050C0A;
  color:var(--ink);font-family:var(--mono);font-size:12.5px;outline:none;
  clip-path:polygon(0 0,calc(100% - 10px) 0,100% 10px,100% 100%,10px 100%,0 calc(100% - 10px))}
.seek input:focus{border-color:var(--c1)}
.seek span{position:absolute;top:50%;right:13px;transform:translateY(-50%);color:var(--c1);font-size:13px}

/* ═══ تب‌ها ═══ */
.tabs{display:flex;gap:7px;overflow-x:auto;padding-bottom:13px;scrollbar-width:none;
  -webkit-overflow-scrolling:touch}
.tabs::-webkit-scrollbar{display:none}
.tab{flex:0 0 auto;padding:8px 13px;font-family:var(--mono);font-size:11.5px;font-weight:700;cursor:pointer;
  border:1px solid var(--line);background:#061410;color:var(--dim);white-space:nowrap;
  transition:background .15s,color .15s;
  clip-path:polygon(8px 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px)}
.tab.on{color:var(--bg);background:var(--c1);border-color:var(--c1)}

/* ═══ کارت سرویس ═══ */
.list{display:grid;gap:11px}
.node{position:relative;padding:14px;cursor:pointer;overflow:hidden;contain:content;
  border:1px solid var(--line);background:var(--panel);
  clip-path:polygon(0 0,calc(100% - 14px) 0,100% 14px,100% 100%,14px 100%,0 calc(100% - 14px))}
.node.hide{display:none}
.node:active{background:var(--panel2);border-color:var(--c1)}
.node:before{content:"";position:absolute;top:0;right:0;bottom:0;width:3px;background:var(--c1);opacity:.8}
.node.vip:before{background:var(--c3)}
/* انیمیشن ورود فقط بار اول — با فیلتر دوباره اجرا نمی‌شود */
.first .node{animation:slide .34s cubic-bezier(.2,.9,.3,1) backwards}
@keyframes slide{from{opacity:0;transform:translateX(-14px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){ .first .node{animation:none} }

.nrow{display:flex;align-items:flex-start;gap:11px}
.ico{width:44px;height:44px;flex:0 0 auto;display:grid;place-items:center;font-size:21px;
  border:1px solid var(--line);background:#050C0A;
  clip-path:polygon(9px 0,100% 0,100% calc(100% - 9px),calc(100% - 9px) 100%,0 100%,0 9px)}
.nbody{flex:1;min-width:0}
.nbody h3{margin:0;font-size:14.5px;font-weight:800;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.nbody p{margin:4px 0 0;font-size:11.5px;color:var(--dim);line-height:1.75}
.flag{font-family:var(--mono);font-size:9px;font-weight:700;padding:2px 6px;color:var(--bg);background:var(--c3)}
.dot{font-family:var(--mono);font-size:8.5px;padding:2px 5px;color:var(--bg);background:var(--c2)}
.nfoot{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;
  padding-top:11px;border-top:1px dashed var(--line)}
.cost{font-family:var(--mono);font-size:16px;font-weight:700;color:var(--c1);direction:ltr}
.cost em{font-family:Vazirmatn,sans-serif;font-style:normal;font-size:10.5px;color:var(--dim);margin-inline-start:4px}
.take{padding:8px 15px;font-family:var(--mono);font-size:11.5px;font-weight:700;color:var(--bg);
  background:var(--c1);border:0;cursor:pointer;
  clip-path:polygon(7px 0,100% 0,100% calc(100% - 7px),calc(100% - 7px) 100%,0 100%,0 7px)}

.none{text-align:center;padding:44px 18px;color:var(--dim);font-size:12.5px;font-family:var(--mono)}
.none div{font-size:40px;margin-bottom:10px;opacity:.4}
.load{height:96px;border:1px solid var(--line);background:var(--panel)}
body.fx2 .load{animation:pan 1.2s ease-in-out infinite alternate}
@keyframes pan{to{opacity:.5}}

/* ═══ ترمینال خرید ═══ */
.mask{position:fixed;inset:0;z-index:40;background:rgba(0,4,3,.9);
  opacity:0;pointer-events:none;transition:opacity .22s}
.mask.on{opacity:1;pointer-events:auto}
.term{position:fixed;left:0;right:0;bottom:0;z-index:41;transform:translate3d(0,102%,0);
  transition:transform .3s cubic-bezier(.2,.9,.25,1);max-height:92vh;overflow-y:auto;
  background:#071310;border-top:2px solid var(--c1);padding:0 0 calc(20px + var(--safe));
  -webkit-overflow-scrolling:touch;will-change:transform}
.term.on{transform:translate3d(0,0,0)}
.bar{display:flex;align-items:center;gap:7px;padding:11px 14px;border-bottom:1px solid var(--line);
  position:sticky;top:0;background:#071310;z-index:2}
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
.in input,.in textarea{width:100%;padding:13px;border:1px solid var(--line);background:#040A08;
  color:var(--ink);font-family:var(--mono);font-size:13.5px;outline:none}
.in textarea{min-height:78px;resize:vertical;font-family:Vazirmatn,sans-serif;font-size:13px}
.in input:focus,.in textarea:focus{border-color:var(--c1)}
.in .tip{font-size:10.5px;color:var(--dim);margin-top:6px;line-height:1.7}

.pm{display:flex;gap:9px;align-items:center}
.pm button{width:44px;height:46px;flex:0 0 auto;border:1px solid var(--line);background:#040A08;
  color:var(--c1);font-family:var(--mono);font-size:19px;cursor:pointer}
.pm button:active{background:var(--c1);color:var(--bg)}
.pm input{text-align:center;font-weight:700;font-size:16px}
.quick{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}
.quick i{padding:5px 11px;font-family:var(--mono);font-size:11px;font-style:normal;cursor:pointer;
  border:1px solid var(--line);background:#040A08;color:var(--dim)}
.quick i:active{background:var(--c1);color:var(--bg)}

.sum{margin:15px 0;padding:14px 15px;display:flex;justify-content:space-between;align-items:center;
  border:1px solid var(--c1);background:color-mix(in srgb,var(--c1) 10%,transparent);
  clip-path:polygon(0 0,calc(100% - 12px) 0,100% 12px,100% 100%,12px 100%,0 calc(100% - 12px))}
.sum span{font-size:11.5px;color:var(--dim);font-family:var(--mono)}
.sum b{font-family:var(--mono);font-size:20px;font-weight:700;color:var(--c1);direction:ltr}

.exec{width:100%;padding:16px;border:0;cursor:pointer;font-family:var(--mono);font-size:14px;font-weight:700;
  color:var(--bg);background:var(--c1);
  clip-path:polygon(12px 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%,0 12px)}
.exec:active{opacity:.86}
.exec[disabled]{opacity:.5;cursor:default}
.abort{width:100%;margin-top:9px;padding:13px;border:1px solid var(--line);background:transparent;
  color:var(--dim);font-family:var(--mono);font-size:12.5px;cursor:pointer}

/* ═══ موفقیت ═══ */
.ok{position:fixed;inset:0;z-index:60;display:none;place-items:center;text-align:center;padding:28px;
  background-color:var(--bg);
  background-image:radial-gradient(70% 50% at 50% 45%,color-mix(in srgb,var(--c1) 20%,transparent),transparent 76%)}
.ok.on{display:grid}
.box{width:104px;height:104px;margin:0 auto 22px;display:grid;place-items:center;font-size:46px;color:var(--bg);
  background:var(--c1);
  clip-path:polygon(18px 0,100% 0,100% calc(100% - 18px),calc(100% - 18px) 100%,0 100%,0 18px)}
body.fx2 .box{animation:zoom .45s cubic-bezier(.2,1.4,.4,1) backwards}
@keyframes zoom{from{transform:scale(.5);opacity:0}to{transform:none;opacity:1}}
.ok h2{margin:0 0 9px;font-size:20px;font-weight:900}
.ok p{margin:0 0 22px;font-size:12.5px;color:var(--dim);line-height:1.9;max-width:300px}
.ok .ref{font-family:var(--mono);font-size:11.5px;padding:9px 15px;margin-bottom:22px;direction:ltr;
  border:1px dashed var(--line);color:var(--c1)}
.log{font-family:var(--mono);font-size:10.5px;color:var(--dim);max-width:290px;margin:0 auto 22px;line-height:2}
.log b{color:var(--c1);font-weight:400}

/* ═══ هشدار ═══ */
.warn{position:fixed;top:12px;left:50%;transform:translate(-50%,-160%);z-index:80;max-width:88vw;
  padding:12px 17px;font-size:12.5px;font-weight:700;text-align:center;line-height:1.7;
  color:var(--bg);background:var(--c3);transition:transform .3s cubic-bezier(.2,1.3,.4,1);
  clip-path:polygon(10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%,0 10px)}
.warn.on{transform:translate(-50%,0)}
</style>
</head>
<body>
<div class="bg"><div class="grid"></div><div class="beam"></div><div class="glow"></div></div>

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

  <div class="seek"><input id="q" placeholder="جستجو…" autocomplete="off" spellcheck="false"><span>⌕</span></div>
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
var FX = __FX__;
var TG = (window.Telegram && window.Telegram.WebApp) ? window.Telegram.WebApp : null;
var $  = function(id){ return document.getElementById(id); };

document.body.className = 'fx' + FX;

if (TG) {
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.setBackgroundColor && TG.setBackgroundColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
}
function tap(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(k||'light'); }catch(e){} }
function buzz(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.notificationOccurred(k); }catch(e){} }

/* ── ساعت HUD — با پنهان شدن صفحه متوقف می‌شود ── */
(function(){
  var t = null;
  function pad(n){ return n < 10 ? '0' + n : '' + n; }
  function tick(){
    var d = new Date();
    $('clk').textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
  }
  function start(){ if (!t) { tick(); t = setInterval(tick, 1000); } }
  function stop(){ if (t) { clearInterval(t); t = null; } }
  document.addEventListener('visibilitychange', function(){ document.hidden ? stop() : start(); });
  start();
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

var S = { cat:'', q:'', item:null, qty:1, busy:false, bal:0, nodes:[] };

function api(action, extra, ok, bad){
  var body = Object.assign({ action:action, app:B.app,
    initData: (TG && TG.initData) ? TG.initData : '' }, extra || {});
  var ctl = null, timer = null;
  try { ctl = new AbortController(); timer = setTimeout(function(){ ctl.abort(); }, 20000); } catch(e){}

  fetch(B.api, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body), signal: ctl ? ctl.signal : undefined,
    cache:'no-store', credentials:'omit', referrerPolicy:'no-referrer'
  }).then(function(r){
    return r.json().catch(function(){ return { ok:false, message:'پاسخ سرور نامعتبر بود.' }; });
  }).then(function(j){
    if (timer) clearTimeout(timer);
    if (j && j.ok) ok(j); else bad(j || {});
  }).catch(function(){
    if (timer) clearTimeout(timer);
    bad({ message:'ارتباط با سرور برقرار نشد.' });
  });
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
            (c.emoji ? esc(c.emoji) + ' ' : '') + esc(c.name) + '</div>';
  });
  box.innerHTML = html;
}

/* یک بار ساخته می‌شود، بعدش فقط نمایش/پنهان — علت اصلی روان بودن لیست */
function buildList(){
  var box = $('list');
  if (!B.items.length){
    box.innerHTML = '<div class="none"><div>▚</div>' + esc(B.ui.empty) + '</div>';
    return;
  }
  var html = '';
  B.items.forEach(function(i, n){
    html += '<div class="node' + (i.badge ? ' vip' : '') + '" data-i="' + esc(i.id) + '"' +
            ' style="animation-delay:' + Math.min(n*40, 320) + 'ms">' +
              '<div class="nrow">' +
                '<div class="ico">' + esc(i.emoji || '💠') + '</div>' +
                '<div class="nbody">' +
                  '<h3>' + esc(i.name) +
                    (i.badge ? '<span class="flag">' + esc(i.badge) + '</span>' : '') +
                    (i.live  ? '<span class="dot">LIVE</span>' : '') +
                  '</h3>' +
                  (i.desc ? '<p>' + esc(i.desc) + '</p>' : '') +
                '</div>' +
              '</div>' +
              '<div class="nfoot">' +
                '<div class="cost">' + fa(i.price) + '<em>' + esc(B.currency) +
                  (i.ask === 'qty' && i.unit ? ' / ' + esc(i.unit) : '') + '</em></div>' +
                '<button class="take" type="button">' + esc(B.ui.buy) + ' ◂</button>' +
              '</div>' +
            '</div>';
  });
  box.classList.add('first');
  box.innerHTML = html;
  S.nodes = [].slice.call(box.children);

  // انیمیشن ورود فقط همین یک بار
  setTimeout(function(){ box.classList.remove('first'); }, 700);

  // یک شنونده برای کل لیست به‌جای یکی برای هر کارت
  box.addEventListener('click', function(ev){
    var el = ev.target.closest ? ev.target.closest('.node') : null;
    if (el && el.getAttribute) open(el.getAttribute('data-i'));
  });
}

/* فیلتر بدون بازسازی DOM */
function applyFilter(){
  var q = S.q.trim().toLowerCase(), shown = 0;
  for (var n = 0; n < S.nodes.length; n++){
    var el = S.nodes[n], it = B.items[n];
    var ok = (!S.cat || it.cat === S.cat) &&
             (!q || (it.name + ' ' + it.desc + ' ' + it.badge).toLowerCase().indexOf(q) >= 0);
    if (ok) { el.classList.remove('hide'); shown++; }
    else    { el.classList.add('hide'); }
  }
  var none = document.getElementById('noneBox');
  if (!shown && !none){
    none = document.createElement('div');
    none.id = 'noneBox'; none.className = 'none';
    none.innerHTML = '<div>▚</div>' + esc(B.ui.empty);
    $('list').appendChild(none);
  } else if (shown && none){
    none.remove();
  }
}

$('tabs').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('.tab') : null;
  if (!el) return;
  S.cat = el.getAttribute('data-c');
  tap();
  drawTabs();
  applyFilter();
});

var qT;
$('q').addEventListener('input', function(){
  var v = this.value;
  clearTimeout(qT);
  qT = setTimeout(function(){ S.q = v; applyFilter(); }, 120);
});

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
            '<input id="fTxt" type="text" placeholder="@username" dir="ltr" style="text-align:left" ' +
            'autocomplete="off" spellcheck="false" maxlength="64">' +
            '<div class="tip">آیدی عمومی حسابی که سرویس روی آن فعال می‌شود.</div></div>';
  }
  if (it.ask === 'wallet'){
    html += '<div class="in"><label>WALLET_ADDR</label>' +
            '<input id="fTxt" type="text" placeholder="UQ… / T…" dir="ltr" style="text-align:left" ' +
            'autocomplete="off" spellcheck="false" maxlength="128">' +
            '<div class="tip">آدرس را کامل و بدون فاصله وارد کنید.</div></div>';
  }
  if (it.ask === 'text'){
    html += '<div class="in"><label>NOTE</label>' +
            '<textarea id="fTxt" maxlength="300" placeholder="توضیح سفارش، لوکیشن دلخواه، تعداد کاربر…"></textarea></div>';
  }
  f.innerHTML = html;

  if (it.ask === 'qty'){
    f.addEventListener('click', function(ev){
      var b = ev.target;
      if (b.hasAttribute && b.hasAttribute('data-d')){ setQty(S.qty + Number(b.getAttribute('data-d'))); tap(); }
      if (b.hasAttribute && b.hasAttribute('data-m')){ setQty(S.qty * Number(b.getAttribute('data-m'))); tap(); }
    });
    $('fQty').addEventListener('input', function(){
      setQty(parseFloat(this.value.replace(/[^\d.]/g,'')) || 0, true);
    });
  }
  total();

  $('mask').classList.add('on');
  $('term').classList.add('on');
  if (TG && TG.BackButton){ try{ TG.BackButton.show(); }catch(e){} }
}

function setQty(v, typing){
  var it = S.item; if (!it) return;
  v = Math.floor(Number(v) || 0);
  if (!isFinite(v) || v < 0) v = 0;
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

  api('order', { item: it.id, qty: S.qty, field: fv, seen_price: it.price }, function(j){
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
    // قیمت زنده عوض شده — قیمت تازه را بنشان و به کاربر بگو
    if (j && j.error === 'price_changed' && j.price){
      it.price = j.price;
      total();
      var node = S.nodes[B.items.indexOf(it)];
      if (node){
        var c = node.querySelector('.cost');
        if (c) c.innerHTML = fa(j.price) + '<em>' + esc(B.currency) +
                             (it.ask === 'qty' && it.unit ? ' / ' + esc(it.unit) : '') + '</em>';
      }
    }
    warn((j && j.message) ? j.message : 'ثبت سفارش انجام نشد.');
  });
};

$('oGo').onclick = function(){ if (TG) { try{ TG.close(); }catch(e){} } else location.reload(); };

drawTabs();
buildList();
applyFilter();
})();
</script>
</body>
</html>
HTML;
}
