<?php
/**
 * ☎️ نمای مینی‌اپ «شماره مجازی» — پوسته‌ی Ocean
 *
 * این یکی برخلاف دو نمای دیگر، قالبِ مشترک را قرض نمی‌گیرد و صفحه‌ی
 * خودش را دارد. دلیلش یک چیز است: فروش شماره یک صفحه‌ی اضافه دارد که
 * هیچ محصول دیگری ندارد — «انتظار برای کد». آن صفحه زنده است (شمارش
 * معکوس، پرسش هر چند ثانیه، کپی، لغو) و چپاندنش در قالبِ مشترک، هم آن
 * قالب را شلوغ می‌کرد هم این را ناقص.
 *
 * قاعده‌های این فایل:
 *   • هیچ فایل بیرونی بار نمی‌شود — نه فونت، نه کتابخانه. صفحه در یک
 *     رفت‌وبرگشت کامل می‌شود و روی اینترنت همراه هم سریع باز می‌شود.
 *   • هیچ داده‌ای با innerHTML داخل صفحه نمی‌رود؛ همه با textContent.
 *     پس اسم محصول یا نام کاربر، هرچه باشد، فقط متن است.
 *   • هر درخواست initData را می‌برد؛ سرور خودش امضا را بررسی می‌کند.
 */

function maViewNum($a, $boot) {
    $th = $a['theme'] ?? [];
    return strtr(maTplNum(), [
        '__C1__'    => $th['c1'] ?? '#2E7DFF',
        '__C2__'    => $th['c2'] ?? '#00E0C6',
        '__C3__'    => $th['c3'] ?? '#7C4DFF',
        '__BG__'    => $th['bg'] ?? '#050B18',
        '__GLOW__'  => !empty($th['glow']) ? '1' : '0',
        '__FX__'    => (string)maFxLevel($th),
        '__TITLE__' => htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8'),
        '__BOOT__'  => json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                         | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ]);
}

function maTplNum() {
    return <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,maximum-scale=1,user-scalable=no">
<meta name="color-scheme" content="dark">
<title>__TITLE__</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<style>
:root{
  --c1:__C1__; --c2:__C2__; --c3:__C3__; --bg:__BG__;

  --s1:#0C1526;      /* کارت */
  --s2:#111C31;      /* کارت برجسته */
  --s3:#17243D;      /* فشرده / فعال */
  --hair:rgba(255,255,255,.07);

  --ink:#EAF1FF;
  --dim:#8FA0BF;
  --dim2:#5D6C88;
  --ok:#2ED47A;
  --warn:#FFB020;
  --bad:#FF5A6E;

  --mono:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  --ui:-apple-system,BlinkMacSystemFont,"Segoe UI",Vazirmatn,Tahoma,sans-serif;
  --r-lg:22px; --r-md:16px; --r-sm:12px;
  --safe:env(safe-area-inset-bottom,0px);
  --ease:cubic-bezier(.22,.61,.36,1);
  --nav:72px;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;padding:0;min-height:100%}
body{
  background:var(--bg); color:var(--ink); font-family:var(--ui);
  font-size:15px; line-height:1.6; overflow-x:hidden;
  padding-bottom:calc(var(--nav) + var(--safe) + 12px);
}
/* 🌊 هاله‌های پس‌زمینه — ثابت، بدون انیمیشنِ سنگین */
body::before{
  content:''; position:fixed; inset:-30% -20% auto -20%; height:70vh; z-index:0;
  background:
    radial-gradient(42% 46% at 22% 8%, color-mix(in srgb,var(--c1) 34%, transparent), transparent 70%),
    radial-gradient(38% 42% at 84% 4%, color-mix(in srgb,var(--c2) 26%, transparent), transparent 70%),
    radial-gradient(46% 40% at 52% 42%, color-mix(in srgb,var(--c3) 18%, transparent), transparent 72%);
  filter:blur(30px); opacity:calc(.55 * __GLOW__); pointer-events:none;
}
.wrap{position:relative; z-index:1; max-width:640px; margin:0 auto; padding:14px 14px 0}

/* ── سربرگ ───────────────────────────────── */
.top{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.ava{width:42px;height:42px;border-radius:14px;object-fit:cover;background:var(--s2);flex:0 0 auto}
.ava.ph{display:grid;place-items:center;font-size:19px;color:var(--dim)}
.who{min-width:0;flex:1}
.who b{display:block;font-size:14.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.who span{display:block;font-size:11.5px;color:var(--dim2)}
.bal{
  display:flex;align-items:center;gap:7px;padding:8px 12px;border-radius:14px;
  background:linear-gradient(135deg,color-mix(in srgb,var(--c1) 22%,var(--s2)),var(--s1));
  box-shadow:0 6px 18px -10px color-mix(in srgb,var(--c1) 70%,transparent);
  cursor:pointer;flex:0 0 auto;border:1px solid var(--hair)
}
.bal i{font-style:normal;font-size:15px}
.bal b{font-size:14px;font-weight:800;font-family:var(--mono);letter-spacing:.2px}
.bal small{font-size:10px;color:var(--dim);display:block;margin-top:-3px}

/* ── قهرمان ──────────────────────────────── */
.hero{
  position:relative;overflow:hidden;border-radius:var(--r-lg);padding:18px 16px;margin-bottom:16px;
  background:linear-gradient(140deg,color-mix(in srgb,var(--c1) 30%,var(--s1)) 0%,var(--s1) 55%,color-mix(in srgb,var(--c3) 22%,var(--s1)) 100%);
  border:1px solid var(--hair);box-shadow:0 18px 44px -26px rgba(0,0,0,.9)
}
.hero h1{margin:0 0 4px;font-size:20px;font-weight:800;letter-spacing:-.2px}
.hero p{margin:0;font-size:12.5px;color:var(--dim);line-height:1.75}
.hero .rays{position:absolute;inset:auto -20% -60% -20%;height:150px;
  background:radial-gradient(50% 100% at 50% 100%,color-mix(in srgb,var(--c2) 40%,transparent),transparent 70%);
  filter:blur(24px);opacity:calc(.7 * __GLOW__);pointer-events:none}

/* ── نوار کشورها ─────────────────────────── */
.cats{display:flex;gap:8px;overflow-x:auto;padding:2px 0 12px;scrollbar-width:none;-webkit-overflow-scrolling:touch}
.cats::-webkit-scrollbar{display:none}
.cat{
  flex:0 0 auto;display:flex;align-items:center;gap:6px;padding:9px 14px;border-radius:14px;
  background:var(--s1);border:1px solid var(--hair);color:var(--dim);
  font-size:13px;font-weight:600;cursor:pointer;transition:.22s var(--ease);white-space:nowrap
}
.cat i{font-style:normal;font-size:15px}
.cat[aria-selected="true"]{
  color:#fff;border-color:transparent;
  background:linear-gradient(135deg,var(--tint,var(--c1)),color-mix(in srgb,var(--tint,var(--c1)) 55%,var(--c3)));
  box-shadow:0 8px 22px -12px var(--tint,var(--c1))
}

/* ── جستجو ───────────────────────────────── */
.srch{margin:0 0 10px}
.srch input{
  width:100%;padding:12px 14px;border-radius:14px;background:var(--s1);
  border:1px solid var(--hair);color:var(--ink);font-family:var(--ui);font-size:14px;outline:0
}
.srch input::placeholder{color:var(--dim2)}
.srch input:focus{border-color:color-mix(in srgb,var(--c1) 50%,transparent)}
#more{margin-top:12px}

/* ── شبکه‌ی دو ستونه ─────────────────────── */
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media (max-width:330px){.grid{grid-template-columns:1fr}}
.card{
  position:relative;overflow:hidden;border-radius:var(--r-md);padding:13px 12px 12px;
  background:linear-gradient(160deg,color-mix(in srgb,var(--tint) 14%,var(--s1)),var(--s1) 70%);
  border:1px solid var(--hair);cursor:pointer;transition:.2s var(--ease);
  display:flex;flex-direction:column;min-height:150px
}
.card:active{transform:scale(.975)}
.card::after{content:'';position:absolute;inset:auto -30% -70% -30%;height:90px;
  background:radial-gradient(50% 100% at 50% 100%,color-mix(in srgb,var(--tint) 45%,transparent),transparent 70%);
  filter:blur(18px);opacity:calc(.55 * __GLOW__);pointer-events:none}
.card .fl{display:flex;align-items:center;gap:6px;margin-bottom:7px}
.card .fl b{font-size:20px;line-height:1}
.card .badge{
  margin-inline-start:auto;font-size:9.5px;font-weight:800;padding:3px 7px;border-radius:8px;
  background:color-mix(in srgb,var(--tint) 24%,transparent);color:color-mix(in srgb,var(--tint) 70%,#fff);
  border:1px solid color-mix(in srgb,var(--tint) 34%,transparent);white-space:nowrap
}
.card h3{margin:0 0 3px;font-size:13.5px;font-weight:700;line-height:1.45}
.card p{margin:0;font-size:11px;color:var(--dim2);line-height:1.65;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.card .foot{margin-top:auto;padding-top:10px;display:flex;align-items:flex-end;justify-content:space-between;gap:6px}
.card .price{font-family:var(--mono);font-size:15px;font-weight:800;letter-spacing:-.2px;direction:ltr}
.card .price s{display:block;font-size:9px;color:var(--dim2);text-decoration:none;font-family:var(--ui);font-weight:600}
.card .go{
  width:32px;height:32px;border-radius:11px;display:grid;place-items:center;flex:0 0 auto;
  background:linear-gradient(135deg,var(--tint),color-mix(in srgb,var(--tint) 50%,var(--c3)));
  color:#fff;font-size:15px;box-shadow:0 6px 16px -8px var(--tint)
}
.card.off{opacity:.45;pointer-events:none;filter:grayscale(.5)}

/* ── صفحه‌ی انتظار کد ────────────────────── */
.live{
  border-radius:var(--r-lg);padding:16px;margin-bottom:16px;position:relative;overflow:hidden;
  background:linear-gradient(150deg,color-mix(in srgb,var(--c2) 22%,var(--s2)),var(--s1) 70%);
  border:1px solid color-mix(in srgb,var(--c2) 26%,transparent);
  box-shadow:0 20px 50px -28px color-mix(in srgb,var(--c2) 80%,transparent)
}
.live .ttl{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--dim);margin-bottom:12px}
.dot{width:8px;height:8px;border-radius:50%;background:var(--c2);flex:0 0 auto;
  box-shadow:0 0 0 0 color-mix(in srgb,var(--c2) 70%,transparent);animation:pulse 1.8s infinite}
@keyframes pulse{70%{box-shadow:0 0 0 10px transparent}100%{box-shadow:0 0 0 0 transparent}}
.big{
  display:flex;align-items:center;gap:10px;padding:13px 14px;border-radius:var(--r-md);
  background:rgba(0,0,0,.28);border:1px solid var(--hair);margin-bottom:9px
}
.big .v{flex:1;min-width:0;font-family:var(--mono);font-size:20px;font-weight:800;
  direction:ltr;text-align:left;letter-spacing:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.big .k{font-size:10.5px;color:var(--dim2);display:block;letter-spacing:0;font-family:var(--ui);font-weight:600}
.cp{
  padding:9px 12px;border-radius:11px;background:var(--s3);border:1px solid var(--hair);
  color:var(--ink);font-size:12px;font-weight:700;cursor:pointer;flex:0 0 auto;transition:.18s var(--ease)
}
.cp:active{transform:scale(.94)}
.cp.done{background:color-mix(in srgb,var(--ok) 24%,var(--s3));border-color:color-mix(in srgb,var(--ok) 40%,transparent)}
.code .v{color:var(--ok);font-size:26px;letter-spacing:4px}
.timer{display:flex;align-items:center;gap:9px;margin:12px 0 4px}
.bar{flex:1;height:6px;border-radius:6px;background:rgba(0,0,0,.35);overflow:hidden}
.bar i{display:block;height:100%;border-radius:6px;transition:width 1s linear;
  background:linear-gradient(90deg,var(--c2),var(--c1))}
.timer b{font-family:var(--mono);font-size:13px;font-weight:800;direction:ltr;color:var(--dim)}

/* ── دکمه‌ها ─────────────────────────────── */
.btn{
  width:100%;padding:14px;border-radius:var(--r-md);border:0;cursor:pointer;
  font-family:var(--ui);font-size:14.5px;font-weight:800;color:#fff;
  background:linear-gradient(135deg,var(--c1),var(--c3));
  box-shadow:0 12px 28px -14px var(--c1);transition:.2s var(--ease)
}
.btn:active{transform:scale(.98)}
.btn[disabled]{opacity:.5;pointer-events:none}
.btn.ghost{background:var(--s2);border:1px solid var(--hair);box-shadow:none;color:var(--ink)}
.btn.danger{background:linear-gradient(135deg,var(--bad),#C7304A);box-shadow:0 12px 28px -14px var(--bad)}
.btn.sm{padding:11px;font-size:13px;border-radius:13px}

/* ── ناوبری پایین ────────────────────────── */
.nav{
  position:fixed;inset:auto 0 0 0;z-index:40;display:flex;justify-content:space-around;
  padding:8px 8px calc(8px + var(--safe));backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
  background:color-mix(in srgb,var(--bg) 82%,transparent);border-top:1px solid var(--hair)
}
.nav button{
  flex:1;background:none;border:0;color:var(--dim2);font-family:var(--ui);font-size:10.5px;font-weight:700;
  display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 2px;cursor:pointer;transition:.2s var(--ease)
}
.nav button i{font-style:normal;font-size:19px;line-height:1}
.nav button[aria-selected="true"]{color:var(--c2)}
.nav button[aria-selected="true"] i{transform:translateY(-2px);filter:drop-shadow(0 4px 10px color-mix(in srgb,var(--c2) 60%,transparent))}

/* ── صفحه‌ها ─────────────────────────────── */
.page{display:none;animation:in .28s var(--ease)}
.page.on{display:block}
@keyframes in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.sect{display:flex;align-items:center;gap:7px;margin:18px 2px 10px;font-size:12.5px;font-weight:700;color:var(--dim)}
.sect::after{content:'';flex:1;height:1px;background:var(--hair)}
.empty{text-align:center;padding:38px 16px;color:var(--dim2);font-size:13px}
.empty i{display:block;font-style:normal;font-size:38px;margin-bottom:10px;opacity:.5}

/* ── ردیف سفارش ──────────────────────────── */
.row{
  display:flex;align-items:center;gap:11px;padding:12px;border-radius:var(--r-md);
  background:var(--s1);border:1px solid var(--hair);margin-bottom:8px
}
.row .ic{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;font-size:18px;
  background:var(--s3);flex:0 0 auto}
.row .tx{flex:1;min-width:0}
.row .tx b{display:block;font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.row .tx span{display:block;font-size:11px;color:var(--dim2);font-family:var(--mono);direction:ltr;text-align:right}
.pill{font-size:10px;font-weight:800;padding:4px 9px;border-radius:9px;white-space:nowrap;flex:0 0 auto}
.pill.w{background:color-mix(in srgb,var(--warn) 20%,transparent);color:var(--warn)}
.pill.d{background:color-mix(in srgb,var(--ok) 20%,transparent);color:var(--ok)}
.pill.x{background:color-mix(in srgb,var(--bad) 20%,transparent);color:var(--bad)}

/* ── 📋 کارتِ سفارش ──────────────────────── */
.ocard{
  border-radius:var(--r-lg);padding:14px;margin-bottom:12px;
  background:linear-gradient(150deg,color-mix(in srgb,var(--c1) 12%,var(--s1)),var(--s1) 70%);
  border:1px solid var(--hair)
}
.ohint{margin:0 2px 14px;font-size:11.5px;color:var(--dim2);line-height:1.75}
.ohead{display:flex;align-items:center;gap:8px;margin-bottom:11px}
.ohead b{flex:1;min-width:0;font-size:13.5px;font-weight:700;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ocard .big{margin-bottom:8px}
.ocard .big .v{font-size:17px}
.ocard .code .v{font-size:22px}
.ocard .timer{margin:10px 0 12px}

/* ── شیت و توست ──────────────────────────── */
.mask{position:fixed;inset:0;z-index:60;background:rgba(2,6,14,.72);backdrop-filter:blur(6px);
  opacity:0;pointer-events:none;transition:.25s var(--ease)}
.mask.on{opacity:1;pointer-events:auto}
.sheet{
  position:fixed;inset:auto 0 0 0;z-index:61;max-width:640px;margin:0 auto;
  background:var(--s1);border:1px solid var(--hair);border-bottom:0;
  border-radius:26px 26px 0 0;padding:8px 16px calc(20px + var(--safe));
  transform:translateY(105%);transition:.32s var(--ease);max-height:88vh;overflow-y:auto
}
.sheet.on{transform:none}
.grip{width:38px;height:4px;border-radius:4px;background:var(--hair);margin:6px auto 14px}
.sheet h2{margin:0 0 6px;font-size:17px;font-weight:800}
.sheet .lead{margin:0 0 16px;font-size:12.5px;color:var(--dim);line-height:1.8}
.fld{margin-bottom:12px}
.fld label{display:block;font-size:11.5px;color:var(--dim);margin-bottom:6px;font-weight:600}
.fld input{
  width:100%;padding:13px;border-radius:13px;background:var(--s3);border:1px solid var(--hair);
  color:var(--ink);font-family:var(--mono);font-size:15px;direction:ltr;text-align:left;outline:0
}
.fld input:focus{border-color:color-mix(in srgb,var(--c1) 50%,transparent)}
.kv{display:flex;justify-content:space-between;gap:10px;padding:10px 0;font-size:13px;border-bottom:1px solid var(--hair)}
.kv:last-of-type{border-bottom:0}
.kv span{color:var(--dim)}
.kv b{font-family:var(--mono);direction:ltr}
.toast{
  position:fixed;left:50%;bottom:calc(var(--nav) + var(--safe) + 16px);transform:translate(-50%,20px);
  z-index:80;padding:11px 18px;border-radius:14px;background:var(--s3);border:1px solid var(--hair);
  font-size:13px;font-weight:600;box-shadow:0 16px 40px -16px rgba(0,0,0,.9);
  opacity:0;pointer-events:none;transition:.26s var(--ease);max-width:88vw;text-align:center
}
.toast.on{opacity:1;transform:translate(-50%,0)}
.toast.ok{border-color:color-mix(in srgb,var(--ok) 45%,transparent)}
.toast.bad{border-color:color-mix(in srgb,var(--bad) 45%,transparent)}
.sk{border-radius:var(--r-md);background:linear-gradient(100deg,var(--s1) 30%,var(--s2) 50%,var(--s1) 70%);
  background-size:220% 100%;animation:sh 1.3s infinite;min-height:150px}
@keyframes sh{from{background-position:200% 0}to{background-position:-40% 0}}
</style>
</head>
<body>
<div class="wrap">

  <header class="top">
    <div class="ava ph" id="ava">👤</div>
    <div class="who"><b id="uname">…</b><span id="uhandle"></span></div>
    <div class="bal" id="balBtn"><i>💎</i><div><b id="balV">—</b><small id="balK">اعتبار</small></div></div>
  </header>

  <!-- 🏠 خانه -->
  <section class="page on" id="p-home">
    <div class="hero"><div class="rays"></div>
      <h1 id="hTitle">…</h1><p id="hHero"></p></div>
    <div id="liveBox"></div>
    <div class="sect" id="sHot">پرطرفدارترین‌ها</div>
    <div class="grid" id="hotGrid"><div class="sk"></div><div class="sk"></div></div>
  </section>

  <!-- ☎️ شماره‌ها -->
  <section class="page" id="p-shop">
    <div class="srch"><input id="q" type="search" inputmode="search" autocomplete="off"></div>
    <div class="cats" id="cats"></div>
    <div class="grid" id="grid"></div>
    <button class="btn ghost" id="more" hidden></button>
    <div class="empty" id="gridEmpty" hidden><i>🔍</i><span></span></div>
  </section>

  <!-- 🧾 سفارش‌ها -->
  <section class="page" id="p-orders">
    <div class="sect" id="sOrders">سفارش‌های من</div>
    <p class="ohint" id="oHint"></p>
    <div id="orders"></div>
  </section>

  <!-- 👤 حساب -->
  <section class="page" id="p-me">
    <div class="hero"><div class="rays"></div>
      <h1 id="meName">…</h1><p id="meNote"></p></div>
    <div class="kv"><span id="kBal">اعتبار</span><b id="meBal">—</b></div>
    <div class="kv"><span>شناسه</span><b id="meId">—</b></div>
    <div style="height:16px"></div>
    <button class="btn" id="topupBtn">＋ شارژ</button>
  </section>
</div>

<nav class="nav" id="nav">
  <button data-go="home"   aria-selected="true"><i>🏠</i><span>خانه</span></button>
  <button data-go="shop"><i>☎️</i><span>شماره‌ها</span></button>
  <button data-go="orders"><i>🧾</i><span>سفارش‌ها</span></button>
  <button data-go="me"><i>👤</i><span>حساب من</span></button>
</nav>

<div class="mask" id="mask"></div>
<div class="sheet" id="sheet"><div class="grip"></div><div id="sheetIn"></div></div>
<div class="toast" id="toast"></div>

<script>
"use strict";
const B = __BOOT__;
const TG = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
if (TG) { try { TG.ready(); TG.expand(); TG.setHeaderColor && TG.setHeaderColor(getComputedStyle(document.documentElement).getPropertyValue('--bg').trim()); } catch (e) {} }

/* 🎨 رنگِ هر کشور — تا شبکه یک‌دست و بی‌روح نشود */
const TINTS = ['#FF5A6E','#3FA9FF','#FFC93C','#5BE7A9','#B47CFF','#FF9A5B','#4FD9E8','#FF7BD5','#7FE07F','#FFA0C9'];
const tintOf = i => TINTS[i % TINTS.length];

const $  = s => document.querySelector(s);
const $$ = s => Array.from(document.querySelectorAll(s));
const U  = (k, d) => (B.ui && B.ui[k]) ? B.ui[k] : (d || '');

/* عددها همیشه انگلیسی و سه‌رقم‌سه‌رقم */
const fmt = n => {
  n = Math.round(Number(n) || 0);
  return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
};
const mmss = s => {
  s = Math.max(0, Math.floor(s));
  return (Math.floor(s / 60)).toString().padStart(2, '0') + ':' + (s % 60).toString().padStart(2, '0');
};
const buzz = t => { try { TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred(t || 'light'); } catch (e) {} };

let toastT = 0;
function toast(msg, kind) {
  const el = $('#toast');
  el.textContent = msg;
  el.className = 'toast on ' + (kind || '');
  clearTimeout(toastT);
  toastT = setTimeout(() => { el.className = 'toast ' + (kind || ''); }, 2600);
}

/* ── تماس با سرور ───────────────────────────
   هر درخواست initData را می‌برد. اگر شبکه نبود، خطا را بلعیده نمی‌کنیم
   ولی صفحه هم نمی‌شکند: {ok:false} برمی‌گردد و صداکننده تصمیم می‌گیرد. */
async function api(action, data) {
  if (!B.api) return { ok: false, error: 'no_api' };
  const payload = Object.assign({ action, app: B.app, initData: TG ? TG.initData : '' }, data || {});
  try {
    const r = await fetch(B.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      cache: 'no-store',
    });
    const j = await r.json().catch(() => null);
    return j && typeof j === 'object' ? j : { ok: false, error: 'bad_json' };
  } catch (e) {
    return { ok: false, error: 'net' };
  }
}

/* ── وضعیت ───────────────────────────────── */
const S = { me: null, cat: 'all', live: null, tick: 0, poll: 0, page: 'home',
            q: '', shown: 0 };

/* ── ساخت کارت محصول ─────────────────────── */
function catIndex(id) {
  const i = (B.cats || []).findIndex(c => c.id === id);
  return i < 0 ? 0 : i;
}

/* 🎨 رنگِ یک کارت.
   وقتی دسته‌بندی هست، رنگ مالِ دسته است تا کارت‌های یک کشور کنار هم
   دیده شوند. وقتی نیست — یعنی خودِ کشور محصول است — رنگ را از شناسه‌ی
   خودِ محصول درمی‌آوریم، وگرنه کلِ شبکه یک‌رنگ و بی‌روح می‌شود. */
function tintFor(it) {
  if (it.cat && (B.cats || []).length) return tintOf(catIndex(it.cat));
  let h = 0;
  const k = String(it.id || it.name || '');
  for (let i = 0; i < k.length; i++) h = (h * 31 + k.charCodeAt(i)) % 100003;
  return tintOf(h);
}
function catOf(id) { return (B.cats || []).find(c => c.id === id) || null; }

function cardEl(it) {
  const c = catOf(it.cat);
  const el = document.createElement('div');
  el.className = 'card' + (it.stale ? ' off' : '');
  el.style.setProperty('--tint', tintFor(it));

  const fl = document.createElement('div'); fl.className = 'fl';
  const b = document.createElement('b'); b.textContent = (c && c.emoji) || it.emoji || '☎️';
  fl.appendChild(b);
  if (it.badge) { const g = document.createElement('span'); g.className = 'badge'; g.textContent = it.badge; fl.appendChild(g); }
  el.appendChild(fl);

  const h = document.createElement('h3'); h.textContent = it.name; el.appendChild(h);
  if (it.desc) { const p = document.createElement('p'); p.textContent = it.desc; el.appendChild(p); }

  const foot = document.createElement('div'); foot.className = 'foot';
  const pr = document.createElement('div'); pr.className = 'price';
  pr.textContent = fmt(it.price);
  const s = document.createElement('s'); s.textContent = B.currency || 'تومان'; pr.appendChild(s);
  foot.appendChild(pr);
  const go = document.createElement('div'); go.className = 'go'; go.textContent = '←';
  foot.appendChild(go);
  el.appendChild(foot);

  el.addEventListener('click', () => { buzz(); askBuy(it); });
  return el;
}

/* 📄 چند کارت در هر نوبت ساخته شود.
   با کاتالوگِ بزرگ، ساختنِ یک‌جای همه‌ی کارت‌ها صفحه را قفل می‌کند:
   ۸۰۰ محصول یعنی هفت‌هزار گره و بیش از یک ثانیه فریزِ کامل روی گوشی.
   پس یک صفحه می‌سازیم و بقیه با اسکرول می‌آیند. */
const PAGE = 40;

function visibleItems() {
  const q = (S.q || '').trim().toLowerCase();
  return (B.items || []).filter(i => {
    if (S.cat !== 'all' && i.cat !== S.cat) return false;
    if (!q) return true;
    return (i.name || '').toLowerCase().includes(q)
        || (i.desc || '').toLowerCase().includes(q);
  });
}

function paintGrid(reset) {
  const g = $('#grid'), e = $('#gridEmpty');
  if (reset !== false) { g.textContent = ''; S.shown = 0; }

  const list = visibleItems();
  if (!list.length) {
    e.hidden = false;
    e.querySelector('span').textContent = S.q ? U('no_match', 'چیزی پیدا نشد') : U('empty', 'چیزی نیست.');
    $('#more').hidden = true;
    return;
  }
  e.hidden = true;

  const frag = document.createDocumentFragment();
  const to = Math.min(list.length, S.shown + PAGE);
  for (let i = S.shown; i < to; i++) frag.appendChild(cardEl(list[i]));
  g.appendChild(frag);
  S.shown = to;

  const left = list.length - S.shown;
  const more = $('#more');
  more.hidden = left <= 0;
  if (left > 0) more.textContent = U('more', 'بیشتر') + ' (' + fmt(left) + ')';
}

function paintCats() {
  const box = $('#cats');
  box.textContent = '';
  const cats = B.cats || [];

  // یک نوارِ دسته که فقط «همه» دارد، جا می‌گیرد و کاری نمی‌کند
  if (cats.length < 1) { box.hidden = true; return; }
  box.hidden = false;

  const mk = (id, emoji, name, idx) => {
    const b = document.createElement('div');
    b.className = 'cat'; b.setAttribute('aria-selected', S.cat === id ? 'true' : 'false');
    b.style.setProperty('--tint', tintOf(idx));
    const i = document.createElement('i'); i.textContent = emoji; b.appendChild(i);
    const s = document.createElement('span'); s.textContent = name; b.appendChild(s);
    b.addEventListener('click', () => { S.cat = id; buzz(); paintCats(); paintGrid(); });
    return b;
  };
  // «همه» رنگِ خودِ تم را می‌گیرد، نه یکی از رنگ‌های کشورها — وگرنه
  // شبیه یک کشورِ دیگر می‌شود و از بقیه جدا دیده نمی‌شود.
  const all = mk('all', '🌍', U('all', 'همه کشورها'), 0);
  all.style.setProperty('--tint', getComputedStyle(document.documentElement).getPropertyValue('--c1').trim() || '#2E7DFF');
  box.appendChild(all);
  cats.forEach((c, i) => box.appendChild(mk(c.id, c.emoji || '🏳️', c.name, i)));
}

function paintHot() {
  const g = $('#hotGrid'); g.textContent = '';
  const list = (B.items || []).filter(i => !i.stale).slice(0, 4);
  if (!list.length) { g.textContent = ''; return; }
  const frag = document.createDocumentFragment();
  list.forEach(i => frag.appendChild(cardEl(i)));
  g.appendChild(frag);
}

/* ── صفحه‌ی زنده‌ی شماره ─────────────────── */
function paintLive(n) {
  const box = $('#liveBox');
  box.textContent = '';
  S.live = n;
  if (!n) { stopPoll(); return; }

  const w = document.createElement('div'); w.className = 'live';

  const ttl = document.createElement('div'); ttl.className = 'ttl';
  const d = document.createElement('span'); d.className = 'dot'; ttl.appendChild(d);
  const tt = document.createElement('span');
  tt.textContent = n.status === 'done' ? U('code_ttl', 'کد شما') : U('wait_ttl', 'منتظر پیامک');
  ttl.appendChild(tt);
  if (n.name) { const nm = document.createElement('span'); nm.style.marginInlineStart = 'auto'; nm.textContent = n.name; ttl.appendChild(nm); }
  w.appendChild(ttl);

  w.appendChild(bigRow(U('num_ttl', 'شماره‌ی شما'), n.phone, U('copy_num', 'کپی'), false));
  if (n.code) w.appendChild(bigRow(U('code_ttl', 'کد شما'), n.code, U('copy_code', 'کپی کد'), true));

  if (n.status === 'waiting') {
    const t = document.createElement('div'); t.className = 'timer';
    const bar = document.createElement('div'); bar.className = 'bar';
    const fill = document.createElement('i');
    fill.style.width = Math.max(0, Math.min(100, (n.left / Math.max(1, n.wait)) * 100)) + '%';
    bar.appendChild(fill); t.appendChild(bar);
    const lab = document.createElement('b'); lab.textContent = mmss(n.left); t.appendChild(lab);
    w.appendChild(t);

    const sub = document.createElement('p');
    sub.style.cssText = 'margin:6px 0 12px;font-size:11.5px;color:var(--dim);line-height:1.7';
    sub.textContent = U('wait_sub', '');
    w.appendChild(sub);

    const btn = document.createElement('button');
    btn.className = 'btn danger sm'; btn.textContent = U('cancel_do', 'لغو');
    btn.addEventListener('click', () => askCancel(n));
    w.appendChild(btn);

    startPoll();
    S.tick = Math.max(0, n.left);
  } else {
    stopPoll();

    // 🔁 «کد مجدد» فقط وقتی که سرور گفته این شماره واقعا می‌تواند —
    //    دکمه‌ای که بزنی و خطا بدهد، بدتر از نبودنش است.
    if (n.repeat) {
      const rp = document.createElement('button');
      rp.className = 'btn sm'; rp.textContent = U('repeat', 'کد مجدد');
      rp.style.marginTop = '10px';
      rp.addEventListener('click', async () => {
        rp.disabled = true; buzz();
        const r = await api('num_repeat', { order: n.order });
        rp.disabled = false;
        if (r.ok && r.num) { paintLive(r.num); toast(U('repeat_ok', ''), 'ok'); }
        else toast(r.message || 'انجام نشد', 'bad');
      });
      w.appendChild(rp);
    }

    const btn = document.createElement('button');
    btn.className = 'btn ghost sm'; btn.textContent = U('again', 'شماره‌ی تازه');
    btn.style.marginTop = '8px';
    btn.addEventListener('click', () => { paintLive(null); go('shop'); });
    w.appendChild(btn);
  }
  box.appendChild(w);
}

function bigRow(key, val, cpLabel, isCode) {
  const r = document.createElement('div'); r.className = 'big' + (isCode ? ' code' : '');
  const wrap = document.createElement('div'); wrap.style.cssText = 'flex:1;min-width:0';
  const k = document.createElement('span'); k.className = 'k'; k.textContent = key; wrap.appendChild(k);
  const v = document.createElement('div'); v.className = 'v'; v.textContent = val || '—'; wrap.appendChild(v);
  r.appendChild(wrap);
  const cp = document.createElement('button'); cp.className = 'cp'; cp.textContent = cpLabel;
  cp.addEventListener('click', () => copy(val, cp, cpLabel));
  r.appendChild(cp);
  return r;
}

function copy(txt, btn, label) {
  const done = () => {
    buzz('medium');
    btn.classList.add('done'); btn.textContent = U('copied', 'کپی شد ✓');
    setTimeout(() => { btn.classList.remove('done'); btn.textContent = label; }, 1600);
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(String(txt)).then(done).catch(() => fallbackCopy(String(txt), done));
  } else fallbackCopy(String(txt), done);
}
function fallbackCopy(txt, cb) {
  const ta = document.createElement('textarea');
  ta.value = txt; ta.setAttribute('readonly', '');
  ta.style.cssText = 'position:fixed;top:-1000px;opacity:0';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); cb(); } catch (e) { toast(txt); }
  document.body.removeChild(ta);
}

/* ── پرسش دوره‌ای ───────────────────────────
   فقط وقتی صفحه دیده می‌شود و فقط وقتی شماره‌ای باز است. هر ۵ ثانیه؛
   سرور خودش هم فاصله می‌گذارد، پس پنل فروشنده زیر فشار نمی‌رود. */
function startPoll() {
  stopPoll();
  S.poll = setInterval(async () => {
    if (document.hidden) return;
    if (S.tick > 0) { S.tick--; paintTimer(S.tick); }
    if (S.tick % 5 !== 0 && S.tick > 0) return;
    const r = await api('num_state', S.live ? { order: S.live.order } : {});
    if (r && r.ok) {
      if (typeof r.balance === 'number') setBal(r.balance);
      if (r.active === false) { paintLive(null); return; }
      if (r.num) {
        const was = S.live ? S.live.status : '';
        if (r.num.status !== was || r.num.code !== (S.live ? S.live.code : '')) {
          paintLive(r.num);
          if (r.num.status === 'done') { buzz('heavy'); toast(U('code_ttl', 'کد رسید'), 'ok'); }
          else if (r.num.status === 'expired') toast(U('expired', ''), 'bad');
          else if (r.num.status === 'cancel') toast(U('canceled', ''), 'bad');
        } else { S.live = r.num; S.tick = r.num.left; }
      }
    }
  }, 1000);
}
function stopPoll() { if (S.poll) { clearInterval(S.poll); S.poll = 0; } }
function paintTimer(sec) {
  const lab = document.querySelector('.timer b'), fill = document.querySelector('.bar i');
  if (lab) lab.textContent = mmss(sec);
  if (fill && S.live) fill.style.width = Math.max(0, Math.min(100, (sec / Math.max(1, S.live.wait)) * 100)) + '%';
}

/* ── شیت ─────────────────────────────────── */
function sheet(build) {
  const box = $('#sheetIn'); box.textContent = '';
  build(box);
  $('#mask').classList.add('on'); $('#sheet').classList.add('on');
}
function closeSheet() { $('#mask').classList.remove('on'); $('#sheet').classList.remove('on'); }
$('#mask').addEventListener('click', closeSheet);

function askBuy(it) {
  sheet(box => {
    const h = document.createElement('h2'); h.textContent = it.name; box.appendChild(h);
    if (it.desc) { const p = document.createElement('p'); p.className = 'lead'; p.textContent = it.desc; box.appendChild(p); }

    const kv = (k, v) => {
      const r = document.createElement('div'); r.className = 'kv';
      const a = document.createElement('span'); a.textContent = k;
      const b = document.createElement('b'); b.textContent = v;
      r.appendChild(a); r.appendChild(b); box.appendChild(r);
    };
    const c = catOf(it.cat);
    if (c) kv(U('cats_ttl', 'کشور'), (c.emoji || '') + ' ' + c.name);
    kv(U('buy', 'قیمت'), fmt(it.price) + ' ' + (B.currency || ''));
    kv(U('balance', 'اعتبار'), fmt(S.me ? S.me.balance : 0) + ' ' + (B.currency || ''));

    const sp = document.createElement('div'); sp.style.height = '16px'; box.appendChild(sp);
    const btn = document.createElement('button');
    btn.className = 'btn'; btn.textContent = U('buy', 'گرفتن شماره');
    btn.addEventListener('click', () => doBuy(it, btn));
    box.appendChild(btn);
  });
}

async function doBuy(it, btn) {
  btn.disabled = true; btn.textContent = U('sending', '…');
  const r = await api('order', { item: it.id, qty: 1, seen_price: it.price });
  btn.disabled = false; btn.textContent = U('buy', 'گرفتن شماره');

  if (r.ok) {
    closeSheet(); buzz('medium');
    if (typeof r.balance === 'number') setBal(r.balance);
    if (r.num) {
      // 📋 بعد از خرید مستقیم می‌بریمش سرِ «سفارش‌های من» — همان‌جا که
      //    شماره را کپی می‌کند و بعد «دریافت کد» را می‌زند.
      paintLive(r.num); loadOrders(); go('orders');
      toast(U('bought', 'شماره گرفته شد'), 'ok');
    }
    else { toast(r.message || U('done', ''), 'ok'); loadMe(); }
    return;
  }
  if (r.error === 'no_balance') { closeSheet(); askTopup(r.need || 0); return; }
  if (r.error === 'price_changed' && typeof r.price === 'number') {
    it.price = r.price; closeSheet(); paintGrid(); paintHot();
  }
  toast(r.message || 'انجام نشد', 'bad');
}

function askCancel(n) {
  sheet(box => {
    const h = document.createElement('h2'); h.textContent = U('cancel_do', 'لغو'); box.appendChild(h);
    const p = document.createElement('p'); p.className = 'lead';
    p.textContent = U('cancel_ask', 'شماره لغو شود و مبلغ برگردد؟'); box.appendChild(p);
    const btn = document.createElement('button');
    btn.className = 'btn danger'; btn.textContent = U('cancel_do', 'لغو');
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      const r = await api('num_cancel', { order: n.order });
      btn.disabled = false;
      if (r.ok) {
        closeSheet(); paintLive(null);
        if (typeof r.balance === 'number') setBal(r.balance);
        toast(U('canceled', 'لغو شد'), 'ok'); loadMe();
      } else toast(r.message || 'انجام نشد', 'bad');
    });
    box.appendChild(btn);
    const sp = document.createElement('div'); sp.style.height = '8px'; box.appendChild(sp);
    const no = document.createElement('button');
    no.className = 'btn ghost'; no.textContent = U('close', 'بستن');
    no.addEventListener('click', closeSheet); box.appendChild(no);
  });
}

function askTopup(need) {
  sheet(box => {
    const h = document.createElement('h2'); h.textContent = U('topup', 'افزایش اعتبار'); box.appendChild(h);
    const p = document.createElement('p'); p.className = 'lead';
    p.textContent = need > 0
      ? U('low_bal', 'موجودی کافی نیست') + ' — ' + fmt(need) + ' ' + (B.currency || '')
      : U('topup_hint', '');
    box.appendChild(p);

    if (!B.topup || !B.topup.on) {
      const w = document.createElement('p'); w.className = 'lead';
      w.textContent = U('topup_hint', ''); box.appendChild(w);
      return;
    }
    const f = document.createElement('div'); f.className = 'fld';
    const l = document.createElement('label'); l.textContent = U('topup_amt', 'مبلغ'); f.appendChild(l);
    const inp = document.createElement('input');
    inp.type = 'tel'; inp.inputMode = 'numeric';
    inp.value = String(Math.max(Number(B.topup.min) || 0, Math.ceil((need || 0) / 1000) * 1000));
    f.appendChild(inp); box.appendChild(f);

    const btn = document.createElement('button');
    btn.className = 'btn'; btn.textContent = U('topup_do', 'ثبت درخواست');
    btn.addEventListener('click', async () => {
      const amt = Number(String(inp.value).replace(/\D+/g, ''));
      if (!amt) { toast('مبلغ را وارد کنید', 'bad'); return; }
      btn.disabled = true;
      const r = await api('topup', { amount: amt });
      btn.disabled = false;
      if (r.ok) { closeSheet(); toast(r.message || '', 'ok'); if (TG) setTimeout(() => TG.close(), 1400); }
      else toast(r.message || 'انجام نشد', 'bad');
    });
    box.appendChild(btn);
  });
}

/* ── سفارش‌ها ────────────────────────────── */
/* ── 📋 سفارش‌های من ─────────────────────────
   هر سفارش یک کارت است: اسم سرویس، شماره با دکمه‌ی کپی، و بسته به
   حالتش یک دکمه — «دریافت کد» وقتی منتظریم، یا خودِ کد وقتی رسیده. */
function paintOrders(list) {
  const box = $('#orders'); box.textContent = '';
  if (!list || !list.length) {
    const e = document.createElement('div'); e.className = 'empty';
    const i = document.createElement('i'); i.textContent = '🧾'; e.appendChild(i);
    const s = document.createElement('span'); s.textContent = U('no_orders', ''); e.appendChild(s);
    box.appendChild(e); return;
  }
  const frag = document.createDocumentFragment();
  list.forEach(o => frag.appendChild(orderCard(o)));
  box.appendChild(frag);
}

const ST = {
  waiting:  ['w', () => U('wait_ttl', 'منتظر پیامک')],
  done:     ['d', () => U('got_code', 'کد رسید')],
  cancel:   ['x', () => U('canceled', 'لغو شد')],
  expired:  ['x', () => U('expired', 'مهلت تمام شد')],
  buying:   ['w', () => U('sending', '…')],
  repeating:['w', () => U('sending', '…')],
};

function orderCard(o) {
  const card = document.createElement('div');
  card.className = 'ocard';
  card.dataset.order = o.order;

  // سربرگ: اسم سرویس + وضعیت
  const head = document.createElement('div'); head.className = 'ohead';
  const nm = document.createElement('b'); nm.textContent = o.name || '☎️'; head.appendChild(nm);
  const [cls, lab] = ST[o.status] || ['', () => o.status];
  const pill = document.createElement('span'); pill.className = 'pill ' + cls; pill.textContent = lab();
  head.appendChild(pill);
  card.appendChild(head);

  // شماره — همیشه دیده شود و همیشه قابل کپی
  card.appendChild(bigRow(U('num_ttl', 'شماره'), o.phone, U('copy_num', 'کپی'), false));

  // کد، اگر آمده
  if (o.code) card.appendChild(bigRow(U('code_ttl', 'کد'), o.code, U('copy_code', 'کپی کد'), true));

  // شمارش معکوس، تا وقتی منتظریم
  if (o.status === 'waiting' && o.left > 0) {
    const t = document.createElement('div'); t.className = 'timer';
    const bar = document.createElement('div'); bar.className = 'bar';
    const fill = document.createElement('i');
    fill.style.width = Math.max(0, Math.min(100, (o.left / Math.max(1, o.wait)) * 100)) + '%';
    bar.appendChild(fill); t.appendChild(bar);
    const lb = document.createElement('b'); lb.className = 'oleft'; lb.textContent = mmss(o.left);
    t.appendChild(lb);
    card.appendChild(t);
  }

  // 🔑 دکمه‌ی اصلی — همان چیزی که کاربر بعد از کپی کردنِ شماره می‌زند
  if (o.can_get) {
    const get = document.createElement('button');
    get.className = 'btn sm';
    get.textContent = U('get_code', 'دریافت کد');
    get.addEventListener('click', () => askCode(o.order, get, card));
    card.appendChild(get);

    const cx = document.createElement('button');
    cx.className = 'btn ghost sm'; cx.style.marginTop = '8px';
    cx.textContent = U('cancel_do', 'لغو');
    cx.addEventListener('click', () => askCancel(o));
    card.appendChild(cx);
  } else if (o.repeat) {
    const rp = document.createElement('button');
    rp.className = 'btn sm'; rp.textContent = U('repeat', 'کد مجدد');
    rp.addEventListener('click', async () => {
      rp.disabled = true; buzz();
      const r = await api('num_repeat', { order: o.order });
      rp.disabled = false;
      if (r.ok) { toast(U('repeat_ok', ''), 'ok'); loadOrders(); }
      else toast(r.message || 'انجام نشد', 'bad');
    });
    card.appendChild(rp);
  }
  return card;
}

/* «دریافت کد» — همان‌جا از پنل می‌پرسد و جواب را در همان کارت می‌نشاند */
async function askCode(order, btn, card) {
  const was = btn.textContent;
  btn.disabled = true; btn.textContent = U('sending', '…'); buzz();
  const r = await api('num_code', { order });
  btn.disabled = false; btn.textContent = was;

  if (!r.ok) { toast(r.message || 'انجام نشد', 'bad'); return; }
  const n = r.num || {};
  if (n.code) {
    buzz('heavy'); toast(U('code_ttl', 'کد رسید'), 'ok');
    card.replaceWith(orderCard(Object.assign({}, n, { name: n.name, can_get: 0 })));
    return;
  }
  if (n.status && n.status !== 'waiting') { toast(U('expired', ''), 'bad'); loadOrders(); return; }
  // هنوز نیامده — بگو چقدر وقت مانده، نه یک «نشد»ِ خالی
  toast(U('not_yet', 'هنوز نرسیده') + ' · ' + mmss(n.left || 0), '');
  const lb = card.querySelector('.oleft');
  if (lb) lb.textContent = mmss(n.left || 0);
}

async function loadOrders() {
  const r = await api('num_orders');
  if (!r.ok) { if (r.message) toast(r.message, 'bad'); return; }
  if (typeof r.balance === 'number') setBal(r.balance);
  paintOrders(r.list || []);
}

/* ── ناوبری ──────────────────────────────── */
function go(name) {
  S.page = name;
  $$('.page').forEach(p => p.classList.toggle('on', p.id === 'p-' + name));
  $$('#nav button').forEach(b => b.setAttribute('aria-selected', b.dataset.go === name ? 'true' : 'false'));
  window.scrollTo({ top: 0, behavior: 'smooth' });
  if (name === 'orders') loadOrders();
}
$$('#nav button').forEach(b => b.addEventListener('click', () => { buzz(); go(b.dataset.go); }));

/* ── راه‌اندازی ──────────────────────────── */
function setBal(v) {
  if (S.me) S.me.balance = v;
  $('#balV').textContent = fmt(v);
  $('#meBal').textContent = fmt(v) + ' ' + (B.currency || '');
}

async function loadMe() {
  const r = await api('me');
  if (!r.ok) { if (r.message) toast(r.message, 'bad'); return; }
  S.me = r;
  setBal(r.balance || 0);
  $('#uname').textContent = r.name || '';
  $('#uhandle').textContent = r.uname ? '@' + r.uname : '';
  $('#meName').textContent = r.name || '';
  $('#meId').textContent = String(r.uid || '');
  if (r.photo) {
    const img = document.createElement('img');
    img.className = 'ava'; img.src = r.photo; img.alt = '';
    img.onerror = () => {};
    $('#ava').replaceWith(img); img.id = 'ava';
  }
  paintOrders(r.orders || []);
}

async function loadLive() {
  const r = await api('num_state', {});
  if (r && r.ok && r.active && r.num) paintLive(r.num);
}

function boot() {
  $('#hTitle').textContent = B.title || '';
  $('#hHero').textContent = B.hero || B.sub || '';
  $('#meNote').textContent = B.note || '';
  $('#balK').textContent = U('balance', 'اعتبار');
  $('#kBal').textContent = U('balance', 'اعتبار');
  $('#sHot').textContent = U('hot', '');
  $('#sOrders').textContent = U('orders_ttl', '');
  $('#oHint').textContent = U('orders_hint', '');
  $('#topupBtn').textContent = U('topup_btn', '＋ شارژ');
  $$('#nav button')[0].querySelector('span').textContent = U('nav_home', 'خانه');
  $$('#nav button')[1].querySelector('span').textContent = U('nav_shop', 'شماره‌ها');
  $$('#nav button')[2].querySelector('span').textContent = U('nav_orders', 'سفارش‌ها');
  $$('#nav button')[3].querySelector('span').textContent = U('nav_me', 'حساب من');

  // 🔎 جستجو — با کاتالوگِ چند ده‌تایی، تنها راهِ رسیدن به یک کشورِ خاص
  const q = $('#q');
  q.placeholder = U('search', 'جستجو…');
  let qT = 0;
  q.addEventListener('input', () => {
    clearTimeout(qT);
    qT = setTimeout(() => { S.q = q.value; paintGrid(); }, 160);
  });
  $('#more').addEventListener('click', () => { buzz(); paintGrid(false); });

  $('#balBtn').addEventListener('click', () => { buzz(); askTopup(0); });
  $('#topupBtn').addEventListener('click', () => { buzz(); askTopup(0); });

  paintCats(); paintGrid(); paintHot();
  loadMe(); loadLive(); loadOrders();

  // برگشت به صفحه بعد از قفل شدن گوشی — وضعیت را تازه کن
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && S.live && S.live.status === 'waiting') loadLive();
  });
}
boot();
</script>
</body>
</html>
HTML;
}
