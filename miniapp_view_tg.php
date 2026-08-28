<?php
/**
 * 🌌 نمای مینی‌اپ «خدمات تلگرام» — تم شفق قطبی (Aurora)
 *
 * یک برنامه‌ی چندصفحه‌ای است، نه یک لیست: خانه، فروشگاه، سفارش‌ها، حساب کاربری.
 * پایین صفحه یک «جزیره»ی شیشه‌ای شناور نشسته که بین صفحه‌ها جابه‌جا می‌شود.
 * محصول‌ها دوتا دوتا کنار هم می‌نشینند.
 *
 * کاملا جدا از مینی‌اپ شماره مجازی — نه رنگش یکی است، نه ساختارش، نه حس حرکتش.
 *
 * قاعده‌های سرعت که نباید شکسته شوند:
 *   • backdrop-filter فقط روی چند سطح ثابت (جزیره، شیت) — هرگز روی کارت‌ها
 *   • کارت‌ها یک بار ساخته می‌شوند؛ فیلتر فقط کلاس عوض می‌کند، نه innerHTML
 *   • صفحه‌ها با display جابه‌جا می‌شوند؛ چیزی دوباره ساخته نمی‌شود
 *   • هر انیمیشن فقط transform/opacity — و با prefers-reduced-motion خاموش
 */

function maViewTg($a, $boot) {
    $th   = $a['theme'] ?? [];
    $c1   = $th['c1'] ?? '#7C4DFF';
    $c2   = $th['c2'] ?? '#00E5FF';
    $c3   = $th['c3'] ?? '#FF3D9A';
    $bg   = $th['bg'] ?? '#080512';
    $glow = !empty($th['glow']) ? '1' : '0';
    $grain= !empty($th['grain']) ? '1' : '0';
    $fx   = (string)maFxLevel($th);

    return strtr(maTplApp(maSkinAurora()), [
        '__C1__'    => $c1,
        '__C2__'    => $c2,
        '__C3__'    => $c3,
        '__BG__'    => $bg,
        '__GLOW__'  => $glow,
        '__GRAIN__' => $grain,
        '__FX__'    => $fx,
        '__TITLE__' => htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8'),
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

/**
 * اسکلت مشترک هر دو مینی‌اپ — ساختار و رفتار یکی است، ظاهر نه.
 * هر مینی‌اپ پوسته‌ی خودش را می‌دهد و کلاس‌ها یکی‌اند، پس هر بهبودِ
 * رفتاری روی هردو می‌نشیند بدون اینکه شبیه هم شوند.
 */
function maTplApp($skin) {
    return str_replace('__SKIN__', (string)$skin, maTplBody());
}

function maTplBody() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="referrer" content="no-referrer">
<title>__TITLE__</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<!-- فونت نباید رندر را نگه دارد: در ایران گوگل‌فونت اغلب کند یا نارساست و
     صفحه‌ی سفیدِ چندثانیه‌ای می‌ساخت. حالا نامتقارن می‌آید و تا رسیدنش
     همان فونت سیستم می‌نشیند. -->
<link rel="stylesheet" media="print" onload="this.media='all'"
      href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap">
__SKIN__
</head>
<body>
<div class="sky"></div>
<canvas id="stars"></canvas>
<div class="veil"></div><div class="grain"></div>

<div class="wrap">
  <div class="top">
    <div class="ava" id="ava">★</div>
    <div class="who">
      <h1 id="ttl">—</h1>
      <div class="chipbal" id="balChip"><b>+</b><span id="balTop">…</span><em id="curTop"></em></div>
    </div>
    <button class="bell" id="bell" aria-label="اعلان‌ها">🔔<span class="bdot"></span></button>
    <button class="cta" id="topCta">＋ شارژ</button>
  </div>

  <!-- ══ خانه ══ -->
  <section class="pg on" id="pgHome">
    <div class="purse">
      <div class="spark"></div>
      <div class="lbl">💠 <span id="balLbl">موجودی شما</span></div>
      <div class="val"><span id="bal">…</span><span class="cur" id="cur"></span></div>
      <div class="acts">
        <button id="hTop">➕ شارژ</button>
        <button class="g" id="hShop">🛍 فروشگاه</button>
      </div>
    </div>

    <div class="welcome">
      <div class="logo">
        <span class="halo"></span><i></i><i></i>
        <svg viewBox="0 0 100 100" fill="none" aria-hidden="true">
          <defs>
            <linearGradient id="lg" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="var(--c1)"/><stop offset="1" stop-color="var(--c2)"/>
            </linearGradient>
          </defs>
          <path d="M50 12 L61 38 L89 41 L68 60 L74 88 L50 74 L26 88 L32 60 L11 41 L39 38 Z"
                fill="url(#lg)" stroke="rgba(255,255,255,.35)" stroke-width="1.5" stroke-linejoin="round"/>
          <circle cx="50" cy="50" r="8" fill="rgba(255,255,255,.9)"/>
        </svg>
      </div>
      <h2 id="wcTtl">—</h2>
      <p class="wsub" id="sub"></p>
      <p id="hero"></p>
    </div>

    <div class="sect"><h2><s></s><span id="catsTtl">دسته‌بندی‌ها</span></h2>
      <a id="goShop">همه</a></div>
    <div class="rail" id="rail"></div>

    <div id="hotBox" style="display:none">
      <div class="sect"><h2><s></s><span id="hotTtl">پیشنهاد ویژه</span></h2>
        <a id="goShop2">همه</a></div>
      <div class="grid" id="hotGrid"></div>
    </div>

    <div id="rateBox" style="display:none">
      <div class="sect"><h2><s></s><span id="ratesTtl">نرخ لحظه‌ای</span></h2></div>
      <div class="rates" id="rateList"></div>
    </div>
  </section>

  <!-- ══ فروشگاه ══ -->
  <section class="pg" id="pgShop">
    <div class="find"><input id="q" placeholder="جستجو…"><span>🔎</span></div>
    <div class="tabs" id="tabs"></div>
    <div class="grid" id="grid">
      <div class="skel"></div><div class="skel"></div>
    </div>
  </section>

  <!-- ══ سفارش‌ها ══ -->
  <section class="pg" id="pgOrd">
    <div class="sect"><h2><s></s><span id="ordTtl">سفارش‌های اخیر</span></h2></div>
    <div id="ordList"><div class="void"><div>🧾</div>در حال خواندن…</div></div>
  </section>

  <!-- ══ حساب کاربری ══ -->
  <section class="pg" id="pgMe">
    <div class="prof">
      <div class="big" id="meAva">★</div>
      <div class="d">
        <b id="meName">—</b>
        <span id="meUser"></span>
        <code id="meId"></code>
      </div>
    </div>

    <div class="pane" id="topPane">
      <h3>💳 <span id="topTtl">شارژ کیف پول</span></h3>
      <div id="cardBox"></div>
      <div class="amt"><input id="amt" type="text" inputmode="numeric" placeholder="مبلغ به تومان"></div>
      <div class="quick" id="amtQuick"></div>
      <button class="go" id="topGo" style="margin-top:13px">ثبت درخواست شارژ</button>
      <div class="walbox" id="topNote"></div>
    </div>

    <div class="pane">
      <h3>ℹ️ راهنما</h3>
      <div class="link" id="lnkOrd"><s>🧾</s><em>سفارش‌های من</em><s>‹</s></div>
      <div class="link" id="lnkShop"><s>🛍</s><em>مشاهده فروشگاه</em><s>‹</s></div>
      <div class="link" id="lnkBot"><s>🤖</s><em>بازگشت به ربات</em><s>‹</s></div>
      <div class="walbox" id="meNote" style="margin-top:12px"></div>
    </div>
  </section>

  <!-- ══ 🔔 اعلان‌ها ══ -->
  <section class="pg" id="pgNote">
    <div class="sect"><h2><s></s><span id="noteTtl">اعلان‌ها</span></h2></div>
    <div id="noteList"><div class="void"><div>🔔</div>هنوز خبری نیست.</div></div>
  </section>

  <!-- ══ 👑 مدیریت (فقط مدیر) ══ -->
  <section class="pg adm" id="pgAdm">
    <div class="sect"><h2><s></s><span>مدیریت محصول‌ها</span></h2>
      <a id="admNew">➕ تازه</a></div>
    <div id="admList"><div class="void"><div>👑</div>در حال خواندن…</div></div>
  </section>
</div>

<nav class="dock" id="dock"></nav>

<div class="scrim" id="scrim"></div>
<div class="sheet" id="sheet">
  <div class="grip"></div>
  <div class="head">
    <div class="orb" id="sOrb">💠</div>
    <div style="flex:1;min-width:0"><h2 id="sName">—</h2><p id="sDesc"></p></div>
  </div>
  <div id="sField"></div>
  <div class="total"><span>مبلغ قابل پرداخت</span><b id="sTotal">۰</b></div>
  <button class="go" id="sWal">پرداخت از کیف پول</button>
  <button class="go alt" id="sGo">شارژ حساب</button>
  <div class="walbox" id="sWalNote"></div>
  <button class="ghost" id="sNo">بستن</button>
</div>

<div class="win" id="win">
  <div>
    <div class="ring">✓</div>
    <h2 id="wTtl">سفارش ثبت شد</h2>
    <p id="wSub"></p>
    <div class="code" id="wCode"></div>
    <button class="go" id="wNote" style="max-width:280px">🔔 مشاهده فرآیند خرید</button>
    <button class="ghost" id="wBack" style="max-width:280px;margin:9px auto 0">ادامه خرید</button>
    <button class="ghost" id="wGo" style="max-width:280px;margin:9px auto 0">بازگشت به ربات</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
(function(){
"use strict";
var B  = __BOOT__;
var FX = __FX__;
var TG = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
var $  = function(id){ return document.getElementById(id); };
document.body.classList.add('fx' + FX);
if (__GLOW__)  document.body.classList.add('glow-on');
if (__GRAIN__) document.body.classList.add('grain-on');

if (TG) {
  try { TG.ready(); TG.expand(); } catch(e){}
  try { TG.setHeaderColor && TG.setHeaderColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.setBackgroundColor && TG.setBackgroundColor(getComputedStyle(document.body).backgroundColor); } catch(e){}
  try { TG.disableVerticalSwipes && TG.disableVerticalSwipes(); } catch(e){}
}
function tap(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(k||'light'); }catch(e){} }
function buzz(k){ try{ TG && TG.HapticFeedback && TG.HapticFeedback.notificationOccurred(k); }catch(e){} }

function esc(s){
  return String(s == null ? '' : s).replace(/[&<>"']/g, function(m){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}
function fa(n){
  n = Math.round((Number(n)||0)*100)/100;
  try { return n.toLocaleString('fa-IR'); } catch(e){ return String(n); }
}
/* «۱۲٬۳۴۵٫۶» → «12345.6»
   رقم فارسی و عربی را لاتین می‌کند، جداکننده‌ی هزارگان را دور می‌ریزد،
   و ممیز — چه «.» چه «٫» فارسی — را نگه می‌دارد. بدون نگه‌داشتن ممیز،
   «۲٫۵ تون» می‌شد «۲۵ تون». */
function digits(s){
  s = String(s == null ? '' : s);
  var out = '', fa0 = 1776, ar0 = 1632, dot = false;
  for (var i=0;i<s.length;i++){
    var ch = s[i], c = s.charCodeAt(i);
    if (c >= fa0 && c <= fa0+9) out += (c - fa0);
    else if (c >= ar0 && c <= ar0+9) out += (c - ar0);
    else if (ch >= '0' && ch <= '9') out += ch;
    else if ((ch === '.' || c === 0x066B) && !dot) { out += '.'; dot = true; }
  }
  return out;
}
/* همان، ولی برای جاهایی که فقط عدد صحیح معنی دارد (مبلغ شارژ) */
function intIn(s){ return Math.floor(Number(digits(s)) || 0); }

/* ── ستاره‌ها ── */
(function stars(){
  if (FX < 1) return;
  var cv = $('stars'), cx = cv.getContext('2d'), st = [], W, H;
  function size(){ W = cv.width = innerWidth; H = cv.height = innerHeight; }
  size(); addEventListener('resize', size);
  var COUNT = FX >= 2 ? 32 : 16;
  for (var i=0;i<COUNT;i++) st.push({x:Math.random()*W,y:Math.random()*H,r:Math.random()*1.4+.3,
                                     s:Math.random()*.22+.04,o:Math.random()*.6+.2});
  var run = true, prev = 0;
  document.addEventListener('visibilitychange', function(){
    run = !document.hidden; if (run) requestAnimationFrame(loop);
  });
  function loop(ts){
    if (!run) return;
    requestAnimationFrame(loop);
    if (ts - prev < 33) return;
    prev = ts || 0;
    cx.clearRect(0,0,W,H);
    for (var i=0;i<st.length;i++){ var p=st[i];
      p.y -= p.s; if (p.y < -3){ p.y = H+3; p.x = Math.random()*W; }
      cx.globalAlpha = p.o; cx.fillStyle = '#fff';
      cx.beginPath(); cx.arc(p.x,p.y,p.r,0,6.284); cx.fill();
    }
  }
  requestAnimationFrame(loop);
})();

/* ── متن‌های ثابت ── */
var U = B.ui;
$('ttl').textContent   = B.title;
$('sub').textContent   = B.sub || '';
$('hero').textContent  = B.hero || '';
$('wcTtl').textContent = B.title;
$('cur').textContent   = B.currency;
$('curTop').textContent= B.currency;
$('balLbl').textContent= U.balance;
$('q').placeholder     = U.search;
$('sWal').textContent  = U.pay_wallet;
$('sGo').textContent   = U.topup_btn || 'شارژ حساب';
$('sNo').textContent   = U.close;
$('wTtl').textContent  = U.done;
$('wSub').textContent  = U.done_sub;
$('ava').textContent   = (B.title || '★').trim().charAt(0);
$('catsTtl').textContent = U.cats_ttl;
$('hotTtl').textContent  = U.hot;
$('ratesTtl').textContent= U.rates_ttl;
$('ordTtl').textContent  = U.orders_ttl;
$('topTtl').textContent  = U.topup;
$('topGo').textContent   = U.topup_do;
$('topCta').textContent  = U.topup_btn;
$('goShop').textContent  = U.see_all;
$('goShop2').textContent = U.see_all;
$('meNote').textContent  = B.note || '';
document.title = B.title;

/* ── وضعیت ── */
var S = { page:'home', cat:'', q:'', item:null, qty:1, vol:0, busy:false, bal:0, nodes:[], me:null, notes:0 };

/* ── سرور ── */
function api(action, extra, ok, bad){
  // آدرس سرور تنظیم نشده؟ به‌جای زدن به خود صفحه (که خطای CORS می‌دهد) صریح بگو
  if (!B.api){ bad({ message:'آدرس سرور مینی‌اپ تنظیم نشده است. با پشتیبانی تماس بگیرید.' }); return; }
  var body = Object.assign({ action:action, app:B.app,
    initData: (TG && TG.initData) ? TG.initData : '' }, extra || {});
  var ctl = null, timer = null;
  try { ctl = new AbortController(); timer = setTimeout(function(){ ctl.abort(); }, 20000); } catch(e){}
  fetch(B.api, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body), signal: ctl ? ctl.signal : undefined,
    cache:'no-store', credentials:'omit', referrerPolicy:'no-referrer'
  }).then(function(r){ return r.json().catch(function(){ return {ok:false,message:'پاسخ سرور نامعتبر بود.'}; }); })
    .then(function(j){ if (timer) clearTimeout(timer); if (j && j.ok) ok(j); else bad(j || {}); })
    .catch(function(){ if (timer) clearTimeout(timer); bad({ message:'ارتباط با سرور برقرار نشد.' }); });
}

var toastT;
function toast(m, good){
  var t = $('toast');
  t.textContent = m;
  t.classList.toggle('ok', !!good);
  t.classList.add('on');
  clearTimeout(toastT);
  toastT = setTimeout(function(){ t.classList.remove('on'); }, 3600);
  buzz(good ? 'success' : 'error');
}

function setBal(v){
  S.bal = Number(v) || 0;
  $('bal').textContent    = fa(S.bal);
  $('balTop').textContent = fa(S.bal);
  if (S.item) walletState();
}

/* ── آیکون‌ها ── */
var ICONS = {
  home:  '<svg viewBox="0 0 24 24"><path class="i-float" d="M3.4 10.6L12 3.4l8.6 7.2v9.4H3.4z"/><path d="M9.4 20v-6h5.2v6"/></svg>',
  bag:   '<svg viewBox="0 0 24 24"><path class="i-float" d="M4.4 8h15.2l-1.2 12.4H5.6z"/><path class="i-lid" d="M8.6 8V6.2a3.4 3.4 0 016.8 0V8"/></svg>',
  bill:  '<svg viewBox="0 0 24 24"><path class="i-float" d="M5.4 2.8h13.2v18.4l-2.6-1.8-2.2 1.8-2.2-1.8-2.2 1.8-2.2-1.8-1.8 1.8z"/><path class="i-draw" d="M8.6 8.4h6.8M8.6 12.4h6.8M8.6 16.2h4.2"/></svg>',
  user:  '<svg viewBox="0 0 24 24"><circle class="i-float" cx="12" cy="8" r="4.2"/><path d="M3.8 21c.6-4.6 4-7 8.2-7s7.6 2.4 8.2 7"/></svg>',
  star:  '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M12 2.6l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5 6.1 20.6l1.2-6.5L2.5 9.5l6.6-.9z"/></svg>',
  crown: '<svg viewBox="0 0 24 24"><path class="fl i-float" d="M3 8.4l4.2 3.2L12 4.6l4.8 7 4.2-3.2-1.7 9.6H4.7z"/><rect class="fl" x="4.4" y="19" width="15.2" height="2.1" rx="1"/></svg>',
  gift:  '<svg viewBox="0 0 24 24"><rect x="3.4" y="9.8" width="17.2" height="10.8" rx="2"/><path class="i-lid" d="M2.4 6.4h19.2v3.6H2.4z"/><path d="M12 6.4v14.2"/><path class="i-lid" d="M12 6.4C10.6 3 6.6 3.4 7.2 6.4M12 6.4c1.4-3.4 5.4-3 4.8 0"/></svg>',
  gem:   '<svg viewBox="0 0 24 24"><g class="i-spin"><path d="M4 9.2L12 21l8-11.8L16.6 3H7.4z"/><path d="M4 9.2h16M9.2 9.2L12 21l2.8-11.8M7.4 3l1.8 6.2M16.6 3l-1.8 6.2"/></g></svg>',
  tri:   '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M12 3.2l9 17.6H3z" opacity=".9"/></svg>',
  bolt:  '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M13.4 2.2L4.6 13.6h5.4l-.8 8.2 9-11.6h-5.4z"/></svg>',
  shield:'<svg viewBox="0 0 24 24"><path class="i-draw" d="M12 2.6l8 3v6.2c0 5-3.4 8.7-8 9.8-4.6-1.1-8-4.8-8-9.8V5.6z"/><path class="i-draw" d="M8.6 12.2l2.4 2.4 4.6-4.8"/></svg>',
  globe: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><ellipse class="i-pulse" cx="12" cy="12" rx="4" ry="9"/></svg>',
  box:   '<svg viewBox="0 0 24 24"><path class="i-float" d="M12 2.8l8.4 4.4v9.6L12 21.2 3.6 16.8V7.2z"/><path class="i-float" d="M3.6 7.2L12 11.6l8.4-4.4M12 11.6v9.6"/></svg>',
  clock: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.8"/><path class="i-tick" d="M12 12V6.6"/><path d="M12 12l3.6 2.2"/></svg>',
  inf:   '<svg viewBox="0 0 24 24"><path class="i-draw" d="M8.4 8.2a3.8 3.8 0 100 7.6c3 0 4.2-7.6 7.2-7.6a3.8 3.8 0 110 7.6c-3 0-4.2-7.6-7.2-7.6z"/></svg>',
  wallet:'<svg viewBox="0 0 24 24"><rect x="3" y="6.2" width="18" height="13" rx="2.6"/><path d="M3 10.4h18"/><circle class="fl i-pulse" cx="17" cy="14.6" r="1.5"/></svg>',
  fire:  '<svg viewBox="0 0 24 24"><path class="fl i-pulse" d="M13.2 2.4c2.6 3.4 1 5.2 2.6 6.6 1.4 1.2 3.4.4 3.4.4 1.6 4.6-1.4 12.2-7.2 12.2S3.2 15.6 5.6 11c.8 1.6 2.4 2 2.4 2C6.6 8.8 9.6 5 13.2 2.4z"/></svg>',
  tag:   '<svg viewBox="0 0 24 24"><path class="i-float" d="M11.4 2.8h9.8v9.8L11.6 22.2 1.8 12.4z"/><circle class="fl i-pulse" cx="17" cy="7" r="1.7"/></svg>',
  coin:  '<svg viewBox="0 0 24 24"><ellipse class="i-pulse" cx="12" cy="12" rx="8.6" ry="8.6"/><path d="M14.6 8.6c-.7-.8-1.7-1.2-2.8-1.2-1.7 0-2.9.9-2.9 2.2 0 3 5.9 1.3 5.9 4.3 0 1.4-1.3 2.3-3 2.3-1.2 0-2.3-.5-3-1.3M12 5.6v12.8"/></svg>',
  lock:  '<svg viewBox="0 0 24 24"><rect x="4.4" y="10.2" width="15.2" height="10.6" rx="2.4"/><path class="i-lid" d="M8 10.2V7.6a4 4 0 018 0v2.6"/><circle class="fl i-pulse" cx="12" cy="15.4" r="1.6"/></svg>',
  grid:  '<svg viewBox="0 0 24 24"><rect class="i-float" x="3.2" y="3.2" width="7.4" height="7.4" rx="2"/><rect x="13.4" y="3.2" width="7.4" height="7.4" rx="2"/><rect x="3.2" y="13.4" width="7.4" height="7.4" rx="2"/><rect class="i-pulse" x="13.4" y="13.4" width="7.4" height="7.4" rx="2"/></svg>'
};
var ICO_MAP = [
  [/star|استار|ستار/i,                'star'],
  [/prem|پریم|پرمی/i,                 'crown'],
  [/gift|گیفت|هدیه|teddy|تدی/i,       'gift'],
  [/\bton\b|تون|tonco/i,              'gem'],
  [/trx|tron|ترون|ترکس/i,             'tri'],
  [/usdt|تتر|tether/i,                'wallet'],
  [/vol|حجم|گیگ|گیگا|مگ|giga|\bgb\b/i,'box'],
  [/time|زمان|روز|ماه|month|day/i,    'clock'],
  [/unlim|نامحدود|بی.?نهایت/i,        'inf'],
  [/loc|کشور|لوکیشن|country|سرور/i,   'globe'],
  [/امن|secure|shield/i,              'shield'],
  [/fast|سریع|توربو|turbo|speed/i,    'bolt'],
  [/off|تخفیف|حراج|discount/i,        'tag'],
  [/hot|داغ|ویژه|vip|special/i,       'fire'],
  [/ارز|currency|exchange|صراف|coin|نرخ/i,  'coin'],
  [/اختصاص|dedicat|private|خصوص|قفل|lock/i, 'lock'],
  [/acc|اکانت|account|عضو|member/i,   'user'],
  [/pack|بسته|فروش|shop|buy/i,        'bag']
];
function icoFor(c){
  var key = String(c.id || '') + ' ' + String(c.name || '');
  for (var i = 0; i < ICO_MAP.length; i++) if (ICO_MAP[i][0].test(key)) return ICONS[ICO_MAP[i][1]];
  return '<span class="ico-em">' + esc(c.emoji || '💠') + '</span>';
}

/* ── جزیره‌ی پایین ── */
var PAGES = [
  { id:'home',  ico:'home',  name:U.nav_home },
  { id:'shop',  ico:'grid',  name:U.nav_shop },
  { id:'ord',   ico:'bill',  name:U.nav_orders },
  { id:'me',    ico:'user',  name:U.nav_me },
  { id:'adm',   ico:'crown', name:'مدیریت' },
  // 🔔 در جزیره‌ی پایین نمی‌نشیند — درش زنگِ بالای صفحه است. پنج تا
  //    دکمه‌ی پایین جا دارد، ششمی همه را باریک می‌کند.
  { id:'note',  ico:'',      name:'اعلان‌ها', off:true }
];
(function drawDock(){
  var h = '';
  PAGES.forEach(function(p){
    if (p.off) return;
    h += '<b data-p="' + p.id + '"><i class="ico">' + ICONS[p.ico] + '</i><span>' + esc(p.name) + '</span></b>';
  });
  $('dock').innerHTML = h;
})();
var DOCK = [].slice.call($('dock').children);

function go(page, silent){
  if (S.page === page && silent) return;
  S.page = page;
  var d = 0;
  for (var i=0;i<PAGES.length;i++){
    var on = PAGES[i].id === page;
    if (!PAGES[i].off) { DOCK[d] && DOCK[d].classList.toggle('on', on); d++; }
    $('pg' + PAGES[i].id.charAt(0).toUpperCase() + PAGES[i].id.slice(1)).classList.toggle('on', on);
  }
  window.scrollTo({ top:0, behavior: silent ? 'auto' : 'smooth' });
  if (page === 'ord')  drawOrders();
  if (page === 'note') loadNotes();
  if (page === 'adm' && !ADM.items.length) admLoad();
  backBtn();
}
$('dock').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('b') : null;
  if (!el) return;
  tap(); go(el.getAttribute('data-p'));
});

/* ── 🔔 اعلان‌ها ──
   هیچ خبری به ربات اصلی نمی‌رود؛ همه‌چیز همین‌جاست. */
function noteDot(n){
  S.notes = n | 0;
  $('bell').classList.toggle('has', S.notes > 0);
}
function agoTxt(t){
  var d = Math.max(0, Math.floor(Date.now()/1000) - (t|0));
  if (d < 60) return 'همین الان';
  if (d < 3600) return Math.floor(d/60) + ' دقیقه پیش';
  if (d < 86400) return Math.floor(d/3600) + ' ساعت پیش';
  return Math.floor(d/86400) + ' روز پیش';
}
function loadNotes(){
  api('notes', {}, function(j){
    var box = $('noteList'), list = j.list || [], fresh = j.n | 0;
    if (!list.length){ box.innerHTML = '<div class="void"><div>🔔</div>هنوز خبری نیست.</div>'; noteDot(0); return; }
    var h = '';
    list.forEach(function(n, i){
      h += '<div class="note' + (i < fresh ? ' new' : '') + '">' +
             '<div class="nh"><i>' + esc(n.e || '🔔') + '</i><b>' + esc(n.h || '') + '</b>' +
             '<time>' + esc(agoTxt(n.t)) + '</time></div>' +
             '<p>' + esc(n.b || '') + '</p>';
      if (n.c && n.c.length){
        h += '<div class="ncp">';
        n.c.forEach(function(v){ h += '<button type="button" data-cp="' + esc(v) + '">' + esc(v) + '</button>'; });
        h += '</div>';
      }
      h += '</div>';
    });
    box.innerHTML = h;
    if (fresh > 0) api('notes_seen', {}, function(){}, function(){});
    noteDot(0);
  }, function(){});
}
$('noteList').addEventListener('click', function(ev){
  var b = ev.target.closest ? ev.target.closest('[data-cp]') : null;
  if (!b) return;
  var v = b.getAttribute('data-cp');
  tap();
  if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(v);
  toast(U.copied || 'کپی شد', true);
});
$('bell').addEventListener('click', function(){ tap(); go('note'); });

/* دکمه بازگشت تلگرام: از هر صفحه‌ای به خانه، و از شیت به صفحه */
function backBtn(){
  if (!TG || !TG.BackButton) return;
  try {
    if (S.item || ADM.mode || S.page !== 'home') TG.BackButton.show();
    else TG.BackButton.hide();
  } catch(e){}
}
if (TG && TG.BackButton){
  try { TG.BackButton.onClick(function(){
    if (S.item || ADM.mode) { shut(); return; }
    if (S.page !== 'home') go('home');
  }); } catch(e){}
}

/* ── من ── */
api('me', {}, function(j){
  S.me = j;
  setBal(j.balance);
  if (S.page !== 'note') noteDot(j.notes_n || 0);
  var nm = (j.name || '').trim();
  if (nm) $('ttl').textContent = U.hi.replace('{name}', nm);
  $('meName').textContent = nm || B.title;
  $('meUser').textContent = j.uname ? '@' + j.uname : '';
  $('meId').textContent   = 'ID: ' + (j.uid || '');
  // حرف اول همیشه می‌نشیند؛ عکس فقط اگر واقعا بیاید جایش را می‌گیرد،
  // پس اگر تلگرام عکس نداد یا شبکه نگرفت، آواتار خالی نمی‌ماند.
  var ch = (nm || B.title || '★').trim().charAt(0);
  $('ava').textContent = ch; $('meAva').textContent = ch;
  if (j.photo) ['ava','meAva'].forEach(function(id){
    var im = new Image();
    im.alt = '';
    im.onload = function(){ var b = $(id); b.textContent = ''; b.appendChild(im); };
    im.src = j.photo;
  });
  if (j.admin) document.body.classList.add('is-admin');
  if (S.page === 'ord') drawOrders();
}, function(j){
  setBal(0);
  if (j && j.message) toast(j.message);
});

/* ── میان‌بر دسته‌ها ── */
(function drawRail(){
  var h = '';
  B.cats.forEach(function(c){
    h += '<div class="rc" data-c="' + esc(c.id) + '"><i class="ico">' + icoFor(c) + '</i>' +
         '<span>' + esc(c.name) + '</span></div>';
  });
  $('rail').innerHTML = h;
})();
$('rail').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('.rc') : null;
  if (!el) return;
  tap(); S.cat = el.getAttribute('data-c'); S.q = ''; $('q').value = '';
  drawTabs(); applyFilter(); go('shop');
});

/* ── چیپ دسته‌ها (فروشگاه) ── */
function drawTabs(){
  var h = '<b class="' + (S.cat===''?'on':'') + '" data-c="">' + esc(U.all) + '</b>';
  B.cats.forEach(function(c){
    h += '<b class="' + (S.cat===c.id?'on':'') + '" data-c="' + esc(c.id) + '">' +
         esc(c.emoji ? c.emoji + ' ' : '') + esc(c.name) + '</b>';
  });
  $('tabs').innerHTML = h;
  var rc = $('rail').children;
  for (var i=0;i<rc.length;i++) rc[i].classList.toggle('on', rc[i].getAttribute('data-c') === S.cat);
}
$('tabs').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('b') : null;
  if (!el) return;
  S.cat = el.getAttribute('data-c');
  tap(); drawTabs(); applyFilter();
});

/* ── کارت محصول ── */
function tileHtml(i, n){
  return '<div class="tile' + (i.badge ? ' hot hasbadge' : '') + (i.stale ? ' off' : '') +
           '" data-i="' + esc(i.id) + '" style="animation-delay:' + Math.min(n*35, 300) + 'ms">' +
           (i.badge ? '<span class="tag">' + esc(i.badge) + '</span>' : '') +
           (i.live  ? '<span class="livedot">زنده</span>' : '') +
           '<div class="orb">' + esc(i.emoji || '💠') + '</div>' +
           '<h3>' + esc(i.name) + '</h3>' +
           (i.desc ? '<p>' + esc(i.desc) + '</p>' : '') +
           '<div class="foot"><div class="cost"><b>' +
             (i.stale ? '—'
               : fa(i.price)) + '</b><i>' + esc(B.currency) +
             (i.unit && ['qty','qty_wallet','qty_username'].indexOf(i.ask) >= 0 ? ' / ' + esc(i.unit) : '') + '</i></div>' +
             '<div class="plus">+</div></div>' +
         '</div>';
}

function buildGrid(){
  var box = $('grid');
  if (!B.items.length){
    box.style.display = 'block';
    box.innerHTML = '<div class="void"><div>🌙</div>' + esc(U.empty) + '</div>';
    return;
  }
  var h = '';
  for (var n = 0; n < B.items.length; n++) h += tileHtml(B.items[n], n);
  box.classList.add('first');
  box.innerHTML = h;
  S.nodes = [].slice.call(box.children);
  setTimeout(function(){ box.classList.remove('first'); }, 640);
}

/* پیشنهاد ویژه: نشان‌دار‌ها، و اگر نبود، شش تای اول */
function buildHot(){
  var pick = [];
  for (var i=0;i<B.items.length && pick.length<6;i++) if (B.items[i].badge) pick.push(B.items[i]);
  if (pick.length < 2) pick = B.items.slice(0, 4);
  if (!pick.length) return;
  var h = '';
  for (var k=0;k<pick.length;k++) h += tileHtml(pick[k], k);
  $('hotGrid').innerHTML = h;
  $('hotBox').style.display = '';
}

function buildRates(){
  var live = B.items.filter(function(i){ return i.live; }).slice(0, 5);
  if (!live.length) return;
  var h = '';
  live.forEach(function(i){
    h += '<div class="rate" data-i="' + esc(i.id) + '">' +
           '<span class="e">' + esc(i.emoji || '💠') + '</span>' +
           '<span class="n">' + esc(i.name) +
             (i.unit ? '<em>هر ' + esc(i.unit) + '</em>' : '') + '</span>' +
           (i.stale
             ? '<span class="p down">موقتا بسته</span>'
             : '<span class="p">' + fa(i.price) + '</span>') +
         '</div>';
  });
  $('rateList').innerHTML = h;
  $('rateBox').style.display = '';
}

function openFrom(ev){
  var el = ev.target.closest ? ev.target.closest('[data-i]') : null;
  if (el) open(el.getAttribute('data-i'));
}
$('grid').addEventListener('click', openFrom);
$('hotGrid').addEventListener('click', openFrom);
$('rateList').addEventListener('click', openFrom);

function applyFilter(){
  var q = S.q.trim().toLowerCase(), shown = 0;
  for (var n = 0; n < S.nodes.length; n++){
    var el = S.nodes[n], it = B.items[n];
    var inCat = q ? true : (!S.cat || it.cat === S.cat);
    var ok = inCat && (!q || (it.name + ' ' + it.desc + ' ' + it.badge).toLowerCase().indexOf(q) >= 0);
    el.classList.toggle('hide', !ok);
    if (ok) shown++;
  }
  var none = $('voidBox');
  if (!shown && !none){
    none = document.createElement('div');
    none.id = 'voidBox'; none.className = 'void';
    none.style.gridColumn = '1 / -1';
    none.innerHTML = '<div>🌙</div>' + esc(U.empty);
    $('grid').appendChild(none);
  } else if (shown && none){
    none.remove();
  }
}
var qT;
$('q').addEventListener('input', function(){
  var v = this.value;
  clearTimeout(qT);
  qT = setTimeout(function(){ S.q = v; applyFilter(); }, 120);
});

/* ── سفارش‌ها ── */
function drawOrders(){
  var box = $('ordList');
  if (!S.me){ box.innerHTML = '<div class="void"><div>🧾</div>در حال خواندن…</div>'; return; }
  var os = S.me.orders || [];
  if (!os.length){ box.innerHTML = '<div class="void"><div>🧾</div>' + esc(U.no_orders) + '</div>'; return; }
  var h = '';
  os.forEach(function(o){
    h += '<div class="ord">' +
           '<span class="e">' + esc(o.emoji || '💠') + '</span>' +
           '<span class="m"><b>' + esc(o.name) + '</b><span>' + esc(o.date || '') + '</span></span>' +
           '<span class="s"><u>' + fa(o.total) + '</u><i>' + esc(o.status) + '</i></span>' +
         '</div>';
  });
  box.innerHTML = h;
}

/* «6037997512345678» → «6037 9975 1234 5678» — فقط برای خواندن؛
   چیزی که کپی می‌شود همان مقدار اصلیِ تنظیم‌شده است. */
function prettyCard(v){
  var d = String(v || '').replace(/\D/g, '');
  if (d.length !== 16) return String(v || '');
  return d.replace(/(\d{4})(?=\d)/g, '$1 ');
}

/* ── شارژ کیف پول ── */
(function topup(){
  var t = B.topup || {};
  if (!t.on && !t.gw){
    $('topPane').style.display = 'none';
    return;
  }
  if (t.card){
    $('cardBox').innerHTML =
      '<div class="card-no"><b id="cardNo">' + esc(prettyCard(t.card)) + '</b>' +
        '<button id="cardCp">' + esc(U.copy) + '</button></div>' +
      (t.name ? '<div class="card-holder">به نام: <b>' + esc(t.name) + '</b></div>' : '');
    $('cardCp').onclick = function(){
      var v = String(t.card);
      var done = function(){ toast(U.copied, true); tap('medium'); };
      try {
        if (navigator.clipboard && navigator.clipboard.writeText){
          navigator.clipboard.writeText(v).then(done, fallback);
        } else fallback();
      } catch(e){ fallback(); }
      function fallback(){
        try {
          var ta = document.createElement('textarea');
          ta.value = v; ta.style.position = 'fixed'; ta.style.opacity = '0';
          document.body.appendChild(ta); ta.select();
          document.execCommand('copy'); ta.remove(); done();
        } catch(e2){ toast('شماره کارت را دستی یادداشت کنید.'); }
      }
    };
  }
  var min = Number(t.min) || 10000;
  var picks = [min, min*5, min*10, min*20];
  $('amtQuick').innerHTML = picks.map(function(p){
    return '<i data-a="' + p + '">' + fa(p) + '</i>';
  }).join('');
  $('amtQuick').addEventListener('click', function(ev){
    var el = ev.target.closest ? ev.target.closest('i[data-a]') : null;
    if (!el) return;
    $('amt').value = fa(Number(el.getAttribute('data-a')));
    tap();
  });
  $('topNote').innerHTML = 'مبلغ را به کارت بالا واریز کنید، بعد دکمه را بزنید — ' +
    'فاکتور و دکمه «ارسال رسید» داخل ربات برایتان می‌آید.<br>حداقل مبلغ: <b>' + fa(min) + '</b> ' + esc(B.currency);

  var busy = false;
  $('topGo').onclick = function(){
    if (busy) return;
    var amt = intIn($('amt').value);
    if (!amt || amt < min){ toast('حداقل مبلغ ' + fa(min) + ' ' + B.currency + ' است.'); return; }
    busy = true;
    var btn = this, old = btn.textContent;
    btn.disabled = true; btn.textContent = U.sending;
    tap('medium');
    api('topup', { amount: amt }, function(j){
      busy = false; btn.disabled = false; btn.textContent = old;
      $('amt').value = '';
      $('wTtl').textContent  = 'درخواست شارژ ثبت شد';
      $('wSub').textContent  = j.message || '';
      $('wCode').textContent = j.order || '';
      $('win').classList.add('on');
      buzz('success');
    }, function(j){
      busy = false; btn.disabled = false; btn.textContent = old;
      toast((j && j.message) ? j.message : 'ثبت درخواست شارژ انجام نشد.');
    });
  };
})();

/* ── میان‌برها ── */
$('hTop').onclick   = function(){ tap(); go('me'); };
$('topCta').onclick = function(){ tap(); go('me'); };
$('hShop').onclick  = function(){ tap(); go('shop'); };
$('goShop').onclick = function(){ tap(); S.cat=''; drawTabs(); applyFilter(); go('shop'); };
$('goShop2').onclick= $('goShop').onclick;
$('balChip').onclick= function(){ tap(); go('me'); };
$('lnkOrd').onclick = function(){ tap(); go('ord'); };
$('lnkShop').onclick= function(){ tap(); go('shop'); };
$('lnkBot').onclick = function(){ if (TG) { try{ TG.close(); }catch(e){} } };

/* 👤 آیدیِ خودِ کاربر برای پر کردنِ کادرِ گیرنده.
   یوزرنیم اگر باشد بهترین است (پنل‌ها با @ کار می‌کنند)؛ نبود، شناسه‌ی
   عددی. هیچ‌کدام نبود، دکمه اصلا ساخته نمی‌شود. */
function selfId(){
  var m = S.me || {};
  if (m.uname) return '@' + String(m.uname).replace(/^@/, '');
  return m.uid ? String(m.uid) : '';
}
function selfChip(){
  var v = selfId();
  if (!v) return '';
  return '<div class="selfrow"><button type="button" class="self">👤 برای خودم</button>' +
         '<em>' + esc(v) + '</em></div>';
}

/* ── شیت خرید ── */
function open(id){
  var it = null;
  for (var i=0;i<B.items.length;i++) if (B.items[i].id === id) it = B.items[i];
  if (!it) return;
  tap('medium');

  S.item = it;
  S.qty  = (it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username')
             ? Math.max(1, it.min || 1) : 1;

  $('sOrb').textContent  = it.emoji || '💠';
  $('sName').textContent = it.name;
  $('sDesc').textContent = it.desc || '';
  $('sGo').disabled  = false; $('sGo').textContent  = U.topup_btn || 'شارژ حساب';
  $('sWal').disabled = false; $('sWal').textContent = U.pay_wallet;

  var f = $('sField'), html = '';
  if (it.stale){
    html += '<div class="field"><div class="hint">⏸ نرخ لحظه‌ای این سرویس الان در دسترس نیست، ' +
            'برای همین فروشش موقتا بسته است. چند دقیقه دیگر دوباره سر بزنید.</div></div>';
  }
  var hasQty = it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username';
  if (hasQty){
    var isCoin = it.ask === 'qty_wallet';
    // بسته‌های آماده با تیک، بعد کادر مقدار دلخواه — به‌جای دکمه‌های ±
    // که برای «۵۰۰ استارز» یعنی چهارصد و پنجاه بار زدن.
    html += '<div class="lbl">' + esc(U.plans) + '</div><div class="plans" id="fPlans">' +
              planRows(it).map(function(q){
                return '<i data-q="' + q + '"' + (q === S.qty ? ' class="on"' : '') + '>' +
                       '<s class="pg">' + esc(it.emoji || '💠') + '</s>' +
                       '<b>' + fa(q) + (it.unit ? ' ' + esc(it.unit) : '') + '</b>' +
                       '<u>' + fa(Math.round(it.price * q)) + ' ' + esc(B.currency) + '</u>' +
                       '<em class="chk">✓</em></i>';
              }).join('') +
            '</div>' +
            '<div class="lbl">' + esc(U.custom.replace('{min}', fa(it.min || 1))) + '</div>' +
            '<div class="field">' +
              '<input id="fQty" type="text" inputmode="' + (isCoin ? 'decimal' : 'numeric') +
                '" value="' + S.qty + '">' +
              '<div class="hint">حداقل ' + fa(it.min || 1) +
                (it.max > 0 ? ' · حداکثر ' + fa(it.max) : '') +
                (isCoin ? ' · قیمت هر ' + esc(it.unit || 'واحد') + ': ' + fa(it.price) + ' ' + esc(B.currency) : '') +
              '</div></div>';
  }
  // تعداد دلخواه استارز: هم تعداد می‌خواهد هم آیدی گیرنده
  if (it.ask === 'qty_username'){
    html += '<div class="field"><label>📎 آیدی تلگرام گیرنده</label>' +
            '<input id="fTxt" type="text" placeholder="@username" dir="ltr" style="text-align:left" ' +
            'autocomplete="off" spellcheck="false" maxlength="64">' +
            '<div class="hint">آیدی عمومی حساب — بدون آن سفارش قابل انجام نیست.</div>' +
            selfChip() + '</div>';
  }
  // ارز: هم مقدار می‌خواهد هم آدرس ولت مقصد
  if (it.ask === 'qty_wallet'){
    html += '<div class="field"><label>💼 آدرس ولت مقصد</label>' +
            '<input id="fTxt" type="text" placeholder="UQ… / T…" dir="ltr" style="text-align:left" ' +
            'autocomplete="off" spellcheck="false" maxlength="128">' +
            '<div class="hint">آدرس را کامل و بدون فاصله وارد کنید. ارز به همین آدرس فرستاده می‌شود ' +
            'و بعد از ارسال برگشت‌پذیر نیست.</div></div>';
  }
  if (it.ask === 'username'){
    html += '<div class="field"><label>📎 آیدی تلگرام گیرنده</label>' +
            '<input id="fTxt" type="text" placeholder="@username" dir="ltr" style="text-align:left" ' +
            'autocomplete="off" spellcheck="false" maxlength="64">' +
            '<div class="hint">آیدی عمومی حساب — بدون آن سفارش قابل انجام نیست.</div>' +
            selfChip() + '</div>';
  }
  if (it.ask === 'wallet'){
    html += '<div class="field"><label>💼 آدرس ولت</label>' +
            '<input id="fTxt" type="text" placeholder="UQ… / T…" dir="ltr" style="text-align:left" ' +
            'autocomplete="off" spellcheck="false" maxlength="128">' +
            '<div class="hint">آدرس را کامل و بدون فاصله وارد کنید. مسئولیت درستی آن با شماست.</div></div>';
  }
  if (it.ask === 'text'){
    html += '<div class="field"><label>✍️ توضیح سفارش</label>' +
            '<textarea id="fTxt" maxlength="300" placeholder="هرچه لازم است بنویسید…"></textarea></div>';
  }
  f.innerHTML = html;

  // 👤 «برای خودم» — آیدیِ خودِ کاربر را در کادر می‌گذارد
  var selfBtn = f.querySelector('.self');
  if (selfBtn) selfBtn.onclick = function(){
    var v = selfId();
    if (!v) return;
    var box = $('fTxt');
    if (box){ box.value = v; box.dispatchEvent(new Event('input')); }
    this.classList.add('done');
    this.textContent = '✓ برای خودم';
    // آیدی حالا توی خودِ کادر است؛ تکرارش کنارِ دکمه فقط شلوغی است
    var em = this.parentNode.querySelector('em');
    if (em) em.remove();
    tap();
  };

  if (hasQty){
    f.addEventListener('click', function(ev){
      var el = ev.target.closest ? ev.target.closest('i[data-q]') : null;
      if (!el) return;
      setQty(Number(el.getAttribute('data-q')));
      tap();
    });
    $('fQty').addEventListener('input', function(){
      var raw = digits(this.value) || this.value.replace(/[^\d.]/g,'');
      setQty(parseFloat(raw) || 0, true);
    });
  }
  total();

  if (it.stale){ $('sGo').disabled = true; $('sWal').disabled = true; }

  $('scrim').classList.add('on');
  $('sheet').classList.add('on');
  backBtn();
}

function setQty(v, typing){
  var it = S.item; if (!it) return;
  // ارز اعشار می‌پذیرد (۲٫۵ تون)، بقیه فقط عدد صحیح
  v = Number(v) || 0;
  v = it.ask === 'qty_wallet' ? Math.round(v * 10000) / 10000 : Math.floor(v);
  if (!isFinite(v) || v < 0) v = 0;
  if (!typing){
    if (v < (it.min || 1)) v = it.min || 1;
    if (it.max > 0 && v > it.max) v = it.max;
  }
  S.qty = v;
  var qi = $('fQty');
  if (qi && !typing) qi.value = v;
  markPlan();
  total();
}

/* بسته‌های پیشنهادی یک محصول — از حداقلش ساخته می‌شوند */
function planRows(it){
  var min = Math.max(1, Number(it.min) || 1);
  var out = [], mults = [1, 2, 5, 10];
  for (var i = 0; i < mults.length; i++){
    var q = min * mults[i];
    if (it.max > 0 && q > it.max) break;
    out.push(q);
  }
  return out;
}

/* تیک روی همان بسته‌ای که مقدارش انتخاب شده */
function markPlan(){
  var box = $('fPlans');
  if (!box) return;
  var all = box.querySelectorAll('i[data-q]');
  for (var i = 0; i < all.length; i++)
    all[i].classList.toggle('on', Number(all[i].getAttribute('data-q')) === S.qty);
}

function sum(){
  var it = S.item; if (!it) return 0;
  if (it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username')
    return Math.round(it.price * Math.max(0, S.qty));
  return it.price;
}
function total(){
  $('sTotal').textContent = fa(sum()) + ' ' + B.currency;
  walletState();
}
function walletState(){
  var it = S.item; if (!it) return;
  var t = sum(), enough = S.bal >= t;
  $('sWal').disabled = !enough || !!it.stale;
  // دکمه‌ی شارژ فقط وقتی به‌دردی می‌خورد که موجودی کم باشد
  $('sGo').style.display = enough ? 'none' : '';
  $('sWalNote').innerHTML = enough
    ? '👛 موجودی شما: <b>' + fa(S.bal) + '</b> ' + esc(B.currency) +
      ' · بعد از پرداخت: <b>' + fa(S.bal - t) + '</b>'
    : '⚠️ ' + esc(U.low_bal) + ' — موجودی: <b>' + fa(S.bal) + '</b> ' + esc(B.currency) +
      ' · کسری: <b>' + fa(t - S.bal) + '</b><br>' + esc(U.topup_hint);
}

function shut(){
  $('scrim').classList.remove('on');
  $('sheet').classList.remove('on');
  S.item = null;
  ADM.mode = false;
  backBtn();
}
$('scrim').onclick = shut;
$('sNo').onclick   = function(){ tap(); shut(); };

function validate(){
  var it = S.item, fv = '';
  var fx = $('fTxt');
  if (fx) fv = fx.value.trim();
  if (it.ask === 'qty' || it.ask === 'qty_wallet' || it.ask === 'qty_username'){
    if (!S.qty || S.qty < (it.min || 1)) { toast('حداقل مقدار ' + fa(it.min || 1) + ' است.'); return null; }
    if (it.max > 0 && S.qty > it.max)    { toast('حداکثر مقدار ' + fa(it.max) + ' است.'); return null; }
  }
  if (['username','wallet','qty_wallet','qty_username','text'].indexOf(it.ask) >= 0 && !fv){
    toast('لطفا فیلد بالا را پر کنید.'); return null;
  }
  return fv;
}

function send(payMode, btn){
  if (S.busy || !S.item) return;
  var fv = validate();
  if (fv === null) return;
  var it = S.item;

  S.busy = true;
  btn.disabled = true;
  var old = btn.textContent;
  btn.textContent = U.sending;
  tap('medium');

  api('order', { item: it.id, qty: S.qty, field: fv, seen_price: it.price, pay: payMode },
    function(j){
      S.busy = false;
      btn.disabled = false; btn.textContent = old;
      if (typeof j.balance === 'number') setBal(j.balance);
      shut();
      $('wCode').textContent = j.order || '';
      $('wSub').textContent  = j.message || U.done_sub;
      $('wTtl').textContent  = j.paid ? U.paid_ok : U.done;
      $('win').classList.add('on');
      buzz('success');
      api('me', {}, function(m){ S.me = m; setBal(m.balance); }, function(){});
    },
    function(j){
      S.busy = false;
      btn.disabled = false;
      btn.textContent = old;
      if (j && j.error === 'price_changed' && j.price){
        it.price = j.price;
        total();
        var idx = B.items.indexOf(it), node = S.nodes[idx];
        if (node){
          var pb = node.querySelector('.cost b');
          if (pb) pb.textContent = fa(j.price);
        }
      }
      if (j && j.error === 'no_balance'){
        if (typeof j.balance === 'number') { S.bal = j.balance; setBal(j.balance); }
        walletState();
        toast((j && j.message) ? j.message : U.low_bal);
        go('me');                        // بخش شارژ همان‌جاست
        return;
      }
      toast((j && j.message) ? j.message : 'ثبت سفارش انجام نشد.');
    });
}
$('sWal').onclick = function(){ if (ADM.mode) { admSave(); return; } send('wallet', this); };
$('sGo').onclick  = function(){
  if (ADM.mode) { admDel(); return; }
  // خرید همیشه از موجودی انجام می‌شود؛ این دکمه فقط می‌برد به شارژ
  shut(); tap(); go('me');
};

$('wGo').onclick   = function(){ if (TG) { try{ TG.close(); }catch(e){} } else location.reload(); };
$('wBack').onclick = function(){ $('win').classList.remove('on'); tap(); go('shop'); };
/* 🔔 «مشاهده فرآیند خرید» — سفارش تازه ثبت شده و کاربر می‌خواهد
   بداند بعدش چه می‌شود. هر تکانِ سفارش (پرداخت، تحویل، برگشت وجه)
   یک اعلان است، پس همان‌جا کاملِ ماجرا را می‌بیند. */
$('wNote').onclick = function(){ $('win').classList.remove('on'); tap(); go('note'); };

/* ══ 👑 مدیریت محصول‌ها — فقط وقتی سرور بگوید این کاربر مدیر است ══
   سرور هم مستقل بررسی می‌کند؛ این کلاس فقط برای نمایش است و
   اگر کسی دستکاری‌اش کند، API با ۴۰۴ جوابش می‌دهد. */
var ADM = { items: [], cats: [], asks: {}, edit: null };

function admLoad(){
  api('adm_cats', {}, function(j){ ADM.cats = j.cats || []; }, function(){});
  api('adm_items', {}, function(j){
    ADM.items = j.items || [];
    ADM.asks  = j.asks || {};
    admDraw();
  }, function(j){
    $('admList').innerHTML = '<div class="void"><div>👑</div>' + esc((j && j.message) ? j.message : 'خوانده نشد') + '</div>';
  });
}

function admDraw(){
  var box = $('admList');
  if (!ADM.items.length){ box.innerHTML = '<div class="void"><div>👑</div>هنوز محصولی نیست.</div>'; return; }
  var h = '';
  ADM.items.forEach(function(i){
    h += '<div class="arow' + (i.on ? '' : ' off') + '" data-id="' + esc(i.id) + '">' +
           '<span class="e">' + esc(i.emoji || '💠') + '</span>' +
           '<span class="m"><b>' + esc(i.name) + '</b><span>' +
             esc(i.cat || 'بدون دسته') + ' · ' + esc(ADM.asks[i.ask] || i.ask) +
             (i.live ? ' · نرخ زنده' : '') + '</span></span>' +
           '<span class="p">' + fa(i.final) + '</span>' +
         '</div>';
  });
  box.innerHTML = h;
}

$('admList').addEventListener('click', function(ev){
  var el = ev.target.closest ? ev.target.closest('.arow') : null;
  if (!el) return;
  var id = el.getAttribute('data-id');
  for (var i = 0; i < ADM.items.length; i++)
    if (ADM.items[i].id === id) { admOpen(ADM.items[i]); return; }
});

$('admNew').onclick = function(){
  tap();
  admOpen({ id:'', name:'', emoji:'💠', desc:'', badge:'', price:0, unit:'',
            cat:(ADM.cats[0] ? ADM.cats[0].id : ''), ask:'none', min:1, max:1, order:99, on:1 });
};

/* فرم ویرایش، داخل همان شیت خرید */
function admOpen(it){
  ADM.edit = JSON.parse(JSON.stringify(it));
  var e = ADM.edit;

  $('sOrb').textContent  = e.emoji || '💠';
  $('sName').textContent = e.id ? 'ویرایش محصول' : 'محصول تازه';
  $('sDesc').textContent = e.id ? e.id : 'شناسه خودکار ساخته می‌شود';

  var opts = '';
  ADM.cats.forEach(function(c){
    opts += '<option value="' + esc(c.id) + '"' + (c.id === e.cat ? ' selected' : '') + '>' +
            esc(c.name) + '</option>';
  });
  var asks = '';
  Object.keys(ADM.asks).forEach(function(k){
    asks += '<option value="' + esc(k) + '"' + (k === e.ask ? ' selected' : '') + '>' +
            esc(ADM.asks[k]) + '</option>';
  });

  $('sField').innerHTML =
    '<div class="aform">' +
      '<div class="field"><label>نام</label><input id="aName" maxlength="80" value="' + esc(e.name) + '"></div>' +
      '<div class="a2">' +
        '<div class="field"><label>ایموجی</label><input id="aEmoji" maxlength="8" value="' + esc(e.emoji) + '"></div>' +
        '<div class="field"><label>برچسب</label><input id="aBadge" maxlength="20" value="' + esc(e.badge) + '"></div>' +
      '</div>' +
      '<div class="field"><label>توضیح</label><textarea id="aDesc" maxlength="300">' + esc(e.desc) + '</textarea></div>' +
      '<div class="a2">' +
        '<div class="field"><label>قیمت (پایه)</label><input id="aPrice" inputmode="numeric" value="' + e.price + '"></div>' +
        '<div class="field"><label>واحد</label><input id="aUnit" maxlength="20" value="' + esc(e.unit) + '"></div>' +
      '</div>' +
      '<div class="field"><label>دسته</label><select id="aCat">' + opts + '</select></div>' +
      '<div class="field"><label>از کاربر چه بپرسد</label><select id="aAsk">' + asks + '</select></div>' +
      '<div class="a2">' +
        '<div class="field"><label>حداقل</label><input id="aMin" inputmode="numeric" value="' + e.min + '"></div>' +
        '<div class="field"><label>حداکثر (۰ = بی‌نهایت)</label><input id="aMax" inputmode="numeric" value="' + e.max + '"></div>' +
      '</div>' +
      '<div class="field"><label>ترتیب</label><input id="aOrder" inputmode="numeric" value="' + e.order + '"></div>' +
      '<div class="aswitch' + (e.on ? ' on' : '') + '" id="aOn"><span>نمایش در فروشگاه</span><i></i></div>' +
    '</div>';

  $('aOn').onclick = function(){ this.classList.toggle('on'); tap(); };

  $('sTotal').textContent = e.id ? 'ذخیره تغییرات' : 'افزودن محصول';
  $('sWal').textContent = '💾 ذخیره';
  $('sWal').disabled = false;
  $('sGo').textContent = e.id ? '🗑 حذف محصول' : 'انصراف';
  $('sGo').disabled = false;
  $('sWalNote').innerHTML = 'قیمت پایه است؛ سود و نرخ زنده روی آن اعمال می‌شود.';

  ADM.mode = true;
  $('scrim').classList.add('on');
  $('sheet').classList.add('on');
  backBtn();
}

function admSave(){
  var g = function(id){ var el = $(id); return el ? el.value : ''; };
  var it = {
    id: ADM.edit.id,
    name: g('aName'), emoji: g('aEmoji'), desc: g('aDesc'), badge: g('aBadge'),
    unit: g('aUnit'), cat: g('aCat'), ask: g('aAsk'),
    price: Number(digits(g('aPrice'))) || 0,
    min:   Number(digits(g('aMin')))   || 0,
    max:   Number(digits(g('aMax')))   || 0,
    order: Number(digits(g('aOrder'))) || 99,
    on: $('aOn').classList.contains('on') ? 1 : 0
  };
  if (!it.name.trim()){ toast('نام محصول را بنویسید.'); return; }

  $('sWal').disabled = true;
  api('adm_item_save', { item: it }, function(){
    $('sWal').disabled = false;
    shut();
    toast(it.id ? 'ذخیره شد ✓' : 'محصول اضافه شد ✓', true);
    admLoad();
  }, function(j){
    $('sWal').disabled = false;
    toast((j && j.message) ? j.message : 'ذخیره نشد.');
  });
}

function admDel(){
  if (!ADM.edit || !ADM.edit.id) { shut(); return; }
  api('adm_item_del', { id: ADM.edit.id }, function(){
    shut(); toast('حذف شد ✓', true); admLoad();
  }, function(j){ toast((j && j.message) ? j.message : 'حذف نشد.'); });
}

drawTabs();
buildGrid();
applyFilter();
buildHot();
buildRates();
go('home', true);
})();
</script>
</body>
</html>
HTML;
}

/** نام قدیمی، برای سازگاری */
function maTplTg() { return maTplApp(maSkinAurora()); }

/**
 * 🌌 پوسته‌ی شفق — مینی‌اپ خدمات تلگرام
 * گوشه‌های گرد، شیشه‌ی بنفش/فیروزه‌ای، جزیره‌ی شناور پایین.
 */
function maSkinAurora() {
    return <<<'CSS'
<style>
:root{
  --c1:__C1__; --c2:__C2__; --c3:__C3__; --bg:__BG__;
  --ink:#F5F2FF; --dim:#A79FC6; --line:rgba(255,255,255,.10);
  --pane:#150F2E; --pane2:#1B1440;
  --r:24px; --safe:env(safe-area-inset-bottom,0px);
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;min-height:100%}
body{
  background:var(--bg); color:var(--ink);
  font-family:Vazirmatn,"Vazir","IRANSans","IRANYekan",system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
  overflow-x:hidden; -webkit-font-smoothing:antialiased;
}
img{max-width:100%}

/* ═══ آسمان شفق ═══ گرادیان ثابت، بدون filter — هزینه‌ی اسکرول صفر */
.sky{position:fixed;inset:0;z-index:0;pointer-events:none;
  background:
    radial-gradient(60vw 60vw at 88% -8%,color-mix(in srgb,var(--c1) 55%,transparent),transparent 68%),
    radial-gradient(54vw 54vw at 4% 30%,color-mix(in srgb,var(--c2) 38%,transparent),transparent 66%),
    radial-gradient(48vw 48vw at 80% 106%,color-mix(in srgb,var(--c3) 34%,transparent),transparent 64%)}
.sky:after{content:"";position:absolute;inset:0;opacity:0;
  background:radial-gradient(50vw 50vw at 22% 10%,color-mix(in srgb,var(--c2) 26%,transparent),transparent 62%)}
body.fx2 .sky:after{animation:breathe 9s ease-in-out infinite}
@keyframes breathe{0%,100%{opacity:0}50%{opacity:.85}}
#stars{position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.72}
.veil{position:fixed;inset:0;z-index:2;pointer-events:none;
  background:radial-gradient(122% 78% at 50% 0%,transparent 18%,var(--bg) 92%)}
.grain{position:fixed;inset:0;z-index:3;pointer-events:none;opacity:.05;display:none;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3'/></filter><rect width='140' height='140' filter='url(%23n)' opacity='.55'/></svg>")}
body.grain-on .grain{display:block}
body.fx0 #stars{display:none}
@media (prefers-reduced-motion:reduce){ .sky:after{animation:none!important} #stars{display:none} }

.wrap{position:relative;z-index:5;max-width:600px;margin:0 auto;padding:0 15px calc(112px + var(--safe))}

/* ═══ سربرگ ═══ نوار بالا مثل اپ‌های گیفت: چهره، سلام، موجودی، و یک دکمه‌ی کار */
.top{display:flex;align-items:center;gap:11px;margin:14px 0 4px;padding:11px 12px;border-radius:22px;
  border:1px solid var(--line);background:var(--pane)}
.ava{width:46px;height:46px;border-radius:50%;flex:0 0 auto;display:grid;place-items:center;overflow:hidden;
  font-weight:900;font-size:18px;color:#0B0616;
  background:linear-gradient(135deg,var(--c1),var(--c2));
  box-shadow:0 0 0 2px var(--pane),0 0 0 4px color-mix(in srgb,var(--c2) 55%,transparent)}
.ava img{width:100%;height:100%;object-fit:cover}
.who{flex:1;min-width:0}
.who h1{margin:0;font-size:14.5px;font-weight:800;letter-spacing:-.2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chipbal{display:inline-flex;align-items:center;gap:6px;margin-top:6px;padding:5px 6px 5px 10px;border-radius:12px;
  border:1px solid var(--line);background:rgba(255,255,255,.05);font-size:12.5px;font-weight:900;cursor:pointer}
.chipbal em{font-style:normal;font-size:9.5px;color:var(--dim);font-weight:600}
.chipbal b{width:20px;height:20px;border-radius:7px;display:grid;place-items:center;font-size:14px;line-height:1;
  color:#0B0616;background:linear-gradient(135deg,var(--c2),var(--c1))}
.cta{flex:0 0 auto;padding:11px 14px;border:0;border-radius:15px;cursor:pointer;
  font-family:inherit;font-size:12px;font-weight:800;color:#0B0616;
  background:linear-gradient(135deg,var(--c1),var(--c2))}
.cta:active{transform:scale(.96)}
body.glow-on .cta{box-shadow:0 8px 22px -12px var(--c1)}

/* 🔔 زنگ — با همان نقطه‌ای که هر برنامه‌ای دارد */
.bell{position:relative;flex:0 0 auto;width:38px;height:38px;border-radius:13px;cursor:pointer;
  border:1px solid var(--line,rgba(255,255,255,.1));background:rgba(255,255,255,.06);
  color:inherit;display:grid;place-items:center;font-size:16px;font-family:inherit}
.bell:active{transform:scale(.94)}
.bell .bdot{position:absolute;top:6px;inset-inline-end:6px;width:8px;height:8px;border-radius:99px;
  background:#FF5A6E;opacity:0;transform:scale(.4);transition:opacity .2s,transform .2s}
.bell.has .bdot{opacity:1;transform:scale(1);animation:bping 1.8s ease-out infinite}
@keyframes bping{0%,60%,100%{box-shadow:0 0 0 0 rgba(255,90,110,.55)}30%{box-shadow:0 0 0 6px rgba(255,90,110,0)}}
@media (prefers-reduced-motion:reduce){.bell.has .bdot{animation:none}}

/* 🔔 کارت‌های اعلان */
.note{border:1px solid var(--line,rgba(255,255,255,.1));background:rgba(255,255,255,.04);
  border-radius:16px;padding:13px 14px;margin-bottom:10px;position:relative;overflow:hidden}
.note.new::before{content:'';position:absolute;inset-inline-start:0;top:0;bottom:0;width:3px;
  background:linear-gradient(180deg,var(--c1),var(--c2))}
.note .nh{display:flex;align-items:center;gap:8px;margin-bottom:5px}
.note .nh i{font-style:normal;font-size:16px;line-height:1}
.note .nh b{font-size:13px;font-weight:800;flex:1;min-width:0}
.note .nh time{font-size:10px;color:var(--dim);white-space:nowrap}
.note p{font-size:12px;color:var(--dim);line-height:1.8;white-space:pre-line;margin:0}
.note .ncp{display:flex;flex-wrap:wrap;gap:6px;margin-top:9px}
.note .ncp button{border:1px solid var(--line,rgba(255,255,255,.1));background:rgba(255,255,255,.06);
  color:inherit;border-radius:10px;padding:5px 10px;font-size:11px;font-family:inherit;cursor:pointer}
.note .ncp button:active{transform:scale(.95)}
.wsub{margin:0 0 10px;font-size:11.5px;color:var(--dim);text-align:center}

/* ═══ صفحه‌ها ═══ */
.pg{display:none;animation:pgIn .3s cubic-bezier(.2,.9,.3,1)}
.pg.on{display:block}
@keyframes pgIn{from{opacity:0;transform:translateY(9px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){ .pg{animation:none} }

.sect{display:flex;align-items:baseline;justify-content:space-between;margin:20px 2px 11px}
.sect h2{margin:0;font-size:11.5px;font-weight:800;letter-spacing:1.2px;color:var(--dim);
  display:flex;align-items:center;gap:8px}
.sect h2 s{text-decoration:none;width:5px;height:16px;border-radius:3px;
  background:linear-gradient(180deg,var(--c2),var(--c1))}
.sect a{font-size:11.5px;color:var(--c2);font-weight:700;cursor:pointer}

/* ═══ کارت کیف پول ═══ */
.purse{position:relative;overflow:hidden;padding:19px 18px;border-radius:26px;
  border:1px solid rgba(255,255,255,.14);
  background:linear-gradient(140deg,var(--pane2),var(--pane) 55%,#0E0A20)}
.purse:before{content:"";position:absolute;inset:0;opacity:.35;pointer-events:none;
  background:
    radial-gradient(70% 120% at 100% 0%,color-mix(in srgb,var(--c1) 60%,transparent),transparent 62%),
    radial-gradient(60% 100% at 0% 100%,color-mix(in srgb,var(--c3) 42%,transparent),transparent 60%)}
.purse .spark{position:absolute;inset:0;transform:translateX(-100%);pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.14),transparent)}
body.fx2 .purse .spark{animation:spark 5s ease-in-out infinite}
@keyframes spark{0%,72%{transform:translateX(-100%)}100%{transform:translateX(120%)}}
.purse .lbl{position:relative;font-size:11.5px;color:#CFC6EE;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.purse .val{position:relative;font-size:31px;font-weight:900;letter-spacing:-1px;line-height:1.1;
  background:linear-gradient(90deg,#fff,var(--c2));-webkit-background-clip:text;background-clip:text;color:transparent}
.purse .cur{font-size:13px;font-weight:600;color:#BDB3E2;margin-inline-start:5px;
  -webkit-text-fill-color:#BDB3E2}
.purse .acts{position:relative;display:flex;gap:9px;margin-top:16px}
.purse .acts button{flex:1;padding:12px;border:0;border-radius:15px;cursor:pointer;
  font-family:inherit;font-size:12.5px;font-weight:800;color:#0B0616;
  background:linear-gradient(135deg,var(--c2),var(--c1))}
.purse .acts button.g{color:var(--ink);background:rgba(255,255,255,.09);border:1px solid var(--line)}
.purse .acts button:active{transform:scale(.97)}

/* ═══ کارت خوش‌آمد ═══ */
.welcome{margin-top:16px;padding:22px 18px;border-radius:26px;text-align:center;position:relative;overflow:hidden;
  border:1px solid var(--line);
  background:linear-gradient(165deg,rgba(255,255,255,.08),rgba(255,255,255,.02))}
.welcome:before{content:"";position:absolute;inset:0;pointer-events:none;
  background:linear-gradient(118deg,rgba(255,255,255,.12) 0%,transparent 38%)}
.logo{width:82px;height:82px;margin:0 auto 13px;position:relative}
.logo svg{width:100%;height:100%;display:block;position:relative;z-index:2}
.logo i{position:absolute;inset:-6px;border-radius:50%;border:1.5px dashed color-mix(in srgb,var(--c2) 55%,transparent)}
.logo i:nth-child(2){inset:2px;border-style:solid;border-color:color-mix(in srgb,var(--c1) 40%,transparent)}
.logo .halo{position:absolute;inset:-18px;border-radius:50%;z-index:0;
  background:radial-gradient(circle,color-mix(in srgb,var(--c1) 45%,transparent),transparent 68%)}
body.fx2 .logo i{animation:spin 14s linear infinite}
body.fx2 .logo i:nth-child(2){animation:spin 9s linear infinite reverse}
body.fx2 .logo svg{animation:float 4.5s ease-in-out infinite}
body.fx2 .logo .halo{animation:glow 3.6s ease-in-out infinite}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
@keyframes glow{0%,100%{opacity:.5;transform:scale(.94)}50%{opacity:1;transform:scale(1.08)}}
@media (prefers-reduced-motion:reduce){ .logo i,.logo svg,.logo .halo{animation:none!important} }
.welcome h2{position:relative;margin:0 0 8px;font-size:18px;font-weight:900;letter-spacing:-.3px;
  background:linear-gradient(92deg,#fff,var(--c2));-webkit-background-clip:text;background-clip:text;color:transparent}
.welcome p{position:relative;margin:0;font-size:12.5px;line-height:1.95;color:#D2CBEE}

/* ═══ میان‌بر دسته‌ها ═══ */
.rail{display:flex;gap:9px;overflow-x:auto;padding:2px 2px 6px;scrollbar-width:none}
.rail::-webkit-scrollbar{display:none}
.rail .rc{flex:0 0 auto;width:82px;padding:13px 6px;border-radius:20px;text-align:center;cursor:pointer;
  border:1px solid var(--line);background:var(--pane);transition:border-color .18s,transform .18s}
.rail .rc:active{transform:scale(.95)}
.rail .rc .ico{margin:0 auto 7px}
.rail .rc span{display:block;font-size:10.5px;font-weight:800;color:var(--dim);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rail .rc.on{border-color:color-mix(in srgb,var(--c2) 60%,transparent)}
.rail .rc.on span{color:var(--ink)}

/* ═══ آیکون شیشه‌ای ═══ */
.ico{position:relative;width:38px;height:38px;border-radius:14px;display:grid;place-items:center;
  background:linear-gradient(158deg,rgba(255,255,255,.16),rgba(255,255,255,.03));
  border:1px solid rgba(255,255,255,.17);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.28),inset 0 -6px 12px rgba(0,0,0,.18),0 4px 12px rgba(0,0,0,.28);
  overflow:hidden;
  transition:transform .26s cubic-bezier(.34,1.56,.64,1),box-shadow .26s,border-color .26s}
.ico:before{content:"";position:absolute;inset:-45%;pointer-events:none;
  background:linear-gradient(115deg,transparent 41%,rgba(255,255,255,.55) 50%,transparent 59%);
  transform:translateX(-130%)}
.ico:after{content:"";position:absolute;inset:0;border-radius:inherit;pointer-events:none;
  background:radial-gradient(120% 70% at 50% -10%,rgba(255,255,255,.3),transparent 60%)}
.ico svg{position:relative;width:21px;height:21px;display:block;overflow:visible;
  fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.ico svg .fl{fill:currentColor;stroke:none}
.ico .ico-em{font-size:19px;font-style:normal;line-height:1}
.on>.ico,.rc.on .ico{border-color:rgba(255,255,255,.44);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.5),0 8px 20px rgba(0,0,0,.4)}
.on>.ico:before,.rc.on .ico:before{animation:icoSheen 2.8s cubic-bezier(.4,0,.2,1) infinite}
@keyframes icoSheen{0%{transform:translateX(-130%)}55%,100%{transform:translateX(130%)}}
.i-spin{transform-box:fill-box;transform-origin:50% 50%}
.on .i-spin {animation:icoSpin 5.5s linear infinite}
.on .i-pulse{animation:icoPulse 1.9s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.on .i-float{animation:icoFloat 2.4s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 50%}
.on .i-lid  {animation:icoLid 2.2s ease-in-out infinite;transform-box:fill-box;transform-origin:50% 100%}
.on .i-draw {stroke-dasharray:64;animation:icoDraw 2.6s ease-in-out infinite}
.on .i-tick {animation:icoTick 4s steps(12) infinite;transform-box:fill-box;transform-origin:50% 100%}
@keyframes icoSpin {to{transform:rotate(360deg)}}
@keyframes icoPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.16);opacity:.75}}
@keyframes icoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
@keyframes icoLid  {0%,72%,100%{transform:translateY(0) rotate(0)}82%{transform:translateY(-2.5px) rotate(-8deg)}}
@keyframes icoDraw {0%{stroke-dashoffset:64}45%,100%{stroke-dashoffset:0}}
@keyframes icoTick {to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){
  .ico,.ico:before,.on>.ico:before,.rc.on .ico:before,
  .on .i-spin,.on .i-pulse,.on .i-float,.on .i-lid,.on .i-draw,.on .i-tick{animation:none!important;transition:none!important}
}

/* ═══ جستجو و چیپ دسته ═══ */
.find{position:relative;margin:4px 0 12px}
.find input{width:100%;padding:13px 42px 13px 14px;border-radius:16px;border:1px solid var(--line);
  background:var(--pane);color:var(--ink);font-family:inherit;font-size:13.5px;outline:none;transition:.2s}
.find input:focus{border-color:var(--c1);box-shadow:0 0 0 3px color-mix(in srgb,var(--c1) 18%,transparent)}
.find span{position:absolute;top:50%;right:14px;transform:translateY(-50%);opacity:.5;font-size:15px}
.tabs{display:flex;gap:7px;overflow-x:auto;padding:0 0 12px;scrollbar-width:none}
.tabs::-webkit-scrollbar{display:none}
.tabs b{flex:0 0 auto;padding:9px 15px;border-radius:13px;cursor:pointer;font-size:12px;font-weight:800;
  color:var(--dim);border:1px solid var(--line);background:var(--pane);transition:.18s;white-space:nowrap}
.tabs b.on{color:#0B0616;border-color:transparent;background:linear-gradient(135deg,var(--c1),var(--c2))}

/* ═══ شبکه‌ی محصول — دوتا دوتا ═══ */
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.tile{position:relative;overflow:hidden;padding:14px 12px 12px;border-radius:22px;cursor:pointer;contain:content;
  border:1px solid var(--line);background:var(--pane);
  display:flex;flex-direction:column;min-height:172px;
  transition:border-color .18s,transform .14s;
  animation:rise .4s cubic-bezier(.2,.9,.3,1) backwards}
@keyframes rise{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
.grid:not(.first) .tile{animation:none}
@media (prefers-reduced-motion:reduce){ .tile{animation:none} }
.tile:active{transform:scale(.975)}
.tile:before{content:"";position:absolute;inset:0;opacity:0;transition:opacity .25s;pointer-events:none;
  background:linear-gradient(150deg,color-mix(in srgb,var(--c1) 26%,transparent),transparent 62%)}
.tile.hot:before{opacity:1}
.tile.hot{border-color:color-mix(in srgb,var(--c1) 45%,transparent)}
.tile.hide{display:none}
.orb{position:relative;width:52px;height:52px;border-radius:18px;display:grid;place-items:center;font-size:25px;
  margin-bottom:11px;
  background:linear-gradient(140deg,color-mix(in srgb,var(--c1) 36%,transparent),color-mix(in srgb,var(--c2) 22%,transparent));
  border:1px solid rgba(255,255,255,.14)}
body.glow-on .orb{box-shadow:0 10px 24px -13px var(--c1)}
.tile h3{position:relative;margin:0;font-size:13px;font-weight:800;line-height:1.55;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile p{position:relative;margin:4px 0 0;font-size:10.5px;color:var(--dim);line-height:1.65;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile .foot{position:relative;margin-top:auto;padding-top:11px;display:flex;align-items:flex-end;justify-content:space-between;gap:6px}
.tile .cost b{display:block;font-size:15px;font-weight:900;letter-spacing:-.4px;
  background:linear-gradient(90deg,var(--c2),var(--c1));-webkit-background-clip:text;background-clip:text;color:transparent}
.tile .cost i{display:block;font-style:normal;font-size:9px;color:var(--dim);margin-top:1px}
.tile .plus{width:29px;height:29px;flex:0 0 auto;border-radius:11px;display:grid;place-items:center;
  font-size:17px;font-weight:700;color:#0B0616;line-height:1;
  background:linear-gradient(135deg,var(--c2),var(--c1))}
.tag{position:absolute;top:10px;left:10px;z-index:2;font-size:8.5px;font-weight:900;padding:3px 7px;border-radius:8px;
  color:#0B0616;background:linear-gradient(135deg,var(--c3),var(--c1))}
.livedot{position:absolute;top:10px;left:10px;z-index:2;font-size:8px;font-weight:900;padding:3px 7px;border-radius:8px;
  color:#0B0616;background:var(--c2);letter-spacing:.3px}
.tile.hasbadge .livedot{top:31px}
.tile.off{opacity:.55}

/* ═══ نرخ لحظه‌ای ═══ */
.rates{display:grid;gap:9px}
.rate{display:flex;align-items:center;gap:11px;padding:12px 14px;border-radius:18px;
  border:1px solid var(--line);background:var(--pane)}
.rate .e{font-size:22px;flex:0 0 auto}
.rate .n{flex:1;min-width:0;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rate .n em{display:block;font-style:normal;font-size:10px;color:var(--dim);font-weight:600;margin-top:2px}
.rate .p{font-size:13.5px;font-weight:900;color:var(--c2);flex:0 0 auto}
.rate .p.down{color:#FF6A8A;font-size:11px}

/* ═══ فهرست سفارش ═══ */
.ord{display:flex;align-items:center;gap:11px;padding:13px 14px;border-radius:18px;margin-bottom:9px;
  border:1px solid var(--line);background:var(--pane)}
.ord .e{width:42px;height:42px;flex:0 0 auto;border-radius:14px;display:grid;place-items:center;font-size:21px;
  background:rgba(255,255,255,.06);border:1px solid var(--line)}
.ord .m{flex:1;min-width:0}
.ord .m b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ord .m span{display:block;font-size:10px;color:var(--dim);margin-top:3px;direction:ltr;text-align:right}
.ord .s{flex:0 0 auto;text-align:left}
.ord .s u{display:block;text-decoration:none;font-size:12px;font-weight:900;color:var(--c2)}
.ord .s i{display:block;font-style:normal;font-size:9.5px;color:var(--dim);margin-top:3px}

/* ═══ حساب کاربری ═══ */
.prof{display:flex;align-items:center;gap:14px;padding:19px 17px;border-radius:26px;position:relative;overflow:hidden;
  border:1px solid var(--line);background:linear-gradient(150deg,var(--pane2),var(--pane))}
.prof:before{content:"";position:absolute;inset:0;opacity:.3;pointer-events:none;
  background:radial-gradient(70% 120% at 100% 0%,color-mix(in srgb,var(--c2) 46%,transparent),transparent 62%)}
.prof .big{position:relative;width:64px;height:64px;border-radius:22px;flex:0 0 auto;overflow:hidden;
  display:grid;place-items:center;font-size:26px;font-weight:900;color:#0B0616;
  background:linear-gradient(135deg,var(--c1),var(--c2))}
.prof .big img{width:100%;height:100%;object-fit:cover}
.prof .d{position:relative;flex:1;min-width:0}
.prof .d b{display:block;font-size:16px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prof .d span{display:block;font-size:11.5px;color:var(--dim);margin-top:4px;direction:ltr;text-align:right}
.prof .d code{display:inline-block;margin-top:7px;font-size:10.5px;padding:3px 9px;border-radius:8px;
  background:rgba(255,255,255,.08);border:1px solid var(--line);direction:ltr;font-family:ui-monospace,monospace}

.pane{margin-top:13px;padding:16px;border-radius:22px;border:1px solid var(--line);background:var(--pane)}
.pane h3{margin:0 0 12px;font-size:13px;font-weight:900;display:flex;align-items:center;gap:7px}
/* شماره کارت روی خط خودش می‌نشیند و دکمه زیرش — چون ۱۶ رقم با فاصله
   کنار یک دکمه، روی گوشی‌های باریک دو خط می‌شد و بد جا می‌افتاد. */
.card-no{padding:14px;border-radius:16px;
  border:1px dashed color-mix(in srgb,var(--c2) 40%,transparent);background:rgba(255,255,255,.04)}
.card-no b{display:block;font-size:19px;font-weight:900;letter-spacing:1.5px;direction:ltr;text-align:center;
  font-family:ui-monospace,monospace;white-space:nowrap;overflow-x:auto;
  scrollbar-width:none;color:var(--c2)}
.card-no b::-webkit-scrollbar{display:none}
.card-no button{width:100%;margin-top:11px;padding:11px;border:0;border-radius:12px;cursor:pointer;
  font-family:inherit;font-size:12px;font-weight:800;color:#0B0616;
  background:linear-gradient(135deg,var(--c2),var(--c1))}
.card-no button:active{transform:scale(.985)}
.card-holder{margin-top:9px;font-size:11.5px;color:var(--dim)}
.card-holder b{color:var(--ink)}
.amt{display:flex;gap:9px;margin-top:13px}
.amt input{flex:1;min-width:0;padding:14px;border-radius:15px;border:1px solid var(--line);
  background:rgba(255,255,255,.05);color:var(--ink);font-family:inherit;font-size:15px;font-weight:800;
  outline:none;text-align:center;transition:.2s}
.amt input:focus{border-color:var(--c1);box-shadow:0 0 0 3px color-mix(in srgb,var(--c1) 18%,transparent)}
.quick{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}
.quick i{padding:7px 12px;border-radius:11px;font-style:normal;font-size:11.5px;font-weight:800;cursor:pointer;
  border:1px solid var(--line);background:rgba(255,255,255,.05);color:var(--dim)}
.quick i:active{background:color-mix(in srgb,var(--c1) 30%,transparent);color:#fff}
.link{display:flex;align-items:center;gap:11px;padding:14px;border-radius:16px;margin-top:9px;cursor:pointer;
  border:1px solid var(--line);background:rgba(255,255,255,.04);font-size:12.5px;font-weight:700}
.link:active{transform:scale(.985)}
.link em{flex:1;font-style:normal}
.link s{text-decoration:none;color:var(--dim);font-size:16px}

.void{text-align:center;padding:46px 20px;color:var(--dim);font-size:12.5px;line-height:1.9}
.void div{font-size:44px;margin-bottom:10px;opacity:.55}
.skel{height:172px;border-radius:22px;border:1px solid var(--line);
  background:linear-gradient(90deg,rgba(255,255,255,.03),rgba(255,255,255,.075),rgba(255,255,255,.03));
  background-size:200% 100%;animation:sh 1.3s linear infinite}
@keyframes sh{to{background-position:-200% 0}}

/* ═══ 👑 صفحه‌ی مدیریت — فقط برای مدیر ═══ */
.adm{display:none}
body.is-admin .adm{display:block}
.arow{display:flex;align-items:center;gap:11px;padding:12px 13px;border-radius:16px;margin-bottom:8px;
  border:1px solid var(--line);background:var(--pane);cursor:pointer}
.arow .e{width:38px;height:38px;flex:0 0 auto;border-radius:13px;display:grid;place-items:center;font-size:19px;
  background:rgba(255,255,255,.06);border:1px solid var(--line)}
.arow .m{flex:1;min-width:0}
.arow .m b{display:block;font-size:12.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arow .m span{display:block;font-size:10px;color:var(--dim);margin-top:3px}
.arow .p{flex:0 0 auto;font-size:11.5px;font-weight:800;color:var(--c2)}
.arow.off{opacity:.5}
.aform .field{margin-bottom:11px}
.aform label{display:block;font-size:11px;font-weight:800;color:var(--dim);margin-bottom:6px}
.aform input,.aform select,.aform textarea{width:100%;padding:12px;border-radius:14px;border:1px solid var(--line);
  background:rgba(255,255,255,.05);color:var(--ink);font-family:inherit;font-size:13.5px;outline:none}
.aform textarea{min-height:64px;resize:vertical;font-size:12.5px}
.aform select{appearance:none}
.a2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.aswitch{display:flex;align-items:center;justify-content:space-between;padding:12px 13px;border-radius:14px;
  border:1px solid var(--line);background:rgba(255,255,255,.04);font-size:12.5px;font-weight:700;cursor:pointer}
.aswitch i{width:44px;height:25px;border-radius:13px;background:rgba(255,255,255,.12);position:relative;transition:.2s}
.aswitch i:after{content:"";position:absolute;top:3px;right:3px;width:19px;height:19px;border-radius:50%;
  background:#fff;transition:.2s}
.aswitch.on i{background:linear-gradient(135deg,var(--c1),var(--c2))}
.aswitch.on i:after{right:22px}

/* ═══ جزیره‌ی پایین ═══ */
.dock{position:fixed;left:50%;transform:translateX(-50%);bottom:calc(11px + var(--safe));z-index:30;
  width:min(94vw,420px);display:flex;gap:3px;padding:7px;border-radius:26px;
  border:1px solid rgba(255,255,255,.13);background:rgba(15,10,32,.86);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  box-shadow:0 18px 44px rgba(0,0,0,.55)}
body.fx0 .dock{backdrop-filter:none;-webkit-backdrop-filter:none;background:#100B22}
.dock b{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;gap:4px;
  padding:8px 2px;border-radius:19px;cursor:pointer;color:var(--dim);
  font-size:9.5px;font-weight:800;transition:color .16s,background .16s}
.dock b span{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dock b.on{color:#0B0616;background:linear-gradient(135deg,var(--c1),var(--c2))}
.dock b[data-p="adm"]{display:none}
body.is-admin .dock b[data-p="adm"]{display:flex}

/* ═══ شیت خرید ═══ */
.scrim{position:fixed;inset:0;z-index:40;background:rgba(4,2,10,.74);backdrop-filter:blur(7px);
  opacity:0;pointer-events:none;transition:.28s}
.scrim.on{opacity:1;pointer-events:auto}
.sheet{position:fixed;left:0;right:0;bottom:0;z-index:41;transform:translateY(102%);
  transition:transform .38s cubic-bezier(.2,.9,.25,1);
  background:linear-gradient(180deg,#17112E,#0B0718);
  border-radius:30px 30px 0 0;border-top:1px solid rgba(255,255,255,.14);
  padding:10px 17px calc(22px + var(--safe));max-height:92vh;overflow-y:auto;
  box-shadow:0 -24px 60px rgba(0,0,0,.6)}
.sheet.on{transform:none}
.grip{width:42px;height:4px;border-radius:4px;background:rgba(255,255,255,.22);margin:4px auto 16px}
.sheet .head{display:flex;align-items:center;gap:13px;margin-bottom:16px}
.sheet .head .orb{width:56px;height:56px;font-size:27px;margin:0}
.sheet .head h2{margin:0;font-size:16.5px;font-weight:900}
.sheet .head p{margin:4px 0 0;font-size:11.5px;color:var(--dim);line-height:1.7}

.field{margin-bottom:14px}
.field label{display:block;font-size:12px;font-weight:700;color:var(--dim);margin-bottom:7px}
.field input,.field textarea{width:100%;padding:14px;border-radius:15px;border:1px solid var(--line);
  background:rgba(255,255,255,.05);color:var(--ink);font-family:inherit;font-size:14.5px;outline:none;transition:.2s}
.field textarea{min-height:80px;resize:vertical;font-size:13px}
.field input:focus,.field textarea:focus{border-color:var(--c1);
  box-shadow:0 0 0 3px color-mix(in srgb,var(--c1) 18%,transparent)}
.field .hint{font-size:10.5px;color:var(--dim);margin-top:6px;line-height:1.7}
/* 👤 «برای خودم» — آیدیِ خودِ کاربر را توی کادر می‌گذارد.
   بیشترِ خریدها برای خودِ خریدار است و تایپِ دستیِ آیدی، هم غلط
   می‌شود هم حوصله می‌برد. */
.field .selfrow{display:flex;align-items:center;gap:7px;margin-top:8px;flex-wrap:wrap}
.field .self{
  display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border-radius:12px;cursor:pointer;
  font-family:inherit;font-size:11.5px;font-weight:800;color:#fff;
  background:linear-gradient(135deg,color-mix(in srgb,var(--c1) 70%,transparent),color-mix(in srgb,var(--c2) 55%,transparent));
  border:1px solid color-mix(in srgb,var(--c1) 40%,transparent);
  box-shadow:0 8px 20px -12px var(--c1);transition:transform .16s ease,filter .16s ease
}
.field .self:active{transform:scale(.94)}
.field .self[disabled]{opacity:.45;pointer-events:none;box-shadow:none}
.field .self.done{filter:saturate(.5)}
.field .selfrow em{font-style:normal;font-size:10.5px;color:var(--dim);direction:ltr}
/* ═══ انتخاب بسته — ردیف کامل با تیک، مثل اپ‌های گیفت ═══ */
.lbl{font-size:10.5px;font-weight:800;letter-spacing:1.1px;color:var(--dim);margin:14px 0 9px}
.plans{display:grid;gap:9px}
.plans i{display:flex;align-items:center;gap:12px;padding:13px 14px;border-radius:18px;cursor:pointer;
  font-style:normal;border:1px solid var(--line);background:rgba(255,255,255,.035);
  transition:border-color .18s,background .18s}
.plans i:active{transform:scale(.985)}
.plans i .pg{width:38px;height:38px;flex:0 0 auto;border-radius:13px;display:grid;place-items:center;font-size:20px;
  text-decoration:none;
  background:linear-gradient(140deg,color-mix(in srgb,var(--c1) 40%,transparent),color-mix(in srgb,var(--c2) 24%,transparent));
  border:1px solid rgba(255,255,255,.13)}
.plans i b{flex:1;min-width:0;font-size:13.5px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.plans i u{flex:0 0 auto;text-decoration:none;font-size:12px;font-weight:800;color:var(--c2)}
.plans i .chk{width:22px;height:22px;flex:0 0 auto;border-radius:7px;border:1.5px solid var(--line);
  display:grid;place-items:center;font-size:13px;font-style:normal;color:transparent}
.plans i.on{border-color:color-mix(in srgb,var(--c1) 70%,transparent);
  background:linear-gradient(120deg,color-mix(in srgb,var(--c1) 20%,transparent),transparent)}
.plans i.on .chk{border-color:transparent;color:#0B0616;
  background:linear-gradient(135deg,var(--c1),var(--c2))}

.step{display:flex;align-items:center;gap:10px}
.step button{width:46px;height:46px;flex:0 0 auto;border-radius:15px;border:1px solid var(--line);
  background:rgba(255,255,255,.06);color:var(--ink);font-size:21px;font-weight:700;cursor:pointer;transition:.16s}
.step button:active{transform:scale(.92);background:color-mix(in srgb,var(--c1) 28%,transparent)}
.step input{text-align:center;font-weight:900;font-size:17px}

.total{display:flex;justify-content:space-between;align-items:center;margin:16px 0;padding:15px 16px;
  border-radius:18px;border:1px solid var(--line);
  background:linear-gradient(120deg,color-mix(in srgb,var(--c1) 18%,transparent),color-mix(in srgb,var(--c3) 11%,transparent))}
.total span{font-size:12.5px;color:#CFC6EE}
.total b{font-size:20px;font-weight:900;
  background:linear-gradient(90deg,var(--c2),#fff);-webkit-background-clip:text;background-clip:text;color:transparent}

.go{width:100%;padding:16px;border:0;border-radius:18px;cursor:pointer;
  font-family:inherit;font-size:15px;font-weight:900;color:#0B0616;
  background:linear-gradient(135deg,var(--c1),var(--c2));transition:.2s}
body.glow-on .go{box-shadow:0 14px 34px -16px var(--c1)}
.go:active{transform:scale(.985)}
.go[disabled]{cursor:default;color:var(--dim);background:rgba(255,255,255,.05);
  border:1px solid var(--line);box-shadow:none}
.go.alt{margin-top:9px;color:var(--ink);background:rgba(255,255,255,.07);
  border:1px solid var(--line);box-shadow:none;font-weight:700;font-size:13.5px}
.walbox{margin-top:10px;padding:11px 14px;border-radius:13px;font-size:11.5px;line-height:1.8;
  border:1px solid var(--line);background:rgba(255,255,255,.035);color:var(--dim)}
.walbox b{color:var(--c2)}
.ghost{width:100%;margin-top:9px;padding:14px;border-radius:16px;cursor:pointer;
  border:1px solid var(--line);background:transparent;color:var(--dim);font-family:inherit;font-size:13.5px;font-weight:700}

/* ═══ موفقیت ═══ */
.win{position:fixed;inset:0;z-index:60;display:none;place-items:center;text-align:center;padding:30px;
  background-color:var(--bg);
  background-image:radial-gradient(80% 60% at 50% 40%,color-mix(in srgb,var(--c1) 28%,transparent),transparent 72%)}
.win.on{display:grid}
.ring{position:relative;width:110px;height:110px;margin:0 auto 22px;border-radius:50%;display:grid;place-items:center;font-size:50px;
  background:linear-gradient(135deg,var(--c1),var(--c2));animation:pop .55s cubic-bezier(.2,1.5,.4,1) backwards}
@keyframes pop{from{transform:scale(0) rotate(-45deg);opacity:0}to{transform:none;opacity:1}}
.ring:after{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid var(--c2);
  animation:pulse 1.9s ease-out infinite}
@keyframes pulse{from{transform:scale(1);opacity:.85}to{transform:scale(1.9);opacity:0}}
@media (prefers-reduced-motion:reduce){ .ring,.ring:after{animation:none} }
.win h2{margin:0 0 9px;font-size:20px;font-weight:900}
.win p{margin:0 0 24px;font-size:12.5px;color:var(--dim);line-height:1.9;max-width:300px}
.win .code{font-family:ui-monospace,monospace;font-size:12px;padding:8px 14px;border-radius:11px;
  border:1px solid var(--line);background:rgba(255,255,255,.05);margin-bottom:20px;direction:ltr}

/* ═══ پیام ═══ */
.toast{position:fixed;top:14px;left:50%;transform:translate(-50%,-160%);z-index:80;
  padding:13px 18px;border-radius:15px;font-size:12.5px;font-weight:700;max-width:88vw;text-align:center;
  background:linear-gradient(135deg,#FF3355,#B1004B);color:#fff;
  transition:transform .34s cubic-bezier(.2,1.3,.4,1);box-shadow:0 14px 34px -12px #FF3355;line-height:1.7}
.toast.ok{background:linear-gradient(135deg,var(--c2),var(--c1));color:#0B0616;box-shadow:none}
.toast.on{transform:translate(-50%,0)}
</style>
CSS;
}
