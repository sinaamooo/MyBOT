<?php
/**
 * 🌌 نمای مینی‌اپ «خدمات تلگرام» — تم شفق قطبی (Aurora)
 * بنفش/فیروزه‌ای، شیشه‌ای، با نور متحرک پس‌زمینه و ستاره‌های شناور.
 * کاملا جدا از مینی‌اپ کانفیگ — نه استایلش مشترک است نه ساختارش.
 */

function maViewTg($a, $boot) {
    $th   = $a['theme'] ?? [];
    $c1   = $th['c1'] ?? '#7C4DFF';
    $c2   = $th['c2'] ?? '#00E5FF';
    $c3   = $th['c3'] ?? '#FF3D9A';
    $bg   = $th['bg'] ?? '#080512';
    $glow = !empty($th['glow']) ? '1' : '0';
    $grain= !empty($th['grain']) ? '1' : '0';

    $tpl = maTplTg();
    return strtr($tpl, [
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

function maTplTg() {
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
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;900&display=swap" rel="stylesheet">
<style>
:root{
  --c1:__C1__; --c2:__C2__; --c3:__C3__; --bg:__BG__;
  --ink:#F4F2FF; --dim:#A79FC6; --line:rgba(255,255,255,.09);
  --card:rgba(255,255,255,.045); --card2:rgba(255,255,255,.075);
  --r:22px; --safe:env(safe-area-inset-bottom,0px);
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;height:100%}
body{
  background:var(--bg); color:var(--ink);
  font-family:Vazirmatn,system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  overflow-x:hidden; -webkit-font-smoothing:antialiased;
}

/* ═══ شفق پس‌زمینه ═══ */
.sky{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.blob{position:absolute;border-radius:50%;filter:blur(70px);opacity:.55;will-change:transform}
.b1{width:78vw;height:78vw;background:var(--c1);top:-26vw;right:-20vw;animation:drift1 22s ease-in-out infinite}
.b2{width:66vw;height:66vw;background:var(--c2);top:24vh;left:-26vw;opacity:.4;animation:drift2 26s ease-in-out infinite}
.b3{width:56vw;height:56vw;background:var(--c3);bottom:-18vh;right:-10vw;opacity:.34;animation:drift3 30s ease-in-out infinite}
@keyframes drift1{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(-8vw,7vh) scale(1.14)}}
@keyframes drift2{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(9vw,-6vh) scale(1.2)}}
@keyframes drift3{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(-6vw,-8vh) scale(1.1)}}
#stars{position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.75}
.veil{position:fixed;inset:0;z-index:2;pointer-events:none;
  background:radial-gradient(120% 80% at 50% 0%,transparent 20%,var(--bg) 92%)}
.grain{position:fixed;inset:0;z-index:3;pointer-events:none;opacity:.05;display:none;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3'/></filter><rect width='140' height='140' filter='url(%23n)' opacity='.55'/></svg>")}
body.grain-on .grain{display:block}

.wrap{position:relative;z-index:5;max-width:560px;margin:0 auto;padding:0 16px calc(28px + var(--safe))}

/* ═══ سربرگ ═══ */
.top{padding:18px 2px 12px;display:flex;align-items:center;gap:12px}
.ava{width:46px;height:46px;border-radius:16px;flex:0 0 auto;display:grid;place-items:center;
  font-weight:900;font-size:19px;color:#0B0616;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  box-shadow:0 8px 24px -8px var(--c1)}
.who{flex:1;min-width:0}
.who h1{margin:0;font-size:19px;font-weight:900;letter-spacing:-.3px;
  background:linear-gradient(90deg,#fff,var(--c2));-webkit-background-clip:text;background-clip:text;color:transparent}
.who p{margin:2px 0 0;font-size:12px;color:var(--dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

.purse{margin:6px 0 14px;padding:15px 17px;border-radius:20px;position:relative;overflow:hidden;
  border:1px solid var(--line);background:var(--card);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px)}
.purse:before{content:"";position:absolute;inset:0;opacity:.16;
  background:linear-gradient(120deg,var(--c1),transparent 45%,var(--c3))}
.purse .lbl{position:relative;font-size:11.5px;color:var(--dim);margin-bottom:5px}
.purse .val{position:relative;font-size:26px;font-weight:900;letter-spacing:-.5px;
  background:linear-gradient(90deg,var(--c2),#fff 60%);-webkit-background-clip:text;background-clip:text;color:transparent}
.purse .cur{font-size:13px;font-weight:500;color:var(--dim);margin-inline-start:4px}
.purse .spark{position:absolute;inset:0;transform:translateX(-100%);
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.13),transparent);animation:spark 4.5s ease-in-out infinite}
@keyframes spark{0%,72%{transform:translateX(-100%)}100%{transform:translateX(100%)}}

.hero{margin:0 0 16px;padding:13px 15px;border-radius:16px;font-size:12.5px;line-height:1.85;color:#D9D3F2;
  border:1px solid var(--line);background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.015))}
.hero b{color:var(--c2)}

/* ═══ جستجو ═══ */
.find{position:relative;margin:0 0 14px}
.find input{width:100%;padding:13px 42px 13px 14px;border-radius:15px;border:1px solid var(--line);
  background:var(--card);color:var(--ink);font-family:inherit;font-size:13.5px;outline:none;transition:.2s}
.find input:focus{border-color:var(--c1);box-shadow:0 0 0 3px color-mix(in srgb,var(--c1) 18%,transparent)}
.find span{position:absolute;top:50%;right:14px;transform:translateY(-50%);opacity:.5;font-size:15px}

/* ═══ تب دسته‌ها ═══ */
.tabs{display:flex;gap:8px;overflow-x:auto;padding:2px 2px 14px;scrollbar-width:none}
.tabs::-webkit-scrollbar{display:none}
.tab{flex:0 0 auto;padding:9px 15px;border-radius:13px;font-size:13px;font-weight:700;cursor:pointer;
  border:1px solid var(--line);background:var(--card);color:var(--dim);transition:.22s;white-space:nowrap}
.tab.on{color:#0B0616;border-color:transparent;background:linear-gradient(135deg,var(--c1),var(--c2));
  box-shadow:0 8px 22px -10px var(--c1);transform:translateY(-1px)}

/* ═══ کارت سرویس ═══ */
.grid{display:grid;gap:12px}
.card{position:relative;border-radius:var(--r);padding:15px;overflow:hidden;cursor:pointer;
  border:1px solid var(--line);background:var(--card);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
  display:flex;align-items:center;gap:13px;transition:transform .18s,border-color .18s;
  animation:rise .42s cubic-bezier(.2,.9,.3,1) backwards}
@keyframes rise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.card:active{transform:scale(.982)}
.card:before{content:"";position:absolute;inset:0;opacity:0;transition:.25s;
  background:linear-gradient(120deg,color-mix(in srgb,var(--c1) 22%,transparent),transparent 60%)}
.card.hot:before{opacity:1}
.orb{width:54px;height:54px;flex:0 0 auto;border-radius:18px;display:grid;place-items:center;font-size:26px;
  background:linear-gradient(135deg,color-mix(in srgb,var(--c1) 32%,transparent),color-mix(in srgb,var(--c2) 22%,transparent));
  border:1px solid var(--line);position:relative}
body.glow-on .orb{box-shadow:0 10px 26px -12px var(--c1)}
.meta{flex:1;min-width:0;position:relative}
.meta h3{margin:0;font-size:14.5px;font-weight:800;display:flex;align-items:center;gap:6px}
.meta p{margin:3px 0 0;font-size:11.5px;color:var(--dim);line-height:1.7;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tag{font-size:9.5px;font-weight:800;padding:2px 7px;border-radius:7px;color:#0B0616;
  background:linear-gradient(135deg,var(--c3),var(--c1));flex:0 0 auto}
.price{position:relative;text-align:center;flex:0 0 auto}
.price b{display:block;font-size:15px;font-weight:900;letter-spacing:-.3px;
  background:linear-gradient(90deg,var(--c2),var(--c1));-webkit-background-clip:text;background-clip:text;color:transparent}
.price i{display:block;font-size:9.5px;font-style:normal;color:var(--dim);margin-top:2px}

.void{text-align:center;padding:44px 20px;color:var(--dim);font-size:13px}
.void div{font-size:44px;margin-bottom:10px;opacity:.55}

/* ═══ اسکلت بارگذاری ═══ */
.skel{height:84px;border-radius:var(--r);border:1px solid var(--line);
  background:linear-gradient(90deg,rgba(255,255,255,.03),rgba(255,255,255,.075),rgba(255,255,255,.03));
  background-size:200% 100%;animation:sh 1.3s linear infinite}
@keyframes sh{to{background-position:-200% 0}}

/* ═══ شیت خرید ═══ */
.scrim{position:fixed;inset:0;z-index:40;background:rgba(4,2,10,.72);backdrop-filter:blur(7px);
  opacity:0;pointer-events:none;transition:.28s}
.scrim.on{opacity:1;pointer-events:auto}
.sheet{position:fixed;left:0;right:0;bottom:0;z-index:41;transform:translateY(102%);
  transition:transform .38s cubic-bezier(.2,.9,.25,1);
  background:linear-gradient(180deg,#15102A,#0B0718);
  border-radius:30px 30px 0 0;border-top:1px solid var(--line);
  padding:10px 18px calc(22px + var(--safe));max-height:92vh;overflow-y:auto;
  box-shadow:0 -24px 60px rgba(0,0,0,.6)}
.sheet.on{transform:none}
.grip{width:42px;height:4px;border-radius:4px;background:rgba(255,255,255,.22);margin:4px auto 16px}
.sheet .head{display:flex;align-items:center;gap:13px;margin-bottom:16px}
.sheet .head .orb{width:58px;height:58px;font-size:29px}
.sheet .head h2{margin:0;font-size:17px;font-weight:900}
.sheet .head p{margin:4px 0 0;font-size:12px;color:var(--dim);line-height:1.7}

.field{margin-bottom:14px}
.field label{display:block;font-size:12px;font-weight:700;color:var(--dim);margin-bottom:7px}
.field input,.field textarea{width:100%;padding:14px;border-radius:15px;border:1px solid var(--line);
  background:rgba(255,255,255,.05);color:var(--ink);font-family:inherit;font-size:14.5px;outline:none;transition:.2s}
.field textarea{min-height:80px;resize:vertical;font-size:13px}
.field input:focus,.field textarea:focus{border-color:var(--c1);
  box-shadow:0 0 0 3px color-mix(in srgb,var(--c1) 18%,transparent)}
.field .hint{font-size:10.5px;color:var(--dim);margin-top:6px}

.step{display:flex;align-items:center;gap:10px}
.step button{width:46px;height:46px;flex:0 0 auto;border-radius:15px;border:1px solid var(--line);
  background:rgba(255,255,255,.06);color:var(--ink);font-size:21px;font-weight:700;cursor:pointer;transition:.16s}
.step button:active{transform:scale(.92);background:color-mix(in srgb,var(--c1) 28%,transparent)}
.step input{text-align:center;font-weight:900;font-size:17px}
.chips{display:flex;gap:7px;flex-wrap:wrap;margin-top:9px}
.chip{padding:6px 12px;border-radius:10px;font-size:11.5px;font-weight:700;cursor:pointer;
  border:1px solid var(--line);background:rgba(255,255,255,.045);color:var(--dim);transition:.16s}
.chip:active{background:color-mix(in srgb,var(--c1) 30%,transparent);color:#fff}

.total{display:flex;justify-content:space-between;align-items:center;margin:16px 0;padding:15px 16px;
  border-radius:18px;border:1px solid var(--line);
  background:linear-gradient(120deg,color-mix(in srgb,var(--c1) 16%,transparent),color-mix(in srgb,var(--c3) 10%,transparent))}
.total span{font-size:12.5px;color:var(--dim)}
.total b{font-size:21px;font-weight:900;
  background:linear-gradient(90deg,var(--c2),#fff);-webkit-background-clip:text;background-clip:text;color:transparent}

.go{width:100%;padding:17px;border:0;border-radius:18px;cursor:pointer;
  font-family:inherit;font-size:15.5px;font-weight:900;color:#0B0616;
  background:linear-gradient(135deg,var(--c1),var(--c2));transition:.2s;position:relative;overflow:hidden}
body.glow-on .go{box-shadow:0 14px 34px -14px var(--c1)}
.go:active{transform:scale(.985)}
.go[disabled]{opacity:.55;cursor:default}
.ghost{width:100%;margin-top:9px;padding:14px;border-radius:16px;cursor:pointer;
  border:1px solid var(--line);background:transparent;color:var(--dim);font-family:inherit;font-size:13.5px;font-weight:700}

/* ═══ موفقیت ═══ */
.win{position:fixed;inset:0;z-index:60;display:none;place-items:center;text-align:center;padding:30px;
  background-color:var(--bg);
  background-image:radial-gradient(80% 60% at 50% 40%,color-mix(in srgb,var(--c1) 28%,transparent),transparent 72%)}
.win.on{display:grid}
.ring{width:112px;height:112px;margin:0 auto 22px;border-radius:50%;display:grid;place-items:center;font-size:52px;
  background:linear-gradient(135deg,var(--c1),var(--c2));animation:pop .55s cubic-bezier(.2,1.5,.4,1) backwards}
@keyframes pop{from{transform:scale(0) rotate(-45deg);opacity:0}to{transform:none;opacity:1}}
.ring:after{content:"";position:absolute;width:112px;height:112px;border-radius:50%;
  border:2px solid var(--c2);animation:pulse 1.9s ease-out infinite}
@keyframes pulse{from{transform:scale(1);opacity:.85}to{transform:scale(1.9);opacity:0}}
.win h2{margin:0 0 9px;font-size:21px;font-weight:900}
.win p{margin:0 0 26px;font-size:13px;color:var(--dim);line-height:1.9;max-width:300px}
.win .code{font-family:ui-monospace,monospace;font-size:12px;padding:8px 14px;border-radius:11px;
  border:1px solid var(--line);background:var(--card);margin-bottom:22px;direction:ltr}

/* ═══ پیام خطا ═══ */
.toast{position:fixed;top:14px;left:50%;transform:translate(-50%,-150%);z-index:80;
  padding:13px 18px;border-radius:15px;font-size:13px;font-weight:700;max-width:88vw;text-align:center;
  background:linear-gradient(135deg,#FF3355,#B1004B);color:#fff;transition:transform .34s cubic-bezier(.2,1.3,.4,1);
  box-shadow:0 14px 34px -12px #FF3355;line-height:1.7}
.toast.on{transform:translate(-50%,0)}
</style>
</head>
<body>
<div class="sky"><div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div></div>
<canvas id="stars"></canvas>
<div class="veil"></div><div class="grain"></div>

<div class="wrap">
  <div class="top">
    <div class="ava" id="ava">★</div>
    <div class="who"><h1 id="ttl">—</h1><p id="sub">—</p></div>
  </div>

  <div class="purse">
    <div class="spark"></div>
    <div class="lbl" id="balLbl">موجودی شما</div>
    <div class="val"><span id="bal">…</span><span class="cur" id="cur"></span></div>
  </div>

  <div class="hero" id="hero"></div>

  <div class="find"><input id="q" placeholder="جستجو…"><span>🔎</span></div>
  <div class="tabs" id="tabs"></div>
  <div class="grid" id="grid">
    <div class="skel"></div><div class="skel"></div><div class="skel"></div>
  </div>
</div>

<div class="scrim" id="scrim"></div>
<div class="sheet" id="sheet">
  <div class="grip"></div>
  <div class="head">
    <div class="orb" id="sOrb">💠</div>
    <div style="flex:1;min-width:0"><h2 id="sName">—</h2><p id="sDesc"></p></div>
  </div>
  <div id="sField"></div>
  <div class="total"><span>مبلغ قابل پرداخت</span><b id="sTotal">۰</b></div>
  <button class="go" id="sGo">تایید و ادامه</button>
  <button class="ghost" id="sNo">بستن</button>
</div>

<div class="win" id="win">
  <div>
    <div class="ring">✓</div>
    <h2 id="wTtl">سفارش ثبت شد</h2>
    <p id="wSub"></p>
    <div class="code" id="wCode"></div>
    <button class="go" id="wGo" style="max-width:280px">بازگشت به ربات</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
(function(){
"use strict";
var B = __BOOT__;
var TG = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
var $ = function(id){ return document.getElementById(id); };

/* ── راه‌اندازی تلگرام ── */
if (TG) {
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.setBackgroundColor && TG.setBackgroundColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
}
function tap(kind){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(kind||'light'); }catch(e){} }
function buzz(kind){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.notificationOccurred(kind); }catch(e){} }

if (__GLOW__)  document.body.classList.add('glow-on');
if (__GRAIN__) document.body.classList.add('grain-on');

/* ── ستاره‌های پس‌زمینه ── */
(function stars(){
  var cv = $('stars'), cx = cv.getContext('2d'), st = [], W, H;
  function size(){ W = cv.width = innerWidth; H = cv.height = innerHeight; }
  size(); addEventListener('resize', size);
  for (var i=0;i<64;i++) st.push({x:Math.random()*W,y:Math.random()*H,r:Math.random()*1.4+.3,
                                  s:Math.random()*.22+.04,o:Math.random()*.6+.2});
  (function loop(){
    cx.clearRect(0,0,W,H);
    for (var i=0;i<st.length;i++){ var p=st[i];
      p.y -= p.s; if (p.y < -3){ p.y = H+3; p.x = Math.random()*W; }
      cx.globalAlpha = p.o; cx.fillStyle = '#fff';
      cx.beginPath(); cx.arc(p.x,p.y,p.r,0,6.284); cx.fill();
    }
    requestAnimationFrame(loop);
  })();
})();

/* ── اعداد فارسی ── */
function fa(n){
  n = Math.round((Number(n)||0)*100)/100;
  try { return n.toLocaleString('fa-IR'); } catch(e){ return String(n); }
}

/* ── متن‌ها ── */
$('ttl').textContent  = B.title;
$('sub').textContent  = B.sub || '';
$('hero').textContent = B.hero || '';
$('cur').textContent  = B.currency;
$('balLbl').textContent = B.ui.balance;
$('q').placeholder    = B.ui.search;
$('sGo').textContent  = B.ui.submit;
$('sNo').textContent  = B.ui.close;
$('wTtl').textContent = B.ui.done;
$('wSub').textContent = B.ui.done_sub;
$('ava').textContent  = (B.title || '★').trim().charAt(0);
document.title = B.title;

/* ── وضعیت ── */
var S = { cat:'', q:'', item:null, qty:1, busy:false, bal:0 };

/* ── ارتباط با سرور ── */
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

var toastT;
function toast(m){
  var t = $('toast'); t.textContent = m; t.classList.add('on');
  clearTimeout(toastT); toastT = setTimeout(function(){ t.classList.remove('on'); }, 3600);
  buzz('error');
}

/* ── موجودی ── */
api('me', {}, function(j){
  S.bal = j.balance || 0;
  $('bal').textContent = fa(S.bal);
}, function(j){
  $('bal').textContent = '—';
  if (j && j.message) toast(j.message);
});

/* ── تب‌ها ── */
function drawTabs(){
  var box = $('tabs'), html = '';
  html += '<div class="tab' + (S.cat===''?' on':'') + '" data-c="">' + B.ui.all + '</div>';
  B.cats.forEach(function(c){
    html += '<div class="tab' + (S.cat===c.id?' on':'') + '" data-c="' + c.id + '">' +
            (c.emoji ? c.emoji + ' ' : '') + esc(c.name) + '</div>';
  });
  box.innerHTML = html;
  [].forEach.call(box.children, function(el){
    el.onclick = function(){ S.cat = el.getAttribute('data-c'); tap(); drawTabs(); drawGrid(); };
  });
}

function esc(s){
  return String(s == null ? '' : s).replace(/[&<>"']/g, function(m){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}

/* ── فهرست سرویس‌ها ── */
function visible(){
  var q = S.q.trim().toLowerCase();
  return B.items.filter(function(i){
    if (S.cat && i.cat !== S.cat) return false;
    if (!q) return true;
    return (i.name + ' ' + i.desc + ' ' + i.badge).toLowerCase().indexOf(q) >= 0;
  });
}

function drawGrid(){
  var list = visible(), box = $('grid');
  if (!list.length){
    box.innerHTML = '<div class="void"><div>🌙</div>' + esc(B.ui.empty) + '</div>';
    return;
  }
  var html = '';
  list.forEach(function(i, n){
    html += '<div class="card' + (i.badge ? ' hot' : '') + '" data-i="' + esc(i.id) + '"' +
            ' style="animation-delay:' + Math.min(n*45, 380) + 'ms">' +
              '<div class="orb">' + esc(i.emoji || '💠') + '</div>' +
              '<div class="meta">' +
                '<h3>' + esc(i.name) + (i.badge ? '<span class="tag">' + esc(i.badge) + '</span>' : '') + '</h3>' +
                (i.desc ? '<p>' + esc(i.desc) + '</p>' : '') +
              '</div>' +
              '<div class="price"><b>' + fa(i.price) + '</b><i>' + esc(B.currency) +
                (i.ask === 'qty' && i.unit ? ' / ' + esc(i.unit) : '') + '</i></div>' +
            '</div>';
  });
  box.innerHTML = html;
  [].forEach.call(box.children, function(el){
    el.onclick = function(){ open(el.getAttribute('data-i')); };
  });
}

$('q').oninput = function(){ S.q = this.value; drawGrid(); };

/* ── شیت خرید ── */
function open(id){
  var it = null;
  for (var i=0;i<B.items.length;i++) if (B.items[i].id === id) it = B.items[i];
  if (!it) return;
  tap('medium');

  S.item = it;
  S.qty  = it.ask === 'qty' ? Math.max(1, it.min || 1) : 1;

  $('sOrb').textContent  = it.emoji || '💠';
  $('sName').textContent = it.name;
  $('sDesc').textContent = it.desc || '';
  $('sGo').disabled = false;
  $('sGo').textContent = B.ui.submit;

  var f = $('sField'), html = '';
  if (it.ask === 'qty'){
    html += '<div class="field"><label>🔢 تعداد' + (it.unit ? ' (' + esc(it.unit) + ')' : '') + '</label>' +
            '<div class="step">' +
              '<button type="button" data-d="-1">−</button>' +
              '<input id="fQty" type="text" inputmode="numeric" value="' + S.qty + '">' +
              '<button type="button" data-d="1">+</button>' +
            '</div>' +
            '<div class="chips">' +
              [1,5,10,50,100].map(function(m){
                return '<div class="chip" data-m="' + m + '">×' + fa(m) + '</div>';
              }).join('') +
            '</div>' +
            '<div class="hint">حداقل ' + fa(it.min || 1) +
              (it.max > 0 ? ' · حداکثر ' + fa(it.max) : '') + '</div></div>';
  }
  if (it.ask === 'username'){
    html += '<div class="field"><label>📎 آیدی تلگرام گیرنده</label>' +
            '<input id="fTxt" type="text" placeholder="@username" dir="ltr" style="text-align:left">' +
            '<div class="hint">آیدی عمومی حساب — بدون آن سفارش قابل انجام نیست.</div></div>';
  }
  if (it.ask === 'wallet'){
    html += '<div class="field"><label>💼 آدرس ولت</label>' +
            '<input id="fTxt" type="text" placeholder="UQ… / T…" dir="ltr" style="text-align:left">' +
            '<div class="hint">آدرس را کامل و بدون فاصله وارد کنید. مسئولیت درستی آن با شماست.</div></div>';
  }
  if (it.ask === 'text'){
    html += '<div class="field"><label>✍️ توضیح سفارش</label>' +
            '<textarea id="fTxt" placeholder="هرچه لازم است بنویسید…"></textarea></div>';
  }
  f.innerHTML = html;

  if (it.ask === 'qty'){
    var qi = $('fQty');
    [].forEach.call(f.querySelectorAll('[data-d]'), function(b){
      b.onclick = function(){ setQty(S.qty + Number(b.getAttribute('data-d'))); tap(); };
    });
    [].forEach.call(f.querySelectorAll('[data-m]'), function(b){
      b.onclick = function(){ setQty(S.qty * Number(b.getAttribute('data-m'))); tap(); };
    });
    qi.oninput = function(){ setQty(parseFloat(this.value.replace(/[^\d.]/g,'')) || 0, true); };
  }
  total();

  $('scrim').classList.add('on');
  $('sheet').classList.add('on');
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
  $('sTotal').textContent = fa(t) + ' ' + B.currency;
}

function shut(){
  $('scrim').classList.remove('on');
  $('sheet').classList.remove('on');
  S.item = null;
  if (TG && TG.BackButton){ try{ TG.BackButton.hide(); }catch(e){} }
}
$('scrim').onclick = shut;
$('sNo').onclick   = function(){ tap(); shut(); };
if (TG && TG.BackButton){ try{ TG.BackButton.onClick(shut); }catch(e){} }

/* ── ثبت سفارش ── */
$('sGo').onclick = function(){
  if (S.busy || !S.item) return;
  var it = S.item, fv = '';
  var fx = $('fTxt');
  if (fx) fv = fx.value.trim();

  if (it.ask === 'qty'){
    if (!S.qty || S.qty < (it.min || 1)) { toast('حداقل تعداد ' + fa(it.min || 1) + ' است.'); return; }
    if (it.max > 0 && S.qty > it.max)    { toast('حداکثر تعداد ' + fa(it.max) + ' است.'); return; }
  }
  if ((it.ask === 'username' || it.ask === 'wallet' || it.ask === 'text') && !fv){
    toast('لطفا فیلد بالا را پر کنید.'); return;
  }

  S.busy = true;
  this.disabled = true;
  this.textContent = B.ui.sending;
  tap('medium');

  api('order', { item: it.id, qty: S.qty, field: fv }, function(j){
    S.busy = false;
    shut();
    $('wCode').textContent = j.order || '';
    $('wSub').textContent  = j.message || B.ui.done_sub;
    $('win').classList.add('on');
    buzz('success');
  }, function(j){
    S.busy = false;
    $('sGo').disabled = false;
    $('sGo').textContent = B.ui.submit;
    toast((j && j.message) ? j.message : 'ثبت سفارش انجام نشد.');
  });
};

$('wGo').onclick = function(){ if (TG) { try{ TG.close(); }catch(e){} } else location.reload(); };

drawTabs();
drawGrid();
})();
</script>
</body>
</html>
HTML;
}
